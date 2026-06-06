<?php

namespace Pterodactyl\Plugins;

final class PanelPluginApi
{
    public const VERSION = '3.0';

    public static function satisfies(?string $required): bool
    {
        if ($required === null || $required === '') {
            return true;
        }

        return version_compare(self::VERSION, $required, '>=');
    }
}
