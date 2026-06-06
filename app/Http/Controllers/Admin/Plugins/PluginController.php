<?php

namespace Pterodactyl\Http\Controllers\Admin\Plugins;

use Illuminate\View\View;
use Pterodactyl\Models\Plugin;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Plugins\Permissions;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Plugins\PluginManager;
use Pterodactyl\Services\Plugins\ManifestValidator;
use Pterodactyl\Services\Plugins\PluginRegistry;
use Pterodactyl\Services\Plugins\PluginSettingsStore;
use Pterodactyl\Services\Plugins\PluginSettingsValidator;
use Pterodactyl\Services\Plugins\PluginSettingsSchemaService;
use Pterodactyl\Plugins\Exceptions\PluginException;
use Pterodactyl\Http\Requests\Admin\Plugin\InstallPluginRequest;
use Pterodactyl\Http\Requests\Admin\Plugin\UpdatePluginSettingsRequest;
use Pterodactyl\Http\Requests\Admin\Plugin\UpdatePluginPermissionsRequest;

class PluginController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private PluginManager $pluginManager,
        private ManifestValidator $manifestValidator,
        private PluginSettingsSchemaService $settingsSchemaService,
        private PluginSettingsValidator $settingsValidator,
        private PluginSettingsStore $settingsStore,
    ) {
    }

    public function index(): View
    {
        return view('admin.plugins.index', [
            'plugins' => Plugin::query()->orderBy('name')->get(),
            'permissionDescriptions' => Permissions::descriptions(),
        ]);
    }

    public function installForm(): View
    {
        return view('admin.plugins.install');
    }

    public function install(InstallPluginRequest $request): RedirectResponse
    {
        try {
            $plugin = $this->pluginManager->installFromGithub(
                $request->input('github_url'),
                $request->input('source_ref', 'main') ?? 'main',
            );
        } catch (PluginException $exception) {
            $this->alert->danger($exception->getMessage())->flash();

            return redirect()->route('admin.plugins.install')->withInput();
        }

        $this->alert->success(sprintf(
            'Plugin "%s" was installed. Review its permissions and enable it when ready.',
            $plugin->name
        ))->flash();

        return redirect()->route('admin.plugins.view', ['plugin' => $plugin->id]);
    }

    public function view(Plugin $plugin): View
    {
        $manifest = null;
        $pendingPermissions = null;
        $directory = PluginRegistry::directoryFor($plugin->id);
        if (is_dir($directory)) {
            try {
                $manifest = $this->manifestValidator->readFromDirectory($directory);
                $pendingPermissions = $this->pluginManager->pendingPermissionChanges($plugin, $manifest);
            } catch (PluginException) {
            }
        }

        $approvedPermissions = $plugin->approved_permissions ?? $plugin->permissions ?? [];
        $approvedHttpHosts = $plugin->approved_http_hosts ?? $manifest?->httpAllowedHosts ?? [];

        return view('admin.plugins.view', [
            'plugin' => $plugin,
            'manifest' => $manifest,
            'permissionDescriptions' => Permissions::descriptions(),
            'highRiskPermissions' => Permissions::highRisk(),
            'approvedPermissions' => $approvedPermissions,
            'approvedHttpHosts' => $approvedHttpHosts,
            'pendingPermissions' => $pendingPermissions,
        ]);
    }

    public function settings(Plugin $plugin): View
    {
        $schema = $this->settingsSchemaService->forPlugin($plugin);
        $hasStructuredForm = !$schema->isEmpty();

        return view('admin.plugins.settings', [
            'plugin' => $plugin,
            'schema' => $schema,
            'values' => $hasStructuredForm ? $this->settingsStore->readAdminConfig($plugin, $schema) : [],
            'hasStructuredForm' => $hasStructuredForm,
        ]);
    }

    public function updateSettings(UpdatePluginSettingsRequest $request, Plugin $plugin): RedirectResponse
    {
        $schema = $this->settingsSchemaService->forPlugin($plugin);

        if (!$schema->isEmpty() && $request->has('settings')) {
            try {
                $validated = $this->settingsValidator->validate(
                    $schema,
                    (array) $request->input('settings', []),
                    'admin'
                );
                $this->settingsStore->writeAdminConfig($plugin, $schema, $validated);

                foreach ($schema->forSurfaceAndStorage('admin', 'config') as $field) {
                    if ($field->audit && array_key_exists($field->key, $validated)) {
                        Activity::event('plugin:settings.changed')
                            ->property('plugin_id', $plugin->id)
                            ->property('field', $field->key)
                            ->log();
                    }
                }
            } catch (PluginException $exception) {
                $this->alert->danger($exception->getMessage())->flash();

                return redirect()->route('admin.plugins.settings', ['plugin' => $plugin->id])->withInput();
            }
        } else {
            $raw = $request->input('config_json', '{}');
            $config = json_decode($raw, true);
            if (!is_array($config)) {
                $this->alert->danger('Plugin configuration must be valid JSON.')->flash();

                return redirect()->route('admin.plugins.settings', ['plugin' => $plugin->id])->withInput();
            }

            $this->pluginManager->updateConfig($plugin, $config);
        }

        $this->alert->success('Plugin configuration has been updated.')->flash();

        return redirect()->route('admin.plugins.settings', ['plugin' => $plugin->id]);
    }

    public function updatePermissions(UpdatePluginPermissionsRequest $request, Plugin $plugin): RedirectResponse
    {
        $approved = array_values(array_intersect(
            (array) $request->input('approved_permissions', []),
            $plugin->permissions ?? []
        ));

        if (empty($approved)) {
            $this->alert->danger('At least one capability must remain approved.')->flash();

            return redirect()->route('admin.plugins.view', ['plugin' => $plugin->id]);
        }

        $plugin->approved_permissions = $approved;
        $plugin->approved_http_hosts = array_values(array_unique(array_filter(
            (array) $request->input('approved_http_hosts', [])
        )));
        $plugin->save();

        Activity::event('plugin:permissions.approved')
            ->property('plugin_id', $plugin->id)
            ->property('permissions', $approved)
            ->property('http_hosts', $plugin->approved_http_hosts)
            ->log();

        $this->alert->success('Plugin permissions have been updated. Disable and re-enable the plugin for changes to take full effect.')->flash();

        return redirect()->route('admin.plugins.view', ['plugin' => $plugin->id]);
    }

    public function approvePendingPermissions(Plugin $plugin): RedirectResponse
    {
        $directory = PluginRegistry::directoryFor($plugin->id);
        if (!is_dir($directory)) {
            $this->alert->danger('Plugin files are missing.')->flash();

            return redirect()->route('admin.plugins.view', ['plugin' => $plugin->id]);
        }

        try {
            $manifest = $this->manifestValidator->readFromDirectory($directory);
            $this->pluginManager->approvePendingPermissions($plugin, $manifest);
        } catch (PluginException $exception) {
            $this->alert->danger($exception->getMessage())->flash();

            return redirect()->route('admin.plugins.view', ['plugin' => $plugin->id]);
        }

        $this->alert->success('New plugin permissions have been approved.')->flash();

        return redirect()->route('admin.plugins.view', ['plugin' => $plugin->id]);
    }

    public function enable(Plugin $plugin): RedirectResponse
    {
        try {
            $this->pluginManager->enable($plugin);
        } catch (PluginException $exception) {
            $this->alert->danger($exception->getMessage())->flash();

            return redirect()->route('admin.plugins.view', ['plugin' => $plugin->id]);
        }

        $this->alert->success(sprintf('Plugin "%s" has been enabled.', $plugin->name))->flash();

        return redirect()->route('admin.plugins.view', ['plugin' => $plugin->id]);
    }

    public function update(Plugin $plugin): RedirectResponse
    {
        try {
            $plugin = $this->pluginManager->updateFromGithub($plugin);
        } catch (PluginException $exception) {
            $this->alert->danger($exception->getMessage())->flash();

            return redirect()->route('admin.plugins.view', ['plugin' => $plugin->id]);
        }

        $this->alert->success(sprintf(
            'Plugin "%s" was updated from %s (%s).',
            $plugin->name,
            $plugin->source_url,
            $plugin->source_ref
        ))->flash();

        return redirect()->route('admin.plugins.view', ['plugin' => $plugin->id]);
    }

    public function disable(Plugin $plugin): RedirectResponse
    {
        $this->pluginManager->disable($plugin);

        $this->alert->success(sprintf('Plugin "%s" has been disabled.', $plugin->name))->flash();

        return redirect()->route('admin.plugins.view', ['plugin' => $plugin->id]);
    }

    public function destroy(Plugin $plugin): RedirectResponse
    {
        $name = $plugin->name;
        $this->pluginManager->uninstall($plugin);

        $this->alert->success(sprintf('Plugin "%s" has been uninstalled.', $name))->flash();

        return redirect()->route('admin.plugins');
    }
}
