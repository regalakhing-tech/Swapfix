<?php
/** @var SwapFix\Application\Ui $ui @var callable $e @var array $quickFilters group => list of [label, icon, query values, active] */
?>
<nav class="quick-filters" aria-label="Quick filters">
    <?php foreach ($quickFilters as $group => $chips): ?>
        <div class="quick-group">
            <span class="quick-label"><?= $e($group) ?></span>
            <?php foreach ($chips as [$label, $glyph, $values, $active]): ?>
                <a class="category-chip<?= $active ? ' active' : '' ?>"<?= $active ? ' aria-current="true"' : '' ?> href="<?= $e($ui->url(($active ? array_fill_keys(array_keys($values), null) : $values) + ['page' => null])) ?>"><?= $ui->icon($glyph) ?><?= $e($label) ?></a>
            <?php endforeach ?>
        </div>
    <?php endforeach ?>
</nav>
