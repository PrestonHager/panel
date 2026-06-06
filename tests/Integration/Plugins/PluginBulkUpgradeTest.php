<?php

namespace Pterodactyl\Tests\Integration\Plugins;

use Mockery;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Plugin;
use Pterodactyl\Services\Plugins\PluginManager;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PluginBulkUpgradeTest extends IntegrationTestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function testNonAdminCannotBulkUpgradePlugins(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/admin/plugins/upgrade', ['plugins' => ['com.example.test']])
            ->assertForbidden();
    }

    public function testBulkUpgradeRequiresValidPluginIds(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/plugins/upgrade', ['plugins' => ['not-a-real-plugin']])
            ->assertSessionHasErrors(['plugins.0']);
    }

    public function testAdminCanUpgradeSelectedPlugins(): void
    {
        $admin = User::factory()->admin()->create();
        $plugin = Plugin::query()->create([
            'id' => 'com.example.bulk',
            'name' => 'Bulk Test Plugin',
            'version' => '1.0.0',
            'source_url' => 'https://github.com/example/bulk-plugin',
            'source_ref' => 'main',
            'commit_sha' => 'abc123',
            'enabled' => false,
            'permissions' => [],
            'installed_at' => now(),
        ]);

        $manager = Mockery::mock(PluginManager::class);
        $manager->shouldReceive('upgradePlugins')
            ->once()
            ->with([$plugin->id], Mockery::type('array'))
            ->andReturn([
                'success' => [$plugin->id],
                'failed' => [],
                'skipped' => [],
            ]);

        $this->app->instance(PluginManager::class, $manager);

        $this->mock(\Pterodactyl\Services\Plugins\PluginVersionService::class, function ($mock) use ($plugin) {
            $mock->shouldReceive('checkAll')->andReturn([
                $plugin->id => [
                    'update_available' => true,
                    'installed_version' => '1.0.0',
                    'latest_version' => '1.1.0',
                    'latest_ref' => 'v1.1.0',
                    'remote_commit' => null,
                    'release_url' => null,
                    'check_method' => 'release',
                ],
            ]);
        });

        $this->actingAs($admin)
            ->post('/admin/plugins/upgrade', ['plugins' => [$plugin->id]])
            ->assertRedirect(route('admin.plugins'));
    }

    public function testUpgradeAllOutdatedRedirectsWhenNothingOutdated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->mock(\Pterodactyl\Services\Plugins\PluginVersionService::class, function ($mock) {
            $mock->shouldReceive('checkAll')->andReturn([]);
        });

        $this->actingAs($admin)
            ->post('/admin/plugins/upgrade-outdated')
            ->assertRedirect(route('admin.plugins'));
    }
}
