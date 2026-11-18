<?php
declare(strict_types=1);

namespace SwapFix\Modules\Notifications;

use SwapFix\Integrations\Supabase\Database;

final class NotificationRepository
{
    public const PAGE_SIZE = 100;

    public function __construct(private readonly Database $db) {}

    public function recent(string $token, int $limit = self::PAGE_SIZE, int $offset = 0): array
    {
        return $this->db->select('notifications', [
            'select' => 'id,kind,title,body,link_path,read_at,created_at',
            'order' => 'created_at.desc,id.desc', 'limit' => $limit, 'offset' => $offset,
        ], $token);
    }

    public function markRead(string $token, string $id): array
    {
        return $this->db->rpc('sf_mark_notification_read', ['p_id' => $id], $token);
    }

    public function markAllRead(string $token): array
    {
        return $this->db->rpc('sf_mark_all_notifications_read', [], $token);
    }
}
