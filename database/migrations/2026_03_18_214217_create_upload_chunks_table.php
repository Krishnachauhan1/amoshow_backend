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
    Schema::create('upload_chunks', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('upload_id')->index();
        $table->integer('chunk_index');
        $table->integer('total_chunks');
        $table->string('chunk_path');
        $table->string('original_filename');
        $table->unique(['upload_id', 'chunk_index']);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upload_chunks');
    }
};
