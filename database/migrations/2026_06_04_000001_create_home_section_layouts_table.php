<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('home_section_layouts')) {
            return;
        }

        Schema::create('home_section_layouts', function (Blueprint $table) {
            $table->id();
            $table->string('section_key')->unique();
            $table->unsignedSmallInteger('card_width')->default(200);
            $table->unsignedSmallInteger('card_height')->default(300);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_section_layouts');
    }
};
