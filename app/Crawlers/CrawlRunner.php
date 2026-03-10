<?php

namespace App\Crawlers;

use App\Crawlers\Observers\CrawlStatsObserver;
use App\Models\CrawlRun;
use App\Models\CrawlTarget;
use Illuminate\Support\Carbon;
use Spatie\Crawler\Crawler;
use Throwable;

class CrawlRunner
{
    public function run(CrawlTarget $target, CrawlRun $run): void
    {
        $run->update([
            'status' => 'running',
            'started_at' => Carbon::now(),
            'error_message' => null,
        ]);

        try {
            Crawler::create($target->url)
                ->setCrawlObserver(new CrawlStatsObserver($run))
                ->setCrawlProfile(new CrawlUrlProfile($target))
                ->ignoreRobots(!$target->respect_robots_txt)
                ->setConcurrency($target->concurrency)
                ->setTotalCrawlLimit($target->max_urls)
                ->setMaximumDepth($target->max_depth)
                ->startCrawling($target->url);

            $run->update([
                'status' => 'completed',
                'finished_at' => Carbon::now(),
            ]);

            $target->update([
                'last_crawled_at' => Carbon::now(),
            ]);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'finished_at' => Carbon::now(),
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
