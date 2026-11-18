<?php
declare(strict_types=1);

namespace SwapFix\Support;

/** A business-rule failure whose message is safe to show to the user. */
final class DomainError extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
