<?php
/** @var array $job @var bool $showCustomer */
$person = $showCustomer ? $job['customer'] : ($job['technician']['profile'] ?? null);
?>
<a class="list-row" href="/repairs/<?= $e($job['id']) ?>">
    <?= $ui->avatar($person) ?>
    <span><strong><?= $e($job['device_brand'] . ' ' . $job['device_model']) ?></strong><small><?= $e($person['display_name'] ?? '') ?> &middot; <?= $ui->date($job['updated_at']) ?></small></span>
    <?= $ui->status($job['status']) ?>
</a>
