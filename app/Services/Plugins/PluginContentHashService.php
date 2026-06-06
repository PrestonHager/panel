<?php

namespace Pterodactyl\Services\Plugins;

class PluginContentHashService
{
    /** @var string[] */
    private const EXCLUDED_NAMES = [
        '.git',
        '.github',
        'node_modules',
        '.tmp',
    ];

    public function hashDirectory(string $directory): string
    {
        if (!is_dir($directory)) {
            return '';
        }

        $files = $this->collectFiles($directory, $directory);
        sort($files);

        $parts = [];
        foreach ($files as $relativePath) {
            $fullPath = $directory . DIRECTORY_SEPARATOR . $relativePath;
            if (!is_file($fullPath)) {
                continue;
            }

            $parts[] = str_replace('\\', '/', $relativePath) . "\0" . hash_file('sha256', $fullPath);
        }

        return hash('sha256', implode("\n", $parts));
    }

    /**
     * @return string[]
     */
    private function collectFiles(string $directory, string $root): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relative = ltrim(str_replace($root, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $segments = explode(DIRECTORY_SEPARATOR, $relative);
            if ($this->shouldExclude($segments)) {
                continue;
            }

            $files[] = $relative;
        }

        return $files;
    }

    /**
     * @param string[] $segments
     */
    private function shouldExclude(array $segments): bool
    {
        if ($segments === []) {
            return true;
        }

        return in_array($segments[0], self::EXCLUDED_NAMES, true);
    }
}
