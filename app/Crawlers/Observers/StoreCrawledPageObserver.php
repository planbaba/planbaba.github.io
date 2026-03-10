<?php

namespace App\Crawlers\Observers;

use App\Models\CrawledPage;
use App\Models\Feed;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Spatie\Crawler\CrawlObservers\CrawlObserver;

class StoreCrawledPageObserver extends CrawlObserver
{
    public function __construct(private readonly Feed $feed)
    {
    }

    public function crawled(
        UriInterface $url,
        ResponseInterface $response,
        ?UriInterface $foundOnUrl = null,
        ?string $linkText = null
    ): void {
        $html = (string) $response->getBody();

        CrawledPage::updateOrCreate(
            [
                'feed_id' => $this->feed->id,
                'url' => (string) $url,
            ],
            [
                'status_code' => $response->getStatusCode(),
                'title' => $this->extractTitle($html),
                'html' => Str::limit($html, 65000, ''),
            ]
        );
    }

    public function crawlFailed(
        UriInterface $url,
        \Throwable $exception,
        ?UriInterface $foundOnUrl = null,
        ?string $linkText = null
    ): void {
        CrawledPage::updateOrCreate(
            [
                'feed_id' => $this->feed->id,
                'url' => (string) $url,
            ],
            [
                'status_code' => 0,
                'title' => null,
                'html' => $exception->getMessage(),
            ]
        );
    }

    private function extractTitle(string $html): ?string
    {
        if (! preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return null;
        }

        return trim(html_entity_decode(strip_tags($matches[1])));
    }
}
