/**
 * Admin dashboard client-side tools:
 * - Generates label batches (registered or unregistered) and builds:
 *   1. ZIP archive of PNG QR labels (with human-readable code underneath)
 *   2. Vector A4 PDF with adjustable rows, columns, orientation, margin, and optional cut lines
 *   3. 2-column CSV file (url,code)
 * - Bulk registers or unregisters codes from pasted text or uploaded CSV.
 */
(() => {
    const PT_PER_MM = 72 / 25.4;

    function triggerDownload(blob, filename) {
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(link.href), 10000);
    }

    function escapePdfText(text) {
        return String(text).replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)');
    }

    /**
     * Builds a two-column CSV ("url,code") for a list of { code, url } items.
     */
    function buildCsv(tags) {
        const rows = ['url,code'];
        tags.forEach((tag) => {
            const url = '"' + String(tag.url).replace(/"/g, '""') + '"';
            const code = '"' + String(tag.code).replace(/"/g, '""') + '"';
            rows.push(url + ',' + code);
        });
        return new Blob([rows.join('\r\n') + '\r\n'], { type: 'text/csv;charset=utf-8' });
    }

    /**
     * Builds a vector PDF 1.4 document laying out QR labels in an adjustable grid on A4 pages.
     * Each cell contains the QR code and only the human-readable code underneath.
     */
    function buildPdf(tags, options = {}) {
        const cols = Math.max(1, Math.min(20, Number(options.cols) || 8));
        const rows = Math.max(1, Math.min(30, Number(options.rows) || 4));
        const landscape = (options.orientation || 'landscape') === 'landscape';
        const marginMm = Math.max(0, Math.min(40, Number.isFinite(Number(options.marginMm)) ? Number(options.marginMm) : 8));
        const showGrid = !!options.showGrid;

        const pageW = (landscape ? 297 : 210) * PT_PER_MM;
        const pageH = (landscape ? 210 : 297) * PT_PER_MM;
        const marginPt = marginMm * PT_PER_MM;
        const gridW = Math.max(36, pageW - 2 * marginPt);
        const gridH = Math.max(36, pageH - 2 * marginPt);
        const cellW = gridW / cols;
        const cellH = gridH / rows;
        const perPage = cols * rows;

        const pageStreams = [];
        for (let offset = 0; offset < tags.length; offset += perPage) {
            const slice = tags.slice(offset, offset + perPage);
            const ops = [];

            if (showGrid) {
                ops.push('q 0.80 G 0.4 w [2 2] 0 d');
                for (let c = 0; c <= cols; c++) {
                    const x = (marginPt + c * cellW).toFixed(2);
                    ops.push(`${x} ${marginPt.toFixed(2)} m ${x} ${(pageH - marginPt).toFixed(2)} l`);
                }
                for (let r = 0; r <= rows; r++) {
                    const y = (marginPt + r * cellH).toFixed(2);
                    ops.push(`${marginPt.toFixed(2)} ${y} m ${(pageW - marginPt).toFixed(2)} ${y} l`);
                }
                ops.push('S Q');
            }

            ops.push('0 g');
            slice.forEach((tag, index) => {
                const col = index % cols;
                const row = Math.floor(index / cols);
                const cellX = marginPt + col * cellW;
                const cellY = pageH - marginPt - (row + 1) * cellH;

                const pad = Math.min(cellW, cellH) * 0.06;
                const textZoneH = Math.min(cellW, cellH) * 0.18;
                const qrBox = Math.max(12, Math.min(cellW - 2 * pad, cellH - 2 * pad - textZoneH));

                const qr = qrcode(0, 'M');
                qr.addData(tag.url);
                qr.make();
                const moduleCount = qr.getModuleCount();
                const quietModules = 2;
                const modulePt = qrBox / (moduleCount + quietModules * 2);

                const qrX = cellX + (cellW - qrBox) / 2;
                const qrY = cellY + pad + textZoneH + (cellH - 2 * pad - textZoneH - qrBox) / 2;

                for (let r = 0; r < moduleCount; r++) {
                    for (let c = 0; c < moduleCount; c++) {
                        if (qr.isDark(r, c)) {
                            const mx = qrX + (c + quietModules) * modulePt;
                            const my = qrY + (moduleCount + quietModules - 1 - r) * modulePt;
                            ops.push(`${mx.toFixed(2)} ${my.toFixed(2)} ${modulePt.toFixed(2)} ${modulePt.toFixed(2)} re`);
                        }
                    }
                }
                ops.push('f');

                const codeText = String(tag.code);
                const maxFontByWidth = (qrBox * 0.92) / (Math.max(1, codeText.length) * 0.6);
                const fontSize = Math.max(6, Math.min(textZoneH * 0.78, maxFontByWidth, 16));
                const textW = codeText.length * 0.6 * fontSize;
                const textX = cellX + (cellW - textW) / 2;
                const textY = qrY - fontSize * 0.75;

                ops.push(`BT /F1 ${fontSize.toFixed(2)} Tf ${textX.toFixed(2)} ${textY.toFixed(2)} Td (${escapePdfText(codeText)}) Tj ET`);
            });

            pageStreams.push(ops.join('\n'));
        }

        // Assemble PDF 1.4 objects:
        // 1: Catalog, 2: Pages, 3: Font (/Courier-Bold), 4..: pairs of Page & Content stream
        const objects = [];
        const pageObjIds = [];
        for (let i = 0; i < pageStreams.length; i++) {
            pageObjIds.push(4 + i * 2);
        }

        objects.push('1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n');
        objects.push(
            `2 0 obj\n<< /Type /Pages /Kids [${pageObjIds.map((id) => id + ' 0 R').join(' ')}] /Count ${pageObjIds.length} >>\nendobj\n`,
        );
        objects.push('3 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold /Encoding /WinAnsiEncoding >>\nendobj\n');

        pageStreams.forEach((stream, index) => {
            const pageId = 4 + index * 2;
            const contentId = pageId + 1;
            objects.push(
                `${pageId} 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${pageW.toFixed(2)} ${pageH.toFixed(2)}] /Resources << /Font << /F1 3 0 R >> >> /Contents ${contentId} 0 R >>\nendobj\n`,
            );
            objects.push(`${contentId} 0 obj\n<< /Length ${stream.length} >>\nstream\n${stream}\nendstream\nendobj\n`);
        });

        let body = '%PDF-1.4\n';
        const offsets = [0];
        objects.forEach((obj) => {
            offsets.push(body.length);
            body += obj;
        });

        const xrefStart = body.length;
        body += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`;
        for (let i = 1; i <= objects.length; i++) {
            body += String(offsets[i]).padStart(10, '0') + ' 00000 n \n';
        }
        body += `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${xrefStart}\n%%EOF\n`;

        return new Blob([body], { type: 'application/pdf' });
    }

    window.BuzlookAdmin = { buildCsv, buildPdf };

    const batchForm = document.getElementById('admin-batch-form');
    if (!batchForm) {
        return;
    }

    const batchGenerateBtn = document.getElementById('batch-generate-btn');
    const batchStatus = document.getElementById('batch-status');
    const batchDownloads = document.getElementById('batch-downloads');
    const downloadZipBtn = document.getElementById('download-zip');
    const downloadPdfBtn = document.getElementById('download-pdf');
    const downloadCsvBtn = document.getElementById('download-csv');
    const pdfColsInput = document.getElementById('pdf-cols');
    const pdfRowsInput = document.getElementById('pdf-rows');
    const pdfOrientationInput = document.getElementById('pdf-orientation');
    const pdfMarginInput = document.getElementById('pdf-margin');
    const pdfGridLinesInput = document.getElementById('pdf-grid-lines');

    let currentBatch = [];

    function batchFilenamePrefix() {
        return 'buzlook-labels-' + new Date().toISOString().slice(0, 10);
    }

    batchForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        batchGenerateBtn.disabled = true;
        batchStatus.textContent = window.t('admin_generating');

        try {
            const response = await fetch(batchForm.dataset.url, {
                method: 'POST',
                credentials: 'same-origin',
                body: new FormData(batchForm),
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error || 'HTTP ' + response.status);
            }

            currentBatch = data.tags || [];
            batchDownloads.hidden = currentBatch.length === 0;
            batchStatus.textContent = window.t(
                data.registered ? 'admin_batch_ready_registered' : 'admin_batch_ready_unregistered',
                { count: currentBatch.length },
            );
        } catch (error) {
            batchStatus.textContent = error.message || window.t('export_failed');
        } finally {
            batchGenerateBtn.disabled = false;
        }
    });

    downloadCsvBtn.addEventListener('click', () => {
        if (currentBatch.length === 0) {
            return;
        }
        triggerDownload(buildCsv(currentBatch), batchFilenamePrefix() + '.csv');
        batchStatus.textContent = window.t('admin_done');
    });

    downloadPdfBtn.addEventListener('click', () => {
        if (currentBatch.length === 0) {
            return;
        }
        const cols = Number(pdfColsInput.value) || 8;
        const rows = Number(pdfRowsInput.value) || 4;
        const pages = Math.ceil(currentBatch.length / Math.max(1, cols * rows));
        batchStatus.textContent = window.t('admin_building_pdf', { pages });

        const pdfBlob = buildPdf(currentBatch, {
            cols,
            rows,
            orientation: pdfOrientationInput.value,
            marginMm: Number(pdfMarginInput.value),
            showGrid: pdfGridLinesInput.checked,
        });
        triggerDownload(pdfBlob, batchFilenamePrefix() + '.pdf');
        batchStatus.textContent = window.t('admin_done');
    });

    downloadZipBtn.addEventListener('click', async () => {
        if (currentBatch.length === 0 || !window.BuzlookExport) {
            return;
        }
        downloadZipBtn.disabled = true;
        batchStatus.textContent = window.t('admin_building_zip', { count: currentBatch.length });

        try {
            const files = [];
            for (const tag of currentBatch) {
                const blob = await window.BuzlookExport.renderLabel(tag.url, tag.code);
                files.push({
                    name: tag.code + '.png',
                    data: new Uint8Array(await blob.arrayBuffer()),
                });
            }
            triggerDownload(window.BuzlookExport.buildZip(files), batchFilenamePrefix() + '.zip');
            batchStatus.textContent = window.t('admin_done');
        } catch (error) {
            batchStatus.textContent = window.t('export_failed');
        } finally {
            downloadZipBtn.disabled = false;
        }
    });

    // Bulk register / unregister
    const bulkForm = document.getElementById('admin-bulk-form');
    const bulkStatus = document.getElementById('bulk-status');
    if (bulkForm) {
        bulkForm.querySelectorAll('[data-bulk-action]').forEach((button) => {
            button.addEventListener('click', async () => {
                const action = button.dataset.bulkAction;
                const formData = new FormData(bulkForm);
                formData.set('action', action);

                bulkStatus.textContent = window.t('admin_bulk_working');
                try {
                    const response = await fetch(bulkForm.dataset.url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData,
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        throw new Error(data.error || 'HTTP ' + response.status);
                    }
                    const res = data.result;
                    if (action === 'unregister') {
                        bulkStatus.textContent = window.t('admin_bulk_unreg_done', {
                            unregistered: (res.unregistered || []).length,
                            already: (res.already_unregistered || []).length,
                            not_found: (res.not_found || []).length,
                        });
                    } else {
                        bulkStatus.textContent = window.t('admin_bulk_reg_done', {
                            created: (res.created || []).length,
                            enabled: (res.enabled || []).length,
                            already: (res.already_registered || []).length,
                        });
                    }
                } catch (error) {
                    bulkStatus.textContent = error.message || window.t('export_failed');
                }
            });
        });
    }
})();
