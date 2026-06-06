<?php

namespace Pterodactyl\Tests\Unit\Plugins;

use Pterodactyl\Plugins\DesignTokenRegistry;
use Pterodactyl\Plugins\Exceptions\PluginException;
use Pterodactyl\Tests\Integration\IntegrationTestCase;

class DesignTokenRegistryTest extends IntegrationTestCase
{
    public function testWhitelistContainsPrimaryColorToken(): void
    {
        $registry = app(DesignTokenRegistry::class);

        $this->assertContains('color.primary', $registry->whitelist());
        $this->assertTrue($registry->isAllowedKey('color.primary'));
        $this->assertFalse($registry->isAllowedKey('color.unknown'));
    }

    public function testTokenToCssVariable(): void
    {
        $registry = app(DesignTokenRegistry::class);

        $this->assertSame('--pt-color-primary', $registry->tokenToCssVariable('color.primary'));
        $this->assertSame('--pt-color-bg-surface', $registry->tokenToCssVariable('color.bg.surface'));
    }

    public function testBaseTokensForClientSurface(): void
    {
        $registry = app(DesignTokenRegistry::class);
        $tokens = $registry->baseTokensForSurface('client');

        $this->assertArrayHasKey('color.primary', $tokens);
        $this->assertSame('#2563eb', $tokens['color.primary']);
    }

    public function testValidateTokenMapRejectsUnknownKeys(): void
    {
        $registry = app(DesignTokenRegistry::class);

        $this->expectException(PluginException::class);
        $registry->validateTokenMap(['color.not.real' => '#000000']);
    }

    public function testValidateTokenMapAcceptsWhitelistedKeys(): void
    {
        $registry = app(DesignTokenRegistry::class);

        $validated = $registry->validateTokenMap(['color.primary' => ' #7c3aed ']);

        $this->assertSame('#7c3aed', $validated['color.primary']);
    }
}
