<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('videos') || Schema::hasColumn('videos', 'show_on_home')) {
            return;
        }

        Schema::table('videos', function (Blueprint $table) {
            $table->boolean('show_on_home')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('videos') || ! Schema::hasColumn('videos', 'show_on_home')) {
            return;
        }

        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('show_on_home');
        });
    }
};
