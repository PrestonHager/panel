<?php

namespace Pterodactyl\Services\Plugins;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Plugin;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Subuser;

class GetUserPluginPermissionsService
{
    /**
     * @return array<string, string[]>
     */
    public function handle(Server $server, User $user): array
    {
        $plugins = Plugin::query()->where('enabled', true)->get();
        $output = [];

        foreach ($plugins as $plugin) {
            $clientPermissions = $plugin->client_permissions ?? [];
            if (empty($clientPermissions)) {
                continue;
            }

            if ($user->root_admin || $user->id === $server->owner_id) {
                $output[$plugin->id] = array_keys($clientPermissions);

                continue;
            }

            $subuser = Subuser::query()
                ->where('server_id', $server->id)
                ->where('user_id', $user->id)
                ->with('pluginPermissions')
                ->first();

            if (is_null($subuser)) {
                continue;
            }

            $granted = $subuser->pluginPermissions
                ->where('plugin_id', $plugin->id)
                ->pluck('permission')
                ->values()
                ->all();

            if (!empty($granted)) {
                $output[$plugin->id] = $granted;
            }
        }

        return $output;
    }
}
