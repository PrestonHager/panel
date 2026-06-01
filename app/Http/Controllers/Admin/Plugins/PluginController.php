<?php

namespace Pterodactyl\Http\Controllers\Admin\Plugins;

use Illuminate\View\View;
use Pterodactyl\Models\Plugin;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Plugins\Permissions;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Plugins\PluginManager;
use Pterodactyl\Services\Plugins\ManifestValidator;
use Pterodactyl\Services\Plugins\PluginRegistry;
use Pterodactyl\Plugins\Exceptions\PluginException;
use Pterodactyl\Http\Requests\Admin\Plugin\InstallPluginRequest;
use Pterodactyl\Http\Requests\Admin\Plugin\UpdatePluginSettingsRequest;

class PluginController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private PluginManager $pluginManager,
        private ManifestValidator $manifestValidator,
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
        $directory = PluginRegistry::directoryFor($plugin->id);
        if (is_dir($directory)) {
            try {
                $manifest = $this->manifestValidator->readFromDirectory($directory);
            } catch (PluginException) {
            }
        }

        return view('admin.plugins.view', [
            'plugin' => $plugin,
            'manifest' => $manifest,
            'permissionDescriptions' => Permissions::descriptions(),
        ]);
    }

    public function settings(Plugin $plugin): View
    {
        return view('admin.plugins.settings', [
            'plugin' => $plugin,
        ]);
    }

    public function updateSettings(UpdatePluginSettingsRequest $request, Plugin $plugin): RedirectResponse
    {
        $raw = $request->input('config_json', '{}');
        $config = json_decode($raw, true);
        if (!is_array($config)) {
            $this->alert->danger('Plugin configuration must be valid JSON.')->flash();

            return redirect()->route('admin.plugins.settings', ['plugin' => $plugin->id])->withInput();
        }

        $this->pluginManager->updateConfig($plugin, $config);

        $this->alert->success('Plugin configuration has been updated.')->flash();

        return redirect()->route('admin.plugins.settings', ['plugin' => $plugin->id]);
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
