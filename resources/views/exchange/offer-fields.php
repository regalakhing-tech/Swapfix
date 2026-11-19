<?php
use SwapFix\Support\Labels;

/** @var array $old @var bool $allowPhotos */
$amount = (float) ($old['cash_amount'] ?? 0) > 0 ? $old['cash_amount'] : '';
?>
<div class="form-grid offer-grid">
    <?= $ui->input('device_title', 'Gadget offered', $old['device_title'], ['required' => true, 'minlength' => 3, 'maxlength' => 150, 'data-wide' => true]) ?>
    <?= $ui->input('brand', 'Brand', $old['brand'], ['required' => true, 'maxlength' => 60]) ?>
    <?= $ui->input('model', 'Model', $old['model'], ['maxlength' => 80]) ?>
    <?= $ui->select('condition', 'Condition', Labels::CONDITIONS, $old['condition'], ['required' => true, 'data-wide' => true]) ?>
    <?= $ui->textarea('known_defects', 'Known defects', $old['known_defects'], ['maxlength' => 2000, 'data-wide' => true, 'rows' => 2]) ?>
    <?= $ui->input('cash_amount', 'Cash amount (PHP)', $amount, ['inputmode' => 'decimal', 'placeholder' => '0.00']) ?>
    <?= $ui->select('cash_payer', 'Who pays', ['none' => 'No cash', 'offerer' => 'Offerer pays owner', 'owner' => 'Owner pays offerer'], $old['cash_payer'], ['required' => true]) ?>
    <?= $ui->textarea('message', 'Message', $old['message'], ['required' => true, 'maxlength' => 2000, 'data-wide' => true, 'rows' => 3]) ?>
    <?php if ($allowPhotos): ?><?= $ui->photoInput('photos', 'Gadget photos', 6) ?><?php endif ?>
</div>
