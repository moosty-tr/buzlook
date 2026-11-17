<h1>Buzlook</h1>
<?php if ($freezer !== null): ?>
    <p><?= $this->te('home.logged_in_to') ?> <strong><?= $this->escape($freezer['name']) ?></strong></p>
    <p><a class="button" href="/<?= $this->escape($freezer['code']) ?>"><?= $this->te('home.show_items') ?></a></p>
    <form method="post" action="/logout">
        <button type="submit" class="button secondary"><?= $this->te('common.log_out') ?></button>
    </form>
<?php else: ?>
    <p class="notice"><?= $this->te('home.scan_prompt') ?></p>
<?php endif; ?>
