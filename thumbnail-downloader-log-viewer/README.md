# Thumbnail Downloader Log Viewer (Dark Mode)

A separate WordPress plugin that provides a dark-mode, real-time log dashboard for **JSON Thumbnail Downloader Pro**.

## What it does

- Reads existing log/error options from the downloader plugin:
  - `tdp_logs`
  - `tdp_errors`
- Provides a standalone admin screen with:
  - Live auto-refresh
  - Level filtering (`SUCCESS`, `ERROR`, `INFO`)
  - Search across messages and payload data
  - Log card rendering in dark mode

## Installation

1. Copy `thumbnail-downloader-log-viewer` into `wp-content/plugins/`.
2. Activate **Thumbnail Downloader Log Viewer (Dark Mode)**.
3. Open **TDP Log Viewer** in wp-admin.

## Notes

- This plugin is read-only for logs (it does not modify downloader behavior).
- It is designed specifically to monitor the log structures produced by JSON Thumbnail Downloader Pro.
