function confirmSubmission() {
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
        document.querySelector('form').submit();
    }
}

function toggleCropReport() {
    const reportType = document.getElementById('report-type').value;
    const cropReportFields = document.getElementById('crop-report-fields');
    const damageReportFields = document.getElementById('damage-report-fields');

    if (reportType === 'crop') {
        cropReportFields.style.display = 'block';
        damageReportFields.style.display = 'none';
    } else if (reportType === 'damage') {
        cropReportFields.style.display = 'none';
        damageReportFields.style.display = 'block';
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
    
    cropTypeSelect.innerHTML = '<option value="" disabled selected>' + window.dashboardI18n.t('report_choose_crop_type') + '</option>';

    if (commodity) {
        getFarmerCropTypes(commodity).forEach(type => {
            const option = document.createElement('option');
            option.value = type;
            option.textContent = type;
            cropTypeSelect.appendChild(option);
        });
    }

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

    cropTypeSelect.innerHTML = '<option value="" disabled selected>' + window.dashboardI18n.t('report_choose_crop_type') + '</option>';

    if (commodity) {
        getFarmerDamageCropTypes(commodity).forEach(type => {
            const option = document.createElement("option");
            option.value = type;
            option.textContent = type;
            cropTypeSelect.appendChild(option);
        });
    }

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

        document.querySelectorAll('.submit-btn').forEach(function(button) {
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
}); 

// Add input validation for numerical fields
function validateNumber(input) {
    input.value = input.value.replace(/[^0-9.]/g, '');
    if (input.value.split('.').length > 2) input.value = input.value.replace(/\.+$/, '');
} 

// Add event listener for form submission
document.querySelector('form').addEventListener('submit', function(e) {
    e.preventDefault();
    
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
        this.submit();
    }
}); 
