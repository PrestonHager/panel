<?php

namespace Pterodactyl\Http\Controllers\Plugins;

use Illuminate\Http\Response;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Plugins\PluginThemeService;

class PluginThemeController extends Controller
{
    public function __invoke(PluginThemeService $themeService): Response
    {
        $css = $themeService->overlayCss();

        return response($css, 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
