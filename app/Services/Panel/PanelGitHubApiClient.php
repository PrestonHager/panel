<?php

namespace Pterodactyl\Services\Panel;

use Symfony\Component\Process\Process;

class PanelGitHubApiClient
{
    /**
     * @return array{status: int, data: ?array, error: ?string}
     */
    public function get(string $path): array
    {
        $url = 'https://api.github.com' . ($path[0] === '/' ? $path : '/' . $path);
        $command = [
            'curl',
            '-sSL',
            '-H',
            'Accept: application/vnd.github+json',
            '-H',
            'User-Agent: Pterodactyl-Panel-Upstream',
            '-w',
            '\n%{http_code}',
            $url,
        ];

        $token = config('pterodactyl.update.github_token');
        if (is_string($token) && $token !== '') {
            array_splice($command, -1, 0, ['-H', 'Authorization: Bearer ' . $token]);
        }

        $process = new Process($command);
        $process->setTimeout(30);
        $process->run();

        if (!$process->isSuccessful() && trim($process->getOutput()) === '') {
            return [
                'status' => 0,
                'data' => null,
                'error' => trim($process->getErrorOutput()) ?: 'GitHub API request failed.',
            ];
        }

        $output = $process->getOutput();
        $status = (int) substr($output, strrpos($output, "\n") + 1);
        $body = substr($output, 0, strrpos($output, "\n"));
        $data = json_decode($body, true);

        if ($status >= 200 && $status < 300 && is_array($data)) {
            return [
                'status' => $status,
                'data' => $data,
                'error' => null,
            ];
        }

        $message = is_array($data) && isset($data['message']) ? (string) $data['message'] : null;

        return [
            'status' => $status,
            'data' => is_array($data) ? $data : null,
            'error' => $message ?: 'GitHub API returned HTTP ' . $status . '.',
        ];
    }
}
