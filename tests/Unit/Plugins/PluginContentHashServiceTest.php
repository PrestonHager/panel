<?php

namespace Pterodactyl\Tests\Unit\Plugins;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Services\Plugins\PluginContentHashService;

class PluginContentHashServiceTest extends TestCase
{
    public function testHashesDirectoryDeterministically(): void
    {
        $directory = sys_get_temp_dir() . '/plugin-hash-' . uniqid();
        mkdir($directory);
        file_put_contents($directory . '/plugin.json', '{"id":"com.example.test"}');
        mkdir($directory . '/src');
        file_put_contents($directory . '/src/Plugin.php', '<?php');

        $service = new PluginContentHashService();
        $first = $service->hashDirectory($directory);
        $second = $service->hashDirectory($directory);

        $this->assertSame($first, $second);
        $this->assertSame(64, strlen($first));

        $this->removeDirectory($directory);
    }

    public function testIgnoresGitDirectory(): void
    {
        $directory = sys_get_temp_dir() . '/plugin-hash-' . uniqid();
        mkdir($directory);
        file_put_contents($directory . '/plugin.json', '{"id":"com.example.test"}');
        mkdir($directory . '/.git');
        file_put_contents($directory . '/.git/HEAD', 'ref: refs/heads/main');

        $service = new PluginContentHashService();
        $withoutGit = $service->hashDirectory($directory);

        unlink($directory . '/.git/HEAD');
        rmdir($directory . '/.git');
        $same = $service->hashDirectory($directory);

        $this->assertSame($withoutGit, $same);

        unlink($directory . '/plugin.json');
        rmdir($directory);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
