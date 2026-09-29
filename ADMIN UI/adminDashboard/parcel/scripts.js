let currentCropPages = {}; // Track crop pagination for each parcel
let currentCropIndex = 0; // Track the current crop index

let currentPage = 1;
const itemsPerPage = 10;
let allFarmersData = []; // Store all farmers data
let filteredData = []; // Store filtered farmers data
let parcelData = [];
let currentParcelIndex = 0;
const cropsPerPage = 1; // Number of crops per page
let totalFarmArea = null; // Store total farm area
let totalParcels = null; // Store total parcels

// Add the cropTypes object here
const cropTypes = {
    grains: ['Rice', 'Corn', 'Other'],
    root_crops: ['Sweet Potato', 'Cassava', 'Ube/Yam', 'Ginger', 'Gabi', 'Other'],
    vegetables: ['Ampalaya', 'Bell Pepper', 'Bush Sitao','Chayote', 'Cowpea', 'Cucumber', 'Eggplant', 'Hot Pepper', 'Lady Finger (okra)',
        'Lettuce', 'Mung Bean', 'Mustard', 'Pechay', 'Pole Sitao', 'Radish', 'Snap Beans', 'Soybeans', 'Squash', 'Tomato', 'Upo', 'Patola', 'Sigarilyas', 'Other'],
    fruits: ['Avocado', 'Banana', 'Calamansi', 'Citrus', 'Pomelo', 'Papaya', 'Dragon Fruit', 'Durian', 'Guyabano', 'Jackfruit', 
        'Lanzones', 'Mango', 'Mango Pajo', 'Mangosteen', 'Pineapple', 'Rambutan', 'Santol', 'Tamarind', 'Pili', 'Other'],
    plantation: ['Coconut', 'Coffee', 'Sugarcane', 'Black Pepper', 'Cacao', 'Other']
};

function getParcelCropTypes(commodity) {
    if (window.SCAIMSCropCatalog && typeof window.SCAIMSCropCatalog.getTypes === 'function') {
        return window.SCAIMSCropCatalog.getTypes(commodity, { includeOther: true });
    }

    return cropTypes[commodity] || [];
}

// Function to update crop type filter based on selected commodity
function updateCropTypeFilter() {
    const commoditySelect = document.getElementById("cropCommodityFilter");
    const typeSelect = document.getElementById("cropTypeFilter");
    const selectedCommodity = commoditySelect.value;

    // Clear existing options
    typeSelect.innerHTML = '<option value="" disabled selected>Crop Type</option>';

    // If a commodity is selected, populate crop types
    if (selectedCommodity) {
        getParcelCropTypes(selectedCommodity).forEach(cropType => {
            const option = document.createElement('option');
            option.value = cropType;  // Keep original case
            option.textContent = cropType;
            typeSelect.appendChild(option);
        });
    }

    // Trigger table filter
    filterTable();
}

document.addEventListener('scaims:crop-catalog-ready', function() {
    const commoditySelect = document.getElementById("cropCommodityFilter");
    if (commoditySelect && commoditySelect.value) {
        updateCropTypeFilter();
    }
});

// Location Mapping for consistent display
const farmLocationMapping = {
    'adya': 'Adya',
    'anilao': 'Anilao',
    'anilao-labac': 'Anilao-Labac',
    'antipolodelnorte': 'Antipolo del Norte',
    'antipolodelsur': 'Antipolo del Sur',
    'bagongpook': 'Bagong Pook',
    'balintawak': 'Balintawak',
    'banaybanay': 'Banaybanay',
    'bolbok': 'Bolbok',
    'bugtongnapulo': 'Bugtong na Pulo',
    'bulacnin': 'Bulacnin',
    'bulaklakan': 'Bulaklakan',
    'calamias': 'Calamias',
    'cumba': 'Cumba',
    'dagatan': 'Dagatan',
    'duhatan': 'Duhatan',
    'halang': 'Halang',
    'inosluban': 'Inosluban',
    'kayumanggi': 'Kayumanggi',
    'latag': 'Latag',
    'lodlod': 'Lodlod',
    'lumbang': 'Lumbang',
    'mabini': 'Mabini',
    'malagonlong': 'Malagonlong',
    'malitlit': 'Malitlit',
    'marawoy': 'Marawoy',
    'mataasnalupa': 'Mataas na Lupa',
    'muntingpulo': 'Munting Pulo',
    'pagolinginbata': 'Pagolingin Bata',
    'pagolingineast': 'Pagolingin East',
    'pagolinginwest': 'Pagolingin West',
    'pangao': 'Pangao',
    'pinagkawitan': 'Pinagkawitan',
    'pinagtongulan': 'Pinagtongulan',
    'plaridel': 'Plaridel',
    'poblacionbarangay1': 'Poblacion Barangay 1',
    'poblacionbarangay2': 'Poblacion Barangay 2',
    'poblacionbarangay3': 'Poblacion Barangay 3',
    'poblacionbarangay4': 'Poblacion Barangay 4',
    'poblacionbarangay5': 'Poblacion Barangay 5',
    'poblacionbarangay6': 'Poblacion Barangay 6',
    'poblacionbarangay7': 'Poblacion Barangay 7',
    'poblacionbarangay8': 'Poblacion Barangay 8',
    'poblacionbarangay9': 'Poblacion Barangay 9',
    'poblacionbarangay9a': 'Poblacion Barangay 9-A',
    'poblacionbarangay10': 'Poblacion Barangay 10',
    'poblacionbarangay11': 'Poblacion Barangay 11',
    'poblacionbarangay12': 'Poblacion Barangay 12',
    'pusil': 'Pusil',
    'quezon': 'Quezon',
    'rizal': 'Rizal',
    'sabang': 'Sabang',
    'sampaguita': 'Sampaguita',
    'sanbenito': 'San Benito',
    'sancarlos': 'San Carlos',
    'sancelestino': 'San Celestino',
    'sanfrancisco': 'San Francisco',
    'sanguillermo': 'San Guillermo',
    'sanjose': 'San Jose',
    'sanlucas': 'San Lucas',
    'sansalvador': 'San Salvador',
    'sansebastian': 'San Sebastian',
    'santonino': 'Santo Niño',
    'santoribio': 'Santo Toribio',
    'sapac': 'Sapac',
    'sico': 'Sico',
    'talisay': 'Talisay',
    'tambo': 'Tambo',
    'tangob': 'Tangob',
    'tanguay': 'Tanguay',
    'tibig': 'Tibig',
    'tipacan': 'Tipacan'
};

// Function to format location
function formatLocation(location) {
    if (!location) return 'N/A'; // Check if location is defined
    return farmLocationMapping[location.toLowerCase()] || location;
}

// Function to capitalize the first letter of a string
function capitalizeFirstLetter(string) {
    if (!string) return 'N/A';
    return string.charAt(0).toUpperCase() + string.slice(1).toLowerCase();
}

// Function to format text (split underscores and capitalize)
function formatText(text) {
    if (!text) return 'N/A';
    // Check if it's one of the standard types
    const standardTypes = {
        'registered_owner': 'Registered Owner',
        'lessee': 'Lessee',
        'tenant': 'Tenant'
    };
    
    return standardTypes[text] || 
           text.split('_')
               .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
               .join(' ');
}

// Function to format farm type
function formatFarmType(farmType) {
    return farmType
        .replace(/_/g, ' ') // Replace underscores with spaces
        .replace(/\b\w/g, char => char.toUpperCase()); // Capitalize the first letter of each word
}

// Function to filter the farmers table
function filterTable() {
    const locationFilter = document.getElementById("locationFilter").value.toLowerCase();
    const nameFilter = document.getElementById("searchBox").value.toLowerCase();
    const commodityFilter = document.getElementById("cropCommodityFilter").value.toLowerCase();
    const typeFilter = document.getElementById("cropTypeFilter").value.toLowerCase();

    // Apply filters
    filteredData = allFarmersData.filter(farmer => {
        const normalizedLocation = farmer.FarmLocation ? 
            farmer.FarmLocation.toLowerCase().replace(/\s+/g, '') : '';
        const normalizedFilter = locationFilter.replace(/\s+/g, '');
        
        const locationMatch = locationFilter === '' || 
            normalizedLocation.includes(normalizedFilter);
        
        const nameMatch = farmer.FarmerName ? 
            farmer.FarmerName.toLowerCase().includes(nameFilter) : false;

        // Check crop commodity and type
        const commodities = (farmer.Commodities || '').toLowerCase();
        const cropTypes = (farmer.CropTypes || '').toLowerCase();
        
        const commodityMatch = commodityFilter === '' || 
            commodities.includes(commodityFilter);
        
        const typeMatch = typeFilter === '' || 
            cropTypes.includes(typeFilter);

        return locationMatch && nameMatch && commodityMatch && typeMatch;
    });

    currentPage = 1; // Reset to first page when filtering
    displayFarmers(currentPage);
}

// Function to fetch farmer data
async function fetchFarmers() {
    try {
        const response = await fetch('getFarmers.php');
        
        // Check if the response is OK
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        
        // Try to parse JSON
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON Parsing error:', parseError);
            throw new Error('Failed to parse JSON: ' + responseText);
        }
        
        // Check if there's an error in the returned data
        if (data.error) {
            throw new Error(data.error);
        }
        
        // If data is valid, proceed
        allFarmersData = data.farmers;
        filteredData = [...allFarmersData]; // Initialize filtered data with all data
        displayFarmers(1); // Show first page
    } catch (error) {
        console.error('Error fetching farmers:', error);
        
        // Display error to user
        const tableBody = document.getElementById("farmersTable");
        tableBody.innerHTML = `
            <tr>
                <td colspan="4" style="color: red; text-align: center;">
                    Error loading farmers: ${error.message}
                    <br>Please try refreshing the page or contact support.
                </td>
            </tr>
        `;
    }
}

// Function to display farmers with pagination
function displayFarmers(page) {
    currentPage = page; // Update current page
    const startIndex = (page - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const paginatedData = filteredData.slice(startIndex, endIndex);

    const tableBody = document.getElementById("farmersTable");
    tableBody.innerHTML = '';

    paginatedData.forEach(farmer => {
        let row = tableBody.insertRow();
        row.setAttribute('data-farmer-id', farmer.FarmerID);
        row.innerHTML = `
            <td>${farmer.FarmerName}</td>
            <td>${formatLocation(farmer.FarmLocation)}</td>
            <td>${farmer.NumberOfParcels}</td>
            <td><button onclick="viewParcelDetails(${farmer.FarmerID}, '${farmer.FarmerName}')">View Parcel Details</button></td>
        `;
    });

    // Update pagination info
    document.getElementById('parcel-count').innerText = 
        `Showing ${startIndex + 1} to ${Math.min(endIndex, filteredData.length)} of ${filteredData.length} Total Parcels`;

    // Update page number display
    document.getElementById('current-page').textContent = `Page ${currentPage}`;

    // Update button states
    const prevButton = document.getElementById("prev-page");
    const nextButton = document.getElementById("next-page");
    
    prevButton.disabled = currentPage === 1;
    nextButton.disabled = endIndex >= filteredData.length;
}

function viewParcelDetails(farmerID, farmerName) {
    fetch(`getParcelDetails.php?farmerID=${farmerID}`)
        .then(response => {
            return response.json();
        })
        .then(data => {
            // Check for error message
            if (data.error) {
                console.error('Error fetching parcel details:', data.error);
                alert(`Error: ${data.error}`);
                return;
            }
            
            // Store total farm area and total parcels
            totalFarmArea = data.totalFarmArea; // Store total farm area
            totalParcels = data.totalParcels; // Store total parcels
            
            // Check if farmerData is present and has parcel details
            if (Array.isArray(data.farmerData) && data.farmerData.length > 0) {
                parcelData = data.farmerData; // Store the parcel data
                currentParcelIndex = 0;
                document.getElementById("modal").style.display = "block";
                document.getElementById("modalTitle").textContent = `Parcel Details of ${farmerName}`;
                showParcelDetails();
                updateNavigationButtons();
            } else {
                console.warn('No parcel data received');
                alert('No parcel details found for this farmer.');
            }
        })
        .catch(error => {
            console.error('Error fetching parcel details:', error);
            alert('Failed to fetch parcel details. Please try again.');
        });
}

function groupParcelData(data) {
    const groupedData = [];
    const farmParcelMap = new Map();

    data.forEach(item => {
        const farmParcelKey = item.farm_parcel_id || 'no_parcel';
        
        // Handle farm parcel grouping
        if (!farmParcelMap.has(farmParcelKey)) {
            const farmParcel = {
                ...item,
                parcel_details: []  // Changed to store parcel details array
            };
            farmParcelMap.set(farmParcelKey, farmParcel);
            groupedData.push(farmParcel);
        }

        const farmParcel = farmParcelMap.get(farmParcelKey);
        
        // Handle parcel details grouping
        if (item.parcel_detail_id) {
            let parcelDetail = farmParcel.parcel_details.find(p => p.id === item.parcel_detail_id);
            if (!parcelDetail) {
                parcelDetail = {
                    id: item.parcel_detail_id,
                    location: item.parcel_location,
                    total_area: item.total_area,
                    ownership_type: item.ownership_type,
                    crops: []
                };
                farmParcel.parcel_details.push(parcelDetail);
            }

            // Handle crop details grouping under parcel
            if (item.crop_detail_id) {
                parcelDetail.crops.push({
                    commodity: item.crop_commodity,
                    type: item.crop_type,
                    variety: item.crop_variety,
                    numTrees: item.num_trees,
                    treeStatus: item.tree_status
                });
            }
        }
    });

    return groupedData;
}

function showParcelDetails() {
    const parcel = parcelData[currentParcelIndex];
    if (!parcel) return;

    const parcelDetails = document.getElementById("parcelDetails");
    
    // Farm Parcel Information - Organized Grid
    let detailsHTML = `
        <div class="detail-section">
            <h3>FARM PARCEL INFORMATION</h3>
            <div class="detail-grid">
                ${createDetailItem('Total Farm Area', totalFarmArea ? `${totalFarmArea} hectares` : 'N/A')}
                ${createDetailItem('Number of Parcels', totalParcels ? totalParcels : 'N/A')}
                ${createDetailItem('Farm Type', formatFarmType(parcel.farm_type))}
                ${createDetailItem('Organic Practitioner', capitalizeFirstLetter(parcel.organic))}
            </div>
        </div>
        <div class="parcel-container">`;

    // Parcel Details Section
    detailsHTML += `
        <div class="parcel-details">
            <h3>PARCEL ${currentParcelIndex + 1} DETAILS</h3>
            ${createDetailItem('Parcel Location', capitalizeFirstLetter(parcel.parcel_location))}
            <div class="detail-grid-1">
                ${createDetailItem('Parcel Area', `${parcel.farm_area} hectares`)}
                ${createDetailItem('Ownership Type', formatText(parcel.ownership_type))}
            </div>
        </div>`;

    // Crop Details Section
    detailsHTML += createCropsSection(parcel);

    detailsHTML += `</div>`; // Close parcel-container
    parcelDetails.innerHTML = detailsHTML;
    updateNavigationButtons();
}

// Function to create crops section with pagination
function createCropsSection(parcel) {
    const crops = parcel.crops || []; // Ensure we access the crops correctly

    // Check if there are crops to display
    if (crops.length === 0) {
        return `<div class="crops-section"><h3>CROP DETAILS</h3><p>No crop details available.</p></div>`;
    }

    // Get the current crop to display
    const currentCrop = crops[currentCropIndex];

    return `
        <div class="crops-section">
            <h3>CROP DETAILS</h3>
            <div class="crop-detail">
                ${createCropDetail(currentCrop, currentCropIndex + 1)} <!-- Display current crop -->
            </div>
            <div class="crop-pagination">
                <button onclick="navigateCrop(-1)" ${currentCropIndex === 0 ? 'disabled' : ''}>Previous</button>
                <span>${currentCropIndex + 1} / ${crops.length}</span>
                <button onclick="navigateCrop(1)" ${currentCropIndex === crops.length - 1 ? 'disabled' : ''}>Next</button>
            </div>
        </div>`;
}

// Function to create crop detail display
function createCropDetail(crop, index) {
    return `
        <h4>Crop ${index}</h4>                    
        ${createDetailItem('Commodity', formatText(crop.crop_commodity))}
        <div class="detail-grid-1">
        ${createDetailItem('Crop Type', formatText(crop.crop_type))}
        ${createDetailItem('Crop Variety', formatText(crop.crop_variety))}
        ${['fruits', 'plantation'].includes(crop.crop_commodity.toLowerCase()) ? 
            createDetailItem('Number of Trees', crop.num_trees || 'N/A') + 
            createDetailItem('Tree Status', formatText(crop.tree_status) || 'N/A') : ''}</div>
    `;
}

function createDetailItem(label, value) {
    return `
        <div class="detail-item">
            <div class="label">${label}</div>
            <div class="value">${value || 'N/A'}</div>
        </div>`;
}

function updateNavigationButtons() {
    const navigationButtons = document.getElementById("navigationButtons");
    navigationButtons.innerHTML = ''; // Clear existing buttons

    // Create buttons based on the number of parcels
    parcelData.forEach((_, index) => {
        const button = document.createElement('button');
        button.textContent = `Parcel ${index + 1}`;  // Changed to show "Parcel" prefix
        button.disabled = index === currentParcelIndex; // Disable current page button
        button.onclick = () => {
            currentParcelIndex = index; // Update current parcel index
            showParcelDetails(); // Show details for the selected parcel
        };
        navigationButtons.appendChild(button);
    });
}

function navigateParcel(direction) {
    const newIndex = currentParcelIndex + direction;
    
    // Check if the new index would be valid
    if (newIndex >= 0 && newIndex < parcelData.length) {
        currentParcelIndex = newIndex;
        showParcelDetails();
    }
}

function navigateCrop(direction) {
    const parcel = parcelData[currentParcelIndex];
    const crops = parcel.crops || [];

    // Update the current crop index
    currentCropIndex += direction;

    // Ensure the index stays within bounds
    if (currentCropIndex < 0) {
        currentCropIndex = 0; // Prevent going below 0
    } else if (currentCropIndex >= crops.length) {
        currentCropIndex = crops.length - 1; // Prevent going above the last crop
    }

    // Refresh the crop details display
    showParcelDetails();
}

// Function to close modal
function closeModal() {
    document.getElementById("modal").style.display = "none";
}

// Update the export button handler
function handleExport() {
    // Get the current filter values
    const locationFilter = document.getElementById("locationFilter").value;
    const searchFilter = document.getElementById("searchBox").value;
    const commodityFilter = document.getElementById("cropCommodityFilter").value;
    const typeFilter = document.getElementById("cropTypeFilter").value;

    // Create form for export
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'handleExport.php';

    // Add filter parameters
    const params = {
        barangay: locationFilter,
        search: searchFilter,
        commodity: commodityFilter,
        cropType: typeFilter
    };

    // Add inputs to form
    Object.keys(params).forEach(key => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = params[key];
        form.appendChild(input);
    });

    // Submit form
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// Update the event listener section
document.addEventListener("DOMContentLoaded", function() {
    fetchFarmers();
    
    document.getElementById("next-page").addEventListener("click", () => {
        if ((currentPage * itemsPerPage) < filteredData.length) {
            displayFarmers(currentPage + 1);
        }
    });

    document.getElementById("prev-page").addEventListener("click", () => {
        if (currentPage > 1) {
            displayFarmers(currentPage - 1);
        }
    });

    document.getElementById("locationFilter").addEventListener("input", filterTable);
    document.getElementById("searchBox").addEventListener("input", filterTable);
    document.getElementById("exportButton").addEventListener("click", handleExport);

    // Add event listeners for crop filters
    document.getElementById("cropCommodityFilter").addEventListener("change", filterTable);
    document.getElementById("cropTypeFilter").addEventListener("change", filterTable);
});
