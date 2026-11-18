<?php
use SwapFix\Support\Labels;

$total = (int) $results['total'];
$ui->title = 'Exchange';
?>
<section class="browse-page discover-exchange" aria-labelledby="exchange-title">
    <header class="browse-intro exchange-intro">
        <div class="browse-intro-copy">
            <p class="browse-section-name"><?= $ui->icon('swap') ?>Exchange</p>
            <h1 id="exchange-title">Trade the tech you have.</h1>
            <p>Browse gadgets open to trade and see what each owner is looking for in return.</p>
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
<p class="browse-count"><?= $total ?> <?= $total === 1 ? 'gadget' : 'gadgets' ?> open to trade</p>
<?php if ($results['items'] === []): ?>
    <?= $ui->emptyState('swap', 'No exchanges match yet.', $ui->link('/exchange', 'Clear filters', 'close', 'button button-outline')) ?>
<?php else: ?>
    <div class="product-grid live-grid exchange-results">
        <?php foreach ($results['items'] as $item): $photo = $item['photo_paths'][0] ?? null; ?>
            <article class="product-card">
                <div class="product-photo <?= $photo ? '' : 'service-photo' ?>">
                    <a href="/exchange/<?= $e($item['id']) ?>" aria-label="View <?= $e($item['title']) ?>">
                        <?php if ($photo): ?><img src="<?= $e($ui->photoUrl('listing-images', $photo)) ?>" width="640" height="480" alt="<?= $e($item['title']) ?>" loading="lazy" decoding="async"><?php else: ?><span class="service-symbol"><?= $ui->icon('swap') ?></span><?php endif ?>
                    </a>
                    <span class="listing-badge teal"><?= $e(Labels::status($item['status'])) ?></span>
                </div>
                <div class="product-body">
                    <p class="item-condition"><?= $ui->icon('check-circle') ?><?= $e(Labels::CONDITIONS[$item['condition']] ?? '') ?></p>
                    <h3><a href="/exchange/<?= $e($item['id']) ?>"><?= $e($item['title']) ?></a></h3>
                    <p class="product-specification"><?= $e(trim($item['brand'] . ' ' . $item['model'])) ?></p>
                    <div class="exchange-wanted"><span><?= $ui->icon('swap') ?>Wanted in return</span><strong><?= $e($item['wanted_item']) ?></strong></div>
                    <p class="item-location"><?= $ui->icon('pin') ?><?= $e($item['location']) ?></p>
                </div>
            </article>
        <?php endforeach ?>
    </div>
    <?= $ui->pagination($total, $pageNumber, $pageSize) ?>
<?php endif ?>
</section>
