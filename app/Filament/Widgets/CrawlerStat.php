<?php

namespace App\Filament\Widgets;

use App\Models\CrawlRun;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CrawlerStat extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Queued', (string) CrawlRun::query()->where('status', 'queued')->count()),
            Stat::make('Running', (string) CrawlRun::query()->where('status', 'running')->count()),
            Stat::make('Completed', (string) CrawlRun::query()->where('status', 'completed')->count()),
            Stat::make('Failed', (string) CrawlRun::query()->where('status', 'failed')->count()),
        ];
    }
}
