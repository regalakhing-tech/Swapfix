<?php
declare(strict_types=1);

namespace SwapFix\Support;

final class Uuid
{
    public static function valid(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{8}(-[a-f0-9]{4}){3}-[a-f0-9]{12}$/i', $value) === 1;
    }
}
