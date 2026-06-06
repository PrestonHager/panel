<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Plugins;

use Illuminate\Http\Request;
use Pterodactyl\Models\Plugin;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Plugins\PluginSettingsField;
use Pterodactyl\Plugins\PluginClientPermissionGate;
use Pterodactyl\Plugins\Exceptions\PluginException;
use Pterodactyl\Plugins\Exceptions\PluginPermissionDeniedException;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Services\Plugins\PluginSettingsStore;
use Pterodactyl\Services\Plugins\PluginSettingsValidator;
use Pterodactyl\Services\Plugins\PluginSettingsSchemaService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PluginSettingsController extends ClientApiController
{
    public function __construct(
        private readonly PluginSettingsSchemaService $schemaService,
        private readonly PluginSettingsValidator $settingsValidator,
        private readonly PluginSettingsStore $settingsStore,
        private readonly PluginClientPermissionGate $clientPermissionGate,
    ) {
        parent::__construct();
    }

    public function showForServer(Request $request, Server $server, Plugin $plugin): array
    {
        $this->assertEnabledPlugin($plugin);
        $schema = $this->schemaService->forPlugin($plugin);
        if (!$schema->hasClientSettings()) {
            throw new NotFoundHttpException('This plugin does not expose client settings.');
        }

        /** @var User $user */
        $user = $request->user();
        $fields = [];
        $values = [];

        foreach ($schema->forSurface('client') as $field) {
            if (!$this->canAccessField($user, $server, $plugin, $field, 'read')) {
                continue;
            }

            $fields[] = $this->serializeField($field);
        }

        foreach ($schema->forSurfaceAndStorage('client', 'server') as $field) {
            if (!$this->canAccessField($user, $server, $plugin, $field, 'read')) {
                continue;
            }
            $serverValues = $this->settingsStore->readServerSettings($plugin, $server, $schema);
            $values[$field->key] = $serverValues[$field->key] ?? $field->default;
        }

        foreach ($schema->forSurfaceAndStorage('client', 'global') as $field) {
            if (!$this->canAccessField($user, $server, $plugin, $field, 'read')) {
                continue;
            }
            $globalValues = $this->settingsStore->readGlobalSettings($plugin, $schema);
            $values[$field->key] = $globalValues[$field->key] ?? $field->default;
        }

        return [
            'object' => 'plugin_settings',
            'attributes' => [
                'plugin_id' => $plugin->id,
                'schema' => ['fields' => $fields],
                'values' => $values,
            ],
        ];
    }

    public function updateForServer(Request $request, Server $server, Plugin $plugin): array
    {
        $this->assertEnabledPlugin($plugin);
        $schema = $this->schemaService->forPlugin($plugin);
        if (!$schema->hasClientSettings()) {
            throw new NotFoundHttpException('This plugin does not expose client settings.');
        }

        /** @var User $user */
        $user = $request->user();
        $input = (array) $request->input('settings', []);
        $filtered = [];

        foreach ($schema->forSurface('client') as $field) {
            if (!array_key_exists($field->key, $input)) {
                continue;
            }

            if (!$this->canAccessField($user, $server, $plugin, $field, 'write')) {
                throw new AccessDeniedHttpException(sprintf('You do not have permission to update "%s".', $field->key));
            }

            $filtered[$field->key] = $input[$field->key];
        }

        try {
            $validated = $this->settingsValidator->validate($schema, $filtered, 'client');
        } catch (PluginException $exception) {
            throw new AccessDeniedHttpException($exception->getMessage());
        }

        $serverValues = [];
        $globalValues = [];
        foreach ($validated as $key => $value) {
            $field = $schema->find($key);
            if (is_null($field)) {
                continue;
            }

            if ($field->storage === 'server') {
                $serverValues[$key] = $value;
            } elseif ($field->storage === 'global') {
                $globalValues[$key] = $value;
            }

            if ($field->audit) {
                Activity::event('plugin:settings.changed')
                    ->property('plugin_id', $plugin->id)
                    ->property('field', $field->key)
                    ->property('server_id', $server->id)
                    ->log();
            }
        }

        if (!empty($serverValues)) {
            $this->settingsStore->writeServerSettings($plugin, $server, $schema, $serverValues);
        }

        if (!empty($globalValues)) {
            $this->settingsStore->writeGlobalSettings($plugin, $schema, $globalValues);
        }

        return $this->showForServer($request, $server, $plugin);
    }

    public function showGlobal(Request $request, Plugin $plugin): array
    {
        $this->assertEnabledPlugin($plugin);
        $schema = $this->schemaService->forPlugin($plugin);
        $globalFields = $schema->forSurfaceAndStorage('client', 'global');
        if (empty($globalFields)) {
            throw new NotFoundHttpException('This plugin does not expose global client settings.');
        }

        /** @var User $user */
        $user = $request->user();
        $fields = [];
        $values = [];

        foreach ($globalFields as $field) {
            if (!$this->canAccessGlobalField($user, $plugin, $field, 'read')) {
                continue;
            }

            $fields[] = $this->serializeField($field);
            $globalValues = $this->settingsStore->readGlobalSettings($plugin, $schema);
            $values[$field->key] = $globalValues[$field->key] ?? $field->default;
        }

        return [
            'object' => 'plugin_settings',
            'attributes' => [
                'plugin_id' => $plugin->id,
                'schema' => ['fields' => $fields],
                'values' => $values,
            ],
        ];
    }

    public function updateGlobal(Request $request, Plugin $plugin): array
    {
        $this->assertEnabledPlugin($plugin);
        $schema = $this->schemaService->forPlugin($plugin);
        $globalFields = $schema->forSurfaceAndStorage('client', 'global');
        if (empty($globalFields)) {
            throw new NotFoundHttpException('This plugin does not expose global client settings.');
        }

        /** @var User $user */
        $user = $request->user();
        $input = (array) $request->input('settings', []);
        $filtered = [];

        foreach ($globalFields as $field) {
            if (!array_key_exists($field->key, $input)) {
                continue;
            }

            if (!$this->canAccessGlobalField($user, $plugin, $field, 'write')) {
                throw new AccessDeniedHttpException(sprintf('You do not have permission to update "%s".', $field->key));
            }

            $filtered[$field->key] = $input[$field->key];
        }

        try {
            $validated = $this->settingsValidator->validate($schema, $filtered, 'client');
        } catch (PluginException $exception) {
            throw new AccessDeniedHttpException($exception->getMessage());
        }

        $this->settingsStore->writeGlobalSettings($plugin, $schema, $validated);

        return $this->showGlobal($request, $plugin);
    }

    private function assertEnabledPlugin(Plugin $plugin): void
    {
        if (!$plugin->enabled) {
            throw new NotFoundHttpException('Plugin is not enabled.');
        }
    }

    private function canAccessField(User $user, Server $server, Plugin $plugin, PluginSettingsField $field, string $mode): bool
    {
        if ($field->isPassword()) {
            return false;
        }

        if ($field->ownerOnly && !$user->root_admin && $user->id !== $server->owner_id) {
            return false;
        }

        if (!$field->permission) {
            return $user->root_admin || $user->id === $server->owner_id;
        }

        try {
            $this->clientPermissionGate->authorize($user, $server, $plugin, $field->permission);
        } catch (PluginPermissionDeniedException) {
            return false;
        }

        return true;
    }

    private function canAccessGlobalField(User $user, Plugin $plugin, PluginSettingsField $field, string $mode): bool
    {
        if ($field->isPassword()) {
            return false;
        }

        if ($user->root_admin) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeField(PluginSettingsField $field): array
    {
        $data = $field->toArray();
        unset($data['sensitive']);

        return $data;
    }
}
