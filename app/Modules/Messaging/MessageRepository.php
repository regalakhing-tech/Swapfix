<?php
declare(strict_types=1);

namespace SwapFix\Modules\Messaging;

use SwapFix\Integrations\Supabase\Database;

final class MessageRepository
{
    private const PEOPLE = 'initiator:profiles!conversations_initiator_id_fkey(id,display_name,avatar_path),recipient:profiles!conversations_recipient_id_fkey(id,display_name,avatar_path)';

    public function __construct(private readonly Database $db) {}

    public function conversations(string $token, int $limit = 100, int $offset = 0): array
    {
        return $this->db->select('conversations', [
            'select' => '*,' . self::PEOPLE, 'order' => 'last_message_at.desc.nullslast,created_at.desc,id.desc',
            'limit' => $limit, 'offset' => $offset,
        ], $token);
    }

    public function conversation(string $token, string $id): ?array
    {
        return $this->db->first('conversations', ['select' => '*,' . self::PEOPLE, 'id' => 'eq.' . $id], $token);
    }

    /** Latest messages, oldest first for display. */
    public function recent(string $token, string $conversationId, int $limit = 100): array
    {
        return array_reverse($this->db->select('messages', [
            'select' => 'id,sender_id,body,created_at', 'conversation_id' => 'eq.' . $conversationId, 'order' => 'created_at.desc', 'limit' => $limit,
        ], $token));
    }

    public function send(string $token, string $conversationId, string $body): array
    {
        return $this->db->rpc('sf_send_message', ['p_conversation_id' => $conversationId, 'p_body' => $body], $token);
    }

    public function markRead(string $token, string $conversationId): void
    {
        $this->db->rpc('sf_mark_conversation_read', ['p_conversation_id' => $conversationId], $token);
    }

    public static function contextUrl(array $conversation): string
    {
        return match ($conversation['context_type']) {
            'listing' => '/market/' . $conversation['listing_id'],
            'offer' => '/offers/' . $conversation['offer_id'],
            default => '/repairs/' . $conversation['repair_id'],
        };
    }
}
