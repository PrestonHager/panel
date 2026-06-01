<?php

namespace Pterodactyl\Plugins;

use Pterodactyl\Events\Server\Created;
use Pterodactyl\Events\Server\Creating;
use Pterodactyl\Events\Server\Deleted;
use Pterodactyl\Events\Server\Deleting;
use Pterodactyl\Events\Server\Installed;

final class HookMap
{
    public const HOOKS = [
        'server.creating' => Creating::class,
        'server.created' => Created::class,
        'server.installed' => Installed::class,
        'server.deleting' => Deleting::class,
        'server.deleted' => Deleted::class,
    ];

    public static function isValidHook(string $hook): bool
    {
        return array_key_exists($hook, self::HOOKS);
    }

    public static function eventClass(string $hook): ?string
    {
        return self::HOOKS[$hook] ?? null;
    }
}
