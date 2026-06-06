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

    public const CONFIG_READ = 'config.read';

    public const API_SERVE = 'api.serve';

    public const UI_REGISTER = 'ui.register';

    public const UI_THEME = 'ui.theme';

    public const USER_READ = 'user.read';

    public const USER_CREATE = 'user.create';

    public const SERVER_CREATE = 'server.create';

    public const SERVER_DELETE = 'server.delete';

    public const UI_CLIENT_REGISTER = 'ui.client.register';

    public const UI_BLOCKS_REGISTER = 'ui.blocks.register';

    public const BLOCKS_EMBED = 'blocks.embed';

    public const ALL = [
        self::EVENTS_SUBSCRIBE,
        self::SETTINGS_READ,
        self::SETTINGS_WRITE,
        self::SERVER_READ,
        self::ALLOCATION_READ,
        self::SERVER_METADATA_WRITE,
        self::HTTP_REQUEST,
        self::ACTIVITY_LOG,
        self::CONFIG_READ,
        self::API_SERVE,
        self::UI_REGISTER,
        self::UI_THEME,
        self::USER_READ,
        self::USER_CREATE,
        self::SERVER_CREATE,
        self::SERVER_DELETE,
        self::UI_CLIENT_REGISTER,
        self::UI_BLOCKS_REGISTER,
        self::BLOCKS_EMBED,
    ];

    public const RESERVED_CLIENT_PERMISSIONS = [
        'admin',
        'panel',
        'root',
        '*',
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
            self::SERVER_METADATA_WRITE => 'Store plugin-owned metadata attached to servers, users, or global scope.',
            self::HTTP_REQUEST => 'Make outbound HTTP requests to allowlisted hosts.',
            self::ACTIVITY_LOG => 'Write entries to the panel activity log.',
            self::CONFIG_READ => 'Read admin-configured plugin configuration (encrypted JSON).',
            self::API_SERVE => 'Register HTTP API routes under /api/plugins/{id}.',
            self::UI_REGISTER => 'Expose plugin UI tabs and assets to the client SPA.',
            self::UI_THEME => 'Contribute approved design token overlays to the panel theme.',
            self::USER_READ => 'Read non-sensitive user profile data.',
            self::USER_CREATE => 'Create user accounts (signup or invite redemption).',
            self::SERVER_CREATE => 'Provision servers on behalf of authenticated users.',
            self::SERVER_DELETE => 'Delete servers owned by authenticated users.',
            self::UI_CLIENT_REGISTER => 'Register dashboard-level client SPA tabs (ui.client).',
            self::UI_BLOCKS_REGISTER => 'Register block types for the block designer runtime.',
            self::BLOCKS_EMBED => 'Allow this plugin\'s blocks to be embedded in other plugins\' layouts.',
        ];
    }

    /**
     * @return string[]
     */
    public static function highRisk(): array
    {
        return [
            self::HTTP_REQUEST,
            self::EVENTS_SUBSCRIBE,
            self::API_SERVE,
            self::SETTINGS_WRITE,
            self::SERVER_METADATA_WRITE,
            self::USER_CREATE,
            self::SERVER_CREATE,
            self::SERVER_DELETE,
        ];
    }
}
