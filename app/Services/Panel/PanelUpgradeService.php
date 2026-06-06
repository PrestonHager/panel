<?php

namespace Pterodactyl\Services\Panel;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;
use Pterodactyl\Exceptions\DisplayException;

class PanelUpgradeService
{
    public const STATUS_CACHE_KEY = 'panel:upgrade:status';

    public function __construct(
        private readonly PanelBackupService $backupService,
        private readonly UpstreamVersionService $upstreamVersionService,
    ) {
    }

    /**
     * @param array{user?: string, group?: string, skip_download?: bool, no_backup?: bool, url?: ?string, release?: ?string} $options
     */
    public function run(array $options = [], ?callable $logger = null): array
    {
        $this->updateStatus([
            'state' => 'running',
            'step' => 'starting',
            'progress' => 0,
            'message' => 'Starting panel upgrade...',
            'error' => null,
            'backup_id' => null,
        ]);

        $backupId = null;

        try {
            if (version_compare(PHP_VERSION, '8.2.0', '<')) {
                throw new DisplayException('PHP 8.2.0 or newer is required to upgrade the panel.');
            }

            if (empty($options['no_backup'])) {
                $this->updateStatus(['step' => 'backup', 'progress' => 5, 'message' => 'Creating automatic backup...']);
                $backup = $this->backupService->createAutomaticBackup($logger);
                $backupId = $backup['id'];
                $this->updateStatus(['backup_id' => $backupId, 'progress' => 15]);
            }

            $mode = $this->upstreamVersionService->detectInstallMode();
            $this->log($logger, 'Detected upgrade mode: ' . $mode);

            if ($mode === 'git') {
                $this->updateStatus(['step' => 'git', 'progress' => 25, 'message' => 'Fetching updates from git...']);
                $this->runGitUpgrade($logger);
            } else {
                if (empty($options['skip_download'])) {
                    $this->updateStatus(['step' => 'download', 'progress' => 25, 'message' => 'Downloading release archive...']);
                    $this->downloadReleaseArchive(
                        $options['url'] ?? null,
                        $options['release'] ?? null,
                        $logger
                    );
                }
            }

            $user = $options['user'] ?? $this->detectWebUser();
            $group = $options['group'] ?? $this->detectWebGroup();

            $this->runPostUpgradeSteps($user, $group, $logger);

            $this->updateStatus([
                'state' => 'completed',
                'step' => 'done',
                'progress' => 100,
                'message' => 'Panel upgrade completed successfully.',
                'backup_id' => $backupId,
            ]);

            return [
                'success' => true,
                'backup_id' => $backupId,
                'mode' => $mode,
            ];
        } catch (\Throwable $exception) {
            $this->log($logger, 'Upgrade failed: ' . $exception->getMessage());

            if ($backupId) {
                try {
                    $this->updateStatus(['step' => 'restore', 'progress' => 90, 'message' => 'Restoring backup after failure...']);
                    $this->backupService->restore($backupId, $logger);
                    Artisan::call('up');
                } catch (\Throwable $restoreException) {
                    $this->log($logger, 'Restore failed: ' . $restoreException->getMessage());
                }
            }

            $this->writeFailureLog($backupId, $exception);

            $this->updateStatus([
                'state' => 'failed',
                'step' => 'failed',
                'progress' => 100,
                'message' => 'Panel upgrade failed.',
                'error' => $exception->getMessage(),
                'backup_id' => $backupId,
            ]);

            throw $exception;
        }
    }

    private function runGitUpgrade(?callable $logger): void
    {
        if (!$this->commandExists('git')) {
            throw new DisplayException('Git is required for git-based panel upgrades.');
        }

        $remote = $this->upstreamVersionService->gitRemote();
        $branch = $this->upstreamVersionService->branch();
        $repoUrl = $this->upstreamVersionService->gitRepositoryUrl();

        $status = $this->runProcess(['git', 'status', '--porcelain'], base_path(), $logger);
        if (trim($status) !== '') {
            $this->log($logger, 'Working tree is not clean. Stashing local changes before upgrade.');
            $this->runProcess(['git', 'stash', 'push', '-u', '-m', 'panel-safe-upgrader'], base_path(), $logger);
        }

        $this->runProcess(['git', 'remote', 'set-url', $remote, $repoUrl], base_path(), $logger);
        $this->runProcess(['git', 'fetch', $remote, $branch], base_path(), $logger);
        $this->runProcess(['git', 'merge', '--ff-only', $remote . '/' . $branch], base_path(), $logger);
    }

    private function downloadReleaseArchive(?string $url, ?string $release, ?callable $logger): void
    {
        $downloadUrl = $url ?: $this->upstreamVersionService->releaseDownloadUrl($release);
        $this->log($logger, 'Downloading from ' . $downloadUrl);

        $process = Process::fromShellCommandline(
            sprintf('curl -fsSL %s | tar -xzv', escapeshellarg($downloadUrl)),
            base_path()
        );
        $process->setTimeout(600);
        $this->runTrackedProcess($process, $logger);
    }

    private function runPostUpgradeSteps(string $user, string $group, ?callable $logger): void
    {
        $this->updateStatus(['step' => 'maintenance', 'progress' => 40, 'message' => 'Enabling maintenance mode...']);
        Artisan::call('down');
        $this->log($logger, Artisan::output());

        $this->updateStatus(['step' => 'permissions', 'progress' => 50, 'message' => 'Fixing directory permissions...']);
        $this->runProcess(['chmod', '-R', '755', 'storage', 'bootstrap/cache'], base_path(), $logger);

        $this->updateStatus(['step' => 'composer', 'progress' => 60, 'message' => 'Installing PHP dependencies...']);
        $command = ['composer', 'install', '--no-ansi'];
        if (config('app.env') === 'production' && !config('app.debug')) {
            $command[] = '--optimize-autoloader';
            $command[] = '--no-dev';
        }
        $this->runProcess($command, base_path(), $logger, 600);

        $this->updateStatus(['step' => 'cache', 'progress' => 75, 'message' => 'Clearing cached configuration...']);
        Artisan::call('view:clear');
        Artisan::call('config:clear');

        $this->updateStatus(['step' => 'migrate', 'progress' => 85, 'message' => 'Running database migrations...']);
        Artisan::call('migrate', ['--force' => true, '--seed' => true]);
        $this->log($logger, Artisan::output());

        $this->updateStatus(['step' => 'ownership', 'progress' => 92, 'message' => 'Fixing file ownership...']);
        $chown = Process::fromShellCommandline(
            sprintf('chown -R %s:%s *', escapeshellarg($user), escapeshellarg($group)),
            base_path()
        );
        $this->runTrackedProcess($chown, $logger);

        $this->updateStatus(['step' => 'queue', 'progress' => 96, 'message' => 'Restarting queue workers...']);
        Artisan::call('queue:restart');

        $this->updateStatus(['step' => 'online', 'progress' => 98, 'message' => 'Bringing panel back online...']);
        Artisan::call('up');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateStatus(array $data): void
    {
        $current = Cache::get(self::STATUS_CACHE_KEY, []);
        Cache::put(self::STATUS_CACHE_KEY, array_merge($current, $data), now()->addHours(2));
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        return Cache::get(self::STATUS_CACHE_KEY, [
            'state' => 'idle',
            'step' => 'idle',
            'progress' => 0,
            'message' => 'No upgrade in progress.',
            'error' => null,
            'backup_id' => null,
        ]);
    }

    public function resetStatus(): void
    {
        Cache::forget(self::STATUS_CACHE_KEY);
    }

    private function detectWebUser(): string
    {
        $details = posix_getpwuid(fileowner(base_path('public')));

        return $details['name'] ?? 'www-data';
    }

    private function detectWebGroup(): string
    {
        $details = posix_getgrgid(filegroup(base_path('public')));

        return $details['name'] ?? 'www-data';
    }

    /**
     * @param list<string>|Process $command
     */
    private function runProcess(array|Process $command, ?string $cwd, ?callable $logger, int $timeout = 300): string
    {
        $process = $command instanceof Process ? $command : new Process($command, $cwd);
        $process->setTimeout($timeout);

        return $this->runTrackedProcess($process, $logger);
    }

    private function runTrackedProcess(Process $process, ?callable $logger): string
    {
        $process->run(function ($type, $buffer) use ($logger) {
            $this->log($logger, trim($buffer));
        });

        if (!$process->isSuccessful()) {
            throw new DisplayException(trim($process->getErrorOutput() ?: $process->getOutput() ?: 'Upgrade command failed.'));
        }

        return $process->getOutput();
    }

    private function commandExists(string $command): bool
    {
        $process = Process::fromShellCommandline('command -v ' . escapeshellarg($command));
        $process->run();

        return $process->isSuccessful();
    }

    private function writeFailureLog(?string $backupId, \Throwable $exception): void
    {
        $path = storage_path('logs/panel-upgrade-' . date('Y-m-d-His') . '.log');
        file_put_contents($path, implode(PHP_EOL, [
            'backup_id=' . ($backupId ?? 'none'),
            'message=' . $exception->getMessage(),
            'trace=' . $exception->getTraceAsString(),
        ]));
    }

    private function log(?callable $logger, string $message): void
    {
        if ($logger && trim($message) !== '') {
            $logger($message);
        }
    }
}
