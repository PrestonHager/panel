<?php

namespace Pterodactyl\Tests\Unit\Plugins;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Plugins\PluginManifest;
use Pterodactyl\Services\Plugins\PluginApiRouter;

class PluginApiRouterAuthTest extends TestCase
{
    public function testMatchesRouteByAuthMode(): void
    {
        $manifest = new PluginManifest(
            id: 'com.example.test',
            name: 'Test',
            version: '1.0.0',
            entry: 'Com\\Example\\Test\\Plugin',
            permissions: [],
            hooks: [],
            apiRoutes: [
                [
                    'method' => 'POST',
                    'path' => '/auth/signup',
                    'handler' => 'Com\\Example\\Test\\Http\\AuthController@signup',
                    'permission' => null,
                    'auth' => 'public',
                ],
                [
                    'method' => 'GET',
                    'path' => '/profile',
                    'handler' => 'Com\\Example\\Test\\Http\\ProfileController@show',
                    'permission' => 'profile.read',
                    'auth' => 'client',
                ],
            ],
        );

        $router = new PluginApiRouter();

        $public = $router->match($manifest, 'POST', '/auth/signup', 'public');
        $this->assertNotNull($public);

        $client = $router->match($manifest, 'GET', '/profile', 'client');
        $this->assertNotNull($client);

        $this->assertNull($router->match($manifest, 'POST', '/auth/signup', 'client'));
    }
}
