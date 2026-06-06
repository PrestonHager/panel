<?php

namespace Pterodactyl\Services\Plugins;

use Pterodactyl\Models\Plugin;
use Pterodactyl\Plugins\Permissions;

class PluginUiConfigService
{
    public function __construct(
        private readonly ManifestValidator $validator,
        private readonly PluginSettingsSchemaService $settingsSchemaService,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function serverUiFor(Plugin $plugin): ?array
    {
        if (!in_array(Permissions::UI_REGISTER, $plugin->effectivePermissions(), true)) {
            return null;
        }

        $ui = $plugin->ui_config ?? [];
        if (!empty($ui['server']) && is_array($ui['server'])) {
            $server = $ui['server'];
        } else {
            $server = $this->readManifestServerUi($plugin);
        }

        if (!is_array($server)) {
            return null;
        }

        return $this->enrichServerUi($plugin, $server);
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, mixed>
     */
    private function enrichServerUi(Plugin $plugin, array $server): array
    {
        $schema = $this->settingsSchemaService->forPlugin($plugin);
        $basePath = rtrim((string) ($server['path'] ?? ''), '/');

        $server['hasClientSettings'] = $schema->hasClientSettings();
        $server['settingsPath'] = $schema->hasClientSettings()
            ? ($basePath === '' ? '/settings' : $basePath . '/settings')
            : null;
        $server['settingsPermission'] = $schema->clientSettingsPermission();
        $server['permissionMap'] = $this->buildPermissionMap($plugin, $schema);

        return $server;
    }

    /**
     * @return array<string, string|null>
     */
    private function buildPermissionMap(Plugin $plugin, \Pterodactyl\Plugins\PluginSettingsSchema $schema): array
    {
        $map = [];

        $serverUi = $plugin->ui_config['server'] ?? [];
        if (!empty($serverUi['path']) && !empty($serverUi['permission'])) {
            $map[(string) $serverUi['path']] = (string) $serverUi['permission'];
        }

        if ($schema->hasClientSettings()) {
            $basePath = rtrim((string) ($serverUi['path'] ?? ''), '/');
            $settingsPath = $basePath === '' ? '/settings' : $basePath . '/settings';
            $map[$settingsPath] = $schema->clientSettingsPermission();
        }

        foreach ($schema->forSurface('client') as $field) {
            if ($field->permission) {
                $map['settings.' . $field->key] = $field->permission;
            }
        }

        try {
            $manifest = $this->validator->readFromDirectory(PluginRegistry::directoryFor($plugin->id));
            foreach ($manifest->apiRoutes as $route) {
                if (!empty($route['path']) && !empty($route['permission'])) {
                    $map[$route['path']] = $route['permission'];
                }
            }
        } catch (\Throwable) {
        }

        return $map;
    }

    /**
     * @return array{name: string, bundle: string}|null
     */
    public function adminServerUiFor(Plugin $plugin): ?array
    {
        if (!in_array(Permissions::UI_REGISTER, $plugin->effectivePermissions(), true)) {
            return null;
        }

        $ui = $plugin->ui_config ?? [];
        $admin = $ui['admin']['server'] ?? null;
        if (is_array($admin) && !empty($admin['name']) && !empty($admin['bundle'])) {
            return [
                'name' => (string) $admin['name'],
                'bundle' => (string) $admin['bundle'],
            ];
        }

        $fromManifest = $this->readManifestAdminServerUi($plugin);
        if (!is_null($fromManifest)) {
            return $fromManifest;
        }

        $server = $this->serverUiFor($plugin);
        if (is_array($server) && !empty($server['name']) && !empty($server['bundle'])) {
            return [
                'name' => (string) $server['name'],
                'bundle' => (string) $server['bundle'],
            ];
        }

        return null;
    }

    /**
     * @return array<int, array{id: string, ui: array{server: array<string, mixed>}}>
     */
    public function enabledClientServerPlugins(): array
    {
        $output = [];

        foreach (Plugin::query()->where('enabled', true)->orderBy('name')->get() as $plugin) {
            $server = $this->serverUiFor($plugin);
            if (is_null($server)) {
                continue;
            }

            $output[] = [
                'id' => $plugin->id,
                'ui' => [
                    'server' => $server,
                ],
            ];
        }

        return $output;
    }

    /**
     * @return array<int, array{id: string, name: string, bundle: string}>
     */
    public function enabledAdminServerPlugins(): array
    {
        $output = [];

        foreach (Plugin::query()->where('enabled', true)->orderBy('name')->get() as $plugin) {
            $admin = $this->adminServerUiFor($plugin);
            if (is_null($admin)) {
                continue;
            }

            $output[] = [
                'id' => $plugin->id,
                'name' => $admin['name'],
                'bundle' => $admin['bundle'],
            ];
        }

        return $output;
    }

    /**
     * @return array{name: string, bundle: string}|null
     */
    public function resolveAdminServerTab(string $pluginId): ?array
    {
        $plugin = Plugin::query()->where('id', $pluginId)->where('enabled', true)->first();
        if (is_null($plugin)) {
            return null;
        }

        return $this->adminServerUiFor($plugin);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readManifestServerUi(Plugin $plugin): ?array
    {
        $directory = PluginRegistry::directoryFor($plugin->id);
        if (!is_dir($directory)) {
            return null;
        }

        try {
            $manifest = $this->validator->readFromDirectory($directory);
        } catch (\Throwable) {
            return null;
        }

        $server = $manifest->uiServer();

        return is_array($server) ? $server : null;
    }

    /**
     * @return array{name: string, bundle: string}|null
     */
    private function readManifestAdminServerUi(Plugin $plugin): ?array
    {
        $directory = PluginRegistry::directoryFor($plugin->id);
        if (!is_dir($directory)) {
            return null;
        }

        try {
            $manifest = $this->validator->readFromDirectory($directory);
        } catch (\Throwable) {
            return null;
        }

        $admin = $manifest->uiAdminServer();
        if (is_null($admin) || empty($admin['name']) || empty($admin['bundle'])) {
            return null;
        }

        return [
            'name' => (string) $admin['name'],
            'bundle' => (string) $admin['bundle'],
        ];
    }
}
