<h1><?= $this->te('admin.login_title') ?></h1>

<?php if (!$configured): ?>
    <p class="notice"><?= $this->te('admin.not_configured') ?></p>
<?php else: ?>
    <?php if ($error !== null): ?>
        <p class="error"><?= $this->escape($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/admin/login">
        <label for="token"><?= $this->te('admin.token_label') ?></label>
        <input type="password" id="token" name="token" required autocomplete="current-password">
        <button type="submit" class="button"><?= $this->te('admin.login_button') ?></button>
    </form>
<?php endif; ?>
