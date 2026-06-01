<?php

namespace Pterodactyl\Http\Controllers\Plugins;

use Pterodactyl\Models\Plugin;
use Illuminate\Http\Response;
use Pterodactyl\Services\Plugins\PluginRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PluginAssetController
{
    public function __invoke(string $plugin, string $path): Response
    {
        if (str_contains($path, '..')) {
            throw new NotFoundHttpException();
        }

        $pluginModel = Plugin::query()->find($plugin);
        if (is_null($pluginModel) || !$pluginModel->enabled) {
            throw new NotFoundHttpException();
        }

        $base = PluginRegistry::directoryFor($plugin);
        $fullPath = $base . '/' . ltrim($path, '/');
        $realBase = realpath($base);
        $realFile = realpath($fullPath);

        if ($realBase === false || $realFile === false || !str_starts_with($realFile, $realBase) || !is_file($realFile)) {
            throw new NotFoundHttpException();
        }

        $mime = match (pathinfo($realFile, PATHINFO_EXTENSION)) {
            'js' => 'application/javascript',
            'css' => 'text/css',
            'map' => 'application/json',
            default => 'application/octet-stream',
        };

        return response(file_get_contents($realFile), 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
