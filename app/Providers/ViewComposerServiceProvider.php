<?php

namespace Pterodactyl\Providers;

use Illuminate\Support\ServiceProvider;
use Pterodactyl\Http\ViewComposers\AssetComposer;
use Pterodactyl\Http\ViewComposers\AdminServerPluginNavigationComposer;
use Pterodactyl\Http\ViewComposers\AdminUpdateBadgeComposer;

class ViewComposerServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     */
    public function boot(): void
    {
        $this->app->make('view')->composer('*', AssetComposer::class);
        $this->app->make('view')->composer('layouts.admin', AdminUpdateBadgeComposer::class);
        $this->app->make('view')->composer(
            'admin.servers.partials.navigation',
            AdminServerPluginNavigationComposer::class
        );
    }
}
