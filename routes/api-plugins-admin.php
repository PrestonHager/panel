<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Plugins\PluginAdminApiController;

/*
|--------------------------------------------------------------------------
| Plugin Admin HTTP API
|--------------------------------------------------------------------------
|
| Endpoint: /api/plugins-admin/{plugin_id}/...
|
*/
Route::match(
    ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
    '/{plugin}/{path?}',
    PluginAdminApiController::class
)->where('path', '.*');
