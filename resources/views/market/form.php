<?php
use SwapFix\Support\Labels;

$editing = $listing !== null;
echo $ui->heading('bag', $editing ? 'Edit listing' : 'New listing', $editing ? $listing['title'] : 'Gadgets, accessories and spare parts', $ui->link($editing ? '/market/' . $listing['id'] : '/market', 'Back', 'arrow-left', 'button button-outline'));
$remaining = 8 - count($old['photo_paths'] ?? []);
?>
<form method="post" action="<?= $editing ? '/market/' . $e($listing['id']) : '/market' ?>" enctype="multipart/form-data" class="card form-card">
    <?= $ui->csrf() ?>
    <?= $ui->alert($error ?? null) ?>
    <div class="form-grid">
        <?= $ui->input('title', 'Title', $old['title'], ['required' => true, 'minlength' => 3, 'maxlength' => 150, 'data-wide' => true, 'placeholder' => 'iPhone 13 128GB, blue']) ?>
        <?= $ui->select('category', 'Category', Labels::CATEGORIES, $old['category'], ['required' => true]) ?>
        <?= $ui->select('condition', 'Condition', Labels::CONDITIONS, $old['condition'], ['required' => true]) ?>
        <?= $ui->input('brand', 'Brand', $old['brand'], ['required' => true, 'maxlength' => 60]) ?>
        <?= $ui->input('model', 'Model', $old['model'], ['maxlength' => 80]) ?>
        <?= $ui->input('price', 'Price (PHP)', $old['price'], ['required' => true, 'inputmode' => 'decimal', 'placeholder' => '0.00']) ?>
        <?= $ui->input('location', 'Location', $old['location'], ['required' => true, 'minlength' => 2, 'maxlength' => 120]) ?>
        <?= $ui->textarea('description', 'Description', $old['description'], ['required' => true, 'maxlength' => 4000, 'data-wide' => true], 'What is included and how it works.') ?>
        <?= $ui->textarea('known_defects', 'Known defects', $old['known_defects'], ['maxlength' => 2000, 'data-wide' => true], 'Faults, damage or missing parts. Buyers see this before they inquire.') ?>
        <?php require __DIR__ . '/../partials/keep-photos.php' ?>
        <?php if ($remaining > 0): ?><?= $ui->photoInput('photos', 'Add photos', $remaining) ?><?php endif ?>
    </div>
    <div class="form-actions"><button class="button button-primary" type="submit"><?= $ui->icon('check') ?><?= $editing ? 'Save changes' : 'Publish listing' ?></button></div>
</form>
