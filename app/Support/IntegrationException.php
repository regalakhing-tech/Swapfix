<?php
declare(strict_types=1);

namespace SwapFix\Support;

final class IntegrationException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $upstreamStatus = 503, public readonly ?string $errorCode = null)
    {
        parent::__construct($message);
    }
}
