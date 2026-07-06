$(document).ready(function() {
    let currentPage = 1;
    const cropReportsPerPage = 10;
    const reportMapState = {
        map: null,
        marker: null,
        activeLayer: 'street',
        layers: {},
        currentReport: null
    };

    const fallbackPredefinedCrops = [
        'Rice', 'Corn', 'Coconut', 'Coffee', 'Sugarcane', 'Black Pepper', 'Cacao',
        'Sweet Potato', 'Cassava', 'Ube/Yam', 'Ginger', 'Gabi', 'Ampalaya',
        'Bell Pepper', 'Bush Sitao', 'Chayote', 'Cowpea', 'Cucumber', 'Eggplant',
        'Hot Pepper', 'Lady Finger', 'Lettuce', 'Mung Bean', 'Mustard', 'Patola',
        'Pechay', 'Pole Sitao', 'Radish', 'Sigarilyas', 'Snap Beans', 'Soybeans',
        'Squash', 'Tomato', 'Upo', 'Avocado', 'Banana', 'Calamansi', 'Citrus',
        'Pomelo', 'Papaya', 'Dragon Fruit', 'Durian', 'Guyabano', 'Jackfruit',
        'Lanzones', 'Mango', 'Mango Pajo', 'Mangosteen', 'Pineapple', 'Rambutan',
        'Santol', 'Tamarind', 'Pili'
    ];

    function getPredefinedCrops() {
        if (window.SCAIMSCropCatalog && typeof window.SCAIMSCropCatalog.getAllTypes === 'function') {
            return window.SCAIMSCropCatalog.getAllTypes();
        }

        return fallbackPredefinedCrops;
    }

    function refreshCropFilterOptions() {
        if (window.SCAIMSCropCatalog && typeof window.SCAIMSCropCatalog.populateCropFilter === 'function') {
            window.SCAIMSCropCatalog.populateCropFilter(document.getElementById('crop-filter'), {
                allLabel: 'All Crops',
                includeOther: true
            });
        }
    }

    function getCropMapColor(type) {
        const colors = {
            rice: '#4ade80',
            corn: '#facc15',
            sugarcane: '#fb923c',
            banana: '#fde047',
            coffee: '#92400e',
            coconut: '#16a34a',
            calamansi: '#86efac',
            cassava: '#c084fc',
            pineapple: '#f9a8d4',
            papaya: '#fdba74',
            mango: '#fbbf24',
            tomato: '#f87171',
            eggplant: '#7c3aed',
            ampalaya: '#a3e635',
            cacao: '#78350f',
            default: '#94a3b8'
        };

        if (!type) {
            return colors.default;
        }

        return colors[type.toLowerCase().trim()] || colors.default;
    }

    function createReportMapIcon(cropType) {
        const color = getCropMapColor(cropType);
        if (window.SCAIMSCropIcons && typeof window.SCAIMSCropIcons.createLeafletIcon === 'function') {
            const cropIcon = window.SCAIMSCropIcons.createLeafletIcon(cropType, {
                color,
                label: cropType || 'Crop Report'
            });
            if (cropIcon) {
                return cropIcon;
            }
        }

        return L.divIcon({
            className: '',
            html: `<div style="width:14px;height:14px;border-radius:50%;background:${color};border:3px solid rgba(255,255,255,.9);box-shadow:0 2px 8px rgba(0,0,0,.35);"></div>`,
            iconSize: [14, 14],
            iconAnchor: [7, 7],
            popupAnchor: [0, -10]
        });
    }

    function ensureReportMap() {
        if (reportMapState.map || typeof L === 'undefined') {
            return;
        }

        reportMapState.map = L.map('crop-report-map', {
            center: [13.9411, 121.1631],
            zoom: 13,
            zoomControl: false
        });

        L.control.zoom({ position: 'bottomright' }).addTo(reportMapState.map);

        reportMapState.layers.street = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap',
            maxZoom: 19
        });
        reportMapState.layers.satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: '&copy; Esri',
            maxZoom: 19
        });

        reportMapState.layers.street.addTo(reportMapState.map);

        $('#crop-map-street').on('click', function () {
            setReportMapLayer('street');
        });
        $('#crop-map-satellite').on('click', function () {
            setReportMapLayer('satellite');
        });
    }

    function setReportMapLayer(layerName) {
        ensureReportMap();
        if (!reportMapState.map || reportMapState.activeLayer === layerName) {
            return;
        }

        reportMapState.map.removeLayer(reportMapState.layers[reportMapState.activeLayer]);
        reportMapState.layers[layerName].addTo(reportMapState.map);
        reportMapState.activeLayer = layerName;

        $('#crop-map-street, #crop-map-satellite').removeClass('active');
        $('#crop-map-' + layerName).addClass('active');
    }

    function updateReportMapCard(report) {
        reportMapState.currentReport = report;

        const latitude = parseFloat(report.Latitude);
        const longitude = parseFloat(report.Longitude);
        const hasCoordinates = Number.isFinite(latitude) && Number.isFinite(longitude);
        const locationLabel = report.Barangay || report.Location || 'N/A';
        const coordinatesLabel = hasCoordinates
            ? `${latitude.toFixed(7)}, ${longitude.toFixed(7)}`
            : 'No saved coordinates';

        $('#mapCardLocation').text(locationLabel);
        $('#mapCardCoordinates').text(coordinatesLabel);
        $('#open-report-map-button').prop('disabled', !hasCoordinates);
        $('#show-directions-button').prop('disabled', !hasCoordinates);

        if (!hasCoordinates) {
            $('#crop-report-map').hide();
            $('#crop-report-map-message').text('No latitude and longitude available for this crop report.').show();

            if (reportMapState.marker && reportMapState.map) {
                reportMapState.map.removeLayer(reportMapState.marker);
                reportMapState.marker = null;
            }
            return;
        }

        $('#crop-report-map-message').hide();
        $('#crop-report-map').show();

        ensureReportMap();
        setReportMapLayer(reportMapState.activeLayer);

        if (reportMapState.marker) {
            reportMapState.map.removeLayer(reportMapState.marker);
        }

        reportMapState.marker = L.marker([latitude, longitude], {
            icon: createReportMapIcon(report.Crop)
        }).addTo(reportMapState.map);

        reportMapState.marker.bindPopup(`
            <div style="min-width:240px;font-family:Inter,sans-serif;">
                <div style="background:${getCropMapColor(report.Crop)};padding:12px 16px 10px;">
                    <div style="font-weight:700;font-size:15px;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,.3);">
                        ${report.CropCommodity || report.Crop || 'Crop Report'}
                    </div>
                    <div style="font-size:11px;color:rgba(255,255,255,.82);margin-top:2px;">
                        ${(report.CropVariety || 'No variety')} · ${(report.Barangay || 'Lipa City')}
                    </div>
                </div>
                <div style="padding:12px 16px;background:#fff;font-size:12px;">
                    <div style="display:flex;justify-content:space-between;gap:8px;margin-bottom:5px;">
                        <span style="color:#8a9a6a;">Farmer</span>
                        <span style="color:#3b4918;font-weight:600;text-align:right;">${report.Name || 'N/A'}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;gap:8px;margin-bottom:5px;">
                        <span style="color:#8a9a6a;">Crop Type</span>
                        <span style="color:#3b4918;font-weight:600;text-align:right;">${report.Crop || 'N/A'}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;gap:8px;margin-bottom:5px;">
                        <span style="color:#8a9a6a;">Coordinates</span>
                        <span style="color:#3b4918;font-weight:600;text-align:right;">${coordinatesLabel}</span>
                    </div>
                </div>
            </div>
        `).openPopup();

        reportMapState.map.setView([latitude, longitude], 17);
        setTimeout(function () {
            reportMapState.map.invalidateSize();
        }, 120);
    }

    function updatePagination(totalReports) {
        const totalPages = Math.ceil(totalReports / cropReportsPerPage);
        
        // Disable previous button if on first page
        $('#prev-page').prop('disabled', currentPage === 1);
        
        // Disable next button if on last page or no pages
        $('#next-page').prop('disabled', currentPage >= totalPages || totalPages === 0);
        
        // Update page text
        if (totalPages === 0) {
            $('#current-page').text('Page 0 of 0');
        } else {
            $('#current-page').text(`Page ${currentPage} of ${totalPages}`);
        }
    }

    function loadCropReports(filter, barangay, reporterType, page) {
        console.log('Filter:', filter);
        
        $('#new-reports-body, #previous-reports-body').html('<tr><td colspan="11">Loading...</td></tr>');
        
        // Get the month and year values separately
        const monthYearValue = $('#month-filter').val();
        const [year, month] = monthYearValue ? monthYearValue.split('-') : ['', ''];
        
        const predefinedCrops = getPredefinedCrops();
        
        $.ajax({
            url: 'fetchCropReports.php',
            type: 'POST',
            dataType: 'json',
            data: { 
                filter,
                barangay,
                reporterType,
                search: $('#search-bar').val(),
                month,
                year,
                page: page,
                predefinedCrops: filter === 'Other' ? predefinedCrops : null // Send predefined crops list when "Other" is selected
            },
            success: function(response) {
                console.log('Server response:', response);
                try {
                    if (response.error) {
                        console.error('Server error:', response.error);
                        $('#new-reports-body, #previous-reports-body').html(
                            '<tr><td colspan="11">Error: ' + response.error + '</td></tr>'
                        );
                        return;
                    }

                    $('#new-reports-body').html(response.newOutput || '<tr><td colspan="11">No new reports</td></tr>');
                    $('#previous-reports-body').html(response.previousOutput || '<tr><td colspan="11">No previous reports</td></tr>');
                    
                    $('#cropreport-count').html(
                        `Total Crop Reports: ${response.newCount + response.previousCount}<br>` +
                        `(${response.newCount} New, ${response.previousCount} Previous)`
                    );
            
                    $('#current-page').text(`Page ${page}`);
                    updatePagination(response.total);
                    
                    window.currentFilters = {
                        crop: filter,
                        barangay: barangay,
                        reporterType: reporterType,
                        search: $('#search-bar').val(),
                        month: $('#month-filter').val()
                    };
                    
                } catch (error) {
                    console.error('Error processing response:', error);
                    $('#new-reports-body, #previous-reports-body').html(
                        '<tr><td colspan="11">Error processing data. Please try again.</td></tr>'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX request failed:', status, error);
                console.error('Response:', xhr.responseText);
                $('#new-reports-body, #previous-reports-body').html(
                    '<tr><td colspan="11">Failed to load data. Please check your connection.</td></tr>'
                );
            }
        });
    }

    function refreshTable() {
        currentPage = 1;
        loadCropReports($('#crop-filter').val(), $('#barangay-filter').val(), $('#reporter-filter').val(), currentPage);
    }

    $('#search-button, #crop-filter, #barangay-filter, #month-filter, #reporter-filter').on('change input', function() {
        refreshTable();
    });

    $(document).ready(function() {
        refreshCropFilterOptions();
        refreshTable();

        document.addEventListener('scaims:crop-catalog-ready', function () {
            refreshCropFilterOptions();
            refreshTable();
        });
        
        $('#crop-filter').on('change', function() {
            console.log('Selected crop:', $(this).val());
            refreshTable();
        });
    });

    $('#search-bar').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            currentPage = 1;
            loadCropReports($('#crop-filter').val(), $('#barangay-filter').val(), $('#reporter-filter').val(), currentPage);
        }
    });

    let debounceTimer;
    $('#search-bar').on('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function() {
            currentPage = 1;
            loadCropReports($('#crop-filter').val(), $('#barangay-filter').val(), $('#reporter-filter').val(), currentPage);
        }, 300);
    });

    $('#next-page, #prev-page').on('click', function() {
        // Don't process if button is disabled
        if ($(this).prop('disabled')) {
            return;
        }
        
        const isPrev = $(this).attr('id') === 'prev-page';
        
        if (isPrev && currentPage > 1) {
            currentPage--;
        } else if (!isPrev) {
            currentPage++;
        }
        
        loadCropReports($('#crop-filter').val(), $('#barangay-filter').val(), $('#reporter-filter').val(), currentPage);
        
        // Smooth scroll to the Previous Reports section
        const previousReportsSection = document.querySelector('h3.section-title:contains("Previous Crop Reports")');
        if (previousReportsSection) {
            previousReportsSection.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });

    $('#export-button').on('click', function() {
        const currentFilters = window.currentFilters || {};
        
        let exportUrl = 'exportCropReports.php?';
        const params = [];
        
        if (currentFilters.crop) params.push(`crop=${encodeURIComponent(currentFilters.crop)}`);
        if (currentFilters.barangay) params.push(`barangay=${encodeURIComponent(currentFilters.barangay)}`);
        if (currentFilters.reporterType) params.push(`reporterType=${encodeURIComponent(currentFilters.reporterType)}`);
        if (currentFilters.search) params.push(`search=${encodeURIComponent(currentFilters.search)}`);
        if (currentFilters.month) params.push(`month=${encodeURIComponent(currentFilters.month)}`);
        
        exportUrl += params.join('&');

        // Use fetch to handle the response
        fetch(exportUrl)
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(text || 'Export failed');
                    });
                }
                return response.blob();
            })
            .then(blob => {
                // Create and click a temporary download link
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'CropReports.xls';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                a.remove();
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
    });

    // Add event delegation for the view buttons
    $(document).on('click', '.action-button', function() {
        const reportId = $(this).closest('tr').data('id');
        if (reportId) {
            showCropReportDetails(reportId);
        }
    });

    function showCropReportDetails(reportId) {
        $.ajax({
            url: 'getCropReports.php',
            type: 'POST',
            data: { id: reportId },
            success: function(data) {
                const report = JSON.parse(data);
                if (report.error) {
                    alert(report.error);
                    return;
                }
                // Populate modal fields
                $('#farmerID').text(report.Name);
                $('#reportedBy').text(report.ReportedBy || 'N/A');
                $('#reporterType').text(report.ReporterType || 'N/A');
                $('#location').text(report.Barangay);
                $('#cropCommodity').text(report.CropCommodity);
                $('#cropType').text(report.Crop);
                $('#cropVariety').text(report.CropVariety);
                $('#datePlanted').text(report.DatePlanted);
                $('#harvestDate').text(report.HarvestDate);
                $('#estimateHarvest').text(report.EstimatedHarvest);
                $('#cropStatus').text(report.CropStatus);
                $('#farmGatePrice').text(report.FarmGatePrice);
                $('#numberOfTrees').text(report.NumberOfTrees);
                $('#cropLatitude').text(report.Latitude || 'N/A');
                $('#cropLongitude').text(report.Longitude || 'N/A');
                $('#dateReported').text(report.DateReported);
                updateReportMapCard(report);
                // Show the modal
                $('#cropReportModal').show();
                setTimeout(function () {
                    if (reportMapState.map) {
                        reportMapState.map.invalidateSize();
                    }
                }, 150);
            },
            error: function() {
                alert('Error fetching report details.');
            }
        });
    }

    // Close button functionality
    $('.close-button').click(function() {
        $('#cropReportModal').hide();
    });

    // Click outside modal to close
    $(window).click(function(event) {
        if ($(event.target).is('.modal')) {
            $('.modal').hide();
        }
    });

    $('#open-report-map-button').on('click', function () {
        const report = reportMapState.currentReport;
        if (!report) {
            return;
        }

        const latitude = parseFloat(report.Latitude);
        const longitude = parseFloat(report.Longitude);
        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
            return;
        }

        const params = new URLSearchParams({
            lat: latitude.toFixed(7),
            lng: longitude.toFixed(7),
            crop: report.Crop || '',
            commodity: report.CropCommodity || '',
            location: report.Barangay || ''
        });

    window.open(resolveAppPath('/FARMERS%20UI/farmerLanding/map.html') + '?' + params.toString(), '_blank');
    });

    $('#show-directions-button').on('click', function () {
        const report = reportMapState.currentReport;
        if (!report) {
            return;
        }

        const latitude = parseFloat(report.Latitude);
        const longitude = parseFloat(report.Longitude);
        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
            return;
        }

        const destination = `${latitude.toFixed(7)},${longitude.toFixed(7)}`;
        const directionsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(destination) + '&travelmode=driving';
        window.open(directionsUrl, '_blank');
    });

    // Helper function to format dates
    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-PH', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }

    // Load Chart.js library
    $.getScript('https://cdn.jsdelivr.net/npm/chart.js', function() {
        let harvestChart;

        $('#view-graph-button').on('click', function() {
            const cropType = $('#crop-filter').val();
            if (!cropType) {
                alert('Please select a crop type to view the graph.');
                return;
            }

            // First, fetch available years for this crop type
            $.ajax({
                url: 'fetchAvailableYears.php',
                type: 'POST',
                data: { cropType },
                success: function(response) {
                    try {
                        const years = JSON.parse(response);
                        
                        // Populate year filter dropdown
                        const yearFilter = $('#graph-year-filter');
                        yearFilter.empty();
                        years.forEach(year => {
                            yearFilter.append(`<option value="${year}">${year}</option>`);
                        });
                        
                        // Set current year as default
                        const currentYear = new Date().getFullYear();
                        yearFilter.val(currentYear);
                        
                        // Load initial graph data
                        loadGraphData(cropType, currentYear);
                    } catch (error) {
                        console.error('Error parsing years:', error);
                        alert('Failed to load available years.');
                    }
                }
            });
        });

        // New function to load graph data
        function loadGraphData(cropType, year) {
            $.ajax({
                url: 'fetchGraphData.php',
                type: 'POST',
                data: { 
                    cropType,
                    year 
                },
                success: function(response) {
                    try {
                        const data = JSON.parse(response);
                        
                        if (data.error) {
                            alert(data.error);
                            return;
                        }

                        // Store the data for use in export functions
                        window.graphData = data;

                        const ctx = document.getElementById('harvestGraph').getContext('2d');

                        if (harvestChart) {
                            harvestChart.destroy();
                        }

                        // Create dataset for the selected year
                        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                                          'July', 'August', 'September', 'October', 'November', 'December'];

                        const yearData = data.yearlyData[year];
                        const datasets = [{
                            label: `${year} (Farmers)`,
                            data: yearData.harvestCounts,
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1
                        }];

                        // Update modal title
                        $('#graphModalTitle').text(`Estimated Harvest Graph for ${cropType} - ${year}`);

                        harvestChart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: monthNames,
                                datasets: datasets
                            },
                            options: {
                                responsive: true,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        title: {
                                            display: true,
                                            text: 'Number of Farmers',
                                            font: {
                                                size: 14,
                                                weight: 'bold'
                                            }
                                        }
                                    },
                                    x: {
                                        title: {
                                            display: true,
                                            text: 'Months',
                                            font: {
                                                size: 14,
                                                weight: 'bold'
                                            }
                                        }
                                    }
                                },
                                plugins: {
                                    legend: {
                                        display: true,
                                        position: 'top',
                                        labels: {
                                            font: {
                                                size: 12
                                            }
                                        }
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                return `${context.dataset.label}: ${context.raw} farmers`;
                                            }
                                        }
                                    }
                                }
                            }
                        });

                        // Add export buttons container if it doesn't exist
                        if (!$('#export-buttons-container').length) {
                            const buttonsContainer = $('<div>', {
                                id: 'export-buttons-container',
                                css: {
                                    'margin-top': '20px',
                                    'display': 'flex',
                                    'gap': '10px',
                                    'justify-content': 'center'
                                }
                            });

                            const exportPdfButton = $('<button>', {
                                id: 'export-pdf-button',
                                text: 'Export as PDF',
                                class: 'export-button',
                                css: {
                                    'padding': '8px 16px',
                                    'background-color': '#198754',
                                    'color': 'white',
                                    'border': 'none',
                                    'border-radius': '4px',
                                    'cursor': 'pointer',
                                    'font-family': 'Poppins',
                                    'hover': 'background-color: #3cc786'
                                }
                            });

                            const exportExcelButton = $('<button>', {
                                id: 'export-excel-button',
                                text: 'Export as Excel',
                                class: 'export-button',
                                css: {
                                    'padding': '8px 16px',
                                    'background-color': '#198754',
                                    'color': 'white',
                                    'border': 'none',
                                    'border-radius': '4px',
                                    'cursor': 'pointer',
                                    'font-family': 'Poppins',
                                    'hover': 'background-color: #3cc786'
                                }
                            });

                            buttonsContainer.append(exportPdfButton, exportExcelButton);
                            $('#graphModal .modal-content').append(buttonsContainer);
                        }

                        // Show the modal
                        $('#graphModal').show();

                        // Handle PDF export button click
                        $('#export-pdf-button').off('click').on('click', function() {
                            const data = window.graphData;  // Get the stored data
                            if (!data) {
                                alert('No data available for export. Please try refreshing the graph.');
                                return;
                            }
                            const cropType = $('#crop-filter').val();
                            const selectedYear = $('#graph-year-filter').val();
                            const currentDateTime = new Date().toLocaleString();
                            
                            // Get data directly from the response data
                            const yearData = data.yearlyData[selectedYear]; // Use the data from the AJAX response
                            
                            const { jsPDF } = window.jspdf;
                            const pdf = new jsPDF({
                                orientation: 'landscape',
                                unit: 'mm',
                                format: 'a4'
                            });

                            // Page 1: Title and Chart (Landscape)
                            // Add header with border
                            pdf.setDrawColor(25, 135, 84); // #198754
                            pdf.setLineWidth(0.5);
                            pdf.line(20, 25, pdf.internal.pageSize.width - 20, 25);

                            // Add title
                            pdf.setFont('helvetica', 'bold');
                            pdf.setFontSize(20);
                            pdf.setTextColor(25, 135, 84);
                            pdf.text('CROP HARVEST DISTRIBUTION REPORT', pdf.internal.pageSize.width/2, 20, { align: 'center' });

                            // Add subtitle information
                            pdf.setFont('helvetica', 'normal');
                            pdf.setFontSize(12);
                            pdf.setTextColor(0);
                            pdf.text(`Crop Type: ${cropType}`, pdf.internal.pageSize.width/2, 35, { align: 'center' });
                            pdf.text(`Year: ${selectedYear}`, pdf.internal.pageSize.width/2, 42, { align: 'center' });

                            // Convert chart to image
                            const canvas = document.getElementById('harvestGraph');
                            const chartImage = canvas.toDataURL('image/png', 1.0);
                            
                            // Add chart to PDF (centered and larger)
                            const chartWidth = 220;
                            const chartHeight = 140;
                            const chartX = (pdf.internal.pageSize.width - chartWidth) / 2;
                            pdf.addImage(chartImage, 'PNG', chartX, 50, chartWidth, chartHeight);

                            // Add timestamp
                            pdf.setFontSize(8);
                            pdf.setTextColor(128);
                            pdf.text(`Generated on: ${currentDateTime}`, pdf.internal.pageSize.width - 20, pdf.internal.pageSize.height - 10, { align: 'right' });

                            // Page 2: Summary Table (Portrait)
                            pdf.addPage('p');

                            // Add summary title
                            pdf.setFont('helvetica', 'bold');
                            pdf.setFontSize(14);
                            pdf.setTextColor(25, 135, 84);
                            pdf.text('MONTHLY HARVEST SUMMARY', pdf.internal.pageSize.width/2, 20, { align: 'center' });

                            // Add decorative line
                            pdf.setDrawColor(25, 135, 84);
                            pdf.line(20, 25, pdf.internal.pageSize.width - 20, 25);

                            // Get data from chart
                            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                                            'July', 'August', 'September', 'October', 'November', 'December'];
                            const harvestData = yearData.harvestCounts;
                            
                            // Calculate totals
                            const totalFarmers = harvestData.reduce((a, b) => a + b, 0);

                            // Add totals
                            pdf.setFontSize(10);
                            pdf.setTextColor(0);
                            pdf.text(`Total Number of Farmers: ${totalFarmers}`, 20, 35);

                            // Table setup
                            const tableStartY = 45;
                            const pageWidth = pdf.internal.pageSize.width;
                            const colWidths = [pageWidth * 0.4, pageWidth * 0.2];
                            const rowHeight = 8;
                            let currentY = tableStartY;

                            // Table headers
                            pdf.setFillColor(25, 135, 84);
                            pdf.setTextColor(255);
                            pdf.rect(20, currentY - 6, pageWidth - 40, rowHeight, 'F');
                            
                            const headers = ['Month', 'Number of Farmers'];
                            let currentX = 20;
                            
                            pdf.setFontSize(9);
                            headers.forEach((header, i) => {
                                pdf.text(header, currentX + 3, currentY);
                                currentX += colWidths[i];
                            });

                            // Table data
                            pdf.setTextColor(0);
                            monthNames.forEach((month, index) => {
                                if (harvestData[index] > 0) {
                                    currentY += rowHeight;
                                    
                                    // Zebra striping
                                    if (index % 2 === 0) {
                                        pdf.setFillColor(240, 240, 240);
                                        pdf.rect(20, currentY - 6, pageWidth - 40, rowHeight, 'F');
                                    }

                                    currentX = 20;
                                    pdf.text(month, currentX + 3, currentY);
                                    currentX += colWidths[0];
                                    pdf.text(harvestData[index].toString(), currentX + 3, currentY);
                                }
                            });

                            // Add border to table
                            pdf.setDrawColor(25, 135, 84);
                            pdf.rect(20, tableStartY - 6, pageWidth - 40, currentY - tableStartY + rowHeight);

                            // Add note
                            pdf.setFontSize(8);
                            pdf.setTextColor(128);
                            pdf.text('Note: This report shows the distribution of farmers harvesting crops across different months.', 
                                    20, pdf.internal.pageSize.height - 20);

                            if (cropType === 'Other' && yearData.cropTypes) {
                                // Add a new page for Other Crops breakdown
                                pdf.addPage();
                                
                                pdf.setFont('helvetica', 'bold');
                                pdf.setFontSize(14);
                                pdf.setTextColor(25, 135, 84);
                                pdf.text('BREAKDOWN OF OTHER CROPS', pdf.internal.pageSize.width/2, 20, { align: 'center' });

                                let yPos = 40;
                                monthNames.forEach((month, monthIndex) => {
                                    if (yearData.cropTypes[monthIndex] && yearData.cropTypes[monthIndex].length > 0) {
                                        pdf.setFontSize(12);
                                        pdf.setTextColor(0);
                                        pdf.text(`${month}:`, 20, yPos);
                                        yPos += 8;

                                        yearData.cropTypes[monthIndex].forEach(crop => {
                                            pdf.setFontSize(10);
                                            pdf.text(`• ${crop.type}: ${crop.count} farmers (${crop.harvest} kg)`, 30, yPos);
                                            yPos += 6;
                                        });
                                        yPos += 4;

                                        // Add a new page if we're running out of space
                                        if (yPos > pdf.internal.pageSize.height - 20) {
                                            pdf.addPage();
                                            yPos = 20;
                                        }
                                    }
                                });
                            }

                            // Save PDF
                            const pdfFileName = `CropHarvestDistributionReport_${cropType}_${selectedYear}.pdf`;
                            pdf.save(pdfFileName);
                        });

                        // Handle Excel export button click
                        $('#export-excel-button').off('click').on('click', function() {
                            const data = window.graphData;  // Get the stored data
                            if (!data) {
                                alert('No data available for export. Please try refreshing the graph.');
                                return;
                            }
                            const cropType = $('#crop-filter').val();
                            const selectedYear = $('#graph-year-filter').val();
                            
                            // Get data directly from the response data
                            const yearData = data.yearlyData[selectedYear]; // Use the data from the AJAX response
                            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                                               'July', 'August', 'September', 'October', 'November', 'December'];

                            // Create main summary sheet data
                            const wsData = [['Month', 'Number of Farmers', 'Total Harvest (kg)']];
                            monthNames.forEach((month, index) => {
                                wsData.push([
                                    month,
                                    yearData.harvestCounts[index],
                                    yearData.totalHarvest[index]
                                ]);
                            });

                            // Create workbook
                            const wb = XLSX.utils.book_new();
                            
                            // Add main summary sheet
                            const ws = XLSX.utils.aoa_to_sheet(wsData);
                            XLSX.utils.book_append_sheet(wb, ws, 'Summary');

                            if (cropType === 'Other' && yearData.cropTypes) {
                                // Add breakdown sheet for Other crops
                                const breakdownData = [['Month', 'Crop Type', 'Number of Farmers', 'Total Harvest (kg)']];
                                
                                monthNames.forEach((month, index) => {
                                    if (yearData.cropTypes[index] && yearData.cropTypes[index].length > 0) {
                                        yearData.cropTypes[index].forEach(crop => {
                                            breakdownData.push([
                                                month,
                                                crop.type,
                                                crop.count,
                                                crop.harvest
                                            ]);
                                        });
                                    }
                                });
                                
                                // Add breakdown sheet
                                const wsBreakdown = XLSX.utils.aoa_to_sheet(breakdownData);
                                XLSX.utils.book_append_sheet(wb, wsBreakdown, 'Crop Breakdown');
                            }
                            
                            // Generate Excel file
                            const fileName = cropType === 'Other' ? 
                                `HarvestData_Other_${selectedYear}.xlsx` : 
                                `HarvestData_${cropType}_${selectedYear}.xlsx`;
                            XLSX.writeFile(wb, fileName);
                        });
                    } catch (error) {
                        console.error('Error parsing graph data:', error);
                        alert('Failed to load graph data.');
                    }
                }
            });
        }

        // Close button functionality for the graph modal
        $('.close-button').on('click', function() {
            $('#graphModal').hide(); // Hide the graph modal
        });

        // Optionally, you can also close the modal when clicking outside of it
        $(window).on('click', function(event) {
            if ($(event.target).is('#graphModal')) {
                $('#graphModal').hide(); // Hide the modal if the background is clicked
            }
        });
    });
});
const resolveAppPath = typeof window.resolveAppPath === 'function'
  ? window.resolveAppPath
  : (path) => {
      const pathname = window.location.pathname || '';
      const basePath = (pathname === '/scaims' || pathname.indexOf('/scaims/') === 0) ? '/scaims' : '';
      return path.charAt(0) === '/' ? basePath + path : path;
    };
