<?php

namespace Pterodactyl\Plugins;

use Pterodactyl\Plugins\Exceptions\PluginException;

final class DesignTokenRegistry
{
    private const TOKENS_PATH = 'resources/design/tokens.json';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return string[]
     */
    public function whitelist(): array
    {
        return $this->load()['whitelist'] ?? [];
    }

    /**
     * @return array<string, string>
     */
    public function baseTokensForSurface(string $surface): array
    {
        $surfaces = $this->load()['surfaces'] ?? [];

        if (!isset($surfaces[$surface]) || !is_array($surfaces[$surface])) {
            return [];
        }

        $result = [];
        foreach ($surfaces[$surface] as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function isAllowedKey(string $key): bool
    {
        return in_array($key, $this->whitelist(), true);
    }

    public function tokenToCssVariable(string $key): string
    {
        return '--pt-' . str_replace('.', '-', $key);
    }

    /**
     * @param array<string, string> $tokens
     * @return array<string, string>
     */
    public function validateTokenMap(array $tokens): array
    {
        $validated = [];

        foreach ($tokens as $key => $value) {
            if (!is_string($key) || !is_string($value) || trim($value) === '') {
                throw new PluginException(sprintf('Invalid theme token entry for key "%s".', (string) $key));
            }

            if (!$this->isAllowedKey($key)) {
                throw new PluginException(sprintf('Theme token "%s" is not in the design token whitelist.', $key));
            }

            $validated[$key] = trim($value);
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        if (!is_null(self::$cache)) {
            return self::$cache;
        }

        $path = base_path(self::TOKENS_PATH);
        if (!is_file($path)) {
            self::$cache = ['whitelist' => [], 'surfaces' => []];

            return self::$cache;
        }

        $data = json_decode((string) file_get_contents($path), true);
        self::$cache = is_array($data) ? $data : ['whitelist' => [], 'surfaces' => []];

        return self::$cache;
    }
}
