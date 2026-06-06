<?php

namespace Pterodactyl\Services\Plugins;

use Illuminate\Support\Str;
use Pterodactyl\Models\Plugin;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;
use Pterodactyl\Plugins\Exceptions\PluginException;

class PluginVersionService
{
    public function __construct(
        private readonly GitHubPluginInstaller $installer,
        private readonly PluginContentHashService $contentHash,
    ) {
    }

    /**
     * @return array{
     *     update_available: bool,
     *     installed_version: string,
     *     latest_version: ?string,
     *     latest_ref: ?string,
     *     installed_commit: ?string,
     *     remote_commit: ?string,
     *     installed_hash: ?string,
     *     remote_hash: ?string,
     *     release_url: ?string,
     *     check_method: 'release'|'commit'|'hash'|'local'|'unknown'
     * }
     */
    public function check(Plugin $plugin): array
    {
        $base = [
            'update_available' => false,
            'installed_version' => $plugin->version,
            'latest_version' => null,
            'latest_ref' => null,
            'installed_commit' => null,
            'remote_commit' => null,
            'installed_hash' => null,
            'remote_hash' => null,
            'release_url' => null,
            'check_method' => 'unknown',
        ];

        $source = $this->resolveGithubSource($plugin);
        if ($source === null) {
            return array_merge($base, ['check_method' => 'local']);
        }

        [$owner, $repo, $ref] = $source;

        $release = $this->fetchLatestRelease($owner, $repo);
        $releaseOutdated = $release !== null
            && $release['version'] !== null
            && version_compare($plugin->version, $release['version'], '<');

        $remoteCommit = $this->fetchBranchHead($owner, $repo, $ref);
        $installedCommit = $this->resolveInstalledCommitSha($plugin);
        $commitOutdated = $remoteCommit !== null
            && ($installedCommit === null || !hash_equals($installedCommit, $remoteCommit));

        $installedHash = null;
        $remoteHash = null;
        $contentHashOutdated = false;

        if (!$releaseOutdated && !$commitOutdated) {
            [$installedHash, $remoteHash, $contentHashOutdated] = $this->compareContentHashes(
                $plugin,
                $owner,
                $repo,
                $ref
            );
        }

        $updateAvailable = $releaseOutdated || $commitOutdated || $contentHashOutdated;

        if ($releaseOutdated) {
            return [
                'update_available' => true,
                'installed_version' => $plugin->version,
                'latest_version' => $release['version'],
                'latest_ref' => $release['tag'] ?? $release['version'],
                'installed_commit' => $installedCommit,
                'remote_commit' => $remoteCommit,
                'installed_hash' => $installedHash,
                'remote_hash' => $remoteHash,
                'release_url' => $release['url'],
                'check_method' => 'release',
            ];
        }

        if ($commitOutdated) {
            return [
                'update_available' => true,
                'installed_version' => $plugin->version,
                'latest_version' => $release['version'] ?? null,
                'latest_ref' => $ref,
                'installed_commit' => $installedCommit,
                'remote_commit' => $remoteCommit,
                'installed_hash' => $installedHash,
                'remote_hash' => $remoteHash,
                'release_url' => $release['url'] ?? null,
                'check_method' => 'commit',
            ];
        }

        if ($contentHashOutdated) {
            return [
                'update_available' => true,
                'installed_version' => $plugin->version,
                'latest_version' => $release['version'] ?? null,
                'latest_ref' => $ref,
                'installed_commit' => $installedCommit,
                'remote_commit' => $remoteCommit,
                'installed_hash' => $installedHash,
                'remote_hash' => $remoteHash,
                'release_url' => $release['url'] ?? null,
                'check_method' => 'hash',
            ];
        }

        return [
            'update_available' => false,
            'installed_version' => $plugin->version,
            'latest_version' => $release['version'] ?? null,
            'latest_ref' => $release !== null ? ($release['tag'] ?? $release['version']) : $ref,
            'installed_commit' => $installedCommit,
            'remote_commit' => $remoteCommit,
            'installed_hash' => $installedHash,
            'remote_hash' => $remoteHash,
            'release_url' => $release['url'] ?? null,
            'check_method' => $release !== null ? 'release' : ($remoteCommit !== null ? 'commit' : 'unknown'),
        ];
    }

    /**
     * @return array<string, array{
     *     update_available: bool,
     *     installed_version: string,
     *     latest_version: ?string,
     *     latest_ref: ?string,
     *     installed_commit: ?string,
     *     remote_commit: ?string,
     *     installed_hash: ?string,
     *     remote_hash: ?string,
     *     release_url: ?string,
     *     check_method: 'release'|'commit'|'hash'|'local'|'unknown'
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

    /**
     * @return array{0: string, 1: string, 2: string}|null
     */
    private function resolveGithubSource(Plugin $plugin): ?array
    {
        if (!$this->isLocalPlugin($plugin)) {
            try {
                [$owner, $repo] = $this->parseRepository($plugin);

                return [$owner, $repo, $plugin->source_ref ?: 'main'];
            } catch (PluginException) {
                return null;
            }
        }

        $directory = PluginRegistry::directoryFor($plugin->id);
        $remoteUrl = $this->readGitRemoteUrl($directory);
        if ($remoteUrl === null) {
            return null;
        }

        try {
            [$owner, $repo] = $this->installer->parseGithubUrl($remoteUrl, 'main');
        } catch (PluginException) {
            return null;
        }

        $ref = $this->readGitBranch($directory) ?? ($plugin->source_ref !== 'local' ? $plugin->source_ref : 'main');

        return [$owner, $repo, $ref ?: 'main'];
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

    private function resolveInstalledCommitSha(Plugin $plugin): ?string
    {
        $directory = PluginRegistry::directoryFor($plugin->id);
        $fromGit = $this->readGitHeadCommit($directory);
        if ($fromGit !== null) {
            return $fromGit;
        }

        return $plugin->commit_sha;
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: bool}
     */
    protected function compareContentHashes(Plugin $plugin, string $owner, string $repo, string $ref): array
    {
        $directory = PluginRegistry::directoryFor($plugin->id);
        if (!is_dir($directory)) {
            return [null, null, false];
        }

        $installedHash = $this->contentHash->hashDirectory($directory);
        if ($installedHash === '') {
            return [null, null, false];
        }

        $remoteHash = $this->fetchRemoteContentHash($owner, $repo, $ref);
        if ($remoteHash === null || $remoteHash === '') {
            return [$installedHash, null, false];
        }

        return [$installedHash, $remoteHash, !hash_equals($installedHash, $remoteHash)];
    }

    private function readGitHeadCommit(string $directory): ?string
    {
        if (!is_dir($directory . DIRECTORY_SEPARATOR . '.git')) {
            return null;
        }

        $process = new Process(['git', '-C', $directory, 'rev-parse', 'HEAD']);
        $process->setTimeout(10);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        $sha = trim($process->getOutput());

        return $sha !== '' ? $sha : null;
    }

    private function readGitRemoteUrl(string $directory): ?string
    {
        if (!is_dir($directory . DIRECTORY_SEPARATOR . '.git')) {
            return null;
        }

        $process = new Process(['git', '-C', $directory, 'remote', 'get-url', 'origin']);
        $process->setTimeout(10);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        $url = trim($process->getOutput());

        return $url !== '' ? $url : null;
    }

    private function readGitBranch(string $directory): ?string
    {
        if (!is_dir($directory . DIRECTORY_SEPARATOR . '.git')) {
            return null;
        }

        $process = new Process(['git', '-C', $directory, 'rev-parse', '--abbrev-ref', 'HEAD']);
        $process->setTimeout(10);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        $branch = trim($process->getOutput());

        if ($branch === '' || $branch === 'HEAD') {
            return null;
        }

        return $branch;
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

    protected function fetchRemoteContentHash(string $owner, string $repo, string $ref): ?string
    {
        $cacheKey = 'plugin:upstream:content-hash:' . md5($owner . '/' . $repo . ':' . $ref);

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($owner, $repo, $ref) {
            $tmpRoot = storage_path('app/plugins/.tmp/' . Str::uuid());
            $archive = $tmpRoot . '/archive.tar.gz';

            if (!is_dir(dirname($tmpRoot))) {
                mkdir(dirname($tmpRoot), 0755, true);
            }

            if (!mkdir($tmpRoot, 0755, true) && !is_dir($tmpRoot)) {
                return null;
            }

            try {
                $url = sprintf('https://api.github.com/repos/%s/%s/tarball/%s', $owner, $repo, $ref);
                $download = Process::fromShellCommandline(
                    'curl -fsSL -L -H ' . escapeshellarg('Accept: application/vnd.github+json')
                    . ' -o ' . escapeshellarg($archive) . ' ' . escapeshellarg($url)
                );
                $download->setTimeout(120);
                $download->run();

                if (!$download->isSuccessful() || !is_file($archive)) {
                    return null;
                }

                $extract = $tmpRoot . '/extract';
                if (!mkdir($extract, 0755, true) && !is_dir($extract)) {
                    return null;
                }

                $tar = new Process(['tar', '-xzf', $archive, '-C', $extract]);
                $tar->setTimeout(120);
                $tar->run();

                if (!$tar->isSuccessful()) {
                    return null;
                }

                $entries = array_values(array_diff(scandir($extract) ?: [], ['.', '..']));
                if ($entries === []) {
                    return null;
                }

                $root = $extract . DIRECTORY_SEPARATOR . $entries[0];
                if (!is_dir($root)) {
                    return null;
                }

                $hash = $this->contentHash->hashDirectory($root);

                return $hash !== '' ? $hash : null;
            } finally {
                $this->removeDirectory($tmpRoot);
            }
        });
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
