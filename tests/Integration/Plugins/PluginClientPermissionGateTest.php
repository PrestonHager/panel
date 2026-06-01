<?php

namespace Pterodactyl\Tests\Integration\Plugins;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Plugin;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Plugins\PluginClientPermissionGate;
use Pterodactyl\Plugins\Exceptions\PluginPermissionDeniedException;
use Pterodactyl\Tests\Integration\IntegrationTestCase;

class PluginClientPermissionGateTest extends IntegrationTestCase
{
    public function testOwnerHasAllPluginPermissions(): void
    {
        $user = User::factory()->create();
        $server = $this->createServerModel(['owner_id' => $user->id]);
        $plugin = Plugin::query()->create([
            'id' => 'com.example.gate-test',
            'name' => 'Gate Test',
            'version' => '1.0.0',
            'source_url' => 'file://test',
            'source_ref' => 'local',
            'enabled' => true,
            'permissions' => [],
            'client_permissions' => ['records.read' => 'Read'],
            'installed_at' => now(),
        ]);

        $gate = app(PluginClientPermissionGate::class);
        $gate->authorize($user, $server, $plugin, 'records.read');
        $this->assertTrue(true);
    }

    public function testSubuserRequiresExplicitGrant(): void
    {
        $owner = User::factory()->create();
        $subuserUser = User::factory()->create();
        $server = $this->createServerModel(['owner_id' => $owner->id]);
        $plugin = Plugin::query()->create([
            'id' => 'com.example.gate-test2',
            'name' => 'Gate Test 2',
            'version' => '1.0.0',
            'source_url' => 'file://test',
            'source_ref' => 'local',
            'enabled' => true,
            'permissions' => [],
            'client_permissions' => ['records.read' => 'Read'],
            'installed_at' => now(),
        ]);

        $subuser = Subuser::query()->create([
            'user_id' => $subuserUser->id,
            'server_id' => $server->id,
            'permissions' => ['websocket.connect'],
        ]);

        $gate = app(PluginClientPermissionGate::class);

        $this->expectException(PluginPermissionDeniedException::class);
        $gate->authorize($subuserUser, $server, $plugin, 'records.read');
    }
}
