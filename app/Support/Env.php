<?php
declare(strict_types=1);

namespace SwapFix\Support;

final class Env
{
    public static function get(string $name, string $default = ''): string
    {
        $process = getenv($name);
        return $process !== false ? $process : (string) ($_ENV[$name] ?? $_SERVER[$name] ?? $default);
    }
}
