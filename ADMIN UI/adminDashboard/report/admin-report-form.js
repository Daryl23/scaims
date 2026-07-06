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
        return function () {};
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
                window.alert('Use the Google Maps or Waze format: 13°50\'11.2"N 121°16\'50.5"E');
            }
            return false;
        }

        setCoordinateValues(parsedCoordinates.latitude, parsedCoordinates.longitude);
        return true;
    }

    function requestCurrentLocation() {
        if (!navigator.geolocation) {
            window.alert('Geolocation is not supported by this browser.');
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
                window.alert(message);
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
            action.hidden = false;
            coordinateInput.disabled = true;
            coordinateInput.readOnly = true;
            if (status) {
                status.textContent = 'Getting your current location...';
            }
            requestCurrentLocation();
        } else if (mode.value === 'manual') {
            action.hidden = true;
            coordinateInput.disabled = false;
            coordinateInput.readOnly = false;
            if (status) {
                status.textContent = 'Use the Google Maps or Waze format: 13°50\'11.2"N 121°16\'50.5"E';
            }
        } else {
            action.hidden = true;
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

    return updateMode;
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
            window.alert('Please allow GPS access or click "Use Current Location" before submitting the report.');
            return false;
        }
        coordinateInput.value = formatCoordinatePair(latitude.value, longitude.value);
        return true;
    }

    if (mode.value === 'manual') {
        const parsedCoordinates = parseCoordinatePair(coordinateInput.value);
        if (parsedCoordinates.empty) {
            window.alert('Please enter the coordinates in Google Maps or Waze format: 13°50\'11.2"N 121°16\'50.5"E');
            return false;
        }

        if (!parsedCoordinates.valid) {
            window.alert('Coordinates are invalid. Use the format: 13°50\'11.2"N 121°16\'50.5"E');
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

document.addEventListener('DOMContentLoaded', function () {
    const coordinateRefreshers = [];
    const reportType = document.getElementById('report-type');
    const cropFields = document.getElementById('crop-report-fields');
    const damageFields = document.getElementById('damage-report-fields');
    const farmerSearch = document.getElementById('farmer-search');
    const farmerSelect = document.getElementById('reporting-for');
    const form = document.getElementById('admin-report-form');
    const cropCommodity = document.getElementById('crop-commodity');
    const damageCommodity = document.getElementById('damage-crop-commodity');
    const cropType = document.getElementById('crop-type');
    const damageCropType = document.getElementById('damage-crop-type');
    const otherCropType = document.getElementById('other-crop-type');
    const otherDamageCropType = document.getElementById('other-damage-crop-type');

    function setSectionEnabled(section, enabled) {
        if (!section) {
            return;
        }

        const fields = section.querySelectorAll('input, select, textarea, button');
        fields.forEach(function (field) {
            if (!field.dataset.wasRequired) {
                field.dataset.wasRequired = field.required ? 'true' : 'false';
            }

            field.disabled = !enabled;
            field.required = enabled && field.dataset.wasRequired === 'true';
        });
    }

    function toggleReportType() {
        if (!reportType) {
            return;
        }

        const selectedType = reportType.value;
        cropFields.hidden = selectedType !== 'crop';
        damageFields.hidden = selectedType !== 'damage';
        setSectionEnabled(cropFields, selectedType === 'crop');
        setSectionEnabled(damageFields, selectedType === 'damage');
        coordinateRefreshers.forEach(function (refresh) {
            refresh();
        });
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

    if (reportType) {
        reportType.addEventListener('change', toggleReportType);
        toggleReportType();
    }

    if (farmerSearch) {
        farmerSearch.addEventListener('input', filterFarmers);
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

    document.addEventListener('scaims:crop-catalog-ready', function () {
        if (cropCommodity && cropCommodity.value) {
            populateCropTypes('crop-commodity', 'crop-type', 'other-crop-type');
        }
        if (damageCommodity && damageCommodity.value) {
            populateCropTypes('damage-crop-commodity', 'damage-crop-type', 'other-damage-crop-type');
        }
    });

    coordinateRefreshers.push(initCoordinateControls('crop'));
    coordinateRefreshers.push(initCoordinateControls('damage'));
    coordinateRefreshers.forEach(function (refresh) {
        if (typeof refresh === 'function') {
            refresh();
        }
    });

    if (form) {
        form.addEventListener('submit', function (event) {
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

            if (!window.confirm('Submit this report for the selected farmer?')) {
                event.preventDefault();
                return;
            }

            if (!prepareCoordinateFieldsForSubmit(reportType.value)) {
                event.preventDefault();
            }
        });
    }
});
