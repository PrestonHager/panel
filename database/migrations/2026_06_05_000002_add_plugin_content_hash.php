<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('plugins', function (Blueprint $table) {
            if (!Schema::hasColumn('plugins', 'content_hash')) {
                $table->string('content_hash', 64)->nullable()->after('commit_sha');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plugins', function (Blueprint $table) {
            if (Schema::hasColumn('plugins', 'content_hash')) {
                $table->dropColumn('content_hash');
            }
        });
    }
};
