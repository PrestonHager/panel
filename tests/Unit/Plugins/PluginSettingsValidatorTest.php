<?php

namespace Pterodactyl\Tests\Unit\Plugins;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Plugins\PluginSettingsField;
use Pterodactyl\Plugins\PluginSettingsSchema;
use Pterodactyl\Plugins\Exceptions\PluginException;
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

    public function testValidatesListWithAppendReorderAndDefaults(): void
    {
        $listField = $this->makeProfileListField();
        $schema = new PluginSettingsSchema([$listField]);
        $validator = new PluginSettingsValidator();

        $result = $validator->validate($schema, [
            'profiles' => [
                ['id' => 'second', 'label' => 'Second'],
                ['id' => 'first', 'label' => 'First', 'auto_provision' => true],
            ],
        ], 'admin');

        $this->assertCount(2, $result['profiles']);
        $this->assertSame('second', $result['profiles'][0]['id']);
        $this->assertSame('first', $result['profiles'][1]['id']);
        $this->assertTrue($result['profiles'][1]['auto_provision']);
        $this->assertFalse($result['profiles'][0]['auto_provision']);
    }

    public function testValidatesListFromJsonString(): void
    {
        $listField = $this->makeProfileListField();
        $schema = new PluginSettingsSchema([$listField]);
        $validator = new PluginSettingsValidator();

        $result = $validator->validate($schema, [
            'profiles' => json_encode([['id' => 'only-one', 'label' => 'Only']]),
        ], 'admin');

        $this->assertSame('only-one', $result['profiles'][0]['id']);
    }

    public function testEnforcesMinAndMaxItems(): void
    {
        $listField = $this->makeProfileListField(minItems: 1, maxItems: 2);
        $schema = new PluginSettingsSchema([$listField]);
        $validator = new PluginSettingsValidator();

        $this->expectException(PluginException::class);
        $validator->validate($schema, ['profiles' => []], 'admin');
    }

    public function testRejectsInvalidListRowField(): void
    {
        $listField = $this->makeProfileListField();
        $schema = new PluginSettingsSchema([$listField]);
        $validator = new PluginSettingsValidator();

        $this->expectException(PluginException::class);
        $validator->validate($schema, [
            'profiles' => [
                ['label' => 'Missing ID'],
            ],
        ], 'admin');
    }

    private function makeProfileListField(?int $minItems = null, ?int $maxItems = null): PluginSettingsField
    {
        return new PluginSettingsField(
            key: 'profiles',
            type: 'list',
            label: 'Profiles',
            surfaces: ['admin'],
            storage: 'config',
            default: [],
            itemLabel: 'Profile',
            minItems: $minItems,
            maxItems: $maxItems,
            itemFields: [
                new PluginSettingsField(
                    key: 'id',
                    type: 'string',
                    label: 'ID',
                    surfaces: ['admin'],
                    storage: 'config',
                    required: true,
                ),
                new PluginSettingsField(
                    key: 'label',
                    type: 'string',
                    label: 'Label',
                    surfaces: ['admin'],
                    storage: 'config',
                ),
                new PluginSettingsField(
                    key: 'auto_provision',
                    type: 'boolean',
                    label: 'Auto',
                    surfaces: ['admin'],
                    storage: 'config',
                    default: false,
                ),
            ],
        );
    }
}
