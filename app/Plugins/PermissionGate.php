<?php

namespace Pterodactyl\Plugins;

use Pterodactyl\Plugins\Exceptions\PluginPermissionDeniedException;

class PermissionGate
{
    /**
     * @param string[] $granted
     */
    public function __construct(
        private readonly string $pluginId,
        private readonly array $granted,
    ) {
    }

    public function allows(string $permission): bool
    {
        return in_array($permission, $this->granted, true);
    }

    public function authorize(string $permission): void
    {
        if (!$this->allows($permission)) {
            throw new PluginPermissionDeniedException($this->pluginId, $permission);
        }
    }
}
