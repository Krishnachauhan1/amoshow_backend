<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['video_id', 'created_at']);
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->unsignedBigInteger('comments_count')->default(0)->after('likes_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_comments');

        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('comments_count');
        });
    }
};
