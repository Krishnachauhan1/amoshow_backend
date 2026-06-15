<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->after('banner');
            $table->string('avatar')->nullable()->after('is_private');
            $table->unsignedBigInteger('subscriber_count')->default(0)->after('avatar');
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->string('platform')->default('ott')->after('type');
            $table->string('visibility')->default('public')->after('platform');
            $table->string('genre')->nullable()->after('visibility');
            $table->boolean('comments_enabled')->default(true)->after('genre');
            $table->boolean('downloadable')->default(true)->after('comments_enabled');
            $table->boolean('is_collab')->default(false)->after('downloadable');
            $table->string('ad_placement')->default('none')->after('is_collab');
            $table->boolean('has_paid_promotion')->default(false)->after('ad_placement');
            $table->unsignedBigInteger('views_count')->default(0)->after('has_paid_promotion');
            $table->unsignedBigInteger('earnings_paise')->default(0)->after('views_count');
        });

        Schema::create('channel_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'channel_id']);
        });

        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->enum('type', ['pre-roll', 'mid-roll', 'banner']);
            $table->unsignedBigInteger('budget_paise')->default(0);
            $table->unsignedBigInteger('spent_paise')->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->enum('status', ['active', 'paused', 'completed'])->default('active');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('explore_sections', function (Blueprint $table) {
            $table->id();
            $table->string('emoji', 8);
            $table->string('title');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('youtube_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_categories');
        Schema::dropIfExists('explore_sections');
        Schema::dropIfExists('ad_campaigns');
        Schema::dropIfExists('channel_subscriptions');

        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn([
                'platform', 'visibility', 'genre', 'comments_enabled',
                'downloadable', 'is_collab', 'ad_placement', 'has_paid_promotion',
                'views_count', 'earnings_paise',
            ]);
        });

        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn(['is_private', 'avatar', 'subscriber_count']);
        });
    }
};
