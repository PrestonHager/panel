<?php

namespace Pterodactyl\Tests\Unit\Plugins;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Plugins\PermissionGate;
use Pterodactyl\Plugins\Permissions;
use Pterodactyl\Plugins\Accessors\SettingsAccessor;
use Pterodactyl\Plugins\Exceptions\PluginException;
use Pterodactyl\Plugins\Exceptions\PluginPermissionDeniedException;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class SettingsAccessorTest extends TestCase
{
    public function testSetRequiresWritePermission(): void
    {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $gate = new PermissionGate('com.example.test', [Permissions::SETTINGS_READ]);
        $accessor = new SettingsAccessor('com.example.test', $gate, $settings);

        $this->expectException(PluginPermissionDeniedException::class);
        $accessor->set('api_key', 'secret');
    }

    public function testScopedKeyPrefixIsApplied(): void
    {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $settings->expects($this->once())
            ->method('set')
            ->with('plugins.com.example.test.api_key', 'value');

        $gate = new PermissionGate('com.example.test', [
            Permissions::SETTINGS_READ,
            Permissions::SETTINGS_WRITE,
        ]);
        $accessor = new SettingsAccessor('com.example.test', $gate, $settings);
        $accessor->set('api_key', 'value');
    }

    public function testRejectsForeignSettingsNamespace(): void
    {
        $settings = $this->createMock(SettingsRepositoryInterface::class);
        $gate = new PermissionGate('com.example.test', [
            Permissions::SETTINGS_READ,
            Permissions::SETTINGS_WRITE,
        ]);
        $accessor = new SettingsAccessor('com.example.test', $gate, $settings);

        $this->expectException(PluginException::class);
        $accessor->set('plugins.other.plugin.key', 'value');
    }
}
