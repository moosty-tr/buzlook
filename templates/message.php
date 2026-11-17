<h1><?= $this->escape($title) ?></h1>
<p class="notice"><?= $this->escape($message) ?></p>
<?php if (!empty($back_url)): ?>
    <p><a class="button secondary" href="<?= $this->escape($back_url) ?>"><?= $this->escape($back_label ?? 'Back') ?></a></p>
<?php endif; ?>
