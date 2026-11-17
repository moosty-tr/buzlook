<h1><?= $this->te('edit.title') ?></h1>
<p class="hint"><?= $this->te('add.freezer') ?> <strong><?= $this->escape($freezer['name']) ?></strong> &middot; <?= $this->te('add.label') ?> <span class="code-badge"><?= $this->escape($tag['code']) ?></span></p>

<form id="add-item-form" method="post" action="/<?= $this->escape($tag['code']) ?>/edit" enctype="multipart/form-data">
    <label for="name"><?= $this->te('add.food_name') ?></label>
    <input type="text" id="name" name="name" maxlength="100" required value="<?= $this->escape($input['name']) ?>">
    <?php if (isset($errors['name'])): ?><p class="error"><?= $this->escape($errors['name']) ?></p><?php endif; ?>

    <label for="frozen_date"><?= $this->te('add.date') ?></label>
    <input type="date" id="frozen_date" name="frozen_date" required value="<?= $this->escape($input['frozen_date']) ?>">
    <?php if (isset($errors['frozen_date'])): ?><p class="error"><?= $this->escape($errors['frozen_date']) ?></p><?php endif; ?>

    <label for="quantity"><?= $this->te('add.quantity') ?></label>
    <input type="text" id="quantity" name="quantity" maxlength="50" placeholder="<?= $this->te('add.quantity_placeholder') ?>" value="<?= $this->escape($input['quantity']) ?>">
    <?php if (isset($errors['quantity'])): ?><p class="error"><?= $this->escape($errors['quantity']) ?></p><?php endif; ?>

    <label for="note"><?= $this->te('add.note') ?></label>
    <textarea id="note" name="note" rows="3" maxlength="500"><?= $this->escape($input['note']) ?></textarea>
    <?php if (isset($errors['note'])): ?><p class="error"><?= $this->escape($errors['note']) ?></p><?php endif; ?>

    <label for="photo"><?= $this->te('add.photo') ?></label>
    <?php if ($item['photo_path'] !== null): ?>
        <img class="current-photo" src="<?= $this->escape($uploadsUrl . '/' . $item['photo_path']) ?>" alt="<?= $this->escape($item['name']) ?>">
        <label class="checkbox"><input type="checkbox" name="remove_photo" value="1"> <?= $this->te('edit.photo_remove') ?></label>
    <?php endif; ?>
    <?php require __DIR__ . '/_photo_widget.php'; ?>
    <p id="photo-status" class="hint"><?= $this->te('edit.photo_hint') ?></p>
    <?php if (isset($errors['photo'])): ?><p class="error"><?= $this->escape($errors['photo']) ?></p><?php endif; ?>

    <?php if ($item['voice_path'] !== null): ?>
        <label><?= $this->te('edit.voice_current') ?></label>
        <audio controls preload="none" src="<?= $this->escape($uploadsUrl . '/' . $item['voice_path']) ?>"></audio>
        <label class="checkbox"><input type="checkbox" name="remove_voice" value="1"> <?= $this->te('edit.voice_remove') ?></label>
        <p class="hint"><?= $this->te('edit.voice_replace_hint') ?></p>
    <?php endif; ?>
    <?php require __DIR__ . '/_voice_widget.php'; ?>

    <label for="new-code"><?= $this->te('edit.new_label') ?></label>
    <div class="inline-field">
        <input type="text" id="new-code" name="new_code" autocomplete="off" autocapitalize="off" spellcheck="false"
               placeholder="<?= $this->te('edit.new_label_placeholder') ?>" value="<?= $this->escape($input['new_code']) ?>">
        <button type="button" class="button secondary" data-scan-into="new-code" hidden>📷 <?= $this->te('edit.scan_button') ?></button>
    </div>
    <p class="hint"><?= $this->te('edit.new_label_hint') ?></p>
    <?php if (isset($errors['new_code'])): ?><p class="error"><?= $this->escape($errors['new_code']) ?></p><?php endif; ?>

    <button type="submit" class="button"><?= $this->te('edit.save') ?></button>
</form>

<p><a class="button secondary" href="/<?= $this->escape($tag['code']) ?>"><?= $this->te('edit.cancel') ?></a></p>

<script src="/assets/js/image-compressor.js"></script>
<script src="/assets/js/add-item-form.js"></script>
<script src="/assets/js/voice-recorder.js"></script>
