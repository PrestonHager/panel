<?php

namespace Pterodactyl\Tests\Unit\Plugins;

use Pterodactyl\Models\Plugin;
use Pterodactyl\Services\Plugins\PluginManager;
use Pterodactyl\Services\Plugins\PluginRegistry;
use Pterodactyl\Services\Plugins\PluginThemeService;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PluginThemeServiceTest extends IntegrationTestCase
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

    public function testMergedApprovedOverlaysReturnsOnlyApprovedKeys(): void
    {
        $manager = app(PluginManager::class);
        $plugin = $manager->installFromPath(base_path('tests/Fixtures/plugins/test-plugin'));
        $plugin->update([
            'enabled' => true,
            'approved_theme' => [
                'enabled' => true,
                'surfaces' => ['client'],
                'token_keys' => ['color.primary'],
            ],
        ]);

        $service = app(PluginThemeService::class);
        $overlays = $service->mergedApprovedOverlays('client');

        $this->assertSame('#7c3aed', $overlays['color.primary']);
    }

    public function testUnapprovedSurfaceProducesNoOverlay(): void
    {
        $manager = app(PluginManager::class);
        $plugin = $manager->installFromPath(base_path('tests/Fixtures/plugins/test-plugin'));
        $plugin->update([
            'enabled' => true,
            'approved_theme' => [
                'enabled' => true,
                'surfaces' => ['admin'],
                'token_keys' => ['color.primary'],
            ],
        ]);

        $service = app(PluginThemeService::class);

        $this->assertSame([], $service->mergedApprovedOverlays('client'));
        $this->assertSame('#7c3aed', $service->mergedApprovedOverlays('admin')['color.primary']);
    }

    public function testDisabledThemeProducesNoOverlay(): void
    {
        $manager = app(PluginManager::class);
        $plugin = $manager->installFromPath(base_path('tests/Fixtures/plugins/test-plugin'));
        $plugin->update([
            'enabled' => true,
            'approved_theme' => [
                'enabled' => false,
                'surfaces' => ['client'],
                'token_keys' => ['color.primary'],
            ],
        ]);

        $service = app(PluginThemeService::class);

        $this->assertSame([], $service->mergedApprovedOverlays('client'));
    }

    public function testRegenerateOverlayCacheWritesCss(): void
    {
        $manager = app(PluginManager::class);
        $plugin = $manager->installFromPath(base_path('tests/Fixtures/plugins/test-plugin'));
        $plugin->update([
            'enabled' => true,
            'approved_theme' => [
                'enabled' => true,
                'surfaces' => ['client'],
                'token_keys' => ['color.primary'],
            ],
        ]);

        $service = app(PluginThemeService::class);
        $service->regenerateOverlayCache();

        $css = $service->overlayCss();

        $this->assertStringContainsString('--pt-color-primary: #7c3aed;', $css);
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
