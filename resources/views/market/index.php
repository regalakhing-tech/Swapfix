<?php
use SwapFix\Support\Labels;

$total = (int) $results['total'];
echo $ui->heading('bag', 'Buy & Sell', $total . ' ' . ($total === 1 ? 'listing' : 'listings'), $ui->link('/market/new', 'New listing', 'plus'));
?>
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
<?php require __DIR__ . '/../partials/filter-chips.php' ?>
<?php if ($results['items'] === []): ?>
    <?= $ui->emptyState('bag', 'No listings match yet.', $ui->link('/market', 'Clear filters', 'close', 'button button-outline')) ?>
<?php else: ?>
    <div class="product-grid live-grid">
        <?php foreach ($results['items'] as $item): $photo = $item['photo_paths'][0] ?? null; ?>
            <article class="product-card">
                <div class="product-photo <?= $photo ? '' : 'service-photo' ?>">
                    <a href="/market/<?= $e($item['id']) ?>" aria-label="View <?= $e($item['title']) ?>">
                        <?php if ($photo): ?><img src="<?= $e($ui->photoUrl('listing-images', $photo)) ?>" width="640" height="480" alt="<?= $e($item['title']) ?>" loading="lazy" decoding="async"><?php else: ?><span class="service-symbol"><?= $ui->icon(Labels::CATEGORY_ICONS[$item['category']] ?? 'bag') ?></span><?php endif ?>
                    </a>
                    <?php if ($item['status'] === 'reserved'): ?><span class="listing-badge"><?= $e(Labels::status('reserved')) ?></span><?php endif ?>
                </div>
                <div class="product-body">
                    <p class="item-condition"><?= $ui->icon('check-circle') ?><?= $e(Labels::CONDITIONS[$item['condition']] ?? '') ?></p>
                    <h3><a href="/market/<?= $e($item['id']) ?>"><?= $e($item['title']) ?></a></h3>
                    <p class="product-specification"><?= $e(trim($item['brand'] . ' ' . $item['model'])) ?></p>
                    <?php if ($item['known_defects'] !== ''): ?><p class="defect-note"><?= $ui->icon('alert') ?>Has known defects</p><?php endif ?>
                    <div class="product-price"><?= $ui->money($item['price']) ?></div>
                    <p class="item-location"><?= $ui->icon('pin') ?><?= $e($item['location']) ?></p>
                </div>
            </article>
        <?php endforeach ?>
    </div>
    <?= $ui->pagination($total, $pageNumber, $pageSize) ?>
<?php endif ?>
