<?php

namespace Pterodactyl\Services\Plugins;

use Pterodactyl\Models\Plugin;
use Pterodactyl\Plugins\Permissions;

class BlockRegistry
{
    public function __construct(
        private readonly ManifestValidator $manifestValidator,
    ) {
    }

    /**
     * @return array{blocks: array<int, array<string, mixed>>, keybinds: array<int, array<string, mixed>>}
     */
    public function catalog(): array
    {
        $blocks = [];
        $keybinds = [];

        foreach (Plugin::query()->where('enabled', true)->orderBy('name')->get() as $plugin) {
            if (!in_array(Permissions::UI_BLOCKS_REGISTER, $plugin->effectivePermissions(), true)) {
                continue;
            }

            $manifest = $this->readManifest($plugin);
            if (is_null($manifest)) {
                continue;
            }

            foreach ($manifest->uiBlocks() as $block) {
                $blocks[] = array_merge($block, [
                    'pluginId' => $plugin->id,
                    'pluginName' => $plugin->name,
                ]);
            }

            foreach ($manifest->uiKeybinds() as $keybind) {
                $keybinds[] = array_merge($keybind, [
                    'pluginId' => $plugin->id,
                    'pluginName' => $plugin->name,
                ]);
            }
        }

        return [
            'blocks' => $blocks,
            'keybinds' => $keybinds,
        ];
    }

    private function readManifest(Plugin $plugin): ?\Pterodactyl\Plugins\PluginManifest
    {
        $directory = PluginRegistry::directoryFor($plugin->id);
        if (!is_dir($directory)) {
            return null;
        }

        try {
            return $this->manifestValidator->readFromDirectory($directory);
        } catch (\Throwable) {
            return null;
        }
    }
}
