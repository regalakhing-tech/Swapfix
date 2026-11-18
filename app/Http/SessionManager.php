<?php
declare(strict_types=1);

namespace SwapFix\Http;

use SwapFix\Integrations\Supabase\Auth;
use SwapFix\Integrations\Supabase\Database;
use SwapFix\Support\Crypto;
use SwapFix\Support\IntegrationException;

/** Stateless sessions: Supabase tokens live only in an encrypted, HttpOnly cookie. */
final class SessionManager
{
    public const COOKIE = 'session';
    private const PURPOSE = 'session';
    private const MAX_AGE = 2592000;
    private const REFRESH_MARGIN = 60;
    private const MAX_COOKIE_BYTES = 3900;

    public function __construct(
        private readonly Crypto $crypto,
        private readonly Cookies $cookies,
        private readonly Auth $auth,
        private readonly Database $db,
    ) {}

    /**
     * Restores the session and refreshes expiring tokens.
     * @return array{0: ?UserSession, 1: ?string} The session and a Set-Cookie value when it changed.
     */
    public function restore(?string $cookie): array
    {
        $state = is_string($cookie) && $cookie !== '' ? $this->crypto->decrypt($cookie, self::PURPOSE) : null;
        if (!is_array($state) || !is_string($state['a'] ?? null) || !is_string($state['r'] ?? null) || !is_int($state['e'] ?? null)) {
            return [null, $cookie ? $this->cookies->clear(self::COOKIE) : null];
        }
        $setCookie = null;
        if ($state['e'] - self::REFRESH_MARGIN <= time()) {
            $fresh = $this->refresh($state);
            if ($fresh === null) {
                return [null, $this->cookies->clear(self::COOKIE)];
            }
            [$state, $setCookie] = $fresh;
        }
        try {
            return [$this->load($state), $setCookie];
        } catch (IntegrationException $exception) {
            if ($exception->upstreamStatus !== 401) {
                throw $exception;
            }
        }
        // The token was rejected early, for example after a project key rotation.
        $fresh = $this->refresh($state);
        if ($fresh === null) {
            return [null, $this->cookies->clear(self::COOKIE)];
        }
        [$state, $setCookie] = $fresh;
        return [$this->load($state), $setCookie];
    }

    /** Builds the Set-Cookie value from a Supabase token response. */
    public function issue(array $tokens): string
    {
        return $this->encode($this->stateFrom($tokens, null));
    }

    public function clear(): string
    {
        return $this->cookies->clear(self::COOKIE);
    }

    public function sessionFor(array $tokens): UserSession
    {
        return $this->load($this->stateFrom($tokens, null));
    }

    private function refresh(array $state): ?array
    {
        try {
            $tokens = $this->auth->refresh($state['r']);
        } catch (IntegrationException $exception) {
            if ($exception->upstreamStatus >= 400 && $exception->upstreamStatus < 500) {
                return null;
            }
            throw $exception;
        }
        $next = $this->stateFrom($tokens, $state['m'] ?? '');
        return [$next, $this->encode($next)];
    }

    private function load(array $state): UserSession
    {
        $data = $this->db->rpc('sf_session', [], $state['a']);
        if (!is_string($data['user_id'] ?? null)) {
            throw new IntegrationException('Invalid session response.', 401);
        }
        return new UserSession(
            $data['user_id'], $state['a'], (string) ($state['m'] ?? ''),
            is_array($data['profile'] ?? null) ? $data['profile'] : null,
            (int) ($data['unread_notifications'] ?? 0), (int) ($data['unread_messages'] ?? 0),
        );
    }

    private function stateFrom(array $tokens, ?string $email): array
    {
        if (!is_string($tokens['access_token'] ?? null) || !is_string($tokens['refresh_token'] ?? null)) {
            throw new IntegrationException('Invalid authentication response.', 401);
        }
        return [
            'a' => $tokens['access_token'],
            'r' => $tokens['refresh_token'],
            'e' => is_int($tokens['expires_at'] ?? null) ? $tokens['expires_at'] : time() + (int) ($tokens['expires_in'] ?? 3600),
            'm' => is_string($tokens['user']['email'] ?? null) ? $tokens['user']['email'] : (string) $email,
        ];
    }

    private function encode(array $state): string
    {
        $value = $this->crypto->encrypt($state, self::PURPOSE);
        // shortcut: one cookie holds the session; split into chunks if tokens ever outgrow it.
        if (strlen($value) > self::MAX_COOKIE_BYTES) {
            throw new IntegrationException('The session is too large to store.', 500);
        }
        return $this->cookies->set(self::COOKIE, $value, self::MAX_AGE);
    }
}
