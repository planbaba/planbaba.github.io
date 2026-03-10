<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crawl_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('crawl_target_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued');
            $table->unsignedInteger('pages_crawled')->default(0);
            $table->unsignedInteger('links_found')->default(0);
            $table->unsignedInteger('failed_urls')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_runs');
    }
};
