<?php

namespace Pterodactyl\Http\ViewComposers;

use Illuminate\View\View;
use Pterodactyl\Services\Plugins\PluginUiConfigService;

class AdminServerPluginNavigationComposer
{
    public function __construct(
        private readonly PluginUiConfigService $pluginUiConfigService,
    ) {
    }

    public function compose(View $view): void
    {
        $view->with('adminServerPluginTabs', $this->pluginUiConfigService->enabledAdminServerPlugins());
    }
}
