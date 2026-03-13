# JSON Thumbnail Downloader Pro

JSON Thumbnail Downloader Pro downloads image URLs from a JSON feed in reliable batches using Action Scheduler and registers each file in the WordPress Media Library.

## Installation

1. Copy `thumbnail-downloader-pro` into `wp-content/plugins/`.
2. Ensure WooCommerce (or another Action Scheduler provider) is active.
3. Activate **JSON Thumbnail Downloader Pro**.
4. Open **Thumbnail Downloader Pro** from wp-admin.

## Why scheduled actions (not async)

This plugin intentionally uses:

```php
as_schedule_single_action( time() + 5, 'tdp_process_batch', $args, 'thumbnail_downloader' );
```

and does **not** use `as_enqueue_async_action()`.

Scheduled actions are timestamped and picked up by `action_scheduler_run_queue`, which is more reliable in real environments and avoids stuck background tasks.

## JSON formats supported

```json
["https://example.com/a.jpg", "https://example.com/b.jpg"]
```

```json
[{"url":"https://example.com/a.jpg"}, {"url":"https://example.com/b.jpg"}]
```

```json
[{"url":"https://example.com/a.jpg", "filename":"my-file.jpg"}]
```

```json
[{"thumbnail":"https://example.com/a.jpg"}, {"image":"https://example.com/b.jpg"}]
```

## WP-Cron verification

1. Visit **Tools → Scheduled Actions**.
2. Look for hook `tdp_process_batch` in group `thumbnail_downloader`.
3. Ensure statuses move from `Pending` → `Running` → `Complete`.

If actions remain pending, verify WP-Cron:

- Visit site frontend to trigger cron.
- Confirm `DISABLE_WP_CRON` value.

## System cron setup (if `DISABLE_WP_CRON` is true)

Use server cron to run every minute:

```bash
*/1 * * * * php /path/to/public_html/wp-cron.php >/dev/null 2>&1
```

or with HTTP:

```bash
*/1 * * * * curl -s https://example.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

## Troubleshooting stuck tasks

1. Check **Tools → Scheduled Actions** for failed actions and last error.
2. Verify Action Scheduler tables exist.
3. Ensure loopback requests are not blocked.
4. Confirm cron is firing.
5. Use **Cancel** in plugin UI, then restart run.

## Manually trigger tasks

- In **Tools → Scheduled Actions**, filter by hook `tdp_process_batch`.
- Use **Run** for pending actions.
- Or trigger WP-Cron from CLI: `wp cron event run --due-now`.

## Logging and debugging

- Live logs shown in plugin admin page.
- Last 500 logs retained in option storage.
- Logs also sent to PHP `error_log` with `[Thumbnail Downloader Pro]` prefix.
- Works well with Error Log Viewer WP by filtering for `Thumbnail Downloader Pro`.

## Advanced Cron Manager integration

Advanced Cron Manager can be used to inspect `action_scheduler_run_queue`. Manual “Run now” should immediately process due batches.

## Performance tips

- Default batch size is 5 images per run to reduce memory spikes.
- Keep JSON lists clean and deduplicated where possible.
- Use optimized image sources/CDNs.
- Keep uploads directory writable.

## Security model

- Admin-only capability (`manage_options`).
- Nonce verification for all AJAX calls.
- Sanitization for URLs, filenames, and log fields.

## Functional checklist

- Handles invalid URLs, malformed JSON, and HTTP failures.
- Skips duplicates using file existence checks.
- Pre-queue optimization: entries matching `{videoid}.jpg` already present in Media Library are skipped before scheduling, reducing repeated endpoint pings.
- Re-registers existing disk files not currently in Media Library.
- Supports cancellation and restart.

