(function ($) {
	'use strict';

	let statusInterval = null;
	let logInterval = null;

	const ajaxConfig = window.tdpAjax || window.tdpData || null;

	function hasAjaxConfig() {
		return !!(ajaxConfig && ajaxConfig.ajaxUrl && ajaxConfig.nonce);
	}

	function startPolling() {
		if (statusInterval) {
			clearInterval(statusInterval);
		}
		if (logInterval) {
			clearInterval(logInterval);
		}

		statusInterval = setInterval(updateStatus, 3000);
		logInterval = setInterval(loadLogs, 5000);
		updateStatus();
		loadLogs();
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

	function ajaxRequest(action, extraData = {}) {
		if (!hasAjaxConfig()) {
			return $.Deferred().reject('Missing AJAX config').promise();
		}

		return $.post(ajaxConfig.ajaxUrl, {
			action,
			nonce: ajaxConfig.nonce,
			...extraData
		});
	}

	function updateStatus() {
		ajaxRequest('tdp_get_status')
			.done(function (response) {
				if (!response.success) {
					return;
				}

				const data = response.data;
				$('#tdp-progress-bar').css('width', data.percentage + '%');
				$('#tdp-progress-text').text(data.percentage + '%');
				$('#tdp-stats').text(
					'Total: ' + data.total +
					' | Completed: ' + data.completed +
					' | Failed: ' + data.failed +
					' | Remaining: ' + data.remaining
				);

				$('#tdp-current-batch').text('#' + data.current_batch);
				$('#tdp-pending-actions').text(data.pending_actions);
				$('#tdp-running-actions').text(data.running_actions);

				const $errors = $('#tdp-errors');
				$errors.empty();
				if (Array.isArray(data.errors) && data.errors.length > 0) {
					data.errors.forEach(function (error) {
						$errors.append('<div class="tdp-log-entry tdp-log-error"><strong>' + escapeHtml(error.time || '') + '</strong> — ' + escapeHtml(error.message || 'Unknown error') + '</div>');
					});
				} else {
					$errors.append('<p>No recent errors.</p>');
				}

				if (!data.is_processing) {
					stopPolling();
					if (data.remaining === 0 && data.total > 0) {
						setTimeout(function () {
							window.location.reload();
						}, 2000);
					}
				}
			});
	}

	function loadLogs() {
		ajaxRequest('tdp_get_logs')
			.done(function (response) {
				if (!response.success) {
					return;
				}

				const logs = response.data.logs || [];
				const $viewer = $('#tdp-log-viewer');
				$viewer.empty();

				logs.slice().reverse().forEach(function (entry) {
					const level = (entry.level || 'INFO').toLowerCase();
					const row = $('<div class="tdp-log-entry"></div>');
					row.addClass('tdp-log-' + level);
					const text = '[' + (entry.time || '') + '] [' + (entry.level || '') + '] ' + (entry.message || '');
					row.text(text);
					$viewer.append(row);
				});

				$viewer.scrollTop($viewer[0].scrollHeight);
			});
	}

	function escapeHtml(string) {
		return String(string)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	$('#tdp-start').on('click', function (e) {
		e.preventDefault();
		const jsonUrl = $('#tdp-json-url').val();
		ajaxRequest('tdp_start_download', { json_url: jsonUrl })
			.done(function (response) {
				if (!response.success) {
					window.alert(response.data || 'Failed to start download.');
					return;
				}
				startPolling();
			})
			.fail(function () {
				window.alert('Request failed while starting download.');
			});
	});

	$('#tdp-cancel').on('click', function (e) {
		e.preventDefault();
		ajaxRequest('tdp_cancel_download').done(function () {
			updateStatus();
			loadLogs();
		});
	});

	$('#tdp-clear-logs').on('click', function (e) {
		e.preventDefault();
		ajaxRequest('tdp_clear_logs').done(function () {
			loadLogs();
		});
	});

	$('#tdp-clear-errors').on('click', function (e) {
		e.preventDefault();
		ajaxRequest('tdp_clear_errors').done(function () {
			updateStatus();
		});
	});

	$(document).ready(function () {
		if (!hasAjaxConfig()) {
			window.console.error('Thumbnail Downloader Pro: AJAX configuration is missing.');
			return;
		}
		startPolling();
	});
})(jQuery);
