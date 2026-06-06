<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Plugins\PluginPublicApiController;

/*
|--------------------------------------------------------------------------
| Plugin Public HTTP API
|--------------------------------------------------------------------------
|
| Endpoint: /api/plugins-public/{plugin_id}/...
|
*/
Route::match(
    ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
    '/{plugin}/{path?}',
    PluginPublicApiController::class
)->where('path', '.*');
