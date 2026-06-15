<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_section_video', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_section_id')->constrained('home_sections')->cascadeOnDelete();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['home_section_id', 'video_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_section_video');
    }
};

