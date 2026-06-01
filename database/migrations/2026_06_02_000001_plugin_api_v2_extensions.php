<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('plugins', function (Blueprint $table) {
            if (!Schema::hasColumn('plugins', 'client_permissions')) {
                $table->json('client_permissions')->nullable()->after('permissions');
            }

            if (!Schema::hasColumn('plugins', 'ui_config')) {
                $table->json('ui_config')->nullable()->after('client_permissions');
            }
        });

        if (!Schema::hasTable('subuser_plugin_permissions')) {
            Schema::create('subuser_plugin_permissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('subuser_id');
                $table->string('plugin_id');
                $table->string('permission', 191);
                $table->timestamps();

                $table->foreign('subuser_id')->references('id')->on('subusers')->cascadeOnDelete();
                $table->foreign('plugin_id')->references('id')->on('plugins')->cascadeOnDelete();
                $table->unique(['subuser_id', 'plugin_id', 'permission'], 'subuser_plugin_perm_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subuser_plugin_permissions');

        Schema::table('plugins', function (Blueprint $table) {
            $columns = array_filter(
                ['client_permissions', 'ui_config'],
                fn (string $column) => Schema::hasColumn('plugins', $column),
            );

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
