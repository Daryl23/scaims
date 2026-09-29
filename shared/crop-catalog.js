(function () {
  const DEFAULT_GROUPS = {
    grains: ["Rice", "Corn"],
    plantation: ["Coconut", "Coffee", "Sugarcane", "Black Pepper", "Cacao"],
    root: ["Sweet Potato", "Cassava", "Ube/Yam", "Ginger", "Gabi"],
    vegetables: [
      "Ampalaya", "Bell Pepper", "Bush Sitao", "Chayote", "Cowpea", "Cucumber",
      "Eggplant", "Hot Pepper", "Lady Finger (okra)", "Lettuce", "Mung Bean",
      "Mustard", "Pechay", "Pole Sitao", "Radish", "Snap Beans", "Soybeans",
      "Squash", "Tomato", "Upo", "Patola", "Sigarilyas"
    ],
    fruits: [
      "Avocado", "Banana", "Calamansi", "Citrus", "Pomelo", "Papaya",
      "Dragon Fruit", "Durian", "Guyabano", "Jackfruit", "Lanzones", "Mango",
      "Mango Pajo", "Mangosteen", "Pineapple", "Rambutan", "Santol", "Tamarind",
      "Pili"
    ]
  };

  let groups = cloneGroups(DEFAULT_GROUPS);
  let loaded = false;
  let loadingPromise = null;

  function cloneGroups(source) {
    const copy = {};
    Object.keys(source).forEach((key) => {
      copy[key] = source[key].slice();
    });
    copy.root_crops = copy.root.slice();
    return copy;
  }

  function normalizeCommodityKey(value) {
    const key = String(value || "").toLowerCase().trim().replace(/[-\s]+/g, "_");
    if (key === "root_crops" || key === "root_crop") return "root";
    return key;
  }

  function normalizeOptionText(value) {
    return String(value || "").trim();
  }

  function getTypes(commodity, options) {
    options = options || {};
    const key = normalizeCommodityKey(commodity);
    const base = (groups[key] || []).map(normalizeOptionText).filter(Boolean);
    const result = base.slice();
    const otherLabel = options.otherLabel || "Other";

    if (options.includeOther && !result.some((item) => item.toLowerCase() === otherLabel.toLowerCase())) {
      result.push(otherLabel);
    }

    return result;
  }

  function getAllTypes() {
    const seen = new Set();
    const result = [];
    ["grains", "plantation", "root", "vegetables", "fruits"].forEach((commodity) => {
      getTypes(commodity).forEach((crop) => {
        const key = crop.toLowerCase();
        if (!seen.has(key)) {
          seen.add(key);
          result.push(crop);
        }
      });
    });
    return result;
  }

  function slugifyCrop(value) {
    return String(value || "")
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-+|-+$/g, "");
  }

  function load() {
    if (loadingPromise) return loadingPromise;

    loadingPromise = fetch(window.SCAIMSAppPath.resolve("/shared/crop_catalog_api.php"), {
      headers: { Accept: "application/json" },
      credentials: "same-origin"
    })
      .then((response) => {
        if (!response.ok) throw new Error("Crop catalog request failed.");
        return response.json();
      })
      .then((payload) => {
        if (!payload || !payload.success || !payload.groups) {
          throw new Error(payload && payload.message ? payload.message : "Invalid crop catalog response.");
        }

        groups = cloneGroups(payload.groups);
        loaded = true;
        document.dispatchEvent(new CustomEvent("scaims:crop-catalog-ready", { detail: { groups } }));
        return groups;
      })
      .catch((error) => {
        console.warn("Using built-in crop catalog fallback:", error.message);
        return groups;
      });

    return loadingPromise;
  }

  function appendOption(select, value, label) {
    const option = document.createElement("option");
    option.value = value;
    option.textContent = label;
    select.appendChild(option);
  }

  function populateCropTypeSelect(select, commodity, options) {
    if (!select) return;
    options = options || {};

    const placeholder = options.placeholder || "Select a crop type";
    select.innerHTML = "";
    appendOption(select, "", placeholder);
    select.options[0].disabled = true;
    select.options[0].selected = true;

    getTypes(commodity, {
      includeOther: Boolean(options.includeOther),
      otherLabel: options.otherLabel || "Other"
    }).forEach((crop) => {
      appendOption(select, crop, crop);
    });
  }

  function populateCropFilter(select, options) {
    if (!select) return;
    options = options || {};

    const selected = select.value;
    select.innerHTML = "";
    appendOption(select, "", options.allLabel || "All Crops");
    if (options.includeOther !== false) {
      appendOption(select, "Other", options.otherLabel || "Other");
    }

    getAllTypes().forEach((crop) => {
      appendOption(select, options.valueFormat === "slug" ? slugifyCrop(crop) : crop, crop);
    });

    if ([...select.options].some((option) => option.value === selected)) {
      select.value = selected;
    }
  }

  window.SCAIMSCropCatalog = {
    defaults: cloneGroups(DEFAULT_GROUPS),
    get loaded() {
      return loaded;
    },
    load,
    ready: load,
    getGroups: () => cloneGroups(groups),
    getTypes,
    getAllTypes,
    slugifyCrop,
    populateCropTypeSelect,
    populateCropFilter,
    normalizeCommodityKey
  };

  load();
})();
