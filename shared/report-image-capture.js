(function () {
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    const maxBytes = 6 * 1024 * 1024;

    function text(root, key, fallback) {
        return root.dataset[key] || fallback;
    }

    function fileName(prefix) {
        return prefix + '-' + Date.now() + '.jpg';
    }

    function setFileInputFile(input, file) {
        if (typeof DataTransfer === 'undefined') {
            return false;
        }

        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        return true;
    }

    function clearFileInput(input) {
        input.value = '';
        if (typeof DataTransfer !== 'undefined') {
            input.files = new DataTransfer().files;
        }
    }

    function validateClientFile(file) {
        if (!file) {
            return 'Please choose an image first.';
        }

        if (allowedTypes.indexOf(file.type) === -1) {
            return 'Unsupported image type. Please upload a JPG, PNG, or WEBP image.';
        }

        if (file.size > maxBytes) {
            return 'The selected image is too large. Please choose an image up to 6 MB.';
        }

        return '';
    }

    function dispatchChange(root, input, hasImage) {
        root.dispatchEvent(new CustomEvent('scaims:report-image-changed', {
            bubbles: true,
            detail: {
                hasImage: hasImage,
                file: input.files && input.files[0] ? input.files[0] : null
            }
        }));
    }

    function initReportImageCapture(root) {
        const inputName = text(root, 'inputName', 'report-image');
        const pendingInputName = text(root, 'pendingInputName', 'pending_report_image_token');
        const label = text(root, 'label', 'Larawan ng Pananim');
        const help = text(root, 'help', 'JPG, PNG, or WEBP up to 6 MB.');
        const captureLabel = text(root, 'captureLabel', 'Kumuha ng Larawan');
        const uploadLabel = text(root, 'uploadLabel', 'Pumili mula sa Gallery');
        const replaceLabel = text(root, 'replaceLabel', 'Palitan ang Larawan');
        const removeLabel = text(root, 'removeLabel', 'Alisin ang Larawan');
        const noImageLabel = text(root, 'noImageLabel', 'Walang larawan.');
        const initialUrl = root.dataset.pendingImageUrl || '';
        const initialToken = root.dataset.pendingImageToken || '';
        let locked = root.dataset.locked === 'true';
        let hasCurrentImage = Boolean(initialUrl);

        root.classList.add('report-image-uploader');
        root.innerHTML = `
            <div class="report-image-header">
                <label class="report-image-title">${label}</label>
                <small>${help}</small>
            </div>
            <div class="report-image-actions" data-initial-actions>
                <button type="button" class="report-image-button" data-capture>${captureLabel}</button>
                <button type="button" class="report-image-button report-image-button-secondary" data-upload>${uploadLabel}</button>
            </div>
            <input type="file" name="${inputName}" accept="image/jpeg,image/png,image/webp" hidden data-report-image-input>
            <input type="hidden" name="${pendingInputName}" value="${initialToken}" data-pending-image-token>
            <div class="report-image-feedback" role="alert" aria-live="polite"></div>
            <div class="report-image-camera" hidden>
                <video playsinline autoplay muted></video>
                <div class="report-image-actions">
                    <button type="button" class="report-image-button" data-use-camera-photo>Use Photo</button>
                    <button type="button" class="report-image-button report-image-button-secondary" data-cancel-camera>Cancel</button>
                </div>
            </div>
            <div class="report-image-preview-wrap" hidden>
                <img alt="${label}">
                <p class="report-image-empty">${noImageLabel}</p>
                <div class="report-image-actions">
                    <button type="button" class="report-image-button" data-replace>${replaceLabel}</button>
                    <button type="button" class="report-image-button report-image-button-danger" data-remove>${removeLabel}</button>
                </div>
            </div>
        `;

        const input = root.querySelector('[data-report-image-input]');
        const pendingInput = root.querySelector('[data-pending-image-token]');
        const feedback = root.querySelector('.report-image-feedback');
        const previewWrap = root.querySelector('.report-image-preview-wrap');
        const previewImage = previewWrap.querySelector('img');
        const emptyLabel = previewWrap.querySelector('.report-image-empty');
        const cameraPanel = root.querySelector('.report-image-camera');
        const video = cameraPanel.querySelector('video');
        const initialActions = root.querySelector('[data-initial-actions]');
        const previewActions = previewWrap.querySelector('.report-image-actions');
        let stream = null;
        let objectUrl = '';

        function setFeedback(message, isError) {
            feedback.textContent = message || '';
            feedback.classList.toggle('is-error', Boolean(isError && message));
        }

        function updateActionVisibility() {
            root.classList.toggle('is-report-image-locked', locked);
            root.querySelectorAll('button').forEach(function (button) {
                button.disabled = locked;
            });

            if (locked) {
                initialActions.hidden = true;
                previewActions.hidden = true;
                return;
            }

            initialActions.hidden = hasCurrentImage;
            previewActions.hidden = !hasCurrentImage;
        }

        function setLocked(nextLocked) {
            locked = Boolean(nextLocked);
            updateActionVisibility();
        }

        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(function (track) {
                    track.stop();
                });
                stream = null;
            }
            video.srcObject = null;
            cameraPanel.hidden = true;
        }

        function showPreview(src, hasImage) {
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = '';
            }

            previewWrap.hidden = false;
            previewImage.hidden = !hasImage;
            emptyLabel.hidden = hasImage;
            hasCurrentImage = hasImage;

            if (hasImage) {
                previewImage.src = src;
            } else {
                previewImage.removeAttribute('src');
            }

            updateActionVisibility();
            dispatchChange(root, input, hasImage);
        }

        function resetToInitial() {
            stopCamera();
            clearFileInput(input);
            pendingInput.value = initialToken;
            setFeedback('', false);

            if (initialUrl) {
                showPreview(initialUrl, true);
                return;
            }

            showPreview('', false);
            previewWrap.hidden = true;
            updateActionVisibility();
        }

        function useSelectedFile(file) {
            if (locked) {
                return;
            }

            const validationMessage = validateClientFile(file);
            if (validationMessage) {
                clearFileInput(input);
                showPreview('', false);
                setFeedback(validationMessage, true);
                return;
            }

            pendingInput.value = '';
            objectUrl = URL.createObjectURL(file);
            showPreview(objectUrl, true);
            setFeedback('', false);
        }

        function openFilePicker(useCapture) {
            stopCamera();
            if (useCapture) {
                input.setAttribute('capture', 'environment');
            } else {
                input.removeAttribute('capture');
            }
            input.click();
        }

        function captureFallback() {
            openFilePicker(true);
        }

        async function startCamera() {
            if (locked) {
                return;
            }

            setFeedback('', false);

            if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
                setFeedback('Camera capture is not available on this browser. You can choose an image from your device instead.', true);
                captureFallback();
                return;
            }

            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' } },
                    audio: false
                });
                video.srcObject = stream;
                cameraPanel.hidden = false;
                previewWrap.hidden = true;
                initialActions.hidden = true;
            } catch (error) {
                const denied = error && (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError');
                const message = denied
                    ? 'Camera permission was denied. Please choose an image from your device instead.'
                    : 'Camera capture is unavailable on this device. Please choose an image from your device instead.';
                setFeedback(message, true);
                cameraPanel.hidden = true;
                initialActions.hidden = false;
            }
        }

        function takeCameraPhoto() {
            if (locked) {
                return;
            }

            if (!stream || !video.videoWidth || !video.videoHeight) {
                setFeedback('The camera is still starting. Please try again in a moment.', true);
                return;
            }

            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

            canvas.toBlob(function (blob) {
                if (!blob) {
                    setFeedback('The photo could not be captured. Please try again or choose an image.', true);
                    return;
                }

                const file = new File([blob], fileName('crop-report-image'), { type: 'image/jpeg' });
                if (!setFileInputFile(input, file)) {
                    setFeedback('This browser cannot attach the captured photo. Please choose an image from your device instead.', true);
                    stopCamera();
                    initialActions.hidden = false;
                    return;
                }

                stopCamera();
                useSelectedFile(file);
            }, 'image/jpeg', 0.86);
        }

        root.querySelector('[data-capture]').addEventListener('click', startCamera);
        root.querySelector('[data-upload]').addEventListener('click', function () {
            if (locked) {
                return;
            }
            openFilePicker(false);
        });
        root.querySelector('[data-replace]').addEventListener('click', function () {
            if (locked) {
                return;
            }
            openFilePicker(false);
        });
        root.querySelector('[data-remove]').addEventListener('click', function () {
            if (locked) {
                return;
            }
            stopCamera();
            clearFileInput(input);
            pendingInput.value = '';
            showPreview('', false);
            previewWrap.hidden = true;
            initialActions.hidden = false;
            setFeedback('', false);
            updateActionVisibility();
        });
        root.querySelector('[data-use-camera-photo]').addEventListener('click', takeCameraPhoto);
        root.querySelector('[data-cancel-camera]').addEventListener('click', function () {
            stopCamera();
            previewWrap.hidden = !(input.files && input.files.length > 0) && !pendingInput.value;
            initialActions.hidden = Boolean(input.files && input.files.length > 0) || Boolean(pendingInput.value);
        });

        input.addEventListener('change', function () {
            const file = input.files && input.files[0] ? input.files[0] : null;
            if (file) {
                useSelectedFile(file);
            }
        });

        if (initialUrl) {
            previewImage.src = initialUrl;
            previewImage.hidden = false;
            emptyLabel.hidden = true;
            previewWrap.hidden = false;
            hasCurrentImage = true;
            updateActionVisibility();
            dispatchChange(root, input, true);
        } else {
            updateActionVisibility();
        }

        root.scaimsReportImageCapture = {
            setLocked,
            resetToInitial
        };
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-report-image-capture]').forEach(initReportImageCapture);
    });
})();
