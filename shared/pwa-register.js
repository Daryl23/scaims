// Wires up the farmer-portal PWA (manifest link + service worker) on any
// page under "FARMERS UI/". Self-locating via the current URL so every
// farmer-facing page can include this one script regardless of its depth.
(function () {
  var marker = /\/FARMERS%20UI\//i;
  var match = marker.exec(window.location.pathname);
  if (!match) return;

  var farmerRoot = window.location.pathname.slice(0, match.index + match[0].length);

  if (!document.querySelector('link[rel="manifest"]')) {
    var manifestLink = document.createElement('link');
    manifestLink.rel = 'manifest';
    manifestLink.href = farmerRoot + 'manifest.json';
    document.head.appendChild(manifestLink);
  }

  if (!document.querySelector('meta[name="theme-color"]')) {
    var themeMeta = document.createElement('meta');
    themeMeta.name = 'theme-color';
    themeMeta.content = '#3b4918';
    document.head.appendChild(themeMeta);
  }

  if (!document.querySelector('link[rel="apple-touch-icon"]')) {
    var appleIcon = document.createElement('link');
    appleIcon.rel = 'apple-touch-icon';
    appleIcon.href = farmerRoot + '../assets/pwa-icons/apple-touch-icon.png';
    document.head.appendChild(appleIcon);
  }

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(farmerRoot + 'sw.js', { scope: farmerRoot }).catch(function () {});
    });
  }

  // Surface the native "Add to Home Screen" prompt. The browser only fires
  // this on pages within the app's manifest scope (FARMERS UI/**), so a
  // page outside that scope (e.g. the public landing page) cannot trigger
  // it directly — it can only link into this scope first.
  var deferredInstallPrompt = null;
  window.addEventListener('beforeinstallprompt', function (event) {
    event.preventDefault();
    deferredInstallPrompt = event;
    window.dispatchEvent(new CustomEvent('scaims:pwa-installable'));
  });

  window.scaimsInstallFarmerApp = function () {
    if (!deferredInstallPrompt) return Promise.resolve(false);
    var promptEvent = deferredInstallPrompt;
    deferredInstallPrompt = null;
    promptEvent.prompt();
    return promptEvent.userChoice.then(function (choice) {
      return choice.outcome === 'accepted';
    });
  };
})();
