<?php

namespace Pterodactyl\Plugins\Accessors;

use Pterodactyl\Plugins\PermissionGate;
use Pterodactyl\Plugins\Permissions;
use Pterodactyl\Plugins\Exceptions\PluginException;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class SettingsAccessor
{
    public function __construct(
        private readonly string $pluginId,
        private readonly PermissionGate $gate,
        private readonly SettingsRepositoryInterface $settings,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->gate->authorize(Permissions::SETTINGS_READ);

        return $this->settings->get($this->scopedKey($key), $default);
    }

    public function set(string $key, ?string $value = null): void
    {
        $this->gate->authorize(Permissions::SETTINGS_WRITE);

        $this->settings->set($this->scopedKey($key), $value);
    }

    public function forget(string $key): void
    {
        $this->gate->authorize(Permissions::SETTINGS_WRITE);

        $this->settings->forget($this->scopedKey($key));
    }

    private function scopedKey(string $key): string
    {
        $key = ltrim($key, '.');

        if (str_starts_with($key, 'plugins.')) {
            $expected = 'plugins.' . $this->pluginId . '.';
            if (!str_starts_with($key, $expected)) {
                throw new PluginException(sprintf(
                    'Settings key "%s" is outside the allowed namespace for plugin "%s".',
                    $key,
                    $this->pluginId
                ));
            }

            return $key;
        }

        return 'plugins.' . $this->pluginId . '.' . $key;
    }
}
