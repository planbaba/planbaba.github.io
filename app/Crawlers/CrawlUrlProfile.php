<?php

namespace App\Crawlers;

use App\Models\CrawlTarget;
use Psr\Http\Message\UriInterface;
use Spatie\Crawler\CrawlProfiles\CrawlProfile;

class CrawlUrlProfile extends CrawlProfile
{
    public function __construct(private readonly CrawlTarget $target)
    {
    }

    public function shouldCrawl(UriInterface $url): bool
    {
        $urlString = (string) $url;

        foreach ($this->target->ignored_urls ?? [] as $pattern) {
            if (str_contains($urlString, $pattern)) {
                return false;
            }
        }

        if (blank($this->target->allowed_domains)) {
            return true;
        }

        return in_array($url->getHost(), $this->target->allowed_domains, true);
    }
}
