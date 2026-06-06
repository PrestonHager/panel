<?php

namespace Pterodactyl\Services\Panel;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Exceptions\DisplayException;

class UpstreamVersionService
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
    ) {
    }

    public function repository(): string
    {
        return $this->settings->get('settings::pterodactyl:update:repository')
            ?: config('pterodactyl.update.repository', 'pterodactyl/panel');
    }

    public function mode(): string
    {
        return $this->settings->get('settings::pterodactyl:update:mode')
            ?: config('pterodactyl.update.mode', 'auto');
    }

    public function branch(): string
    {
        return $this->settings->get('settings::pterodactyl:update:branch')
            ?: config('pterodactyl.update.branch', '1.0-develop');
    }

    public function release(): ?string
    {
        $release = $this->settings->get('settings::pterodactyl:update:release')
            ?: config('pterodactyl.update.release');

        return is_string($release) && $release !== '' ? $release : null;
    }

    public function gitRemote(): string
    {
        return $this->settings->get('settings::pterodactyl:update:git_remote')
            ?: config('pterodactyl.update.git_remote', 'origin');
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
     * @return array{tag: ?string, version: ?string, url: ?string}
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
                ];
            } catch (\Throwable) {
            }
        }

        $repo = $this->normalizeRepository($this->repository());
        $cacheKey = 'panel:upstream:latest:' . md5($repo);

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($repo) {
            $process = Process::fromShellCommandline(
                'curl -fsSL ' . escapeshellarg('https://api.github.com/repos/' . $repo . '/releases/latest')
            );
            $process->run();

            if (!$process->isSuccessful()) {
                return ['tag' => null, 'version' => null, 'url' => null];
            }

            $data = json_decode($process->getOutput(), true);
            if (!is_array($data)) {
                return ['tag' => null, 'version' => null, 'url' => null];
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
        if (config('app.version') === 'canary') {
            return false;
        }

        $latest = $this->latestRelease()['version'];
        if (!$latest) {
            return false;
        }

        return version_compare(config('app.version'), $latest, '<');
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
}
