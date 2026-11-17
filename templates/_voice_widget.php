<?php
/**
 * Voice note widget shared by the add and edit forms.
 * Expects: $voiceMaxSeconds, $voiceMaxBytes, $errors.
 */
?>
<label><?= $this->te('add.voice', ['seconds' => $voiceMaxSeconds]) ?></label>
<div id="voice-widget" class="voice-widget" data-max-seconds="<?= (int) $voiceMaxSeconds ?>" data-max-bytes="<?= (int) $voiceMaxBytes ?>">
    <button type="button" id="voice-record" class="button secondary" hidden>🎤 <span id="voice-record-label"><?= $this->te('js.voice_record') ?></span></button>
    <label class="button secondary voice-file-label">
        <?= $this->te('add.voice_file') ?>
        <input type="file" id="voice" name="voice" accept="audio/*" hidden>
    </label>
    <p id="voice-status" class="hint"></p>
    <audio id="voice-preview" controls hidden></audio>
    <button type="button" id="voice-clear" class="button secondary" hidden><?= $this->te('js.voice_clear') ?></button>
</div>
<?php if (isset($errors['voice'])): ?><p class="error"><?= $this->escape($errors['voice']) ?></p><?php endif; ?>
