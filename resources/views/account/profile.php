<?php
$session = $ui->session;
$isTechnician = $session->isTechnician();
$location = (string) ($old['location'] ?? '');
echo $ui->heading('user', 'Profile', $isTechnician ? 'Technician account' : 'Customer account', $isTechnician ? $ui->link('/technician/profile', 'Public profile', 'shield', 'button button-outline') : '');
?>
<div class="profile-layout">
    <aside class="card profile-card">
        <?= $ui->avatar($old, 'avatar-xl') ?>
        <h2><?= $e($old['display_name'] ?? '') ?></h2>
        <p class="profile-email"><?= $e($session->email) ?></p>
        <span class="tag"><?= $ui->icon($isTechnician ? 'wrench' : 'user') ?><?= $isTechnician ? 'Technician' : 'Customer' ?></span>
        <p class="item-location"><?= $ui->icon('pin') ?><?= $e($location !== '' ? $location : 'No location added') ?></p>
        <div class="profile-links">
            <?= $ui->link('/activity', 'My activity', 'activity', 'button button-outline button-block') ?>
            <?php if ($isTechnician): ?><?= $ui->link('/technicians/' . $session->id, 'View public page', 'eye', 'button button-outline button-block') ?><?php endif ?>
        </div>
    </aside>
    <form method="post" action="/account" enctype="multipart/form-data" class="card profile-form">
        <?= $ui->csrf() ?>
        <?= $ui->alert($error ?? null) ?>
        <section>
            <h2 class="section-title"><?= $ui->icon('user') ?>Personal details</h2>
            <div class="form-grid">
                <?= $ui->input('display_name', 'Name', $old['display_name'] ?? '', ['required' => true, 'minlength' => 2, 'maxlength' => 80, 'autocomplete' => 'name']) ?>
                <?= $ui->input('location', 'Location', $location, ['maxlength' => 120, 'autocomplete' => 'address-level2'], 'Kept private on your account.') ?>
            </div>
        </section>
        <section>
            <h2 class="section-title"><?= $ui->icon('camera') ?>Profile photo</h2>
            <div class="form-grid">
                <?= $ui->photoInput('avatar', 'New photo', 1) ?>
                <?php if (!empty($old['avatar_path'])): ?><label class="check-row field-full"><input type="checkbox" name="remove_avatar" value="1">Remove current photo</label><?php endif ?>
            </div>
        </section>
        <?php if ($mailAvailable): ?>
            <section>
                <h2 class="section-title"><?= $ui->icon('bell') ?>Notifications</h2>
                <label class="check-row"><input type="checkbox" name="email_notifications" value="1" <?= !empty($old['email_notifications']) ? 'checked' : '' ?>>Also email my notifications</label>
            </section>
        <?php endif ?>
        <div class="form-actions"><button class="button button-primary" type="submit"><?= $ui->icon('check') ?>Save profile</button></div>
    </form>
</div>
