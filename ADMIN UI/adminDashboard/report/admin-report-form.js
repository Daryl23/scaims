const adminCropTypes = {
    grains: ["Rice", "Corn", "Other"],
    plantation: ["Coconut", "Coffee", "Sugarcane", "Black Pepper", "Cacao", "Other"],
    root: ["Sweet Potato", "Cassava", "Ube/Yam", "Ginger", "Gabi", "Other"],
    vegetables: ["Ampalaya", "Bell Pepper", "Bush Sitao", "Chayote", "Cowpea", "Cucumber", "Eggplant", "Hot Pepper", "Lady Finger (okra)", "Lettuce", "Mung Bean", "Mustard", "Pechay", "Pole Sitao", "Radish", "Snap Beans", "Soybeans", "Squash", "Tomato", "Upo", "Patola", "Sigarilyas", "Other"],
    fruits: ["Avocado", "Banana", "Calamansi", "Citrus", "Pomelo", "Papaya", "Dragon Fruit", "Durian", "Guyabano", "Jackfruit", "Lanzones", "Mango", "Mango Pajo", "Mangosteen", "Pineapple", "Rambutan", "Santol", "Tamarind", "Pili", "Other"]
};

function getAdminCropTypes(commodity) {
    if (window.SCAIMSCropCatalog && typeof window.SCAIMSCropCatalog.getTypes === 'function') {
        return window.SCAIMSCropCatalog.getTypes(commodity, { includeOther: true });
    }

    return adminCropTypes[commodity] || [];
}

function populateCropTypes(commoditySelectId, cropTypeSelectId, otherInputId) {
    const commodity = document.getElementById(commoditySelectId);
    const cropType = document.getElementById(cropTypeSelectId);
    const otherInput = document.getElementById(otherInputId);

    if (!commodity || !cropType || !otherInput) {
        return;
    }

    cropType.innerHTML = '<option value="" disabled selected>Select a crop type</option>';

    const selectedCommodity = commodity.value;
    if (selectedCommodity) {
        getAdminCropTypes(selectedCommodity).forEach(function (type) {
            const option = document.createElement('option');
            option.value = type;
            option.textContent = type;
            cropType.appendChild(option);
        });
    }

    otherInput.style.display = 'none';
    otherInput.required = false;
    otherInput.value = '';
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

function normalizeCommodityKey(value) {
    if (window.SCAIMSCropCatalog && typeof window.SCAIMSCropCatalog.normalizeCommodityKey === 'function') {
        return window.SCAIMSCropCatalog.normalizeCommodityKey(value);
    }

    const key = String(value || '').toLowerCase().trim().replace(/[-\s]+/g, '_');
    return key === 'root_crops' || key === 'root_crop' ? 'root' : key;
}

document.addEventListener('DOMContentLoaded', function () {
    const reportType = document.getElementById('report-type');
    const cropFields = document.getElementById('crop-report-fields');
    const damageFields = document.getElementById('damage-report-fields');
    const farmerSearch = document.getElementById('farmer-search');
    const farmerSelect = document.getElementById('reporting-for');
    const registeredCropGroup = document.getElementById('registered-crop-group');
    const registeredCropSelect = document.getElementById('registered-crop');
    const prefillStatus = document.getElementById('admin-farmer-prefill-status');
    const imagePanel = document.querySelector('#admin-report-image-step [data-report-image-capture]');
    const form = document.getElementById('admin-report-form');
    const cropCommodity = document.getElementById('crop-commodity');
    const damageCommodity = document.getElementById('damage-crop-commodity');
    const cropType = document.getElementById('crop-type');
    const damageCropType = document.getElementById('damage-crop-type');
    const otherCropType = document.getElementById('other-crop-type');
    const otherDamageCropType = document.getElementById('other-damage-crop-type');
    let farmerPrefillByType = { crop: {}, damage: {} };
    let farmerCropChoices = [];
    let selectedCropChoice = null;
    let farmerPrefillRequest = 0;
    const reportState = {
        crop: { snapshot: null, editing: true, loaded: false },
        damage: { snapshot: null, editing: true, loaded: false }
    };

    const reportFieldMap = {
        crop: {
            section: cropFields,
            fields: {

                'crop-commodity': 'crop_commodity',
                'crop-type': 'crop_type',
                'crop-variety': 'crop_variety',
                'date-planted': 'date_planted',
                'harvest-date': 'harvest_date',
                'estimate-harvest': 'estimate_harvest',
                'crop-status': 'crop_status',
                'number-of-trees': 'number_of_trees',
                'farm-gate-price': 'farm_gate_price',


            },
            commodityField: 'crop-commodity',
            cropTypeField: 'crop-type',
            otherCropTypeField: 'other-crop-type',


        },
        damage: {
            section: damageFields,
            fields: {

                'damage-crop-commodity': 'crop_commodity',
                'damage-crop-type': 'crop_type',
                'damage-crop-variety': 'crop_variety',

                'damage-cause': 'damage_cause',
                'damage-percentage': 'percent_damage',
                'estimated-loss': 'estimated_loss',
                'damage-farm-gate-price': 'farm_gate_price',
                'damage-number-of-trees': 'number_of_trees',


            },
            commodityField: 'damage-crop-commodity',
            cropTypeField: 'damage-crop-type',
            otherCropTypeField: 'other-damage-crop-type',


        }
    };

    function setSubmitting(isSubmitting) {
        if (!form) {
            return;
        }

        form.dataset.submitting = isSubmitting ? 'true' : 'false';

        const submitButton = form.querySelector('.submit-report-button');
        if (submitButton) {
            submitButton.disabled = isSubmitting;
            submitButton.textContent = isSubmitting ? 'Uploading...' : 'Submit Report';
        }

        form.querySelectorAll('[data-report-image-capture] button').forEach(function (button) {
            if (isSubmitting) {
                button.dataset.submitWasDisabled = button.disabled ? 'true' : 'false';
                button.disabled = true;
            } else if (button.dataset.submitWasDisabled) {
                button.disabled = button.dataset.submitWasDisabled === 'true';
                delete button.dataset.submitWasDisabled;
            }
        });
    }

    function setStatus(message, isError) {
        if (!prefillStatus) {
            return;
        }

        prefillStatus.textContent = message || '';
        prefillStatus.classList.toggle('is-error', Boolean(isError && message));
    }

    function getFieldValue(fieldId) {
        const field = document.getElementById(fieldId);
        return field ? field.value : '';
    }

    function setFieldValue(fieldId, value) {
        const field = document.getElementById(fieldId);
        if (!field || value === null || typeof value === 'undefined') {
            return;
        }

        if (field.tagName === 'SELECT') {
            setSelectValue(field, value);
            return;
        }

        field.value = value;
    }

    function setSectionSelected(section, selected) {
        if (!section) {
            return;
        }

        const fields = section.querySelectorAll('input, select, textarea, button');
        fields.forEach(function (field) {
            if (field.dataset.adminReportEdit || field.dataset.adminReportApply || field.dataset.adminReportCancel) {
                field.disabled = !selected;
                return;
            }

            if (field.type === 'hidden') {
                field.disabled = !selected;
                return;
            }

            if (!field.dataset.wasRequired) {
                field.dataset.wasRequired = field.required ? 'true' : 'false';
            }

            field.disabled = !selected;
            field.required = selected && field.dataset.wasRequired === 'true';
        });
    }

    function getReportSnapshot(type) {
        const config = reportFieldMap[type];
        const snapshot = {};
        if (!config) {
            return snapshot;
        }

        Object.keys(config.fields).forEach(function (fieldId) {
            snapshot[fieldId] = getFieldValue(fieldId);
        });
        snapshot[config.coordinateMode] = getFieldValue(config.coordinateMode);
        snapshot[config.coordinateInput] = getFieldValue(config.coordinateInput);

        return snapshot;
    }

    function restoreReportSnapshot(type) {
        const config = reportFieldMap[type];
        const state = reportState[type];
        if (!config || !state || !state.snapshot) {
            return;
        }

        Object.keys(state.snapshot).forEach(function (fieldId) {
            setFieldValue(fieldId, state.snapshot[fieldId]);
        });
    }


    function currentPrefillFor(type) {
        const prefill = Object.assign({}, farmerPrefillByType[type] || {});
        if (selectedCropChoice) {
            ['location', 'crop_commodity', 'crop_type', 'crop_variety', 'number_of_trees', 'crop_status'].forEach(function (key) {
                if (selectedCropChoice[key] !== null && typeof selectedCropChoice[key] !== 'undefined' && selectedCropChoice[key] !== '') {
                    prefill[key] = selectedCropChoice[key];
                }
            });
        }
        return prefill;
    }

    function applyPrefill(type) {
        const config = reportFieldMap[type];
        const state = reportState[type];
        if (!config || !state) {
            return;
        }

        const prefill = currentPrefillFor(type);
        const commodity = normalizeCommodityKey(prefill.crop_commodity || '');

        Object.keys(config.fields).forEach(function (fieldId) {
            if (fieldId === config.commodityField || fieldId === config.cropTypeField) {
                return;
            }
            setFieldValue(fieldId, prefill[config.fields[fieldId]] || '');
        });

        setFieldValue(config.commodityField, commodity);
        populateCropTypes(config.commodityField, config.cropTypeField, config.otherCropTypeField);
        setFieldValue(config.cropTypeField, prefill.crop_type || '');


        state.snapshot = getReportSnapshot(type);
        state.loaded = true;
    }

    function setReportEditMode(type, editing) {
        const config = reportFieldMap[type];
        const state = reportState[type];
        if (!config || !state || !config.section) {
            return;
        }

        state.editing = editing;
        config.section.classList.toggle('report-section-locked', !editing);

        config.section.querySelectorAll('input, select, textarea, button').forEach(function (field) {
            if (field.type === 'hidden' || field.dataset.adminReportEdit || field.dataset.adminReportApply || field.dataset.adminReportCancel) {
                return;
            }

            if (field.tagName === 'SELECT' || field.type === 'date' || field.classList.contains('gps-button')) {
                field.disabled = !editing;
            } else {
                field.disabled = false;
                field.readOnly = !editing;
            }
            field.classList.toggle('is-readonly-field', !editing);
        });

        const editButton = config.section.querySelector('[data-admin-report-edit]');
        const applyButton = config.section.querySelector('[data-admin-report-apply]');
        const cancelButton = config.section.querySelector('[data-admin-report-cancel]');
        if (editButton) {
            editButton.disabled = false;
            editButton.hidden = editing;
        }
        if (applyButton) {
            applyButton.disabled = false;
            applyButton.hidden = !editing;
        }
        if (cancelButton) {
            cancelButton.disabled = false;
            cancelButton.hidden = !editing;
        }
    }

    function setReportMessage(type, message, isError) {
        const messageEl = document.querySelector('[data-admin-report-message="' + type + '"]');
        if (!messageEl) {
            return;
        }

        messageEl.textContent = message || '';
        messageEl.classList.toggle('is-error', Boolean(isError && message));
    }

    function applyReportEdits(type) {
        reportState[type].snapshot = getReportSnapshot(type);
        setReportEditMode(type, false);
        setReportMessage(type, 'Applied to this report draft. Submit Report will save it for the selected farmer.', false);
    }

    function cancelReportEdits(type) {
        restoreReportSnapshot(type);
        setReportEditMode(type, false);
        setReportMessage(type, '', false);
    }

    function toggleReportType() {
        if (!reportType) {
            return;
        }

        const selectedType = reportType.value;
        cropFields.hidden = selectedType !== 'crop';
        damageFields.hidden = selectedType !== 'damage';
        setSectionSelected(cropFields, selectedType === 'crop');
        setSectionSelected(damageFields, selectedType === 'damage');

        if (selectedType === 'crop' || selectedType === 'damage') {
            if (!reportState[selectedType].loaded) {
                applyPrefill(selectedType);
            }
            setReportEditMode(selectedType, reportState[selectedType].editing);
        }
    }

    function filterFarmers() {
        if (!farmerSearch || !farmerSelect) {
            return;
        }

        const term = farmerSearch.value.trim().toLowerCase();
        Array.from(farmerSelect.options).forEach(function (option) {
            const haystack = option.dataset.search || option.textContent.toLowerCase();
            option.hidden = term !== '' && haystack.indexOf(term) === -1;
        });
    }

    function toggleOtherInput(selectElement, otherInputElement) {
        if (!selectElement || !otherInputElement) {
            return;
        }

        if (selectElement.value === 'Other') {
            otherInputElement.style.display = 'block';
            otherInputElement.required = !otherInputElement.disabled;
        } else {
            otherInputElement.style.display = 'none';
            otherInputElement.required = false;
            otherInputElement.value = '';
        }
    }

    function resetFarmerLoadedData() {
        farmerPrefillByType = { crop: {}, damage: {} };
        farmerCropChoices = [];
        selectedCropChoice = null;
        reportState.crop.editing = true;
        reportState.damage.editing = true;
        reportState.crop.loaded = false;
        reportState.damage.loaded = false;
        reportState.crop.snapshot = null;
        reportState.damage.snapshot = null;

        if (registeredCropSelect) {
            registeredCropSelect.innerHTML = '<option value="">Use farmer default information</option>';
        }
        if (registeredCropGroup) {
            registeredCropGroup.hidden = true;
        }
        if (reportType) {
            reportType.value = '';
            reportType.disabled = true;
        }
        if (imagePanel && imagePanel.scaimsReportImageCapture) {
            imagePanel.scaimsReportImageCapture.setLocked(true);
            imagePanel.scaimsReportImageCapture.resetToInitial();
        }
        toggleReportType();
    }

    function populateRegisteredCropChoices() {
        if (!registeredCropSelect || !registeredCropGroup) {
            return;
        }

        registeredCropSelect.innerHTML = '<option value="">Use farmer default information</option>';
        farmerCropChoices.forEach(function (crop, index) {
            const labelParts = [crop.crop_type, crop.crop_variety, crop.location].filter(Boolean);
            const option = document.createElement('option');
            option.value = String(index);
            option.textContent = labelParts.length ? labelParts.join(' - ') : 'Registered crop ' + (index + 1);
            registeredCropSelect.appendChild(option);
        });

        registeredCropGroup.hidden = farmerCropChoices.length === 0;
    }

    function loadSelectedFarmer() {
        const requestId = ++farmerPrefillRequest;
        if (!farmerSelect || !farmerSelect.value) {
            resetFarmerLoadedData();
            setStatus('', false);
            return;
        }

        resetFarmerLoadedData();
        setStatus('Loading farmer farm and crop information...', false);

        fetch('get_farmer_report_prefill.php?farmer_id=' + encodeURIComponent(farmerSelect.value), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || data.error || 'Unable to load farmer information.');
                    }
                    return data;
                });
            })
            .then(function (data) {
                if (farmerPrefillRequest !== requestId) {
                    return;
                }

                farmerPrefillByType = data.prefill_by_type || { crop: {}, damage: {} };
                farmerCropChoices = Array.isArray(data.crop_choices) ? data.crop_choices : [];
                populateRegisteredCropChoices();

                if (reportType) {
                    reportType.disabled = !imagePanel || imagePanel.dataset.hasImage !== 'true';
                }
                if (imagePanel && imagePanel.scaimsReportImageCapture) {
                    imagePanel.scaimsReportImageCapture.setLocked(false);
                }

                if (reportType && reportType.value) {
                    reportState[reportType.value].loaded = false;
                    toggleReportType();
                }

                setStatus('Farmer information loaded. Upload a GPS Camera photo, then choose the report type.', false);
            })
            .catch(function (error) {
                if (farmerPrefillRequest !== requestId) {
                    return;
                }
                resetFarmerLoadedData();
                setStatus(error.message || 'Unable to load farmer information.', true);
            });
    }

    if (imagePanel) {
        imagePanel.addEventListener('scaims:report-image-changed', function (event) {
            reportType.disabled = !event.detail.hasImage;
        });
    }

    if (reportType) {
        reportType.addEventListener('change', toggleReportType);
        toggleReportType();
    }

    if (farmerSearch) {
        farmerSearch.addEventListener('input', filterFarmers);
    }

    if (farmerSelect) {
        farmerSelect.addEventListener('change', loadSelectedFarmer);
    }

    if (registeredCropSelect) {
        registeredCropSelect.addEventListener('change', function () {
            selectedCropChoice = registeredCropSelect.value === ''
                ? null
                : farmerCropChoices[Number.parseInt(registeredCropSelect.value, 10)] || null;

            ['crop', 'damage'].forEach(function (type) {
                reportState[type].loaded = false;
            });
            if (reportType && reportType.value) {
                applyPrefill(reportType.value);
                setReportEditMode(reportType.value, true);
            }
        });
    }

    if (cropCommodity) {
        cropCommodity.addEventListener('change', function () {
            populateCropTypes('crop-commodity', 'crop-type', 'other-crop-type');
        });
    }

    if (damageCommodity) {
        damageCommodity.addEventListener('change', function () {
            populateCropTypes('damage-crop-commodity', 'damage-crop-type', 'other-damage-crop-type');
        });
    }

    if (cropType) {
        cropType.addEventListener('change', function () {
            toggleOtherInput(cropType, otherCropType);
        });
    }

    if (damageCropType) {
        damageCropType.addEventListener('change', function () {
            toggleOtherInput(damageCropType, otherDamageCropType);
        });
    }

    document.querySelectorAll('[data-admin-report-edit]').forEach(function (button) {
        button.addEventListener('click', function () {
            setReportMessage(button.dataset.adminReportEdit, '', false);
            setReportEditMode(button.dataset.adminReportEdit, true);
        });
    });

    document.querySelectorAll('[data-admin-report-apply]').forEach(function (button) {
        button.addEventListener('click', function () {
            applyReportEdits(button.dataset.adminReportApply);
        });
    });

    document.querySelectorAll('[data-admin-report-cancel]').forEach(function (button) {
        button.addEventListener('click', function () {
            cancelReportEdits(button.dataset.adminReportCancel);
        });
    });

    document.addEventListener('scaims:crop-catalog-ready', function () {
        if (cropCommodity && cropCommodity.value) {
            populateCropTypes('crop-commodity', 'crop-type', 'other-crop-type');
        }
        if (damageCommodity && damageCommodity.value) {
            populateCropTypes('damage-crop-commodity', 'damage-crop-type', 'other-damage-crop-type');
        }
    });


    if (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }

            if (!farmerSelect || !farmerSelect.value) {
                event.preventDefault();
                alert('Please select the farmer you are reporting for.');
                return;
            }

            if (!reportType || !reportType.value) {
                event.preventDefault();
                alert('Please select the type of report.');
                return;
            }

            if (!imagePanel || !imagePanel.scaimsReportImageCapture.hasImage()) {
                event.preventDefault();
                alert(window.SCAIMSGPSPhoto.t('gps_photo_required'));
                return;
            }

            if (!window.confirm('Submit this report for the selected farmer?')) {
                event.preventDefault();
                return;
            }

            const selectedType = reportType.value;
            const wasEditing = reportState[selectedType] ? reportState[selectedType].editing : false;
            setReportEditMode(selectedType, true);


            if (!form.reportValidity()) {
                event.preventDefault();
                setReportEditMode(selectedType, wasEditing);
                return;
            }

            setSubmitting(true);
        });
    }

    resetFarmerLoadedData();
});
