<?php

namespace Pterodactyl\Services\Plugins;

use Pterodactyl\Models\Plugin;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;
use Pterodactyl\Plugins\Exceptions\PluginException;

class PluginVersionService
{
    public function __construct(
        private readonly GitHubPluginInstaller $installer,
    ) {
    }

    /**
     * @return array{
     *     update_available: bool,
     *     installed_version: string,
     *     latest_version: ?string,
     *     latest_ref: ?string,
     *     remote_commit: ?string,
     *     release_url: ?string,
     *     check_method: 'release'|'commit'|'local'|'unknown'
     * }
     */
    public function check(Plugin $plugin): array
    {
        $base = [
            'update_available' => false,
            'installed_version' => $plugin->version,
            'latest_version' => null,
            'latest_ref' => null,
            'remote_commit' => null,
            'release_url' => null,
            'check_method' => 'unknown',
        ];

        if ($this->isLocalPlugin($plugin)) {
            return array_merge($base, ['check_method' => 'local']);
        }

        try {
            [$owner, $repo] = $this->parseRepository($plugin);
        } catch (PluginException) {
            return $base;
        }

        $release = $this->fetchLatestRelease($owner, $repo);
        if ($release !== null && $release['version'] !== null) {
            $latestVersion = $release['version'];
            $updateAvailable = version_compare($plugin->version, $latestVersion, '<');

            return [
                'update_available' => $updateAvailable,
                'installed_version' => $plugin->version,
                'latest_version' => $latestVersion,
                'latest_ref' => $release['tag'] ?? $latestVersion,
                'remote_commit' => null,
                'release_url' => $release['url'],
                'check_method' => 'release',
            ];
        }

        $ref = $plugin->source_ref ?: 'main';
        $remoteCommit = $this->fetchBranchHead($owner, $repo, $ref);
        if ($remoteCommit === null) {
            return $base;
        }

        $installedCommit = $plugin->commit_sha;
        $updateAvailable = $installedCommit === null || !hash_equals($installedCommit, $remoteCommit);

        return [
            'update_available' => $updateAvailable,
            'installed_version' => $plugin->version,
            'latest_version' => null,
            'latest_ref' => $ref,
            'remote_commit' => $remoteCommit,
            'release_url' => null,
            'check_method' => 'commit',
        ];
    }

    /**
     * @return array<string, array{
     *     update_available: bool,
     *     installed_version: string,
     *     latest_version: ?string,
     *     latest_ref: ?string,
     *     remote_commit: ?string,
     *     release_url: ?string,
     *     check_method: 'release'|'commit'|'local'|'unknown'
     * }>
     */
    public function checkAll(): array
    {
        $results = [];

        foreach (Plugin::query()->orderBy('name')->get() as $plugin) {
            $results[$plugin->id] = $this->check($plugin);
        }

        return $results;
    }

    /**
     * @param array<string, array<string, mixed>>|null $checks
     */
    public function outdatedCount(?array $checks = null): int
    {
        $checks ??= $this->checkAll();

        return count(array_filter($checks, fn (array $check) => $check['update_available']));
    }

    private function isLocalPlugin(Plugin $plugin): bool
    {
        if ($plugin->source_ref === 'local') {
            return true;
        }

        return str_starts_with($plugin->source_url, 'file://');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseRepository(Plugin $plugin): array
    {
        [$owner, $repo] = $this->installer->parseGithubUrl(
            $plugin->source_url,
            $plugin->source_ref ?: 'main'
        );

        return [$owner, $repo];
    }

    /**
     * @return array{tag: ?string, version: ?string, url: ?string}|null
     */
    protected function fetchLatestRelease(string $owner, string $repo): ?array
    {
        $cacheKey = 'plugin:upstream:release:' . md5($owner . '/' . $repo);

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($owner, $repo) {
            $process = Process::fromShellCommandline(
                'curl -fsSL ' . escapeshellarg(sprintf(
                    'https://api.github.com/repos/%s/%s/releases/latest',
                    $owner,
                    $repo
                ))
            );
            $process->run();

            if (!$process->isSuccessful()) {
                return null;
            }

            $data = json_decode($process->getOutput(), true);
            if (!is_array($data)) {
                return null;
            }

            $tag = isset($data['tag_name']) ? (string) $data['tag_name'] : null;
            $version = $tag ? ltrim($tag, 'v') : null;

            return [
                'tag' => $tag,
                'version' => $version,
                'url' => isset($data['html_url']) ? (string) $data['html_url'] : null,
            ];
        });
    }

    protected function fetchBranchHead(string $owner, string $repo, string $ref): ?string
    {
        $cacheKey = 'plugin:upstream:commit:' . md5($owner . '/' . $repo . ':' . $ref);

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($owner, $repo, $ref) {
            $process = Process::fromShellCommandline(
                'curl -fsSL ' . escapeshellarg(sprintf(
                    'https://api.github.com/repos/%s/%s/commits/%s',
                    $owner,
                    $repo,
                    $ref
                ))
            );
            $process->run();

            if (!$process->isSuccessful()) {
                return null;
            }

            $data = json_decode($process->getOutput(), true);
            if (!is_array($data) || !isset($data['sha'])) {
                return null;
            }

            return (string) $data['sha'];
        });
    }
}
