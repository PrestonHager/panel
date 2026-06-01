<?php

namespace Pterodactyl\Plugins;

final readonly class PluginManifest
{
    /**
     * @param string[] $permissions
     * @param array<string, string> $hooks
     * @param array<string, string> $clientPermissions
     * @param array<int, array{method: string, path: string, handler: string, permission?: string}> $apiRoutes
     * @param array<string, mixed> $ui
     * @param array<string, mixed> $configSchema
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $version,
        public string $entry,
        public array $permissions,
        public array $hooks,
        public array $clientPermissions = [],
        public array $apiRoutes = [],
        public array $ui = [],
        public ?string $requiresPanelPluginApi = null,
        public array $configSchema = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $apiRoutes = [];
        foreach ($data['api']['routes'] ?? [] as $route) {
            if (!is_array($route)) {
                continue;
            }
            $apiRoutes[] = [
                'method' => (string) ($route['method'] ?? ''),
                'path' => (string) ($route['path'] ?? ''),
                'handler' => (string) ($route['handler'] ?? ''),
                'permission' => isset($route['permission']) ? (string) $route['permission'] : null,
            ];
        }

        return new self(
            id: (string) $data['id'],
            name: (string) $data['name'],
            version: (string) $data['version'],
            entry: (string) $data['entry'],
            permissions: array_values($data['permissions'] ?? []),
            hooks: $data['hooks'] ?? [],
            clientPermissions: $data['clientPermissions'] ?? [],
            apiRoutes: $apiRoutes,
            ui: $data['ui'] ?? [],
            requiresPanelPluginApi: $data['requires']['panelPluginApi'] ?? null,
            configSchema: $data['config']['schema'] ?? [],
        );
    }

    public function namespacePrefix(): string
    {
        $parts = explode('\\', $this->entry);
        array_pop($parts);

        return implode('\\', $parts) . '\\';
    }

    public function uiServer(): ?array
    {
        $server = $this->ui['server'] ?? null;

        return is_array($server) ? $server : null;
    }

    public function uiAdminServer(): ?array
    {
        $admin = $this->ui['admin']['server'] ?? null;

        return is_array($admin) ? $admin : null;
    }
}
