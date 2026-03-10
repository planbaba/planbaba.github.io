<?php

namespace App\Crawlers\Observers;

use App\Models\CrawlRun;
use Psr\Http\Message\UriInterface;
use Spatie\Crawler\CrawlObservers\CrawlObserver;
use Throwable;

class CrawlStatsObserver extends CrawlObserver
{
    public function __construct(private readonly CrawlRun $run)
    {
    }

    public function crawled(UriInterface $url, $response, ?UriInterface $foundOnUrl = null, ?string $linkText = null): void
    {
        $this->run->increment('pages_crawled');

        if (method_exists($response, 'getHeader')) {
            $links = $response->getHeader('Link');
            if ($links !== []) {
                $this->run->increment('links_found', count($links));
            }
        }
    }

    public function crawlFailed(UriInterface $url, Throwable $throwable, ?UriInterface $foundOnUrl = null, ?string $linkText = null): void
    {
        $this->run->increment('failed_urls');

        $this->run->update([
            'error_message' => $throwable->getMessage(),
        ]);
    }
}
