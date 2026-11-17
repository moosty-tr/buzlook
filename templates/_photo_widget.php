<?php
/**
 * Photo input + live camera widget shared by the add and edit forms.
 */
?>
<div id="photo-widget" class="photo-widget">
    <img id="photo-preview" class="current-photo" alt="" hidden>
    <div id="photo-camera-box" class="photo-camera-box" hidden>
        <video id="photo-video" playsinline muted></video>
        <div class="photo-camera-actions">
            <button type="button" id="photo-capture" class="button">📸 <?= $this->te('photo.capture_button') ?></button>
            <button type="button" id="photo-switch-camera" class="button secondary">🔄 <?= $this->te('scan.switch_camera') ?></button>
            <button type="button" id="photo-camera-close" class="button secondary"><?= $this->te('scan.close') ?></button>
        </div>
    </div>
    <button type="button" id="photo-camera-open" class="button secondary" hidden>📷 <?= $this->te('photo.take_button') ?></button>
    <input type="file" id="photo" name="photo" accept="image/*">
    <button type="button" id="photo-clear" class="button secondary" hidden><?= $this->te('photo.clear_button') ?></button>
</div>
