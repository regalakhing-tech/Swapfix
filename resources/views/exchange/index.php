<?php
use SwapFix\Support\Labels;

$total = (int) $results['total'];
$ui->title = 'Exchange';
$quickFilters = ['Condition' => array_map(
    static fn (string $key): array => [Labels::CONDITIONS[$key], 'check-circle', ['condition' => $key], $filters['condition'] === $key],
    ['new', 'like_new', 'good', 'fair'],
)];
?>
<section class="browse-page discover-exchange" aria-labelledby="exchange-title">
    <header class="browse-intro exchange-intro">
        <div class="browse-intro-copy">
            <p class="browse-section-name"><?= $ui->icon('swap') ?>Exchange</p>
            <h1 id="exchange-title">Trade the tech you have.</h1>
            <p>See what each owner wants in return.</p>
            <ul class="browse-facts">
                <li><?= $ui->icon('swap') ?><?= $total ?> open <?= $total === 1 ? 'trade' : 'trades' ?></li>
                <li><?= $ui->icon('cash') ?>Cash optional</li>
                <li><?= $ui->icon('clipboard') ?>Offer history</li>
            </ul>
            <?= $ui->link('/exchange/new', 'Post an exchange', 'plus') ?>
        </div>
        <div class="browse-scene" aria-hidden="true">
            <?php $sceneKey = 'home-trade'; $sceneLabel = ''; $sceneSizes = '(max-width: 720px) 45vw, 340px'; require __DIR__ . '/../partials/scene-art.php'; ?>
        </div>
    </header>
<form class="filter-bar card" method="get" action="/exchange" role="search">
    <label class="search-field filter-search"><?= $ui->icon('search') ?><span class="sr-only">Search exchanges</span><input type="search" name="q" value="<?= $e($filters['q']) ?>" maxlength="100" placeholder="Gadget or what is wanted"></label>
    <input type="hidden" name="category" value="<?= $e($filters['category']) ?>">
    <details class="filter-more" <?= ($filters['brand'] . $filters['condition'] . $filters['location']) !== '' ? 'open' : '' ?>>
        <summary class="button button-outline"><?= $ui->icon('settings') ?>Filters</summary>
        <div class="form-grid filter-grid">
            <?= $ui->input('brand', 'Brand', $filters['brand'], ['maxlength' => 60]) ?>
            <?= $ui->select('condition', 'Condition', ['' => 'Any'] + Labels::CONDITIONS, $filters['condition']) ?>
            <?= $ui->input('location', 'Location', $filters['location'], ['maxlength' => 120]) ?>
        </div>
    </details>
    <button class="button button-primary" type="submit"><?= $ui->icon('search') ?>Search</button>
</form>
<?php require __DIR__ . '/../partials/filter-chips.php' ?>
<?php require __DIR__ . '/../partials/quick-filters.php' ?>
<p class="browse-count"><?= $total ?> <?= $total === 1 ? 'gadget' : 'gadgets' ?> open to trade</p>
<?php if ($results['items'] === []): ?>
    <?= $ui->emptyState('swap', 'No exchanges match yet.', $ui->link('/exchange', 'Clear filters', 'close', 'button button-outline')) ?>
<?php else: ?>
    <div class="product-grid live-grid exchange-results">
        <?php foreach ($results['items'] as $item): require __DIR__ . '/../partials/exchange-card.php'; endforeach ?>
    </div>
    <?= $ui->pagination($total, $pageNumber, $pageSize) ?>
<?php endif ?>
<?php $guideKey = 'exchange'; $guideCta = ['Got a spare device?', 'Trade it for what you need.', '/exchange/new', 'Start a trade', 'swap']; require __DIR__ . '/../partials/section-guide.php' ?>
</section>
