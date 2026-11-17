<h1><?= $this->te('add.title') ?></h1>
<p class="hint"><?= $this->te('add.freezer') ?> <strong><?= $this->escape($freezer['name']) ?></strong> &middot; <?= $this->te('add.label') ?> <span class="code-badge"><?= $this->escape($tag['code']) ?></span></p>

<form id="add-item-form" method="post" action="/<?= $this->escape($tag['code']) ?>/add" enctype="multipart/form-data">
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
    <?php require __DIR__ . '/_photo_widget.php'; ?>
    <p id="photo-status" class="hint"></p>
    <?php if (isset($errors['photo'])): ?><p class="error"><?= $this->escape($errors['photo']) ?></p><?php endif; ?>

    <?php require __DIR__ . '/_voice_widget.php'; ?>

    <button type="submit" class="button"><?= $this->te('add.save') ?></button>
</form>

<p><a class="button secondary" href="/<?= $this->escape($freezer['code']) ?>"><?= $this->te('common.cancel_to_freezer') ?></a></p>

<script src="/assets/js/image-compressor.js"></script>
<script src="/assets/js/add-item-form.js"></script>
<script src="/assets/js/voice-recorder.js"></script>
