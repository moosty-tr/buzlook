/**
 * Client-side image compression using the HTML5 Canvas API.
 * Produces a JPEG no larger than the requested byte budget.
 */
const ImageCompressor = (() => {
    const DEFAULTS = {
        maxBytes: 180 * 1024, // Stays safely inside the 150-200 KB target range.
        startDimension: 1280,
        minDimension: 320,
        startQuality: 0.85,
        minQuality: 0.4,
        qualityStep: 0.07,
        dimensionScale: 0.8,
    };

    async function loadBitmap(file) {
        if ('createImageBitmap' in window) {
            return createImageBitmap(file, { imageOrientation: 'from-image' });
        }

        return new Promise((resolve, reject) => {
            const image = new Image();
            image.onload = () => resolve(image);
            image.onerror = () => reject(new Error(window.t('image_unreadable')));
            image.src = URL.createObjectURL(file);
        });
    }

    function renderToBlob(source, dimension, quality) {
        const scale = Math.min(1, dimension / Math.max(source.width, source.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(source.width * scale));
        canvas.height = Math.max(1, Math.round(source.height * scale));

        const context = canvas.getContext('2d');
        context.fillStyle = '#ffffff'; // JPEG has no transparency.
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(source, 0, 0, canvas.width, canvas.height);

        return new Promise((resolve, reject) => {
            canvas.toBlob(
                (blob) => (blob ? resolve(blob) : reject(new Error(window.t('compress_failed')))),
                'image/jpeg',
                quality
            );
        });
    }

    /**
     * @param {File} file
     * @param {Partial<typeof DEFAULTS>} [overrides]
     * @returns {Promise<File>}
     */
    async function compress(file, overrides = {}) {
        const options = { ...DEFAULTS, ...overrides };
        const source = await loadBitmap(file);

        let dimension = options.startDimension;
        let quality = options.startQuality;
        let blob = await renderToBlob(source, dimension, quality);

        while (blob.size > options.maxBytes) {
            if (quality - options.qualityStep >= options.minQuality) {
                quality -= options.qualityStep;
            } else if (dimension * options.dimensionScale >= options.minDimension) {
                dimension = Math.round(dimension * options.dimensionScale);
                quality = options.startQuality;
            } else {
                break; // Smallest allowed size reached; the server enforces the hard limit.
            }
            blob = await renderToBlob(source, dimension, quality);
        }

        const baseName = file.name.replace(/\.[^.]+$/, '') || 'photo';
        return new File([blob], baseName + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
    }

    return { compress };
})();
