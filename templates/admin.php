<?php
/**
 * Formats byte counts into human-readable KB / MB strings.
 */
$formatBytes = static function (int $bytes): string {
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 2) . ' MB';
    }
    return number_format(max(0, $bytes) / 1024, 1) . ' KB';
};
?>
<div class="admin-page">
    <h1><?= $this->te('admin.title') ?></h1>

    <?php if ($flash === 'updated'): ?>
        <p class="notice"><?= $this->te('admin.flash_updated') ?></p>
    <?php elseif ($flash === 'purged'): ?>
        <p class="notice"><?= $this->te('admin.flash_purged', ['count' => $flashCount]) ?></p>
    <?php elseif ($flash === 'orphans_cleaned'): ?>
        <p class="notice"><?= $this->te('admin.flash_orphans', ['count' => $flashCount]) ?></p>
    <?php elseif ($flash === 'rate_limits_cleared'): ?>
        <p class="notice"><?= $this->te('admin.flash_rate_limits', ['count' => $flashCount]) ?></p>
    <?php endif; ?>

    <!-- 1. Overview & Storage -->
    <section class="admin-card">
        <h2><?= $this->te('admin.stats_heading') ?></h2>
        <div class="stat-grid">
            <div class="stat-box">
                <span class="stat-value"><?= (int) $stats['total_enabled'] ?></span>
                <span class="stat-label"><?= $this->te('admin.stat_enabled') ?></span>
            </div>
            <div class="stat-box">
                <span class="stat-value"><?= (int) $stats['untyped'] ?></span>
                <span class="stat-label"><?= $this->te('admin.stat_untyped') ?></span>
            </div>
            <div class="stat-box">
                <span class="stat-value"><?= (int) $stats['freezers'] ?></span>
                <span class="stat-label"><?= $this->te('admin.stat_freezers') ?></span>
            </div>
            <div class="stat-box">
                <span class="stat-value"><?= (int) $stats['food'] ?></span>
                <span class="stat-label"><?= $this->te('admin.stat_food') ?></span>
            </div>
            <div class="stat-box">
                <span class="stat-value"><?= (int) $stats['disabled'] ?></span>
                <span class="stat-label"><?= $this->te('admin.stat_disabled') ?></span>
            </div>
            <div class="stat-box">
                <span class="stat-value"><?= (int) $stats['active_items'] ?></span>
                <span class="stat-label"><?= $this->te('admin.stat_active_items') ?></span>
            </div>
            <div class="stat-box">
                <span class="stat-value"><?= (int) $stats['consumed_items'] ?></span>
                <span class="stat-label"><?= $this->te('admin.stat_consumed_items') ?></span>
            </div>
            <div class="stat-box">
                <span class="stat-value"><?= $this->escape($formatBytes((int) $storage['uploads_bytes'])) ?></span>
                <span class="stat-label"><?= $this->te('admin.stat_uploads', ['files' => (int) $storage['uploads_files']]) ?></span>
            </div>
            <div class="stat-box">
                <span class="stat-value"><?= $this->escape($formatBytes((int) $storage['db_bytes'])) ?></span>
                <span class="stat-label"><?= $this->te('admin.stat_db_size') ?></span>
            </div>
        </div>

        <p><a class="button secondary" href="/admin/backup/database"><?= $this->te('admin.backup_button') ?></a></p>

        <?php if ($storage['orphan_files'] > 0): ?>
            <form method="post" action="/admin/maintenance/clean-orphans">
                <input type="hidden" name="csrf_token" value="<?= $this->escape($csrfToken) ?>">
                <button type="submit" class="button secondary"><?= $this->te('admin.clean_orphans_button', ['count' => (int) $storage['orphan_files']]) ?></button>
            </form>
        <?php endif; ?>
    </section>

    <!-- 2. Batch Label Generator & Downloads (ZIP of PNGs, A4 PDF Grid, 2-Column CSV) -->
    <section class="admin-card">
        <h2><?= $this->te('admin.batch_heading') ?></h2>
        <form id="admin-batch-form" data-url="/admin/tags/batch">
            <input type="hidden" name="csrf_token" value="<?= $this->escape($csrfToken) ?>">

            <div class="form-row">
                <div>
                    <label for="batch-count"><?= $this->te('admin.batch_count') ?></label>
                    <input type="number" id="batch-count" name="count" min="1" max="<?= (int) $maxBatch ?>" value="32" required>
                </div>
                <div>
                    <label for="batch-length"><?= $this->te('admin.batch_length') ?></label>
                    <input type="number" id="batch-length" name="length" min="4" max="64" value="<?= (int) $defaultLength ?>" required>
                </div>
            </div>

            <label for="batch-mode"><?= $this->te('admin.batch_mode') ?></label>
            <select id="batch-mode" name="mode">
                <option value="register"><?= $this->te('admin.batch_mode_register') ?></option>
                <option value="unregistered"><?= $this->te('admin.batch_mode_unregistered') ?></option>
            </select>

            <button type="submit" id="batch-generate-btn" class="button"><?= $this->te('admin.batch_generate_button') ?></button>
        </form>

        <p id="batch-status" class="hint" role="status"></p>

        <div id="batch-downloads" class="batch-downloads" hidden>
            <div class="download-actions">
                <button type="button" id="download-zip" class="button"><?= $this->te('admin.batch_download_zip') ?></button>
                <button type="button" id="download-pdf" class="button"><?= $this->te('admin.batch_download_pdf') ?></button>
                <button type="button" id="download-csv" class="button secondary"><?= $this->te('admin.batch_download_csv') ?></button>
            </div>

            <fieldset class="pdf-fieldset">
                <legend><?= $this->te('admin.pdf_settings') ?></legend>
                <div class="form-row">
                    <div>
                        <label for="pdf-cols"><?= $this->te('admin.pdf_cols') ?></label>
                        <input type="number" id="pdf-cols" min="1" max="20" value="8">
                    </div>
                    <div>
                        <label for="pdf-rows"><?= $this->te('admin.pdf_rows') ?></label>
                        <input type="number" id="pdf-rows" min="1" max="30" value="4">
                    </div>
                </div>
                <div class="form-row">
                    <div>
                        <label for="pdf-orientation"><?= $this->te('admin.pdf_orientation') ?></label>
                        <select id="pdf-orientation">
                            <option value="landscape" selected><?= $this->te('admin.pdf_landscape') ?></option>
                            <option value="portrait"><?= $this->te('admin.pdf_portrait') ?></option>
                        </select>
                    </div>
                    <div>
                        <label for="pdf-margin"><?= $this->te('admin.pdf_margin') ?></label>
                        <input type="number" id="pdf-margin" min="0" max="40" value="8">
                    </div>
                </div>
                <label class="checkbox">
                    <input type="checkbox" id="pdf-grid-lines" value="1">
                    <?= $this->te('admin.pdf_grid_lines') ?>
                </label>
            </fieldset>
        </div>
    </section>

    <!-- 3. Bulk Register / Unregister (Paste or CSV Upload) -->
    <section class="admin-card">
        <h2><?= $this->te('admin.bulk_heading') ?></h2>
        <p class="hint"><?= $this->te('admin.bulk_hint') ?></p>

        <form id="admin-bulk-form" data-url="/admin/tags/bulk" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $this->escape($csrfToken) ?>">
            <label for="bulk-codes"><?= $this->te('admin.bulk_codes_label') ?></label>
            <textarea id="bulk-codes" name="codes_text" rows="4" placeholder="7Kq9Xz&#10;https://host/Ab3Def"></textarea>

            <label for="bulk-csv"><?= $this->te('admin.bulk_csv_label') ?></label>
            <input type="file" id="bulk-csv" name="csv_file" accept=".csv,.txt,text/csv,text/plain">

            <div class="download-actions">
                <button type="button" data-bulk-action="register" class="button"><?= $this->te('admin.bulk_register_button') ?></button>
                <button type="button" data-bulk-action="unregister" class="button secondary"><?= $this->te('admin.bulk_unregister_button') ?></button>
            </div>
        </form>
        <p id="bulk-status" class="hint" role="status"></p>
    </section>

    <!-- 4. Single Label Inspector & Reset Tool -->
    <section class="admin-card">
        <h2><?= $this->te('admin.inspect_heading') ?></h2>
        <form method="get" action="/admin">
            <div class="inline-field">
                <input type="text" id="inspect-code" name="inspect" autocomplete="off" autocapitalize="off" spellcheck="false"
                       placeholder="<?= $this->te('admin.inspect_placeholder') ?>" value="<?= $this->escape($inspectCode) ?>">
                <button type="button" class="button secondary" data-scan-into="inspect-code" hidden>📷 <?= $this->te('edit.scan_button') ?></button>
                <button type="submit" class="button"><?= $this->te('admin.inspect_button') ?></button>
            </div>
        </form>

        <?php if ($inspectCode !== ''): ?>
            <?php if ($inspectedTag === null): ?>
                <p class="notice"><?= $this->te('admin.inspect_not_found', ['code' => $inspectCode]) ?></p>
                <form method="post" action="/admin/tags/inspect-action">
                    <input type="hidden" name="csrf_token" value="<?= $this->escape($csrfToken) ?>">
                    <input type="hidden" name="code" value="<?= $this->escape($inspectCode) ?>">
                    <input type="hidden" name="operation" value="register">
                    <button type="submit" class="button"><?= $this->te('admin.inspect_register_now', ['code' => $inspectCode]) ?></button>
                </form>
            <?php else: ?>
                <dl class="inspect-details">
                    <dt><?= $this->te('freezer.label_code') ?></dt>
                    <dd><span class="code-badge"><?= $this->escape($inspectedTag['code']) ?></span> <small><a href="/<?= $this->escape($inspectedTag['code']) ?>"><?= $this->escape($inspectedUrl) ?></a></small></dd>

                    <dt><?= $this->te('admin.inspect_status') ?></dt>
                    <dd><?= (int) $inspectedTag['enabled'] === 1 ? $this->te('admin.inspect_enabled') : $this->te('admin.inspect_disabled') ?></dd>

                    <dt><?= $this->te('admin.inspect_type') ?></dt>
                    <dd>
                        <?php if ($inspectedTag['type'] === null): ?>
                            <?= $this->te('admin.inspect_type_untyped') ?>
                        <?php elseif ($inspectedTag['type'] === 'freezer'): ?>
                            <?= $this->te('admin.inspect_type_freezer', [
                                'name' => (string) $inspectedTag['name'],
                                'food' => (int) $inspectedTag['food_tag_count'],
                                'active' => (int) $inspectedTag['active_item_count'],
                                'consumed' => (int) $inspectedTag['consumed_item_count'],
                            ]) ?>
                        <?php else: ?>
                            <?= $this->te('admin.inspect_type_food', [
                                'freezer' => ($inspectedTag['freezer']['name'] ?? '?') . ' (' . ($inspectedTag['freezer']['code'] ?? '?') . ')',
                            ]) ?>
                        <?php endif; ?>
                    </dd>

                    <?php if (!empty($inspectedTag['active_item'])): ?>
                        <dt><?= $this->te('admin.inspect_active_item') ?></dt>
                        <dd><?= $this->escape($inspectedTag['active_item']['name']) ?> (<?= $this->escape($inspectedTag['active_item']['frozen_date']) ?>)</dd>
                    <?php endif; ?>

                    <dt><?= $this->te('admin.inspect_created') ?></dt>
                    <dd><?= $this->escape((string) $inspectedTag['created_at']) ?></dd>

                    <dt><?= $this->te('admin.inspect_last_scan') ?></dt>
                    <dd><?= $inspectedTag['last_scan_at'] !== null ? $this->escape((string) $inspectedTag['last_scan_at']) : $this->te('admin.inspect_never') ?></dd>
                </dl>

                <div class="download-actions">
                    <form method="post" action="/admin/tags/inspect-action">
                        <input type="hidden" name="csrf_token" value="<?= $this->escape($csrfToken) ?>">
                        <input type="hidden" name="code" value="<?= $this->escape($inspectedTag['code']) ?>">
                        <?php if ((int) $inspectedTag['enabled'] === 1): ?>
                            <input type="hidden" name="operation" value="disable">
                            <button type="submit" class="button secondary"><?= $this->te('admin.inspect_disable_btn') ?></button>
                        <?php else: ?>
                            <input type="hidden" name="operation" value="enable">
                            <button type="submit" class="button"><?= $this->te('admin.inspect_enable_btn') ?></button>
                        <?php endif; ?>
                    </form>

                    <?php if ($inspectedTag['type'] !== null): ?>
                        <form method="post" action="/admin/tags/inspect-action" onsubmit="return confirm(<?= $this->escape(json_encode($this->t('admin.inspect_reset_confirm'))) ?>);">
                            <input type="hidden" name="csrf_token" value="<?= $this->escape($csrfToken) ?>">
                            <input type="hidden" name="code" value="<?= $this->escape($inspectedTag['code']) ?>">
                            <input type="hidden" name="operation" value="reset">
                            <button type="submit" class="button secondary"><?= $this->te('admin.inspect_reset_btn') ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <!-- 5. Inactivity Report (Last N Days, default N = 730) & Purge -->
    <section class="admin-card">
        <h2><?= $this->te('admin.inactive_heading') ?></h2>
        <form method="get" action="/admin">
            <label for="inactive-days"><?= $this->te('admin.inactive_days_label') ?></label>
            <div class="inline-field">
                <input type="number" id="inactive-days" name="days" min="1" max="36500" value="<?= (int) $inactivity['days'] ?>" required>
                <button type="submit" class="button"><?= $this->te('admin.inactive_check_button') ?></button>
            </div>
        </form>

        <div class="stat-grid" style="margin-top: 1rem;">
            <div class="stat-box">
                <span class="stat-value"><?= (int) $inactivity['total_unscanned'] ?></span>
                <span class="stat-label"><?= $this->te('admin.inactive_total', ['days' => (int) $inactivity['days']]) ?></span>
            </div>
            <div class="stat-box">
                <span class="stat-value"><?= (int) $inactivity['claimed_unscanned'] ?></span>
                <span class="stat-label"><?= $this->te('admin.inactive_claimed', ['days' => (int) $inactivity['days']]) ?></span>
            </div>
            <div class="stat-box">
                <span class="stat-value"><?= (int) $inactivity['untyped_unscanned'] ?></span>
                <span class="stat-label"><?= $this->te('admin.inactive_untyped', ['days' => (int) $inactivity['days']]) ?></span>
            </div>
            <div class="stat-box">
                <span class="stat-value"><?= (int) $inactivity['never_scanned'] ?></span>
                <span class="stat-label"><?= $this->te('admin.inactive_never') ?></span>
            </div>
        </div>

        <p class="hint" style="margin-top: 1rem;">
            <strong><?= $this->te('admin.inactive_freezers_list', ['days' => (int) $inactivity['days'], 'count' => count($inactivity['inactive_freezers'])]) ?></strong>
        </p>

        <?php if ($inactivity['inactive_freezers'] !== []): ?>
            <ul class="inactive-list">
                <?php foreach ($inactivity['inactive_freezers'] as $freezer): ?>
                    <li>
                        <span class="code-badge"><?= $this->escape($freezer['code']) ?></span>
                        <strong><?= $this->escape((string) $freezer['name']) ?></strong>
                        <small>
                            &middot; <?= (int) $freezer['food_tag_count'] ?> food labels, <?= (int) $freezer['active_item_count'] ?> active items
                            &middot; <?= $this->te('admin.inspect_last_scan') ?>: <?= $this->escape($freezer['last_scan_at'] ?? $this->t('admin.inspect_never')) ?>
                        </small>
                    </li>
                <?php endforeach; ?>
            </ul>

            <form method="post" action="/admin/maintenance/purge-inactive" onsubmit="return confirm(<?= $this->escape(json_encode($this->t('admin.inactive_purge_confirm'))) ?>);">
                <input type="hidden" name="csrf_token" value="<?= $this->escape($csrfToken) ?>">
                <input type="hidden" name="days" value="<?= (int) $inactivity['days'] ?>">
                <button type="submit" class="button secondary"><?= $this->te('admin.inactive_purge_button', ['count' => count($inactivity['inactive_freezers']), 'days' => (int) $inactivity['days']]) ?></button>
            </form>
        <?php endif; ?>
    </section>

    <!-- 6. Rate-Limit Blocks -->
    <section class="admin-card">
        <h2><?= $this->te('admin.ratelimit_heading') ?></h2>
        <?php if ($rateBlocks === []): ?>
            <p class="hint"><?= $this->te('admin.ratelimit_none') ?></p>
        <?php else: ?>
            <ul class="inactive-list">
                <?php foreach ($rateBlocks as $block): ?>
                    <li><code><?= $this->escape($block['key']) ?></code> &middot; <?= (int) ceil($block['remaining_seconds'] / 60) ?> min remaining</li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <form method="post" action="/admin/rate-limits/clear">
            <input type="hidden" name="csrf_token" value="<?= $this->escape($csrfToken) ?>">
            <button type="submit" class="button secondary"><?= $this->te('admin.ratelimit_clear_button') ?></button>
        </form>
    </section>

    <form method="post" action="/admin/logout" class="logout-form">
        <button type="submit" class="button secondary"><?= $this->te('admin.logout_button') ?></button>
    </form>
</div>

<script src="/assets/vendor/qrcode.js"></script>
<script src="/assets/js/export-labels.js"></script>
<script src="/assets/js/admin.js"></script>
