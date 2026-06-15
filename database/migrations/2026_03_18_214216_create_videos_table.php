<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::create('videos', function (Blueprint $table) {
        $table->id();
        $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
        $table->string('title');
        $table->text('description')->nullable();
        $table->string('video_path');
        $table->string('thumbnail')->nullable();
        $table->enum('type', ['movie', 'series', 'episode'])->default('movie');
        $table->bigInteger('duration')->nullable();
        $table->boolean('is_premium')->default(false);
        $table->enum('status', ['processing', 'published', 'draft'])->default('draft');
        $table->unsignedBigInteger('series_id')->nullable();
        $table->integer('episode_number')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
