<?php
/** @var list<string> $photoSources Image URLs. @var string $photoAlt @var string $photoIcon */
?>
<div class="gallery">
    <?php if ($photoSources === []): ?>
        <div class="gallery-empty"><?= $ui->icon($photoIcon) ?><span>No photos added</span></div>
    <?php else: foreach ($photoSources as $index => $source): ?>
        <a href="<?= $e($source) ?>" class="<?= $index === 0 ? 'gallery-main' : 'gallery-thumb' ?>" target="_blank" rel="noopener"><img src="<?= $e($source) ?>" width="800" height="600" alt="<?= $e($photoAlt . ', photo ' . ($index + 1)) ?>" <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async"></a>
    <?php endforeach; endif ?>
</div>
