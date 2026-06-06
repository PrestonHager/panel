<?php

namespace Pterodactyl\Jobs\Panel;

use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Pterodactyl\Services\Panel\PanelUpgradeService;

class RunPanelUpgradeJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public function __construct()
    {
        $this->onQueue('high');
    }

    public function handle(PanelUpgradeService $upgradeService): void
    {
        $upgradeService->resetStatus();

        try {
            $upgradeService->run([], fn (string $message) => logger()->info('[panel-upgrade] ' . $message));
        } catch (\Throwable $exception) {
            logger()->error('[panel-upgrade] ' . $exception->getMessage());
        }
    }
}
