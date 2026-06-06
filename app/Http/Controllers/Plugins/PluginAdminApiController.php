<?php

namespace Pterodactyl\Http\Controllers\Plugins;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PluginAdminApiController
{
    public function __construct(
        private readonly PluginApiDispatcher $dispatcher,
    ) {
    }

    public function __invoke(Request $request, string $plugin, string $path = ''): JsonResponse
    {
        return $this->dispatcher->dispatch($request, $plugin, $path, 'admin');
    }
}
