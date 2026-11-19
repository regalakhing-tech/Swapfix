<?php
declare(strict_types=1);

use SwapFix\Support\Assets;

/** @var SwapFix\Application\Ui $ui @var callable $e @var string $content */
$registration = $ui->path === '/register' && isset($old['account_type']);
$signIn = $ui->path === '/login';
$layoutClass = match (true) {
    $registration => ' auth-layout--register',
    $signIn => '',
    default => ' auth-layout--single',
};
$authArt = $registration || $signIn ? Assets::image($registration ? 'hero-discover' : 'scene-auth') : null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1046d1">
    <title><?= $e($ui->title . ' | SwapFix') ?></title>
    <?php require __DIR__ . '/../partials/head-icons.php' ?>
    <link rel="preload" href="<?= $ui->asset('/assets/fonts/sora-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= $ui->asset('/assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= $ui->asset('/assets/css/workspace.css') ?>">
    <link rel="stylesheet" href="<?= $ui->asset('/assets/css/public-header.css') ?>">
    <link rel="stylesheet" href="<?= $ui->asset('/assets/css/auth.css') ?>">
    <script src="<?= $ui->asset('/assets/js/app.js') ?>" defer></script>
</head>
<body class="auth-page">
<a class="skip-link" href="#main">Skip to content</a>
<?php $publicPath = $ui->path; $publicSignedIn = $ui->session !== null; require __DIR__ . '/../partials/public-header.php'; ?>
<main class="container auth-layout<?= $e($layoutClass) ?>" id="main">
    <div class="auth-form-content"><?= $content ?></div>
    <?php if ($authArt !== null): ?>
        <aside class="auth-visual-panel" aria-labelledby="auth-visual-title">
            <div class="auth-visual-art" aria-hidden="true">
                <img src="<?= $e($authArt['src']) ?>" srcset="<?= $e($authArt['srcset']) ?>" sizes="(max-width: 500px) 92px, (max-width: 760px) 116px, 320px" width="<?= $authArt['width'] ?>" height="<?= $authArt['height'] ?>" alt="" decoding="async">
            </div>
            <div class="auth-visual-copy">
                <?php if ($registration): ?>
                    <h2 id="auth-visual-title">Choose the right account</h2>
                    <ul class="auth-perks">
                        <li><?= $ui->icon('bag') ?>Buy and sell</li>
                        <li><?= $ui->icon('swap') ?>Trade gadgets</li>
                        <li><?= $ui->icon('wrench') ?>Request or offer repairs</li>
                    </ul>
                <?php else: ?>
                    <h2 id="auth-visual-title">Pick up where you left off</h2>
                    <ul class="auth-perks">
                        <li><?= $ui->icon('bag') ?>Your listings</li>
                        <li><?= $ui->icon('swap') ?>Trade offers</li>
                        <li><?= $ui->icon('clipboard') ?>Repair updates</li>
                    </ul>
                <?php endif ?>
            </div>
        </aside>
    <?php endif ?>
</main>
<?php require __DIR__ . '/../partials/toasts.php' ?>
</body>
</html>
