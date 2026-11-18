<?php
use SwapFix\Support\Labels;

$person = $technician['profile'];
echo $ui->heading('wrench', 'Request a repair', 'To ' . $person['display_name'], $ui->link('/technicians/' . $technician['user_id'], 'Back', 'arrow-left', 'button button-outline'));
$serviceOptions = ['' => 'Not sure yet'];
foreach ($services as $service) {
    $serviceOptions[$service['id']] = $service['name'];
}
?>
<form method="post" action="/repair/request/<?= $e($technician['user_id']) ?>" enctype="multipart/form-data" class="card form-card">
    <?= $ui->csrf() ?>
    <?= $ui->alert($error ?? null) ?>
    <div class="form-grid">
        <?= $ui->select('service_id', 'Repair service', $serviceOptions, $old['service_id'], ['data-wide' => true]) ?>
        <?= $ui->select('device_type', 'Device type', Labels::DEVICE_TYPES, $old['device_type'], ['required' => true, 'data-wide' => true]) ?>
        <?= $ui->input('device_brand', 'Brand', $old['device_brand'], ['required' => true, 'maxlength' => 60]) ?>
        <?= $ui->input('device_model', 'Model', $old['device_model'], ['required' => true, 'maxlength' => 80]) ?>
        <?= $ui->textarea('problem', 'Problem', $old['problem'], ['required' => true, 'minlength' => 5, 'maxlength' => 4000, 'data-wide' => true], 'Describe the issue and any symptoms.') ?>
        <?= $ui->photoInput('photos', 'Photos', 6) ?>
    </div>
    <p class="field-hint"><?= $ui->icon('shield') ?> Photos stay private to you and this technician. No work starts until you approve a quotation.</p>
    <div class="form-actions"><button class="button button-primary" type="submit"><?= $ui->icon('send') ?>Send request</button></div>
</form>
