<?php

namespace Pterodactyl\Tests\Unit\Panel;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Services\Panel\UpdateOverviewService;
use Pterodactyl\Services\Panel\UpstreamVersionService;
use Pterodactyl\Services\Plugins\PluginVersionService;

class UpdateOverviewServiceTest extends TestCase
{
    public function testSummaryAggregatesPanelAndPluginStatus(): void
    {
        $upstream = $this->createMock(UpstreamVersionService::class);
        $upstream->method('latestRelease')->willReturn([
            'tag' => 'v2.0.0',
            'version' => '2.0.0',
            'url' => 'https://github.com/pterodactyl/panel/releases/latest',
        ]);
        $upstream->method('isUpdateAvailable')->willReturn(true);

        $plugins = $this->createMock(PluginVersionService::class);
        $plugins->method('checkAll')->willReturn([
            'com.example.one' => [
                'update_available' => true,
                'installed_version' => '1.0.0',
                'latest_version' => '1.1.0',
                'latest_ref' => 'v1.1.0',
                'remote_commit' => null,
                'release_url' => null,
                'check_method' => 'release',
            ],
            'com.example.two' => [
                'update_available' => false,
                'installed_version' => '2.0.0',
                'latest_version' => '2.0.0',
                'latest_ref' => 'v2.0.0',
                'remote_commit' => null,
                'release_url' => null,
                'check_method' => 'release',
            ],
        ]);

        $service = new UpdateOverviewService($upstream, $plugins);

        $pluginStatus = $service->pluginStatus();

        $this->assertSame(1, $pluginStatus['outdated_count']);
        $this->assertSame(['com.example.one'], $pluginStatus['outdated_ids']);
        $this->assertTrue($pluginStatus['checks']['com.example.one']['update_available']);
        $this->assertFalse($pluginStatus['checks']['com.example.two']['update_available']);
    }
}
