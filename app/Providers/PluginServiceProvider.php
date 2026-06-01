<?php

namespace Pterodactyl\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Pterodactyl\Plugins\Exceptions\PluginException;
use Pterodactyl\Services\Plugins\PluginRegistry;
use Pterodactyl\Plugins\PluginEventSubscriber;
use Illuminate\Contracts\Events\Dispatcher;

class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PluginRegistry::class);
    }

    public function boot(Dispatcher $events): void
    {
        $events->subscribe($this->app->make(PluginEventSubscriber::class));

        if (!$this->canBootPlugins()) {
            return;
        }

        $registry = $this->app->make(PluginRegistry::class);

        try {
            $registry->load();
        } catch (\Throwable $exception) {
            Log::error('Failed to load plugins.', ['message' => $exception->getMessage()]);

            return;
        }

        foreach (array_keys($registry->entries()) as $pluginId) {
            try {
                $registry->bootEntry($pluginId);
            } catch (PluginException $exception) {
                Log::error('Failed to boot plugin.', [
                    'plugin_id' => $pluginId,
                    'message' => $exception->getMessage(),
                ]);
            } catch (\Throwable $exception) {
                Log::error('Unexpected error while booting plugin.', [
                    'plugin_id' => $pluginId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function canBootPlugins(): bool
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            return false;
        }

        try {
            return Schema::hasTable('plugins');
        } catch (\Throwable) {
            return false;
        }
    }
}
