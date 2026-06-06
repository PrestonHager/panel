<?php

namespace Pterodactyl\Tests\Unit\Plugins;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Plugins\PluginSettingsField;
use Pterodactyl\Plugins\PluginSettingsSchema;
use Pterodactyl\Services\Plugins\PluginSettingsValidator;

class PluginSettingsValidatorTest extends TestCase
{
    public function testValidatesBooleanAndPasswordSentinel(): void
    {
        $field = new PluginSettingsField(
            key: 'flag',
            type: 'boolean',
            label: 'Flag',
            surfaces: ['admin'],
            storage: 'config',
        );

        $schema = new PluginSettingsSchema([$field]);
        $validator = new PluginSettingsValidator();

        $result = $validator->validate($schema, ['flag' => '1'], 'admin');
        $this->assertTrue($result['flag']);

        $passwordField = new PluginSettingsField(
            key: 'secret',
            type: 'password',
            label: 'Secret',
            surfaces: ['admin'],
            storage: 'config',
            sensitive: true,
        );
        $passwordSchema = new PluginSettingsSchema([$passwordField]);
        $validated = $validator->validate($passwordSchema, ['secret' => PluginSettingsField::PASSWORD_MASK], 'admin');
        $this->assertEmpty($validated);
    }
}
