<?php

namespace Pterodactyl\Services\Plugins;

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Pterodactyl\Plugins\PluginManifest;
use Pterodactyl\Plugins\Exceptions\PluginException;

class GitHubPluginInstaller
{
    public function __construct(
        private readonly ManifestValidator $validator,
    ) {
    }

    /**
     * @return array{manifest: PluginManifest, commit_sha: ?string, source_url: string, source_ref: string}
     */
    public function install(string $githubUrl, string $ref = 'main'): array
    {
        [$owner, $repo, $ref] = $this->parseGithubUrl($githubUrl, $ref);
        $sourceUrl = sprintf('https://github.com/%s/%s', $owner, $repo);

        $tmp = storage_path('app/plugins/.tmp/' . Str::uuid());
        if (!is_dir(dirname($tmp))) {
            mkdir(dirname($tmp), 0755, true);
        }

        try {
            $this->cloneRepository($owner, $repo, $ref, $tmp);
            $this->assertRepositorySize($tmp);

            $manifest = $this->validator->readFromDirectory($tmp);
            $commitSha = $this->resolveCommitSha($tmp);

            $target = PluginRegistry::directoryFor($manifest->id);
            if (is_dir($target)) {
                throw new PluginException(sprintf('Plugin "%s" is already installed.', $manifest->id));
            }

            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }

            $this->moveDirectory($tmp, $target);

            return [
                'manifest' => $manifest,
                'commit_sha' => $commitSha,
                'source_url' => $sourceUrl,
                'source_ref' => $ref,
            ];
        } finally {
            $this->removeDirectory($tmp);
        }
    }

    /**
     * @return array{manifest: PluginManifest, commit_sha: ?string, source_url: string, source_ref: string}
     */
    public function update(string $githubUrl, string $ref, string $expectedPluginId): array
    {
        [$owner, $repo, $ref] = $this->parseGithubUrl($githubUrl, $ref);
        $sourceUrl = sprintf('https://github.com/%s/%s', $owner, $repo);

        $tmp = storage_path('app/plugins/.tmp/' . Str::uuid());
        if (!is_dir(dirname($tmp))) {
            mkdir(dirname($tmp), 0755, true);
        }

        try {
            $this->cloneRepository($owner, $repo, $ref, $tmp);
            $this->assertRepositorySize($tmp);

            $manifest = $this->validator->readFromDirectory($tmp);
            if ($manifest->id !== $expectedPluginId) {
                throw new PluginException(sprintf(
                    'Updated repository manifest ID "%s" does not match installed plugin "%s".',
                    $manifest->id,
                    $expectedPluginId
                ));
            }

            $commitSha = $this->resolveCommitSha($tmp);
            $target = PluginRegistry::directoryFor($manifest->id);

            if (is_dir($target)) {
                $this->removeDirectory($target);
            }

            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }

            $this->moveDirectory($tmp, $target);

            return [
                'manifest' => $manifest,
                'commit_sha' => $commitSha,
                'source_url' => $sourceUrl,
                'source_ref' => $ref,
            ];
        } finally {
            $this->removeDirectory($tmp);
        }
    }

    /**
     * @return array{manifest: PluginManifest, commit_sha: null, source_url: string, source_ref: string}
     */
    public function installFromPath(string $sourcePath): array
    {
        $sourcePath = realpath($sourcePath);
        if ($sourcePath === false || !is_dir($sourcePath)) {
            throw new PluginException('Source plugin directory does not exist.');
        }

        $manifest = $this->validator->readFromDirectory($sourcePath);
        $target = PluginRegistry::directoryFor($manifest->id);

        if (is_dir($target)) {
            $this->removeDirectory($target);
        }

        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }

        $this->copyDirectory($sourcePath, $target);

        return [
            'manifest' => $manifest,
            'commit_sha' => null,
            'source_url' => 'file://' . $sourcePath,
            'source_ref' => 'local',
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    public function parseGithubUrl(string $url, string $defaultRef): array
    {
        $url = trim($url);
        if (preg_match('#^https?://github\.com/([^/]+)/([^/]+?)(?:\.git)?/?$#i', $url, $matches)) {
            return [$matches[1], rtrim($matches[2], '.git'), $defaultRef];
        }

        if (preg_match('#^([^/]+)/([^/]+)$#', $url, $matches)) {
            return [$matches[1], $matches[2], $defaultRef];
        }

        throw new PluginException('GitHub URL must be in the form https://github.com/owner/repo or owner/repo.');
    }

    private function cloneRepository(string $owner, string $repo, string $ref, string $target): void
    {
        $repoUrl = sprintf('https://github.com/%s/%s.git', $owner, $repo);
        $timeout = (int) config('pterodactyl.plugins.install.timeout', 120);

        $process = new Process([
            'git',
            'clone',
            '--depth',
            '1',
            '--branch',
            $ref,
            $repoUrl,
            $target,
        ]);
        $process->setTimeout($timeout);
        $process->run();

        if (!$process->isSuccessful()) {
            $process = new Process([
                'git',
                'clone',
                '--depth',
                '1',
                $repoUrl,
                $target,
            ]);
            $process->setTimeout($timeout);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new PluginException('Failed to clone plugin repository: ' . $process->getErrorOutput());
            }

            $checkout = new Process(['git', '-C', $target, 'checkout', $ref]);
            $checkout->setTimeout(60);
            $checkout->run();

            if (!$checkout->isSuccessful()) {
                throw new PluginException('Failed to checkout ref "' . $ref . '": ' . $checkout->getErrorOutput());
            }
        }
    }

    private function resolveCommitSha(string $directory): ?string
    {
        $process = new Process(['git', '-C', $directory, 'rev-parse', 'HEAD']);
        $process->setTimeout(10);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput()) ?: null;
    }

    private function assertRepositorySize(string $directory): void
    {
        $maxBytes = (int) config('pterodactyl.plugins.install.max_bytes', 10 * 1024 * 1024);
        $size = $this->directorySize($directory);
        if ($size > $maxBytes) {
            throw new PluginException(sprintf(
                'Plugin package exceeds maximum allowed size (%d bytes).',
                $maxBytes
            ));
        }
    }

    private function directorySize(string $directory): int
    {
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    private function moveDirectory(string $from, string $to): void
    {
        if (!@rename($from, $to)) {
            $this->copyDirectory($from, $to);
            $this->removeDirectory($from);
        }
    }

    private function copyDirectory(string $from, string $to): void
    {
        if (!is_dir($to)) {
            mkdir($to, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $to . '/' . $iterator->getSubPathname();
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item->getPathname(), $target);
            }
        }
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
