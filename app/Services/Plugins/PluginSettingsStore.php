<?php

namespace Pterodactyl\Services\Plugins;

use Pterodactyl\Models\Plugin;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\PluginData;
use Pterodactyl\Plugins\PluginSettingsField;
use Pterodactyl\Plugins\PluginSettingsSchema;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class PluginSettingsStore
{
    public const SERVER_SETTINGS_KEY = 'plugin_settings';

    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function readAdminConfig(Plugin $plugin, PluginSettingsSchema $schema): array
    {
        $config = $plugin->config ?? [];
        $values = [];

        foreach ($schema->forSurfaceAndStorage('admin', 'config') as $field) {
            $value = $config[$field->key] ?? $field->default;
            if ($field->isPassword() && is_string($value) && $value !== '') {
                $values[$field->key] = PluginSettingsField::PASSWORD_MASK;
            } else {
                $values[$field->key] = $value;
            }
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    public function readServerSettings(Plugin $plugin, Server $server, PluginSettingsSchema $schema): array
    {
        $stored = $this->getServerSettingsBlob($plugin, $server);
        $values = [];

        foreach ($schema->forSurfaceAndStorage('client', 'server') as $field) {
            $values[$field->key] = $stored[$field->key] ?? $field->default;
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    public function readGlobalSettings(Plugin $plugin, PluginSettingsSchema $schema): array
    {
        $values = [];

        foreach ($schema->forSurfaceAndStorage('client', 'global') as $field) {
            $values[$field->key] = $this->settings->get(
                $this->scopedKey($plugin->id, $field->key),
                $field->default
            );
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function writeAdminConfig(Plugin $plugin, PluginSettingsSchema $schema, array $values): array
    {
        $config = $plugin->config ?? [];

        foreach ($schema->forSurfaceAndStorage('admin', 'config') as $field) {
            if (!array_key_exists($field->key, $values)) {
                continue;
            }

            $value = $values[$field->key];
            if ($field->isPassword() && $value === PluginSettingsField::PASSWORD_MASK) {
                continue;
            }

            $config[$field->key] = $value;
        }

        $plugin->config = $config;
        $plugin->save();

        return $config;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function writeServerSettings(Plugin $plugin, Server $server, PluginSettingsSchema $schema, array $values): void
    {
        $stored = $this->getServerSettingsBlob($plugin, $server);

        foreach ($schema->forSurfaceAndStorage('client', 'server') as $field) {
            if (!array_key_exists($field->key, $values)) {
                continue;
            }

            $stored[$field->key] = $values[$field->key];
        }

        PluginData::query()->updateOrCreate(
            [
                'plugin_id' => $plugin->id,
                'subject_type' => 'server',
                'subject_id' => $server->id,
                'key' => self::SERVER_SETTINGS_KEY,
            ],
            ['value' => $stored],
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    public function writeGlobalSettings(Plugin $plugin, PluginSettingsSchema $schema, array $values): void
    {
        foreach ($schema->forSurfaceAndStorage('client', 'global') as $field) {
            if (!array_key_exists($field->key, $values)) {
                continue;
            }

            $stored = $values[$field->key];
            $this->settings->set(
                $this->scopedKey($plugin->id, $field->key),
                is_scalar($stored) ? (string) $stored : json_encode($stored)
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getServerSettingsBlob(Plugin $plugin, Server $server): array
    {
        $record = PluginData::query()
            ->where('plugin_id', $plugin->id)
            ->where('subject_type', 'server')
            ->where('subject_id', $server->id)
            ->where('key', self::SERVER_SETTINGS_KEY)
            ->first();

        $value = $record?->value;

        return is_array($value) ? $value : [];
    }

    private function scopedKey(string $pluginId, string $key): string
    {
        return 'plugins.' . $pluginId . '.' . $key;
    }
}
