<?php

namespace App\Console\Commands;

use App\Jobs\CrawlFeedJob;
use App\Models\Feed;
use Illuminate\Console\Command;

class CrawlFeedsCommand extends Command
{
    protected $signature = 'feeds:crawl';

    protected $description = 'Dispatch crawl jobs for every feed URL';

    public function handle(): int
    {
        Feed::query()
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->chunkById(100, function ($feeds): void {
                foreach ($feeds as $feed) {
                    CrawlFeedJob::dispatch($feed);
                    $this->line("Queued crawl for: {$feed->url}");
                }
            });

        $this->info('All feed crawl jobs have been dispatched.');

        return self::SUCCESS;
    }
}
