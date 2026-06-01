<?php

namespace Pterodactyl\Services\Plugins;

use Pterodactyl\Plugins\HookMap;
use Pterodactyl\Plugins\Permissions;
use Pterodactyl\Plugins\PluginManifest;
use Pterodactyl\Plugins\Exceptions\InvalidPluginManifestException;

class ManifestValidator
{
    public const MANIFEST_FILENAME = 'plugin.json';

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

        $permissions = $data['permissions'] ?? [];
        if (!is_array($permissions)) {
            throw new InvalidPluginManifestException('Manifest "permissions" must be an array.');
        }

        foreach ($permissions as $permission) {
            if (!is_string($permission) || !Permissions::isValid($permission)) {
                throw new InvalidPluginManifestException(sprintf('Unknown permission "%s" in manifest.', (string) $permission));
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
        }

        return PluginManifest::fromArray($data);
    }
}
