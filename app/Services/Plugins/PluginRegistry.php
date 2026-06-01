<?php

namespace Pterodactyl\Services\Plugins;

use Pterodactyl\Models\Plugin;
use Pterodactyl\Plugins\PluginManifest;
use Pterodactyl\Plugins\Contracts\PluginInterface;
use Pterodactyl\Plugins\PluginContextFactory;
use Pterodactyl\Plugins\Exceptions\InvalidPluginManifestException;
use Pterodactyl\Plugins\Exceptions\PluginException;

class PluginRegistry
{
    /** @var array<string, array{plugin: Plugin, manifest: PluginManifest, directory: string, instance: ?PluginInterface}> */
    private array $entries = [];

    private bool $loaded = false;

    public function __construct(
        private readonly ManifestValidator $validator,
        private readonly PluginAutoloader $autoloader,
        private readonly PluginContextFactory $contextFactory,
    ) {
    }

    public function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->entries = [];
        $plugins = Plugin::query()->where('enabled', true)->get();

        foreach ($plugins as $plugin) {
            $directory = $this->directoryFor($plugin->id);
            if (!is_dir($directory)) {
                continue;
            }

            try {
                $manifest = $this->validator->readFromDirectory($directory);
            } catch (InvalidPluginManifestException) {
                continue;
            }

            if ($manifest->id !== $plugin->id) {
                continue;
            }

            $this->autoloader->register($manifest, $directory);
            $this->entries[$plugin->id] = [
                'plugin' => $plugin,
                'manifest' => $manifest,
                'directory' => $directory,
                'instance' => null,
            ];
        }

        $this->loaded = true;
    }

    /**
     * @return array<string, array{plugin: Plugin, manifest: PluginManifest, directory: string, instance: ?PluginInterface}>
     */
    public function entries(): array
    {
        $this->load();

        return $this->entries;
    }

    public function get(string $pluginId): ?array
    {
        $this->load();

        return $this->entries[$pluginId] ?? null;
    }

    public function bootEntry(string $pluginId): void
    {
        $entry = $this->get($pluginId);
        if (is_null($entry)) {
            return;
        }

        if ($entry['instance'] instanceof PluginInterface) {
            return;
        }

        $manifest = $entry['manifest'];
        if (!class_exists($manifest->entry)) {
            throw new PluginException(sprintf(
                'Plugin entry class "%s" could not be loaded for "%s".',
                $manifest->entry,
                $pluginId
            ));
        }

        $instance = new $manifest->entry();
        if (!$instance instanceof PluginInterface) {
            throw new PluginException(sprintf(
                'Plugin entry class "%s" must implement PluginInterface.',
                $manifest->entry
            ));
        }

        $context = $this->contextFactory->make($entry['plugin']);
        $instance->register($context);

        $this->entries[$pluginId]['instance'] = $instance;
    }

    public function bootAll(): void
    {
        foreach (array_keys($this->entries()) as $pluginId) {
            $this->bootEntry($pluginId);
        }
    }

    public function flush(): void
    {
        $this->entries = [];
        $this->loaded = false;
        $this->autoloader->unregisterAll();
    }

    public static function directoryFor(string $pluginId): string
    {
        return storage_path('app/plugins/' . $pluginId);
    }
}
