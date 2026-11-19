<?php
use SwapFix\Support\Labels;

$photo = $item['photo_paths'][0] ?? null;
?>
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
