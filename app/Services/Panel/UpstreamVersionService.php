<?php

namespace Pterodactyl\Services\Panel;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Exceptions\DisplayException;

class UpstreamVersionService
{
    private const COMMIT_SETTING = 'settings::pterodactyl:update:commit_sha';

    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
        private readonly PanelGitHubApiClient $github,
    ) {
    }

    public function repository(): string
    {
        return $this->settings->get('settings::pterodactyl:update:repository', null)
            ?: config('pterodactyl.update.repository', 'pterodactyl/panel');
    }

    public function mode(): string
    {
        return $this->settings->get('settings::pterodactyl:update:mode', null)
            ?: config('pterodactyl.update.mode', 'auto');
    }

    public function branch(): string
    {
        return $this->settings->get('settings::pterodactyl:update:branch', null)
            ?: config('pterodactyl.update.branch', '1.0-develop');
    }

    public function release(): ?string
    {
        $release = $this->settings->get('settings::pterodactyl:update:release', null)
            ?: config('pterodactyl.update.release');

        return is_string($release) && $release !== '' ? $release : null;
    }

    public function gitRemote(): string
    {
        return $this->settings->get('settings::pterodactyl:update:git_remote', null)
            ?: config('pterodactyl.update.git_remote', 'origin');
    }

    public function gitStrategy(): string
    {
        $strategy = $this->settings->get('settings::pterodactyl:update:git_strategy', null)
            ?: config('pterodactyl.update.git_strategy', 'auto');

        return in_array($strategy, ['auto', 'ff-only', 'reset'], true) ? $strategy : 'auto';
    }

    public function storedCommitSha(): ?string
    {
        $commit = $this->settings->get(self::COMMIT_SETTING, null);

        return is_string($commit) && $commit !== '' ? $commit : null;
    }

    public function releaseDownloadUrl(?string $release = null): string
    {
        $repo = $this->normalizeRepository($this->repository());
        $release = $release ?: $this->release();
        $suffix = $release ? 'download/v' . ltrim($release, 'v') : 'latest/download';

        return sprintf('https://github.com/%s/releases/%s/panel.tar.gz', $repo, $suffix);
    }

    public function gitRepositoryUrl(): string
    {
        $repo = $this->normalizeRepository($this->repository());

        return sprintf('https://github.com/%s.git', $repo);
    }

    /**
     * @return array{
     *     update_available: bool,
     *     current_version: string,
     *     latest_version: ?string,
     *     latest_tag: ?string,
     *     latest_ref: ?string,
     *     release_url: ?string,
     *     repository: string,
     *     branch: string,
     *     installed_commit: ?string,
     *     remote_commit: ?string,
     *     release_status: 'found'|'none'|'error'|'official',
     *     upstream_error: ?string,
     *     check_method: 'release'|'commit'|'none'|'canary'|'unknown'
     * }
     */
    public function check(): array
    {
        $currentVersion = $this->currentVersion();
        $repository = $this->normalizeRepository($this->repository());
        $branch = $this->branch();
        $latest = $this->latestRelease();

        $base = [
            'update_available' => false,
            'current_version' => $currentVersion,
            'latest_version' => $latest['version'],
            'latest_tag' => $latest['tag'],
            'latest_ref' => $latest['tag'] ?? $latest['version'],
            'release_url' => $latest['url'],
            'repository' => $repository,
            'branch' => $branch,
            'installed_commit' => null,
            'remote_commit' => null,
            'release_status' => $latest['status'] ?? 'unknown',
            'upstream_error' => $latest['error'] ?? null,
            'check_method' => 'unknown',
        ];

        if ($currentVersion === 'canary') {
            return array_merge($base, [
                'installed_commit' => $this->resolveInstalledCommitSha(),
                'check_method' => 'canary',
            ]);
        }

        $releaseOutdated = $latest['version'] !== null
            && version_compare($currentVersion, $latest['version'], '<');

        $installedCommit = null;
        $remoteCommit = null;
        $commitOutdated = false;

        if ($this->shouldCheckCommits()) {
            [$remoteCommit, $commitError] = $this->fetchBranchHead($repository, $branch);
            $installedCommit = $this->resolveInstalledCommitSha();
            $commitOutdated = $remoteCommit !== null
                && ($installedCommit === null || !hash_equals($installedCommit, $remoteCommit));

            if ($remoteCommit === null && $commitError !== null) {
                $base['upstream_error'] = $commitError;
            }
        }

        if ($releaseOutdated) {
            return array_merge($base, [
                'update_available' => true,
                'installed_commit' => $installedCommit,
                'remote_commit' => $remoteCommit,
                'latest_ref' => $latest['tag'] ?? $latest['version'],
                'check_method' => 'release',
            ]);
        }

        if ($commitOutdated) {
            return array_merge($base, [
                'update_available' => true,
                'installed_commit' => $installedCommit,
                'remote_commit' => $remoteCommit,
                'latest_ref' => $branch,
                'check_method' => 'commit',
            ]);
        }

        return array_merge($base, [
            'installed_commit' => $installedCommit,
            'remote_commit' => $remoteCommit,
            'latest_ref' => $latest['tag'] ?? $branch,
            'check_method' => $latest['version'] !== null ? 'release' : ($remoteCommit !== null ? 'commit' : 'none'),
        ]);
    }

    /**
     * @return array{
     *     tag: ?string,
     *     version: ?string,
     *     url: ?string,
     *     status: 'found'|'none'|'error'|'official',
     *     error: ?string
     * }
     */
    public function latestRelease(): array
    {
        if ($this->isDefaultUpstream()) {
            try {
                $service = app(\Pterodactyl\Services\Helpers\SoftwareVersionService::class);

                return [
                    'tag' => 'v' . $service->getPanel(),
                    'version' => $service->getPanel(),
                    'url' => 'https://github.com/pterodactyl/panel/releases/latest',
                    'status' => 'official',
                    'error' => null,
                ];
            } catch (\Throwable) {
            }
        }

        $pinned = $this->release();
        if ($pinned !== null) {
            $repo = $this->normalizeRepository($this->repository());

            return [
                'tag' => 'v' . ltrim($pinned, 'v'),
                'version' => ltrim($pinned, 'v'),
                'url' => sprintf('https://github.com/%s/releases/tag/v%s', $repo, ltrim($pinned, 'v')),
                'status' => 'found',
                'error' => null,
            ];
        }

        $repo = $this->normalizeRepository($this->repository());
        $cacheKey = 'panel:upstream:latest:v2:' . md5($repo);

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($repo) {
            $response = $this->github->get('/repos/' . $repo . '/releases/latest');

            if ($response['status'] === 404) {
                return [
                    'tag' => null,
                    'version' => null,
                    'url' => null,
                    'status' => 'none',
                    'error' => null,
                ];
            }

            if ($response['data'] === null) {
                return [
                    'tag' => null,
                    'version' => null,
                    'url' => null,
                    'status' => 'error',
                    'error' => $response['error'],
                ];
            }

            $data = $response['data'];
            $tag = isset($data['tag_name']) ? (string) $data['tag_name'] : null;
            $version = $tag ? ltrim($tag, 'v') : null;

            return [
                'tag' => $tag,
                'version' => $version,
                'url' => isset($data['html_url']) ? (string) $data['html_url'] : null,
                'status' => 'found',
                'error' => null,
            ];
        });
    }

    public function detectInstallMode(): string
    {
        $mode = $this->mode();
        if ($mode !== 'auto') {
            return $mode;
        }

        return is_dir(base_path('.git')) ? 'git' : 'release';
    }

    public function isUpdateAvailable(): bool
    {
        return $this->check()['update_available'];
    }

    public function recordInstalledCommit(?string $commit = null): void
    {
        $commit ??= $this->resolveInstalledCommitSha();
        if ($commit !== null && $commit !== '') {
            $this->settings->set(self::COMMIT_SETTING, $commit);
        }
    }

    public function clearUpdateCache(): void
    {
        $repository = $this->normalizeRepository($this->repository());
        $branch = $this->branch();

        Cache::forget('panel:upstream:latest:v2:' . md5($repository));
        Cache::forget('panel:upstream:latest:' . md5($repository));
        Cache::forget('panel:upstream:commit:v2:' . md5($repository . ':' . $branch));
        Cache::forget('panel:upstream:commit:' . md5($repository . ':' . $branch));
    }

    public function resolveInstalledCommitSha(): ?string
    {
        $fromGit = $this->readGitHeadCommit(base_path());
        if ($fromGit !== null) {
            return $fromGit;
        }

        return $this->storedCommitSha();
    }

    protected function currentVersion(): string
    {
        return (string) config('app.version');
    }

    protected function shouldCheckCommits(): bool
    {
        $mode = $this->mode();

        if ($mode === 'release' && !is_dir(base_path('.git'))) {
            return false;
        }

        if ($mode === 'git') {
            return true;
        }

        return is_dir(base_path('.git'));
    }

    private function isDefaultUpstream(): bool
    {
        return strtolower($this->normalizeRepository($this->repository())) === 'pterodactyl/panel';
    }

    private function normalizeRepository(string $repository): string
    {
        $repository = trim($repository);
        $repository = preg_replace('#^https?://github\.com/#i', '', $repository) ?? $repository;
        $repository = rtrim($repository, '.git');
        $repository = trim($repository, '/');

        if (!preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repository)) {
            throw new DisplayException('The configured update repository must be in owner/repo format.');
        }

        return $repository;
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

    /**
     * @return array{0: ?string, 1: ?string}
     */
    protected function fetchBranchHead(string $repository, string $ref): array
    {
        $cacheKey = 'panel:upstream:commit:v2:' . md5($repository . ':' . $ref);

        $cached = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($repository, $ref) {
            $response = $this->github->get(sprintf('/repos/%s/commits/%s', $repository, $ref));

            if ($response['data'] === null || !isset($response['data']['sha'])) {
                return [null, $response['error']];
            }

            return [(string) $response['data']['sha'], null];
        });

        if (is_string($cached)) {
            return [$cached, null];
        }

        return $cached;
    }
}
