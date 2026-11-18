<?php
declare(strict_types=1);

namespace SwapFix\Modules\Repair;

/** Decides which controls to show. The database independently enforces every rule. */
final class RepairRules
{
    private const NEXT = ['accepted' => 'in_repair', 'in_repair' => 'ready_for_pickup', 'ready_for_pickup' => 'completed'];

    public static function pending(array $records): ?array
    {
        foreach ($records as $record) {
            if ($record['status'] === 'pending') {
                return $record;
            }
        }
        return null;
    }

    public static function actions(array $repair, string $userId): array
    {
        $status = $repair['status'];
        $isCustomer = $repair['customer_id'] === $userId;
        $isTechnician = $repair['technician_id'] === $userId;
        $quotation = self::pending($repair['repair_quotations'] ?? []);
        $cost = self::pending($repair['repair_added_costs'] ?? []);
        $next = $isTechnician ? (self::NEXT[$status] ?? null) : null;
        return [
            'isCustomer' => $isCustomer,
            'isTechnician' => $isTechnician,
            'quotation' => $quotation,
            'addedCost' => $cost,
            'canQuote' => $isTechnician && in_array($status, ['request_sent', 'quotation_sent'], true),
            'canDecideQuotation' => $isCustomer && $quotation !== null && $status === 'quotation_sent',
            'canRequestAddedCost' => $isTechnician && in_array($status, ['accepted', 'in_repair'], true) && $cost === null,
            'canWithdrawAddedCost' => $isTechnician && $cost !== null,
            'canDecideAddedCost' => $isCustomer && $cost !== null,
            'nextStatus' => $next,
            'nextBlocked' => $next === 'ready_for_pickup' && $cost !== null,
            'canCancel' => ($isCustomer && in_array($status, ['request_sent', 'quotation_sent', 'accepted'], true))
                || ($isTechnician && in_array($status, ['request_sent', 'quotation_sent', 'accepted', 'in_repair'], true)),
            'canReview' => $isCustomer && $status === 'completed' && empty($repair['review']),
        ];
    }

    /** Dashboard groups from the README: each job appears in exactly one group. */
    public static function board(array $jobs): array
    {
        $board = ['new' => [], 'awaiting' => [], 'active' => [], 'completed' => [], 'cancelled' => []];
        foreach ($jobs as $job) {
            $pendingCost = self::pending($job['repair_added_costs'] ?? []) !== null;
            $group = match (true) {
                $job['status'] === 'cancelled' => 'cancelled',
                $job['status'] === 'completed' => 'completed',
                $job['status'] === 'request_sent' => 'new',
                $job['status'] === 'quotation_sent', $pendingCost => 'awaiting',
                default => 'active',
            };
            $board[$group][] = $job;
        }
        return $board;
    }
}
