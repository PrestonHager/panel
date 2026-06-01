<?php

namespace Pterodactyl\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
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
        $registry->load();
        $registry->bootAll();
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
