const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..', '..');
const read = relativePath => fs.readFileSync(path.join(root, relativePath), 'utf8');

function createJQueryHarness() {
    const records = new Map();
    function record(selector) {
        if (!records.has(selector)) {
            records.set(selector, { selector, handlers: {}, props: {}, textValue: '', visible: true, attrs: {} });
        }
        return records.get(selector);
    }
    function wrap(item) {
        const value = typeof item === 'string' ? record(item) : item;
        return {
            length: value.length === undefined ? 1 : value.length,
            on(name, handler) { value.handlers[name] = handler; return this; },
            prop(name, next) { if (arguments.length === 1) return value.props[name]; value.props[name] = next; return this; },
            text(next) { if (arguments.length === 0) return value.textValue; value.textValue = next; return this; },
            html(next) { value.htmlValue = next; return this; },
            is(selector) { return value.selector === selector; },
            removeClass() { return this; },
            addClass() { return this; },
            hide() { value.visible = false; return this; },
            show() { value.visible = true; return this; },
            css(name, next) { value.css = value.css || {}; value.css[name] = next; return this; },
            attr(name, next) { if (arguments.length === 1) return value.attrs[name]; value.attrs[name] = next; return this; },
            removeAttr(name) { delete value.attrs[name]; return this; },
            find() { return wrap({ length: 0, handlers: {}, props: {}, attrs: {} }); },
            append() { return this; },
            submit() { value.submitted = true; return this; },
            remove() { value.removed = true; return this; }
        };
    }
    const $ = item => wrap(item);
    $.ajax = () => {};
    return { $, records, record };
}

function createContext() {
    const jquery = createJQueryHarness();
    const opened = [];
    const document = {
        querySelector: () => null,
        createElement: () => ({ click() {}, remove() {} }),
        body: { appendChild() {} }
    };
    const window = { jQuery: jquery.$, URL, open: (...args) => opened.push(args), document };
    window.window = window;
    const context = {
        window,
        document,
        URL,
        Chart: function Chart() {},
        fetch: () => Promise.reject(new Error('not used')),
        setTimeout: callback => callback(),
        clearTimeout() {}
    };
    vm.runInNewContext(read('shared/admin-report-view/report-view-core.js'), context);
    return { context, window, opened, ...jquery };
}

function testPagination() {
    const harness = createContext();
    const changes = [];
    const api = harness.window.SCAIMSAdminReportView.createPagination({
        pageSize: 10,
        onPageChange: page => changes.push(page)
    });
    api.update(21);
    assert.strictEqual(harness.record('#prev-page').props.disabled, true);
    assert.strictEqual(harness.record('#next-page').props.disabled, false);
    assert.strictEqual(harness.record('#current-page').textValue, 'Page 1 of 3');

    const combined = harness.record('#next-page, #prev-page');
    combined.handlers.click.call({ selector: '#next-page', handlers: {}, props: {} });
    assert.deepStrictEqual(changes, [2]);
    api.update(21);
    assert.strictEqual(harness.record('#current-page').textValue, 'Page 2 of 3');
    assert.strictEqual(api.reset(), 1);
    api.update(0);
    assert.strictEqual(harness.record('#current-page').textValue, 'Page 0 of 0');
}

function testMapLifecycle() {
    const harness = createContext();
    const removed = [];
    const map = {
        removeLayer(layer) { removed.push(layer); },
        setView(value, zoom) { this.view = [value, zoom]; },
        invalidateSize() { this.invalidated = true; }
    };
    const marker = {
        addTo() { return this; },
        bindPopup(value) { this.popup = value; return this; },
        openPopup() { this.opened = true; return this; }
    };
    harness.context.L = {
        map() { return map; },
        control: { zoom: () => ({ addTo() {} }) },
        tileLayer: () => ({ addTo() {} }),
        marker: () => marker,
        divIcon: options => options
    };
    vm.runInNewContext(read('shared/admin-report-view/report-map.js'), harness.context);
    const reportMap = harness.window.SCAIMSAdminReportView.createReportMap({
        reportType: 'harvest', mapId: 'map', mapSelector: '#map', messageSelector: '#message',
        streetButtonSelector: '#street', satelliteButtonSelector: '#satellite',
        locationSelector: '#location', coordinatesSelector: '#coordinates',
        openMapButtonSelector: '#open', directionsButtonSelector: '#directions',
        noCoordinatesMessage: 'No coordinates', defaultMarkerLabel: 'Report',
        colors: { rice: '#0f0', default: '#999' },
        getLocation: report => report.Location,
        getCropType: report => report.Crop,
        renderPopup: (report, coordinates) => `${report.Crop}:${coordinates}`
    });

    reportMap.update({ Location: 'Lipa', Crop: 'Rice', Latitude: '', Longitude: '' });
    assert.strictEqual(harness.record('#message').textValue, 'No coordinates');
    assert.strictEqual(harness.record('#map').visible, false);
    assert.strictEqual(harness.record('#open, #directions').props.disabled, true);

    reportMap.update({ Location: 'Lipa', Crop: 'Rice', Latitude: '13.9411', Longitude: '121.1631' });
    assert.strictEqual(harness.record('#map').visible, true);
    assert.strictEqual(harness.record('#open, #directions').props.disabled, false);
    assert.strictEqual(JSON.stringify(map.view), JSON.stringify([[13.9411, 121.1631], 17]));
    assert.strictEqual(marker.popup, 'Rice:13.9411000, 121.1631000');
    assert.strictEqual(marker.opened, true);
}

function testChartLifecycle() {
    const harness = createContext();
    let destroyed = 0;
    harness.context.Chart = function Chart(context, config) { this.context = context; this.config = config; };
    const previous = { destroy() { destroyed += 1; } };
    const next = harness.window.SCAIMSAdminReportView.replaceChart(previous, 'context', { type: 'bar' });
    assert.strictEqual(destroyed, 1);
    assert.strictEqual(next.config.type, 'bar');
    assert.strictEqual(harness.window.SCAIMSAdminReportView.destroyChart(next), null);
}

function testPageContracts() {
    const harvestPage = read('ADMIN UI/adminDashboard/report/crop/cropReports.php');
    const damagePage = read('ADMIN UI/adminDashboard/report/damage/damageReports.php');
    const harvestScript = read('ADMIN UI/adminDashboard/report/crop/cropReports.js');
    const damageScript = read('ADMIN UI/adminDashboard/report/damage/cropDamage.js');

    for (const page of [harvestPage, damagePage]) {
        assert(page.includes('admin-report-view/report-view.css'));
        assert(page.includes('admin-report-view/report-view-core.js'));
        assert(page.includes('admin-report-view/report-map.js'));
        const typeScriptTag = page === harvestPage ? '<script src="cropReports.js' : '<script src="cropDamage.js';
        assert(page.indexOf('admin-report-view/report-view-core.js') < page.indexOf(typeScriptTag));
        assert(page.includes('class="admin-report-map"'));
        assert(page.includes('id="export-button"'));
    }
    assert(harvestScript.includes("url: 'fetchCropReports.php'"));
    assert(harvestScript.includes("url: 'getCropReports.php'"));
    assert(harvestScript.includes("'exportCropReports.php?'"));
    assert(damageScript.includes("url: 'fetchDamageReports.php'"));
    assert(damageScript.includes("url: 'getDamageReports.php'"));
    assert(damageScript.includes("url: 'exportDamageReports.php'"));
    assert(harvestScript.includes("type: 'bar'"));
    assert(damageScript.includes("type: 'pie'"));
}

testPagination();
testMapLifecycle();
testChartLifecycle();
testPageContracts();
console.log('Admin report-view characterization tests passed.');
