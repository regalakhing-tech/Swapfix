<?php
declare(strict_types=1);

namespace SwapFix\Modules\Activity;

use SwapFix\Integrations\Supabase\Database;

final class ActivityRepository
{
    public function __construct(private readonly Database $db) {}

    public function listings(string $token, string $userId, int $limit, int $offset): array
    {
        return $this->db->select('product_listings', [
            'select' => 'id,title,price,status,location,photo_paths,updated_at', 'owner_id' => 'eq.' . $userId,
            'order' => 'updated_at.desc,id.desc', 'limit' => $limit, 'offset' => $offset,
        ], $token);
    }

    public function exchanges(string $token, string $userId, int $limit, int $offset): array
    {
        return $this->db->select('exchange_listings', [
            'select' => 'id,title,wanted_item,status,location,photo_paths,updated_at', 'owner_id' => 'eq.' . $userId,
            'order' => 'updated_at.desc,id.desc', 'limit' => $limit, 'offset' => $offset,
        ], $token);
    }

    /** Sent and received offers; RLS already limits rows to the user's own negotiations. */
    public function offers(string $token, int $limit, int $offset): array
    {
        return $this->db->select('trade_offers', [
            'select' => 'id,status,offerer_id,owner_id,awaiting_user_id,current_version,updated_at,exchange:exchange_listings!trade_offers_exchange_id_fkey(id,title,status)',
            'order' => 'updated_at.desc,id.desc', 'limit' => $limit, 'offset' => $offset,
        ], $token);
    }

    public function reviews(string $token, string $userId, int $limit, int $offset): array
    {
        return $this->db->select('reviews', [
            'select' => 'id,repair_id,rating,comment,created_at,technician:technician_profiles(user_id,profile:profiles(display_name))',
            'customer_id' => 'eq.' . $userId, 'order' => 'created_at.desc,id.desc', 'limit' => $limit, 'offset' => $offset,
        ], $token);
    }
}
