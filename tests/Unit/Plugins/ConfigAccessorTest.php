<?php

namespace Pterodactyl\Tests\Unit\Plugins;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Plugins\PermissionGate;
use Pterodactyl\Plugins\Permissions;
use Pterodactyl\Plugins\Accessors\ConfigAccessor;
use Pterodactyl\Plugins\Exceptions\PluginPermissionDeniedException;

class ConfigAccessorTest extends TestCase
{
    public function testGetRequiresConfigRead(): void
    {
        $gate = new PermissionGate('com.example.test', [Permissions::CONFIG_READ]);
        $accessor = new ConfigAccessor($gate, ['api_token' => 'secret']);

        $this->assertSame('secret', $accessor->get('api_token'));
    }

    public function testDenyWithoutPermission(): void
    {
        $gate = new PermissionGate('com.example.test', []);
        $accessor = new ConfigAccessor($gate, ['api_token' => 'secret']);

        $this->expectException(PluginPermissionDeniedException::class);
        $accessor->get('api_token');
    }
}
