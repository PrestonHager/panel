<?php

namespace Pterodactyl\Tests\Integration\Plugins;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Plugin;
use Pterodactyl\Services\Plugins\PluginRegistry;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PluginAdminTest extends IntegrationTestCase
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

    public function testAdminCanViewPluginsIndex(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/plugins')
            ->assertOk()
            ->assertSee('Installed Plugins');
    }

    public function testNonAdminCannotAccessPlugins(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/plugins')
            ->assertForbidden();
    }

    public function testInstallFromPathViaManagerShowsInDatabase(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = app(\Pterodactyl\Services\Plugins\PluginManager::class);
        $manager->installFromPath(base_path('tests/Fixtures/plugins/test-plugin'));

        $this->actingAs($admin)
            ->get('/admin/plugins')
            ->assertOk()
            ->assertSee('Test Plugin');

        $this->assertDatabaseHas('plugins', ['id' => 'com.pterodactyl.test-plugin']);
    }

    public function testEnablePluginFromAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = app(\Pterodactyl\Services\Plugins\PluginManager::class);
        $plugin = $manager->installFromPath(base_path('tests/Fixtures/plugins/test-plugin'));

        $this->actingAs($admin)
            ->post('/admin/plugins/view/' . $plugin->id . '/enable')
            ->assertRedirect();

        $this->assertTrue(Plugin::query()->find($plugin->id)->enabled);
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
