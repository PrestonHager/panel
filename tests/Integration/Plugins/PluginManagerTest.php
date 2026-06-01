<?php

namespace Pterodactyl\Tests\Integration\Plugins;

use Pterodactyl\Models\Plugin;
use Pterodactyl\Models\PluginData;
use Pterodactyl\Events\Server\Created;
use Pterodactyl\Services\Plugins\PluginManager;
use Pterodactyl\Services\Plugins\PluginRegistry;
use Pterodactyl\Plugins\PluginEventDispatcher;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PluginManagerTest extends IntegrationTestCase
{
    use DatabaseTransactions;

    private string $fixturePath;

    public function setUp(): void
    {
        parent::setUp();
        $this->fixturePath = base_path('tests/Fixtures/plugins/test-plugin');
    }

    public function tearDown(): void
    {
        $directory = PluginRegistry::directoryFor('com.pterodactyl.test-plugin');
        if (is_dir($directory)) {
            $this->removeDirectory($directory);
        }

        parent::tearDown();
    }

    public function testInstallEnableAndHookExecution(): void
    {
        /** @var PluginManager $manager */
        $manager = app(PluginManager::class);

        $plugin = $manager->installFromPath($this->fixturePath);
        $this->assertInstanceOf(Plugin::class, $plugin);
        $this->assertFalse($plugin->enabled);

        $server = $this->createServerModel();
        $manager->enable($plugin->fresh());

        app(PluginRegistry::class)->flush();
        app(PluginRegistry::class)->load();

        app(PluginEventDispatcher::class)->dispatch('server.created', new Created($server));

        $this->assertDatabaseHas('plugin_data', [
            'plugin_id' => 'com.pterodactyl.test-plugin',
            'subject_type' => 'server',
            'subject_id' => $server->id,
            'key' => 'hook_ran',
        ]);

        $record = PluginData::query()->where('plugin_id', 'com.pterodactyl.test-plugin')->first();
        $this->assertTrue($record->value['ran'] ?? false);
    }

    public function testUninstallRemovesPlugin(): void
    {
        /** @var PluginManager $manager */
        $manager = app(PluginManager::class);
        $plugin = $manager->installFromPath($this->fixturePath);

        $manager->uninstall($plugin);

        $this->assertDatabaseMissing('plugins', ['id' => 'com.pterodactyl.test-plugin']);
        $this->assertDirectoryDoesNotExist(PluginRegistry::directoryFor('com.pterodactyl.test-plugin'));
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
