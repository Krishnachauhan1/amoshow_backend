<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('collaborator_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('collaborator_email');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->timestamps();

            $table->unique(['video_id', 'collaborator_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_collaborators');
    }
};
