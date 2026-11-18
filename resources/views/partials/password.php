<div class="field">
    <label for="password">Password</label>
    <div class="password-field">
        <input id="password" name="password" type="password" placeholder="<?= $e($placeholder) ?>" autocomplete="<?= $e($autocomplete) ?>" required <?= $autocomplete === 'new-password' ? 'minlength="8"' : '' ?>>
        <button type="button" class="icon-button" data-password-toggle aria-label="Show password" aria-pressed="false"><?= $ui->icon('eye') ?></button>
    </div>
</div>
