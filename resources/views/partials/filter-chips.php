<?php
use SwapFix\Support\Labels;
?>
<nav class="category-filters" aria-label="Categories">
    <a class="category-chip <?= $filters['category'] === '' ? 'active' : '' ?>" href="<?= $e($ui->url(['category' => null, 'page' => null])) ?>"><?= $ui->icon('grid') ?>All</a>
    <?php foreach (Labels::CATEGORIES as $key => $label): ?>
        <a class="category-chip <?= $filters['category'] === $key ? 'active' : '' ?>" href="<?= $e($ui->url(['category' => $key, 'page' => null])) ?>"><?= $ui->icon(Labels::CATEGORY_ICONS[$key]) ?><?= $e($label) ?></a>
    <?php endforeach ?>
</nav>
