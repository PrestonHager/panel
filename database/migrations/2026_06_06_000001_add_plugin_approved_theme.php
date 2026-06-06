<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plugins', function (Blueprint $table) {
            $table->json('approved_theme')->nullable()->after('approved_http_hosts');
        });
    }

    public function down(): void
    {
        Schema::table('plugins', function (Blueprint $table) {
            $table->dropColumn('approved_theme');
        });
    }
};
