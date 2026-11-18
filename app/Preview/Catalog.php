<?php
declare(strict_types=1);

namespace SwapFix\Preview;

/** Illustrative UI records only. No transactions or database records are created here. */
final class Catalog
{
    public function browse(string $view = 'buy-sell', string $category = 'all', string $search = '', string $sort = 'latest'): array
    {
        $items = require dirname(__DIR__, 2) . '/resources/data/catalog.php';
        $search = mb_strtolower(trim($search));
        $items = array_values(array_filter($items, static function (array $item) use ($view, $category, $search): bool {
            return $item['type'] === $view
                && ($category === 'all' || $item['category'] === $category)
                && ($search === '' || str_contains(mb_strtolower(implode(' ', [$item['name'], $item['description'], $item['location'], $item['specification']])), $search));
        }));
        if (in_array($sort, ['price-low', 'price-high'], true)) {
            usort($items, static fn (array $a, array $b): int => $sort === 'price-low' ? $a['price'] <=> $b['price'] : $b['price'] <=> $a['price']);
        }
        return $items;
    }
}
