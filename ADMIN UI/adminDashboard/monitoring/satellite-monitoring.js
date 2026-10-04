(function () {
    'use strict';

    const selector = document.getElementById('parcel-selector');
    const loadStatus = document.getElementById('parcel-load-status');
    const parcelPanel = document.querySelector('.parcel-panel');
    const recordBadge = document.getElementById('parcel-record-badge');
    const mapEmptyState = document.getElementById('map-empty-state');
    const mapLocationLabel = document.getElementById('map-location-label');
    const endpoint = document.body.dataset.parcelEndpoint || 'getParcel.php';
    let map = null;
    let mapLayer = null;

    function display(value, fallback) {
        if (value === null || typeof value === 'undefined' || String(value).trim() === '') {
            return fallback || '—';
        }
        return String(value);
    }

    function setField(name, value) {
        const field = document.querySelector(`[data-parcel-field="${name}"]`);
        if (field) {
            field.textContent = display(value);
        }
    }

    function resetParcel() {
        ['parcel_id', 'farmer_name', 'crop', 'location', 'municipality', 'area', 'status', 'created_at']
            .forEach(name => setField(name, null));
        recordBadge.textContent = 'No parcel selected';
        mapLocationLabel.textContent = 'Select a parcel to inspect its stored location.';
        mapEmptyState.hidden = false;
        mapEmptyState.querySelector('strong').textContent = 'No mapped parcel selected';
        mapEmptyState.querySelector('span').textContent = 'Select a parcel to check its available map data.';
        clearMapLayer();
    }

    function formatDate(value) {
        if (!value) {
            return '—';
        }
        const parsed = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(parsed.getTime())) {
            return value;
        }
        return new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' }).format(parsed);
    }

    function initializeMap() {
        if (!window.L) {
            mapEmptyState.querySelector('strong').textContent = 'Map unavailable';
            mapEmptyState.querySelector('span').textContent = 'The map library could not be loaded.';
            return;
        }
        map = L.map('parcel-map', { scrollWheelZoom: false }).setView([13.9411, 121.1622], 11);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);
    }

    function clearMapLayer() {
        if (map && mapLayer) {
            map.removeLayer(mapLayer);
            mapLayer = null;
        }
    }

    function updateMap(parcel) {
        clearMapLayer();
        mapLocationLabel.textContent = display(parcel.location || parcel.farmer_barangay, 'No stored location');

        if (!map) {
            return;
        }

        if (parcel.boundary_geojson) {
            try {
                mapLayer = L.geoJSON(parcel.boundary_geojson).addTo(map);
                map.fitBounds(mapLayer.getBounds(), { padding: [20, 20] });
                mapEmptyState.hidden = true;
                return;
            } catch (error) {
                // Invalid stored geometry is treated as unavailable, not drawn.
            }
        }

        const latitude = Number(parcel.latitude);
        const longitude = Number(parcel.longitude);
        if (Number.isFinite(latitude) && Number.isFinite(longitude)
            && parcel.latitude !== null && parcel.longitude !== null) {
            mapLayer = L.marker([latitude, longitude]).addTo(map);
            map.setView([latitude, longitude], 16);
            mapEmptyState.hidden = true;
            return;
        }

        map.setView([13.9411, 121.1622], 11);
        mapEmptyState.hidden = false;
        mapEmptyState.querySelector('strong').textContent = 'No parcel coordinates available';
        mapEmptyState.querySelector('span').textContent = 'The existing parcel record contains a text location only; no marker or boundary was fabricated.';
    }

    function renderParcel(parcel) {
        const cropNames = Array.isArray(parcel.crops)
            ? [...new Set(parcel.crops.map(crop => crop.type).filter(Boolean))]
            : [];
        setField('parcel_id', `#${parcel.parcel_id}`);
        setField('farmer_name', parcel.farmer_name);
        setField('crop', cropNames.length ? cropNames.join(', ') : null);
        setField('location', parcel.location || parcel.farmer_barangay);
        setField('municipality', parcel.municipality);
        setField('area', parcel.area_hectares === null ? null : `${parcel.area_hectares.toLocaleString()} ha`);
        setField('status', parcel.status);
        setField('created_at', formatDate(parcel.created_at));
        recordBadge.textContent = `Parcel #${parcel.parcel_id}`;
        updateMap(parcel);
    }

    async function loadParcel(parcelId) {
        parcelPanel.classList.add('is-loading');
        selector.disabled = true;
        loadStatus.textContent = 'Loading parcel information…';
        try {
            const response = await fetch(`${endpoint}?id=${encodeURIComponent(parcelId)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const result = await response.json();
            if (!response.ok || !result.ok || !result.parcel) {
                throw new Error('Parcel information could not be loaded.');
            }
            renderParcel(result.parcel);
            loadStatus.textContent = 'Existing SCAIMS parcel information loaded.';
        } catch (error) {
            resetParcel();
            loadStatus.textContent = error.message || 'Parcel information could not be loaded.';
        } finally {
            parcelPanel.classList.remove('is-loading');
            selector.disabled = false;
        }
    }

    if (selector) {
        selector.addEventListener('change', function () {
            if (!selector.value) {
                resetParcel();
                loadStatus.textContent = 'Select a parcel to load its registered information.';
                return;
            }
            loadParcel(selector.value);
        });
    }

    initializeMap();
}());
