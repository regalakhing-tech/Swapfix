<?php
declare(strict_types=1);

namespace SwapFix\Http;

use SwapFix\Support\Crypto;

/** Signed double-submit tokens bound to a cookie seed and the signed-in user. */
final class CsrfTokens
{
    private ?string $newSeed = null;

    public function __construct(private readonly Crypto $crypto, private readonly ?string $seed, private readonly ?string $userId) {}

    public function token(): string
    {
        $seed = $this->seed ?? ($this->newSeed ??= bin2hex(random_bytes(32)));
        return $this->sign($seed);
    }

    public function valid(mixed $token): bool
    {
        return is_string($token) && $this->seed !== null && $this->seed !== '' && hash_equals($this->sign($this->seed), $token);
    }

    public function newSeed(): ?string
    {
        return $this->newSeed;
    }

    private function sign(string $seed): string
    {
        return $this->crypto->sign('csrf|' . $seed . '|' . ($this->userId ?? ''));
    }
}
