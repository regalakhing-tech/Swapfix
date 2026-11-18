<?php
declare(strict_types=1);

namespace SwapFix\Application;

/** A live feature was opened before its environment settings exist. */
final class SetupRequired extends \RuntimeException
{
    public function __construct(public readonly array $settings)
    {
        parent::__construct('SwapFix is not connected yet.');
    }
}
