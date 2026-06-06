<?php

namespace Pterodactyl\Plugins\Contracts;

interface PluginMigrationInterface
{
    public function up(): void;

    public function down(): void;
}
