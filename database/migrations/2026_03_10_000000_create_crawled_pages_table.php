<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crawled_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('feed_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->unsignedSmallInteger('status_code')->default(0);
            $table->string('title')->nullable();
            $table->longText('html')->nullable();
            $table->timestamps();

            $table->unique(['feed_id', 'url']);
            $table->index('status_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawled_pages');
    }
};
