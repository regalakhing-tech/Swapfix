<?php
use SwapFix\Support\Labels;
?>
<nav class="category-filters" aria-label="Categories">
    <a class="category-chip <?= $filters['category'] === '' ? 'active' : '' ?>" <?= $filters['category'] === '' ? 'aria-current="true"' : '' ?> href="<?= $e($ui->url(['category' => null, 'page' => null])) ?>"><?= $ui->icon('grid') ?>All</a>
    <?php foreach (Labels::CATEGORIES as $key => $label): ?>
        <a class="category-chip <?= $filters['category'] === $key ? 'active' : '' ?>" <?= $filters['category'] === $key ? 'aria-current="true"' : '' ?> href="<?= $e($ui->url(['category' => $key, 'page' => null])) ?>"><?= $ui->icon(Labels::CATEGORY_ICONS[$key]) ?><?= $e($label) ?></a>
    <?php endforeach ?>
</nav>
