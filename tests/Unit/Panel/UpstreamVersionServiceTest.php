<?php

namespace Pterodactyl\Tests\Unit\Panel;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Services\Panel\UpstreamVersionService;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class UpstreamVersionServiceTest extends TestCase
{
    public function testBuildsReleaseDownloadUrlForCustomRepository(): void
    {
        $service = $this->makeService([
            ['settings::pterodactyl:update:repository', null, 'myuser/my-fork'],
            ['settings::pterodactyl:update:mode', null, 'release'],
            ['settings::pterodactyl:update:branch', null, 'main'],
            ['settings::pterodactyl:update:release', null, '1.2.3'],
            ['settings::pterodactyl:update:git_remote', null, 'origin'],
        ]);

        $this->assertSame(
            'https://github.com/myuser/my-fork/releases/download/v1.2.3/panel.tar.gz',
            $service->releaseDownloadUrl()
        );
    }

    public function testDetectsNewCommitWhenReleaseVersionMatches(): void
    {
        $service = new TestableUpstreamVersionService($this->makeSettings([
            ['settings::pterodactyl:update:repository', null, 'PrestonHager/panel'],
            ['settings::pterodactyl:update:mode', null, 'git'],
            ['settings::pterodactyl:update:branch', null, 'feat/plugin-manager'],
            ['settings::pterodactyl:update:release', null, null],
            ['settings::pterodactyl:update:git_remote', null, 'origin'],
            ['settings::pterodactyl:update:commit_sha', null, 'oldcommit000000'],
        ]));
        $service->releaseResponse = [
            'tag' => 'v1.12.4',
            'version' => '1.12.4',
            'url' => 'https://github.com/PrestonHager/panel/releases/latest',
        ];
        $service->commitResponse = 'abc123def456';
        $service->appVersion = '1.12.4';
        $service->checkCommits = true;

        $check = $service->check();

        $this->assertTrue($check['update_available']);
        $this->assertSame('commit', $check['check_method']);
        $this->assertSame('feat/plugin-manager', $check['latest_ref']);
        $this->assertSame('abc123def456', $check['remote_commit']);
    }

    public function testMarksUpToDateWhenReleaseAndCommitMatch(): void
    {
        $service = new TestableUpstreamVersionService($this->makeSettings([
            ['settings::pterodactyl:update:repository', null, 'PrestonHager/panel'],
            ['settings::pterodactyl:update:mode', null, 'git'],
            ['settings::pterodactyl:update:branch', null, 'feat/plugin-manager'],
            ['settings::pterodactyl:update:release', null, null],
            ['settings::pterodactyl:update:git_remote', null, 'origin'],
            ['settings::pterodactyl:update:commit_sha', null, 'abc123def456'],
        ]));
        $service->releaseResponse = [
            'tag' => 'v1.12.4',
            'version' => '1.12.4',
            'url' => 'https://github.com/PrestonHager/panel/releases/latest',
        ];
        $service->commitResponse = 'abc123def456';
        $service->appVersion = '1.12.4';
        $service->checkCommits = true;

        $check = $service->check();

        $this->assertFalse($check['update_available']);
        $this->assertSame('abc123def456', $check['installed_commit']);
    }

    public function testDetectsOutdatedReleaseVersion(): void
    {
        $service = new TestableUpstreamVersionService($this->makeSettings([
            ['settings::pterodactyl:update:repository', null, 'pterodactyl/panel'],
            ['settings::pterodactyl:update:mode', null, 'release'],
            ['settings::pterodactyl:update:branch', null, '1.0-develop'],
            ['settings::pterodactyl:update:release', null, null],
            ['settings::pterodactyl:update:git_remote', null, 'origin'],
        ]));
        $service->releaseResponse = [
            'tag' => 'v1.13.0',
            'version' => '1.13.0',
            'url' => 'https://github.com/pterodactyl/panel/releases/latest',
        ];
        $service->appVersion = '1.12.4';
        $service->checkCommits = false;

        $check = $service->check();

        $this->assertTrue($check['update_available']);
        $this->assertSame('release', $check['check_method']);
    }

    /**
     * @param array<int, array{0: string, 1: mixed, 2: mixed}> $map
     */
    private function makeService(array $map): UpstreamVersionService
    {
        return new UpstreamVersionService($this->makeSettings($map));
    }

    /**
     * @param array<int, array{0: string, 1: mixed, 2: mixed}> $map
     */
    private function makeSettings(array $map): SettingsRepositoryInterface
    {
        $values = [];
        foreach ($map as $entry) {
            $values[$entry[0]] = $entry[2];
        }

        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $settings->method('get')->willReturnCallback(function (string $key, mixed $default = null) use ($values) {
            return array_key_exists($key, $values) ? $values[$key] : $default;
        });

        return $settings;
    }
}

class TestableUpstreamVersionService extends UpstreamVersionService
{
    public ?array $releaseResponse = null;

    public ?string $commitResponse = null;

    public string $appVersion = '1.0.0';

    public bool $checkCommits = true;

    public function latestRelease(): array
    {
        return $this->releaseResponse ?? ['tag' => null, 'version' => null, 'url' => null];
    }

    protected function fetchBranchHead(string $repository, string $ref): ?string
    {
        return $this->commitResponse;
    }

    protected function currentVersion(): string
    {
        return $this->appVersion;
    }

    protected function shouldCheckCommits(): bool
    {
        return $this->checkCommits;
    }

    public function resolveInstalledCommitSha(): ?string
    {
        return $this->storedCommitSha();
    }
}
