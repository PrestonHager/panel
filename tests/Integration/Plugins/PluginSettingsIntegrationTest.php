<?php

namespace Pterodactyl\Tests\Integration\Plugins;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Plugin;
use Pterodactyl\Services\Plugins\PluginSettingsStore;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Plugins\PluginRegistry;
use Pterodactyl\Services\Plugins\PluginManager;
use Pterodactyl\Services\Plugins\PluginSettingsSchemaService;
use Pterodactyl\Services\Plugins\PluginSettingsStore;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PluginSettingsIntegrationTest extends IntegrationTestCase
{
    use DatabaseTransactions;

    public function tearDown(): void
    {
        $directory = PluginRegistry::directoryFor('com.pterodactyl.test-plugin');
        if (is_dir($directory)) {
            $this->removeDirectory($directory);
        }

        parent::tearDown();
    }

    public function testAdminStructuredSettingsRoundTrip(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = app(PluginManager::class);
        $plugin = $manager->installFromPath(base_path('tests/Fixtures/plugins/test-plugin'));

        $schema = app(PluginSettingsSchemaService::class)->forPlugin($plugin);
        $store = app(PluginSettingsStore::class);
        $store->writeAdminConfig($plugin, $schema, ['example_admin_setting' => 'configured']);

        $this->actingAs($admin)
            ->get('/admin/plugins/view/' . $plugin->id . '/settings')
            ->assertOk()
            ->assertSee('Example Admin Setting')
            ->assertSee('plugin-settings-form');

        $values = $store->readAdminConfig($plugin->fresh(), $schema);
        $this->assertSame('configured', $values['example_admin_setting']);
    }

    public function testClientSettingsRequiresPermission(): void
    {
        $manager = app(PluginManager::class);
        $plugin = $manager->installFromPath(base_path('tests/Fixtures/plugins/test-plugin'));
        $manager->enable($plugin);

        $owner = User::factory()->create();
        $server = Server::factory()->create(['owner_id' => $owner->id]);
        $subuser = User::factory()->create();

        $server->subusers()->create([
            'user_id' => $subuser->id,
        ]);

        $this->actingAs($subuser)
            ->getJson('/api/client/servers/' . $server->uuid . '/plugins/' . $plugin->id . '/settings')
            ->assertOk()
            ->assertJsonPath('attributes.schema.fields', []);

        $this->actingAs($owner)
            ->getJson('/api/client/servers/' . $server->uuid . '/plugins/' . $plugin->id . '/settings')
            ->assertOk()
            ->assertJsonPath('attributes.schema.fields.0.key', 'server_flag');
    }

    public function testClientCanUpdateServerScopedSetting(): void
    {
        $manager = app(PluginManager::class);
        $plugin = $manager->installFromPath(base_path('tests/Fixtures/plugins/test-plugin'));
        $manager->enable($plugin);

        $owner = User::factory()->create();
        $server = Server::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->patchJson('/api/client/servers/' . $server->uuid . '/plugins/' . $plugin->id . '/settings', [
                'settings' => ['server_flag' => true],
            ])
            ->assertOk()
            ->assertJsonPath('attributes.values.server_flag', true);
    }

    public function testWriteAdminConfigPreservesMaskedPassword(): void
    {
        $plugin = Plugin::query()->create([
            'id' => 'com.example.store-test',
            'name' => 'Store Test',
            'version' => '1.0.0',
            'source_url' => 'local://test',
            'source_ref' => 'main',
            'enabled' => false,
            'permissions' => [],
            'config' => ['secret' => 'original-secret'],
            'installed_at' => now(),
        ]);

        $field = new \Pterodactyl\Plugins\PluginSettingsField(
            key: 'secret',
            type: 'password',
            label: 'Secret',
            surfaces: ['admin'],
            storage: 'config',
            sensitive: true,
        );
        $other = new \Pterodactyl\Plugins\PluginSettingsField(
            key: 'name',
            type: 'string',
            label: 'Name',
            surfaces: ['admin'],
            storage: 'config',
        );
        $schema = new \Pterodactyl\Plugins\PluginSettingsSchema([$field, $other]);

        $store = app(PluginSettingsStore::class);
        $store->writeAdminConfig($plugin, $schema, [
            'secret' => \Pterodactyl\Plugins\PluginSettingsField::PASSWORD_MASK,
            'name' => 'updated',
        ]);

        $plugin = $plugin->fresh();
        $this->assertSame('original-secret', $plugin->config['secret']);
        $this->assertSame('updated', $plugin->config['name']);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($directory);
    }
}
