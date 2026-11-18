<?php
echo $ui->heading('grid', 'Technician dashboard', count($board['new']) . ' new, ' . count($board['active']) . ' active', $ui->link('/technician/services', 'Services', 'settings', 'button button-outline'));
$groups = [
    'new' => ['New requests', 'clipboard', 'stat-blue'],
    'awaiting' => ['Awaiting customer', 'clock', 'stat-amber'],
    'active' => ['Active jobs', 'wrench', 'stat-teal'],
    'completed' => ['Completed', 'check-circle', 'stat-green'],
];
$showCustomer = true;
?>
<div class="metrics">
    <?php foreach ($groups as $key => [$label, $glyph, $tone]): ?>
        <a class="stat <?= $e($tone) ?>" href="#<?= $e($key) ?>"><span class="stat-icon"><?= $ui->icon($glyph) ?></span><strong><?= count($board[$key]) ?></strong><span><?= $e($label) ?></span></a>
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
