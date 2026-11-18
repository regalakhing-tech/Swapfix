<?php
declare(strict_types=1);

namespace SwapFix\Integrations\Supabase;

use SwapFix\Support\IntegrationException;

final class Auth
{
    public function __construct(private readonly SupabaseClient $client) {}

    public function user(string $accessToken): array
    {
        if ($accessToken === '') {
            throw new IntegrationException('Authentication required.', 401);
        }
        $user = $this->client->request('GET', '/auth/v1/user', [], $accessToken);
        if (!is_string($user['id'] ?? null) || !preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/i', $user['id'])) {
            throw new IntegrationException('Invalid authentication response.', 401);
        }
        return $user;
    }

    public function signUp(string $email, string $password, ?string $redirectTo = null): array
    {
        $this->validateCredentials($email, $password);
        $options = ['json' => ['email' => $email, 'password' => $password]];
        if ($redirectTo !== null) {
            $options['query'] = ['redirect_to' => $redirectTo];
        }
        return $this->client->request('POST', '/auth/v1/signup', $options);
    }

    public function signIn(string $email, string $password): array
    {
        $this->validateCredentials($email, $password);
        return $this->client->request('POST', '/auth/v1/token?grant_type=password', ['json' => ['email' => $email, 'password' => $password]]);
    }

    public function refresh(string $refreshToken): array
    {
        if ($refreshToken === '') {
            throw new \InvalidArgumentException('A refresh token is required.');
        }
        return $this->client->request('POST', '/auth/v1/token?grant_type=refresh_token', ['json' => ['refresh_token' => $refreshToken]]);
    }

    public function signOut(string $accessToken): void
    {
        $this->user($accessToken);
        $this->client->request('POST', '/auth/v1/logout', [], $accessToken);
    }

    private function validateCredentials(string $email, string $password): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            throw new \InvalidArgumentException('A valid email and password are required.');
        }
    }
}
