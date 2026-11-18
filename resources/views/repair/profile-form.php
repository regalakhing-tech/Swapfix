<?php
echo $ui->heading('shield', 'Public profile', 'What customers see', $ui->link('/technicians/' . $ui->session->id, 'View profile', 'eye', 'button button-outline'));
$list = static fn (string $key): string => implode(', ', $old[$key] ?? []);
?>
<form method="post" action="/technician/profile" class="card form-card">
    <?= $ui->csrf() ?>
    <?= $ui->alert($error ?? null) ?>
    <div class="form-grid">
        <?= $ui->input('headline', 'Headline', $old['headline'] ?? '', ['maxlength' => 120, 'data-wide' => true, 'placeholder' => 'Phone and laptop repair specialist']) ?>
        <?= $ui->input('location', 'Location', $old['location'] ?? '', ['required' => true, 'minlength' => 2, 'maxlength' => 120]) ?>
        <?= $ui->input('service_area', 'Service area', $old['service_area'] ?? '', ['required' => true, 'minlength' => 2, 'maxlength' => 200]) ?>
        <?= $ui->input('available_hours', 'Available hours', $old['available_hours'] ?? '', ['required' => true, 'minlength' => 2, 'maxlength' => 200, 'data-wide' => true, 'placeholder' => 'Mon to Sat, 9 AM to 6 PM']) ?>
        <?= $ui->input('specialties', 'Specialties', $list('specialties'), ['required' => true, 'data-wide' => true], 'Separate with commas. You appear in search after adding one.') ?>
        <?= $ui->input('supported_brands', 'Supported brands', $list('supported_brands'), ['data-wide' => true], 'Separate with commas.') ?>
        <?= $ui->input('supported_models', 'Supported models', $list('supported_models'), ['data-wide' => true], 'Separate with commas.') ?>
        <?= $ui->textarea('bio', 'About', $old['bio'] ?? '', ['maxlength' => 2000, 'data-wide' => true]) ?>
    </div>
    <div class="form-actions"><button class="button button-primary" type="submit"><?= $ui->icon('check') ?>Save profile</button></div>
</form>
