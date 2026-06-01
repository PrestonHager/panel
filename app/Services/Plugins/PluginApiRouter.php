<?php

namespace Pterodactyl\Services\Plugins;

use Pterodactyl\Plugins\PluginManifest;

class PluginApiRouter
{
    /**
     * @return array{route: array{method: string, path: string, handler: string, permission?: string|null}, params: array<string, string>}|null
     */
    public function match(PluginManifest $manifest, string $method, string $path): ?array
    {
        $path = '/' . trim($path, '/');
        if ($path === '/') {
            $path = '';
        }

        foreach ($manifest->apiRoutes as $route) {
            if (strtoupper($route['method']) !== strtoupper($method)) {
                continue;
            }

            $pattern = $this->compilePattern($route['path']);
            if (preg_match($pattern, $path, $matches)) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }

                return ['route' => $route, 'params' => $params];
            }
        }

        return null;
    }

    private function compilePattern(string $path): string
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $path);

        return '#^' . $pattern . '$#';
    }
}
