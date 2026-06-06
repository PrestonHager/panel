<?php

namespace Pterodactyl\Console\Commands;

use Illuminate\Console\Command;
use Pterodactyl\Services\Panel\PanelUpgradeService;

class UpgradeCommand extends Command
{
    protected $signature = 'p:upgrade
        {--user= : The user that PHP runs under. All files will be owned by this user.}
        {--group= : The group that PHP runs under. All files will be owned by this group.}
        {--url= : The specific archive to download.}
        {--release= : A specific Pterodactyl version to download from GitHub. Leave blank to use latest.}
        {--skip-download : If set no archive will be downloaded.}';

    protected $description = 'Downloads a new archive for Pterodactyl from GitHub and then executes the normal upgrade commands.';

    public function handle(PanelUpgradeService $upgradeService): int
    {
        $skipDownload = (bool) $this->option('skip-download');
        if (!$skipDownload) {
            $this->output->warning('This command does not verify the integrity of downloaded assets. Consider using php artisan p:upgrade:safe for automatic backups and rollback support.');
            $upstream = app(\Pterodactyl\Services\Panel\UpstreamVersionService::class);
            $this->output->comment('Download Source:');
            $this->line($this->option('url') ?: $upstream->releaseDownloadUrl($this->option('release')));
        }

        if ($this->input->isInteractive()) {
            if (!$skipDownload && !$this->confirm('Would you like to download and unpack the archive files for the latest version?', true)) {
                $skipDownload = true;
            }

            if (!$this->confirm('Are you sure you want to run the upgrade process for your Panel?', true)) {
                $this->warn('Upgrade process terminated by user.');

                return self::FAILURE;
            }
        }

        ini_set('output_buffering', '0');
        $bar = $this->output->createProgressBar(10);
        $bar->start();

        try {
            $upgradeService->run([
                'user' => $this->option('user'),
                'group' => $this->option('group'),
                'url' => $this->option('url'),
                'release' => $this->option('release'),
                'skip_download' => $skipDownload,
                'no_backup' => true,
            ], function (string $message) use ($bar) {
                $bar->clear();
                $this->line($message);
                $bar->advance();
                $bar->display();
            });

            $bar->finish();
            $this->newLine(2);
            $this->info('Panel has been successfully upgraded. Please ensure you also update any Wings instances: https://pterodactyl.io/wings/1.0/upgrading.html');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $bar->finish();
            $this->newLine(2);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
