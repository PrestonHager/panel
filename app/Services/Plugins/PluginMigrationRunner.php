<?php

namespace Pterodactyl\Services\Plugins;

use Pterodactyl\Models\Plugin;
use Pterodactyl\Plugins\Contracts\PluginMigrationInterface;
use Pterodactyl\Plugins\Exceptions\PluginException;

class PluginMigrationRunner
{
    public function __construct(
        private readonly ManifestValidator $manifestValidator,
    ) {
    }

    public function migrate(Plugin $plugin): void
    {
        $directory = PluginRegistry::directoryFor($plugin->id);
        $manifest = $this->manifestValidator->readFromDirectory($directory);
        $applied = $plugin->migration_version ?? [];

        foreach ($manifest->migrations as $class) {
            if (in_array($class, $applied, true)) {
                continue;
            }

            $instance = $this->resolveMigration($class, $manifest->namespacePrefix());
            $instance->up();
            $applied[] = $class;
        }

        $plugin->migration_version = array_values($applied);
        $plugin->save();
    }

    public function rollback(Plugin $plugin): void
    {
        $directory = PluginRegistry::directoryFor($plugin->id);
        $manifest = $this->manifestValidator->readFromDirectory($directory);
        $applied = array_values($plugin->migration_version ?? []);

        foreach (array_reverse($applied) as $class) {
            if (!in_array($class, $manifest->migrations, true)) {
                continue;
            }

            $instance = $this->resolveMigration($class, $manifest->namespacePrefix());
            $instance->down();
        }

        $plugin->migration_version = [];
        $plugin->save();
    }

    private function resolveMigration(string $class, string $namespacePrefix): PluginMigrationInterface
    {
        if (!str_starts_with($class, $namespacePrefix)) {
            throw new PluginException(sprintf('Migration class "%s" must be within plugin namespace.', $class));
        }

        if (!class_exists($class)) {
            throw new PluginException(sprintf('Migration class "%s" could not be loaded.', $class));
        }

        $instance = new $class();
        if (!$instance instanceof PluginMigrationInterface) {
            throw new PluginException(sprintf('Migration class "%s" must implement PluginMigrationInterface.', $class));
        }

        return $instance;
    }
}
