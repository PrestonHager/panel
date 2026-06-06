<?php

namespace Pterodactyl\Services\Plugins;

use Pterodactyl\Models\Plugin;
use Pterodactyl\Plugins\PluginSettingsField;
use Pterodactyl\Plugins\PluginSettingsSchema;
use Pterodactyl\Plugins\Exceptions\PluginException;

class PluginSettingsSchemaService
{
    public const SETTINGS_FILENAME = 'settings.json';

    public function __construct(
        private readonly ManifestValidator $manifestValidator,
    ) {
    }

    public function forPlugin(Plugin $plugin): PluginSettingsSchema
    {
        $directory = PluginRegistry::directoryFor($plugin->id);
        if (!is_dir($directory)) {
            return new PluginSettingsSchema([]);
        }

        $settingsPath = rtrim($directory, '/') . '/' . self::SETTINGS_FILENAME;
        if (is_file($settingsPath)) {
            return $this->parseSettingsFile($settingsPath, $plugin);
        }

        try {
            $manifest = $this->manifestValidator->readFromDirectory($directory);
        } catch (\Throwable) {
            return new PluginSettingsSchema([]);
        }

        if (empty($manifest->configSchema)) {
            return new PluginSettingsSchema([]);
        }

        return $this->fromConfigSchema($manifest->configSchema, $plugin);
    }

    /**
     * @return string[]
     */
    public function validateSettingsFile(string $directory, Plugin $plugin): array
    {
        $warnings = [];
        $path = rtrim($directory, '/') . '/' . self::SETTINGS_FILENAME;
        if (!is_file($path)) {
            return $warnings;
        }

        try {
            $schema = $this->parseSettingsFile($path, $plugin);
        } catch (PluginException $exception) {
            $warnings[] = $exception->getMessage();

            return $warnings;
        }

        foreach ($schema->fields as $field) {
            if (!in_array($field->type, PluginSettingsField::TYPES, true)) {
                $warnings[] = sprintf('Unknown field type "%s" for key "%s".', $field->type, $field->key);
            }
        }

        return $warnings;
    }

    private function parseSettingsFile(string $path, Plugin $plugin): PluginSettingsSchema
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new PluginException('Unable to read plugin settings schema.');
        }

        $data = json_decode($contents, true);
        if (!is_array($data) || !isset($data['fields']) || !is_array($data['fields'])) {
            throw new PluginException('Plugin settings schema must contain a "fields" array.');
        }

        $fields = [];
        foreach ($data['fields'] as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $fields[] = $this->parseField($raw, $plugin);
        }

        return new PluginSettingsSchema($fields);
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function parseField(array $raw, Plugin $plugin): PluginSettingsField
    {
        $key = (string) ($raw['key'] ?? '');
        if ($key === '') {
            throw new PluginException('Each settings field must have a "key".');
        }

        $type = (string) ($raw['type'] ?? 'string');
        $surfaces = array_values(array_filter(
            (array) ($raw['surfaces'] ?? ['admin']),
            fn ($surface) => is_string($surface) && in_array($surface, PluginSettingsField::SURFACES, true)
        ));
        if (empty($surfaces)) {
            $surfaces = ['admin'];
        }

        $storage = (string) ($raw['storage'] ?? 'config');
        if (!in_array($storage, PluginSettingsField::STORAGES, true)) {
            throw new PluginException(sprintf('Invalid storage "%s" for field "%s".', $storage, $key));
        }

        if ($storage === 'config' && in_array('client', $surfaces, true)) {
            throw new PluginException(sprintf('Field "%s" with storage "config" cannot be exposed on the client surface.', $key));
        }

        $permission = isset($raw['permission']) ? (string) $raw['permission'] : null;
        if (in_array('client', $surfaces, true)) {
            if (!$permission) {
                throw new PluginException(sprintf('Client-visible field "%s" must declare a "permission".', $key));
            }
            $clientPermissions = $plugin->client_permissions ?? [];
            if (!array_key_exists($permission, $clientPermissions)) {
                throw new PluginException(sprintf(
                    'Field "%s" references client permission "%s" which is not declared in plugin.json clientPermissions.',
                    $key,
                    $permission
                ));
            }
        }

        $options = [];
        foreach ((array) ($raw['options'] ?? []) as $option) {
            if (!is_array($option) || !isset($option['value'])) {
                continue;
            }
            $options[] = [
                'value' => (string) $option['value'],
                'label' => (string) ($option['label'] ?? $option['value']),
            ];
        }

        return new PluginSettingsField(
            key: $key,
            type: $type,
            label: (string) ($raw['label'] ?? $key),
            surfaces: $surfaces,
            storage: $storage,
            description: isset($raw['description']) ? (string) $raw['description'] : null,
            placeholder: isset($raw['placeholder']) ? (string) $raw['placeholder'] : null,
            default: $raw['default'] ?? null,
            required: (bool) ($raw['required'] ?? false),
            permission: $permission,
            options: $options,
            min: isset($raw['min']) ? (int) $raw['min'] : null,
            max: isset($raw['max']) ? (int) $raw['max'] : null,
            sensitive: (bool) ($raw['sensitive'] ?? ($type === 'password')),
            audit: (bool) ($raw['audit'] ?? false),
            ownerOnly: (bool) ($raw['ownerOnly'] ?? false),
        );
    }

    /**
     * @param array<string, mixed> $configSchema
     */
    private function fromConfigSchema(array $configSchema, Plugin $plugin): PluginSettingsSchema
    {
        $properties = $configSchema['properties'] ?? [];
        if (!is_array($properties)) {
            return new PluginSettingsSchema([]);
        }

        $required = array_flip((array) ($configSchema['required'] ?? []));
        $fields = [];

        foreach ($properties as $key => $definition) {
            if (!is_string($key) || !is_array($definition)) {
                continue;
            }

            $type = $this->mapJsonSchemaType($definition);
            $fields[] = new PluginSettingsField(
                key: $key,
                type: $type,
                label: (string) ($definition['title'] ?? $key),
                surfaces: ['admin'],
                storage: 'config',
                description: isset($definition['description']) ? (string) $definition['description'] : null,
                default: $definition['default'] ?? null,
                required: isset($required[$key]),
                sensitive: $type === 'password',
            );
        }

        return new PluginSettingsSchema($fields, fromFallback: true);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function mapJsonSchemaType(array $definition): string
    {
        $type = $definition['type'] ?? 'string';
        if (is_array($type)) {
            $type = $type[0] ?? 'string';
        }

        return match ($type) {
            'boolean' => 'boolean',
            'integer' => 'integer',
            'number' => 'number',
            'array' => 'json',
            'object' => 'json',
            default => 'string',
        };
    }
}
