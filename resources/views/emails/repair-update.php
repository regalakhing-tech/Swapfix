<?php
declare(strict_types=1);
/** @var string $customerName */
/** @var string $device */
/** @var string $status */
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<h1>SwapFix repair update</h1>
<p>Hello <?= $escape($customerName) ?>,</p>
<p>Your <?= $escape($device) ?> repair is now: <strong><?= $escape($status) ?></strong>.</p>
<p>Open SwapFix to view the repair record and message your technician.</p>
