<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Base;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;

Route::get('/', [Base\IndexController::class, 'index'])->name('index')->fallback();
Route::get('/account', [Base\IndexController::class, 'index'])
    ->withoutMiddleware(RequireTwoFactorAuthentication::class)
    ->name('account');

Route::get('/locales/locale.json', Base\LocaleController::class)
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->where('namespace', '.*');

Route::get('/plugins-assets/{plugin}/{path}', [\Pterodactyl\Http\Controllers\Plugins\PluginAssetController::class, '__invoke'])
    ->where('path', '.*');

Route::get('/plugins/panel-theme.css', [\Pterodactyl\Http\Controllers\Plugins\PluginThemeController::class, '__invoke'])
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->name('plugins.panel-theme');

Route::get('/plugins/panel-tokens.css', function () {
    $path = public_path('plugins/panel-tokens.css');
    abort_unless(is_file($path), 404);

    return response(file_get_contents($path), 200, [
        'Content-Type' => 'text/css; charset=UTF-8',
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->name('plugins.panel-tokens');

Route::get('/{react}', [Base\IndexController::class, 'index'])
    ->where('react', '^(?!(\/)?(api|auth|admin|daemon|plugins-assets)).+');
