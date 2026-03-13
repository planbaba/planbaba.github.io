# Adaptive JSON Media Importer

Adaptive JSON Media Importer is a WordPress plugin for importing images from JSON using Action Scheduler single actions.

## Features

- Reliable batch processing using:

```php
as_schedule_single_action( time() + 5, 'ajmi_process_batch', $args, 'ajmi_media_import' );
```

- JSON input format support:
  - `['url1', 'url2']`
  - `[{"url":"..."}]`
  - `[{"thumbnail":"..."}]`
  - `[{"image":"...", "filename":"custom.jpg"}]`
- Duplicate prevention with file + attachment lookup.
- Auto registration of existing files into Media Library.
- Cron-health indicator to help debug pending Action Scheduler jobs.
- Structured rotating logs + errors.

## Installation

1. Upload folder to `wp-content/plugins/adaptive-json-media-importer`.
2. Activate plugin.
3. Ensure Action Scheduler is available (e.g. WooCommerce active).
4. Open **AJ Media Importer** in WordPress admin.

## Cron note

If background jobs remain pending, configure system cron:

```bash
*/1 * * * * php /path/to/public_html/wp-cron.php >/dev/null 2>&1
```

or

```bash
*/1 * * * * curl -s https://example.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```
