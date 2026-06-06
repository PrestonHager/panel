<?php

namespace Pterodactyl\Console\Commands;

use Illuminate\Console\Command;
use Pterodactyl\Services\Panel\PanelUpgradeService;

class UpgradeSafeCommand extends Command
{
    protected $signature = 'p:upgrade:safe
        {--user= : The user that PHP runs under.}
        {--group= : The group that PHP runs under.}
        {--url= : Override the release archive URL.}
        {--release= : Pin a specific release version.}
        {--skip-download : Skip downloading a release archive.}
        {--no-backup : Skip creating an automatic backup before upgrading.}';

    protected $description = 'Safely upgrade the panel with automatic backup and rollback support.';

    public function handle(PanelUpgradeService $upgradeService): int
    {
        $this->warn('This command will create an automatic backup unless --no-backup is provided.');
        $this->line('Upstream repository: ' . app(\Pterodactyl\Services\Panel\UpstreamVersionService::class)->repository());

        if ($this->input->isInteractive() && !$this->confirm('Continue with the safe upgrade process?', true)) {
            $this->warn('Upgrade cancelled.');

            return self::FAILURE;
        }

        try {
            $result = $upgradeService->run([
                'user' => $this->option('user'),
                'group' => $this->option('group'),
                'url' => $this->option('url'),
                'release' => $this->option('release'),
                'skip_download' => (bool) $this->option('skip-download'),
                'no_backup' => (bool) $this->option('no-backup'),
            ], fn (string $message) => $this->line($message));

            $this->info('Panel upgrade completed successfully.');
            if (!empty($result['backup_id'])) {
                $this->line('Backup ID: ' . $result['backup_id']);
            }
            $this->line('Please ensure Wings instances are also updated: https://pterodactyl.io/wings/1.0/upgrading.html');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            $this->warn('The panel attempted to restore the pre-upgrade backup automatically. Review storage/logs/panel-upgrade-*.log for details.');

            return self::FAILURE;
        }
    }
}
