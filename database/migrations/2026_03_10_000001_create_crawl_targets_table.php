<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crawl_targets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->unsignedInteger('max_depth')->default(10);
            $table->unsignedInteger('max_urls')->default(250);
            $table->unsignedInteger('concurrency')->default(10);
            $table->boolean('respect_robots_txt')->default(true);
            $table->string('user_agent')->nullable();
            $table->json('allowed_domains')->nullable();
            $table->json('ignored_urls')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_crawled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_targets');
    }
};
