<?php
/**
 * Admin page template.
 *
 * @var string $json_url
 */
?>
<div class="wrap tdp-wrap">
	<h1><?php esc_html_e( 'JSON Thumbnail Downloader Pro', 'thumbnail-downloader-pro' ); ?></h1>
	<p><?php esc_html_e( 'Fetch thumbnail URLs from JSON and process them in reliable Action Scheduler batches.', 'thumbnail-downloader-pro' ); ?></p>

	<div class="tdp-card">
		<label for="tdp-json-url"><strong><?php esc_html_e( 'JSON URL', 'thumbnail-downloader-pro' ); ?></strong></label>
		<input type="url" class="regular-text code" id="tdp-json-url" value="<?php echo esc_attr( $json_url ); ?>" placeholder="https://example.com/feed.json" />
		<p class="description"><?php esc_html_e( 'Supported formats: string arrays, and objects with url/image/thumbnail keys.', 'thumbnail-downloader-pro' ); ?></p>
		<div class="tdp-actions">
			<button class="button button-primary" id="tdp-start"><?php esc_html_e( 'Start Download', 'thumbnail-downloader-pro' ); ?></button>
			<button class="button" id="tdp-cancel"><?php esc_html_e( 'Cancel', 'thumbnail-downloader-pro' ); ?></button>
			<button class="button" id="tdp-clear-logs"><?php esc_html_e( 'Clear Logs', 'thumbnail-downloader-pro' ); ?></button>
			<button class="button" id="tdp-clear-errors"><?php esc_html_e( 'Clear Errors', 'thumbnail-downloader-pro' ); ?></button>
		</div>
	</div>

	<div class="tdp-card">
		<h2><?php esc_html_e( 'Progress', 'thumbnail-downloader-pro' ); ?></h2>
		<div class="tdp-progress-wrap">
			<div class="tdp-progress-bar" id="tdp-progress-bar"></div>
			<span id="tdp-progress-text">0%</span>
		</div>
		<p id="tdp-stats">Total: 0 | Completed: 0 | Failed: 0 | Remaining: 0</p>

		<div class="tdp-as-meta">
			<div><strong><?php esc_html_e( 'Current Batch:', 'thumbnail-downloader-pro' ); ?></strong> <span id="tdp-current-batch">#0</span></div>
			<div><strong><?php esc_html_e( 'Pending Actions:', 'thumbnail-downloader-pro' ); ?></strong> <span id="tdp-pending-actions">0</span></div>
			<div><strong><?php esc_html_e( 'Running Actions:', 'thumbnail-downloader-pro' ); ?></strong> <span id="tdp-running-actions">0</span></div>
		</div>
	</div>

	<div class="tdp-card">
		<h2><?php esc_html_e( 'Recent Errors', 'thumbnail-downloader-pro' ); ?></h2>
		<div id="tdp-errors"></div>
	</div>

	<div class="tdp-card">
		<h2><?php esc_html_e( 'Live Log Viewer', 'thumbnail-downloader-pro' ); ?></h2>
		<div id="tdp-log-viewer" class="tdp-log-viewer" role="log" aria-live="polite"></div>
	</div>
</div>
