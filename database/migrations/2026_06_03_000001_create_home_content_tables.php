<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function videoTable(string $name): void
    {
        if (Schema::hasTable($name)) {
            return;
        }

        Schema::create($name, function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->string('video_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    private function linkTable(string $name): void
    {
        if (Schema::hasTable($name)) {
            return;
        }

        Schema::create($name, function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_path');
            $table->string('action_url');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function up(): void
    {
        $this->videoTable('home_music');
        $this->videoTable('home_top10_music');
        $this->videoTable('home_movies');
        $this->videoTable('home_top10_movies');
        $this->videoTable('home_tv_series');
        $this->videoTable('home_news');
        $this->linkTable('home_products');
        $this->linkTable('home_top_channels');
        $this->linkTable('home_games');
    }

    public function down(): void
    {
        Schema::dropIfExists('home_top_channels');
        Schema::dropIfExists('home_products');
        Schema::dropIfExists('home_news');
        Schema::dropIfExists('home_tv_series');
        Schema::dropIfExists('home_top10_movies');
        Schema::dropIfExists('home_movies');
        Schema::dropIfExists('home_top10_music');
        Schema::dropIfExists('home_music');
    }
};
