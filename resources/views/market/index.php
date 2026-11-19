<?php
use SwapFix\Support\Labels;

$total = (int) $results['total'];
$ui->title = 'Buy & Sell';
$minPrice = (float) ($filters['min'] ?? 0);
$maxPrice = (float) ($filters['max'] ?? 0);
$priceChip = static fn (string $label, ?int $min, ?int $max): array => [$label, 'cash', ['min' => $min, 'max' => $max], $minPrice === (float) $min && $maxPrice === (float) $max];
$conditionChip = static fn (string $key): array => [Labels::CONDITIONS[$key], 'check-circle', ['condition' => $key], $filters['condition'] === $key];
$quickFilters = [
    'Price' => [$priceChip('Under ₱5,000', null, 5000), $priceChip('₱5,000 to ₱20,000', 5000, 20000), $priceChip('Over ₱20,000', 20000, null)],
    'Condition' => [$conditionChip('new'), $conditionChip('like_new'), $conditionChip('good')],
];
?>
<section class="browse-page discover-buy-sell" aria-labelledby="market-title">
    <header class="browse-intro market-intro">
        <div class="browse-intro-copy">
            <p class="browse-section-name"><?= $ui->icon('bag') ?>Buy &amp; Sell</p>
            <h1 id="market-title">Find a gadget worth keeping.</h1>
            <p>Check price, condition, and location before you message the seller.</p>
            <ul class="browse-facts">
                <li><?= $ui->icon('bag') ?><?= $total ?> <?= $total === 1 ? 'listing' : 'listings' ?></li>
                <li><?= $ui->icon('shield') ?>Verified email</li>
                <li><?= $ui->icon('message') ?>Built-in chat</li>
            </ul>
            <?= $ui->link('/market/new', 'List a gadget', 'plus') ?>
        </div>
        <div class="browse-scene" aria-hidden="true">
            <?php $sceneKey = 'scene-market'; $sceneLabel = ''; $sceneSizes = '(max-width: 720px) 45vw, 340px'; require __DIR__ . '/../partials/scene-art.php'; ?>
        </div>
    </header>
<?php require __DIR__ . '/../partials/filter-chips.php' ?>
<?php require __DIR__ . '/../partials/quick-filters.php' ?>
<form class="filter-bar card" method="get" action="/market" role="search">
    <label class="search-field filter-search"><?= $ui->icon('search') ?><span class="sr-only">Search listings</span><input type="search" name="q" value="<?= $e($filters['q']) ?>" maxlength="100" placeholder="Name, brand or model"></label>
    <input type="hidden" name="category" value="<?= $e($filters['category']) ?>">
    <details class="filter-more" <?= ($filters['brand'] . $filters['condition'] . $filters['min'] . $filters['max'] . $filters['location']) !== '' ? 'open' : '' ?>>
        <summary class="button button-outline"><?= $ui->icon('settings') ?>Filters</summary>
        <div class="form-grid filter-grid">
            <?= $ui->input('brand', 'Brand', $filters['brand'], ['maxlength' => 60]) ?>
            <?= $ui->select('condition', 'Condition', ['' => 'Any'] + Labels::CONDITIONS, $filters['condition']) ?>
            <?= $ui->input('min', 'Min price', $filters['min'], ['inputmode' => 'decimal']) ?>
            <?= $ui->input('max', 'Max price', $filters['max'], ['inputmode' => 'decimal']) ?>
            <?= $ui->input('location', 'Location', $filters['location'], ['maxlength' => 120]) ?>
        </div>
    </details>
    <label class="sort-field"><span class="sr-only">Sort</span><select name="sort"><?php foreach (['newest' => 'Newest', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low'] as $value => $label): ?><option value="<?= $e($value) ?>" <?= $filters['sort'] === $value ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach ?></select></label>
    <button class="button button-primary" type="submit"><?= $ui->icon('search') ?>Search</button>
</form>
<p class="browse-count"><?= $total ?> <?= $total === 1 ? 'listing' : 'listings' ?></p>
<?php if ($results['items'] === []): ?>
    <?= $ui->emptyState('bag', 'No listings match yet.', $ui->link('/market', 'Clear filters', 'close', 'button button-outline')) ?>
<?php else: ?>
    <div class="product-grid live-grid">
        <?php foreach ($results['items'] as $item): require __DIR__ . '/../partials/listing-card.php'; endforeach ?>
    </div>
    <?= $ui->pagination($total, $pageNumber, $pageSize) ?>
<?php endif ?>
<?php $guideKey = 'buy-sell'; $guideCta = ['Selling something?', 'Reach buyers near you.', '/market/new', 'Start selling', 'bag']; require __DIR__ . '/../partials/section-guide.php' ?>
</section>
