<?php

namespace Pterodactyl\Plugins\Accessors;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Pterodactyl\Plugins\PermissionGate;
use Pterodactyl\Plugins\Permissions;
use Pterodactyl\Plugins\Exceptions\PluginException;

class HttpClientAccessor
{
    private ?ClientInterface $client = null;

    public function __construct(
        private readonly PermissionGate $gate,
        private readonly array $allowedHosts,
        private readonly int $timeout,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     * @return array{status: int, body: string, headers: array<string, string[]>}
     */
    public function request(string $method, string $url, array $options = []): array
    {
        $this->gate->authorize(Permissions::HTTP_REQUEST);
        $this->assertAllowedUrl($url);

        $options['timeout'] = $options['timeout'] ?? $this->timeout;
        $options['http_errors'] = false;

        $response = $this->client()->request($method, $url, $options);

        return [
            'status' => $response->getStatusCode(),
            'body' => (string) $response->getBody(),
            'headers' => $response->getHeaders(),
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array{status: int, body: string, headers: array<string, string[]>}
     */
    public function get(string $url, array $options = []): array
    {
        return $this->request('GET', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     * @return array{status: int, body: string, headers: array<string, string[]>}
     */
    public function post(string $url, array $options = []): array
    {
        return $this->request('POST', $url, $options);
    }

    private function client(): ClientInterface
    {
        return $this->client ??= new Client();
    }

    private function assertAllowedUrl(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            throw new PluginException('Invalid URL provided for HTTP request.');
        }

        $host = strtolower($host);
        foreach ($this->allowedHosts as $allowed) {
            $allowed = strtolower($allowed);
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return;
            }
        }

        throw new PluginException(sprintf(
            'HTTP host "%s" is not in the plugin HTTP allowlist.',
            $host
        ));
    }
}
