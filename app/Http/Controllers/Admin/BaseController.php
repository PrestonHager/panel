<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Panel\UpdateOverviewService;
use Pterodactyl\Services\Helpers\SoftwareVersionService;

class BaseController extends Controller
{
    public function __construct(
        private UpdateOverviewService $updateOverview,
        private SoftwareVersionService $version,
    ) {
    }

    public function index(): View
    {
        return view('admin.index', [
            'updateSummary' => $this->updateOverview->summary(),
            'version' => $this->version,
        ]);
    }
}
