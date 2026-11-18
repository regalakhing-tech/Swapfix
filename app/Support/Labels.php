<?php
declare(strict_types=1);

namespace SwapFix\Support;

/** Display names and status tones for values stored in the database. */
final class Labels
{
    public const CATEGORIES = ['cellphone' => 'Cellphones', 'tablet' => 'Tablets', 'laptop' => 'Laptops', 'accessory' => 'Accessories', 'spare_part' => 'Spare parts'];
    public const CATEGORY_ICONS = ['cellphone' => 'phone', 'tablet' => 'tablet', 'laptop' => 'laptop', 'accessory' => 'headphones', 'spare_part' => 'chip'];
    public const CONDITIONS = ['new' => 'New', 'like_new' => 'Like new', 'good' => 'Good', 'fair' => 'Fair', 'for_parts' => 'For parts'];
    public const DEVICE_TYPES = ['cellphone' => 'Cellphone', 'tablet' => 'Tablet', 'laptop' => 'Laptop', 'other' => 'Other device'];
    public const CASH_PREFERENCES = ['none' => 'No cash', 'owner_may_add' => 'Owner may add cash', 'offerer_may_add' => 'Offerer may add cash', 'either' => 'Either side may add cash'];
    public const REPAIR_FLOW = ['request_sent', 'quotation_sent', 'accepted', 'in_repair', 'ready_for_pickup', 'completed'];

    private const STATUSES = [
        'available' => ['Available', 'green'], 'reserved' => ['Reserved', 'amber'], 'sold' => ['Sold', 'muted'], 'removed' => ['Removed', 'red'],
        'open' => ['Open', 'green'], 'agreed' => ['Agreed', 'violet'],
        'pending' => ['Pending', 'amber'], 'accepted' => ['Accepted', 'green'], 'declined' => ['Declined', 'red'],
        'withdrawn' => ['Withdrawn', 'muted'], 'cancelled' => ['Cancelled', 'red'], 'completed' => ['Completed', 'green'],
        'superseded' => ['Replaced', 'muted'],
        'request_sent' => ['Request Sent', 'blue'], 'quotation_sent' => ['Quotation Sent', 'amber'],
        'in_repair' => ['In Repair', 'violet'], 'ready_for_pickup' => ['Ready for Pickup', 'teal'],
    ];

    public static function status(string $status): string
    {
        return self::STATUSES[$status][0] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function tone(string $status): string
    {
        return self::STATUSES[$status][1] ?? 'muted';
    }

    public static function cashTerms(mixed $amount, string $payer, bool $viewerIsOwner): string
    {
        if ($payer === 'none' || (float) $amount === 0.0) {
            return 'No cash';
        }
        $who = $payer === 'owner' ? ($viewerIsOwner ? 'You pay' : 'Owner pays') : ($viewerIsOwner ? 'Offerer pays' : 'You pay');
        return $who . ' ' . Money::format($amount);
    }
}
