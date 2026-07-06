// New function to generate crop fields
function generateCropFields(parcelIndex) {
    const numCrops = document.getElementById(`numCrops_${parcelIndex}`).value;
    const cropFieldsContainer = document.getElementById(`cropFields_${parcelIndex}`);

    cropFieldsContainer.innerHTML = '';

    for (let j = 1; j <= numCrops; j++) {
        let cropFieldset = document.createElement('fieldset');
        cropFieldset.innerHTML = `
            <h4>Crop ${j}</h4>
            <div class="crop-row">
                <div class="crop-group">
                    <label for="crop_${parcelIndex}_${j}">Crop Commodity:</label>
                    <select id="crop_${parcelIndex}_${j}" name="crop_${parcelIndex}_${j}" required onchange="showCropType(${parcelIndex}, ${j})">
                        <option value="" disabled selected>Pumili ng Crop Commodity</option>
                        <option value="grains">Grains</option>
                        <option value="plantation">Plantation Crops</option>
                        <option value="root_crops">Root Crops</option>
                        <option value="vegetables">Vegetables</option>
                        <option value="fruits">Fruits</option>
                    </select>
                </div>

                <div class="crop-group" id="cropTypeContainer_${parcelIndex}_${j}">
                    <!-- Crop Type fields will be inserted here -->
                </div>
            </div>
        `;

        cropFieldsContainer.appendChild(cropFieldset);
    }
}

// Update the showCropType function to handle the new structure
function showCropType(parcelIndex, cropIndex) {
    const cropCommodity = document.getElementById(`crop_${parcelIndex}_${cropIndex}`).value;
    const cropTypeContainer = document.getElementById(`cropTypeContainer_${parcelIndex}_${cropIndex}`);

    cropTypeContainer.innerHTML = '';

    const fallbackCropTypes = {
        grains: ['Rice', 'Corn'],
        root_crops: ['Sweet Potato', 'Cassava', 'Ube/Yam', 'Ginger', 'Gabi'],
        vegetables: ['Ampalaya', 'Bell Pepper', 'Bush Sitao','Chayote', 'Cowpea', 'Cucumber', 'Eggplant', 'Hot Pepper', 'Lady Finger (okra)',
            'Lettuce', 'Mung Bean', 'Mustard', 'Pechay', 'Pole Sitao', 'Radish', 'Snap Beans', 'Soybeans', 'Squash', 'Tomato', 'Upo', 'Patola', 'Sigarilyas'],
        fruits: ['Avocado', 'Banana', 'Calamansi', 'Citrus', 'Pomelo', 'Papaya', 'Dragon Fruit', 'Durian', 'Guyabano', 'Jackfruit', 
            'Lanzones', 'Mango', 'Mango Pajo', 'Mangosteen', 'Pineapple', 'Rambutan', 'Santol', 'Tamarind', 'Pili'],
        plantation: ['Coconut', 'Coffee', 'Sugarcane', 'Black Pepper', 'Cacao']
    };
    const cropTypes = window.SCAIMSCropCatalog && typeof window.SCAIMSCropCatalog.getTypes === 'function'
        ? window.SCAIMSCropCatalog.getTypes(cropCommodity)
        : (fallbackCropTypes[cropCommodity] || []);

    if (cropTypes.length) {
        let cropTypeLabel = cropCommodity === 'fruits' ? 'Fruit Type' : 'Crop Type';
        let varietyLabel = cropCommodity === 'fruits' ? 'Fruit Variety' : 'Crop Variety';

        let cropTypeOptions = cropTypes
            .map(type => `<option value="${type}">${type}</option>`)
            .join('');

        cropTypeContainer.innerHTML = `
            <label for="cropType_${parcelIndex}_${cropIndex}">${cropTypeLabel}:</label>
            <select id="cropType_${parcelIndex}_${cropIndex}" name="cropType_${parcelIndex}_${cropIndex}" required onchange="checkCustomCropType(${parcelIndex}, ${cropIndex})">
                <option value="" disabled selected>Pumili ng ${cropTypeLabel}</option>
                ${cropTypeOptions}
                <option value="other">Other</option>
            </select>

            <input type="text" id="customCropType_${parcelIndex}_${cropIndex}" 
                   name="customCropType_${parcelIndex}_${cropIndex}" 
                   placeholder="Specify ${cropTypeLabel}" 
                   style="display:none;"
                   onchange="updateCropTypeValue(${parcelIndex}, ${cropIndex})">

            <label for="cropVariety_${parcelIndex}_${cropIndex}">${varietyLabel}:</label>
            <input type="text" id="cropVariety_${parcelIndex}_${cropIndex}" name="cropVariety_${parcelIndex}_${cropIndex}" placeholder="Ilagay ang variety ng crop">
        `;

        if (cropCommodity === 'plantation' || cropCommodity === 'fruits') {
            cropTypeContainer.innerHTML += `
                <label for="numTrees_${parcelIndex}_${cropIndex}">Number of Trees:</label>
                <input type="number" id="numTrees_${parcelIndex}_${cropIndex}" name="numTrees_${parcelIndex}_${cropIndex}" min="1" step="1" placeholder="Ilagay ang bilang ng mga puno">

                <label for="treeStatus_${parcelIndex}_${cropIndex}">Tree Status:</label>
                <select id="treeStatus_${parcelIndex}_${cropIndex}" name="treeStatus_${parcelIndex}_${cropIndex}">
                    <option value="" disabled selected>Pumili ng Tree Status</option>
                    <option value="seedling">Seedling/Vegetative</option>
                    <option value="bearing">Bearing</option>
                    <option value="non-bearing">Non-bearing</option>
                </select>
            `;
        }
    } else {
        cropTypeContainer.innerHTML = '';
    }
}

function checkCustomCropType(parcelIndex, cropIndex) {
    const cropTypeSelect = document.getElementById(`cropType_${parcelIndex}_${cropIndex}`);
    const customCropTypeInput = document.getElementById(`customCropType_${parcelIndex}_${cropIndex}`);

    if (cropTypeSelect.value === 'other') {
        customCropTypeInput.style.display = 'block';
        customCropTypeInput.required = true;
        // Clear the select value so it can be updated with custom input
        cropTypeSelect.value = '';
    } else {
        customCropTypeInput.style.display = 'none';
        customCropTypeInput.required = false;
        customCropTypeInput.value = ''; // Clear the custom input
    }
}

function updateCropTypeValue(parcelIndex, cropIndex) {
    const cropTypeSelect = document.getElementById(`cropType_${parcelIndex}_${cropIndex}`);
    const customCropTypeInput = document.getElementById(`customCropType_${parcelIndex}_${cropIndex}`);
    
    if (customCropTypeInput.value.trim() !== '') {
        // Update the select element with a new option containing the custom value
        const newOption = new Option(customCropTypeInput.value, customCropTypeInput.value);
        cropTypeSelect.add(newOption);
        cropTypeSelect.value = customCropTypeInput.value;
    }
}

function checkCustomOwnership(parcelIndex) {
    const ownershipSelect = document.getElementById(`ownership_${parcelIndex}`);
    const customOwnershipInput = document.getElementById(`customOwnership_${parcelIndex}`);

    if (ownershipSelect.value === 'other') {
        customOwnershipInput.style.display = 'block';
        customOwnershipInput.required = true;
    } else {
        customOwnershipInput.style.display = 'none';
        customOwnershipInput.required = false;
        customOwnershipInput.value = ''; // Clear the input when not visible
    }
}

document.getElementById('farmForm').addEventListener('submit', function (event) {
    event.preventDefault(); 

    const formData = new FormData(this);

    fetch('submitFarmInfo.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.href = 'farmersFP.php';
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            alert("An error occurred: " + error.message);
        });
});
