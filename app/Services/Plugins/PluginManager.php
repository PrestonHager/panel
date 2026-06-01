<?php

namespace Pterodactyl\Services\Plugins;

use Pterodactyl\Models\Plugin;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Plugins\Permissions;
use Pterodactyl\Plugins\PluginManifest;
use Pterodactyl\Plugins\Contracts\PluginInterface;
use Pterodactyl\Plugins\Exceptions\PluginException;

class PluginManager
{
    public function __construct(
        private readonly GitHubPluginInstaller $installer,
        private readonly PluginRegistry $registry,
    ) {
    }

    public function installFromGithub(string $url, string $ref = 'main'): Plugin
    {
        $result = $this->installer->install($url, $ref);
        $manifest = $result['manifest'];

        $plugin = Plugin::query()->create(array_merge(
            $this->attributesFromManifest($manifest),
            [
                'source_url' => $result['source_url'],
                'source_ref' => $result['source_ref'],
                'commit_sha' => $result['commit_sha'],
                'enabled' => false,
                'config' => [],
                'installed_at' => now(),
            ]
        ));

        Activity::event('plugin:install')
            ->property('plugin_id', $plugin->id)
            ->property('version', $plugin->version)
            ->property('source_url', $plugin->source_url)
            ->log();

        return $plugin;
    }

    public function installFromPath(string $path): Plugin
    {
        $result = $this->installer->installFromPath($path);
        $manifest = $result['manifest'];

        return Plugin::query()->updateOrCreate(
            ['id' => $manifest->id],
            array_merge(
                $this->attributesFromManifest($manifest),
                [
                    'source_url' => $result['source_url'],
                    'source_ref' => $result['source_ref'],
                    'commit_sha' => $result['commit_sha'],
                    'enabled' => false,
                    'config' => [],
                    'installed_at' => now(),
                ]
            )
        );
    }

    public function updateFromGithub(Plugin $plugin, ?string $ref = null): Plugin
    {
        $ref = $ref ?: ($plugin->source_ref ?: 'main');
        $wasEnabled = $plugin->enabled;

        if ($wasEnabled) {
            $this->disable($plugin);
            $plugin = $plugin->fresh();
        }

        $result = $this->installer->update($plugin->source_url, $ref, $plugin->id);
        $plugin->fill(array_merge(
            $this->attributesFromManifest($result['manifest']),
            [
                'commit_sha' => $result['commit_sha'],
                'source_url' => $result['source_url'],
                'source_ref' => $result['source_ref'],
            ]
        ));
        $plugin->save();

        Activity::event('plugin:update')
            ->property('plugin_id', $plugin->id)
            ->property('version', $plugin->version)
            ->property('commit_sha', $plugin->commit_sha)
            ->log();

        if ($wasEnabled) {
            return $this->enable($plugin->fresh());
        }

        return $plugin->fresh();
    }

    public function enable(Plugin $plugin): Plugin
    {
        if ($plugin->enabled) {
            return $plugin;
        }

        $this->assertEntryClassValid($plugin);

        $manifest = app(ManifestValidator::class)->readFromDirectory(PluginRegistry::directoryFor($plugin->id));
        $plugin->fill($this->attributesFromManifest($manifest));

        $plugin->enabled = true;
        $plugin->save();

        $this->registry->flush();
        $this->registry->load();
        $this->registry->bootEntry($plugin->id);

        Activity::event('plugin:enable')->property('plugin_id', $plugin->id)->log();

        // Registry is reloaded so PluginEventDispatcher picks up hooks on the next event.

        return $plugin->fresh();
    }

    public function disable(Plugin $plugin): Plugin
    {
        if (!$plugin->enabled) {
            return $plugin;
        }

        $plugin->enabled = false;
        $plugin->save();

        $this->registry->flush();

        Activity::event('plugin:disable')->property('plugin_id', $plugin->id)->log();

        return $plugin->fresh();
    }

    public function uninstall(Plugin $plugin): void
    {
        if ($plugin->enabled) {
            $this->disable($plugin);
        }

        $directory = PluginRegistry::directoryFor($plugin->id);
        if (is_dir($directory)) {
            $this->removeDirectory($directory);
        }

        $plugin->delete();

        Activity::event('plugin:uninstall')->property('plugin_id', $plugin->id)->log();
    }

    /**
     * @param array<string, mixed> $config
     */
    public function updateConfig(Plugin $plugin, array $config): Plugin
    {
        $plugin->config = $config;
        $plugin->save();

        return $plugin->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFromManifest(PluginManifest $manifest): array
    {
        return [
            'id' => $manifest->id,
            'name' => $manifest->name,
            'version' => $manifest->version,
            'permissions' => $manifest->permissions,
            'client_permissions' => $manifest->clientPermissions,
            'ui_config' => $manifest->ui,
        ];
    }

    private function assertEntryClassValid(Plugin $plugin): void
    {
        $directory = PluginRegistry::directoryFor($plugin->id);
        if (!is_dir($directory)) {
            throw new PluginException(sprintf('Plugin files for "%s" are missing.', $plugin->id));
        }

        $validator = app(ManifestValidator::class);
        $manifest = $validator->readFromDirectory($directory);

        if ($manifest->id !== $plugin->id) {
            throw new PluginException('Installed plugin manifest ID does not match database record.');
        }

        $autoloader = app(PluginAutoloader::class);
        $autoloader->register($manifest, $directory);

        if (!class_exists($manifest->entry)) {
            throw new PluginException(sprintf('Plugin entry class "%s" could not be loaded.', $manifest->entry));
        }

        $instance = new $manifest->entry();
        if (!$instance instanceof PluginInterface) {
            throw new PluginException(sprintf('Plugin entry class "%s" must implement PluginInterface.', $manifest->entry));
        }

        $stored = $plugin->permissions ?? [];
        sort($stored);
        $declared = $manifest->permissions;
        sort($declared);

        if ($stored !== $declared) {
            throw new PluginException('Plugin permissions have changed. Disable the plugin, reinstall, and approve the new permissions.');
        }

        if (!empty($manifest->hooks) && !in_array(Permissions::EVENTS_SUBSCRIBE, $stored, true)) {
            throw new PluginException('Plugin declares hooks but does not have events.subscribe permission.');
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($directory);
    }
}
