<?php
/**
 * Plugin Name: JSON Thumbnail Downloader Pro
 * Description: Download thumbnails from a JSON feed in Action Scheduler batches and register them in the WordPress Media Library.
 * Version: 2.0.0
 * Author: Development Team
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: thumbnail-downloader-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Thumbnail_Downloader_Pro {
	const VERSION = '2.0.0';
	const BATCH_SIZE = 5;

	const OPTION_JSON_URL       = 'tdp_json_url';
	const OPTION_PROCESSING     = 'tdp_processing';
	const OPTION_QUEUE          = 'tdp_queue';
	const OPTION_PROCESSED      = 'tdp_processed';
	const OPTION_ERRORS         = 'tdp_errors';
	const OPTION_LOGS           = 'tdp_logs';
	const OPTION_CURRENT_BATCH  = 'tdp_current_batch';
	const OPTION_CURRENT_RUN_ID = 'tdp_current_run_id';

	const ACTION_HOOK  = 'tdp_process_batch';
	const ACTION_GROUP = 'thumbnail_downloader';

	/**
	 * Constructor.
	 */
	public function __construct() {
		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivate' ) );

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		add_action( 'wp_ajax_tdp_start_download', array( $this, 'ajax_start_download' ) );
		add_action( 'wp_ajax_tdp_get_status', array( $this, 'ajax_get_status' ) );
		add_action( 'wp_ajax_tdp_cancel_download', array( $this, 'ajax_cancel_download' ) );
		add_action( 'wp_ajax_tdp_get_logs', array( $this, 'ajax_get_logs' ) );
		add_action( 'wp_ajax_tdp_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_tdp_clear_errors', array( $this, 'ajax_clear_errors' ) );

		add_action( self::ACTION_HOOK, array( $this, 'process_batch' ) );

		add_action( 'admin_notices', array( $this, 'maybe_show_action_scheduler_notice' ) );
	}

	/**
	 * Activation callback.
	 */
	public static function activate() {
		add_option( self::OPTION_JSON_URL, '' );
		add_option( self::OPTION_PROCESSING, false );
		add_option( self::OPTION_QUEUE, array() );
		add_option( self::OPTION_PROCESSED, array() );
		add_option( self::OPTION_ERRORS, array() );
		add_option( self::OPTION_LOGS, array() );
		add_option( self::OPTION_CURRENT_BATCH, 0 );
		add_option( self::OPTION_CURRENT_RUN_ID, '' );
	}

	/**
	 * Deactivation callback.
	 */
	public static function deactivate() {
		update_option( self::OPTION_PROCESSING, false );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::ACTION_HOOK, array(), self::ACTION_GROUP );
		}
	}

	/**
	 * Display admin notice if Action Scheduler is unavailable.
	 */
	public function maybe_show_action_scheduler_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( $this->is_action_scheduler_available() ) {
			return;
		}
		?>
		<div class="notice notice-error"><p>
			<?php esc_html_e( 'Thumbnail Downloader Pro: Action Scheduler is required. Please install WooCommerce or another plugin that includes Action Scheduler.', 'thumbnail-downloader-pro' ); ?>
		</p></div>
		<?php
	}

	/**
	 * Add admin menu page.
	 */
	public function admin_menu() {
		add_menu_page(
			esc_html__( 'Thumbnail Downloader Pro', 'thumbnail-downloader-pro' ),
			esc_html__( 'Thumbnail Downloader Pro', 'thumbnail-downloader-pro' ),
			'manage_options',
			'thumbnail-downloader-pro',
			array( $this, 'admin_page' ),
			'dashicons-format-image',
			58
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix Admin page hook suffix.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'toplevel_page_thumbnail-downloader-pro' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'tdp-admin-style',
			plugin_dir_url( __FILE__ ) . 'assets/admin-style.css',
			array(),
			self::VERSION
		);

		wp_enqueue_script(
			'tdp-admin-script',
			plugin_dir_url( __FILE__ ) . 'assets/admin-script.js',
			array( 'jquery' ),
			self::VERSION,
			true
		);

		wp_localize_script(
			'tdp-admin-script',
			'tdpData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'tdp_nonce' ),
			)
		);
	}

	/**
	 * Render admin page.
	 */
	public function admin_page() {
		$json_url = get_option( self::OPTION_JSON_URL, '' );
		include plugin_dir_path( __FILE__ ) . 'views/admin-page.php';
	}

	/**
	 * Start a download job via AJAX.
	 */
	public function ajax_start_download() {
		check_ajax_referer( 'tdp_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		if ( ! $this->is_action_scheduler_available() ) {
			wp_send_json_error( 'Action Scheduler is not available.' );
		}

		$json_url = isset( $_POST['json_url'] ) ? esc_url_raw( wp_unslash( $_POST['json_url'] ) ) : '';
		if ( empty( $json_url ) ) {
			wp_send_json_error( 'Please provide a valid JSON URL.' );
		}

		$this->log( 'INFO', 'Starting download job with JSON URL', array( 'url' => $json_url ) );

		$response = wp_remote_get(
			$json_url,
			array(
				'timeout'   => 60,
				'sslverify' => false,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log( 'ERROR', 'JSON fetch failed', array( 'error' => $response->get_error_message() ) );
			wp_send_json_error( 'Failed to fetch JSON: ' . $response->get_error_message() );
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$this->log( 'INFO', 'JSON fetch HTTP response code', array( 'http_code' => $http_code ) );

		if ( 200 !== $http_code ) {
			$this->log( 'ERROR', 'JSON fetch returned non-200', array( 'http_code' => $http_code ) );
			wp_send_json_error( 'JSON endpoint returned HTTP ' . $http_code );
		}

		$body      = wp_remote_retrieve_body( $response );
		$json_size = strlen( (string) $body );
		$this->log( 'INFO', 'JSON payload size', array( 'bytes' => $json_size ) );

		$data = json_decode( $body, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			$message = json_last_error_msg();
			$this->log( 'ERROR', 'JSON parse failed', array( 'error' => $message ) );
			wp_send_json_error( 'Invalid JSON: ' . $message );
		}

		$this->log( 'SUCCESS', 'JSON parsed successfully' );

		$urls = $this->extract_urls( $data );
		if ( empty( $urls ) ) {
			$this->log( 'ERROR', 'No supported image URLs found in JSON.' );
			wp_send_json_error( 'No supported image URLs found in JSON.' );
		}

		$sample = array_slice(
			array_map(
				function( $item ) {
					return is_array( $item ) ? ( $item['url'] ?? '' ) : (string) $item;
				},
				$urls
			),
			0,
			5
		);
		$this->log( 'INFO', 'Extracted URLs', array( 'count' => count( $urls ), 'sample' => $sample ) );

		$run_id = wp_generate_uuid4();

		update_option( self::OPTION_JSON_URL, $json_url );
		update_option( self::OPTION_QUEUE, array_values( $urls ) );
		update_option( self::OPTION_PROCESSED, array() );
		update_option( self::OPTION_ERRORS, array() );
		update_option( self::OPTION_CURRENT_BATCH, 0 );
		update_option( self::OPTION_CURRENT_RUN_ID, $run_id );
		update_option( self::OPTION_PROCESSING, true );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::ACTION_HOOK, array(), self::ACTION_GROUP );
		}

		// CRITICAL: Use as_schedule_single_action() instead of as_enqueue_async_action().
		$action_id = as_schedule_single_action(
			time() + 5,
			self::ACTION_HOOK,
			array(
				'batch_number' => 1,
				'run_id'       => $run_id,
			),
			self::ACTION_GROUP
		);

		$this->log( 'INFO', 'First batch scheduled', array( 'action_id' => $action_id, 'run_id' => $run_id ) );

		wp_send_json_success(
			array(
				'total'     => count( $urls ),
				'action_id' => $action_id,
			)
		);
	}

	/**
	 * Return status details via AJAX.
	 */
	public function ajax_get_status() {
		check_ajax_referer( 'tdp_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$queue      = get_option( self::OPTION_QUEUE, array() );
		$processed  = get_option( self::OPTION_PROCESSED, array() );
		$errors     = get_option( self::OPTION_ERRORS, array() );
		$is_running = (bool) get_option( self::OPTION_PROCESSING, false );

		$total     = count( $queue ) + count( $processed ) + count( $errors );
		$completed = count( $processed );
		$failed    = count( $errors );
		$remaining = count( $queue );
		$percent   = $total > 0 ? (int) round( ( ( $completed + $failed ) / $total ) * 100 ) : 0;

		wp_send_json_success(
			array(
				'is_processing'   => $is_running,
				'total'           => $total,
				'completed'       => $completed,
				'failed'          => $failed,
				'remaining'       => $remaining,
				'percentage'      => min( 100, max( 0, $percent ) ),
				'current_batch'   => (int) get_option( self::OPTION_CURRENT_BATCH, 0 ),
				'pending_actions' => $this->count_actions_by_status( 'pending' ),
				'running_actions' => $this->count_actions_by_status( 'running' ),
				'errors'          => array_slice( array_reverse( $errors ), 0, 5 ),
			)
		);
	}

	/**
	 * Cancel currently running job.
	 */
	public function ajax_cancel_download() {
		check_ajax_referer( 'tdp_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		update_option( self::OPTION_PROCESSING, false );
		update_option( self::OPTION_QUEUE, array() );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::ACTION_HOOK, array(), self::ACTION_GROUP );
		}

		$this->log( 'INFO', 'Processing cancelled by user.' );

		wp_send_json_success( array( 'message' => 'Processing cancelled.' ) );
	}

	/**
	 * Return logs via AJAX.
	 */
	public function ajax_get_logs() {
		check_ajax_referer( 'tdp_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$logs = get_option( self::OPTION_LOGS, array() );
		$logs = array_slice( array_reverse( $logs ), 0, 100 );

		wp_send_json_success(
			array(
				'logs' => $logs,
			)
		);
	}

	/**
	 * Clear logs via AJAX.
	 */
	public function ajax_clear_logs() {
		check_ajax_referer( 'tdp_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		update_option( self::OPTION_LOGS, array() );
		wp_send_json_success();
	}

	/**
	 * Clear errors via AJAX.
	 */
	public function ajax_clear_errors() {
		check_ajax_referer( 'tdp_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		update_option( self::OPTION_ERRORS, array() );
		wp_send_json_success();
	}

	/**
	 * Process one queue batch from Action Scheduler.
	 *
	 * @param array $args Batch args.
	 */
	public function process_batch( $args = array() ) {
		$batch_number = isset( $args['batch_number'] ) ? (int) $args['batch_number'] : 1;
		$run_id       = isset( $args['run_id'] ) ? sanitize_text_field( (string) $args['run_id'] ) : '';

		$this->log( 'INFO', '----------------------------------------' );
		$this->log( 'INFO', 'Batch started', array( 'batch_number' => $batch_number, 'run_id' => $run_id ) );

		if ( ! get_option( self::OPTION_PROCESSING, false ) ) {
			$this->log( 'INFO', 'Batch exited: processing disabled.' );
			return;
		}

		$current_run_id = (string) get_option( self::OPTION_CURRENT_RUN_ID, '' );
		if ( ! empty( $run_id ) && $current_run_id !== $run_id ) {
			$this->log( 'INFO', 'Batch exited: stale run id.', array( 'expected' => $current_run_id, 'received' => $run_id ) );
			return;
		}

		update_option( self::OPTION_CURRENT_BATCH, $batch_number );

		$queue = get_option( self::OPTION_QUEUE, array() );
		$this->log( 'INFO', 'Queue size at batch start', array( 'queue_count' => count( $queue ) ) );

		if ( empty( $queue ) ) {
			update_option( self::OPTION_PROCESSING, false );
			$this->log( 'SUCCESS', 'Processing complete. Queue empty.' );
			return;
		}

		$batch_items = array_slice( $queue, 0, self::BATCH_SIZE );
		$remaining   = array_slice( $queue, self::BATCH_SIZE );

		$successes = 0;
		$failures  = 0;
		$offset    = ( $batch_number - 1 ) * self::BATCH_SIZE;

		foreach ( $batch_items as $index => $item ) {
			$item_number = $offset + $index + 1;
			$this->log( 'INFO', 'Processing item', array( 'item_number' => $item_number ) );
			if ( $this->download_thumbnail( $item, $item_number ) ) {
				++$successes;
			} else {
				++$failures;
			}
		}

		update_option( self::OPTION_QUEUE, $remaining );
		$this->log(
			'INFO',
			'Batch complete',
			array(
				'batch_number' => $batch_number,
				'successes'    => $successes,
				'failures'     => $failures,
				'remaining'    => count( $remaining ),
			)
		);

		if ( ! empty( $remaining ) && get_option( self::OPTION_PROCESSING, false ) ) {
			$next_batch = $batch_number + 1;
			$action_id  = as_schedule_single_action(
				time() + 5,
				self::ACTION_HOOK,
				array(
					'batch_number' => $next_batch,
					'run_id'       => $current_run_id,
				),
				self::ACTION_GROUP
			);
			$this->log( 'INFO', 'Next batch scheduled', array( 'batch_number' => $next_batch, 'action_id' => $action_id ) );
		} else {
			update_option( self::OPTION_PROCESSING, false );
			$this->log( 'SUCCESS', 'All batches completed.' );
		}
	}

	/**
	 * Download one thumbnail and register attachment.
	 *
	 * @param mixed $item Item payload.
	 * @param int   $item_number Item number.
	 * @return bool
	 */
	private function download_thumbnail( $item, $item_number ) {
		$url      = is_array( $item ) && isset( $item['url'] ) ? esc_url_raw( $item['url'] ) : esc_url_raw( (string) $item );
		$filename = is_array( $item ) && ! empty( $item['filename'] ) ? sanitize_file_name( $item['filename'] ) : '';

		if ( empty( $url ) ) {
			$this->append_error( 'Empty URL provided.', array( 'item_number' => $item_number, 'item' => $item ) );
			return false;
		}

		if ( empty( $filename ) ) {
			$path     = (string) parse_url( $url, PHP_URL_PATH );
			$filename = basename( $path );
		}

		if ( empty( $filename ) || false === strpos( $filename, '.' ) ) {
			$filename = 'thumbnail_' . md5( $url ) . '.jpg';
		}

		$filename = sanitize_file_name( $filename );

		$this->log( 'INFO', 'Downloading URL', array( 'item_number' => $item_number, 'url' => $url, 'filename' => $filename ) );

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			$this->append_error( 'Upload directory error', array( 'error' => $upload_dir['error'] ) );
			return false;
		}

		$file_path = trailingslashit( $upload_dir['path'] ) . $filename;
		$file_url  = trailingslashit( $upload_dir['url'] ) . $filename;

		// CRITICAL: Prevent duplicate downloads by checking existing files first.
		if ( file_exists( $file_path ) ) {
			$existing_id = $this->find_attachment_id_by_filename( basename( $file_path ) );
			if ( ! $existing_id ) {
				// CRITICAL: Existing files must be registered with wp_insert_attachment() to appear in Media Library.
				$attach_id = $this->register_existing_file( $file_path, $filename, $file_url );
				if ( $attach_id ) {
					$this->append_processed(
						array(
							'url'           => $url,
							'filename'      => $filename,
							'attachment_id' => $attach_id,
							'status'        => 'registered_existing',
						)
					);
					$this->log( 'SUCCESS', 'Existing file registered to Media Library', array( 'attachment_id' => $attach_id ) );
					return true;
				}
				$this->append_error( 'Existing file could not be registered', array( 'file' => $file_path ) );
				return false;
			}

			$this->append_processed(
				array(
					'url'           => $url,
					'filename'      => $filename,
					'attachment_id' => (int) $existing_id,
					'status'        => 'already_exists',
				)
			);
			$this->log( 'INFO', 'File already exists in Media Library', array( 'attachment_id' => (int) $existing_id ) );
			return true;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 60,
				'stream'    => true,
				'filename'  => $file_path,
				'sslverify' => false,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->append_error(
				'Download failed',
				array(
					'url'   => $url,
					'error' => $response->get_error_message(),
				)
			);
			return false;
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$this->log( 'INFO', 'Thumbnail HTTP response', array( 'url' => $url, 'http_code' => $http_code ) );
		if ( 200 !== $http_code ) {
			if ( file_exists( $file_path ) ) {
				wp_delete_file( $file_path );
			}
			$this->append_error( 'HTTP error while downloading', array( 'url' => $url, 'http_code' => $http_code ) );
			return false;
		}

		if ( ! file_exists( $file_path ) ) {
			$this->append_error( 'File was not created', array( 'path' => $file_path, 'url' => $url ) );
			return false;
		}

		$file_size = (int) filesize( $file_path );
		$attach_id = $this->register_existing_file( $file_path, $filename, $file_url );
		if ( ! $attach_id ) {
			$this->append_error( 'Failed to register downloaded file.', array( 'path' => $file_path, 'url' => $url ) );
			return false;
		}

		$this->append_processed(
			array(
				'url'           => $url,
				'filename'      => $filename,
				'attachment_id' => $attach_id,
				'file_size'     => $file_size,
				'status'        => 'downloaded',
			)
		);

		$this->log(
			'SUCCESS',
			'Downloaded and registered',
			array(
				'attachment_id' => $attach_id,
				'size'          => size_format( $file_size ),
			)
		);

		return true;
	}

	/**
	 * Register an existing file as media attachment.
	 *
	 * @param string $file_path Full filesystem path.
	 * @param string $filename Filename.
	 * @param string $file_url Public URL.
	 * @return int
	 */
	private function register_existing_file( $file_path, $filename, $file_url ) {
		$filetype   = wp_check_filetype( $file_path );
		$attachment = array(
			'guid'           => esc_url_raw( $file_url ),
			'post_mime_type' => ! empty( $filetype['type'] ) ? $filetype['type'] : 'image/jpeg',
			'post_title'     => sanitize_text_field( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attach_id = wp_insert_attachment( $attachment, $file_path );
		if ( is_wp_error( $attach_id ) || ! $attach_id ) {
			$this->log( 'ERROR', 'wp_insert_attachment failed', array( 'error' => is_wp_error( $attach_id ) ? $attach_id->get_error_message() : 'Unknown error' ) );
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attach_id, $file_path );
		wp_update_attachment_metadata( $attach_id, $metadata );

		return (int) $attach_id;
	}

	/**
	 * Extract URLs from supported JSON formats.
	 *
	 * @param mixed $data Decoded JSON data.
	 * @return array
	 */
	private function extract_urls( $data ) {
		$results = array();
		$seen    = array();

		if ( ! is_array( $data ) ) {
			return array();
		}

		foreach ( $data as $item ) {
			$url      = '';
			$filename = '';

			if ( is_string( $item ) ) {
				$url = $item;
			} elseif ( is_array( $item ) ) {
				if ( ! empty( $item['url'] ) ) {
					$url = $item['url'];
				} elseif ( ! empty( $item['thumbnail'] ) ) {
					$url = $item['thumbnail'];
				} elseif ( ! empty( $item['image'] ) ) {
					$url = $item['image'];
				}

				if ( ! empty( $item['filename'] ) ) {
					$filename = (string) $item['filename'];
				}
			}

			$url = esc_url_raw( (string) $url );
			if ( empty( $url ) ) {
				continue;
			}

			$dedupe_key = md5( $url . '|' . $filename );
			if ( isset( $seen[ $dedupe_key ] ) ) {
				continue;
			}
			$seen[ $dedupe_key ] = true;

			if ( ! empty( $filename ) ) {
				$results[] = array(
					'url'      => $url,
					'filename' => sanitize_file_name( $filename ),
				);
			} else {
				$results[] = array( 'url' => $url );
			}
		}

		return $results;
	}

	/**
	 * Log details to option storage and PHP error_log.
	 *
	 * @param string     $level Log level.
	 * @param string     $message Message.
	 * @param array|null $data Optional context.
	 */
	private function log( $level, $message, $data = null ) {
		$logs = get_option( self::OPTION_LOGS, array() );

		$entry = array(
			'time'      => current_time( 'mysql' ),
			'timestamp' => time(),
			'level'     => sanitize_text_field( $level ),
			'message'   => sanitize_text_field( $message ),
			'data'      => $data,
		);

		$logs[] = $entry;
		if ( count( $logs ) > 500 ) {
			$logs = array_slice( $logs, -500 );
		}

		update_option( self::OPTION_LOGS, $logs );

		$line = sprintf( '[Thumbnail Downloader Pro][%s] %s', $entry['level'], $entry['message'] );
		if ( null !== $data ) {
			$line .= ' | Data: ' . wp_json_encode( $data );
		}

		error_log( $line );
	}

	/**
	 * Append processed item.
	 *
	 * @param array $item Processed item entry.
	 */
	private function append_processed( $item ) {
		$processed   = get_option( self::OPTION_PROCESSED, array() );
		$processed[] = $item;
		update_option( self::OPTION_PROCESSED, $processed );
	}

	/**
	 * Append error entry and log it.
	 *
	 * @param string $message Error message.
	 * @param array  $context Error context.
	 */
	private function append_error( $message, $context = array() ) {
		$errors   = get_option( self::OPTION_ERRORS, array() );
		$errors[] = array(
			'time'    => current_time( 'mysql' ),
			'message' => sanitize_text_field( $message ),
			'data'    => $context,
		);
		update_option( self::OPTION_ERRORS, $errors );
		$this->log( 'ERROR', $message, $context );
	}

	/**
	 * Determine if Action Scheduler functions are available.
	 *
	 * @return bool
	 */
	private function is_action_scheduler_available() {
		return function_exists( 'as_enqueue_async_action' ) && function_exists( 'as_schedule_single_action' );
	}

	/**
	 * Count actions by status.
	 *
	 * @param string $status Action Scheduler status.
	 * @return int
	 */
	private function count_actions_by_status( $status ) {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return 0;
		}

		$actions = as_get_scheduled_actions(
			array(
				'hook'     => self::ACTION_HOOK,
				'group'    => self::ACTION_GROUP,
				'status'   => $status,
				'per_page' => 1,
			),
			'ARRAY_A'
		);

		if ( isset( $actions['total'] ) ) {
			return (int) $actions['total'];
		}

		if ( is_array( $actions ) ) {
			return count( $actions );
		}

		return 0;
	}

	/**
	 * Find existing attachment by filename.
	 *
	 * @param string $filename Filename.
	 * @return int
	 */
	private function find_attachment_id_by_filename( $filename ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE guid LIKE %s AND post_type = 'attachment' LIMIT 1",
			'%' . $wpdb->esc_like( $filename )
		);

		return (int) $wpdb->get_var( $query );
	}
}

new Thumbnail_Downloader_Pro();
