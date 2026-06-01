<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plugins', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('version', 32);
            $table->string('source_url');
            $table->string('source_ref')->default('main');
            $table->string('commit_sha', 64)->nullable();
            $table->boolean('enabled')->default(false);
            $table->json('permissions');
            $table->json('client_permissions')->nullable();
            $table->json('ui_config')->nullable();
            $table->text('config')->nullable();
            $table->timestamp('installed_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('plugin_data', function (Blueprint $table) {
            $table->id();
            $table->string('plugin_id');
            $table->string('subject_type', 64);
            $table->unsignedBigInteger('subject_id');
            $table->string('key', 191);
            $table->json('value');
            $table->timestamps();

            $table->foreign('plugin_id')->references('id')->on('plugins')->cascadeOnDelete();
            $table->unique(['plugin_id', 'subject_type', 'subject_id', 'key']);
            $table->index(['plugin_id', 'subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_data');
        Schema::dropIfExists('plugins');
    }
};
