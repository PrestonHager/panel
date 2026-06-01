<?php

namespace Pterodactyl\Tests\Unit\Plugins;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Services\Plugins\ManifestValidator;
use Pterodactyl\Plugins\Exceptions\InvalidPluginManifestException;

class ManifestValidatorTest extends TestCase
{
    private ManifestValidator $validator;

    public function setUp(): void
    {
        parent::setUp();
        $this->validator = new ManifestValidator();
    }

    public function testValidManifestPasses(): void
    {
        $manifest = $this->validator->validate([
            'id' => 'com.example.test',
            'name' => 'Test',
            'version' => '1.0.0',
            'entry' => 'Com\\Example\\Test\\Plugin',
            'permissions' => ['events.subscribe'],
            'hooks' => [],
        ]);

        $this->assertSame('com.example.test', $manifest->id);
        $this->assertSame('1.0.0', $manifest->version);
    }

    public function testUnknownPermissionFails(): void
    {
        $this->expectException(InvalidPluginManifestException::class);

        $this->validator->validate([
            'id' => 'com.example.test',
            'name' => 'Test',
            'version' => '1.0.0',
            'entry' => 'Com\\Example\\Test\\Plugin',
            'permissions' => ['invalid.permission'],
        ]);
    }

    public function testHooksRequireEventsSubscribePermission(): void
    {
        $this->expectException(InvalidPluginManifestException::class);

        $this->validator->validate([
            'id' => 'com.example.test',
            'name' => 'Test',
            'version' => '1.0.0',
            'entry' => 'Com\\Example\\Test\\Plugin',
            'permissions' => ['server.read'],
            'hooks' => [
                'server.created' => 'Com\\Example\\Test\\Listener',
            ],
        ]);
    }

    public function testReadsFixtureManifest(): void
    {
        $path = dirname(__DIR__, 2) . '/Fixtures/plugins/test-plugin';
        $manifest = $this->validator->readFromDirectory($path);

        $this->assertSame('com.pterodactyl.test-plugin', $manifest->id);
        $this->assertArrayHasKey('server.created', $manifest->hooks);
    }
}
