<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'crawl_target_id',
        'status',
        'pages_crawled',
        'links_found',
        'failed_urls',
        'started_at',
        'finished_at',
        'error_message',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(CrawlTarget::class, 'crawl_target_id');
    }
}
