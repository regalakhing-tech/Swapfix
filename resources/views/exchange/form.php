<?php
use SwapFix\Support\Labels;

$editing = $exchange !== null;
echo $ui->heading('swap', $editing ? 'Edit exchange' : 'New exchange', $editing ? $exchange['title'] : 'Offer a gadget and say what you want', $ui->link($editing ? '/exchange/' . $exchange['id'] : '/exchange', 'Back', 'arrow-left', 'button button-outline'));
?>
<form method="post" action="<?= $editing ? '/exchange/' . $e($exchange['id']) : '/exchange' ?>" enctype="multipart/form-data" class="card form-card">
    <?= $ui->csrf() ?>
    <?= $ui->alert($error ?? null) ?>
    <h2 class="section-title"><?= $ui->icon('phone') ?>Your gadget</h2>
    <div class="form-grid form-grid-wide">
        <?= $ui->input('title', 'Title', $old['title'], ['required' => true, 'minlength' => 3, 'maxlength' => 150, 'data-wide' => true]) ?>
        <?= $ui->select('category', 'Category', Labels::CATEGORIES, $old['category'], ['required' => true]) ?>
        <?= $ui->select('condition', 'Condition', Labels::CONDITIONS, $old['condition'], ['required' => true]) ?>
        <?= $ui->input('brand', 'Brand', $old['brand'], ['required' => true, 'maxlength' => 60]) ?>
        <?= $ui->input('model', 'Model', $old['model'], ['maxlength' => 80]) ?>
        <?= $ui->input('location', 'Location', $old['location'], ['required' => true, 'minlength' => 2, 'maxlength' => 120, 'data-wide' => true]) ?>
        <?= $ui->textarea('description', 'Description', $old['description'], ['maxlength' => 4000, 'data-wide' => true]) ?>
        <?= $ui->textarea('known_defects', 'Known defects', $old['known_defects'], ['maxlength' => 2000, 'data-wide' => true]) ?>
        <?php require __DIR__ . '/../partials/keep-photos.php' ?>
        <?= $ui->photoInput('photos', 'Add photos', 8) ?>
    </div>
    <h2 class="section-title"><?= $ui->icon('swap') ?>What you want</h2>
    <div class="form-grid">
        <?= $ui->input('wanted_item', 'Wanted gadget', $old['wanted_item'], ['required' => true, 'minlength' => 2, 'maxlength' => 200, 'data-wide' => true, 'placeholder' => 'iPhone 13 or similar']) ?>
        <?= $ui->textarea('wanted_details', 'Wanted details', $old['wanted_details'], ['maxlength' => 2000, 'data-wide' => true]) ?>
        <?= $ui->select('cash_preference', 'Cash adjustment', Labels::CASH_PREFERENCES, $old['cash_preference'], ['required' => true, 'data-wide' => true]) ?>
    </div>
    <div class="form-actions"><button class="button button-primary" type="submit"><?= $ui->icon('check') ?><?= $editing ? 'Save changes' : 'Publish exchange' ?></button></div>
</form>
