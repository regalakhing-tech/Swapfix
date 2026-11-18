<?php
declare(strict_types=1);

namespace SwapFix\Modules\Marketplace;

use SwapFix\Integrations\Supabase\Database;

final class ListingRepository
{
    private const COLUMNS = 'id,owner_id,title,description,category,brand,model,condition,known_defects,price,location,photo_paths,status,status_changed_at,created_at,updated_at';
    private const OWNER = 'owner:profiles!product_listings_owner_id_fkey(id,display_name,avatar_path,account_type)';
    public const PAGE_SIZE = 24;

    public function __construct(private readonly Database $db) {}

    public function search(array $filters, int $page, ?string $token): array
    {
        return $this->db->rpc('sf_search_listings', [
            'p_query' => $filters['q'] ?: null, 'p_category' => $filters['category'] ?: null, 'p_brand' => $filters['brand'] ?: null,
            'p_condition' => $filters['condition'] ?: null, 'p_min_price' => $filters['min'], 'p_max_price' => $filters['max'],
            'p_location' => $filters['location'] ?: null, 'p_sort' => $filters['sort'], 'p_limit' => self::PAGE_SIZE,
            'p_offset' => ($page - 1) * self::PAGE_SIZE,
        ], $token);
    }

    public function find(string $id, ?string $token): ?array
    {
        return $this->db->first('product_listings', ['select' => self::COLUMNS . ',' . self::OWNER, 'id' => 'eq.' . $id], $token);
    }

    public function save(string $token, ?string $id, array $fields, array $photoPaths): array
    {
        return $this->db->rpc('sf_save_listing', [
            'p_id' => $id, 'p_title' => $fields['title'], 'p_description' => $fields['description'], 'p_category' => $fields['category'],
            'p_brand' => $fields['brand'], 'p_model' => $fields['model'], 'p_condition' => $fields['condition'],
            'p_known_defects' => $fields['known_defects'], 'p_price' => $fields['price'], 'p_location' => $fields['location'],
            'p_photo_paths' => $photoPaths,
        ], $token);
    }

    public function setStatus(string $token, string $id, string $status, string $expected): array
    {
        return $this->db->rpc('sf_set_listing_status', ['p_id' => $id, 'p_status' => $status, 'p_expected_status' => $expected], $token);
    }

    public function inquire(string $token, string $id, string $message): array
    {
        return $this->db->rpc('sf_start_listing_inquiry', ['p_listing_id' => $id, 'p_message' => $message], $token);
    }

    public function inquiries(string $token, string $id): array
    {
        return $this->db->select('conversations', [
            'select' => 'id,last_message_preview,last_message_at,initiator:profiles!conversations_initiator_id_fkey(id,display_name,avatar_path)',
            'listing_id' => 'eq.' . $id, 'order' => 'last_message_at.desc.nullslast', 'limit' => 50,
        ], $token);
    }
}
