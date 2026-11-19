<?php
use SwapFix\Support\Labels;

$photo = $item['photo_paths'][0] ?? null;
?>
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
