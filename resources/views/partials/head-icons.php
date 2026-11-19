<?php
declare(strict_types=1);

use SwapFix\Support\Assets;

$tabIcon = Assets::image('favicon');
$touchIcon = Assets::image('apple-touch-icon');
?>
<link rel="icon" type="image/png" sizes="64x64" href="<?= htmlspecialchars($tabIcon['src'], ENT_QUOTES, 'UTF-8') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars($touchIcon['src'], ENT_QUOTES, 'UTF-8') ?>">
