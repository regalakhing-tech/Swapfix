<?php
declare(strict_types=1);

namespace SwapFix\Modules\Accounts;

use SwapFix\Integrations\Supabase\Database;

final class AccountRepository
{
    public function __construct(private readonly Database $db) {}

    public function createProfile(string $token, string $accountType, string $name, string $location): array
    {
        return $this->db->rpc('sf_create_profile', ['p_account_type' => $accountType, 'p_display_name' => $name, 'p_location' => $location], $token);
    }

    public function updateProfile(string $token, string $name, string $location, ?string $avatarPath, bool $clearAvatar, bool $emailNotifications): array
    {
        return $this->db->rpc('sf_update_profile', [
            'p_display_name' => $name, 'p_location' => $location, 'p_avatar_path' => $avatarPath,
            'p_clear_avatar' => $clearAvatar, 'p_email_notifications' => $emailNotifications,
        ], $token);
    }
}
