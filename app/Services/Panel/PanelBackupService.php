<?php

namespace Pterodactyl\Services\Panel;

use Illuminate\Support\Str;
use Pterodactyl\Models\Plugin;
use Pterodactyl\Models\Setting;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Pterodactyl\Exceptions\DisplayException;

class PanelBackupService
{
    public const BACKUP_ROOT = 'panel-upgrades/backups';

    /**
     * @return array{id: string, path: string, manifest: array<string, mixed>}
     */
    public function createAutomaticBackup(?callable $logger = null): array
    {
        $id = (string) Str::uuid();
        $directory = $this->backupPath($id);
        File::ensureDirectoryExists($directory);

        $manifest = [
            'id' => $id,
            'type' => 'automatic',
            'created_at' => now()->toIso8601String(),
            'panel_version' => config('app.version'),
            'database_driver' => config('database.default'),
            'base_path' => base_path(),
            'files' => [],
        ];

        $this->log($logger, 'Backing up environment file...');
        if (is_file(base_path('.env'))) {
            copy(base_path('.env'), $directory . '/.env');
            $manifest['files']['env'] = '.env';
        }

        $this->log($logger, 'Backing up config/app.php version snapshot...');
        if (is_file(base_path('config/app.php'))) {
            copy(base_path('config/app.php'), $directory . '/config-app.php');
            $manifest['files']['config_app'] = 'config-app.php';
        }

        $this->log($logger, 'Backing up storage/app directory...');
        $storageArchive = $directory . '/storage-app.tar.gz';
        $this->createStorageAppArchive($storageArchive);
        if (is_file($storageArchive)) {
            $manifest['files']['storage_app'] = 'storage-app.tar.gz';
        }

        $this->log($logger, 'Backing up database...');
        $databaseArchive = $directory . '/database.sql.gz';
        $this->dumpDatabase($databaseArchive);
        $manifest['files']['database'] = 'database.sql.gz';

        file_put_contents(
            $directory . '/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->pruneOldBackups((int) config('pterodactyl.update.backup_retention', 5));

        return [
            'id' => $id,
            'path' => $directory,
            'manifest' => $manifest,
        ];
    }

    /**
     * @return array{path: string, filename: string}
     */
    public function createDownloadBundle(): array
    {
        $tempDir = storage_path('app/panel-upgrades/temp/' . Str::uuid());
        File::ensureDirectoryExists($tempDir);

        if (is_file(base_path('.env'))) {
            copy(base_path('.env'), $tempDir . '/.env');
        }

        $settings = Setting::query()->orderBy('key')->get(['key', 'value'])->map(fn ($row) => [
            'key' => $row->key,
            'value' => $row->value,
        ])->values()->all();
        file_put_contents($tempDir . '/settings.json', json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $plugins = [];
        if (class_exists(Plugin::class)) {
            try {
                $plugins = Plugin::query()->orderBy('name')->get(['id', 'name', 'version', 'enabled', 'source_url'])->toArray();
            } catch (\Throwable) {
            }
        }
        file_put_contents($tempDir . '/plugins.json', json_encode($plugins, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        file_put_contents($tempDir . '/UPGRADE.md', implode(PHP_EOL, [
            '# Panel Upgrade Checklist',
            '',
            '- Verify you have a recent database backup.',
            '- Confirm `.env` values are correct after any upgrade.',
            '- Review installed plugins for compatibility.',
            '- Update Wings separately: https://pterodactyl.io/wings/1.0/upgrading.html',
            '',
            'This bundle is optional. The updater will still create an automatic backup before updating.',
        ]));

        $zipPath = storage_path('app/panel-upgrades/downloads/panel-config-' . date('Y-m-d-His') . '.zip');
        File::ensureDirectoryExists(dirname($zipPath));
        $this->createZipFromDirectory($tempDir, $zipPath);
        File::deleteDirectory($tempDir);

        return [
            'path' => $zipPath,
            'filename' => basename($zipPath),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listBackups(): array
    {
        $root = storage_path('app/' . self::BACKUP_ROOT);
        if (!is_dir($root)) {
            return [];
        }

        $backups = [];
        foreach (scandir($root) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $manifestPath = $root . '/' . $entry . '/manifest.json';
            if (!is_file($manifestPath)) {
                continue;
            }

            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (!is_array($manifest)) {
                continue;
            }

            $backups[] = $manifest;
        }

        usort($backups, fn ($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        return $backups;
    }

    public function restore(string $backupId, ?callable $logger = null): void
    {
        $directory = $this->backupPath($backupId);
        $manifestPath = $directory . '/manifest.json';
        if (!is_file($manifestPath)) {
            throw new DisplayException('The selected backup could not be found.');
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            throw new DisplayException('The backup manifest is invalid.');
        }

        if (is_file($directory . '/.env')) {
            $this->log($logger, 'Restoring .env file...');
            copy($directory . '/.env', base_path('.env'));
        }

        if (is_file($directory . '/storage-app.tar.gz')) {
            $this->log($logger, 'Restoring storage/app archive...');
            $this->extractStorageAppArchive($directory . '/storage-app.tar.gz');
        }

        if (is_file($directory . '/database.sql.gz')) {
            $this->log($logger, 'Restoring database...');
            $this->restoreDatabase($directory . '/database.sql.gz');
        }
    }

    public function pruneOldBackups(int $keep = 5): void
    {
        $backups = $this->listBackups();
        if (count($backups) <= $keep) {
            return;
        }

        $toDelete = array_slice($backups, $keep);
        foreach ($toDelete as $backup) {
            if (!empty($backup['id'])) {
                File::deleteDirectory($this->backupPath((string) $backup['id']));
            }
        }
    }

    private function backupPath(string $id): string
    {
        return storage_path('app/' . self::BACKUP_ROOT . '/' . $id);
    }

    private function dumpDatabase(string $targetPath): void
    {
        $driver = config('database.default');
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new DisplayException(sprintf(
                'Automatic database backups require MySQL/MariaDB. Current driver: %s',
                $driver
            ));
        }

        if (!$this->commandExists('mysqldump')) {
            throw new DisplayException('mysqldump was not found on this server. Install mysql-client to enable automatic database backups.');
        }

        $connection = config('database.connections.' . $driver);
        $command = [
            'mysqldump',
            '--host=' . ($connection['host'] ?? '127.0.0.1'),
            '--port=' . ($connection['port'] ?? '3306'),
            '--user=' . ($connection['username'] ?? ''),
            '--single-transaction',
            '--quick',
            '--lock-tables=false',
            $connection['database'] ?? 'panel',
        ];

        if (!empty($connection['unix_socket'])) {
            $command = [
                'mysqldump',
                '--socket=' . $connection['unix_socket'],
                '--user=' . ($connection['username'] ?? ''),
                '--single-transaction',
                '--quick',
                '--lock-tables=false',
                $connection['database'] ?? 'panel',
            ];
        }

        $env = [];
        if (!empty($connection['password'])) {
            $env['MYSQL_PWD'] = $connection['password'];
        }

        $dump = new Process($command, null, $env);
        $dump->setTimeout(600);
        $dump->run();

        if (!$dump->isSuccessful()) {
            throw new DisplayException('Database backup failed: ' . trim($dump->getErrorOutput() ?: $dump->getOutput()));
        }

        $gzip = Process::fromShellCommandline('gzip -c', null, null, null, 600);
        $gzip->setInput($dump->getOutput());
        $gzip->run();

        if (!$gzip->isSuccessful()) {
            throw new DisplayException('Failed to compress database backup.');
        }

        file_put_contents($targetPath, $gzip->getOutput());
    }

    private function restoreDatabase(string $archivePath): void
    {
        $driver = config('database.default');
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new DisplayException('Database restore requires MySQL/MariaDB.');
        }

        if (!$this->commandExists('mysql')) {
            throw new DisplayException('mysql client was not found on this server.');
        }

        $connection = config('database.connections.' . $driver);
        $command = [
            'mysql',
            '--host=' . ($connection['host'] ?? '127.0.0.1'),
            '--port=' . ($connection['port'] ?? '3306'),
            '--user=' . ($connection['username'] ?? ''),
            $connection['database'] ?? 'panel',
        ];

        if (!empty($connection['unix_socket'])) {
            $command = [
                'mysql',
                '--socket=' . $connection['unix_socket'],
                '--user=' . ($connection['username'] ?? ''),
                $connection['database'] ?? 'panel',
            ];
        }

        $env = [];
        if (!empty($connection['password'])) {
            $env['MYSQL_PWD'] = $connection['password'];
        }

        $gunzip = Process::fromShellCommandline('gzip -dc ' . escapeshellarg($archivePath));
        $gunzip->setTimeout(600);
        $gunzip->run();

        if (!$gunzip->isSuccessful()) {
            throw new DisplayException('Failed to decompress database backup.');
        }

        $import = new Process($command, null, $env);
        $import->setInput($gunzip->getOutput());
        $import->setTimeout(600);
        $import->run();

        if (!$import->isSuccessful()) {
            throw new DisplayException('Database restore failed: ' . trim($import->getErrorOutput() ?: $import->getOutput()));
        }
    }

    private function createStorageAppArchive(string $targetPath): void
    {
        $source = storage_path('app');
        if (!is_dir($source)) {
            return;
        }

        $process = Process::fromShellCommandline(
            sprintf(
                'tar --exclude=./logs --exclude=./framework/cache -czf %s -C %s .',
                escapeshellarg($targetPath),
                escapeshellarg($source)
            )
        );
        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new DisplayException('Failed to archive storage/app: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }

    private function extractStorageAppArchive(string $archivePath): void
    {
        $target = storage_path('app');
        File::ensureDirectoryExists($target);

        $process = Process::fromShellCommandline(
            sprintf('tar -xzf %s -C %s', escapeshellarg($archivePath), escapeshellarg($target))
        );
        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new DisplayException('Failed to restore storage/app archive.');
        }
    }

    private function createZipFromDirectory(string $sourceDirectory, string $zipPath): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new DisplayException('Unable to create download bundle.');
        }

        foreach (File::allFiles($sourceDirectory) as $file) {
            $relative = str_replace($sourceDirectory . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $zip->addFile($file->getPathname(), $relative);
        }

        $zip->close();
    }

    private function commandExists(string $command): bool
    {
        $process = Process::fromShellCommandline('command -v ' . escapeshellarg($command));
        $process->run();

        return $process->isSuccessful();
    }

    private function log(?callable $logger, string $message): void
    {
        if ($logger) {
            $logger($message);
        }
    }
}
