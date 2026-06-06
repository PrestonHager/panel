<?php

namespace Pterodactyl\Http\Controllers\Admin\Settings;

use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Prologue\Alerts\AlertsMessageBag;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Pterodactyl\Jobs\Panel\RunPanelUpgradeJob;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Panel\PanelBackupService;
use Pterodactyl\Services\Panel\PanelUpgradeService;
use Pterodactyl\Services\Panel\UpstreamVersionService;
use Pterodactyl\Services\Panel\UpdateOverviewService;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Http\Requests\Admin\Settings\UpdateSettingsFormRequest;

class UpdatesController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private SettingsRepositoryInterface $settings,
        private PanelBackupService $backupService,
        private PanelUpgradeService $upgradeService,
        private UpstreamVersionService $upstreamVersionService,
        private UpdateOverviewService $updateOverviewService,
    ) {
    }

    public function index(): View
    {
        $updateCheck = $this->upstreamVersionService->check();
        $updateSummary = $this->updateOverviewService->summary();

        return view('admin.settings.updates', [
            'currentVersion' => config('app.version'),
            'latestRelease' => [
                'version' => $updateCheck['latest_version'],
                'tag' => $updateCheck['latest_tag'],
                'url' => $updateCheck['release_url'],
                'status' => $updateCheck['release_status'] ?? 'unknown',
            ],
            'updateCheck' => $updateCheck,
            'updateAvailable' => $updateCheck['update_available'],
            'detectedMode' => $this->upstreamVersionService->detectInstallMode(),
            'backups' => $this->backupService->listBackups(),
            'status' => $this->upgradeService->getStatus(),
            'repository' => $this->upstreamVersionService->repository(),
            'mode' => $this->upstreamVersionService->mode(),
            'branch' => $this->upstreamVersionService->branch(),
            'release' => $this->upstreamVersionService->release(),
            'gitRemote' => $this->upstreamVersionService->gitRemote(),
            'outdatedPluginCount' => $updateSummary['plugins']['outdated_count'],
        ]);
    }

    public function update(UpdateSettingsFormRequest $request): RedirectResponse
    {
        foreach ($request->normalize() as $key => $value) {
            $this->settings->set('settings::' . $key, $value ?? '');
        }

        $this->upstreamVersionService->clearUpdateCache();

        $this->alert->success('Update settings have been saved successfully.')->flash();

        return redirect()->route('admin.settings.updates');
    }

    public function downloadBundle(): BinaryFileResponse|RedirectResponse
    {
        try {
            $bundle = $this->backupService->createDownloadBundle();
        } catch (\Throwable $exception) {
            $this->alert->danger($exception->getMessage())->flash();

            return redirect()->route('admin.settings.updates');
        }

        return response()->download($bundle['path'], $bundle['filename'])->deleteFileAfterSend(true);
    }

    public function run(): RedirectResponse
    {
        $status = $this->upgradeService->getStatus();
        if (($status['state'] ?? 'idle') === 'running') {
            $this->alert->warning('An upgrade is already in progress.')->flash();

            return redirect()->route('admin.settings.updates');
        }

        RunPanelUpgradeJob::dispatch();

        $this->alert->success('Panel upgrade has been queued. Monitor progress on this page.')->flash();

        return redirect()->route('admin.settings.updates');
    }

    public function status(): JsonResponse
    {
        return response()->json($this->upgradeService->getStatus());
    }

    public function restore(string $backup): RedirectResponse
    {
        $status = $this->upgradeService->getStatus();
        if (($status['state'] ?? 'idle') === 'running') {
            $this->alert->warning('Cannot restore while an upgrade is running.')->flash();

            return redirect()->route('admin.settings.updates');
        }

        try {
            $this->backupService->restore($backup);
            $this->alert->success('Backup restored successfully.')->flash();
        } catch (\Throwable $exception) {
            $this->alert->danger($exception->getMessage())->flash();
        }

        return redirect()->route('admin.settings.updates');
    }
}
