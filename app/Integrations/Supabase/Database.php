<?php
declare(strict_types=1);

namespace SwapFix\Integrations\Supabase;

/** User-scoped Data API access. Reads run under RLS; writes go through sf_* functions. */
final class Database
{
    public function __construct(private readonly SupabaseClient $client) {}

    public function rpc(string $function, array $arguments = [], ?string $accessToken = null): array
    {
        return $this->client->request('POST', '/rest/v1/rpc/' . $function, ['json' => (object) $arguments], $accessToken);
    }

    public function select(string $table, array $query, ?string $accessToken = null): array
    {
        return $this->client->request('GET', '/rest/v1/' . $table, ['query' => $query], $accessToken);
    }

    public function first(string $table, array $query, ?string $accessToken = null): ?array
    {
        return $this->select($table, $query + ['limit' => 1], $accessToken)[0] ?? null;
    }
}
