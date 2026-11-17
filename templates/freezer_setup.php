<h1><?= $this->te('setup.title') ?></h1>
<p class="notice"><?= $this->te('setup.intro') ?></p>

<form method="post" action="/<?= $this->escape($tag['code']) ?>/freezer">
    <label for="name"><?= $this->te('setup.name_label') ?></label>
    <input type="text" id="name" name="name" maxlength="100" required value="<?= $this->escape($input['name']) ?>">
    <?php if (isset($errors['name'])): ?><p class="error"><?= $this->escape($errors['name']) ?></p><?php endif; ?>

    <button type="submit" class="button"><?= $this->te('setup.submit') ?></button>
</form>
