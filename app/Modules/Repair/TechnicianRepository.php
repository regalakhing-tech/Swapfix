<?php
declare(strict_types=1);

namespace SwapFix\Modules\Repair;

use SwapFix\Integrations\Supabase\Database;

final class TechnicianRepository
{
    public const PAGE_SIZE = 24;

    public function __construct(private readonly Database $db) {}

    public function search(array $filters, int $page, ?string $token): array
    {
        return $this->db->rpc('sf_search_technicians', [
            'p_query' => $filters['q'] ?: null, 'p_area' => $filters['area'] ?: null, 'p_specialty' => $filters['specialty'] ?: null,
            'p_brand' => $filters['brand'] ?: null, 'p_limit' => self::PAGE_SIZE, 'p_offset' => ($page - 1) * self::PAGE_SIZE,
        ], $token);
    }

    public function find(string $id, ?string $token): ?array
    {
        return $this->db->first('technician_profiles', [
            'select' => '*,profile:profiles(id,display_name,avatar_path,account_type)', 'user_id' => 'eq.' . $id,
        ], $token);
    }

    public function activeServices(string $id, ?string $token): array
    {
        return $this->db->select('repair_services', [
            'select' => '*', 'technician_id' => 'eq.' . $id, 'is_active' => 'is.true', 'order' => 'created_at.desc', 'limit' => 50,
        ], $token);
    }

    public function ownServices(string $token, string $id): array
    {
        return $this->db->select('repair_services', ['select' => '*', 'technician_id' => 'eq.' . $id, 'order' => 'is_active.desc,created_at.desc', 'limit' => 100], $token);
    }

    public function reviews(string $id, ?string $token, int $limit = 20): array
    {
        return $this->db->rpc('sf_public_reviews', ['p_technician_id' => $id, 'p_limit' => $limit, 'p_offset' => 0], $token);
    }

    public function saveProfile(string $token, array $fields): array
    {
        return $this->db->rpc('sf_save_technician_profile', [
            'p_headline' => $fields['headline'], 'p_bio' => $fields['bio'], 'p_location' => $fields['location'],
            'p_service_area' => $fields['service_area'], 'p_available_hours' => $fields['available_hours'],
            'p_specialties' => $fields['specialties'], 'p_supported_brands' => $fields['supported_brands'], 'p_supported_models' => $fields['supported_models'],
        ], $token);
    }

    public function saveService(string $token, ?string $id, array $fields): array
    {
        return $this->db->rpc('sf_save_repair_service', [
            'p_id' => $id, 'p_name' => $fields['name'], 'p_description' => $fields['description'],
            'p_supported_brands' => $fields['supported_brands'], 'p_supported_models' => $fields['supported_models'],
            'p_estimated_price' => $fields['estimated_price'],
        ], $token);
    }

    public function setServiceActive(string $token, string $id, bool $active): array
    {
        return $this->db->rpc('sf_set_service_active', ['p_id' => $id, 'p_active' => $active], $token);
    }
}
