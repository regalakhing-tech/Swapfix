<?php
declare(strict_types=1);

namespace SwapFix\Modules\Exchange;

/** What the viewer may do with an offer. The database enforces the same rules. */
final class OfferActions
{
    public static function for(array $offer, array $exchange, string $userId): array
    {
        $pending = $offer['status'] === 'pending';
        $open = $exchange['status'] === 'open';
        $myTurn = $pending && $open && ($offer['awaiting_user_id'] ?? null) === $userId;
        $isOwner = $offer['owner_id'] === $userId;
        $agreed = $offer['status'] === 'accepted' && $exchange['status'] === 'agreed';
        return [
            'respond' => $myTurn,
            'withdraw' => $pending && $offer['offerer_id'] === $userId,
            'complete' => $agreed && $isOwner,
            'cancelAgreement' => $agreed,
            'onHold' => $pending && !$open,
            'waitingForOther' => $pending && $open && !$myTurn,
        ];
    }
}
