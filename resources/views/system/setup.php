<?php $ui->title = 'Not connected yet'; ?>
<section class="system-page">
    <span class="icon-tile"><?= $ui->icon('plug') ?></span>
    <h1>SwapFix is not connected yet</h1>
    <p>Accounts and live records need these server settings. No sample data is shown here in their place.</p>
    <ul class="setting-list"><?php foreach ($settings as $setting): ?><li><code><?= $e($setting) ?></code></li><?php endforeach ?></ul>
    <a class="button button-outline" href="/"><?= $ui->icon('home') ?>View the design preview</a>
</section>
