<?php

namespace Pterodactyl\Services\Plugins;

use Pterodactyl\Plugins\HookMap;
use Pterodactyl\Plugins\PanelPluginApi;
use Pterodactyl\Plugins\Permissions;
use Pterodactyl\Plugins\DesignTokenRegistry;
use Pterodactyl\Plugins\PluginManifest;
use Pterodactyl\Plugins\Exceptions\InvalidPluginManifestException;

class ManifestValidator
{
    public const MANIFEST_FILENAME = 'plugin.json';

    private const ALLOWED_HTTP_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    public function readFromDirectory(string $directory): PluginManifest
    {
        $path = rtrim($directory, '/') . '/' . self::MANIFEST_FILENAME;
        if (!is_file($path)) {
            throw new InvalidPluginManifestException(sprintf(
                'Plugin manifest "%s" was not found in the package.',
                self::MANIFEST_FILENAME
            ));
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new InvalidPluginManifestException('Unable to read plugin manifest.');
        }

        $data = json_decode($contents, true);
        if (!is_array($data)) {
            throw new InvalidPluginManifestException('Plugin manifest is not valid JSON.');
        }

        return $this->validate($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function validate(array $data): PluginManifest
    {
        foreach (['id', 'name', 'version', 'entry'] as $field) {
            if (empty($data[$field]) || !is_string($data[$field])) {
                throw new InvalidPluginManifestException(sprintf('Manifest field "%s" is required.', $field));
            }
        }

        if (!preg_match('/^[a-z][a-z0-9-]*(\.[a-z][a-z0-9-]*)+$/', $data['id'])) {
            throw new InvalidPluginManifestException('Manifest "id" must be a reverse-domain identifier (e.g. com.example.my-plugin).');
        }

        if (!preg_match('/^\d+\.\d+\.\d+/', $data['version'])) {
            throw new InvalidPluginManifestException('Manifest "version" must be a semantic version string.');
        }

        $requiredApi = $data['requires']['panelPluginApi'] ?? null;
        if (is_string($requiredApi) && !PanelPluginApi::satisfies($requiredApi)) {
            throw new InvalidPluginManifestException(sprintf(
                'Plugin requires panel plugin API %s but this panel provides %s.',
                $requiredApi,
                PanelPluginApi::VERSION
            ));
        }

        $permissions = $data['permissions'] ?? [];
        if (!is_array($permissions)) {
            throw new InvalidPluginManifestException('Manifest "permissions" must be an array.');
        }

        foreach ($permissions as $permission) {
            if (!is_string($permission) || !Permissions::isValid($permission)) {
                throw new InvalidPluginManifestException(sprintf('Unknown permission "%s" in manifest.', (string) $permission));
            }
        }

        $namespacePrefix = $this->namespacePrefixFromEntry((string) $data['entry']);

        $clientPermissions = $data['clientPermissions'] ?? [];
        if (!is_array($clientPermissions)) {
            throw new InvalidPluginManifestException('Manifest "clientPermissions" must be an object.');
        }

        foreach ($clientPermissions as $key => $label) {
            if (!is_string($key) || !is_string($label)) {
                throw new InvalidPluginManifestException('Each clientPermissions entry must be a string key with a string label.');
            }
            if (in_array($key, Permissions::RESERVED_CLIENT_PERMISSIONS, true)) {
                throw new InvalidPluginManifestException(sprintf('Client permission "%s" is reserved.', $key));
            }
        }

        $hooks = $data['hooks'] ?? [];
        if (!is_array($hooks)) {
            throw new InvalidPluginManifestException('Manifest "hooks" must be an object.');
        }

        if (!empty($hooks) && !in_array(Permissions::EVENTS_SUBSCRIBE, $permissions, true)) {
            throw new InvalidPluginManifestException('Plugins declaring hooks must request the "events.subscribe" permission.');
        }

        foreach ($hooks as $hook => $class) {
            if (!is_string($hook) || !HookMap::isValidHook($hook)) {
                throw new InvalidPluginManifestException(sprintf('Unknown hook "%s" in manifest.', (string) $hook));
            }
            if (!is_string($class) || $class === '') {
                throw new InvalidPluginManifestException(sprintf('Hook "%s" must map to a listener class name.', $hook));
            }
            $this->assertClassInNamespace($class, $namespacePrefix, 'hook listener');
        }

        $apiRoutes = $data['api']['routes'] ?? [];
        if (!is_array($apiRoutes)) {
            throw new InvalidPluginManifestException('Manifest "api.routes" must be an array.');
        }

        if (!empty($apiRoutes) && !in_array(Permissions::API_SERVE, $permissions, true)) {
            throw new InvalidPluginManifestException('Plugins declaring API routes must request the "api.serve" permission.');
        }

        foreach ($apiRoutes as $index => $route) {
            if (!is_array($route)) {
                throw new InvalidPluginManifestException(sprintf('API route at index %d must be an object.', $index));
            }

            $method = strtoupper((string) ($route['method'] ?? ''));
            if (!in_array($method, self::ALLOWED_HTTP_METHODS, true)) {
                throw new InvalidPluginManifestException(sprintf('Invalid HTTP method "%s" in API route.', $method));
            }

            $path = (string) ($route['path'] ?? '');
            if ($path === '' || !str_starts_with($path, '/')) {
                throw new InvalidPluginManifestException('API route paths must start with "/".');
            }

            if (str_contains($path, '/api/') || str_contains($path, '..')) {
                throw new InvalidPluginManifestException('API route paths must be relative to the plugin API prefix.');
            }

            $handler = (string) ($route['handler'] ?? '');
            if ($handler === '') {
                throw new InvalidPluginManifestException('API route handler is required.');
            }

            $this->assertHandlerInNamespace($handler, $namespacePrefix);

            $routePermission = $route['permission'] ?? null;
            if ($routePermission !== null && !is_string($routePermission)) {
                throw new InvalidPluginManifestException('API route permission must be a string.');
            }

            if (is_string($routePermission) && !array_key_exists($routePermission, $clientPermissions)) {
                throw new InvalidPluginManifestException(sprintf(
                    'API route permission "%s" is not declared in clientPermissions.',
                    $routePermission
                ));
            }
        }

        $ui = $data['ui'] ?? [];
        if (!is_array($ui)) {
            throw new InvalidPluginManifestException('Manifest "ui" must be an object.');
        }

        if (!empty($ui) && !in_array(Permissions::UI_REGISTER, $permissions, true)) {
            throw new InvalidPluginManifestException('Plugins declaring UI must request the "ui.register" permission.');
        }

        $uiServer = $ui['server'] ?? null;
        if ($uiServer !== null) {
            if (!is_array($uiServer)) {
                throw new InvalidPluginManifestException('Manifest "ui.server" must be an object.');
            }

            foreach (['path', 'name', 'bundle', 'permission'] as $field) {
                if (empty($uiServer[$field]) || !is_string($uiServer[$field])) {
                    throw new InvalidPluginManifestException(sprintf('ui.server.%s is required.', $field));
                }
            }

            if (!str_starts_with($uiServer['path'], '/')) {
                throw new InvalidPluginManifestException('ui.server.path must start with "/".');
            }

            if (!array_key_exists($uiServer['permission'], $clientPermissions)) {
                throw new InvalidPluginManifestException('ui.server.permission must be declared in clientPermissions.');
            }
        }

        $uiAdminServer = $ui['admin']['server'] ?? null;
        if ($uiAdminServer !== null) {
            if (!is_array($uiAdminServer)) {
                throw new InvalidPluginManifestException('Manifest "ui.admin.server" must be an object.');
            }

            foreach (['name', 'bundle'] as $field) {
                if (empty($uiAdminServer[$field]) || !is_string($uiAdminServer[$field])) {
                    throw new InvalidPluginManifestException(sprintf('ui.admin.server.%s is required.', $field));
                }
            }
        }

        if (in_array(Permissions::HTTP_REQUEST, $permissions, true)) {
            $hosts = $data['http']['allowedHosts'] ?? [];
            if (!is_array($hosts) || empty($hosts)) {
                throw new InvalidPluginManifestException('Plugins requesting http.request must declare http.allowedHosts.');
            }
        }

        $uiTheme = $ui['theme'] ?? null;
        if ($uiTheme !== null) {
            if (!is_array($uiTheme)) {
                throw new InvalidPluginManifestException('Manifest "ui.theme" must be an object.');
            }

            if (!in_array(Permissions::UI_THEME, $permissions, true)) {
                throw new InvalidPluginManifestException('Plugins declaring ui.theme must request the "ui.theme" permission.');
            }

            $registry = new DesignTokenRegistry();
            foreach ((array) ($uiTheme['tokens'] ?? []) as $key => $value) {
                if (!is_string($key) || !is_string($value)) {
                    throw new InvalidPluginManifestException('ui.theme.tokens must be string key/value pairs.');
                }
                if (!$registry->isAllowedKey($key)) {
                    throw new InvalidPluginManifestException(sprintf('ui.theme token "%s" is not in the design token whitelist.', $key));
                }
            }

            $surfaces = (array) ($uiTheme['surfaces'] ?? ['client']);
            foreach ($surfaces as $surface) {
                if (!is_string($surface) || !in_array($surface, ['client', 'admin'], true)) {
                    throw new InvalidPluginManifestException('ui.theme.surfaces must contain "client" and/or "admin".');
                }
            }
        }

        return PluginManifest::fromArray($data);
    }

    private function namespacePrefixFromEntry(string $entry): string
    {
        $parts = explode('\\', $entry);
        array_pop($parts);

        return implode('\\', $parts) . '\\';
    }

    private function assertClassInNamespace(string $class, string $namespacePrefix, string $context): void
    {
        if (!str_starts_with($class, $namespacePrefix)) {
            throw new InvalidPluginManifestException(sprintf(
                '%s class "%s" must be within namespace "%s".',
                ucfirst($context),
                $class,
                rtrim($namespacePrefix, '\\')
            ));
        }
    }

    private function assertHandlerInNamespace(string $handler, string $namespacePrefix): void
    {
        $class = str_contains($handler, '@') ? explode('@', $handler, 2)[0] : $handler;
        $this->assertClassInNamespace($class, $namespacePrefix, 'API handler');
    }
}
