<?php
declare(strict_types=1);

namespace SwapFix\Support;

/** Amounts stay decimal strings in PHP. The database does all money arithmetic. */
final class Money
{
    public static function parse(string $value): ?string
    {
        $clean = str_replace([',', ' '], '', trim($value));
        return preg_match('/^\d{1,10}(\.\d{1,2})?$/', $clean) === 1 ? $clean : null;
    }

    public static function format(mixed $value): string
    {
        return "\u{20B1}" . number_format((float) $value, 2);
    }
}
