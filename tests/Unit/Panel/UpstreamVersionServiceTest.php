<?php

namespace Pterodactyl\Tests\Unit\Panel;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Services\Panel\UpstreamVersionService;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class UpstreamVersionServiceTest extends TestCase
{
    public function testBuildsReleaseDownloadUrlForCustomRepository(): void
    {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $settings->method('get')->willReturnMap([
            ['settings::pterodactyl:update:repository', null, 'myuser/my-fork'],
            ['settings::pterodactyl:update:mode', null, 'release'],
            ['settings::pterodactyl:update:branch', null, 'main'],
            ['settings::pterodactyl:update:release', null, '1.2.3'],
            ['settings::pterodactyl:update:git_remote', null, 'origin'],
        ]);

        $service = new UpstreamVersionService($settings);

        $this->assertSame(
            'https://github.com/myuser/my-fork/releases/download/v1.2.3/panel.tar.gz',
            $service->releaseDownloadUrl()
        );
    }
}
