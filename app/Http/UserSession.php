<?php
declare(strict_types=1);

namespace SwapFix\Http;

/** The verified signed-in user for one request. Tokens never reach templates. */
final class UserSession
{
    public function __construct(
        public readonly string $id,
        public readonly string $accessToken,
        public readonly string $email,
        public readonly ?array $profile,
        public readonly int $unreadNotifications = 0,
        public readonly int $unreadMessages = 0,
    ) {}

    public function isTechnician(): bool
    {
        return ($this->profile['account_type'] ?? null) === 'technician';
    }

    public function name(): string
    {
        return (string) ($this->profile['display_name'] ?? 'Account');
    }
}
