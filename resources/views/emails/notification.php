<?php
declare(strict_types=1);
/** @var string $name */
/** @var string $title */
/** @var string $body */
/** @var string $url */
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<h1><?= $escape($title) ?></h1>
<p>Hello <?= $escape($name) ?>,</p>
<?php if ($body !== ''): ?><p><?= $escape($body) ?></p><?php endif ?>
<p><a href="<?= $escape($url) ?>">Open SwapFix</a></p>
<p>You receive this because email notifications are on in your SwapFix profile.</p>
