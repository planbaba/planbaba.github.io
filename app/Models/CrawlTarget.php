<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrawlTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'max_depth',
        'max_urls',
        'concurrency',
        'respect_robots_txt',
        'user_agent',
        'allowed_domains',
        'ignored_urls',
        'is_active',
        'last_crawled_at',
    ];

    protected function casts(): array
    {
        return [
            'allowed_domains' => 'array',
            'ignored_urls' => 'array',
            'is_active' => 'boolean',
            'respect_robots_txt' => 'boolean',
            'last_crawled_at' => 'datetime',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(CrawlRun::class);
    }
}
