<?php
declare(strict_types=1);

namespace SwapFix\Modules\Repair;

use SwapFix\Integrations\Supabase\Database;

final class RepairRepository
{
    private const PEOPLE = 'customer:profiles!repair_requests_customer_id_fkey(id,display_name,avatar_path),'
        . 'technician:technician_profiles!repair_requests_technician_id_fkey(user_id,location,service_area,profile:profiles(id,display_name,avatar_path))';

    public function __construct(private readonly Database $db) {}

    public function create(string $token, string $technicianId, ?string $serviceId, array $fields, array $photoPaths): array
    {
        return $this->db->rpc('sf_create_repair_request', [
            'p_technician_id' => $technicianId, 'p_service_id' => $serviceId, 'p_device_type' => $fields['device_type'],
            'p_device_brand' => $fields['device_brand'], 'p_device_model' => $fields['device_model'],
            'p_problem' => $fields['problem'], 'p_photo_paths' => $photoPaths,
        ], $token);
    }

    public function find(string $token, string $id): ?array
    {
        $repair = $this->db->first('repair_requests', [
            'select' => '*,' . self::PEOPLE . ',repair_quotations!repair_quotations_repair_id_fkey(*),repair_added_costs(*),repair_events(*),reviews(*),conversations(id,initiator_id,initiator_unread,recipient_unread)',
            'id' => 'eq.' . $id,
        ], $token);
        if ($repair === null) {
            return null;
        }
        usort($repair['repair_quotations'], static fn (array $a, array $b): int => $b['version'] <=> $a['version']);
        usort($repair['repair_added_costs'], static fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));
        usort($repair['repair_events'], static fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));
        $repair['review'] = self::single($repair['reviews'] ?? null);
        $repair['conversation'] = self::single($repair['conversations'] ?? null);
        return $repair;
    }

    public function forCustomer(string $token, string $userId, int $limit = 100): array
    {
        return $this->db->select('repair_requests', [
            'select' => '*,' . self::PEOPLE . ',repair_added_costs(id,status)', 'customer_id' => 'eq.' . $userId,
            'order' => 'updated_at.desc', 'limit' => $limit,
        ], $token);
    }

    public function forTechnician(string $token, string $userId, int $limit = 200): array
    {
        return $this->db->select('repair_requests', [
            'select' => '*,' . self::PEOPLE . ',repair_added_costs(id,status)', 'technician_id' => 'eq.' . $userId,
            'order' => 'updated_at.desc', 'limit' => $limit,
        ], $token);
    }

    public function forParticipant(string $token, string $userId, int $limit, int $offset): array
    {
        return $this->db->select('repair_requests', [
            'select' => 'id,technician_id,device_brand,device_model,status,updated_at,' . self::PEOPLE,
            'or' => "(customer_id.eq.$userId,technician_id.eq.$userId)",
            'order' => 'updated_at.desc,id.desc', 'limit' => $limit, 'offset' => $offset,
        ], $token);
    }

    public function sendQuotation(string $token, string $repairId, string $scope, string $parts, string $cost, string $date): array
    {
        return $this->db->rpc('sf_send_quotation', ['p_repair_id' => $repairId, 'p_scope' => $scope, 'p_parts' => $parts, 'p_cost' => $cost, 'p_estimated_completion' => $date], $token);
    }

    public function decideQuotation(string $token, string $quotationId, string $decision, string $note): array
    {
        return $this->db->rpc('sf_decide_quotation', ['p_quotation_id' => $quotationId, 'p_decision' => $decision, 'p_note' => $note], $token);
    }

    public function requestAddedCost(string $token, string $repairId, string $reason, string $work, string $amount, ?string $date): array
    {
        return $this->db->rpc('sf_request_added_cost', ['p_repair_id' => $repairId, 'p_reason' => $reason, 'p_proposed_work' => $work, 'p_amount' => $amount, 'p_revised_completion' => $date], $token);
    }

    public function withdrawAddedCost(string $token, string $id): array
    {
        return $this->db->rpc('sf_withdraw_added_cost', ['p_id' => $id], $token);
    }

    public function decideAddedCost(string $token, string $id, string $decision, string $note): array
    {
        return $this->db->rpc('sf_decide_added_cost', ['p_id' => $id, 'p_decision' => $decision, 'p_note' => $note], $token);
    }

    public function updateStatus(string $token, string $repairId, string $expected, string $status, string $note): array
    {
        return $this->db->rpc('sf_update_repair_status', ['p_repair_id' => $repairId, 'p_expected_status' => $expected, 'p_status' => $status, 'p_note' => $note], $token);
    }

    public function cancel(string $token, string $repairId, string $reason): array
    {
        return $this->db->rpc('sf_cancel_repair', ['p_repair_id' => $repairId, 'p_reason' => $reason], $token);
    }

    public function review(string $token, string $repairId, int $rating, string $comment): array
    {
        return $this->db->rpc('sf_create_review', ['p_repair_id' => $repairId, 'p_rating' => $rating, 'p_comment' => $comment], $token);
    }

    /** PostgREST returns one-to-one embeds as an object and others as a list. */
    private static function single(mixed $embedded): ?array
    {
        if (!is_array($embedded) || $embedded === []) {
            return null;
        }
        return array_is_list($embedded) ? $embedded[0] : $embedded;
    }
}
