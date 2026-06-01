<?php

namespace Pterodactyl\Tests\Unit\Plugins;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Plugins\PluginManifest;
use Pterodactyl\Services\Plugins\PluginApiRouter;

class PluginApiRouterTest extends TestCase
{
    public function testMatchesApiRoute(): void
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
                    'method' => 'GET',
                    'path' => '/servers/{server}/records',
                    'handler' => 'Com\\Example\\Test\\Http\\RecordsController@index',
                    'permission' => 'records.read',
                ],
            ],
        );

        $router = new PluginApiRouter();
        $match = $router->match($manifest, 'GET', 'servers/abc-uuid/records');

        $this->assertNotNull($match);
        $this->assertSame('abc-uuid', $match['params']['server']);
    }
}
