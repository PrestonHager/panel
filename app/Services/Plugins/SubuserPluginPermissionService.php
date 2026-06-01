<?php

namespace Pterodactyl\Services\Plugins;

use Pterodactyl\Models\Plugin;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\SubuserPluginPermission;
use Pterodactyl\Plugins\Exceptions\PluginException;

class SubuserPluginPermissionService
{
    /**
     * @param array<string, string[]> $pluginPermissions
     */
    public function sync(Subuser $subuser, array $pluginPermissions): void
    {
        SubuserPluginPermission::query()->where('subuser_id', $subuser->id)->delete();

        foreach ($pluginPermissions as $pluginId => $permissions) {
            if (!is_string($pluginId) || !is_array($permissions)) {
                continue;
            }

            $plugin = Plugin::query()->find($pluginId);
            if (is_null($plugin) || !$plugin->enabled) {
                continue;
            }

            $allowed = array_keys($plugin->client_permissions ?? []);

            foreach (array_unique($permissions) as $permission) {
                if (!is_string($permission) || !in_array($permission, $allowed, true)) {
                    throw new PluginException(sprintf(
                        'Permission "%s" is not valid for plugin "%s".',
                        $permission,
                        $pluginId
                    ));
                }

                SubuserPluginPermission::query()->create([
                    'subuser_id' => $subuser->id,
                    'plugin_id' => $pluginId,
                    'permission' => $permission,
                ]);
            }
        }
    }

    /**
     * @return array<string, string[]>
     */
    public function forSubuser(Subuser $subuser): array
    {
        $grouped = [];

        foreach ($subuser->pluginPermissions as $row) {
            $grouped[$row->plugin_id][] = $row->permission;
        }

        return $grouped;
    }
}
