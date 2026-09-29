(function (global, $) {
    'use strict';

    const namespace = global.SCAIMSAdminReportView = global.SCAIMSAdminReportView || {};

    namespace.createReportMap = function (config) {
        const state = {
            map: null,
            marker: null,
            activeLayer: 'street',
            layers: {},
            currentReport: null
        };

        function colorFor(cropType) {
            const key = cropType ? String(cropType).toLowerCase().trim() : 'default';
            return config.colors[key] || config.colors.default;
        }

        function createIcon(cropType) {
            const color = colorFor(cropType);
            if (global.SCAIMSCropIcons && typeof global.SCAIMSCropIcons.createLeafletIcon === 'function') {
                const cropIcon = global.SCAIMSCropIcons.createLeafletIcon(cropType, {
                    color: color,
                    label: cropType || config.defaultMarkerLabel
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

        function ensureMap() {
            if (state.map || typeof L === 'undefined') {
                return;
            }

            state.map = L.map(config.mapId, {
                center: [13.9411, 121.1631],
                zoom: 13,
                zoomControl: false
            });
            L.control.zoom({ position: 'bottomright' }).addTo(state.map);
            state.layers.street = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap',
                maxZoom: 19
            });
            state.layers.satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: '&copy; Esri',
                maxZoom: 19
            });
            state.layers.street.addTo(state.map);
        }

        function setLayer(layerName) {
            ensureMap();
            if (!state.map || state.activeLayer === layerName) {
                return;
            }
            state.map.removeLayer(state.layers[state.activeLayer]);
            state.layers[layerName].addTo(state.map);
            state.activeLayer = layerName;
            $(config.streetButtonSelector + ', ' + config.satelliteButtonSelector).removeClass('active');
            $(layerName === 'street' ? config.streetButtonSelector : config.satelliteButtonSelector).addClass('active');
        }

        function coordinatesFor(report) {
            const latitude = parseFloat(report.Latitude);
            const longitude = parseFloat(report.Longitude);
            return {
                latitude: latitude,
                longitude: longitude,
                valid: Number.isFinite(latitude) && Number.isFinite(longitude)
            };
        }

        function update(report) {
            state.currentReport = report;
            const coordinates = coordinatesFor(report);
            const coordinatesLabel = coordinates.valid
                ? `${coordinates.latitude.toFixed(7)}, ${coordinates.longitude.toFixed(7)}`
                : 'No saved coordinates';

            $(config.locationSelector).text(config.getLocation(report) || 'N/A');
            $(config.coordinatesSelector).text(coordinatesLabel);
            $(config.openMapButtonSelector + ', ' + config.directionsButtonSelector).prop('disabled', !coordinates.valid);

            if (!coordinates.valid) {
                $(config.mapSelector).hide();
                $(config.messageSelector).text(config.noCoordinatesMessage).show();
                if (state.marker && state.map) {
                    state.map.removeLayer(state.marker);
                    state.marker = null;
                }
                return;
            }

            $(config.messageSelector).hide();
            $(config.mapSelector).show();
            ensureMap();
            setLayer(state.activeLayer);
            if (state.marker) {
                state.map.removeLayer(state.marker);
            }

            const cropType = config.getCropType(report);
            state.marker = L.marker([coordinates.latitude, coordinates.longitude], {
                icon: createIcon(cropType)
            }).addTo(state.map);
            state.marker.bindPopup(config.renderPopup(report, coordinatesLabel, colorFor(cropType))).openPopup();
            state.map.setView([coordinates.latitude, coordinates.longitude], 17);
            setTimeout(function () { state.map.invalidateSize(); }, 120);
        }

        function openPublicMap() {
            const report = state.currentReport;
            const coordinates = report ? coordinatesFor(report) : { valid: false };
            if (!coordinates.valid) {
                return;
            }
            const path = typeof global.resolveAppPath === 'function'
                ? global.resolveAppPath('/FARMERS%20UI/farmerLanding/map.html')
                : '/FARMERS%20UI/farmerLanding/map.html';
            const params = new URLSearchParams(config.buildPublicMapParams
                ? config.buildPublicMapParams(report, coordinates)
                : {
                    lat: coordinates.latitude,
                    lng: coordinates.longitude,
                    crop: config.getCropType(report) || '',
                    reportType: config.reportType
                });
            global.open(path + '?' + params.toString(), '_blank');
        }

        function showDirections() {
            const report = state.currentReport;
            const coordinates = report ? coordinatesFor(report) : { valid: false };
            if (coordinates.valid) {
                const url = config.buildDirectionsUrl
                    ? config.buildDirectionsUrl(report, coordinates)
                    : `https://www.google.com/maps/dir/?api=1&destination=${coordinates.latitude},${coordinates.longitude}`;
                global.open(url, '_blank');
            }
        }

        $(config.streetButtonSelector).on('click', function () { setLayer('street'); });
        $(config.satelliteButtonSelector).on('click', function () { setLayer('satellite'); });
        $(config.openMapButtonSelector).on('click', openPublicMap);
        $(config.directionsButtonSelector).on('click', showDirections);

        return {
            state: state,
            update: update,
            ensure: ensureMap,
            setLayer: setLayer,
            invalidate: function () {
                if (state.map) {
                    state.map.invalidateSize();
                }
            }
        };
    };
})(window, window.jQuery);
