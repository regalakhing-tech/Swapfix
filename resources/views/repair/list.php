<?php
echo $ui->heading('clipboard', 'My repairs', count($repairs) . ' requested', $ui->link('/repair', 'Find a technician', 'search'));
$showCustomer = false;
?>
<?php if ($repairs === []): ?>
    <?= $ui->emptyState('wrench', 'You have not requested a repair yet.', $ui->link('/repair', 'Find a technician', 'search')) ?>
<?php else: ?>
    <div class="row-list card"><?php foreach ($repairs as $job): require __DIR__ . '/job-row.php'; endforeach ?></div>
<?php endif ?>
