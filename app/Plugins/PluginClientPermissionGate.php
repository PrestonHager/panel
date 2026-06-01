<?php

namespace Pterodactyl\Plugins;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Plugin;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Plugins\Exceptions\PluginPermissionDeniedException;

class PluginClientPermissionGate
{
    public function allows(User $user, Server $server, Plugin $plugin, string $permission): bool
    {
        if ($user->root_admin || $user->id === $server->owner_id) {
            return true;
        }

        if (!is_array($plugin->client_permissions) || !array_key_exists($permission, $plugin->client_permissions)) {
            return false;
        }

        $subuser = Subuser::query()
            ->where('server_id', $server->id)
            ->where('user_id', $user->id)
            ->first();

        if (is_null($subuser)) {
            return false;
        }

        return $subuser->pluginPermissions()
            ->where('plugin_id', $plugin->id)
            ->where('permission', $permission)
            ->exists();
    }

    public function authorize(User $user, Server $server, Plugin $plugin, string $permission): void
    {
        if (!$this->allows($user, $server, $plugin, $permission)) {
            throw new PluginPermissionDeniedException($plugin->id, $permission);
        }
    }
}
