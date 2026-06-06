<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('plugins', function (Blueprint $table) {
            $table->json('approved_permissions')->nullable()->after('permissions');
            $table->json('approved_http_hosts')->nullable()->after('approved_permissions');
        });
    }

    public function down(): void
    {
        Schema::table('plugins', function (Blueprint $table) {
            $table->dropColumn(['approved_permissions', 'approved_http_hosts']);
        });
    }
};
