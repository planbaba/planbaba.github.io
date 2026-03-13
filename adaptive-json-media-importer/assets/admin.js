(function ($) {
	'use strict';

	let statusInterval = null;
	let logInterval = null;
	let seenActiveRun = false;
	let autoReloaded = false;

	function request(action, payload = {}) {
		return $.post(ajmiData.ajaxUrl, {
			action,
			nonce: ajmiData.nonce,
			...payload
		});
	}

	function refreshStatus() {
		request('ajmi_status').done(function (resp) {
			if (!resp.success) {
				return;
			}

			const d = resp.data;
			if (d.is_processing) {
				seenActiveRun = true;
			}

			$('#ajmi-progress-bar').css('width', d.percentage + '%');
			$('#ajmi-progress-text').text(d.percentage + '%');
			$('#ajmi-stats').text('Total: ' + d.total + ' | Completed: ' + d.completed + ' | Failed: ' + d.failed + ' | Remaining: ' + d.remaining);
			$('#ajmi-batch').text('#' + d.current_batch);
			$('#ajmi-pending').text(d.pending_actions);
			$('#ajmi-running').text(d.running_actions);

			const c = d.cron_health || {};
			$('#ajmi-cron-health').text('next queue run: ' + (c.next_run || 'unknown') + ' | DISABLE_WP_CRON=' + (c.disable_wp_cron ? 'true' : 'false'));

			const $errors = $('#ajmi-errors');
			$errors.empty();
			if (Array.isArray(d.errors) && d.errors.length) {
				d.errors.forEach(function (e) {
					$errors.append('<div class="ajmi-error-row"><strong>' + escapeHtml(e.time || '') + '</strong> - ' + escapeHtml(e.message || 'Error') + '</div>');
				});
			} else {
				$errors.html('<p>No recent errors.</p>');
			}

			if (!d.is_processing) {
				stopPolling();
				if (seenActiveRun && !autoReloaded && d.remaining === 0 && d.total > 0) {
					autoReloaded = true;
					setTimeout(function () {
						window.location.reload();
					}, 1500);
				}
			}
		});
	}

	function refreshLogs() {
		request('ajmi_logs').done(function (resp) {
			if (!resp.success) {
				return;
			}
			const logs = resp.data.logs || [];
			const $logs = $('#ajmi-logs');
			$logs.empty();
			logs.slice().reverse().forEach(function (entry) {
				const level = (entry.level || 'INFO').toLowerCase();
				const txt = '[' + (entry.time || '') + '] [' + (entry.level || '') + '] ' + (entry.message || '');
				$logs.append('<div class="ajmi-log-row ajmi-' + level + '">' + escapeHtml(txt) + '</div>');
			});
			if ($logs[0]) {
				$logs.scrollTop($logs[0].scrollHeight);
			}
		});
	}

	function startPolling() {
		stopPolling();
		statusInterval = setInterval(refreshStatus, 3000);
		logInterval = setInterval(refreshLogs, 5000);
		refreshStatus();
		refreshLogs();
	}

	function stopPolling() {
		if (statusInterval) {
			clearInterval(statusInterval);
			statusInterval = null;
		}
		if (logInterval) {
			clearInterval(logInterval);
			logInterval = null;
		}
	}

	function escapeHtml(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	$(document).on('click', '#ajmi-start', function (e) {
		e.preventDefault();
		const url = $('#ajmi-json-url').val();
		seenActiveRun = false;
		autoReloaded = false;
		request('ajmi_start', { json_url: url }).done(function (resp) {
			if (!resp.success) {
				window.alert(resp.data || 'Failed to start import');
				return;
			}
			startPolling();
		});
	});

	$(document).on('click', '#ajmi-cancel', function (e) {
		e.preventDefault();
		request('ajmi_cancel').done(function () {
			refreshStatus();
			refreshLogs();
		});
	});

	$(document).on('click', '#ajmi-clear-logs', function (e) {
		e.preventDefault();
		request('ajmi_clear_logs').done(function () {
			refreshLogs();
		});
	});

	$(document).on('click', '#ajmi-clear-errors', function (e) {
		e.preventDefault();
		request('ajmi_clear_errors').done(function () {
			refreshStatus();
		});
	});

	$(function () {
		startPolling();
	});
})(jQuery);
