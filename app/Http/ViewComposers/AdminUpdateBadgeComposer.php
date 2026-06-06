<?php

namespace Pterodactyl\Http\ViewComposers;

use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Pterodactyl\Services\Panel\UpdateOverviewService;

class AdminUpdateBadgeComposer
{
    public function __construct(
        private readonly UpdateOverviewService $updateOverviewService,
    ) {
    }

    public function compose(View $view): void
    {
        $user = Auth::user();
        if (!$user || !$user->root_admin) {
            $view->with('adminUpdateBadgeCount', 0);

            return;
        }

        $summary = $this->updateOverviewService->summary();

        $view->with('adminUpdateBadgeCount', $summary['total_outdated_count']);
    }
}
