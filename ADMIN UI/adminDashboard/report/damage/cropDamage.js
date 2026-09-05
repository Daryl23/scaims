$(document).ready(function() {
    let currentPage = 1;
    const damageReportsPerPage = 10;
    const damageMapState = {
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
        'Santol', 'Tamarind'
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

    function getDamageMapColor(type) {
        const colors = {
            rice: '#dc2626',
            corn: '#f59e0b',
            sugarcane: '#fb923c',
            banana: '#facc15',
            coffee: '#92400e',
            coconut: '#16a34a',
            calamansi: '#84cc16',
            cassava: '#8b5cf6',
            pineapple: '#ec4899',
            papaya: '#fb7185',
            mango: '#fbbf24',
            tomato: '#ef4444',
            eggplant: '#7c3aed',
            cacao: '#78350f',
            default: '#94a3b8'
        };

        if (!type) {
            return colors.default;
        }

        return colors[type.toLowerCase().trim()] || colors.default;
    }

    function createDamageMapIcon(cropType) {
        const color = getDamageMapColor(cropType);
        if (window.SCAIMSCropIcons && typeof window.SCAIMSCropIcons.createLeafletIcon === 'function') {
            const cropIcon = window.SCAIMSCropIcons.createLeafletIcon(cropType, {
                color,
                label: cropType || 'Damage Report'
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

    function ensureDamageMap() {
        if (damageMapState.map || typeof L === 'undefined') {
            return;
        }

        damageMapState.map = L.map('damage-report-map', {
            center: [13.9411, 121.1631],
            zoom: 13,
            zoomControl: false
        });

        L.control.zoom({ position: 'bottomright' }).addTo(damageMapState.map);

        damageMapState.layers.street = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap',
            maxZoom: 19
        });
        damageMapState.layers.satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: '&copy; Esri',
            maxZoom: 19
        });

        damageMapState.layers.street.addTo(damageMapState.map);

        $('#damage-map-street').on('click', function () {
            setDamageMapLayer('street');
        });
        $('#damage-map-satellite').on('click', function () {
            setDamageMapLayer('satellite');
        });
    }

    function setDamageMapLayer(layerName) {
        ensureDamageMap();
        if (!damageMapState.map || damageMapState.activeLayer === layerName) {
            return;
        }

        damageMapState.map.removeLayer(damageMapState.layers[damageMapState.activeLayer]);
        damageMapState.layers[layerName].addTo(damageMapState.map);
        damageMapState.activeLayer = layerName;

        $('#damage-map-street, #damage-map-satellite').removeClass('active');
        $('#damage-map-' + layerName).addClass('active');
    }

    function updateDamageMapCard(report) {
        damageMapState.currentReport = report;

        const latitude = parseFloat(report.Latitude);
        const longitude = parseFloat(report.Longitude);
        const hasCoordinates = Number.isFinite(latitude) && Number.isFinite(longitude);
        const locationLabel = report.Location || report.Barangay || 'N/A';
        const coordinatesLabel = hasCoordinates
            ? `${latitude.toFixed(7)}, ${longitude.toFixed(7)}`
            : 'No saved coordinates';

        $('#damageMapCardLocation').text(locationLabel);
        $('#damageMapCardCoordinates').text(coordinatesLabel);
        $('#damage-open-map-button').prop('disabled', !hasCoordinates);
        $('#damage-show-directions-button').prop('disabled', !hasCoordinates);

        if (!hasCoordinates) {
            $('#damage-report-map').hide();
            $('#damage-report-map-message').text('No latitude and longitude available for this damage report.').show();

            if (damageMapState.marker && damageMapState.map) {
                damageMapState.map.removeLayer(damageMapState.marker);
                damageMapState.marker = null;
            }
            return;
        }

        $('#damage-report-map-message').hide();
        $('#damage-report-map').show();

        ensureDamageMap();
        setDamageMapLayer(damageMapState.activeLayer);

        if (damageMapState.marker) {
            damageMapState.map.removeLayer(damageMapState.marker);
        }

        damageMapState.marker = L.marker([latitude, longitude], {
            icon: createDamageMapIcon(report.CropType)
        }).addTo(damageMapState.map);

        damageMapState.marker.bindPopup(`
            <div style="min-width:240px;font-family:Inter,sans-serif;">
                <div style="background:${getDamageMapColor(report.CropType)};padding:12px 16px 10px;">
                    <div style="font-weight:700;font-size:15px;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,.3);">
                        ${report.CropCommodity || report.CropType || 'Damage Report'}
                    </div>
                    <div style="font-size:11px;color:rgba(255,255,255,.82);margin-top:2px;">
                        ${(report.CropVariety || 'No variety')} · ${(report.Location || 'Lipa City')}
                    </div>
                </div>
                <div style="padding:12px 16px;background:#fff;font-size:12px;">
                    <div style="display:flex;justify-content:space-between;gap:8px;margin-bottom:5px;">
                        <span style="color:#8a9a6a;">Farmer</span>
                        <span style="color:#3b4918;font-weight:600;text-align:right;">${report.Name || 'N/A'}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;gap:8px;margin-bottom:5px;">
                        <span style="color:#8a9a6a;">Damage Cause</span>
                        <span style="color:#3b4918;font-weight:600;text-align:right;">${report.DamageCause || 'N/A'}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;gap:8px;margin-bottom:5px;">
                        <span style="color:#8a9a6a;">Coordinates</span>
                        <span style="color:#3b4918;font-weight:600;text-align:right;">${coordinatesLabel}</span>
                    </div>
                </div>
            </div>
        `).openPopup();

        damageMapState.map.setView([latitude, longitude], 17);
        setTimeout(function () {
            damageMapState.map.invalidateSize();
        }, 120);
    }

    window.damageMapState = damageMapState;
    window.updateDamageMapCard = updateDamageMapCard;

    function updatePagination(totalReports) {
        const totalPages = Math.ceil(totalReports / damageReportsPerPage);
        
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

    refreshCropFilterOptions();
    document.addEventListener('scaims:crop-catalog-ready', function () {
        refreshCropFilterOptions();
        handleFilterChange();
    });

    function handleFilterChange() {
        currentPage = 1;
        loadDamageReports(
            $('#crop-filter').val(),
            $('#barangay-filter').val(),
            $('#reporter-filter').val(),
            currentPage
        );
    }
    
    $('#crop-filter, #barangay-filter, #reporter-filter').on('change', handleFilterChange);
    
    function loadDamageReports(filter, barangay, reporterType, page) {
        const searchTerm = $('#search-bar').val();
        const filterDate = $('#date-filter').val();

        const predefinedCrops = getPredefinedCrops();

        // Format the filter value to match database format
        let formattedFilter = filter;
        if (filter && filter !== 'Other') {
            // Convert filter to lowercase and replace spaces with hyphens
            formattedFilter = filter.toLowerCase().replace(/\s+/g, '-');
        }

        $.ajax({
            url: 'fetchDamageReports.php',
            type: 'POST',
            data: { 
                filter: formattedFilter, 
                barangay: barangay, 
                reporterType: reporterType,
                search: searchTerm,
                page: page,
                filterDate: filterDate,
                predefinedCrops: filter === 'Other' ? predefinedCrops : null
            },
            success: function(response) {
                try {
                    const data = JSON.parse(response);
                    if (data.error) {
                        console.error('Server error:', data.error);
                        return;
                    }

                    // Clear existing table content
                    $('#new-reports-body').empty();
                    $('#previous-reports-body').empty();

                    // Update tables with new data
                    if (data.newOutput) {
                        $('#new-reports-body').html(data.newOutput);
                    } else {
                        $('#new-reports-body').html('<tr><td colspan="10">No new reports found</td></tr>');
                    }
                    
                    if (data.previousOutput) {
                        $('#previous-reports-body').html(data.previousOutput);
                    } else {
                        $('#previous-reports-body').html('<tr><td colspan="10">No previous reports found</td></tr>');
                    }
                    
                    // Update count display
                    if (data.total > 0) {
                        $('#damage-report-count').html(
                            `Total Damage Reports: ${data.newCount + data.previousCount}<br>` +
                            `(${data.newCount} New, ${data.previousCount} Previous)`
                        );
                    } else {
                        $('#damage-report-count').text('No damage reports found');
                    }
                    
                    // Update pagination
                    updatePagination(data.total);
                    
                } catch (error) {
                    console.error('Error parsing response:', error);
                        $('#new-reports-body, #previous-reports-body').html(
                        '<tr><td colspan="10">Error processing data</td></tr>'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                $('#new-reports-body, #previous-reports-body').html(
                    '<tr><td colspan="10">Failed to load data</td></tr>'
                );
            }
        });
    }

    loadDamageReports(
        $('#crop-filter').val(),
        $('#barangay-filter').val(),
        $('#reporter-filter').val(),
        currentPage
    );

    $('#search-bar').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            currentPage = 1;
            loadDamageReports($('#crop-filter').val(), $('#barangay-filter').val(), $('#reporter-filter').val(), currentPage);
        }
    });

    let debounceTimer;
    $('#search-bar').on('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function() {
            currentPage = 1;
            loadDamageReports($('#crop-filter').val(), $('#barangay-filter').val(), $('#reporter-filter').val(), currentPage);
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
        
        loadDamageReports($('#crop-filter').val(), $('#barangay-filter').val(), $('#reporter-filter').val(), currentPage);
        
        // Smooth scroll to the Previous Reports section
        const previousReportsSection = document.querySelector('h3.section-title:contains("Previous Damage Reports")');
        if (previousReportsSection) {
            previousReportsSection.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });

    $('#export-button').on('click', function() {
        const crop = $('#crop-filter').val();
        const barangay = $('#barangay-filter').val();
        const reporterType = $('#reporter-filter').val();
        const search = $('#search-bar').val();
        const filterDate = $('#date-filter').val();

        // Create form and submit it
        const form = $('<form>', {
            'method': 'POST',
            'action': 'exportDamageReports.php',
            'style': 'display: none'
        });

        // Add input fields
        if (crop) form.append($('<input>', {'name': 'crop', 'value': crop}));
        if (barangay) form.append($('<input>', {'name': 'barangay', 'value': barangay}));
        if (reporterType) form.append($('<input>', {'name': 'reporterType', 'value': reporterType}));
        if (search) form.append($('<input>', {'name': 'search', 'value': search}));
        if (filterDate) form.append($('<input>', {'name': 'filterDate', 'value': filterDate}));

        // Append form to body and submit
        $('body').append(form);
        form.submit();
        form.remove();
    });

    $('#date-filter').on('change', function() {
        currentPage = 1;
        loadDamageReports($('#crop-filter').val(), $('#barangay-filter').val(), $('#reporter-filter').val(), currentPage);
    });

    let damageChart = null;

    $('#view-graph-button').on('click', function() {
        const barangay = $('#barangay-filter').val();
        const filterDate = $('#date-filter').val();
        const cropFilter = $('#crop-filter').val();
        
        // Show loading state
        $(this).prop('disabled', true);
        $(this).text('Loading...');

        $.ajax({
            url: 'getGraphData.php',
            type: 'POST',
            data: { 
                barangay: barangay,
                filterDate: filterDate,
                filter: cropFilter
            },
            success: function(response) {
                try {
                    const data = JSON.parse(response);
                    if (!data.success) {
                        console.error('Server error:', data.error);
                        alert('Error loading graph data');
                        return;
                    }

                    // Get the canvas element
                    const ctx = document.getElementById('damageChart').getContext('2d');

                    // Destroy existing chart if it exists
                    if (window.damageChart instanceof Chart) {
                        window.damageChart.destroy();
                    }

                    // Format the date for display
                    const displayDate = filterDate ? 
                        new Date(filterDate).toLocaleDateString('en-US', { month: 'long', year: 'numeric' }) : 
                        'All Time';

                    // Prepare data for the chart
                    let chartData = {
                        labels: data.data.map(item => `${item.cropType} (${item.count} reports)`),
                        datasets: [{
                            label: 'Average Damage Percentage',
                            data: data.data.map(item => item.percentage),
                            backgroundColor: generateColors(data.data.length),
                            borderWidth: 1
                        }]
                    };

                    // Create new chart
                    window.damageChart = new Chart(ctx, {
                        type: 'pie',
                        data: chartData,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        font: {
                                            family: 'Poppins'
                                        }
                                    }
                                },
                                title: {
                                    display: true,
                                    text: [
                                        cropFilter ? `Damage Distribution for ${data.cropName}` : 'Crop Damage Distribution',
                                        `Location: ${data.barangay}`,
                                        `Period: ${displayDate}`
                                    ],
                                    font: {
                                        family: 'Poppins',
                                        size: 16
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.raw || 0;
                                            return `${label}: ${value.toFixed(1)}% Average Damage`;
                                        }
                                    }
                                }
                            }
                        }
                    });

                    // Add export buttons to modal
                    if (!$('#export-buttons-container').length) {
                        const buttonsContainer = $('<div>', {
                            id: 'export-buttons-container',
                            css: {
                                'margin-top': '20px',
                                'display': 'flex',
                                'gap': '10px'
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
                                'font-family': 'Poppins'
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
                                'font-family': 'Poppins'
                            }
                        });

                        buttonsContainer.append(exportPdfButton, exportExcelButton);
                        $('#graphModal .modal-content').append(buttonsContainer);
                    }

                    // Show the modal
                    $('#graphModal').css('display', 'block');

                    // Handle export button click
                    $('#export-pdf-button').off('click').on('click', function() {
                        const canvas = document.getElementById('damageChart');
                        const now = new Date().toLocaleString();
                        
                        // Initialize jsPDF for first page (landscape for chart)
                        const { jsPDF } = window.jspdf;
                        const pdf = new jsPDF({
                            orientation: 'landscape',
                            unit: 'mm',
                            format: 'a4'
                        });

                        // Set background color to transparent for chart
                        const chartContext = canvas.getContext('2d');
                        chartContext.globalCompositeOperation = 'destination-over';
                        chartContext.fillStyle = 'transparent';
                        chartContext.fillRect(0, 0, canvas.width, canvas.height);

                        // Page 1: Title and Chart (Landscape)
                        // Add header with border
                        pdf.setDrawColor(25, 135, 84); // #198754
                        pdf.setLineWidth(0.5);
                        pdf.line(20, 25, pdf.internal.pageSize.width - 20, 25);

                        // Add title
                        pdf.setFont('helvetica', 'bold');
                        pdf.setFontSize(20);
                        pdf.setTextColor(25, 135, 84); // #198754
                        pdf.text('CROP DAMAGE DISTRIBUTION REPORT', pdf.internal.pageSize.width/2, 20, { align: 'center' });

                        // Add subtitle information
                        pdf.setFont('helvetica', 'normal');
                        pdf.setFontSize(12);
                        pdf.setTextColor(0);
                        pdf.text(`Location: ${data.barangay}`, pdf.internal.pageSize.width/2, 35, { align: 'center' });
                        pdf.text(`Period: ${displayDate}`, pdf.internal.pageSize.width/2, 42, { align: 'center' });

                        // Convert chart to image with transparent background
                        const chartImage = canvas.toDataURL('image/png', 1.0);
                        
                        // Add chart to PDF (centered and larger)
                        const chartWidth = 220;
                        const chartHeight = 140;
                        const chartX = (pdf.internal.pageSize.width - chartWidth) / 2;
                        pdf.addImage(chartImage, 'PNG', chartX, 50, chartWidth, chartHeight);

                        // Add timestamp at the bottom of first page
                        pdf.setFontSize(8);
                        pdf.setTextColor(128);
                        pdf.text(`Generated on: ${now}`, pdf.internal.pageSize.width - 20, pdf.internal.pageSize.height - 10, { align: 'right' });

                        // Page 2: Summary Table (Portrait)
                        pdf.addPage('p');

                        // Add summary title
                        pdf.setFont('helvetica', 'bold');
                        pdf.setFontSize(14);
                        pdf.setTextColor(25, 135, 84);
                        pdf.text('DAMAGE SUMMARY', pdf.internal.pageSize.width/2, 20, { align: 'center' });

                        // Add decorative line under title
                        pdf.setDrawColor(25, 135, 84);
                        pdf.line(20, 25, pdf.internal.pageSize.width - 20, 25);

                        // Calculate totals
                        const totalReports = data.data.reduce((sum, item) => sum + item.count, 0);
                        const weightedDamage = data.data.reduce((sum, item) => sum + (item.percentage * item.count), 0);
                        const averageDamage = (weightedDamage / totalReports).toFixed(1);

                        // Add totals
                        pdf.setFontSize(10);
                        pdf.setTextColor(0);
                        pdf.text(`Total Number of Reports: ${totalReports}`, 20, 35);
                        pdf.text(`Overall Average Damage: ${averageDamage}%`, pdf.internal.pageSize.width - 20, 35, { align: 'right' });

                        // Table styling with adjusted spacing
                        const tableStartY = 45;
                        const pageWidth = pdf.internal.pageSize.width;
                        const colWidths = [pageWidth * 0.5, pageWidth * 0.2, pageWidth * 0.2];
                        const rowHeight = 8;
                        let currentY = tableStartY;

                        // Table headers with background
                        pdf.setFillColor(25, 135, 84);
                        pdf.setTextColor(255);
                        pdf.rect(20, currentY - 6, pageWidth - 40, rowHeight, 'F');
                        
                        const headers = ['Crop Type', 'Average Damage %', 'Number of Reports'];
                        let currentX = 20;
                        
                        // Add headers with smaller font
                        pdf.setFontSize(9);
                        headers.forEach((header, i) => {
                            pdf.text(header, currentX + 3, currentY);
                            currentX += colWidths[i];
                        });

                        // Table data
                        pdf.setTextColor(0);
                        data.data.forEach((item, index) => {
                            currentY += rowHeight;
                            
                            // Check if we need a new page
                            if (currentY > pdf.internal.pageSize.height - 30) {
                                pdf.addPage('p');
                                currentY = 40;
                                
                                // Repeat headers on new page
                                pdf.setFillColor(25, 135, 84);
                                pdf.setTextColor(255);
                                pdf.rect(20, currentY - 6, pageWidth - 40, rowHeight, 'F');
                                
                                currentX = 20;
                                headers.forEach((header, i) => {
                                    pdf.text(header, currentX + 3, currentY);
                                    currentX += colWidths[i];
                                });
                                
                                currentY += rowHeight;
                                pdf.setTextColor(0);
                            }

                            // Add zebra striping
                            if (index % 2 === 0) {
                                pdf.setFillColor(240, 240, 240);
                                pdf.rect(20, currentY - 6, pageWidth - 40, rowHeight, 'F');
                            }

                            currentX = 20;
                            // Crop Type
                            pdf.setFont('helvetica', 'normal');
                            pdf.setFontSize(9);
                            pdf.text(item.cropType, currentX + 3, currentY);
                            
                            // Average Damage
                            currentX += colWidths[0];
                            pdf.text(`${item.percentage.toFixed(1)}%`, currentX + 3, currentY);
                            
                            // Number of Reports
                            currentX += colWidths[1];
                            pdf.text(item.count.toString(), currentX + 3, currentY);
                        });

                        // Add border to entire table
                        pdf.setDrawColor(25, 135, 84);
                        pdf.rect(20, tableStartY - 6, pageWidth - 40, (Math.min(data.data.length + 1, 25)) * rowHeight);

                        // Add note at bottom of summary page
                        pdf.setFontSize(8);
                        pdf.setTextColor(128);
                        pdf.text('Note: This report shows the distribution of crop damage across different crop types in the selected location and time period.', 
                                 20, pdf.internal.pageSize.height - 20);
                        pdf.text(`Generated on: ${now}`, pageWidth - 20, pdf.internal.pageSize.height - 10, { align: 'right' });

                        // Save the PDF
                        const fileName = `Crop_Damage_Report_${data.barangay}_${filterDate || 'All_Time'}.pdf`;
                        pdf.save(fileName);
                    });

                    // Handle Excel export
                    $('#export-excel-button').off('click').on('click', function() {
                        // Calculate totals
                        const totalReports = data.data.reduce((sum, item) => sum + item.count, 0);
                        const weightedDamage = data.data.reduce((sum, item) => sum + (item.percentage * item.count), 0);
                        const averageDamage = (weightedDamage / totalReports).toFixed(1);

                        // Create workbook
                        const wb = XLSX.utils.book_new();
                        
                        // Create headers and data for the summary sheet
                        const summaryData = [
                            ['CROP DAMAGE DISTRIBUTION REPORT'],
                            [],
                            ['Location:', data.barangay],
                            ['Period:', displayDate],
                            ['Generated on:', new Date().toLocaleString()],
                            [],
                            ['SUMMARY STATISTICS'],
                            ['Total Number of Reports:', totalReports],
                            ['Overall Average Damage:', `${averageDamage}%`],
                            [],
                            ['DETAILED BREAKDOWN'],
                            ['Crop Type', 'Average Damage %', 'Number of Reports']
                        ];

                        // Add data rows
                        data.data.forEach(item => {
                            summaryData.push([
                                item.cropType,
                                `${item.percentage.toFixed(1)}%`,
                                item.count
                            ]);
                        });

                        // Create worksheet
                        const ws = XLSX.utils.aoa_to_sheet(summaryData);

                        // Set column widths
                        const cols = [
                            { wch: 40 }, // Crop Type
                            { wch: 15 }, // Average Damage
                            { wch: 15 }  // Number of Reports
                        ];
                        ws['!cols'] = cols;

                        // Add styles
                        ws['!merges'] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: 2 } }]; // Merge title cells

                        // Add worksheet to workbook
                        XLSX.utils.book_append_sheet(wb, ws, 'Damage Report');

                        // Generate Excel file
                        const fileName = `Crop_Damage_Report_${data.barangay}_${filterDate || 'All_Time'}.xlsx`;
                        XLSX.writeFile(wb, fileName);
                    });

                } catch (error) {
                    console.error('Error parsing response:', error);
                    alert('Error loading graph data');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                alert('Error loading graph data');
            },
            complete: function() {
                // Reset button state
                $('#view-graph-button').prop('disabled', false);
                $('#view-graph-button').text('View Graph');
            }
        });
    });

    // Close modal when clicking outside or on close button
    $(window).on('click', function(event) {
        if (event.target === document.getElementById('graphModal')) {
            $('#graphModal').css('display', 'none');
            if (window.damageChart instanceof Chart) {
                window.damageChart.destroy();
            }
        }
    });

    $('#graphModal .close-button').on('click', function() {
        $('#graphModal').css('display', 'none');
        if (window.damageChart instanceof Chart) {
            window.damageChart.destroy();
        }
    });

    // Helper function to generate colors for the pie chart
    function generateColors(count) {
        const colors = [];
        for (let i = 0; i < count; i++) {
            const hue = (i * 137.508) % 360; // Use golden angle approximation
            colors.push(`hsl(${hue}, 70%, 60%)`);
        }
        return colors;
    }

    // Helper function to sum array values
    function sum(arr) {
        return arr.reduce((a, b) => a + b, 0);
    }
});

function updateDamageReportImage(imageUrl) {
    const panel = $('#damageReportImagePanel');
    const image = $('#damageReportImage');
    const noImage = $('#damageReportNoImage');

    panel.removeAttr('hidden').show();

    if (imageUrl) {
        image.attr('src', imageUrl).removeAttr('hidden').show();
        noImage.attr('hidden', true).hide();
        return;
    }

    image.removeAttr('src').attr('hidden', true).hide();
    noImage.removeAttr('hidden').show();
}

$(document).on('click', '.action-button', function() {
    const reportId = $(this).closest('tr').data('id');
    console.log("Damage Report ID:", reportId);
    if (!reportId) return;

    $.ajax({
        url: 'getDamageReports.php',
        type: 'POST',
        data: { id: reportId },
        success: function(response) {
            console.log('Response from server:', response);
            try {
                const data = JSON.parse(response);

                if (data.error) {
                    alert(data.error);
                    return;
                }

                // Clear previous values
                $('#modal-body span').text(''); // Clear all span elements in modal

                // Update modal content with proper error checking
                $('#farmerID').text(data.Name || 'N/A');
                $('#reportedBy').text(data.ReportedBy || 'N/A');
                $('#reporterType').text(data.ReporterType || 'N/A');
                $('#dmgLocation').text(data.Location || 'N/A');
                $('#dmgCropCommodity').text(data.CropCommodity || 'N/A');
                $('#dmgCropType').text(data.CropType || 'N/A');
                $('#dmgCropVariety').text(data.CropVariety || 'N/A');
                $('#dateOccurrence').text(data.DateOccurrence || 'N/A');
                $('#damageCause').text(data.DamageCause || 'N/A');
                $('#percentDamage').text(data.PercentDamage ? `${data.PercentDamage}%` : 'N/A');
                $('#estimatedLoss').text(data.EstimatedLoss || 'N/A');
                $('#dmgMarketPrice').text(data.MarketPrice || 'N/A');
                $('#dmgFarmGatePrice').text(data.FarmGatePrice || 'N/A');
                $('#damageLatitude').text(data.Latitude || 'N/A');
                $('#damageLongitude').text(data.Longitude || 'N/A');
                $('#dmgReportDate').text(data.ReportDate || 'N/A');
                $('#numberOfTrees').text(data.NumberOfTrees || 'N/A');
                updateDamageReportImage(data.ImageUrl || '');
                if (typeof window.updateDamageMapCard === 'function') {
                    window.updateDamageMapCard(data);
                }

                // Make sure modal is visible
                $('#damageReportModal').css('display', 'block');
                setTimeout(function () {
                    if (window.damageMapState && window.damageMapState.map) {
                        window.damageMapState.map.invalidateSize();
                    }
                }, 150);
            } catch (error) {
                console.error('Error parsing response:', error);
                alert('Failed to load damage report details.');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX request failed:', status, error);
            alert('An error occurred while fetching the damage report details.');
        }
    });
});

// Update modal close handlers
$('.close-button').on('click', function() {
    $('#damageReportModal').css('display', 'none');
});

$(window).on('click', function(event) {
    const modal = $('#damageReportModal');
    if (event.target === modal[0]) {
        modal.css('display', 'none');
    }
});

$(document).on('click', '#damage-open-map-button', function () {
    const sourceReport = window.damageMapState ? window.damageMapState.currentReport : null;
    if (!sourceReport) {
        return;
    }

    const latitude = parseFloat(sourceReport.Latitude);
    const longitude = parseFloat(sourceReport.Longitude);
    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
        return;
    }

    const params = new URLSearchParams({
        lat: latitude.toFixed(7),
        lng: longitude.toFixed(7),
        crop: sourceReport.CropType || '',
        commodity: sourceReport.CropCommodity || '',
        location: sourceReport.Location || ''
    });

    window.open(resolveAppPath('/FARMERS%20UI/farmerLanding/map.html') + '?' + params.toString(), '_blank');
});

$(document).on('click', '#damage-show-directions-button', function () {
    const sourceReport = window.damageMapState ? window.damageMapState.currentReport : null;
    if (!sourceReport) {
        return;
    }

    const latitude = parseFloat(sourceReport.Latitude);
    const longitude = parseFloat(sourceReport.Longitude);
    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
        return;
    }

    const destination = `${latitude.toFixed(7)},${longitude.toFixed(7)}`;
    const directionsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(destination) + '&travelmode=driving';
    window.open(directionsUrl, '_blank');
});
const resolveAppPath = typeof window.resolveAppPath === 'function'
  ? window.resolveAppPath
  : (path) => {
      const pathname = window.location.pathname || '';
      const basePath = (pathname === '/scaims' || pathname.indexOf('/scaims/') === 0) ? '/scaims' : '';
      return path.charAt(0) === '/' ? basePath + path : path;
    };
