<?php
declare(strict_types=1);

namespace SwapFix\Modules\Exchange;

use SwapFix\Http\Input;
use SwapFix\Support\DomainError;
use SwapFix\Support\Money;

/** Shared parsing for new offers and counteroffers. */
final class OfferForm
{
    public const BLANK = ['device_title' => '', 'brand' => '', 'model' => '', 'condition' => 'good', 'known_defects' => '', 'message' => '', 'cash_amount' => '', 'cash_payer' => 'none'];

    public static function terms(Input $input): array
    {
        $amount = $input->text('cash_amount', 20);
        return [
            'device_title' => $input->text('device_title', 150), 'brand' => $input->text('brand', 60), 'model' => $input->text('model', 80),
            'condition' => $input->text('condition', 20), 'known_defects' => $input->text('known_defects', 2000), 'message' => $input->text('message', 2000),
            'cash_amount' => $amount === '' ? '0' : (Money::parse($amount) ?? $amount),
            'cash_payer' => $input->choice('cash_payer', ['none', 'owner', 'offerer'], 'none'),
        ];
    }

    public static function requireCash(Input $input): void
    {
        $amount = $input->text('cash_amount', 20);
        if ($amount !== '' && Money::parse($amount) === null) {
            throw new DomainError('Cash amount must be a valid amount with up to two decimals.');
        }
    }
}
