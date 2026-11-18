<?php
declare(strict_types=1);

namespace SwapFix\Integrations\Supabase;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use SwapFix\Support\IntegrationException;

final class SupabaseClient
{
    private readonly ClientInterface $http;
    private readonly string $url;

    public function __construct(string $url, private readonly string $publishableKey, ?ClientInterface $http = null)
    {
        if ($url === '' || $publishableKey === '') {
            throw new \RuntimeException('Supabase is not configured.');
        }
        $parts = parse_url($url);
        $local = in_array($parts['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true);
        if (!$parts || !isset($parts['host']) || (!in_array($parts['scheme'] ?? '', ['https'], true) && !(($parts['scheme'] ?? '') === 'http' && $local))
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || !in_array($parts['path'] ?? '', ['', '/'], true)) {
            throw new \InvalidArgumentException('A valid HTTPS Supabase project URL is required.');
        }
        if (!str_starts_with($publishableKey, 'sb_publishable_')) {
            throw new \InvalidArgumentException('Use a Supabase publishable key for user-scoped requests.');
        }
        $this->url = rtrim($url, '/');
        $this->http = $http ?? new Client();
    }

    public function request(string $method, string $path, array $options = [], ?string $accessToken = null): array
    {
        if (!preg_match('~^/(auth|rest|storage)/v1/~', $path) || str_contains($path, '..') || str_contains($path, '#')) {
            throw new \InvalidArgumentException('Unsupported Supabase API path.');
        }
        $headers = $options['headers'] ?? [];
        unset($headers['Authorization'], $headers['authorization']);
        $headers['apikey'] = $this->publishableKey;
        $headers['Accept'] = 'application/json';
        if ($accessToken !== null && $accessToken !== '') {
            $headers['Authorization'] = 'Bearer ' . $accessToken;
        }
        $options = array_replace($options, ['headers' => $headers, 'http_errors' => false, 'allow_redirects' => false, 'timeout' => 20, 'connect_timeout' => 5]);
        try {
            $response = $this->http->request($method, $this->url . $path, $options);
        } catch (GuzzleException) {
            throw new IntegrationException('Supabase is temporarily unreachable.');
        }
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new IntegrationException('Supabase request failed.', $status);
        }
        $body = (string) $response->getBody();
        if ($body === '') {
            return [];
        }
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new IntegrationException('Supabase returned an invalid response.');
        }
        if (!is_array($decoded)) {
            throw new IntegrationException('Supabase returned an invalid response.');
        }
        return $decoded;
    }
}
