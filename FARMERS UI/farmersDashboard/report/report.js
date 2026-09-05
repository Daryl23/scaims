function confirmSubmission() {
    const form = getFarmerReportForm();
    if (!form) {
        return;
    }

    if (form && form.dataset.submitting === 'true') {
        return;
    }

    const reportType = document.getElementById('report-type').value;
    
    if (!reportType) {
        alert("Please select a report type.");
        return;
    }
    
    if (reportType === "crop") {
        // Check if all required fields are filled
        const requiredFields = [
            "crop-location",
            "crop-commodity",
            "crop-type",
            "crop-variety",
            "date-planted",
            "harvest-date",
            "estimate-harvest",
            "crop-status",
            "farm-gate-price"
        ];

        for (const fieldId of requiredFields) {
            const field = document.getElementById(fieldId);
            if (!field.value.trim()) {
                alert(`Please fill in the ${field.previousElementSibling.textContent.replace(':', '')}`);
                field.focus();
                return;
            }
        }

        // Validate date fields
        const datePlanted = new Date(document.getElementById("date-planted").value);
        const harvestDate = new Date(document.getElementById("harvest-date").value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (datePlanted > today) {
            alert("Date planted cannot be in the future.");
            return;
        }

        if (harvestDate.getTime() <= datePlanted.getTime()) {
            alert("Estimated harvest date must be after the date planted.");
            return;
        }

        // Validate numerical fields
        const estimateHarvest = document.getElementById("estimate-harvest").value;
        const farmGatePrice = document.getElementById("farm-gate-price").value;

        if (isNaN(estimateHarvest) || estimateHarvest <= 0) {
            alert("Please enter a valid positive number for estimated harvest.");
            return;
        }

        if (isNaN(farmGatePrice) || farmGatePrice <= 0) {
            alert("Please enter a valid positive number for farm gate price.");
            return;
        }

    } else if (reportType === "damage") {
        // Check if all required fields are filled
        const requiredFields = [
            "damage-location",
            "damage-crop-commodity",
            "damage-crop-type",
            "damage-crop-variety",
            "date-occurrence",
            "damage-cause",
            "damage-percentage",
            "estimated-loss",
            "damage-farm-gate-price"
        ];

        for (const fieldId of requiredFields) {
            const field = document.getElementById(fieldId);
            if (!field.value.trim()) {
                alert(`Please fill in the ${field.previousElementSibling.textContent.replace(':', '')}`);
                field.focus();
                return;
            }
        }

        // Validate date of occurrence
        const dateOccurrence = new Date(document.getElementById("date-occurrence").value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        if (dateOccurrence > today) {
            alert("Date of occurrence cannot be in the future.");
            return;
        }

        // Validate numerical fields
        const estimatedLoss = document.getElementById("estimated-loss").value;
        const damagePercentage = document.getElementById("damage-percentage").value;
        const farmGatePrice = document.getElementById("damage-farm-gate-price").value;

        if (isNaN(estimatedLoss) || estimatedLoss <= 0) {
            alert("Please enter a valid positive number for estimated loss.");
            return;
        }

        if (isNaN(damagePercentage) || damagePercentage < 0 || damagePercentage > 100) {
            alert("Please enter a valid percentage between 0 and 100.");
            return;
        }

        if (isNaN(farmGatePrice) || farmGatePrice <= 0) {
            alert("Please enter a valid positive number for farm gate price.");
            return;
        }
    }

    // If all validations pass, show confirmation dialog
    const confirmation = confirm("Are you sure you want to submit this report? Please review your information before proceeding.");
    if (confirmation) {
        if (!prepareCoordinateFieldsForSubmit(reportType)) {
            return;
        }
        setNewReportEditMode(reportType, true);
        setFarmerReportSubmitting(true);
        form.submit();
    }
}

function getFarmerReportForm() {
    return document.getElementById('farmer-report-form') || document.querySelector('.report-form form');
}

const NEW_REPORT_FIELD_MAP = {
    crop: {
        sectionId: 'crop-report-fields',
        fields: {
            'crop-location': 'location',
            'crop-commodity': 'crop_commodity',
            'crop-type': 'crop_type',
            'crop-variety': 'crop_variety',
            'date-planted': 'date_planted',
            'harvest-date': 'harvest_date',
            'estimate-harvest': 'estimate_harvest',
            'crop-status': 'crop_status',
            'number-of-trees': 'number_of_trees',
            'farm-gate-price': 'farm_gate_price',
            'crop-latitude': 'latitude',
            'crop-longitude': 'longitude'
        },
        coordinateMode: 'crop-coordinate-mode',
        coordinateInput: 'crop-coordinate-input'
    },
    damage: {
        sectionId: 'damage-report-fields',
        fields: {
            'damage-location': 'location',
            'damage-crop-commodity': 'crop_commodity',
            'damage-crop-type': 'crop_type',
            'damage-crop-variety': 'crop_variety',
            'date-occurrence': 'date_occurrence',
            'damage-cause': 'damage_cause',
            'damage-percentage': 'percent_damage',
            'estimated-loss': 'estimated_loss',
            'damage-farm-gate-price': 'farm_gate_price',
            'damage-number-of-trees': 'number_of_trees',
            'damage-latitude': 'latitude',
            'damage-longitude': 'longitude'
        },
        coordinateMode: 'damage-coordinate-mode',
        coordinateInput: 'damage-coordinate-input'
    }
};

const newReportState = {
    crop: { prefilled: false, snapshot: null, editing: false, saving: false },
    damage: { prefilled: false, snapshot: null, editing: false, saving: false }
};

function normalizeCommodityKey(value) {
    if (window.SCAIMSCropCatalog && typeof window.SCAIMSCropCatalog.normalizeCommodityKey === 'function') {
        return window.SCAIMSCropCatalog.normalizeCommodityKey(value);
    }

    const key = String(value || '').toLowerCase().trim().replace(/[-\s]+/g, '_');
    return key === 'root_crops' || key === 'root_crop' ? 'root' : key;
}

function setSelectValue(select, value) {
    if (!select || value === null || typeof value === 'undefined') {
        return;
    }

    const normalizedValue = String(value);
    if (normalizedValue === '') {
        select.value = '';
        return;
    }

    const hasOption = Array.from(select.options).some(function (option) {
        return option.value === normalizedValue;
    });

    if (!hasOption) {
        const option = document.createElement('option');
        option.value = normalizedValue;
        option.textContent = normalizedValue;
        select.appendChild(option);
    }

    select.value = normalizedValue;
}

function setFieldValue(fieldId, value) {
    const field = document.getElementById(fieldId);
    if (!field || value === null || typeof value === 'undefined' || value === '') {
        return;
    }

    if (field.tagName === 'SELECT') {
        setSelectValue(field, value);
        return;
    }

    field.value = value;
}

function getPrefillForReportType(reportType) {
    const byType = window.farmerReportPrefillByType || {};
    if (byType[reportType]) {
        return byType[reportType];
    }

    return window.farmerReportPrefill || {};
}

function getSectionForReportType(reportType) {
    const config = NEW_REPORT_FIELD_MAP[reportType];
    return config ? document.getElementById(config.sectionId) : null;
}

function getNewReportSnapshot(reportType) {
    const config = NEW_REPORT_FIELD_MAP[reportType];
    const snapshot = {};
    if (!config) {
        return snapshot;
    }

    Object.keys(config.fields).forEach(function (fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
            snapshot[fieldId] = field.value;
        }
    });

    [config.coordinateMode, config.coordinateInput].forEach(function (fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
            snapshot[fieldId] = field.value;
        }
    });

    return snapshot;
}

function restoreNewReportSnapshot(reportType, snapshot) {
    if (!snapshot) {
        return;
    }

    Object.keys(snapshot).forEach(function (fieldId) {
        setFieldValue(fieldId, snapshot[fieldId]);
    });
    syncNewReportCoordinateControl(reportType);
}

function syncNewReportCoordinateControl(reportType) {
    const config = NEW_REPORT_FIELD_MAP[reportType];
    if (!config) {
        return;
    }

    const mode = document.getElementById(config.coordinateMode);
    const coordinateInput = document.getElementById(config.coordinateInput);
    const action = document.getElementById(reportType + '-coordinate-action');
    const status = document.getElementById(reportType + '-coordinate-status');

    if (!mode || !coordinateInput || !action) {
        return;
    }

    if (mode.value === 'auto') {
        action.style.display = 'block';
        coordinateInput.disabled = true;
        coordinateInput.readOnly = true;
        if (status) {
            status.textContent = 'Getting your current location...';
        }
        return;
    }

    if (mode.value === 'manual') {
        action.style.display = 'none';
        coordinateInput.disabled = false;
        coordinateInput.readOnly = false;
        if (status) {
            status.textContent = 'Use the Google Maps or Waze format: 13°50\'11.2"N 121°16\'50.5"E';
        }
        return;
    }

    action.style.display = 'none';
    coordinateInput.disabled = true;
    coordinateInput.readOnly = true;
    if (status) {
        status.textContent = 'Allow location access to fill latitude and longitude automatically.';
    }
}

function applyNewReportPrefill(reportType) {
    const config = NEW_REPORT_FIELD_MAP[reportType];
    const state = newReportState[reportType];
    const prefill = getPrefillForReportType(reportType);

    if (!config || !state || state.prefilled) {
        return;
    }

    const commodityFieldId = reportType === 'crop' ? 'crop-commodity' : 'damage-crop-commodity';
    const cropTypeFieldId = reportType === 'crop' ? 'crop-type' : 'damage-crop-type';
    const commodity = normalizeCommodityKey(prefill.crop_commodity || '');

    Object.keys(config.fields).forEach(function (fieldId) {
        if (fieldId === commodityFieldId || fieldId === cropTypeFieldId) {
            return;
        }
        setFieldValue(fieldId, prefill[config.fields[fieldId]]);
    });

    if (commodity) {
        setFieldValue(commodityFieldId, commodity);
        if (reportType === 'crop') {
            updateCropTypes();
        } else {
            updateCropDamageTypes();
        }
    }

    setFieldValue(cropTypeFieldId, prefill.crop_type);

    if (prefill.latitude && prefill.longitude) {
        setFieldValue(config.coordinateMode, 'manual');
        setFieldValue(config.coordinateInput, formatCoordinatePair(prefill.latitude, prefill.longitude));
        syncNewReportCoordinateControl(reportType);
    }

    state.prefilled = true;
    state.snapshot = getNewReportSnapshot(reportType);
}

function setNewReportEditMode(reportType, editing) {
    const config = NEW_REPORT_FIELD_MAP[reportType];
    const state = newReportState[reportType];
    const section = getSectionForReportType(reportType);

    if (!config || !state || !section) {
        return;
    }

    state.editing = editing;
    section.classList.toggle('report-section-locked', !editing);
    section.classList.toggle('report-section-editing', editing);

    section.querySelectorAll('input, select, textarea, button').forEach(function (field) {
        if (field.type === 'hidden' || field.classList.contains('report-submit-action') || field.dataset.newReportEdit || field.dataset.newReportSave || field.dataset.newReportCancel) {
            return;
        }

        if (field.closest('[data-report-image-capture]')) {
            return;
        }

        if (field.tagName === 'SELECT' || field.type === 'date' || field.classList.contains('gps-button')) {
            field.disabled = !editing;
            field.classList.toggle('is-readonly-field', !editing);
        } else {
            field.disabled = false;
            field.readOnly = !editing;
            field.classList.toggle('is-readonly-field', !editing);
        }
    });

    const imageComponent = section.querySelector('[data-report-image-capture]');
    if (imageComponent && imageComponent.scaimsReportImageCapture) {
        imageComponent.scaimsReportImageCapture.setLocked(!editing);
    }

    const editButton = section.querySelector('[data-new-report-edit]');
    const saveButton = section.querySelector('[data-new-report-save]');
    const cancelButton = section.querySelector('[data-new-report-cancel]');
    if (editButton) {
        editButton.hidden = editing;
    }
    if (saveButton) {
        saveButton.hidden = !editing;
    }
    if (cancelButton) {
        cancelButton.hidden = !editing;
    }
}

function setNewReportMessage(reportType, message, isError) {
    const messageEl = document.querySelector('[data-new-report-message="' + reportType + '"]');
    if (!messageEl) {
        return;
    }

    messageEl.textContent = message || '';
    messageEl.classList.toggle('is-error', Boolean(isError && message));
}

function setNewReportDefaultSaveState(reportType, isSaving) {
    const state = newReportState[reportType];
    const section = getSectionForReportType(reportType);
    if (!state || !section) {
        return;
    }

    state.saving = isSaving;
    const saveButton = section.querySelector('[data-new-report-save]');
    const cancelButton = section.querySelector('[data-new-report-cancel]');
    const editButton = section.querySelector('[data-new-report-edit]');

    [saveButton, cancelButton, editButton].forEach(function (button) {
        if (button) {
            button.disabled = isSaving;
        }
    });

    if (saveButton) {
        if (isSaving) {
            saveButton.dataset.originalText = saveButton.textContent;
            saveButton.textContent = 'Saving...';
        } else if (saveButton.dataset.originalText) {
            saveButton.textContent = saveButton.dataset.originalText;
        }
    }
}

function saveNewReportDefaults(reportType) {
    const form = getFarmerReportForm();
    const state = newReportState[reportType];
    if (!form || !state || state.saving) {
        return;
    }

    if (!prepareCoordinateFieldsForSubmit(reportType)) {
        return;
    }

    const formData = new FormData(form);
    formData.set('report-type', reportType);
    setNewReportDefaultSaveState(reportType, true);
    setNewReportMessage(reportType, 'Saving report data...', false);

    fetch('report_defaults_save.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
        .then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Unable to save report data.');
                }
                return data;
            });
        })
        .then(function (data) {
            window.farmerReportPrefillByType = window.farmerReportPrefillByType || {};
            window.farmerReportPrefillByType[reportType] = data.defaults || getNewReportSnapshot(reportType);
            state.snapshot = getNewReportSnapshot(reportType);
            setNewReportEditMode(reportType, false);
            setNewReportMessage(reportType, 'Saved. This data will load next time from Home or Reports.', false);
        })
        .catch(function (error) {
            setNewReportMessage(reportType, error.message || 'Network interruption. Please try saving again.', true);
        })
        .finally(function () {
            setNewReportDefaultSaveState(reportType, false);
        });
}

function resetNewReportEdit(reportType) {
    const section = getSectionForReportType(reportType);
    const imageComponent = section ? section.querySelector('[data-report-image-capture]') : null;
    restoreNewReportSnapshot(reportType, newReportState[reportType].snapshot);
    if (imageComponent && imageComponent.scaimsReportImageCapture) {
        imageComponent.scaimsReportImageCapture.resetToInitial();
    }
    setNewReportEditMode(reportType, false);
    setNewReportMessage(reportType, '', false);
}

function activateNewReportType(reportType) {
    if (!NEW_REPORT_FIELD_MAP[reportType]) {
        return;
    }

    applyNewReportPrefill(reportType);
    setNewReportEditMode(reportType, newReportState[reportType].editing);
}

function setFarmerReportSubmitting(isSubmitting) {
    const form = getFarmerReportForm();
    if (!form) {
        return;
    }

    form.dataset.submitting = isSubmitting ? 'true' : 'false';
    form.querySelectorAll('.report-submit-action').forEach(function (button) {
        button.disabled = isSubmitting;
        if (isSubmitting) {
            button.dataset.originalText = button.textContent;
            button.textContent = 'Uploading...';
        } else if (button.dataset.originalText) {
            button.textContent = button.dataset.originalText;
        }
    });
}

function toggleCropReport() {
    const reportType = document.getElementById('report-type').value;
    const cropReportFields = document.getElementById('crop-report-fields');
    const damageReportFields = document.getElementById('damage-report-fields');

    if (reportType === 'crop') {
        cropReportFields.style.display = 'block';
        damageReportFields.style.display = 'none';
        activateNewReportType('crop');
    } else if (reportType === 'damage') {
        cropReportFields.style.display = 'none';
        damageReportFields.style.display = 'block';
        activateNewReportType('damage');
    }
}

function normalizeCoordinateNumber(value, min, max) {
    if (value === '' || value === null || typeof value === 'undefined') {
        return '';
    }

    const parsedValue = Number.parseFloat(value);
    if (!Number.isFinite(parsedValue) || parsedValue < min || parsedValue > max) {
        return null;
    }

    return parsedValue.toFixed(7);
}

function convertDmsToDecimal(degrees, minutes, seconds, direction) {
    const absoluteValue = Number(degrees) + (Number(minutes) / 60) + (Number(seconds) / 3600);
    const upperDirection = String(direction || '').toUpperCase();
    const signedValue = (upperDirection === 'S' || upperDirection === 'W') ? -absoluteValue : absoluteValue;
    return signedValue.toFixed(7);
}

function convertDecimalToDms(value, positiveDirection, negativeDirection) {
    const numericValue = Number.parseFloat(value);
    if (!Number.isFinite(numericValue)) {
        return '';
    }

    const absoluteValue = Math.abs(numericValue);
    const degrees = Math.floor(absoluteValue);
    const minutesFloat = (absoluteValue - degrees) * 60;
    const minutes = Math.floor(minutesFloat);
    let seconds = (minutesFloat - minutes) * 60;
    seconds = Math.round(seconds * 10) / 10;

    let normalizedDegrees = degrees;
    let normalizedMinutes = minutes;
    let normalizedSeconds = seconds;

    if (normalizedSeconds >= 60) {
        normalizedSeconds = 0;
        normalizedMinutes += 1;
    }

    if (normalizedMinutes >= 60) {
        normalizedMinutes = 0;
        normalizedDegrees += 1;
    }

    const direction = numericValue >= 0 ? positiveDirection : negativeDirection;
    return `${normalizedDegrees}°${normalizedMinutes}'${normalizedSeconds.toFixed(1)}"${direction}`;
}

function parseCoordinatePair(value) {
    const trimmedValue = String(value || '').trim();
    if (trimmedValue === '') {
        return { empty: true, valid: true, latitude: '', longitude: '' };
    }

    let match = trimmedValue.match(/(-?\d+(?:\.\d+)?)\s*[, ]\s*(-?\d+(?:\.\d+)?)/);
    if (!match) {
        match = trimmedValue.match(/(\d{1,3})\s*[°º]\s*(\d{1,2})\s*['′]\s*(\d{1,2}(?:\.\d+)?)\s*(?:"|″)?\s*([NS])[\s,]+(\d{1,3})\s*[°º]\s*(\d{1,2})\s*['′]\s*(\d{1,2}(?:\.\d+)?)\s*(?:"|″)?\s*([EW])/i);
        if (!match) {
            return { empty: false, valid: false, latitude: '', longitude: '' };
        }

        const latitudeFromDms = normalizeCoordinateNumber(convertDmsToDecimal(match[1], match[2], match[3], match[4]), -90, 90);
        const longitudeFromDms = normalizeCoordinateNumber(convertDmsToDecimal(match[5], match[6], match[7], match[8]), -180, 180);

        if (latitudeFromDms === null || longitudeFromDms === null) {
            return { empty: false, valid: false, latitude: '', longitude: '' };
        }

        return { empty: false, valid: true, latitude: latitudeFromDms, longitude: longitudeFromDms };
    }

    const latitude = normalizeCoordinateNumber(match[1], -90, 90);
    const longitude = normalizeCoordinateNumber(match[2], -180, 180);

    if (latitude === null || longitude === null) {
        return { empty: false, valid: false, latitude: '', longitude: '' };
    }

    return { empty: false, valid: true, latitude, longitude };
}

function formatCoordinatePair(latitude, longitude) {
    if (!latitude || !longitude) {
        return '';
    }

    return `${convertDecimalToDms(latitude, 'N', 'S')} ${convertDecimalToDms(longitude, 'E', 'W')}`;
}

function initCoordinateControls(prefix) {
    const mode = document.getElementById(prefix + '-coordinate-mode');
    const coordinateInput = document.getElementById(prefix + '-coordinate-input');
    const latitude = document.getElementById(prefix + '-latitude');
    const longitude = document.getElementById(prefix + '-longitude');
    const gpsButton = document.getElementById(prefix + '-use-gps');
    const action = document.getElementById(prefix + '-coordinate-action');
    const status = document.getElementById(prefix + '-coordinate-status');

    if (!mode || !coordinateInput || !latitude || !longitude || !action) {
        return;
    }

    function clearValues() {
        coordinateInput.value = '';
        latitude.value = '';
        longitude.value = '';
    }

    function setCoordinateValues(nextLatitude, nextLongitude) {
        latitude.value = nextLatitude;
        longitude.value = nextLongitude;
        coordinateInput.value = formatCoordinatePair(nextLatitude, nextLongitude);
    }

    function updateManualCoordinates(showAlert) {
        const parsedCoordinates = parseCoordinatePair(coordinateInput.value);

        if (parsedCoordinates.empty) {
            latitude.value = '';
            longitude.value = '';
            return true;
        }

        if (!parsedCoordinates.valid) {
            latitude.value = '';
            longitude.value = '';
            if (showAlert) {
                alert('Use the Google Maps or Waze format: 13°50\'11.2"N 121°16\'50.5"E');
            }
            return false;
        }

        setCoordinateValues(parsedCoordinates.latitude, parsedCoordinates.longitude);
        return true;
    }

    function requestCurrentLocation() {
        if (!navigator.geolocation) {
            alert('Geolocation is not supported by this browser.');
            return;
        }

        if (status) {
            status.textContent = 'Getting your current location...';
        }

        navigator.geolocation.getCurrentPosition(
            function (position) {
                setCoordinateValues(
                    position.coords.latitude.toFixed(7),
                    position.coords.longitude.toFixed(7)
                );
                if (status) {
                    status.textContent = 'Current GPS location loaded.';
                }
            },
            function (error) {
                const message = error && error.message ? error.message : 'Unable to get your current location.';
                if (status) {
                    status.textContent = message;
                }
                alert(message);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    }

    function updateMode() {
        clearValues();

        if (mode.value === 'auto') {
            action.style.display = 'block';
            coordinateInput.disabled = true;
            coordinateInput.readOnly = true;
            if (status) {
                status.textContent = 'Getting your current location...';
            }
            requestCurrentLocation();
        } else if (mode.value === 'manual') {
            action.style.display = 'none';
            coordinateInput.disabled = false;
            coordinateInput.readOnly = false;
            if (status) {
                status.textContent = 'Use the Google Maps or Waze format: 13°50\'11.2"N 121°16\'50.5"E';
            }
        } else {
            action.style.display = 'none';
            clearValues();
            coordinateInput.disabled = true;
            coordinateInput.readOnly = true;
            if (status) {
                status.textContent = 'Allow location access to fill latitude and longitude automatically.';
            }
        }
    }

    mode.addEventListener('change', updateMode);
    updateMode();

    if (gpsButton) {
        gpsButton.addEventListener('click', requestCurrentLocation);
    }

    coordinateInput.addEventListener('input', function () {
        if (mode.value === 'manual') {
            updateManualCoordinates(false);
        }
    });

    coordinateInput.addEventListener('blur', function () {
        if (mode.value === 'manual') {
            updateManualCoordinates(false);
        }
    });
}

function prepareCoordinateFieldsForSubmit(prefix) {
    const mode = document.getElementById(prefix + '-coordinate-mode');
    const coordinateInput = document.getElementById(prefix + '-coordinate-input');
    const latitude = document.getElementById(prefix + '-latitude');
    const longitude = document.getElementById(prefix + '-longitude');

    if (!mode || !coordinateInput || !latitude || !longitude) {
        return true;
    }

    if (mode.value === '') {
        clearCoordinateFields(prefix);
        return true;
    }

    if (mode.value === 'auto') {
        if (!latitude.value || !longitude.value) {
            alert('Please allow GPS access or click "Use Current Location" before submitting the report.');
            return false;
        }
        coordinateInput.value = formatCoordinatePair(latitude.value, longitude.value);
        return true;
    }

    if (mode.value === 'manual') {
        const parsedCoordinates = parseCoordinatePair(coordinateInput.value);
        if (parsedCoordinates.empty) {
            alert('Please enter the coordinates in Google Maps or Waze format: 13°50\'11.2"N 121°16\'50.5"E');
            return false;
        }

        if (!parsedCoordinates.valid) {
            alert('Coordinates are invalid. Use the format: 13°50\'11.2"N 121°16\'50.5"E');
            return false;
        }

        latitude.value = parsedCoordinates.latitude;
        longitude.value = parsedCoordinates.longitude;
        coordinateInput.value = formatCoordinatePair(parsedCoordinates.latitude, parsedCoordinates.longitude);
    }

    return true;
}

function clearCoordinateFields(prefix) {
    const coordinateInput = document.getElementById(prefix + '-coordinate-input');
    const latitude = document.getElementById(prefix + '-latitude');
    const longitude = document.getElementById(prefix + '-longitude');

    if (coordinateInput) {
        coordinateInput.value = '';
    }

    if (latitude) {
        latitude.value = '';
    }

    if (longitude) {
        longitude.value = '';
    }
}

const cropTypes = {
    grains: ["Rice", "Corn", "Other"],
    plantation: ["Coconut", "Coffee", "Sugarcane", "Black Pepper", "Cacao", "Other"],
    root: ["Sweet Potato", "Cassava", "Ube/Yam", "Ginger", "Gabi", "Other"],
    vegetables: ["Ampalaya", "Bell Pepper", "Bush Sitao", "Chayote", "Cowpea", "Cucumber", "Eggplant", "Hot Pepper", "Ladyfinger (Okra)", "Lettuce", 
                 "Mung Bean", "Mustard", "Pechay", "Pole Sitao", "Radish", "Snap Beans", "Soybeans", "Squash", "Tomato", "Upo", "Patola", "Sigarilyas", "Others"],
    fruits: ["Avocado", "Banana", "Calamansi", "Citrus", "Pomelo", "Papaya", "Dragon Fruit", "Durian", "Guyabano", "Jackfruit", "Lanzones", "Mango", 
             "Mango Pajo", "Mangosteen", "Pineapple", "Rambutan", "Santol", "Tamarind", "Pili", "Other"]
};

function getFarmerCropTypes(commodity) {
    if (window.SCAIMSCropCatalog && typeof window.SCAIMSCropCatalog.getTypes === 'function') {
        return window.SCAIMSCropCatalog.getTypes(commodity, { includeOther: true });
    }

    return cropTypes[commodity] || [];
}

function updateCropTypes() {
    const commodity = document.getElementById('crop-commodity').value;
    const cropTypeSelect = document.getElementById('crop-type');
    const otherCropTypeInput = document.getElementById('other-crop-type');
    const selectedCropType = cropTypeSelect.value;
    
    cropTypeSelect.innerHTML = '<option value="" disabled selected>' + window.dashboardI18n.t('report_choose_crop_type') + '</option>';

    if (commodity) {
        getFarmerCropTypes(commodity).forEach(type => {
            const option = document.createElement('option');
            option.value = type;
            option.textContent = type;
            cropTypeSelect.appendChild(option);
        });
    }

    setSelectValue(cropTypeSelect, selectedCropType);

    // Add event listener for the crop type select
    cropTypeSelect.addEventListener('change', function() {
        if (this.value === 'Other') {
            otherCropTypeInput.style.display = 'block';
            otherCropTypeInput.required = true;
        } else {
            otherCropTypeInput.style.display = 'none';
            otherCropTypeInput.required = false;
            otherCropTypeInput.value = '';
        }
    });
}

const cropDamageTypes = {
    grains: ["Rice", "Corn", "Other"],
    plantation: ["Coconut", "Coffee", "Sugarcane", "Black Pepper", "Cacao", "Other"],
    root: ["Sweet Potato", "Cassava", "Ube/Yam", "Ginger", "Gabi", "Other"],
    vegetables: ["Ampalaya", "Bell Pepper", "Bush Sitao", "Chayote", "Cowpea", "Cucumber", "Eggplant", "Hot Pepper", "Ladyfinger (Okra)", "Lettuce", 
                "Mung Bean", "Mustard", "Pechay", "Pole Sitao", "Radish", "Snap Beans", "Soybeans", "Squash", "Tomato", "Upo", "Patola", "Sigarilyas", "Others"],
    fruits: ["Avocado", "Banana", "Calamansi", "Citrus", "Pomelo", "Papaya", "Dragon Fruit", "Durian", "Guyabano", "Jackfruit", "Lanzones", "Mango", 
            "Mango Pajo", "Mangosteen", "Pineapple", "Rambutan", "Santol", "Tamarind", "Pili", "Other"]
};

function getFarmerDamageCropTypes(commodity) {
    if (window.SCAIMSCropCatalog && typeof window.SCAIMSCropCatalog.getTypes === 'function') {
        return window.SCAIMSCropCatalog.getTypes(commodity, { includeOther: true });
    }

    return cropDamageTypes[commodity] || [];
}

function updateCropDamageTypes() {
    const commodity = document.getElementById("damage-crop-commodity").value;
    const cropTypeSelect = document.getElementById("damage-crop-type");
    const otherDamageCropTypeInput = document.getElementById('other-damage-crop-type');
    const selectedCropType = cropTypeSelect.value;

    cropTypeSelect.innerHTML = '<option value="" disabled selected>' + window.dashboardI18n.t('report_choose_crop_type') + '</option>';

    if (commodity) {
        getFarmerDamageCropTypes(commodity).forEach(type => {
            const option = document.createElement("option");
            option.value = type;
            option.textContent = type;
            cropTypeSelect.appendChild(option);
        });
    }

    setSelectValue(cropTypeSelect, selectedCropType);

    // Add event listener for the damage crop type select
    cropTypeSelect.addEventListener('change', function() {
        if (this.value === 'Other') {
            otherDamageCropTypeInput.style.display = 'block';
            otherDamageCropTypeInput.required = true;
        } else {
            otherDamageCropTypeInput.style.display = 'none';
            otherDamageCropTypeInput.required = false;
            otherDamageCropTypeInput.value = '';
        }
    });
}

document.addEventListener('scaims:crop-catalog-ready', function() {
    const cropCommodity = document.getElementById('crop-commodity');
    const damageCommodity = document.getElementById('damage-crop-commodity');

    if (cropCommodity && cropCommodity.value) {
        updateCropTypes();
    }
    if (damageCommodity && damageCommodity.value) {
        updateCropDamageTypes();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const form = getFarmerReportForm();
    const initialReportType = form ? form.dataset.initialReportType : '';
    const reportTypeSelect = document.getElementById('report-type');

    function setText(selector, key) {
        const element = document.querySelector(selector);
        if (element) {
            element.textContent = window.dashboardI18n.t(key);
        }
    }

    function setOptionText(selector, key) {
        const option = document.querySelector(selector);
        if (option) {
            option.textContent = window.dashboardI18n.t(key);
        }
    }

    function applyReportLanguage() {
        setText('#crop-report-fields h3', 'report_crop_heading');
        setText('#damage-report-fields h3', 'report_damage_heading');
        setText('label[for="crop-location"]', 'report_location');
        setText('label[for="crop-commodity"]', 'report_crop_commodity');
        setText('label[for="crop-type"]', 'report_crop_type');
        setText('label[for="crop-variety"]', 'report_crop_variety');
        setText('label[for="date-planted"]', 'report_date_planted');
        setText('label[for="harvest-date"]', 'report_harvest_date');
        setText('label[for="estimate-harvest"]', 'report_estimated_harvest');
        setText('label[for="crop-status"]', 'report_crop_status');
        setText('label[for="number-of-trees"]', 'report_number_of_trees');
        setText('label[for="farm-gate-price"]', 'report_farm_gate_price');
        setText('label[for="damage-location"]', 'report_location');
        setText('label[for="damage-crop-commodity"]', 'report_damage_crop_commodity');
        setText('label[for="damage-crop-type"]', 'report_damage_crop_type');
        setText('label[for="damage-crop-variety"]', 'report_damage_crop_variety');
        setText('label[for="date-occurrence"]', 'report_date_occurrence');
        setText('label[for="damage-cause"]', 'report_damage_cause');
        setText('label[for="damage-percentage"]', 'report_damage_percentage');
        setText('label[for="estimated-loss"]', 'report_estimated_loss');
        setText('label[for="damage-farm-gate-price"]', 'report_damage_farm_gate_price');
        setText('label[for="damage-number-of-trees"]', 'report_number_of_trees');

        setOptionText('#crop-location option[value=""]', 'report_choose_location');
        setOptionText('#damage-location option[value=""]', 'report_choose_location');
        setOptionText('#crop-commodity option[value=""]', 'report_choose_commodity');
        setOptionText('#damage-crop-commodity option[value=""]', 'report_choose_commodity');
        setOptionText('#crop-type option[value=""]', 'report_choose_crop_type');
        setOptionText('#damage-crop-type option[value=""]', 'report_choose_crop_type');
        setOptionText('#crop-status option[value=""]', 'report_choose_status');

        document.querySelectorAll('.report-submit-action').forEach(function(button) {
            button.textContent = window.dashboardI18n.t('report_submit');
        });

        const otherCropType = document.getElementById('other-crop-type');
        const otherDamageCropType = document.getElementById('other-damage-crop-type');
        if (otherCropType) {
            otherCropType.placeholder = window.dashboardI18n.t('report_please_specify');
        }
        if (otherDamageCropType) {
            otherDamageCropType.placeholder = window.dashboardI18n.t('report_please_specify');
        }
    }

    // Add input validation for numerical fields
    const numericInputs = [
        document.getElementById("estimate-harvest"),
        document.getElementById("farm-gate-price"),
        document.getElementById("estimated-loss"),
        document.getElementById("damage-farm-gate-price"),
        document.getElementById("damage-percentage")
    ];

    numericInputs.forEach(input => {
        if (input) {
            input.addEventListener('input', function() {
                validateNumber(this);
            });
        }
    });

    // Add input validation for date inputs
    const datePlanted = document.getElementById("date-planted");
    const harvestDate = document.getElementById("harvest-date");
    const dateOccurrence = document.getElementById("date-occurrence");
    
    // Set max date for date planted and date of occurrence
    const today = new Date().toISOString().split("T")[0];
    if (datePlanted) {
        datePlanted.max = today;
    }
    if (dateOccurrence) {
        dateOccurrence.max = today;
    }
    
    if (datePlanted && harvestDate) {
        datePlanted.addEventListener('change', function() {
            harvestDate.min = this.value;
        });
    }

    initCoordinateControls('crop');
    initCoordinateControls('damage');

    applyReportLanguage();
    document.addEventListener('dashboard:language-changed', applyReportLanguage);

    document.querySelectorAll('[data-new-report-edit]').forEach(function (button) {
        button.addEventListener('click', function () {
            setNewReportMessage(button.dataset.newReportEdit, '', false);
            setNewReportEditMode(button.dataset.newReportEdit, true);
        });
    });

    document.querySelectorAll('[data-new-report-save]').forEach(function (button) {
        button.addEventListener('click', function () {
            saveNewReportDefaults(button.dataset.newReportSave);
        });
    });

    document.querySelectorAll('[data-new-report-cancel]').forEach(function (button) {
        button.addEventListener('click', function () {
            resetNewReportEdit(button.dataset.newReportCancel);
        });
    });

    if (initialReportType && reportTypeSelect) {
        reportTypeSelect.value = initialReportType;
        toggleCropReport();
    } else if (reportTypeSelect && reportTypeSelect.value) {
        toggleCropReport();
    }

    initFarmerReportDetails();
}); 

// Add input validation for numerical fields
function validateNumber(input) {
    input.value = input.value.replace(/[^0-9.]/g, '');
    if (input.value.split('.').length > 2) input.value = input.value.replace(/\.+$/, '');
}

// Add event listener for form submission
const farmerReportForm = getFarmerReportForm();
if (farmerReportForm) {
    farmerReportForm.addEventListener('submit', function(e) {
        e.preventDefault();

        if (this.dataset.submitting === 'true') {
            return;
        }
    
        const reportType = document.getElementById('report-type').value;
        if (!reportType) {
            alert('Please select a report type');
            return;
        }

        // Confirm submission
        if (confirm('Are you sure you want to submit this report?')) {
            if (!prepareCoordinateFieldsForSubmit(reportType)) {
                return;
            }
            setNewReportEditMode(reportType, true);
            setFarmerReportSubmitting(true);
            this.submit();
        }
    });
}

function initFarmerReportDetails() {
    const reports = Array.isArray(window.farmerSubmittedReports) ? window.farmerSubmittedReports : [];
    const modal = document.getElementById('farmerReportModal');
    const closeButton = document.querySelector('.farmer-report-modal-close');
    const title = document.getElementById('farmerReportModalTitle');
    const detailGrid = document.getElementById('farmerReportDetails');
    const image = document.getElementById('farmerReportDetailImage');
    const noImage = document.getElementById('farmerReportNoImage');
    const editForm = document.getElementById('farmerReportEditForm');
    const editFields = document.getElementById('farmerReportEditFields');
    const viewActions = document.getElementById('farmerReportViewActions');
    const editButton = document.getElementById('farmerReportEditButton');
    const cancelEditButton = document.getElementById('farmerReportCancelEdit');
    const editMessage = document.getElementById('farmerReportEditMessage');
    const editImageActions = document.getElementById('farmerReportEditImageActions');
    const imageInput = document.getElementById('edit-report-image');
    const replaceImageButton = document.getElementById('edit-replace-report-image');
    const removeImageButton = document.getElementById('edit-remove-image-button');
    const removeImageInput = document.getElementById('edit-remove-report-image');
    const editReportId = document.getElementById('edit-report-id');
    const editReportType = document.getElementById('edit-report-type');
    let activeReportIndex = null;
    let activeReport = null;
    let editedImageUrl = '';
    let saving = false;

    if (!modal || !detailGrid || !title || !image || !noImage || !editForm || !editFields || !viewActions || !editButton) {
        return;
    }

    function addDetail(label, value) {
        const item = document.createElement('p');
        const strong = document.createElement('strong');
        const span = document.createElement('span');
        strong.textContent = label;
        span.textContent = value || 'N/A';
        item.appendChild(strong);
        item.appendChild(span);
        detailGrid.appendChild(item);
    }

    function reportTypeLabel(report) {
        return report.report_type === 'crop' ? 'Harvest Report' : 'Damaged Crop Report';
    }

    function setModalImage(imageUrl) {
        if (editedImageUrl && editedImageUrl !== imageUrl) {
            URL.revokeObjectURL(editedImageUrl);
            editedImageUrl = '';
        }

        if (imageUrl) {
            image.src = imageUrl;
            image.hidden = false;
            noImage.hidden = true;
        } else {
            image.removeAttribute('src');
            image.hidden = true;
            noImage.hidden = false;
        }
    }

    function showReadOnlyReport(report) {
        const isCropReport = report.report_type === 'crop';
        title.textContent = isCropReport ? 'Harvest Report Details' : 'Damaged Crop Report Details';
        detailGrid.innerHTML = '';
        detailGrid.hidden = false;
        editForm.hidden = true;
        viewActions.hidden = false;
        if (editImageActions) {
            editImageActions.hidden = true;
        }
        setEditMessage('', false);
        setModalImage(report.image_url || '');

        addDetail('Uri ng Ulat', reportTypeLabel(report));
        addDetail('Lokasyon', report.location);
        addDetail('Uri ng Pananim', report.crop_commodity);
        addDetail('Klase ng Pananim', report.crop_type);
        addDetail('Barayti ng Pananim', report.crop_variety);

        if (isCropReport) {
            addDetail('Petsa ng Pagtatanim', report.date_planted);
            addDetail('Petsa ng Pag-ani', report.harvest_date);
            addDetail('Tinatayang Ani (kg)', report.estimate_harvest);
            addDetail('Kalagayan ng Pananim', report.crop_status);
        } else {
            addDetail('Petsa ng Pangyayari', report.date_occurrence);
            addDetail('Sanhi ng Pinsala', report.damage_cause);
            addDetail('Porsyento ng Pinsala (%)', report.percent_damage);
            addDetail('Tinatayang Nawalang Ani (kg)', report.estimated_loss);
        }

        addDetail('Bilang ng Puno', report.number_of_trees);
        addDetail('Presyo sa Farm Gate', report.farm_gate_price);
        addDetail('Latitude', report.latitude);
        addDetail('Longitude', report.longitude);
        addDetail('Naipasa', report.reportDate);

        modal.style.display = 'block';
        modal.setAttribute('aria-hidden', 'false');
    }

    function createField(label, name, value, type, options) {
        const wrapper = document.createElement('p');
        const fieldLabel = document.createElement('label');
        const field = options ? document.createElement('select') : document.createElement('input');

        fieldLabel.textContent = label;
        fieldLabel.setAttribute('for', 'edit-' + name);
        field.name = name;
        field.id = 'edit-' + name;

        if (options) {
            options.forEach(function (optionData) {
                const option = document.createElement('option');
                option.value = optionData.value;
                option.textContent = optionData.label;
                field.appendChild(option);
            });
            setSelectValue(field, value || '');
        } else {
            field.type = type || 'text';
            field.value = value || '';
            if (field.type === 'number') {
                field.step = '0.01';
            }
        }

        if (name !== 'number-of-trees' && name !== 'latitude' && name !== 'longitude') {
            field.required = true;
        }

        wrapper.appendChild(fieldLabel);
        wrapper.appendChild(field);
        editFields.appendChild(wrapper);
    }

    function renderEditFields(report) {
        const commodityOptions = [
            { value: 'grains', label: 'Grains' },
            { value: 'plantation', label: 'Plantation Crops' },
            { value: 'root', label: 'Root Crops' },
            { value: 'vegetables', label: 'Vegetables' },
            { value: 'fruits', label: 'Fruits' }
        ];
        const cropStatusOptions = [
            { value: 'Seedling/Vegetative', label: 'Seedling/Vegetative' },
            { value: 'Bearing', label: 'Bearing' },
            { value: 'Non-Bearing', label: 'Non-Bearing' }
        ];
        const commodity = report.crop_commodity || '';

        editFields.innerHTML = '';
        createField('Lokasyon', 'location', report.location);
        createField('Uri ng Pananim', 'crop-commodity', commodity, 'text', commodityOptions);
        createField('Klase ng Pananim', 'crop-type', report.crop_type);
        createField('Barayti ng Pananim', 'crop-variety', report.crop_variety);

        if (report.report_type === 'crop') {
            createField('Petsa ng Pagtatanim', 'date-planted', report.date_planted, 'date');
            createField('Petsa ng Pag-ani', 'harvest-date', report.harvest_date, 'date');
            createField('Tinatayang Ani (kg)', 'estimate-harvest', report.estimate_harvest, 'number');
            createField('Kalagayan ng Pananim', 'crop-status', report.crop_status, 'text', cropStatusOptions);
        } else {
            createField('Petsa ng Pangyayari', 'date-occurrence', report.date_occurrence, 'date');
            createField('Sanhi ng Pinsala', 'damage-cause', report.damage_cause);
            createField('Porsyento ng Pinsala (%)', 'damage-percentage', report.percent_damage, 'number');
            createField('Tinatayang Nawalang Ani (kg)', 'estimated-loss', report.estimated_loss, 'number');
        }

        createField('Bilang ng Puno', 'number-of-trees', report.number_of_trees, 'number');
        createField('Presyo sa Farm Gate', 'farm-gate-price', report.farm_gate_price, 'number');
        createField('Latitude', 'latitude', report.latitude);
        createField('Longitude', 'longitude', report.longitude);
    }

    function setEditMessage(message, isError) {
        if (!editMessage) {
            return;
        }

        editMessage.textContent = message || '';
        editMessage.classList.toggle('is-error', Boolean(isError && message));
    }

    function setSaveState(isSaving) {
        saving = isSaving;
        editForm.querySelectorAll('button, input, select').forEach(function (field) {
            if (field.type !== 'hidden') {
                field.disabled = isSaving;
            }
        });
    }

    function openEditMode() {
        if (!activeReport) {
            return;
        }

        renderEditFields(activeReport);
        editReportId.value = activeReport.id;
        editReportType.value = activeReport.report_type;
        removeImageInput.value = '0';
        if (imageInput) {
            imageInput.value = '';
        }
        detailGrid.hidden = true;
        editForm.hidden = false;
        viewActions.hidden = true;
        if (editImageActions) {
            editImageActions.hidden = false;
        }
        setEditMessage('', false);
    }

    function cancelEditMode() {
        if (!activeReport) {
            return;
        }

        if (imageInput) {
            imageInput.value = '';
        }
        removeImageInput.value = '0';
        showReadOnlyReport(activeReport);
    }

    function updateReportRow(index, report) {
        const button = document.querySelector('.farmer-report-view-button[data-report-index="' + index + '"]');
        const row = button ? button.closest('tr') : null;
        if (!row) {
            return;
        }

        const cells = row.querySelectorAll('td');
        const eventDate = report.report_type === 'crop' ? report.harvest_date : report.date_occurrence;
        cells[1].textContent = reportTypeLabel(report);
        cells[2].textContent = report.crop_type || 'N/A';
        cells[3].textContent = report.location || 'N/A';
        cells[4].textContent = eventDate || 'N/A';
        cells[5].textContent = report.reportDate || 'N/A';

        cells[0].textContent = '';
        if (report.image_url) {
            const thumb = document.createElement('span');
            const img = document.createElement('img');
            thumb.className = 'report-thumbnail';
            img.src = report.image_url;
            img.alt = reportTypeLabel(report) + ' image';
            thumb.appendChild(img);
            cells[0].appendChild(thumb);
        } else {
            const empty = document.createElement('span');
            empty.className = 'report-thumbnail-empty';
            empty.textContent = 'Walang larawan';
            cells[0].appendChild(empty);
        }
    }

    function validateEditImage(file) {
        if (!file) {
            return '';
        }

        if (['image/jpeg', 'image/png', 'image/webp'].indexOf(file.type) === -1) {
            return 'Unsupported image type. Please upload a JPG, PNG, or WEBP image.';
        }

        if (file.size > 6 * 1024 * 1024) {
            return 'The selected image is too large. Please choose an image up to 6 MB.';
        }

        return '';
    }

    document.querySelectorAll('.farmer-report-view-button').forEach(function (button) {
        button.addEventListener('click', function () {
            const reportIndex = Number.parseInt(button.dataset.reportIndex, 10);
            if (Number.isInteger(reportIndex) && reports[reportIndex]) {
                activeReportIndex = reportIndex;
                activeReport = reports[reportIndex];
                showReadOnlyReport(activeReport);
            }
        });
    });

    editButton.addEventListener('click', openEditMode);
    if (cancelEditButton) {
        cancelEditButton.addEventListener('click', cancelEditMode);
    }

    if (replaceImageButton && imageInput) {
        replaceImageButton.addEventListener('click', function () {
            imageInput.click();
        });

        imageInput.addEventListener('change', function () {
            const file = imageInput.files && imageInput.files[0] ? imageInput.files[0] : null;
            const error = validateEditImage(file);
            if (error) {
                imageInput.value = '';
                setEditMessage(error, true);
                return;
            }

            removeImageInput.value = '0';
            if (file) {
                if (editedImageUrl) {
                    URL.revokeObjectURL(editedImageUrl);
                }
                editedImageUrl = URL.createObjectURL(file);
                setModalImage(editedImageUrl);
            }
        });
    }

    if (removeImageButton) {
        removeImageButton.addEventListener('click', function () {
            if (imageInput) {
                imageInput.value = '';
            }
            removeImageInput.value = '1';
            setModalImage('');
            setEditMessage('', false);
        });
    }

    editForm.addEventListener('submit', function (event) {
        event.preventDefault();
        if (saving || activeReportIndex === null || !activeReport) {
            return;
        }

        const formData = new FormData(editForm);
        setSaveState(true);
        setEditMessage('Saving changes...', false);

        fetch('report_update.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Unable to save report changes.');
                    }
                    return data;
                });
            })
            .then(function (data) {
                reports[activeReportIndex] = data.report;
                activeReport = data.report;
                updateReportRow(activeReportIndex, activeReport);
                setSaveState(false);
                showReadOnlyReport(activeReport);
            })
            .catch(function (error) {
                setSaveState(false);
                setEditMessage(error.message || 'Network interruption. Please try saving again.', true);
            });
    });

    function closeModal() {
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        if (imageInput) {
            imageInput.value = '';
        }
        if (removeImageInput) {
            removeImageInput.value = '0';
        }
        setModalImage('');
        activeReport = null;
        activeReportIndex = null;
    }

    closeButton.addEventListener('click', closeModal);
    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });
}
