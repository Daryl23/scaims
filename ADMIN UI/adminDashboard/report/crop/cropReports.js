$(document).ready(function() {
    const reportView = window.SCAIMSAdminReportView;

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

    const cropMapColors = {
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

    const reportMap = reportView.createReportMap({
        reportType: 'harvest',
        mapId: 'crop-report-map',
        mapSelector: '#crop-report-map',
        messageSelector: '#crop-report-map-message',
        streetButtonSelector: '#crop-map-street',
        satelliteButtonSelector: '#crop-map-satellite',
        locationSelector: '#mapCardLocation',
        coordinatesSelector: '#mapCardCoordinates',
        openMapButtonSelector: '#open-report-map-button',
        directionsButtonSelector: '#show-directions-button',
        defaultMarkerLabel: 'Crop Report',
        noCoordinatesMessage: 'No latitude and longitude available for this crop report.',
        colors: cropMapColors,
        getLocation: report => report.Barangay || report.Location,
        getCropType: report => report.Crop,
        buildPublicMapParams: (report, coordinates) => ({
            lat: coordinates.latitude.toFixed(7),
            lng: coordinates.longitude.toFixed(7),
            crop: report.Crop || '',
            commodity: report.CropCommodity || '',
            location: report.Barangay || ''
        }),
        buildDirectionsUrl: (report, coordinates) => {
            const destination = `${coordinates.latitude.toFixed(7)},${coordinates.longitude.toFixed(7)}`;
            return 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(destination) + '&travelmode=driving';
        },
        renderPopup: (report, coordinatesLabel, color) => `
            <div style="min-width:240px;font-family:Inter,sans-serif;">
                <div style="background:${color};padding:12px 16px 10px;">
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
        `
    });
    const updateReportMapCard = reportMap.update;

    const pagination = reportView.createPagination({
        pageSize: 10,
        scrollSelector: '#previous-reports-section',
        onPageChange: page => loadCropReports(
            $('#crop-filter').val(),
            $('#barangay-filter').val(),
            $('#reporter-filter').val(),
            page
        )
    });

    function loadCropReports(filter, barangay, reporterType, page) {
        $('#new-reports-body, #previous-reports-body').html('<tr><td colspan="11">Loading...</td></tr>');
        
        // Get the month and year values separately
        const monthYearValue = $('#month-filter').val();
        const [year, month] = monthYearValue ? monthYearValue.split('-') : ['', ''];
        
        const predefinedCrops = getPredefinedCrops();
        
        reportView.fetchList({
            url: 'fetchCropReports.php',
            method: 'POST',
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
            onSuccess: function(response) {
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
            
                    pagination.update(response.total);
                    
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
            onError: function(xhr, status, error) {
                console.error('AJAX request failed:', status, error);
                console.error('Response:', xhr.responseText);
                $('#new-reports-body, #previous-reports-body').html(
                    '<tr><td colspan="11">Failed to load data. Please check your connection.</td></tr>'
                );
            }
        });
    }

    function refreshTable() {
        const page = pagination.reset();
        loadCropReports($('#crop-filter').val(), $('#barangay-filter').val(), $('#reporter-filter').val(), page);
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
            refreshTable();
        });
    });

    reportView.bindSearch({
        onSearch: refreshTable
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

        reportView.downloadBlob(exportUrl, 'CropReports.xls')
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
        reportView.fetchDetail({
            url: 'getCropReports.php',
            method: 'POST',
            data: { id: reportId },
            onSuccess: function(data) {
                const report = (typeof data === 'string' ? JSON.parse(data) : data);
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
                reportView.setReportImage({
                    panelSelector: '#cropReportImagePanel',
                    imageSelector: '#cropReportImage',
                    noImageSelector: '#cropReportNoImage',
                    imageUrl: report.ImageUrl || ''
                });
                let gpsPanel = document.getElementById('cropReportImagePanelGPS');
                if (!gpsPanel) { gpsPanel = document.createElement('section'); gpsPanel.id = 'cropReportImagePanelGPS'; document.getElementById('cropReportImagePanel').after(gpsPanel); }
                gpsPanel.gpsPhoto = report.gps_photo;
                window.SCAIMSGPSPhoto.renderDetails(gpsPanel, report.gps_photo);
                ["location", "cropLatitude", "cropLongitude"].forEach(id => { const field = document.getElementById(id); if (field) field.closest('p').hidden = Boolean(report.gps_photo); });

                updateReportMapCard(report);
                // Show the modal
                reportView.openModal('#cropReportModal');
                setTimeout(function () {
                    reportMap.invalidate();
                }, 150);
            },
            onError: function() {
                alert('Error fetching report details.');
            }
        });
    }

    reportView.bindModalDismiss({
        modalSelector: '#cropReportModal',
        closeSelector: '#cropReportModal .close-button'
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

                        harvestChart = reportView.replaceChart(harvestChart, ctx, {
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

                        // Export content remains Harvest-specific; only the button shell is shared.
                        reportView.ensureExportButtons('#graphModal', { justifyContent: 'center' });

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

        reportView.bindModalDismiss({
            modalSelector: '#graphModal',
            closeSelector: '#graphModal .close-button',
            onClose: function () {
                harvestChart = reportView.destroyChart(harvestChart);
            }
        });
    });
});

document.addEventListener('dashboard:language-changed', function () { const panel = document.getElementById('cropReportImagePanelGPS'); if (panel) window.SCAIMSGPSPhoto.renderDetails(panel, panel.gpsPhoto); });
