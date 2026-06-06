<?php

namespace Pterodactyl\Tests\Unit\Plugins;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Models\Plugin;
use Pterodactyl\Services\Plugins\GitHubPluginInstaller;
use Pterodactyl\Services\Plugins\ManifestValidator;
use Pterodactyl\Services\Plugins\PluginVersionService;

class PluginVersionServiceTest extends TestCase
{
    public function testSkipsLocalPlugins(): void
    {
        $service = $this->makeService();
        $plugin = $this->makePlugin([
            'source_url' => 'file:///tmp/plugin',
            'source_ref' => 'local',
            'version' => '1.0.0',
        ]);

        $result = $service->check($plugin);

        $this->assertFalse($result['update_available']);
        $this->assertSame('local', $result['check_method']);
    }

    public function testDetectsOutdatedReleaseVersion(): void
    {
        $service = new TestablePluginVersionService(
            new GitHubPluginInstaller($this->createMock(ManifestValidator::class))
        );
        $service->releaseResponse = [
            'tag' => 'v2.0.0',
            'version' => '2.0.0',
            'url' => 'https://github.com/owner/repo/releases/tag/v2.0.0',
        ];

        $plugin = $this->makePlugin([
            'source_url' => 'https://github.com/owner/repo',
            'source_ref' => 'main',
            'version' => '1.0.0',
        ]);

        $result = $service->check($plugin);

        $this->assertTrue($result['update_available']);
        $this->assertSame('release', $result['check_method']);
        $this->assertSame('2.0.0', $result['latest_version']);
        $this->assertSame('v2.0.0', $result['latest_ref']);
    }

    public function testDetectsUpToDateReleaseVersion(): void
    {
        $service = new TestablePluginVersionService(
            new GitHubPluginInstaller($this->createMock(ManifestValidator::class))
        );
        $service->releaseResponse = [
            'tag' => 'v1.0.0',
            'version' => '1.0.0',
            'url' => 'https://github.com/owner/repo/releases/tag/v1.0.0',
        ];

        $plugin = $this->makePlugin([
            'source_url' => 'https://github.com/owner/repo',
            'source_ref' => 'main',
            'version' => '1.0.0',
        ]);

        $result = $service->check($plugin);

        $this->assertFalse($result['update_available']);
        $this->assertSame('release', $result['check_method']);
    }

    public function testFallsBackToCommitComparisonWhenNoRelease(): void
    {
        $service = new TestablePluginVersionService(
            new GitHubPluginInstaller($this->createMock(ManifestValidator::class))
        );
        $service->releaseResponse = null;
        $service->commitResponse = 'abc123def456';

        $plugin = $this->makePlugin([
            'source_url' => 'https://github.com/owner/repo',
            'source_ref' => 'main',
            'version' => '1.0.0',
            'commit_sha' => 'oldcommit000000',
        ]);

        $result = $service->check($plugin);

        $this->assertTrue($result['update_available']);
        $this->assertSame('commit', $result['check_method']);
        $this->assertSame('abc123def456', $result['remote_commit']);
        $this->assertSame('main', $result['latest_ref']);
    }

    public function testCommitComparisonMarksUpToDateWhenShaMatches(): void
    {
        $service = new TestablePluginVersionService(
            new GitHubPluginInstaller($this->createMock(ManifestValidator::class))
        );
        $service->releaseResponse = null;
        $service->commitResponse = 'abc123def456';

        $plugin = $this->makePlugin([
            'source_url' => 'https://github.com/owner/repo',
            'source_ref' => 'main',
            'version' => '1.0.0',
            'commit_sha' => 'abc123def456',
        ]);

        $result = $service->check($plugin);

        $this->assertFalse($result['update_available']);
        $this->assertSame('commit', $result['check_method']);
    }

    private function makeService(): PluginVersionService
    {
        return new TestablePluginVersionService(
            new GitHubPluginInstaller($this->createMock(ManifestValidator::class))
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function makePlugin(array $attributes): Plugin
    {
        $plugin = new Plugin();
        $plugin->forceFill(array_merge([
            'id' => 'com.example.plugin',
            'name' => 'Example Plugin',
            'version' => '1.0.0',
            'source_url' => 'https://github.com/owner/repo',
            'source_ref' => 'main',
            'commit_sha' => null,
            'enabled' => false,
            'permissions' => [],
        ], $attributes));

        return $plugin;
    }
}

class TestablePluginVersionService extends PluginVersionService
{
    public ?array $releaseResponse = null;

    public ?string $commitResponse = null;

    protected function fetchLatestRelease(string $owner, string $repo): ?array
    {
        return $this->releaseResponse;
    }

    protected function fetchBranchHead(string $owner, string $repo, string $ref): ?string
    {
        return $this->commitResponse;
    }
}
