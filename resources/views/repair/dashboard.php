<?php
echo $ui->dashHero(
    'wrench',
    'Technician',
    'Technician dashboard',
    count($board['new']) . ' new, ' . count($board['active']) . ' active',
    $ui->link('/technician/services', 'Services', 'settings', 'button button-light') . $ui->link('/technician/profile', 'Public profile', 'shield', 'button button-ghost'),
);
$groups = [
    'new' => ['New requests', 'clipboard', 'blue'],
    'awaiting' => ['Awaiting customer', 'clock', 'amber'],
    'active' => ['Active jobs', 'wrench', 'teal'],
    'completed' => ['Completed', 'check-circle', 'green'],
];
$jobTotal = array_sum(array_map('count', $board));
$showCustomer = true;
?>
<div class="metrics">
    <?php foreach ($groups as $key => [$label, $glyph, $tone]): ?>
        <?= $ui->kpi($glyph, count($board[$key]), $label, $tone, '#' . $key, [count($board[$key]), $jobTotal]) ?>
    <?php endforeach ?>
</div>
<div class="panel-grid">
    <?php foreach ($groups as $key => [$label, $glyph]): ?>
        <section class="card" id="<?= $e($key) ?>">
            <h2 class="section-title"><?= $ui->icon($glyph) ?><?= $e($label) ?></h2>
            <?php if ($board[$key] === []): ?><p class="muted-line">Nothing here right now.</p><?php endif ?>
            <div class="row-list"><?php foreach ($board[$key] as $job): require __DIR__ . '/job-row.php'; endforeach ?></div>
        </section>
    <?php endforeach ?>
    <?php if ($board['cancelled'] !== []): ?>
        <section class="card"><h2 class="section-title"><?= $ui->icon('close') ?>Cancelled</h2><div class="row-list"><?php foreach ($board['cancelled'] as $job): require __DIR__ . '/job-row.php'; endforeach ?></div></section>
    <?php endif ?>
</div>
