<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SwapFix\Preview\Catalog;

final class PreviewCatalogTest extends TestCase
{
    public function testBuySellCatalogCanBeFilteredByCategoryAndSearch(): void
    {
        $items = (new Catalog())->browse('buy-sell', 'phones', 'BLUE', 'latest');
        self::assertCount(1, $items);
        self::assertSame('iphone-pro-blue', $items[0]['id']);
    }

    public function testPriceSortingUsesNumericValues(): void
    {
        $items = (new Catalog())->browse('buy-sell', 'all', '', 'price-low');
        $prices = array_column($items, 'price');
        $expected = $prices;
        sort($expected, SORT_NUMERIC);
        self::assertSame($expected, $prices);
    }

    public function testExchangeAndRepairHaveTheirOwnRecords(): void
    {
        $catalog = new Catalog();
        foreach ($catalog->browse('exchange') as $item) {
            self::assertSame('exchange', $item['type']);
        }
        foreach ($catalog->browse('repair') as $item) {
            self::assertSame('repair', $item['type']);
        }
        self::assertNotEmpty($catalog->browse('exchange'));
        self::assertNotEmpty($catalog->browse('repair'));
    }

    public function testUnknownCategoryHasAnHonestEmptyState(): void
    {
        self::assertSame([], (new Catalog())->browse('buy-sell', 'spare-parts'));
    }
}
