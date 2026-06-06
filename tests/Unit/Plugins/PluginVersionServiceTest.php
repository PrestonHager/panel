<?php

namespace Pterodactyl\Tests\Unit\Plugins;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Models\Plugin;
use Pterodactyl\Services\Plugins\GitHubPluginInstaller;
use Pterodactyl\Services\Plugins\ManifestValidator;
use Pterodactyl\Services\Plugins\PluginContentHashService;
use Pterodactyl\Services\Plugins\PluginVersionService;

class PluginVersionServiceTest extends TestCase
{
    public function testSkipsLocalPluginsWithoutGithubRemote(): void
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
            new GitHubPluginInstaller($this->createMock(ManifestValidator::class)),
            new PluginContentHashService()
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

    public function testDetectsNewCommitWhenReleaseVersionMatches(): void
    {
        $service = new TestablePluginVersionService(
            new GitHubPluginInstaller($this->createMock(ManifestValidator::class)),
            new PluginContentHashService()
        );
        $service->releaseResponse = [
            'tag' => 'v1.0.0',
            'version' => '1.0.0',
            'url' => 'https://github.com/owner/repo/releases/tag/v1.0.0',
        ];
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
        $this->assertSame('oldcommit000000', $result['installed_commit']);
        $this->assertSame('main', $result['latest_ref']);
    }

    public function testDetectsUpToDateReleaseVersion(): void
    {
        $service = new TestablePluginVersionService(
            new GitHubPluginInstaller($this->createMock(ManifestValidator::class)),
            new PluginContentHashService()
        );
        $service->releaseResponse = [
            'tag' => 'v1.0.0',
            'version' => '1.0.0',
            'url' => 'https://github.com/owner/repo/releases/tag/v1.0.0',
        ];
        $service->commitResponse = 'abc123def456';
        $service->remoteContentHash = 'samehash';

        $plugin = $this->makePlugin([
            'source_url' => 'https://github.com/owner/repo',
            'source_ref' => 'main',
            'version' => '1.0.0',
            'commit_sha' => 'abc123def456',
        ]);

        $directory = storage_path('app/plugins/' . $plugin->id);
        if (!is_dir(dirname($directory))) {
            mkdir(dirname($directory), 0755, true);
        }
        mkdir($directory, 0755, true);
        file_put_contents($directory . '/plugin.json', '{"id":"com.example.plugin","version":"1.0.0"}');

        $service->installedContentHash = 'samehash';

        $result = $service->check($plugin);

        $this->assertFalse($result['update_available']);
        $this->assertSame('release', $result['check_method']);

        unlink($directory . '/plugin.json');
        rmdir($directory);
    }

    public function testFallsBackToCommitComparisonWhenNoRelease(): void
    {
        $service = new TestablePluginVersionService(
            new GitHubPluginInstaller($this->createMock(ManifestValidator::class)),
            new PluginContentHashService()
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
            new GitHubPluginInstaller($this->createMock(ManifestValidator::class)),
            new PluginContentHashService()
        );
        $service->releaseResponse = null;
        $service->commitResponse = 'abc123def456';
        $service->remoteContentHash = 'samehash';
        $service->installedContentHash = 'samehash';

        $plugin = $this->makePlugin([
            'source_url' => 'https://github.com/owner/repo',
            'source_ref' => 'main',
            'version' => '1.0.0',
            'commit_sha' => 'abc123def456',
        ]);

        $directory = storage_path('app/plugins/' . $plugin->id);
        if (!is_dir(dirname($directory))) {
            mkdir(dirname($directory), 0755, true);
        }
        mkdir($directory, 0755, true);
        file_put_contents($directory . '/plugin.json', '{"id":"com.example.plugin","version":"1.0.0"}');

        $result = $service->check($plugin);

        $this->assertFalse($result['update_available']);
        $this->assertSame('commit', $result['check_method']);

        unlink($directory . '/plugin.json');
        rmdir($directory);
    }

    public function testDetectsContentHashDriftWhenCommitMatches(): void
    {
        $service = new TestablePluginVersionService(
            new GitHubPluginInstaller($this->createMock(ManifestValidator::class)),
            new PluginContentHashService()
        );
        $service->releaseResponse = [
            'tag' => 'v1.0.0',
            'version' => '1.0.0',
            'url' => 'https://github.com/owner/repo/releases/tag/v1.0.0',
        ];
        $service->commitResponse = 'abc123def456';
        $service->remoteContentHash = 'remotehash';
        $service->installedContentHash = 'localhash';

        $plugin = $this->makePlugin([
            'source_url' => 'https://github.com/owner/repo',
            'source_ref' => 'main',
            'version' => '1.0.0',
            'commit_sha' => 'abc123def456',
        ]);

        $directory = storage_path('app/plugins/' . $plugin->id);
        if (!is_dir(dirname($directory))) {
            mkdir(dirname($directory), 0755, true);
        }
        mkdir($directory, 0755, true);
        file_put_contents($directory . '/plugin.json', '{"id":"com.example.plugin","version":"1.0.0"}');

        $result = $service->check($plugin);

        $this->assertTrue($result['update_available']);
        $this->assertSame('hash', $result['check_method']);
        $this->assertSame('localhash', $result['installed_hash']);
        $this->assertSame('remotehash', $result['remote_hash']);

        unlink($directory . '/plugin.json');
        rmdir($directory);
    }

    private function makeService(): PluginVersionService
    {
        return new TestablePluginVersionService(
            new GitHubPluginInstaller($this->createMock(ManifestValidator::class)),
            new PluginContentHashService()
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

    public ?string $remoteContentHash = null;

    public ?string $installedContentHash = null;

    protected function fetchLatestRelease(string $owner, string $repo): ?array
    {
        return $this->releaseResponse;
    }

    protected function fetchBranchHead(string $owner, string $repo, string $ref): ?string
    {
        return $this->commitResponse;
    }

    protected function fetchRemoteContentHash(string $owner, string $repo, string $ref): ?string
    {
        return $this->remoteContentHash;
    }

    protected function compareContentHashes(Plugin $plugin, string $owner, string $repo, string $ref): array
    {
        if ($this->installedContentHash !== null || $this->remoteContentHash !== null) {
            $installedHash = $this->installedContentHash;
            $remoteHash = $this->remoteContentHash;

            if ($installedHash === null) {
                $directory = \Pterodactyl\Services\Plugins\PluginRegistry::directoryFor($plugin->id);
                if (is_dir($directory)) {
                    $installedHash = (new PluginContentHashService())->hashDirectory($directory);
                }
            }

            if ($installedHash === null || $remoteHash === null || $installedHash === '' || $remoteHash === '') {
                return [$installedHash, $remoteHash, false];
            }

            return [$installedHash, $remoteHash, !hash_equals($installedHash, $remoteHash)];
        }

        return parent::compareContentHashes($plugin, $owner, $repo, $ref);
    }
}
