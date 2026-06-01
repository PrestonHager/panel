<?php

namespace Pterodactyl\Tests\Unit\Plugins;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Plugins\PermissionGate;
use Pterodactyl\Plugins\Permissions;
use Pterodactyl\Plugins\Exceptions\PluginPermissionDeniedException;

class PermissionGateTest extends TestCase
{
    public function testAllowsGrantedPermission(): void
    {
        $gate = new PermissionGate('com.example.test', [Permissions::SERVER_READ]);

        $this->assertTrue($gate->allows(Permissions::SERVER_READ));
        $gate->authorize(Permissions::SERVER_READ);
        $this->assertTrue(true);
    }

    public function testDeniesMissingPermission(): void
    {
        $gate = new PermissionGate('com.example.test', []);

        $this->expectException(PluginPermissionDeniedException::class);
        $gate->authorize(Permissions::HTTP_REQUEST);
    }
}
