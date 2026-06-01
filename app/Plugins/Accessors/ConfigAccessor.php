<?php

namespace Pterodactyl\Plugins\Accessors;

use Pterodactyl\Plugins\PermissionGate;
use Pterodactyl\Plugins\Permissions;

class ConfigAccessor
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly PermissionGate $gate,
        private readonly array $config,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->gate->authorize(Permissions::CONFIG_READ);

        return $this->config[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $this->gate->authorize(Permissions::CONFIG_READ);

        return $this->config;
    }
}
