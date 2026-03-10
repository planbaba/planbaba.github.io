<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrawledPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'feed_id',
        'url',
        'status_code',
        'title',
        'html',
    ];

    public function feed()
    {
        return $this->belongsTo(Feed::class);
    }
}
