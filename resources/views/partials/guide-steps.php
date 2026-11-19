<?php
declare(strict_types=1);

use SwapFix\Support\Icon;

/** @var array $guide One entry from resources/data/guides.php */
?>
<ol class="guide-steps">
    <?php foreach ($guide['steps'] as $number => [$glyph, $title, $text]): ?>
        <li>
            <span class="guide-step-icon"><?= Icon::render($glyph) ?><b aria-hidden="true"><?= $number + 1 ?></b></span>
            <span><strong><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?></small></span>
        </li>
    <?php endforeach ?>
</ol>
