(function () {
    const stageUrl = new URL('report_image_stage.php', document.currentScript.src).href;
    const copy = {
        gps_info_title: "GPS PHOTO INFORMATION",
        gps_info_source: "Automatically extracted from the image. These values cannot be edited.",
        gps_info_date: "Photo Date",
        gps_info_time: "Photo Time",
        gps_info_location: "GPS Photo Location",
        gps_info_latitude: "Latitude",
        gps_info_longitude: "Longitude",
        gps_ocr_reading: "Reading GPS Camera information...",
        gps_ocr_detected: "GPS Camera information detected",
        gps_ocr_failed: "GPS information could not be read from this image. Please upload a clear photo taken using the approved GPS Camera app. Make sure the date, time, latitude, longitude, and location are visible.",
        gps_ocr_unavailable: "GPS information could not be read right now. Please try uploading the photo again. Submission is unavailable until extraction succeeds.",
        gps_choose_another: "Choose Another Photo",

        gps_photo_title: 'GPS Camera Photo',
        gps_photo_help: 'Upload a photo taken using a GPS Camera app. The date, time, latitude, longitude, and location/address should be visible on the photo. JPG, PNG, or WEBP up to 6 MB.',
        gps_photo_upload: 'Upload GPS Camera Photo', gps_photo_replace: 'Replace Photo', gps_photo_remove: 'Remove Photo',
        gps_photo_empty: 'No image available', gps_photo_selected: 'GPS Camera Photo Selected', gps_photo_uploaded: 'GPS Camera Photo Uploaded',
        gps_photo_enlarge: 'Enlarge Photo', gps_photo_required: 'Please upload a GPS Camera photo before submitting.',
        gps_photo_invalid: 'Choose a displayable JPG, PNG, or WEBP image up to 6 MB.', gps_photo_checking: 'Checking image...'
    };
    function t(key) {
        const i18n = window.dashboardI18n || window.adminDashboardI18n;
        const value = i18n && i18n.t(key);
        return value && value !== key ? value : copy[key] || key;
    }
    async function validateFile(file) {
        if (!file || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || !file.size || file.size > 6 * 1024 * 1024) throw new Error(t('gps_photo_invalid'));
        const url = URL.createObjectURL(file);
        try {
            await new Promise(function (resolve, reject) { const img = new Image(); img.onload = resolve; img.onerror = reject; img.src = url; });
        } catch (_) { throw new Error(t('gps_photo_invalid')); }
        finally { URL.revokeObjectURL(url); }
    }
    function formatPhotoDate(value) {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
        return match ? `${match[2]}/${match[3]}/${match[1]}` : '';
    }
    function formatPhotoTime(value) {
        const match = /^(\d{2}):(\d{2})(?::(\d{2}))?$/.exec(value || '');
        if (!match) return '';
        const hour = Number(match[1]);
        return `${String(hour % 12 || 12).padStart(2, '0')}:${match[2]}${match[3] === undefined ? '' : ':' + match[3]} ${hour >= 12 ? 'PM' : 'AM'}`;
    }
    function renderDetails(container, photo) {
        container.replaceChildren(); container.classList.add('gps-photo-details'); container.hidden = !photo;
        if (!photo) return;
        const title = document.createElement('h3'); title.textContent = t('gps_info_title');
        const source = document.createElement('p'); source.textContent = t('gps_info_source');
        const list = document.createElement('dl'); list.className = 'gps-photo-grid';
        const values = { date: formatPhotoDate(photo.date), time: formatPhotoTime(photo.time), location: photo.location, latitude: String(Number(photo.latitude)), longitude: String(Number(photo.longitude)) };
        for (const [key, value] of Object.entries(values)) {
            const group = document.createElement('div'); group.className = 'gps-photo-value gps-photo-' + key;
            const label = document.createElement('dt'); label.textContent = t('gps_info_' + key);
            const output = document.createElement('dd'); output.textContent = value || ''; output.dataset.gpsValue = key;
            group.append(label, output); list.append(group);
        }
        container.append(title, source, list);
    }
    async function extractFile(file) {
        await validateFile(file);
        const body = new FormData(); body.append('report_image', file);
        try {
            const response = await fetch(stageUrl, { method: 'POST', body, credentials: 'same-origin', signal: AbortSignal.timeout(65000) });
            const result = await response.json();
            if (!response.ok || !result.success || !result.photo || !result.token) {
                const code = response.status === 503 ? 'gps_ocr_unavailable' : 'gps_ocr_failed';
                throw Object.assign(new Error(t(code)), { code });
            }
            return result;
        } catch (error) {
            if (error.name === 'TimeoutError' || error.name === 'TypeError') throw new Error(t('gps_ocr_unavailable'));
            throw error;
        }
    }
    function init(root) {
        if (root.scaimsReportImageCapture) return;
        root.classList.add('report-image-uploader');
        root.innerHTML = `<div class="report-image-header"><h3 class="report-image-title" data-photo-text="gps_photo_title"></h3><small data-photo-text="gps_photo_help"></small></div>
            <div class="report-image-actions" data-initial-actions><button type="button" class="report-image-button" data-upload data-photo-text="gps_photo_upload"></button></div>
            <input type="file" accept="image/jpeg,image/png,image/webp" hidden data-report-image-input><input type="hidden" data-pending-image-token>
            <div class="report-image-feedback" role="status" aria-live="polite"></div>
            <div class="report-image-preview-wrap" hidden><img alt="GPS Camera Photo"><a data-enlarge target="_blank" rel="noopener" data-photo-text="gps_photo_enlarge"></a>
            <div class="report-image-actions"><button type="button" class="report-image-button" data-replace data-photo-text="gps_photo_replace"></button>
            <button type="button" class="report-image-button report-image-button-danger" data-remove data-photo-text="gps_photo_remove"></button></div></div>`;
        const input = root.querySelector('[data-report-image-input]'), token = root.querySelector('[data-pending-image-token]');
        const preview = root.querySelector('.report-image-preview-wrap'), img = preview.querySelector('img'), feedback = root.querySelector('.report-image-feedback');
        token.name = root.dataset.pendingInputName || 'pending-report-image-token';
        const details = document.createElement('section'); root.append(details);
        let photo = null;
        let locked = root.dataset.locked === 'true', currentUrl = '', objectUrl = '', revision = 0, checking = false, error = '';
        function render() {
            root.querySelectorAll('[data-photo-text]').forEach(el => { el.textContent = t(el.dataset.photoText); });
            preview.hidden = !currentUrl;
            root.querySelector('[data-initial-actions]').hidden = locked || Boolean(currentUrl);
            preview.querySelector('.report-image-actions').hidden = locked;
            root.querySelectorAll('button').forEach(el => { el.disabled = locked || checking; });
            feedback.textContent = (error ? t(error) : '') || (checking ? t('gps_ocr_reading') : photo ? t('gps_ocr_detected') : '');
            feedback.classList.toggle('is-error', Boolean(error)); root.dataset.hasImage = currentUrl && photo && token.value && !checking ? 'true' : 'false';
            root.setAttribute('aria-busy', String(checking));
            feedback.classList.toggle('is-reading', checking);
            renderDetails(details, photo);
            if (error) { root.querySelector('[data-upload]').textContent = t('gps_choose_another'); root.querySelector('[data-replace]').textContent = t('gps_choose_another'); }
            const form = root.closest('form');
            if (form) form.querySelectorAll('.report-submit-action, .submit-report-button').forEach(button => { button.disabled = checking || !photo || !token.value; });
        }
        function change() { render(); root.dispatchEvent(new CustomEvent('scaims:report-image-changed', { bubbles: true, detail: { hasImage: Boolean(currentUrl && photo && token.value) && !checking, photo, token: token.value, file: input.files[0] || null } })); }
        function show(url) {
            currentUrl = url;
            if (url) { img.src = url; root.querySelector('[data-enlarge]').href = url; }
            else { img.removeAttribute('src'); root.querySelector('[data-enlarge]').removeAttribute('href'); }
            change();
        }
        function clear() {
            revision++; checking = false; photo = null; error = ''; input.value = ''; token.value = '';
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = ''; show('');
        }
        function resetToInitial() { clear(); token.value = root.dataset.pendingImageToken || '';
            try { photo = JSON.parse(root.dataset.pendingPhoto || 'null'); } catch (_) { photo = null; }
            show(root.dataset.pendingImageUrl || ''); }
        root.querySelector('[data-upload]').onclick = root.querySelector('[data-replace]').onclick = function () { if (!locked) input.click(); };
        root.querySelector('[data-remove]').onclick = function () { if (!locked) clear(); };
        input.addEventListener('change', async function () {
            const file = input.files[0]; if (!file || locked) return;
            const version = ++revision; checking = true; photo = null; token.value = ''; error = ''; change();
            try {
                await validateFile(file); if (version !== revision) return;
                if (objectUrl) URL.revokeObjectURL(objectUrl);
                objectUrl = URL.createObjectURL(file); show(objectUrl);
                const result = await extractFile(file); if (version !== revision) return;
                token.value = result.token; photo = result.photo; checking = false; change();
            } catch (e) { if (version !== revision) return; photo = null; token.value = ''; checking = false; error = e.code || e.message; change(); }
        });
        img.addEventListener('error', function () { clear(); error = t('gps_photo_invalid'); render(); });
        root.scaimsReportImageCapture = { setLocked(value) { locked = Boolean(value); render(); }, resetToInitial, hasImage() { return Boolean(currentUrl && photo && token.value) && !checking; } };
        document.addEventListener('dashboard:language-changed', render); resetToInitial();
    }
    window.SCAIMSGPSPhoto = { validateFile, extractFile, renderDetails, formatPhotoDate, formatPhotoTime, t, init };
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-report-image-capture]').forEach(init);
        document.querySelectorAll('.report-image-detail img').forEach(function (img) {
            const link = document.createElement('a'); link.target = '_blank'; link.rel = 'noopener'; img.after(link);
            function update() { link.textContent = t('gps_photo_enlarge'); link.hidden = !img.getAttribute('src') || img.hidden; if (!link.hidden) link.href = img.src; }
            img.addEventListener('error', function () { img.hidden = true; img.removeAttribute('src'); });
            new MutationObserver(update).observe(img, { attributes: true, attributeFilter: ['src', 'hidden'] });
            document.addEventListener('dashboard:language-changed', update); update();
        });
    });
})();
