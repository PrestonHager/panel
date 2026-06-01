<?php

namespace Pterodactyl\Plugins;

final class Permissions
{
    public const EVENTS_SUBSCRIBE = 'events.subscribe';

    public const SETTINGS_READ = 'settings.read';

    public const SETTINGS_WRITE = 'settings.write';

    public const SERVER_READ = 'server.read';

    public const ALLOCATION_READ = 'allocation.read';

    public const SERVER_METADATA_WRITE = 'server.metadata.write';

    public const HTTP_REQUEST = 'http.request';

    public const ACTIVITY_LOG = 'activity.log';

    public const ALL = [
        self::EVENTS_SUBSCRIBE,
        self::SETTINGS_READ,
        self::SETTINGS_WRITE,
        self::SERVER_READ,
        self::ALLOCATION_READ,
        self::SERVER_METADATA_WRITE,
        self::HTTP_REQUEST,
        self::ACTIVITY_LOG,
    ];

    public static function isValid(string $permission): bool
    {
        return in_array($permission, self::ALL, true);
    }

    /**
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        return [
            self::EVENTS_SUBSCRIBE => 'Subscribe to panel lifecycle event hooks declared in the manifest.',
            self::SETTINGS_READ => 'Read plugin-scoped settings keys (plugins.{id}.*).',
            self::SETTINGS_WRITE => 'Write plugin-scoped settings keys (plugins.{id}.*).',
            self::SERVER_READ => 'Read non-sensitive server metadata.',
            self::ALLOCATION_READ => 'Read allocation IP and port information for servers.',
            self::SERVER_METADATA_WRITE => 'Store plugin-owned metadata attached to servers.',
            self::HTTP_REQUEST => 'Make outbound HTTP requests to allowlisted hosts.',
            self::ACTIVITY_LOG => 'Write entries to the panel activity log.',
        ];
    }
}
