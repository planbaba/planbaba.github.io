# Laravel 12 Simple Crawler (Spatie Crawler v9)

This repository now includes a simple crawler implementation for Laravel 12 using:

- `spatie/crawler:^9.0`
- queued jobs (`ShouldQueue`)
- `feeds` table as the source of URLs
- `crawled_pages` table to persist crawl results

## Installation

```bash
composer require spatie/crawler:^9.0
```

## Files added

- `app/Console/Commands/CrawlFeedsCommand.php`
- `app/Jobs/CrawlFeedJob.php`
- `app/Crawlers/Observers/StoreCrawledPageObserver.php`
- `app/Models/Feed.php`
- `app/Models/CrawledPage.php`
- `database/migrations/2026_03_10_000000_create_crawled_pages_table.php`

## Run migrations

```bash
php artisan migrate
```

## Queue worker

```bash
php artisan queue:work
```

## Dispatch crawling for all feeds

```bash
php artisan feeds:crawl
```

This command reads each `url` from `feeds` and dispatches one `CrawlFeedJob` per feed.  
Each job crawls that URL and stores page URL, status code, page title, and HTML snapshot in `crawled_pages`.
