<fieldset class="choice-cards">
    <legend>Account type</legend>
    <?php foreach (['customer' => ['user', 'Customer', 'Buy, sell, swap and request repairs'], 'technician' => ['wrench', 'Technician', 'All of that, plus repair jobs']] as $value => [$glyph, $label, $detail]): ?>
        <label class="choice-card">
            <input class="sr-only" type="radio" name="account_type" value="<?= $e($value) ?>" required <?= ($old['account_type'] ?? '') === $value ? 'checked' : '' ?>>
            <span class="icon-tile"><?= $ui->icon($glyph) ?></span>
            <span><strong><?= $e($label) ?></strong><small><?= $e($detail) ?></small></span>
        </label>
    <?php endforeach ?>
</fieldset>
<p class="field-hint">Your account type cannot be changed later.</p>
