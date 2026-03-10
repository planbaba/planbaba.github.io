# Filament crawler setup (Laravel 12 + Filament v5)

1. Install package:

```bash
composer require spatie/crawler:^9.0
```

2. Run migrations:

```bash
php artisan migrate
```

3. Start queue worker for background crawling:

```bash
php artisan queue:work
```

4. Register widget in your Filament panel provider (`getWidgets()`):

```php
\App\Filament\Widgets\CrawlerStat::class,
```

5. Ensure your panel auto-discovers resources from `app/Filament/Resources` or register `CrawlTargetResource` manually.


## Filament v5 structure note

This implementation uses dedicated `Schemas/CrawlTargetForm.php` and `Tables/CrawlTargetsTable.php` classes, and the table action closure intentionally avoids an incompatible concrete type-hint on `$record` to prevent runtime type errors during closure evaluation.
