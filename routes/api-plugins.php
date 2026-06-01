<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Plugins\PluginApiController;

/*
|--------------------------------------------------------------------------
| Plugin HTTP API
|--------------------------------------------------------------------------
|
| Endpoint: /api/plugins/{plugin_id}/...
|
*/
Route::match(
    ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
    '/{plugin}/{path?}',
    PluginApiController::class
)->where('path', '.*');
