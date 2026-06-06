<?php

namespace Pterodactyl\Plugins;

final readonly class PluginManifest
{
    /**
     * @param string[] $permissions
     * @param array<string, string> $hooks
     * @param array<string, string> $clientPermissions
     * @param array<int, array{method: string, path: string, handler: string, permission?: string|null, auth?: string}> $apiRoutes
     * @param array<string, mixed> $ui
     * @param array<string, mixed> $configSchema
     * @param string[] $httpAllowedHosts
     * @param string[] $migrations
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
        public array $httpAllowedHosts = [],
        public array $migrations = [],
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
                'auth' => (string) ($route['auth'] ?? 'client'),
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
            httpAllowedHosts: array_values(array_filter(array_map(
                'strval',
                $data['http']['allowedHosts'] ?? []
            ))),
            migrations: array_values(array_filter(array_map(
                'strval',
                $data['migrations'] ?? []
            ))),
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

    public function uiClient(): ?array
    {
        $client = $this->ui['client'] ?? null;

        return is_array($client) ? $client : null;
    }

    public function uiAdmin(): ?array
    {
        $admin = $this->ui['admin'] ?? null;
        if (!is_array($admin)) {
            return null;
        }

        if (isset($admin['path'], $admin['name'], $admin['bundle'])) {
            return $admin;
        }

        return null;
    }

    public function uiAdminServer(): ?array
    {
        $admin = $this->ui['admin']['server'] ?? null;

        return is_array($admin) ? $admin : null;
    }

    /**
     * @return array<int, array{id: string, label: string, bundle?: string|null, propsSchema?: array<string, mixed>, surfaces: string[]}>
     */
    public function uiBlocks(): array
    {
        $blocks = $this->ui['blocks'] ?? [];
        if (!is_array($blocks)) {
            return [];
        }

        $output = [];
        foreach ($blocks as $block) {
            if (!is_array($block) || empty($block['id']) || empty($block['label'])) {
                continue;
            }

            $surfaces = array_values(array_filter(
                (array) ($block['surfaces'] ?? ['client']),
                fn ($s) => is_string($s) && in_array($s, ['client', 'admin', 'public'], true)
            ));

            $output[] = [
                'id' => (string) $block['id'],
                'label' => (string) $block['label'],
                'bundle' => isset($block['bundle']) ? (string) $block['bundle'] : null,
                'propsSchema' => is_array($block['propsSchema'] ?? null) ? $block['propsSchema'] : [],
                'surfaces' => $surfaces !== [] ? $surfaces : ['client'],
            ];
        }

        return $output;
    }

    /**
     * @return array<int, array{id: string, label: string, default?: string|null, surfaces: string[]}>
     */
    public function uiKeybinds(): array
    {
        $keybinds = $this->ui['keybinds'] ?? [];
        if (!is_array($keybinds)) {
            return [];
        }

        $output = [];
        foreach ($keybinds as $keybind) {
            if (!is_array($keybind) || empty($keybind['id']) || empty($keybind['label'])) {
                continue;
            }

            $surfaces = array_values(array_filter(
                (array) ($keybind['surfaces'] ?? ['client']),
                fn ($s) => is_string($s) && in_array($s, ['client', 'admin'], true)
            ));

            $output[] = [
                'id' => (string) $keybind['id'],
                'label' => (string) $keybind['label'],
                'default' => isset($keybind['default']) ? (string) $keybind['default'] : null,
                'surfaces' => $surfaces !== [] ? $surfaces : ['client'],
            ];
        }

        return $output;
    }

    /**
     * @return array{surfaces: string[], tokens: array<string, string>, stylesheet: string|null}|null
     */
    public function uiTheme(): ?array
    {
        $theme = $this->ui['theme'] ?? null;
        if (!is_array($theme)) {
            return null;
        }

        $surfaces = array_values(array_filter(
            (array) ($theme['surfaces'] ?? ['client']),
            fn ($s) => is_string($s) && in_array($s, ['client', 'admin'], true)
        ));
        if ($surfaces === []) {
            $surfaces = ['client'];
        }

        $tokens = [];
        foreach ((array) ($theme['tokens'] ?? []) as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $tokens[$key] = $value;
            }
        }

        $stylesheet = isset($theme['stylesheet']) ? (string) $theme['stylesheet'] : null;

        return [
            'surfaces' => $surfaces,
            'tokens' => $tokens,
            'stylesheet' => $stylesheet !== '' ? $stylesheet : null,
        ];
    }
}
