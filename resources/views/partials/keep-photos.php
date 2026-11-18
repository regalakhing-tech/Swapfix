<?php if (($old['photo_paths'] ?? []) !== []): ?>
    <fieldset class="keep-photos field-full">
        <legend>Current photos</legend>
        <?php foreach ($old['photo_paths'] as $path): ?>
            <label><img src="<?= $e($ui->photoUrl('listing-images', $path)) ?>" width="160" height="120" alt="Current listing photo" loading="lazy"><span><input type="checkbox" name="keep_photos[]" value="<?= $e($path) ?>" checked>Keep</span></label>
        <?php endforeach ?>
    </fieldset>
<?php endif ?>
