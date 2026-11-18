<?php
echo $ui->heading('settings', 'Services', count($services) . ' published or hidden', $ui->dialogLink('service-dialog', 'add', 'Add service', 'plus'));
$form = $old ?? ['id' => null, 'name' => '', 'description' => '', 'supported_brands' => [], 'supported_models' => [], 'estimated_price' => ''];
$editId = $ui->query['edit'] ?? null;
foreach ($services as $service) {
    if (!isset($old) && $service['id'] === $editId) {
        $form = $service;
    }
}
$dialogParam = !empty($form['id']) ? 'edit' : 'add';
$list = static fn (array $items): string => implode(', ', $items);
?>
<?= $ui->alert($formError ?? null) ?>
<?php if ($services === []): ?>
    <?= $ui->emptyState('settings', 'Publish your first service.', $ui->dialogLink('service-dialog', 'add', 'Add service', 'plus')) ?>
<?php else: ?>
    <div class="row-list card">
        <?php foreach ($services as $service): ?>
            <div class="list-row static service-row">
                <span><strong><?= $e($service['name']) ?></strong><small><?= $ui->money($service['estimated_price']) ?><?php if ($service['supported_brands'] !== []): ?> &middot; <?= $e($list($service['supported_brands'])) ?><?php endif ?></small></span>
                <?= $service['is_active'] ? '<span class="status status-green">Published</span>' : '<span class="status status-muted">Hidden</span>' ?>
                <span class="row-actions">
                    <?= $ui->link($ui->url(['edit' => $service['id'], 'add' => null]), 'Edit', 'edit', 'button button-outline button-small') ?>
                    <?= $ui->postButton('/technician/services/' . $service['id'] . '/active', $service['is_active'] ? 'Hide' : 'Publish', $service['is_active'] ? 'close' : 'check', 'button button-outline button-small', ['active' => $service['is_active'] ? '0' : '1']) ?>
                </span>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>
<?= $ui->dialogStart('service-dialog', !empty($form['id']) ? 'Edit service' : 'Add service', $dialogParam) ?>
<form method="post" action="/technician/services<?= !empty($form['id']) ? '/' . $e($form['id']) : '' ?>" class="stack-form">
    <?= $ui->csrf() ?>
    <?= $ui->alert($formError ?? null) ?>
    <?= $ui->input('name', 'Service name', $form['name'], ['required' => true, 'minlength' => 3, 'maxlength' => 120, 'placeholder' => 'Screen replacement']) ?>
    <?= $ui->input('estimated_price', 'Estimated price (PHP)', $form['estimated_price'], ['required' => true, 'inputmode' => 'decimal']) ?>
    <?= $ui->input('supported_brands', 'Supported brands', $list($form['supported_brands']), [], 'Separate with commas.') ?>
    <?= $ui->input('supported_models', 'Supported models', $list($form['supported_models']), [], 'Separate with commas.') ?>
    <?= $ui->textarea('description', 'Description', $form['description'], ['maxlength' => 2000, 'rows' => 3]) ?>
    <button class="button button-primary button-block" type="submit"><?= $ui->icon('check') ?>Save service</button>
</form>
<?= $ui->dialogEnd() ?>
