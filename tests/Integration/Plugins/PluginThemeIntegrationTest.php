<?php

namespace Pterodactyl\Tests\Integration\Plugins;

use Pterodactyl\Models\User;
use Pterodactyl\Services\Plugins\PluginManager;
use Pterodactyl\Services\Plugins\PluginRegistry;
use Pterodactyl\Services\Plugins\PluginThemeService;
use Pterodactyl\Services\Plugins\ManifestValidator;
use Pterodactyl\Plugins\Exceptions\InvalidPluginManifestException;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PluginThemeIntegrationTest extends IntegrationTestCase
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

    public function testManifestRejectsThemeWithoutPermission(): void
    {
        $validator = app(ManifestValidator::class);

        $this->expectException(InvalidPluginManifestException::class);

        $validator->validate([
            'id' => 'com.example.theme',
            'name' => 'Theme',
            'version' => '1.0.0',
            'entry' => 'Com\\Example\\Theme\\Plugin',
            'permissions' => ['ui.register'],
            'ui' => [
                'theme' => [
                    'tokens' => ['color.primary' => '#000000'],
                ],
            ],
        ]);
    }

    public function testManifestRejectsUnknownThemeToken(): void
    {
        $validator = app(ManifestValidator::class);

        $this->expectException(InvalidPluginManifestException::class);

        $validator->validate([
            'id' => 'com.example.theme',
            'name' => 'Theme',
            'version' => '1.0.0',
            'entry' => 'Com\\Example\\Theme\\Plugin',
            'permissions' => ['ui.theme'],
            'ui' => [
                'theme' => [
                    'tokens' => ['color.not.whitelisted' => '#000000'],
                ],
            ],
        ]);
    }

    public function testPanelThemeRouteReturnsApprovedOverlay(): void
    {
        $manager = app(PluginManager::class);
        $plugin = $manager->installFromPath(base_path('tests/Fixtures/plugins/test-plugin'));
        $manager->enable($plugin);
        $plugin->update([
            'approved_theme' => [
                'enabled' => true,
                'surfaces' => ['client'],
                'token_keys' => ['color.primary'],
            ],
        ]);

        app(PluginThemeService::class)->regenerateOverlayCache();

        $this->get('/plugins/panel-theme.css')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=UTF-8')
            ->assertSee('--pt-color-primary: #7c3aed;', false);
    }

    public function testAdminCanApproveThemeOverlay(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = app(PluginManager::class);
        $plugin = $manager->installFromPath(base_path('tests/Fixtures/plugins/test-plugin'));

        $this->actingAs($admin)
            ->patch('/admin/plugins/view/' . $plugin->id . '/permissions', [
                'approved_permissions' => $plugin->permissions,
                'approved_theme_enabled' => '1',
                'approved_theme_surfaces' => ['client'],
                'approved_theme_token_keys' => ['color.primary'],
            ])
            ->assertRedirect();

        $plugin = $plugin->fresh();
        $this->assertTrue($plugin->approved_theme['enabled']);
        $this->assertSame(['client'], $plugin->approved_theme['surfaces']);
        $this->assertSame(['color.primary'], $plugin->approved_theme['token_keys']);
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
