<?php
declare(strict_types=1);

namespace SwapFix\Http;

/** One-request messages carried across a redirect in an encrypted cookie. */
final class Flash
{
    private array $outgoing = [];

    public function __construct(private readonly array $incoming = []) {}

    public function success(string $message): void
    {
        $this->outgoing[] = ['success', mb_substr($message, 0, 200)];
    }

    public function error(string $message): void
    {
        $this->outgoing[] = ['error', mb_substr($message, 0, 200)];
    }

    public function incoming(): array
    {
        return $this->incoming;
    }

    public function outgoing(): array
    {
        return array_slice($this->outgoing, 0, 3);
    }
}
