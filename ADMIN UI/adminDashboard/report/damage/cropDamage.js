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

    const damageMapColors = {
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

    const damageMap = reportView.createReportMap({
        reportType: 'damage',
        mapId: 'damage-report-map',
        mapSelector: '#damage-report-map',
        messageSelector: '#damage-report-map-message',
        streetButtonSelector: '#damage-map-street',
        satelliteButtonSelector: '#damage-map-satellite',
        locationSelector: '#damageMapCardLocation',
        coordinatesSelector: '#damageMapCardCoordinates',
        openMapButtonSelector: '#damage-open-map-button',
        directionsButtonSelector: '#damage-show-directions-button',
        defaultMarkerLabel: 'Damage Report',
        noCoordinatesMessage: 'No latitude and longitude available for this damage report.',
        colors: damageMapColors,
        getLocation: report => report.Location || report.Barangay,
        getCropType: report => report.CropType,
        buildPublicMapParams: (report, coordinates) => ({
            lat: coordinates.latitude.toFixed(7),
            lng: coordinates.longitude.toFixed(7),
            crop: report.CropType || '',
            commodity: report.CropCommodity || '',
            location: report.Location || ''
        }),
        buildDirectionsUrl: (report, coordinates) => {
            const destination = `${coordinates.latitude.toFixed(7)},${coordinates.longitude.toFixed(7)}`;
            return 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(destination) + '&travelmode=driving';
        },
        renderPopup: (report, coordinatesLabel, color) => `
            <div style="min-width:240px;font-family:Inter,sans-serif;">
                <div style="background:${color};padding:12px 16px 10px;">
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
        `
    });
    const damageMapState = damageMap.state;
    const updateDamageMapCard = damageMap.update;

    window.damageMapState = damageMapState;
    window.updateDamageMapCard = updateDamageMapCard;
    window.damageReportMap = damageMap;

    const pagination = reportView.createPagination({
        pageSize: 10,
        scrollSelector: '#previous-reports-section',
        onPageChange: page => loadDamageReports(
            $('#crop-filter').val(),
            $('#barangay-filter').val(),
            $('#reporter-filter').val(),
            page
        )
    });

    refreshCropFilterOptions();
    document.addEventListener('scaims:crop-catalog-ready', function () {
        refreshCropFilterOptions();
        handleFilterChange();
    });

    function handleFilterChange() {
        const page = pagination.reset();
        loadDamageReports(
            $('#crop-filter').val(),
            $('#barangay-filter').val(),
            $('#reporter-filter').val(),
            page
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

        reportView.fetchList({
            url: 'fetchDamageReports.php',
            method: 'POST',
            data: { 
                filter: formattedFilter, 
                barangay: barangay, 
                reporterType: reporterType,
                search: searchTerm,
                page: page,
                filterDate: filterDate,
                predefinedCrops: filter === 'Other' ? predefinedCrops : null
            },
            onSuccess: function(response) {
                try {
                    const data = (typeof response === 'string' ? JSON.parse(response) : response);
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
                    pagination.update(data.total);
                    
                } catch (error) {
                    console.error('Error parsing response:', error);
                        $('#new-reports-body, #previous-reports-body').html(
                        '<tr><td colspan="10">Error processing data</td></tr>'
                    );
                }
            },
            onError: function(xhr, status, error) {
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
        pagination.getPage()
    );

    reportView.bindSearch({
        onSearch: handleFilterChange
    });

    $('#export-button').on('click', function() {
        const crop = $('#crop-filter').val();
        const barangay = $('#barangay-filter').val();
        const reporterType = $('#reporter-filter').val();
        const search = $('#search-bar').val();
        const filterDate = $('#date-filter').val();

        reportView.submitExportForm({
            url: 'exportDamageReports.php',
            fields: { crop, barangay, reporterType, search, filterDate }
        });
    });

    $('#date-filter').on('change', function() {
        handleFilterChange();
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
                    const data = (typeof response === 'string' ? JSON.parse(response) : response);
                    if (!data.success) {
                        console.error('Server error:', data.error);
                        alert('Error loading graph data');
                        return;
                    }

                    // Get the canvas element
                    const ctx = document.getElementById('damageChart').getContext('2d');

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
                    window.damageChart = reportView.replaceChart(window.damageChart, ctx, {
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

                    // Export content remains Damage-specific; only the button shell is shared.
                    reportView.ensureExportButtons('#graphModal');

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

    reportView.bindModalDismiss({
        modalSelector: '#graphModal',
        closeSelector: '#graphModal .close-button',
        onClose: function () {
            window.damageChart = reportView.destroyChart(window.damageChart);
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

$(document).on('click', '.action-button', function() {
    const reportId = $(this).closest('tr').data('id');
    if (!reportId) return;

    window.SCAIMSAdminReportView.fetchDetail({
        url: 'getDamageReports.php',
        method: 'POST',
        data: { id: reportId },
        onSuccess: function(response) {
            try {
                const data = (typeof response === 'string' ? JSON.parse(response) : response);

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
                window.SCAIMSAdminReportView.setReportImage({
                    panelSelector: '#damageReportImagePanel',
                    imageSelector: '#damageReportImage',
                    noImageSelector: '#damageReportNoImage',
                    imageUrl: data.ImageUrl || ''
                });
                let gpsPanel = document.getElementById('damageReportImagePanelGPS');
                if (!gpsPanel) { gpsPanel = document.createElement('section'); gpsPanel.id = 'damageReportImagePanelGPS'; document.getElementById('damageReportImagePanel').after(gpsPanel); }
                gpsPanel.gpsPhoto = data.gps_photo;
                window.SCAIMSGPSPhoto.renderDetails(gpsPanel, data.gps_photo);
                ["dmgLocation", "damageLatitude", "damageLongitude", "dateOccurrence"].forEach(id => { const field = document.getElementById(id); if (field) field.closest('p').hidden = Boolean(data.gps_photo); });

                if (typeof window.updateDamageMapCard === 'function') {
                    window.updateDamageMapCard(data);
                }

                // Make sure modal is visible
                window.SCAIMSAdminReportView.openModal('#damageReportModal');
                setTimeout(function () {
                    if (window.damageReportMap) window.damageReportMap.invalidate();
                }, 150);
            } catch (error) {
                console.error('Error parsing response:', error);
                alert('Failed to load damage report details.');
            }
        },
        onError: function(xhr, status, error) {
            console.error('AJAX request failed:', status, error);
            alert('An error occurred while fetching the damage report details.');
        }
    });
});

window.SCAIMSAdminReportView.bindModalDismiss({
    modalSelector: '#damageReportModal',
    closeSelector: '#damageReportModal .close-button'
});

document.addEventListener('dashboard:language-changed', function () { const panel = document.getElementById('damageReportImagePanelGPS'); if (panel) window.SCAIMSGPSPhoto.renderDetails(panel, panel.gpsPhoto); });
