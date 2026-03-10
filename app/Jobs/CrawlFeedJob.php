<?php

namespace App\Jobs;

use App\Crawlers\Observers\StoreCrawledPageObserver;
use App\Models\Feed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\Crawler\Crawler;

class CrawlFeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(public readonly Feed $feed)
    {
    }

    public function handle(): void
    {
        Crawler::create([
            'timeout' => 10,
            'connect_timeout' => 10,
            'allow_redirects' => true,
        ])
            ->setCrawlObserver(new StoreCrawledPageObserver($this->feed))
            ->setMaximumDepth(2)
            ->setTotalCrawlLimit(100)
            ->setConcurrency(5)
            ->ignoreRobots()
            ->startCrawling($this->feed->url);
    }
}
