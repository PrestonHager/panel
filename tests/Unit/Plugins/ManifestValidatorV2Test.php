<?php

namespace Pterodactyl\Tests\Unit\Plugins;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Services\Plugins\ManifestValidator;
use Pterodactyl\Plugins\Exceptions\InvalidPluginManifestException;

class ManifestValidatorV2Test extends TestCase
{
    private ManifestValidator $validator;

    public function setUp(): void
    {
        parent::setUp();
        $this->validator = new ManifestValidator();
    }

    public function testReadsV2Fixture(): void
    {
        $path = dirname(__DIR__, 2) . '/Fixtures/plugins/test-plugin';
        $manifest = $this->validator->readFromDirectory($path);

        $this->assertSame('2.0', $manifest->requiresPanelPluginApi);
        $this->assertArrayHasKey('records.read', $manifest->clientPermissions);
        $this->assertNotEmpty($manifest->apiRoutes);
        $this->assertNotNull($manifest->uiServer());
    }

    public function testRejectsReservedClientPermission(): void
    {
        $this->expectException(InvalidPluginManifestException::class);

        $this->validator->validate([
            'id' => 'com.example.test',
            'name' => 'Test',
            'version' => '1.0.0',
            'entry' => 'Com\\Example\\Test\\Plugin',
            'permissions' => [],
            'clientPermissions' => ['admin' => 'Bad'],
        ]);
    }

    public function testApiRoutesRequireApiServe(): void
    {
        $this->expectException(InvalidPluginManifestException::class);

        $this->validator->validate([
            'id' => 'com.example.test',
            'name' => 'Test',
            'version' => '1.0.0',
            'entry' => 'Com\\Example\\Test\\Plugin',
            'permissions' => [],
            'clientPermissions' => ['records.read' => 'Read'],
            'api' => [
                'routes' => [
                    [
                        'method' => 'GET',
                        'path' => '/servers/{server}/records',
                        'handler' => 'Com\\Example\\Test\\Http\\RecordsController@index',
                        'permission' => 'records.read',
                    ],
                ],
            ],
        ]);
    }
}
