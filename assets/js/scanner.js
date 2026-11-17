/**
 * "Scan label" button available on every page, plus shared camera & sound helpers.
 *
 * - Live camera scanning needs a secure context (HTTPS or localhost).
 * - Over plain HTTP (e.g. local network testing) or when the camera is blocked, the user can
 *   take a photo of the QR code instead; that works without camera permission.
 * - Users can switch between cameras (rear / front / multiple lenses); the choice is remembered.
 * - Sound feedback plays a happy beep on a valid Buzlook QR scan and a descending beep on
 *   failed scan or when the user closes/cancels scanning.
 * - Decoding uses the native BarcodeDetector when present, otherwise the bundled jsQR.
 * - Only the label code is taken from the scanned text; we always navigate on our own origin.
 */
(() => {
    const openButton = document.getElementById('scan-open');
    const overlay = document.getElementById('scan-overlay');
    const video = document.getElementById('scan-video');
    const statusElement = document.getElementById('scan-status');
    const switchButton = document.getElementById('scan-switch-camera');
    const closeButton = document.getElementById('scan-close');
    const photoLabel = document.getElementById('scan-photo-label');
    const photoInput = document.getElementById('scan-photo');

    const CODE_PATTERN = /^[1-9A-HJ-NP-Za-km-z]{4,64}$/;
    const STORAGE_FACING = 'buzlook_camera_facing';
    const STORAGE_DEVICE = 'buzlook_camera_device_id';
    const hasLiveCamera = window.isSecureContext && !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);

    let stream = null;
    let scanning = false;
    let detector = null;
    let target = null; // input to fill with the scanned code instead of opening the label
    let audioCtx = null;
    let lastBeep = null;
    let lastFailBeepAt = 0;

    if ('BarcodeDetector' in window) {
        try {
            detector = new BarcodeDetector({ formats: ['qr_code'] });
        } catch (error) {
            detector = null;
        }
    }

    function safeGetStorage(key) {
        try {
            return localStorage.getItem(key) || '';
        } catch (error) {
            return '';
        }
    }

    function safeSetStorage(key, value) {
        try {
            if (value) {
                localStorage.setItem(key, value);
            } else {
                localStorage.removeItem(key);
            }
        } catch (error) {
            // Storage unavailable.
        }
    }

    function unlockAudio() {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) {
            return null;
        }
        try {
            if (!audioCtx) {
                audioCtx = new AudioContextClass();
            }
            if (audioCtx.state === 'suspended') {
                audioCtx.resume().catch(() => {});
            }
        } catch (error) {
            audioCtx = null;
        }
        return audioCtx;
    }

    /**
     * Synthesizes scan feedback tones via Web Audio API:
     * - 'success': cheerful two-tone chime before navigating to the scanned label
     * - 'fail': descending two-tone low beep on invalid QR, failed read, or scan give-up
     */
    function playBeep(type) {
        lastBeep = type;
        const ctx = unlockAudio();
        if (!ctx) {
            return Promise.resolve();
        }

        try {
            const now = ctx.currentTime;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);

            if (type === 'success') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(880, now);
                osc.frequency.setValueAtTime(1174.66, now + 0.07);
                gain.gain.setValueAtTime(0.0001, now);
                gain.gain.exponentialRampToValueAtTime(0.22, now + 0.015);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.16);
                osc.start(now);
                osc.stop(now + 0.165);
                return new Promise((resolve) => setTimeout(resolve, 165));
            }

            osc.type = 'triangle';
            osc.frequency.setValueAtTime(440, now);
            osc.frequency.setValueAtTime(293.66, now + 0.085);
            gain.gain.setValueAtTime(0.0001, now);
            gain.gain.exponentialRampToValueAtTime(0.22, now + 0.015);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.19);
            osc.start(now);
            osc.stop(now + 0.195);
            return new Promise((resolve) => setTimeout(resolve, 195));
        } catch (error) {
            return Promise.resolve();
        }
    }

    /**
     * Shared camera helper used by both the QR scanner and the food photo capture widget.
     */
    async function openCameraStream(preferExactFacing = false) {
        const savedDeviceId = safeGetStorage(STORAGE_DEVICE);
        const savedFacing = safeGetStorage(STORAGE_FACING) || 'environment';

        const attempts = [];
        if (savedDeviceId) {
            attempts.push({ video: { deviceId: { exact: savedDeviceId } }, audio: false });
        }
        if (preferExactFacing) {
            attempts.push({ video: { facingMode: { exact: savedFacing } }, audio: false });
        }
        attempts.push({ video: { facingMode: { ideal: savedFacing } }, audio: false });
        attempts.push({ video: true, audio: false });

        let lastError = null;
        for (const constraints of attempts) {
            try {
                const mediaStream = await navigator.mediaDevices.getUserMedia(constraints);
                const track = mediaStream.getVideoTracks()[0];
                const settings = (track && track.getSettings && track.getSettings()) || {};
                if (settings.deviceId) {
                    safeSetStorage(STORAGE_DEVICE, settings.deviceId);
                }
                if (settings.facingMode) {
                    safeSetStorage(STORAGE_FACING, settings.facingMode);
                }
                return mediaStream;
            } catch (error) {
                lastError = error;
            }
        }
        throw lastError || new Error('Camera unavailable');
    }

    async function switchCameraStream(currentStream) {
        const currentTrack = currentStream && currentStream.getVideoTracks ? currentStream.getVideoTracks()[0] : null;
        const currentSettings = (currentTrack && currentTrack.getSettings && currentTrack.getSettings()) || {};
        const currentDeviceId = currentSettings.deviceId || safeGetStorage(STORAGE_DEVICE);
        const currentFacing = currentSettings.facingMode || safeGetStorage(STORAGE_FACING) || 'environment';
        const nextFacing = currentFacing === 'environment' ? 'user' : 'environment';

        let videoDevices = [];
        if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
            try {
                const all = await navigator.mediaDevices.enumerateDevices();
                videoDevices = all.filter((d) => d.kind === 'videoinput' && d.deviceId);
            } catch (error) {
                videoDevices = [];
            }
        }

        if (currentStream) {
            currentStream.getTracks().forEach((track) => track.stop());
        }

        safeSetStorage(STORAGE_FACING, nextFacing);
        if (videoDevices.length > 1) {
            const currentIndex = videoDevices.findIndex((d) => d.deviceId === currentDeviceId);
            const nextDevice = videoDevices[(currentIndex + 1) % videoDevices.length];
            safeSetStorage(STORAGE_DEVICE, nextDevice.deviceId);
        } else {
            safeSetStorage(STORAGE_DEVICE, '');
        }

        return openCameraStream(true);
    }

    window.BuzlookCamera = {
        hasLiveCamera,
        openStream: openCameraStream,
        switchStream: switchCameraStream,
    };
    window.BuzlookScanner = {
        playBeep,
        getLastBeep: () => lastBeep,
    };

    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d', { willReadFrequently: true });

    openButton.hidden = false;

    function setStatus(message) {
        statusElement.textContent = message;
    }

    /** Extracts a label code from scanned text (a full URL or the bare code). */
    function extractCode(text) {
        const value = String(text || '').trim();
        if (CODE_PATTERN.test(value)) {
            return value;
        }
        try {
            const segments = new URL(value).pathname.split('/').filter(Boolean);
            if (segments.length === 1 && CODE_PATTERN.test(segments[0])) {
                return segments[0];
            }
        } catch (error) {
            // Not a URL.
        }
        return null;
    }

    function handleResult(text) {
        const code = extractCode(text);
        if (code === null) {
            setStatus(window.t('scan_not_label'));
            const now = Date.now();
            if (now - lastFailBeepAt > 1500) {
                lastFailBeepAt = now;
                playBeep('fail');
            }
            return false;
        }
        const destinationTarget = target;
        close(false);
        playBeep('success').then(() => {
            if (destinationTarget) {
                destinationTarget.value = code;
                return;
            }
            window.location.assign('/' + code);
        });
        return true;
    }

    async function decodeSource(source, width, height) {
        if (detector) {
            try {
                const found = await detector.detect(source);
                if (found.length > 0) {
                    return found[0].rawValue;
                }
            } catch (error) {
                // Fall through to jsQR.
            }
        }
        if (typeof jsQR === 'function') {
            canvas.width = width;
            canvas.height = height;
            context.drawImage(source, 0, 0, width, height);
            const result = jsQR(context.getImageData(0, 0, width, height).data, width, height);
            return result ? result.data : null;
        }
        return null;
    }

    async function scanLoop() {
        if (!scanning) {
            return;
        }
        if (video.readyState >= 2 && video.videoWidth > 0) {
            const scale = Math.min(1, 800 / video.videoWidth);
            const text = await decodeSource(video, Math.round(video.videoWidth * scale), Math.round(video.videoHeight * scale));
            if (text !== null && handleResult(text)) {
                return;
            }
        }
        setTimeout(scanLoop, 150);
    }

    async function attachStream(mediaStream) {
        stream = mediaStream;
        video.hidden = false;
        if (switchButton) {
            switchButton.hidden = false;
        }
        video.srcObject = stream;
        await video.play();
        setStatus(window.t('scan_prompt'));
        scanning = true;
        scanLoop();
    }

    async function startCamera() {
        if (!hasLiveCamera) {
            video.hidden = true;
            if (switchButton) {
                switchButton.hidden = true;
            }
            setStatus(window.t('scan_needs_https'));
            return;
        }
        try {
            await attachStream(await openCameraStream(false));
        } catch (error) {
            video.hidden = true;
            if (switchButton) {
                switchButton.hidden = true;
            }
            setStatus(window.t('scan_camera_unavailable'));
            playBeep('fail');
        }
    }

    function stopCamera() {
        scanning = false;
        if (stream) {
            stream.getTracks().forEach((track) => track.stop());
            stream = null;
        }
        video.srcObject = null;
        if (switchButton) {
            switchButton.hidden = true;
        }
    }

    function open(targetInput) {
        unlockAudio();
        target = targetInput || null;
        overlay.hidden = false;
        photoLabel.hidden = false;
        startCamera();
    }

    function close(gaveUp = false) {
        if (gaveUp && !overlay.hidden) {
            playBeep('fail');
        }
        target = null;
        stopCamera();
        overlay.hidden = true;
    }

    if (switchButton) {
        switchButton.addEventListener('click', async () => {
            unlockAudio();
            scanning = false;
            try {
                await attachStream(await switchCameraStream(stream));
            } catch (error) {
                stopCamera();
                video.hidden = true;
                setStatus(window.t('scan_camera_unavailable'));
                playBeep('fail');
            }
        });
    }

    photoInput.addEventListener('change', async () => {
        unlockAudio();
        const file = photoInput.files[0];
        photoInput.value = '';
        if (!file) {
            return;
        }
        setStatus(window.t('scan_reading'));
        try {
            const bitmap = await createImageBitmap(file);
            const scale = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height));
            const text = await decodeSource(bitmap, Math.round(bitmap.width * scale), Math.round(bitmap.height * scale));
            if (text === null) {
                setStatus(window.t('scan_none_found'));
                playBeep('fail');
            } else {
                handleResult(text);
            }
        } catch (error) {
            setStatus(window.t('scan_read_failed'));
            playBeep('fail');
        }
    });

    openButton.addEventListener('click', () => open(null));
    document.querySelectorAll('[data-scan-into]').forEach((button) => {
        button.hidden = false;
        button.addEventListener('click', () => open(document.getElementById(button.dataset.scanInto)));
    });
    closeButton.addEventListener('click', () => close(true));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !overlay.hidden) {
            close(true);
        }
    });
})();
