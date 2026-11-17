/**
 * Handles the food photo widget on the add / edit item forms:
 * - Compresses a selected or captured photo in the browser before the form is submitted.
 * - Provides an in-app camera view with a "Switch camera" button so users are never stuck on the
 *   wrong camera when taking a food photo.
 */
(() => {
    const form = document.getElementById('add-item-form');
    const photoInput = document.getElementById('photo');
    const statusElement = document.getElementById('photo-status');
    const previewImage = document.getElementById('photo-preview');
    const clearButton = document.getElementById('photo-clear');
    const cameraOpenButton = document.getElementById('photo-camera-open');
    const cameraBox = document.getElementById('photo-camera-box');
    const video = document.getElementById('photo-video');
    const captureButton = document.getElementById('photo-capture');
    const switchButton = document.getElementById('photo-switch-camera');
    const cameraCloseButton = document.getElementById('photo-camera-close');
    const submitButton = form.querySelector('button[type="submit"]');

    const cameraHelper = window.BuzlookCamera;
    let stream = null;
    let previewUrl = null;

    if (cameraOpenButton && cameraHelper && cameraHelper.hasLiveCamera) {
        cameraOpenButton.hidden = false;
    }

    function formatKilobytes(bytes) {
        return Math.round(bytes / 1024) + ' KB';
    }

    function showPreview(file) {
        if (!previewImage) {
            return;
        }
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }
        previewUrl = URL.createObjectURL(file);
        previewImage.src = previewUrl;
        previewImage.hidden = false;
        if (clearButton) {
            clearButton.hidden = false;
        }
    }

    function clearSelectedPhoto() {
        photoInput.value = '';
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }
        if (previewImage) {
            previewImage.removeAttribute('src');
            previewImage.hidden = true;
        }
        if (clearButton) {
            clearButton.hidden = true;
        }
        statusElement.textContent = '';
    }

    async function processFile(originalFile) {
        submitButton.disabled = true;
        statusElement.textContent = window.t('compressing');

        try {
            const compressedFile = await ImageCompressor.compress(originalFile);

            // Swap the original file for the compressed one inside the file input.
            const transfer = new DataTransfer();
            transfer.items.add(compressedFile);
            photoInput.files = transfer.files;

            showPreview(compressedFile);
            statusElement.textContent =
                formatKilobytes(originalFile.size) + ' → ' + formatKilobytes(compressedFile.size);
        } catch (error) {
            clearSelectedPhoto();
            statusElement.textContent = error.message || window.t('photo_unprocessable');
        } finally {
            submitButton.disabled = false;
        }
    }

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach((track) => track.stop());
            stream = null;
        }
        if (video) {
            video.srcObject = null;
        }
        if (cameraBox) {
            cameraBox.hidden = true;
        }
    }

    async function startCamera() {
        if (!cameraHelper || !cameraHelper.hasLiveCamera) {
            return;
        }
        try {
            stream = await cameraHelper.openStream(false);
            video.srcObject = stream;
            cameraBox.hidden = false;
            await video.play();
        } catch (error) {
            stopCamera();
            statusElement.textContent = window.t('scan_camera_unavailable');
        }
    }

    if (cameraOpenButton) {
        cameraOpenButton.addEventListener('click', () => {
            if (cameraBox && !cameraBox.hidden) {
                stopCamera();
            } else {
                startCamera();
            }
        });
    }

    if (switchButton) {
        switchButton.addEventListener('click', async () => {
            try {
                stream = await cameraHelper.switchStream(stream);
                video.srcObject = stream;
                await video.play();
            } catch (error) {
                stopCamera();
                statusElement.textContent = window.t('scan_camera_unavailable');
            }
        });
    }

    if (captureButton) {
        captureButton.addEventListener('click', () => {
            if (!video || !video.videoWidth) {
                return;
            }
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            stopCamera();
            canvas.toBlob(
                (blob) => {
                    if (blob) {
                        processFile(new File([blob], 'capture.jpg', { type: 'image/jpeg' }));
                    }
                },
                'image/jpeg',
                0.92,
            );
        });
    }

    if (cameraCloseButton) {
        cameraCloseButton.addEventListener('click', stopCamera);
    }

    if (clearButton) {
        clearButton.addEventListener('click', clearSelectedPhoto);
    }

    photoInput.addEventListener('change', async () => {
        const originalFile = photoInput.files[0];
        if (!originalFile) {
            clearSelectedPhoto();
            return;
        }
        await processFile(originalFile);
    });

    window.addEventListener('pagehide', stopCamera);
})();
