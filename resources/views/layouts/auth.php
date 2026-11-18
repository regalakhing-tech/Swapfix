<?php
declare(strict_types=1);
/** @var SwapFix\Application\Ui $ui @var callable $e @var string $content */
$logo = $ui->logo();
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
    <script src="<?= $ui->asset('/assets/js/app.js') ?>" defer></script>
</head>
<body class="auth-page">
<main class="auth-shell">
    <a class="auth-back" href="/"><?= $ui->icon('arrow-left') ?>Back</a>
    <section class="auth-card">
        <a class="auth-logo" href="/" aria-label="SwapFix home"><img src="<?= $e($logo['src']) ?>" alt="SwapFix" width="<?= (int) $logo['width'] ?>" height="<?= (int) $logo['height'] ?>"></a>
        <?= $content ?>
    </section>
</main>
<?php require __DIR__ . '/../partials/toasts.php' ?>
</body>
</html>
