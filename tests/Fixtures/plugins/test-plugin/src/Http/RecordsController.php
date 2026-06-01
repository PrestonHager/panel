<?php

namespace Pterodactyl\TestPlugin\Http;

use Pterodactyl\Plugins\PluginContext;
use Pterodactyl\Plugins\Http\PluginHttpRequest;
use Pterodactyl\Plugins\Http\PluginHttpResponse;

class RecordsController
{
    public function index(PluginContext $context, PluginHttpRequest $request): PluginHttpResponse
    {
        return PluginHttpResponse::json([
            'plugin_id' => $context->id(),
            'server_id' => $request->serverId,
        ]);
    }
}
