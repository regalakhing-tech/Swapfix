<?php
$session = $ui->session;
echo $ui->heading('user', 'Profile', $session->isTechnician() ? 'Technician account' : 'Customer account', $session->isTechnician() ? $ui->link('/technician/profile', 'Public profile', 'shield', 'button button-outline') : '');
?>
<form method="post" action="/account" enctype="multipart/form-data" class="card form-card">
    <?= $ui->csrf() ?>
    <?= $ui->alert($error ?? null) ?>
    <div class="profile-photo-row">
        <?= $ui->avatar($old, 'avatar-large') ?>
        <div><strong><?= $e($old['display_name'] ?? '') ?></strong><small><?= $e($session->email) ?></small></div>
    </div>
    <div class="form-grid">
        <?= $ui->input('display_name', 'Name', $old['display_name'] ?? '', ['required' => true, 'minlength' => 2, 'maxlength' => 80, 'autocomplete' => 'name']) ?>
        <?= $ui->input('location', 'Location', $old['location'] ?? '', ['maxlength' => 120, 'autocomplete' => 'address-level2'], 'Kept private on your account.') ?>
        <?= $ui->photoInput('avatar', 'Profile photo', 1) ?>
        <?php if (!empty($old['avatar_path'])): ?><label class="check-row field-full"><input type="checkbox" name="remove_avatar" value="1">Remove current photo</label><?php endif ?>
        <?php if ($mailAvailable): ?><label class="check-row field-full"><input type="checkbox" name="email_notifications" value="1" <?= !empty($old['email_notifications']) ? 'checked' : '' ?>>Also email my notifications</label><?php endif ?>
    </div>
    <div class="form-actions"><button class="button button-primary" type="submit"><?= $ui->icon('check') ?>Save profile</button></div>
</form>
