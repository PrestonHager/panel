<?php

namespace Pterodactyl\Console\Commands;

use Illuminate\Console\Command;
use Pterodactyl\Services\Panel\UpstreamVersionService;

class UpgradeCheckCommand extends Command
{
    protected $signature = 'p:upgrade:check {--json : Output machine-readable JSON}';

    protected $description = 'Check whether a panel upgrade is available from the configured upstream.';

    public function handle(UpstreamVersionService $upstreamVersionService): int
    {
        try {
            $check = $upstreamVersionService->check();
        } catch (\Throwable $exception) {
            if ($this->option('json')) {
                $this->line(json_encode([
                    'error' => $exception->getMessage(),
                ], JSON_UNESCAPED_SLASHES));
            } else {
                $this->error($exception->getMessage());
            }

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($check, JSON_UNESCAPED_SLASHES));

            return $check['update_available'] ? 1 : self::SUCCESS;
        }

        $this->line('Current version: ' . $check['current_version']);
        $this->line('Upstream repository: ' . $check['repository']);
        $this->line('Upstream branch: ' . $check['branch']);
        $this->line('Check method: ' . $check['check_method']);

        if ($check['latest_version']) {
            $this->line('Latest release: ' . $check['latest_version']);
        }

        if ($check['installed_commit']) {
            $this->line('Installed commit: ' . substr($check['installed_commit'], 0, 12));
        }

        if ($check['remote_commit']) {
            $this->line('Remote commit: ' . substr($check['remote_commit'], 0, 12));
        }

        if ($check['update_available']) {
            $this->warn('An update is available.');

            return 1;
        }

        $this->info('Panel is up to date for the configured upstream.');

        return self::SUCCESS;
    }
}
