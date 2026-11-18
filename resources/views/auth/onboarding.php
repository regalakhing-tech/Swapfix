<?php $ui->title = 'Finish your account'; ?>
<h1>Finish your account</h1>
<p class="auth-lead">One last step before you start.</p>
<?= $ui->alert($error ?? null) ?>
<form method="post" action="/onboarding" class="stack-form">
    <?= $ui->csrf() ?>
    <?php require __DIR__ . '/../partials/account-type.php' ?>
    <?= $ui->input('display_name', 'Name', $old['display_name'] ?? '', ['required' => true, 'minlength' => 2, 'maxlength' => 80, 'autocomplete' => 'name']) ?>
    <?= $ui->input('location', 'Location', $old['location'] ?? '', ['maxlength' => 120, 'autocomplete' => 'address-level2']) ?>
    <button class="button button-primary button-block" type="submit">Continue <?= $ui->icon('arrow') ?></button>
</form>
<?= $ui->postButton('/logout', 'Use another account', '', 'text-button') ?>
