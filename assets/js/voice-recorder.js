/**
 * Voice note widget (add / edit item forms).
 *
 * - Live recording needs MediaRecorder and a secure context (HTTPS or localhost). It is low bitrate on
 *   purpose: speech quality is enough and a minute stays well below 200 KB.
 * - Without that (plain HTTP on the local network, old browsers) the user can pick / record an audio file
 *   with the phone's own recorder instead.
 * - The recording is placed into the form's file input, so it is uploaded with the normal form post.
 */
(() => {
    const widget = document.getElementById('voice-widget');
    if (!widget) {
        return;
    }

    const maxSeconds = Number(widget.dataset.maxSeconds) || 60;
    const maxBytes = Number(widget.dataset.maxBytes) || 1024 * 1024;
    const input = document.getElementById('voice');
    const recordButton = document.getElementById('voice-record');
    const recordLabel = document.getElementById('voice-record-label');
    const clearButton = document.getElementById('voice-clear');
    const preview = document.getElementById('voice-preview');
    const statusElement = document.getElementById('voice-status');

    const canRecord = window.isSecureContext && !!window.MediaRecorder && !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    const MIME_CANDIDATES = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'];

    let recorder = null;
    let stream = null;
    let chunks = [];
    let timer = null;
    let previewUrl = null;

    recordButton.hidden = !canRecord;

    function setStatus(message) {
        statusElement.textContent = message;
    }

    function extensionFor(type) {
        if (type.includes('webm')) return 'webm';
        if (type.includes('ogg')) return 'ogg';
        if (type.includes('mp4') || type.includes('aac')) return 'm4a';
        return 'webm';
    }

    function showPreview(blob) {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }
        previewUrl = URL.createObjectURL(blob);
        preview.src = previewUrl;
        preview.hidden = false;
        clearButton.hidden = false;
    }

    function clearVoice() {
        stopRecording(true);
        input.value = '';
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }
        preview.removeAttribute('src');
        preview.hidden = true;
        clearButton.hidden = true;
        setStatus('');
    }

    function releaseStream() {
        if (stream) {
            stream.getTracks().forEach((track) => track.stop());
            stream = null;
        }
        clearInterval(timer);
        timer = null;
        recordLabel.textContent = window.t('voice_record');
    }

    function stopRecording(discard) {
        if (recorder && recorder.state !== 'inactive') {
            if (discard) {
                recorder.onstop = null;
            }
            recorder.stop();
        }
        recorder = null;
        if (discard) {
            releaseStream();
        }
    }

    async function startRecording() {
        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch (error) {
            setStatus(window.t('voice_denied'));
            return;
        }

        const mimeType = MIME_CANDIDATES.find((type) => MediaRecorder.isTypeSupported(type)) || '';
        const options = { audioBitsPerSecond: 24000 };
        if (mimeType) {
            options.mimeType = mimeType;
        }

        chunks = [];
        recorder = new MediaRecorder(stream, options);
        recorder.ondataavailable = (event) => {
            if (event.data && event.data.size > 0) {
                chunks.push(event.data);
            }
        };
        recorder.onstop = () => {
            const type = (recorder && recorder.mimeType) || mimeType || 'audio/webm';
            const blob = new Blob(chunks, { type });
            releaseStream();
            recorder = null;

            const file = new File([blob], 'voice.' + extensionFor(type), { type });
            if (file.size > maxBytes) {
                setStatus(window.t('voice_too_large'));
                return;
            }
            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;
            showPreview(blob);
            setStatus(window.t('voice_ready'));
        };

        recorder.start();
        const startedAt = Date.now();
        recordLabel.textContent = window.t('voice_stop');
        timer = setInterval(() => {
            const left = Math.max(0, maxSeconds - Math.floor((Date.now() - startedAt) / 1000));
            setStatus(window.t('voice_recording', { seconds: left }));
            if (left <= 0) {
                stopRecording(false);
            }
        }, 250);
        setStatus(window.t('voice_recording', { seconds: maxSeconds }));
    }

    recordButton.addEventListener('click', () => {
        if (recorder && recorder.state === 'recording') {
            stopRecording(false);
        } else {
            clearVoice();
            startRecording();
        }
    });

    // A file chosen with the file picker / the phone's recorder.
    input.addEventListener('change', () => {
        const file = input.files[0];
        if (!file) {
            return;
        }
        if (file.size > maxBytes) {
            clearVoice();
            setStatus(window.t('voice_too_large'));
            return;
        }

        const url = URL.createObjectURL(file);
        const probe = new Audio();
        probe.preload = 'metadata';
        probe.onloadedmetadata = () => {
            URL.revokeObjectURL(url);
            if (Number.isFinite(probe.duration) && probe.duration > maxSeconds + 1) {
                clearVoice();
                setStatus(window.t('voice_too_long', { max: maxSeconds }));
                return;
            }
            showPreview(file);
            setStatus(window.t('voice_ready'));
        };
        probe.onerror = () => {
            URL.revokeObjectURL(url);
            showPreview(file);
            setStatus(window.t('voice_ready'));
        };
        probe.src = url;
    });

    clearButton.addEventListener('click', clearVoice);
    window.addEventListener('pagehide', () => stopRecording(true));
})();
