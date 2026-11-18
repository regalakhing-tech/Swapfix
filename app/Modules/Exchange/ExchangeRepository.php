<?php
declare(strict_types=1);

namespace SwapFix\Modules\Exchange;

use SwapFix\Integrations\Supabase\Database;

final class ExchangeRepository
{
    private const COLUMNS = 'id,owner_id,title,description,category,brand,model,condition,known_defects,location,photo_paths,wanted_item,wanted_details,cash_preference,status,accepted_offer_id,completed_at,created_at,updated_at';
    private const PERSON = '(id,display_name,avatar_path)';
    public const PAGE_SIZE = 24;

    public function __construct(private readonly Database $db) {}

    public function search(array $filters, int $page, ?string $token): array
    {
        return $this->db->rpc('sf_search_exchanges', [
            'p_query' => $filters['q'] ?: null, 'p_category' => $filters['category'] ?: null, 'p_brand' => $filters['brand'] ?: null,
            'p_condition' => $filters['condition'] ?: null, 'p_location' => $filters['location'] ?: null,
            'p_limit' => self::PAGE_SIZE, 'p_offset' => ($page - 1) * self::PAGE_SIZE,
        ], $token);
    }

    public function find(string $id, ?string $token): ?array
    {
        return $this->db->first('exchange_listings', [
            'select' => self::COLUMNS . ',owner:profiles!exchange_listings_owner_id_fkey' . self::PERSON, 'id' => 'eq.' . $id,
        ], $token);
    }

    /** RLS returns every offer to the owner and only their own offers to an offerer. */
    public function offersFor(string $token, string $exchangeId): array
    {
        return self::withLatestTerms($this->db->select('trade_offers', [
            'select' => '*,offerer:profiles!trade_offers_offerer_id_fkey' . self::PERSON . ',trade_offer_terms(*)',
            'exchange_id' => 'eq.' . $exchangeId, 'order' => 'created_at.desc', 'limit' => 100,
        ], $token));
    }

    public function offer(string $token, string $id): ?array
    {
        $rows = $this->db->select('trade_offers', [
            'select' => '*,offerer:profiles!trade_offers_offerer_id_fkey' . self::PERSON . ',owner:profiles!trade_offers_owner_id_fkey' . self::PERSON
                . ',exchange:exchange_listings!trade_offers_exchange_id_fkey(' . self::COLUMNS . '),trade_offer_terms(*),trade_offer_events(*)',
            'id' => 'eq.' . $id, 'limit' => 1,
        ], $token);
        return self::withLatestTerms($rows)[0] ?? null;
    }

    public function conversationForOffer(string $token, string $offerId): ?array
    {
        return $this->db->first('conversations', ['select' => 'id', 'offer_id' => 'eq.' . $offerId], $token);
    }

    public function save(string $token, ?string $id, array $fields, array $photoPaths): array
    {
        return $this->db->rpc('sf_save_exchange', [
            'p_id' => $id, 'p_title' => $fields['title'], 'p_description' => $fields['description'], 'p_category' => $fields['category'],
            'p_brand' => $fields['brand'], 'p_model' => $fields['model'], 'p_condition' => $fields['condition'],
            'p_known_defects' => $fields['known_defects'], 'p_location' => $fields['location'], 'p_photo_paths' => $photoPaths,
            'p_wanted_item' => $fields['wanted_item'], 'p_wanted_details' => $fields['wanted_details'], 'p_cash_preference' => $fields['cash_preference'],
        ], $token);
    }

    public function remove(string $token, string $id): array
    {
        return $this->db->rpc('sf_remove_exchange', ['p_id' => $id], $token);
    }

    public function complete(string $token, string $id): array
    {
        return $this->db->rpc('sf_complete_exchange', ['p_exchange_id' => $id], $token);
    }

    public function cancelAgreement(string $token, string $id, string $note): array
    {
        return $this->db->rpc('sf_cancel_agreement', ['p_exchange_id' => $id, 'p_note' => $note], $token);
    }

    public function submitOffer(string $token, string $exchangeId, array $terms, array $photoPaths): array
    {
        return $this->db->rpc('sf_submit_offer', ['p_exchange_id' => $exchangeId] + self::termArguments($terms) + ['p_photo_paths' => $photoPaths], $token);
    }

    public function counter(string $token, string $offerId, int $version, array $terms, ?array $photoPaths): array
    {
        return $this->db->rpc('sf_counter_offer', ['p_offer_id' => $offerId, 'p_expected_version' => $version] + self::termArguments($terms) + ['p_photo_paths' => $photoPaths], $token);
    }

    public function accept(string $token, string $offerId, int $version): array
    {
        return $this->db->rpc('sf_accept_offer', ['p_offer_id' => $offerId, 'p_expected_version' => $version], $token);
    }

    public function decline(string $token, string $offerId, int $version, string $note): array
    {
        return $this->db->rpc('sf_decline_offer', ['p_offer_id' => $offerId, 'p_expected_version' => $version, 'p_note' => $note], $token);
    }

    public function withdraw(string $token, string $offerId): array
    {
        return $this->db->rpc('sf_withdraw_offer', ['p_offer_id' => $offerId], $token);
    }

    private static function termArguments(array $terms): array
    {
        return [
            'p_device_title' => $terms['device_title'], 'p_brand' => $terms['brand'], 'p_model' => $terms['model'],
            'p_condition' => $terms['condition'], 'p_known_defects' => $terms['known_defects'], 'p_message' => $terms['message'],
            'p_cash_amount' => $terms['cash_amount'], 'p_cash_payer' => $terms['cash_payer'],
        ];
    }

    private static function withLatestTerms(array $offers): array
    {
        foreach ($offers as &$offer) {
            $terms = $offer['trade_offer_terms'] ?? [];
            usort($terms, static fn (array $a, array $b): int => $b['version'] <=> $a['version']);
            $offer['terms'] = $terms;
            $offer['latest'] = $terms[0] ?? null;
        }
        return $offers;
    }
}
