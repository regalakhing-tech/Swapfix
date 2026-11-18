<?php
declare(strict_types=1);

use SwapFix\Support\Assets;

$sceneArt = Assets::image($sceneKey);
?>
<img src="<?= htmlspecialchars($sceneArt['src'], ENT_QUOTES, 'UTF-8') ?>" srcset="<?= htmlspecialchars($sceneArt['srcset'], ENT_QUOTES, 'UTF-8') ?>" sizes="<?= htmlspecialchars($sceneSizes, ENT_QUOTES, 'UTF-8') ?>" width="<?= $sceneArt['width'] ?>" height="<?= $sceneArt['height'] ?>" alt="<?= htmlspecialchars($sceneLabel, ENT_QUOTES, 'UTF-8') ?>" loading="lazy" decoding="async">
