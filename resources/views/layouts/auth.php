<?php
declare(strict_types=1);

use SwapFix\Support\Assets;

/** @var SwapFix\Application\Ui $ui @var callable $e @var string $content */
$authArt = Assets::image('scene-auth');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1046d1">
    <title><?= $e($ui->title . ' | SwapFix') ?></title>
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
<main class="container auth-layout" id="main">
    <div class="auth-form-panel">
        <div class="auth-form-content"><?= $content ?></div>
    </div>
    <aside class="auth-visual-panel" aria-labelledby="auth-visual-title">
        <div class="auth-visual-copy">
            <h2 id="auth-visual-title">Your listings, trades, and repairs.</h2>
            <p>Use one SwapFix account to manage devices, messages, and repair updates.</p>
        </div>
        <div class="auth-visual-art" aria-hidden="true">
            <img src="<?= $e($authArt['src']) ?>" srcset="<?= $e($authArt['srcset']) ?>" sizes="(max-width: 760px) 36vw, 44vw" width="<?= $authArt['width'] ?>" height="<?= $authArt['height'] ?>" alt="" decoding="async">
        </div>
    </aside>
</main>
<?php require __DIR__ . '/../partials/toasts.php' ?>
</body>
</html>
