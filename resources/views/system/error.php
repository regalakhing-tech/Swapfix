<?php $ui->title = $title; ?>
<section class="system-page">
    <span class="icon-tile"><?= $ui->icon($status === 404 ? 'search' : 'alert') ?></span>
    <h1><?= $e($title) ?></h1>
    <p><?= $e($message) ?></p>
    <a class="button button-primary" href="/market"><?= $ui->icon('bag') ?>Go to Buy &amp; Sell</a>
</section>
