<?php
/**
 * @var array $state
 */
?>
<div class="wrap ajmi-wrap">
	<h1><?php esc_html_e( 'Adaptive JSON Media Importer', 'ajmi' ); ?></h1>
	<p><?php esc_html_e( 'Advanced JSON image importer with resilient Action Scheduler batches and cron health visibility.', 'ajmi' ); ?></p>

	<div class="ajmi-card">
		<label for="ajmi-json-url"><strong><?php esc_html_e( 'JSON URL', 'ajmi' ); ?></strong></label>
		<input id="ajmi-json-url" type="url" class="large-text code" value="<?php echo esc_attr( $state['json_url'] ); ?>" placeholder="https://example.com/images.json" />
		<div class="ajmi-btns">
			<button class="button button-primary" id="ajmi-start"><?php esc_html_e( 'Start Import', 'ajmi' ); ?></button>
			<button class="button" id="ajmi-cancel"><?php esc_html_e( 'Cancel', 'ajmi' ); ?></button>
			<button class="button" id="ajmi-clear-logs"><?php esc_html_e( 'Clear Logs', 'ajmi' ); ?></button>
			<button class="button" id="ajmi-clear-errors"><?php esc_html_e( 'Clear Errors', 'ajmi' ); ?></button>
		</div>
	</div>

	<div class="ajmi-card">
		<h2><?php esc_html_e( 'Progress', 'ajmi' ); ?></h2>
		<div class="ajmi-progress"><div id="ajmi-progress-bar"></div><span id="ajmi-progress-text">0%</span></div>
		<p id="ajmi-stats">Total: 0 | Completed: 0 | Failed: 0 | Remaining: 0</p>
		<div class="ajmi-meta">
			<div><strong>Batch:</strong> <span id="ajmi-batch">#0</span></div>
			<div><strong>Pending:</strong> <span id="ajmi-pending">0</span></div>
			<div><strong>Running:</strong> <span id="ajmi-running">0</span></div>
		</div>
		<div class="ajmi-cron"><strong>Cron Health:</strong> <span id="ajmi-cron-health">—</span></div>
	</div>

	<div class="ajmi-card">
		<h2><?php esc_html_e( 'Recent Errors', 'ajmi' ); ?></h2>
		<div id="ajmi-errors"></div>
	</div>

	<div class="ajmi-card">
		<h2><?php esc_html_e( 'Live Logs', 'ajmi' ); ?></h2>
		<div id="ajmi-logs" class="ajmi-log-viewer" role="log" aria-live="polite"></div>
	</div>
</div>
