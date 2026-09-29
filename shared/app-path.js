(function () {
  function detectBasePath() {
    var currentScript = document.currentScript;
    var scriptUrl = currentScript && currentScript.src ? currentScript.src : "";

    if (!scriptUrl) {
      var fallbackScript = document.querySelector('script[src$="shared/app-path.js"]');
      scriptUrl = fallbackScript && fallbackScript.src ? fallbackScript.src : "";
    }

    if (scriptUrl) {
      try {
        var parsedUrl = new URL(scriptUrl, window.location.href);
        var sharedIndex = parsedUrl.pathname.lastIndexOf("/shared/app-path.js");
        if (sharedIndex >= 0) {
          var derivedBase = parsedUrl.pathname.slice(0, sharedIndex);
          return derivedBase === "" ? "" : derivedBase.replace(/\/$/, "");
        }
      } catch (error) {
        // Fall through to pathname-based detection.
      }
    }

    var path = (window.location && window.location.pathname) || "";
    if (path === "/scaims" || path.indexOf("/scaims/") === 0) {
      return "/scaims";
    }

    return "";
  }

  var basePath = detectBasePath();

  function resolveAppPath(value) {
    if (!value || typeof value !== "string") return value;
    if (/^(?:[a-z]+:)?\/\//i.test(value)) return value;
    if (/^(mailto:|tel:|javascript:|#)/i.test(value)) return value;
    if (value.charAt(0) === "/") {
      if (!basePath) return value;
      if (value === basePath || value.indexOf(basePath + "/") === 0) return value;
      return basePath + value;
    }
    return value;
  }

  function toAppRelativePath(value) {
    if (!value || typeof value !== "string") return value;
    if (/^(?:[a-z]+:)?\/\//i.test(value)) return value;
    if (/^(mailto:|tel:|javascript:|#)/i.test(value)) return value;
    if (value.charAt(0) !== "/") return value;

    var currentPath = (window.location && window.location.pathname) || "/";
    var normalizedCurrent = currentPath;
    var normalizedTarget = value;

    if (basePath && normalizedCurrent.indexOf(basePath + "/") === 0) {
      normalizedCurrent = normalizedCurrent.slice(basePath.length);
    } else if (basePath && normalizedCurrent === basePath) {
      normalizedCurrent = "/";
    }

    if (basePath && normalizedTarget.indexOf(basePath + "/") === 0) {
      normalizedTarget = normalizedTarget.slice(basePath.length);
    }

    var currentSegments = normalizedCurrent.split("/").filter(Boolean);
    if (!/\/$/.test(normalizedCurrent) && currentSegments.length) {
      currentSegments.pop();
    }

    var targetSegments = normalizedTarget.split("/").filter(Boolean);
    var sharedLength = 0;
    while (
      sharedLength < currentSegments.length &&
      sharedLength < targetSegments.length &&
      currentSegments[sharedLength] === targetSegments[sharedLength]
    ) {
      sharedLength += 1;
    }

    var upSegments = currentSegments.slice(sharedLength).map(function () {
      return "..";
    });
    var downSegments = targetSegments.slice(sharedLength);
    var relativeSegments = upSegments.concat(downSegments);

    return relativeSegments.length ? relativeSegments.join("/") : ".";
  }

  window.APP_BASE_PATH = basePath;
  window.resolveAppPath = resolveAppPath;
  window.toAppRelativePath = toAppRelativePath;
  window.SCAIMSAppPath = {
    getBasePath: function () { return basePath; },
    resolve: resolveAppPath,
    toRelative: toAppRelativePath
  };
})();
