<h1><?= $this->te('consume.title') ?></h1>

<div class="item-card">
    <?php if ($item['photo_path'] !== null): ?>
        <img src="<?= $this->escape($uploadsUrl . '/' . $item['photo_path']) ?>" alt="<?= $this->escape($item['name']) ?>">
    <?php endif; ?>
    <div>
        <strong><?= $this->escape($item['name']) ?></strong><br>
        <small><?= $this->te('freezer.frozen', ['date' => $item['frozen_date']]) ?></small><br>
        <?php if (!empty($item['quantity'])): ?>
            <small><?= $this->te('item.quantity', ['value' => $item['quantity']]) ?></small><br>
        <?php endif; ?>
        <span class="code-badge" title="<?= $this->te('freezer.label_code') ?>"><?= $this->escape($tag['code']) ?></span>
    </div>
</div>
<?php if (!empty($item['note'])): ?>
    <p class="item-note"><?= nl2br($this->escape($item['note'])) ?></p>
<?php endif; ?>
<?php if (!empty($item['voice_path'])): ?>
    <audio class="item-voice" controls preload="none" src="<?= $this->escape($uploadsUrl . '/' . $item['voice_path']) ?>"></audio>
<?php endif; ?>

<form method="post" action="/<?= $this->escape($tag['code']) ?>/consume">
    <button type="submit" class="button"><?= $this->te('consume.submit') ?></button>
</form>

<p><a class="button secondary" href="/<?= $this->escape($tag['code']) ?>/edit"><?= $this->te('consume.edit') ?></a></p>
<p><a class="button secondary" href="/<?= $this->escape($freezer['code']) ?>"><?= $this->te('common.cancel_to_freezer') ?></a></p>
