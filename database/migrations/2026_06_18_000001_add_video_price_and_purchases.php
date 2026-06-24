<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0)->after('is_premium');
        });

        Schema::create('video_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('transaction_id')->unique();
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->string('gateway')->default('razorpay');
            $table->timestamps();

            $table->index(['user_id', 'video_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_purchases');

        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
