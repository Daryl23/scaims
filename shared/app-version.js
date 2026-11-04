// One-time cleanup when a browser last used an older SCAIMS release.
// Only data SCAIMS itself owns is removed, because other apps can share the
// same origin (e.g. several projects under one localhost). Keep the version in
// sync with shared/app_version.php.
(function () {
  var SCAIMS_APP_VERSION = '7.0.0';
  var VERSION_KEY = 'scaims_app_version';
  var OWNED_STORAGE_KEYS = ['farmersDashboardLanguage', 'adminDashboardLanguage'];
  var OWNED_CACHE_PREFIX = 'scaims-';
  var CURRENT_CACHE = 'scaims-farmer-shell-v2';

  window.SCAIMS_APP_VERSION = SCAIMS_APP_VERSION;

  var storedVersion = null;
  try { storedVersion = localStorage.getItem(VERSION_KEY); } catch (e) { return; }
  if (storedVersion === SCAIMS_APP_VERSION) return;

  try {
    OWNED_STORAGE_KEYS.forEach(function (key) {
      localStorage.removeItem(key);
      sessionStorage.removeItem(key);
    });
    localStorage.setItem(VERSION_KEY, SCAIMS_APP_VERSION);
  } catch (e) { /* storage blocked: nothing to wipe */ }

  if (window.caches && caches.keys) {
    caches.keys().then(function (names) {
      return Promise.all(names
        .filter(function (name) { return name.indexOf(OWNED_CACHE_PREFIX) === 0 && name !== CURRENT_CACHE; })
        .map(function (name) { return caches.delete(name); }));
    }).catch(function () {});
  }

  if (navigator.serviceWorker && navigator.serviceWorker.getRegistrations) {
    navigator.serviceWorker.getRegistrations().then(function (registrations) {
      registrations.forEach(function (registration) {
        if (/\/FARMERS%20UI\//i.test(registration.scope)) registration.update();
      });
    }).catch(function () {});
  }
})();
