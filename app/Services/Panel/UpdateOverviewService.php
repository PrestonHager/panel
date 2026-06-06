<?php

namespace Pterodactyl\Services\Panel;

use Pterodactyl\Services\Plugins\PluginVersionService;

class UpdateOverviewService
{
    public function __construct(
        private readonly UpstreamVersionService $upstreamVersionService,
        private readonly PluginVersionService $pluginVersionService,
    ) {
    }

    /**
     * @return array{
     *     version: ?string,
     *     tag: ?string,
     *     url: ?string,
     *     update_available: bool,
     *     current_version: string
     * }
     */
    public function panelStatus(): array
    {
        $latest = $this->upstreamVersionService->latestRelease();

        return [
            'version' => $latest['version'],
            'tag' => $latest['tag'],
            'url' => $latest['url'],
            'update_available' => $this->upstreamVersionService->isUpdateAvailable(),
            'current_version' => (string) config('app.version'),
        ];
    }

    /**
     * @return array{
     *     checks: array<string, array<string, mixed>>,
     *     outdated_count: int,
     *     outdated_ids: string[]
     * }
     */
    public function pluginStatus(): array
    {
        $checks = $this->pluginVersionService->checkAll();
        $outdatedIds = array_keys(array_filter(
            $checks,
            fn (array $check) => $check['update_available']
        ));

        return [
            'checks' => $checks,
            'outdated_count' => count($outdatedIds),
            'outdated_ids' => $outdatedIds,
        ];
    }

    /**
     * @return array{
     *     panel: array<string, mixed>,
     *     plugins: array<string, mixed>,
     *     total_outdated_count: int
     * }
     */
    public function summary(): array
    {
        $panel = $this->panelStatus();
        $plugins = $this->pluginStatus();

        return [
            'panel' => $panel,
            'plugins' => $plugins,
            'total_outdated_count' => ($panel['update_available'] ? 1 : 0) + $plugins['outdated_count'],
        ];
    }
}
