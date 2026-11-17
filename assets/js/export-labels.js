/**
 * "Export labels": draws a QR code PNG (code printed under it) for the freezer label and every food label of the
 * freezer and downloads them as one ZIP. Everything happens in the browser, so no server extensions are needed.
 */
(() => {
    const MODULE_PX = 12; // size of one QR module in the PNG
    const QUIET_MODULES = 4; // white border required around a QR code
    const TEXT_ZONE_PX = 72; // space under the QR code for the human readable code

    function renderLabel(url, code) {
        const qr = qrcode(0, 'M');
        qr.addData(url);
        qr.make();

        const count = qr.getModuleCount();
        const qrSize = (count + QUIET_MODULES * 2) * MODULE_PX;
        const canvas = document.createElement('canvas');
        canvas.width = qrSize;
        canvas.height = qrSize + TEXT_ZONE_PX;

        const context = canvas.getContext('2d');
        context.fillStyle = '#fff';
        context.fillRect(0, 0, canvas.width, canvas.height);

        context.fillStyle = '#000';
        for (let row = 0; row < count; row++) {
            for (let column = 0; column < count; column++) {
                if (qr.isDark(row, column)) {
                    context.fillRect((column + QUIET_MODULES) * MODULE_PX, (row + QUIET_MODULES) * MODULE_PX, MODULE_PX, MODULE_PX);
                }
            }
        }

        // Only the code, as large as fits.
        let fontSize = 48;
        const family = '"DejaVu Sans Mono", Consolas, Menlo, "Courier New", monospace';
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.font = `bold ${fontSize}px ${family}`;
        while (context.measureText(code).width > qrSize * 0.9 && fontSize > 12) {
            fontSize -= 2;
            context.font = `bold ${fontSize}px ${family}`;
        }
        context.fillText(code, qrSize / 2, qrSize + TEXT_ZONE_PX / 2 - 6);

        return new Promise((resolve, reject) => {
            canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('PNG failed'))), 'image/png');
        });
    }

    const CRC_TABLE = (() => {
        const table = new Uint32Array(256);
        for (let n = 0; n < 256; n++) {
            let c = n;
            for (let k = 0; k < 8; k++) {
                c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
            }
            table[n] = c >>> 0;
        }
        return table;
    })();

    function crc32(bytes) {
        let crc = 0xffffffff;
        for (let i = 0; i < bytes.length; i++) {
            crc = CRC_TABLE[(crc ^ bytes[i]) & 0xff] ^ (crc >>> 8);
        }
        return (crc ^ 0xffffffff) >>> 0;
    }

    /** Minimal ZIP writer (stored, no compression: PNG is already compressed). */
    function buildZip(files) {
        const encoder = new TextEncoder();
        const now = new Date();
        const dosTime = (now.getHours() << 11) | (now.getMinutes() << 5) | (now.getSeconds() >> 1);
        const dosDate = ((now.getFullYear() - 1980) << 9) | ((now.getMonth() + 1) << 5) | now.getDate();

        const parts = [];
        const central = [];
        let offset = 0;
        let centralSize = 0;

        files.forEach((file) => {
            const name = encoder.encode(file.name);
            const crc = crc32(file.data);
            const size = file.data.length;

            const local = new DataView(new ArrayBuffer(30));
            local.setUint32(0, 0x04034b50, true);
            local.setUint16(4, 20, true);
            local.setUint16(6, 0x0800, true); // UTF-8 names
            local.setUint16(8, 0, true); // stored
            local.setUint16(10, dosTime, true);
            local.setUint16(12, dosDate, true);
            local.setUint32(14, crc, true);
            local.setUint32(18, size, true);
            local.setUint32(22, size, true);
            local.setUint16(26, name.length, true);
            local.setUint16(28, 0, true);
            parts.push(new Uint8Array(local.buffer), name, file.data);

            const entry = new DataView(new ArrayBuffer(46));
            entry.setUint32(0, 0x02014b50, true);
            entry.setUint16(4, 20, true);
            entry.setUint16(6, 20, true);
            entry.setUint16(8, 0x0800, true);
            entry.setUint16(10, 0, true);
            entry.setUint16(12, dosTime, true);
            entry.setUint16(14, dosDate, true);
            entry.setUint32(16, crc, true);
            entry.setUint32(20, size, true);
            entry.setUint32(24, size, true);
            entry.setUint16(28, name.length, true);
            entry.setUint32(42, offset, true);
            central.push(new Uint8Array(entry.buffer), name);

            offset += 30 + name.length + size;
            centralSize += 46 + name.length;
        });

        const end = new DataView(new ArrayBuffer(22));
        end.setUint32(0, 0x06054b50, true);
        end.setUint16(8, files.length, true);
        end.setUint16(10, files.length, true);
        end.setUint32(12, centralSize, true);
        end.setUint32(16, offset, true);

        return new Blob([...parts, ...central, new Uint8Array(end.buffer)], { type: 'application/zip' });
    }

    // Exposed for automated tests.
    window.BuzlookExport = { renderLabel, buildZip };

    const button = document.getElementById('export-labels');
    const statusElement = document.getElementById('export-status');
    if (!button) {
        return;
    }

    async function run() {
        button.disabled = true;
        statusElement.textContent = window.t('export_working');

        try {
            const response = await fetch(button.dataset.url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            const data = await response.json();

            const files = [];
            for (const label of data.labels) {
                const blob = await renderLabel(label.url, label.code);
                files.push({ name: label.type + '_' + label.code + '.png', data: new Uint8Array(await blob.arrayBuffer()) });
            }

            const link = document.createElement('a');
            link.href = URL.createObjectURL(buildZip(files));
            link.download = button.dataset.prefix + '-' + new Date().toISOString().slice(0, 10) + '.zip';
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(link.href), 10000);

            statusElement.textContent = window.t('export_done', { count: files.length });
        } catch (error) {
            statusElement.textContent = window.t('export_failed');
        } finally {
            button.disabled = false;
        }
    }

    button.addEventListener('click', run);
})();
