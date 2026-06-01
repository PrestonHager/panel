<?php

namespace Pterodactyl\Services\Plugins;

use Pterodactyl\Plugins\PluginManifest;

class PluginAutoloader
{
    /** @var array<string, bool> */
    private array $registered = [];

    public function register(PluginManifest $manifest, string $directory): void
    {
        $prefix = $manifest->namespacePrefix();
        if (isset($this->registered[$prefix])) {
            return;
        }

        $src = rtrim($directory, '/') . '/src';
        if (!is_dir($src)) {
            return;
        }

        spl_autoload_register(function (string $class) use ($prefix, $src): void {
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $path = $src . '/' . str_replace('\\', '/', $relative) . '.php';
            if (is_file($path)) {
                require_once $path;
            }
        }, true, true);

        $this->registered[$prefix] = true;
    }

    public function unregisterAll(): void
    {
        $this->registered = [];
    }
}
