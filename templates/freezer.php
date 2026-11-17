<h1><?= $this->escape($freezer['name']) ?></h1>

<?php if ($items === []): ?>
    <p class="notice"><?= $this->te('freezer.empty') ?></p>
<?php else: ?>
    <input type="search" id="item-search" class="search-box" autocomplete="off"
           placeholder="<?= $this->te('freezer.search_placeholder') ?>"
           aria-label="<?= $this->te('freezer.search_label') ?>">
    <p id="item-no-matches" class="notice" hidden><?= $this->te('freezer.no_matches') ?></p>

    <ul class="item-list" id="item-list">
        <?php foreach ($items as $item): ?>
            <li class="item-entry" data-search="<?= $this->escape(trim($item['name'] . ' ' . $item['tag_code'] . ' ' . ($item['quantity'] ?? '') . ' ' . ($item['note'] ?? ''))) ?>">
                <div class="item-card">
                    <?php if ($item['photo_path'] !== null): ?>
                        <img src="<?= $this->escape($uploadsUrl . '/' . $item['photo_path']) ?>" alt="<?= $this->escape($item['name']) ?>" loading="lazy">
                    <?php endif; ?>
                    <div>
                        <strong><?= $this->escape($item['name']) ?></strong><br>
                        <small><?= $this->te('freezer.frozen', ['date' => $item['frozen_date']]) ?></small><br>
                        <?php if (!empty($item['quantity'])): ?>
                            <small><?= $this->te('item.quantity', ['value' => $item['quantity']]) ?></small><br>
                        <?php endif; ?>
                        <span class="code-badge" title="<?= $this->te('freezer.label_code') ?>"><?= $this->escape($item['tag_code']) ?></span>
                        <a class="edit-link" href="/<?= $this->escape($item['tag_code']) ?>/edit"><?= $this->te('common.edit') ?></a>
                    </div>
                </div>
                <?php if (!empty($item['note'])): ?>
                    <p class="item-note"><?= nl2br($this->escape($item['note'])) ?></p>
                <?php endif; ?>
                <?php if (!empty($item['voice_path'])): ?>
                    <audio class="item-voice" controls preload="none" src="<?= $this->escape($uploadsUrl . '/' . $item['voice_path']) ?>"></audio>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <script src="/assets/js/freezer-search.js"></script>
<?php endif; ?>

<section class="export-section">
    <button type="button" id="export-labels" class="button secondary" data-url="/export/labels" data-prefix="buzlook-labels-<?= $this->escape($freezer['code']) ?>"><?= $this->te('freezer.export_button') ?></button>
    <p class="hint"><?= $this->te('freezer.export_hint') ?></p>
    <p id="export-status" class="hint" role="status"></p>
</section>

<form method="post" action="/logout" class="logout-form">
    <p class="hint"><?= $this->te('freezer.logout_hint') ?></p>
    <button type="submit" class="button secondary"><?= $this->te('common.log_out') ?></button>
</form>

<script src="/assets/vendor/qrcode.js"></script>
<script src="/assets/js/export-labels.js"></script>
