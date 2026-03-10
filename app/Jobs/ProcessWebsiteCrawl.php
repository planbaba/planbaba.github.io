<?php

namespace App\Jobs;

use App\Crawlers\CrawlRunner;
use App\Models\CrawlRun;
use App\Models\CrawlTarget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWebsiteCrawl implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $crawlTargetId, public readonly int $crawlRunId)
    {
    }

    public function handle(CrawlRunner $runner): void
    {
        $target = CrawlTarget::query()->findOrFail($this->crawlTargetId);
        $run = CrawlRun::query()->findOrFail($this->crawlRunId);

        $runner->run($target, $run);
    }
}
