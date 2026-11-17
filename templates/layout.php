<!DOCTYPE html>
<html lang="<?= $this->escape($this->language()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->escape($title) ?> · Buzlook</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<header class="app-header">
    <a class="brand" href="/">❄️ Buzlook</a>
    <button type="button" id="scan-open" class="scan-button" hidden>📷 <?= $this->te('app.scan_label') ?></button>
</header>
<main class="container">
    <?= $content ?>
</main>

<div id="scan-overlay" class="scan-overlay" hidden>
    <div class="scan-panel">
        <video id="scan-video" playsinline muted></video>
        <p id="scan-status" class="scan-status"><?= $this->te('js.scan_prompt') ?></p>
        <button type="button" id="scan-switch-camera" class="button secondary" hidden>🔄 <?= $this->te('scan.switch_camera') ?></button>
        <label class="button scan-photo" id="scan-photo-label" hidden>
            <?= $this->te('scan.photo_button') ?>
            <input type="file" id="scan-photo" accept="image/*" hidden>
        </label>
        <button type="button" id="scan-close" class="button secondary"><?= $this->te('scan.close') ?></button>
    </div>
</div>

<script>
    window.BUZLOOK_I18N = <?= $this->jsMessages() ?>;
    window.t = function (key, params) {
        var text = window.BUZLOOK_I18N[key] || key;
        Object.keys(params || {}).forEach(function (name) { text = text.split('{' + name + '}').join(params[name]); });
        return text;
    };
</script>
<script src="/assets/vendor/jsQR.js"></script>
<script src="/assets/js/scanner.js"></script>
</body>
</html>
