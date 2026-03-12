(function () {
	'use strict';

	const state = {
		auto: true,
		search: '',
		level: 'all',
		interval: null
	};

	function request(action, payload) {
		return fetch(tdlvData.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: new URLSearchParams({ action, nonce: tdlvData.nonce, ...payload })
		}).then((r) => r.json());
	}

	function esc(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function renderLogs(logs) {
		const panel = document.getElementById('tdlv-log-panel');
		if (!Array.isArray(logs) || logs.length === 0) {
			panel.innerHTML = '<div class="tdlv-empty">No logs found for current filters.</div>';
			return;
		}

		panel.innerHTML = logs.map((entry) => {
			const level = (entry.level || 'INFO').toUpperCase();
			const css = level === 'ERROR' ? 'tdlv-error' : (level === 'SUCCESS' ? 'tdlv-success' : 'tdlv-info');
			const message = esc(entry.message || '');
			const time = esc(entry.time || '');
			const data = entry.data ? '<pre>' + esc(JSON.stringify(entry.data, null, 2)) + '</pre>' : '';
			return '<article class="tdlv-item ' + css + '"><header><span class="tdlv-level">' + esc(level) + '</span><time>' + time + '</time></header><div class="tdlv-msg">' + message + '</div>' + data + '</article>';
		}).join('');
	}

	function refreshStats() {
		request('tdlv_get_stats', {}).then((res) => {
			if (!res.success) {
				return;
			}
			document.getElementById('tdlv-total').textContent = res.data.total ?? 0;
			document.getElementById('tdlv-success').textContent = res.data.success ?? 0;
			document.getElementById('tdlv-error').textContent = res.data.error ?? 0;
			document.getElementById('tdlv-info').textContent = res.data.info ?? 0;
			document.getElementById('tdlv-last').textContent = res.data.last ?? '—';
		});
	}

	function refreshLogs() {
		request('tdlv_get_logs', {
			search: state.search,
			level: state.level,
			limit: 200
		}).then((res) => {
			if (!res.success) {
				return;
			}
			renderLogs(res.data.logs || []);
		});
	}

	function refreshAll() {
		refreshStats();
		refreshLogs();
	}

	function setAutoRefresh(enabled) {
		state.auto = enabled;
		if (state.interval) {
			clearInterval(state.interval);
			state.interval = null;
		}
		if (enabled) {
			state.interval = setInterval(refreshAll, 10000);
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.getElementById('tdlv-refresh').addEventListener('click', function () {
			refreshAll();
		});

		document.getElementById('tdlv-search').addEventListener('input', function (event) {
			state.search = event.target.value || '';
			refreshLogs();
		});

		document.getElementById('tdlv-level').addEventListener('change', function (event) {
			state.level = event.target.value || 'all';
			refreshLogs();
		});

		document.getElementById('tdlv-auto').addEventListener('change', function (event) {
			setAutoRefresh(!!event.target.checked);
		});

		refreshAll();
		setAutoRefresh(true);
	});
})();
