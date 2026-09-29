(function () {
  var ICON_FILES = [
    "Ampalaya.png",
    "Arrowroot.png",
    "Avocado.png",
    "Banana.png",
    "Black Pepper.png",
    "Cacao.png",
    "Cassava.png",
    "Coconut.png",
    "Coffee.png",
    "Corn.png",
    "Ginger.png",
    "Guyabano.png",
    "Jackfruit.png",
    "Lady Finger (okra).png",
    "Lanzones.png",
    "Mango.png",
    "Mustard.png",
    "Pole Sitao.png",
    "Pomelo.png",
    "Rambutan.png",
    "Rice.png",
    "Squash.png",
    "Tomato.png",
    "Ube-Yam.png",
    "Wheat.png"
  ];

  var ALIASES = {
    "lady finger okra": "Lady Finger (okra).png",
    "okra": "Lady Finger (okra).png",
    "ube": "Ube-Yam.png",
    "yam": "Ube-Yam.png",
    "ube yam": "Ube-Yam.png",
    "sitao": "Pole Sitao.png",
    "string bean": "Pole Sitao.png",
    "string beans": "Pole Sitao.png",
    "bush sitao": "Pole Sitao.png"
  };

  var iconByKey = {};

  function stripExtension(value) {
    return String(value || "").replace(/\.[a-z0-9]+$/i, "");
  }

  function stripDiacritics(value) {
    if (value && typeof value.normalize === "function") {
      return value.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    }
    return value;
  }

  function normalizeLabel(value) {
    return stripDiacritics(stripExtension(value))
      .toLowerCase()
      .replace(/\([^)]*\)/g, " ")
      .replace(/&/g, " and ")
      .replace(/[\/_+-]+/g, " ")
      .replace(/[^a-z0-9\s]/g, " ")
      .replace(/\s+/g, " ")
      .trim();
  }

  function registerIcon(label, fileName) {
    var key = normalizeLabel(label);
    if (key) {
      iconByKey[key] = fileName;
    }
  }

  ICON_FILES.forEach(function (fileName) {
    registerIcon(fileName, fileName);
  });

  Object.keys(ALIASES).forEach(function (label) {
    registerIcon(label, ALIASES[label]);
  });

  function getIconFile(cropLabel) {
    return iconByKey[normalizeLabel(cropLabel)] || null;
  }

  function getIconUrl(cropLabel) {
    var fileName = getIconFile(cropLabel);
    if (!fileName) {
      return null;
    }

    return window.SCAIMSAppPath.resolve("/assets/icons/" + encodeURIComponent(fileName));
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value).replace(/[&<>"']/g, function (char) {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;"
      }[char];
    });
  }

  function sanitizeCssColor(value) {
    var color = String(value || "").trim();
    if (/^#[0-9a-f]{3,8}$/i.test(color) || /^rgba?\([0-9.,\s%]+\)$/i.test(color)) {
      return color;
    }
    return "#3b4918";
  }

  function ensureStyles() {
    if (typeof document === "undefined" || document.getElementById("scaims-leaflet-crop-icons-style")) {
      return;
    }

    var style = document.createElement("style");
    style.id = "scaims-leaflet-crop-icons-style";
    style.textContent = [
      ".scaims-crop-marker-icon{background:transparent;border:0;}",
      ".scaims-crop-marker{position:relative;width:40px;height:46px;cursor:pointer;transform-origin:center bottom;transition:transform .16s ease;}",
      ".scaims-crop-marker:hover{transform:scale(1.12) translateY(-2px);}",
      ".scaims-crop-marker:after{content:\"\";position:absolute;left:50%;bottom:3px;width:11px;height:11px;transform:translateX(-50%) rotate(45deg);background:#fff;border-right:2px solid var(--crop-icon-accent);border-bottom:2px solid var(--crop-icon-accent);box-shadow:3px 3px 8px rgba(0,0,0,.22);z-index:0;}",
      ".scaims-crop-marker__frame{position:absolute;left:0;top:0;z-index:1;box-sizing:border-box;width:40px;height:40px;border-radius:50%;border:3px solid var(--crop-icon-accent);background:linear-gradient(180deg,#fff,#fff8df);box-shadow:0 4px 13px rgba(0,0,0,.28),inset 0 0 0 2px rgba(255,255,255,.85);display:flex;align-items:center;justify-content:center;overflow:hidden;}",
      ".scaims-crop-marker__frame img{display:block;width:28px;height:28px;object-fit:contain;filter:drop-shadow(0 1px 1px rgba(42,51,17,.28));}"
    ].join("");

    (document.head || document.documentElement).appendChild(style);
  }

  function createLeafletIcon(cropLabel, options) {
    var url = getIconUrl(cropLabel);
    if (!url || !window.L || typeof window.L.divIcon !== "function") {
      return null;
    }

    options = options || {};
    ensureStyles();

    var label = options.label || cropLabel || "Crop";
    var color = sanitizeCssColor(options.color);
    var iconSize = options.iconSize || [40, 46];
    var iconAnchor = options.iconAnchor || [20, 43];
    var popupAnchor = options.popupAnchor || [0, -38];

    return window.L.divIcon({
      className: options.className || "scaims-crop-marker-icon",
      html: '<div class="scaims-crop-marker" title="' + escapeHtml(label) + '" style="--crop-icon-accent:' + color + '"><div class="scaims-crop-marker__frame"><img src="' + escapeHtml(url) + '" alt="' + escapeHtml(label) + '"></div></div>',
      iconSize: iconSize,
      iconAnchor: iconAnchor,
      popupAnchor: popupAnchor
    });
  }

  window.SCAIMSCropIcons = {
    labels: ICON_FILES.map(stripExtension),
    normalizeLabel: normalizeLabel,
    getIconFile: getIconFile,
    getIconUrl: getIconUrl,
    hasIcon: function (cropLabel) {
      return Boolean(getIconFile(cropLabel));
    },
    createLeafletIcon: createLeafletIcon
  };
})();
