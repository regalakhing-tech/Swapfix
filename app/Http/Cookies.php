<?php
declare(strict_types=1);

namespace SwapFix\Http;

/** HttpOnly, SameSite=Lax cookies. HTTPS origins get the Secure flag and __Host- prefix. */
final class Cookies
{
    public function __construct(private readonly bool $secure) {}

    public function name(string $base): string
    {
        return ($this->secure ? '__Host-' : '') . 'swapfix_' . $base;
    }

    public function set(string $base, string $value, int $maxAge): string
    {
        return $this->name($base) . '=' . rawurlencode($value) . '; Path=/; Max-Age=' . $maxAge . '; HttpOnly; SameSite=Lax' . ($this->secure ? '; Secure' : '');
    }

    public function clear(string $base): string
    {
        return $this->set($base, '', 0);
    }
}
