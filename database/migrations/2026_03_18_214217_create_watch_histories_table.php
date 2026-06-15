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
    Schema::create('watch_histories', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->foreignId('video_id')->constrained()->cascadeOnDelete();
        $table->integer('watched_seconds')->default(0);
        $table->integer('total_seconds')->default(0);
        $table->boolean('completed')->default(false);
        $table->timestamp('last_watched_at')->useCurrent();
        $table->unique(['user_id', 'video_id']);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('watch_histories');
    }
};
