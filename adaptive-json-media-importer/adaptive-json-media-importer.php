<?php
/**
 * Plugin Name: Adaptive JSON Media Importer
 * Description: Reliable Action Scheduler based JSON image importer with batch processing, Media Library registration, cron health checks, and deep logs.
 * Version: 1.0.0
 * Author: Development Team
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: ajmi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AJMI_Plugin {
	const VERSION = '1.0.0';
	const BATCH_SIZE = 5;
	const LOG_LIMIT = 1000;
	const ACTION_HOOK = 'ajmi_process_batch';
	const ACTION_GROUP = 'ajmi_media_import';
	const NONCE = 'ajmi_nonce';

	const OPTION_STATE = 'ajmi_state';
	const OPTION_LOGS = 'ajmi_logs';
	const OPTION_ERRORS = 'ajmi_errors';
	const OPTION_HISTORY = 'ajmi_history';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivate' ) );

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_dependency_notice' ) );

		add_action( 'wp_ajax_ajmi_start', array( $this, 'ajax_start' ) );
		add_action( 'wp_ajax_ajmi_status', array( $this, 'ajax_status' ) );
		add_action( 'wp_ajax_ajmi_cancel', array( $this, 'ajax_cancel' ) );
		add_action( 'wp_ajax_ajmi_logs', array( $this, 'ajax_logs' ) );
		add_action( 'wp_ajax_ajmi_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_ajmi_clear_errors', array( $this, 'ajax_clear_errors' ) );

		add_action( self::ACTION_HOOK, array( $this, 'process_batch' ) );
	}

	public static function activate() {
		add_option( self::OPTION_STATE, self::default_state() );
		add_option( self::OPTION_LOGS, array() );
		add_option( self::OPTION_ERRORS, array() );
		add_option( self::OPTION_HISTORY, array() );
	}

	public static function deactivate() {
		$state = get_option( self::OPTION_STATE, self::default_state() );
		$state['is_processing'] = false;
		update_option( self::OPTION_STATE, $state, false );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::ACTION_HOOK, array(), self::ACTION_GROUP );
		}
	}

	private static function default_state() {
		return array(
			'job_id' => '',
			'json_url' => '',
			'queue' => array(),
			'processed' => array(),
			'failed' => array(),
			'is_processing' => false,
			'current_batch' => 0,
			'created_at' => '',
			'last_activity' => '',
		);
	}

	public function maybe_show_dependency_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( $this->is_as_available() ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Adaptive JSON Media Importer requires Action Scheduler (WooCommerce or standalone Action Scheduler plugin).', 'ajmi' ) . '</p></div>';
	}

	public function admin_menu() {
		add_menu_page(
			esc_html__( 'Adaptive JSON Media Importer', 'ajmi' ),
			esc_html__( 'AJ Media Importer', 'ajmi' ),
			'manage_options',
			'ajmi',
			array( $this, 'render_admin_page' ),
			'dashicons-download',
			56
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_ajmi' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'ajmi-admin', plugin_dir_url( __FILE__ ) . 'assets/admin.css', array(), self::VERSION );
		wp_enqueue_script( 'ajmi-admin', plugin_dir_url( __FILE__ ) . 'assets/admin.js', array( 'jquery' ), self::VERSION, true );
		wp_localize_script(
			'ajmi-admin',
			'ajmiData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( self::NONCE ),
			)
		);
	}

	public function render_admin_page() {
		$state = get_option( self::OPTION_STATE, self::default_state() );
		include plugin_dir_path( __FILE__ ) . 'views/admin-page.php';
	}

	public function ajax_start() {
		$this->guard();
		if ( ! $this->is_as_available() ) {
			wp_send_json_error( 'Action Scheduler is not available.' );
		}

		$json_url = isset( $_POST['json_url'] ) ? esc_url_raw( wp_unslash( $_POST['json_url'] ) ) : '';
		if ( empty( $json_url ) ) {
			wp_send_json_error( 'Please enter a valid JSON URL.' );
		}

		$this->log( 'INFO', 'Starting import', array( 'url' => $json_url ) );
		$response = wp_remote_get( $json_url, array( 'timeout' => 60, 'sslverify' => false ) );
		if ( is_wp_error( $response ) ) {
			$this->append_error( 'JSON request failed', array( 'error' => $response->get_error_message() ) );
			wp_send_json_error( $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$this->log( 'INFO', 'JSON endpoint response', array( 'code' => $code ) );
		if ( 200 !== $code ) {
			$this->append_error( 'JSON endpoint HTTP error', array( 'code' => $code ) );
			wp_send_json_error( 'HTTP ' . $code );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			$this->append_error( 'JSON parse error', array( 'message' => json_last_error_msg() ) );
			wp_send_json_error( 'JSON parse error: ' . json_last_error_msg() );
		}

		$items = $this->extract_items( $data );
		if ( empty( $items ) ) {
			$this->append_error( 'No image URLs found in JSON' );
			wp_send_json_error( 'No image URLs found in JSON.' );
		}

		$job_id = wp_generate_uuid4();
		$state = self::default_state();
		$state['job_id'] = $job_id;
		$state['json_url'] = $json_url;
		$state['queue'] = array_values( $items );
		$state['is_processing'] = true;
		$state['created_at'] = current_time( 'mysql' );
		$state['last_activity'] = current_time( 'mysql' );
		update_option( self::OPTION_STATE, $state, false );
		update_option( self::OPTION_ERRORS, array(), false );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::ACTION_HOOK, array(), self::ACTION_GROUP );
		}

		$action_id = as_schedule_single_action(
			time() + 5,
			self::ACTION_HOOK,
			array( $job_id, 1 ),
			self::ACTION_GROUP
		);

		$this->kick_cron();
		$this->log( 'INFO', 'First batch scheduled', array( 'job_id' => $job_id, 'action_id' => $action_id, 'count' => count( $items ) ) );
		wp_send_json_success( array( 'job_id' => $job_id, 'total' => count( $items ), 'action_id' => $action_id ) );
	}

	public function ajax_status() {
		$this->guard();
		$state = get_option( self::OPTION_STATE, self::default_state() );
		$errors = get_option( self::OPTION_ERRORS, array() );

		$total = count( $state['queue'] ) + count( $state['processed'] ) + count( $state['failed'] );
		$done = count( $state['processed'] ) + count( $state['failed'] );
		$percentage = $total > 0 ? (int) round( ( $done / $total ) * 100 ) : 0;

		wp_send_json_success(
			array(
				'job_id' => $state['job_id'],
				'is_processing' => (bool) $state['is_processing'],
				'total' => $total,
				'completed' => count( $state['processed'] ),
				'failed' => count( $state['failed'] ),
				'remaining' => count( $state['queue'] ),
				'percentage' => min( 100, max( 0, $percentage ) ),
				'current_batch' => (int) $state['current_batch'],
				'pending_actions' => $this->count_actions( 'pending' ),
				'running_actions' => $this->count_actions( 'in-progress' ),
				'cron_health' => $this->cron_health_payload(),
				'errors' => array_slice( array_reverse( $errors ), 0, 5 ),
			)
		);
	}

	public function ajax_cancel() {
		$this->guard();
		$state = get_option( self::OPTION_STATE, self::default_state() );
		$state['is_processing'] = false;
		$state['queue'] = array();
		$state['last_activity'] = current_time( 'mysql' );
		update_option( self::OPTION_STATE, $state, false );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::ACTION_HOOK, array(), self::ACTION_GROUP );
		}
		$this->log( 'INFO', 'Job cancelled', array( 'job_id' => $state['job_id'] ) );
		wp_send_json_success();
	}

	public function ajax_logs() {
		$this->guard();
		$logs = get_option( self::OPTION_LOGS, array() );
		$logs = array_slice( array_reverse( $logs ), 0, 200 );
		wp_send_json_success( array( 'logs' => $logs ) );
	}

	public function ajax_clear_logs() {
		$this->guard();
		update_option( self::OPTION_LOGS, array(), false );
		wp_send_json_success();
	}

	public function ajax_clear_errors() {
		$this->guard();
		update_option( self::OPTION_ERRORS, array(), false );
		wp_send_json_success();
	}

	public function process_batch( $arg1 = null, $arg2 = null ) {
		$job_id = '';
		$batch_number = 1;

		if ( is_array( $arg1 ) ) {
			$job_id = isset( $arg1['job_id'] ) ? sanitize_text_field( (string) $arg1['job_id'] ) : '';
			$batch_number = isset( $arg1['batch_number'] ) ? (int) $arg1['batch_number'] : 1;
		} else {
			$job_id = sanitize_text_field( (string) $arg1 );
			$batch_number = null !== $arg2 ? (int) $arg2 : 1;
		}
		$state = get_option( self::OPTION_STATE, self::default_state() );

		if ( empty( $job_id ) || $state['job_id'] !== $job_id ) {
			$this->log( 'INFO', 'Ignoring stale batch', array( 'incoming_job_id' => $job_id, 'active_job_id' => $state['job_id'] ) );
			return;
		}

		if ( empty( $state['is_processing'] ) ) {
			$this->log( 'INFO', 'Batch skipped because processing flag is false', array( 'job_id' => $job_id ) );
			return;
		}

		$state['current_batch'] = $batch_number;
		$state['last_activity'] = current_time( 'mysql' );
		update_option( self::OPTION_STATE, $state, false );

		$queue = is_array( $state['queue'] ) ? $state['queue'] : array();
		$this->log( 'INFO', 'Batch started', array( 'job_id' => $job_id, 'batch' => $batch_number, 'queue_count' => count( $queue ) ) );

		if ( empty( $queue ) ) {
			$state['is_processing'] = false;
			$state['last_activity'] = current_time( 'mysql' );
			update_option( self::OPTION_STATE, $state, false );
			$this->record_history( $state );
			$this->log( 'SUCCESS', 'Import completed (queue empty)', array( 'job_id' => $job_id ) );
			return;
		}

		$batch = array_slice( $queue, 0, self::BATCH_SIZE );
		$remaining = array_slice( $queue, self::BATCH_SIZE );
		$succ = 0;
		$fail = 0;

		foreach ( $batch as $offset => $item ) {
			$item_num = ( ( $batch_number - 1 ) * self::BATCH_SIZE ) + $offset + 1;
			if ( $this->download_item( $item, $item_num ) ) {
				++$succ;
			} else {
				++$fail;
			}
		}

		$state = get_option( self::OPTION_STATE, self::default_state() );
		$state['queue'] = $remaining;
		$state['last_activity'] = current_time( 'mysql' );
		update_option( self::OPTION_STATE, $state, false );

		$this->log( 'INFO', 'Batch finished', array( 'job_id' => $job_id, 'batch' => $batch_number, 'success' => $succ, 'failed' => $fail, 'remaining' => count( $remaining ) ) );

		if ( ! empty( $remaining ) && ! empty( $state['is_processing'] ) ) {
			$next_id = as_schedule_single_action( time() + 5, self::ACTION_HOOK, array( $job_id, $batch_number + 1 ), self::ACTION_GROUP );
			$this->kick_cron();
			$this->log( 'INFO', 'Next batch scheduled', array( 'job_id' => $job_id, 'action_id' => $next_id ) );
		} else {
			$state['is_processing'] = false;
			update_option( self::OPTION_STATE, $state, false );
			$this->record_history( $state );
			$this->log( 'SUCCESS', 'Import completed', array( 'job_id' => $job_id ) );
		}
	}

	private function download_item( $item, $item_number ) {
		$url = is_array( $item ) ? ( $item['url'] ?? '' ) : (string) $item;
		$filename = is_array( $item ) ? ( $item['filename'] ?? '' ) : '';
		$url = esc_url_raw( $url );

		if ( empty( $url ) ) {
			$this->append_error( 'Invalid/empty URL', array( 'item_number' => $item_number ) );
			$this->append_failed_item( array( 'item_number' => $item_number, 'url' => $url ) );
			return false;
		}

		if ( empty( $filename ) ) {
			$path = (string) parse_url( $url, PHP_URL_PATH );
			$filename = basename( $path );
		}
		if ( empty( $filename ) || false === strpos( $filename, '.' ) ) {
			$filename = 'ajmi_' . md5( $url ) . '.jpg';
		}
		$filename = sanitize_file_name( $filename );

		$this->log( 'INFO', 'Processing item', array( 'item_number' => $item_number, 'url' => $url, 'filename' => $filename ) );

		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			$this->append_error( 'Upload dir error', array( 'error' => $upload['error'] ) );
			$this->append_failed_item( array( 'item_number' => $item_number, 'url' => $url ) );
			return false;
		}

		$file_path = trailingslashit( $upload['path'] ) . $filename;
		$relative_path = ltrim( str_replace( trailingslashit( $upload['basedir'] ), '', $file_path ), '/' );
		$file_url = trailingslashit( $upload['url'] ) . $filename;

		if ( file_exists( $file_path ) ) {
			$existing = $this->find_attachment_id( $relative_path, $filename );
			if ( $existing ) {
				$this->append_processed_item( array( 'url' => $url, 'filename' => $filename, 'attachment_id' => $existing, 'status' => 'already_exists' ) );
				$this->log( 'INFO', 'File already exists', array( 'attachment_id' => $existing, 'filename' => $filename ) );
				return true;
			}

			$registered = $this->register_file( $file_path, $file_url, $filename, $relative_path );
			if ( $registered ) {
				$this->append_processed_item( array( 'url' => $url, 'filename' => $filename, 'attachment_id' => $registered, 'status' => 'registered_existing' ) );
				$this->log( 'SUCCESS', 'Existing file registered', array( 'attachment_id' => $registered ) );
				return true;
			}

			$this->append_error( 'Existing file registration failed', array( 'path' => $file_path ) );
			$this->append_failed_item( array( 'item_number' => $item_number, 'url' => $url ) );
			return false;
		}

		$response = wp_remote_get( $url, array( 'timeout' => 60, 'stream' => true, 'filename' => $file_path, 'sslverify' => false ) );
		if ( is_wp_error( $response ) ) {
			$this->append_error( 'Download failed', array( 'url' => $url, 'error' => $response->get_error_message() ) );
			$this->append_failed_item( array( 'item_number' => $item_number, 'url' => $url ) );
			return false;
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $http_code ) {
			if ( file_exists( $file_path ) ) {
				wp_delete_file( $file_path );
			}
			$this->append_error( 'Download HTTP error', array( 'url' => $url, 'code' => $http_code ) );
			$this->append_failed_item( array( 'item_number' => $item_number, 'url' => $url ) );
			return false;
		}

		if ( ! file_exists( $file_path ) ) {
			$this->append_error( 'Download did not create file', array( 'path' => $file_path ) );
			$this->append_failed_item( array( 'item_number' => $item_number, 'url' => $url ) );
			return false;
		}

		$attachment_id = $this->register_file( $file_path, $file_url, $filename, $relative_path );
		if ( ! $attachment_id ) {
			$this->append_error( 'Attachment registration failed', array( 'file' => $file_path ) );
			$this->append_failed_item( array( 'item_number' => $item_number, 'url' => $url ) );
			return false;
		}

		$this->append_processed_item( array( 'url' => $url, 'filename' => $filename, 'attachment_id' => $attachment_id, 'status' => 'downloaded' ) );
		$this->log( 'SUCCESS', 'Downloaded and registered', array( 'attachment_id' => $attachment_id, 'file_size' => size_format( (int) filesize( $file_path ) ) ) );
		return true;
	}

	private function register_file( $file_path, $file_url, $filename, $relative_path ) {
		$filetype = wp_check_filetype( $file_path );
		$attachment = array(
			'guid' => esc_url_raw( $file_url ),
			'post_mime_type' => ! empty( $filetype['type'] ) ? $filetype['type'] : 'image/jpeg',
			'post_title' => sanitize_text_field( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_content' => '',
			'post_status' => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $file_path );
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return 0;
		}

		update_post_meta( $attachment_id, '_wp_attached_file', $relative_path );
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$meta = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $meta );
		return (int) $attachment_id;
	}

	private function find_attachment_id( $relative_path, $filename ) {
		global $wpdb;
		$by_meta = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
				$relative_path
			)
		);
		if ( $by_meta ) {
			return (int) $by_meta;
		}

		$by_guid = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND guid LIKE %s LIMIT 1",
				'%' . $wpdb->esc_like( $filename )
			)
		);
		return (int) $by_guid;
	}

	private function extract_items( $data ) {
		if ( ! is_array( $data ) ) {
			return array();
		}
		$items = array();
		$seen = array();
		foreach ( $data as $row ) {
			$url = '';
			$filename = '';
			if ( is_string( $row ) ) {
				$url = $row;
			} elseif ( is_array( $row ) ) {
				if ( ! empty( $row['url'] ) ) {
					$url = $row['url'];
				} elseif ( ! empty( $row['thumbnail'] ) ) {
					$url = $row['thumbnail'];
				} elseif ( ! empty( $row['image'] ) ) {
					$url = $row['image'];
				}
				if ( ! empty( $row['filename'] ) ) {
					$filename = sanitize_file_name( (string) $row['filename'] );
				}
			}
			$url = esc_url_raw( (string) $url );
			if ( empty( $url ) ) {
				continue;
			}
			$key = md5( $url . '|' . $filename );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$item = array( 'url' => $url );
			if ( ! empty( $filename ) ) {
				$item['filename'] = $filename;
			}
			$items[] = $item;
		}
		return $items;
	}

	private function cron_health_payload() {
		$next = wp_next_scheduled( 'action_scheduler_run_queue' );
		return array(
			'next_run' => $next ? gmdate( 'Y-m-d H:i:s', $next ) : 'not_scheduled',
			'disable_wp_cron' => (bool) ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ),
			'now' => gmdate( 'Y-m-d H:i:s' ),
		);
	}

	private function count_actions( $status ) {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return 0;
		}
		$actions = as_get_scheduled_actions(
			array(
				'hook' => self::ACTION_HOOK,
				'group' => self::ACTION_GROUP,
				'status' => $status,
				'per_page' => 100,
			),
			'ARRAY_A'
		);
		return is_array( $actions ) ? count( $actions ) : 0;
	}

	private function kick_cron() {
		wp_remote_post(
			site_url( '/wp-cron.php?doing_wp_cron=' . rawurlencode( (string) microtime( true ) ) ),
			array(
				'timeout' => 0.01,
				'blocking' => false,
				'sslverify' => false,
			)
		);
	}

	private function append_processed_item( $item ) {
		$state = get_option( self::OPTION_STATE, self::default_state() );
		$state['processed'][] = $item;
		$state['last_activity'] = current_time( 'mysql' );
		update_option( self::OPTION_STATE, $state, false );
	}

	private function append_failed_item( $item ) {
		$state = get_option( self::OPTION_STATE, self::default_state() );
		$state['failed'][] = $item;
		$state['last_activity'] = current_time( 'mysql' );
		update_option( self::OPTION_STATE, $state, false );
	}

	private function append_error( $message, $data = array() ) {
		$errors = get_option( self::OPTION_ERRORS, array() );
		$errors[] = array(
			'time' => current_time( 'mysql' ),
			'message' => sanitize_text_field( $message ),
			'data' => $data,
		);
		if ( count( $errors ) > self::LOG_LIMIT ) {
			$errors = array_slice( $errors, -self::LOG_LIMIT );
		}
		update_option( self::OPTION_ERRORS, $errors, false );
		$this->log( 'ERROR', $message, $data );
	}

	private function log( $level, $message, $data = null ) {
		$logs = get_option( self::OPTION_LOGS, array() );
		$entry = array(
			'time' => current_time( 'mysql' ),
			'timestamp' => time(),
			'level' => sanitize_text_field( strtoupper( $level ) ),
			'message' => sanitize_text_field( $message ),
			'data' => $data,
		);
		$logs[] = $entry;
		if ( count( $logs ) > self::LOG_LIMIT ) {
			$logs = array_slice( $logs, -self::LOG_LIMIT );
		}
		update_option( self::OPTION_LOGS, $logs, false );
		$line = '[AJMI][' . $entry['level'] . '] ' . $entry['message'];
		if ( null !== $data ) {
			$line .= ' | ' . wp_json_encode( $data );
		}
		error_log( $line );
	}

	private function record_history( $state ) {
		$history = get_option( self::OPTION_HISTORY, array() );
		$history[] = array(
			'job_id' => $state['job_id'],
			'json_url' => $state['json_url'],
			'created_at' => $state['created_at'],
			'finished_at' => current_time( 'mysql' ),
			'processed' => count( $state['processed'] ),
			'failed' => count( $state['failed'] ),
		);
		if ( count( $history ) > 100 ) {
			$history = array_slice( $history, -100 );
		}
		update_option( self::OPTION_HISTORY, $history, false );
	}

	private function is_as_available() {
		return function_exists( 'as_schedule_single_action' ) && function_exists( 'as_get_scheduled_actions' );
	}

	private function guard() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}
	}
}

AJMI_Plugin::instance();
