const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..', '..');

function read(relativePath) {
    return fs.readFileSync(path.join(root, relativePath), 'utf8');
}

function appPathContext(scriptSrc, pathname) {
    const window = {
        location: { href: `http://localhost${pathname}`, pathname },
    };
    window.window = window;
    return {
        window,
        URL,
        document: {
            currentScript: { src: scriptSrc },
            querySelector: () => null,
        },
    };
}

function testAppPath() {
    const nested = appPathContext(
        'http://localhost/scaims/shared/app-path.js',
        '/scaims/FARMERS%20UI/farmersDashboard/home/home.php'
    );
    vm.runInNewContext(read('shared/app-path.js'), nested);
    assert.strictEqual(nested.window.SCAIMSAppPath.getBasePath(), '/scaims');
    assert.strictEqual(nested.window.SCAIMSAppPath.resolve('/index.html'), '/scaims/index.html');
    assert.strictEqual(nested.window.SCAIMSAppPath.resolve('/scaims/index.html'), '/scaims/index.html');
    assert.strictEqual(nested.window.SCAIMSAppPath.resolve('https://example.test/x'), 'https://example.test/x');
    assert.strictEqual(nested.window.resolveAppPath, nested.window.SCAIMSAppPath.resolve);
    assert.strictEqual(nested.window.toAppRelativePath('/index.html'), '../../../index.html');

    const hosted = appPathContext(
        'https://example.test/deploy/scaims-v2/shared/app-path.js',
        '/deploy/scaims-v2/ADMIN%20UI/adminDashboard/home/home.php'
    );
    vm.runInNewContext(read('shared/app-path.js'), hosted);
    assert.strictEqual(hosted.window.SCAIMSAppPath.getBasePath(), '/deploy/scaims-v2');
    assert.strictEqual(hosted.window.SCAIMSAppPath.resolve('/shared/data.php'), '/deploy/scaims-v2/shared/data.php');

    const rootDeployment = appPathContext('https://example.test/shared/app-path.js', '/index.html');
    vm.runInNewContext(read('shared/app-path.js'), rootDeployment);
    assert.strictEqual(rootDeployment.window.SCAIMSAppPath.getBasePath(), '');
    assert.strictEqual(rootDeployment.window.SCAIMSAppPath.resolve('/index.html'), '/index.html');
}

function testI18nEngine() {
    const saved = new Map();
    const events = [];
    const listeners = {};
    const text = { dataset: { i18n: 'title' }, textContent: '' };
    const html = { dataset: { i18nHtml: 'body' }, innerHTML: '' };
    const placeholder = { dataset: { i18nPlaceholder: 'hint' }, placeholder: '' };
    const aria = {
        dataset: { i18nAriaLabel: 'label' },
        value: '',
        setAttribute(name, value) { this[name] = value; },
    };
    const button = {
        dataset: { language: 'tl' },
        classList: { active: false, toggle(name, active) { if (name === 'active') this.active = active; } },
        addEventListener(name, callback) { listeners[name] = callback; },
    };
    const elements = {
        '[data-i18n]': [text],
        '[data-i18n-html]': [html],
        '[data-i18n-placeholder]': [placeholder],
        '[data-i18n-aria-label]': [aria],
        '.language-toggle-button': [button],
    };
    const window = {};
    window.window = window;
    const context = {
        window,
        localStorage: {
            getItem: key => saved.has(key) ? saved.get(key) : null,
            setItem: (key, value) => saved.set(key, value),
        },
        CustomEvent: function (type, options) { this.type = type; this.detail = options.detail; },
        document: {
            documentElement: { lang: 'en' },
            querySelectorAll: selector => elements[selector] || [],
            dispatchEvent: event => events.push(event),
        },
    };

    vm.runInNewContext(read('shared/i18n.js'), context);
    const i18n = window.SCAIMSI18n.create({
        storageKey: 'testLanguage',
        fallbackLanguage: 'en',
        translations: {
            en: { title: 'Title', body: '<b>Body</b>', hint: 'Hint', label: 'Label' },
            tl: { title: 'Pamagat', body: '<b>Katawan</b>', hint: 'Pahiwatig', label: 'Tatak' },
        },
    });
    i18n.bindLanguageButtons();
    i18n.applyTranslations(i18n.getLanguage());
    assert.deepStrictEqual([text.textContent, html.innerHTML, placeholder.placeholder, aria['aria-label']],
        ['Title', '<b>Body</b>', 'Hint', 'Label']);
    assert.strictEqual(context.document.documentElement.lang, 'en');
    listeners.click();
    assert.strictEqual(saved.get('testLanguage'), 'tl');
    assert.deepStrictEqual([text.textContent, html.innerHTML, placeholder.placeholder, aria['aria-label']],
        ['Pamagat', '<b>Katawan</b>', 'Pahiwatig', 'Tatak']);
    assert.strictEqual(context.document.documentElement.lang, 'tl');
    assert.strictEqual(button.classList.active, true);
    assert.strictEqual(events.at(-1).detail.language, 'tl');
    assert.strictEqual(i18n.t('title'), 'Pamagat');
}

function testPathConsumers() {
    let requestedUrl = null;
    const window = {
        SCAIMSAppPath: { resolve: value => `/nested${value}` },
    };
    window.window = window;
    const context = {
        window,
        console: { warn() {} },
        fetch(url) {
            requestedUrl = url;
            return Promise.resolve({
                ok: true,
                json: () => Promise.resolve({ success: true, groups: {} }),
            });
        },
        CustomEvent: function (type, options) { this.type = type; this.detail = options.detail; },
        document: {
            dispatchEvent() {},
            createElement: () => ({}),
            getElementById: () => null,
            head: { appendChild() {} },
            documentElement: { appendChild() {} },
        },
    };
    vm.runInNewContext(read('shared/crop-catalog.js'), context);
    assert.strictEqual(requestedUrl, '/nested/shared/crop_catalog_api.php');
    vm.runInNewContext(read('shared/leaflet-crop-icons.js'), context);
    assert.strictEqual(window.SCAIMSCropIcons.getIconUrl('Rice'), '/nested/assets/icons/Rice.png');
}

function testPortalLanguage(relativePath, globalName, storageKey) {
    const saved = new Map();
    const listeners = {};
    const events = [];
    const text = { dataset: { i18n: 'language_label' }, textContent: '' };
    const button = {
        dataset: { language: 'tl' },
        classList: { toggle() {} },
        addEventListener(name, callback) { listeners[name] = callback; },
    };
    let ready;
    const window = {};
    window.window = window;
    const context = {
        window,
        localStorage: {
            getItem: key => saved.has(key) ? saved.get(key) : null,
            setItem: (key, value) => saved.set(key, value),
        },
        CustomEvent: function (type, options) { this.type = type; this.detail = options.detail; },
        document: {
            documentElement: { lang: 'en' },
            addEventListener(name, callback) { if (name === 'DOMContentLoaded') ready = callback; },
            querySelectorAll(selector) {
                if (selector === '[data-i18n]') return [text];
                if (selector === '.language-toggle-button') return [button];
                return [];
            },
            dispatchEvent: event => events.push(event),
        },
    };
    vm.runInNewContext(read('shared/i18n.js'), context);
    vm.runInNewContext(read(relativePath), context);
    ready();
    assert.ok(window[globalName]);
    assert.strictEqual(text.textContent, 'Language / Wika');
    listeners.click();
    assert.strictEqual(saved.get(storageKey), 'tl');
    assert.strictEqual(text.textContent, 'Wika / Language');
    assert.strictEqual(events.at(-1).type, 'dashboard:language-changed');
    assert.strictEqual(events.at(-1).detail.language, 'tl');
}

testAppPath();
testI18nEngine();
testPathConsumers();
testPortalLanguage(
    'FARMERS UI/farmersDashboard/shared/dashboard-language.js',
    'dashboardI18n',
    'farmersDashboardLanguage'
);
testPortalLanguage(
    'ADMIN UI/shared/admin-dashboard-language.js',
    'adminDashboardI18n',
    'adminDashboardLanguage'
);

const farmerLanguage = read('FARMERS UI/farmersDashboard/shared/dashboard-language.js');
const adminLanguage = read('ADMIN UI/shared/admin-dashboard-language.js');
assert.match(farmerLanguage, /storageKey = 'farmersDashboardLanguage'/);
assert.match(adminLanguage, /storageKey = 'adminDashboardLanguage'/);
assert.match(farmerLanguage, /window\.SCAIMSI18n\.create/);
assert.match(adminLanguage, /window\.SCAIMSI18n\.create/);

function assertLoadedBefore(relativePath, dependency, consumer) {
    const source = read(relativePath);
    const dependencyIndex = source.lastIndexOf(dependency);
    const consumerIndex = source.lastIndexOf(consumer);
    assert.ok(dependencyIndex >= 0, `${relativePath} must load ${dependency}`);
    assert.ok(consumerIndex > dependencyIndex,
        `${relativePath} must load ${dependency} before ${consumer}`);
}

[
    'FARMERS UI/farmersDashboard/home/home.php',
    'FARMERS UI/farmersDashboard/parcel/farmersFP.php',
    'FARMERS UI/farmersDashboard/profile/editProfile.php',
    'FARMERS UI/farmersDashboard/report/report.php',
].forEach(file => assertLoadedBefore(file, 'shared/i18n.js', 'dashboard-language.js'));
assertLoadedBefore('ADMIN UI/shared/admin-sidebar.php', 'shared/i18n.js', 'admin-dashboard-language.js');

[
    'FARMERS UI/farmersDashboard/report/report.php',
    'FARMERS UI/farmersDashboard/parcel/editParcel.php',
    'FARMERS UI/farmersDashboard/parcel/farmParcel.php',
    'ADMIN UI/adminDashboard/parcel/farmParcel.php',
    'ADMIN UI/adminDashboard/report/reportAnalytics.php',
    'ADMIN UI/adminDashboard/crops/cropManagement.php',
    'ADMIN UI/adminDashboard/report/crop/cropReports.php',
    'ADMIN UI/adminDashboard/report/damage/damageReports.php',
].forEach(file => assertLoadedBefore(file, 'app-path.js', 'crop-catalog.js'));
assertLoadedBefore('FARMERS UI/farmerLanding/map.html', 'app-path.js', 'leaflet-crop-icons.js');
const legacyRegistration = read('FARMERS UI/accountCreation/accountCreation.html');
assert.match(legacyRegistration, /\.\.\/registration\/registration_form\.html/);
assert.doesNotMatch(legacyRegistration, /accountCreation\.js|submitAccount\.php/);

console.log('PASS: shared i18n behavior, portal configuration, script load order, path consumers, and root/nested app-path resolution.');
