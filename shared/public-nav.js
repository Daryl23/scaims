(function () {
  const resolvePath = typeof window.resolveAppPath === "function"
    ? window.resolveAppPath
    : function (path) { return path; };
  const toRelativePath = typeof window.toAppRelativePath === "function"
    ? window.toAppRelativePath
    : resolvePath;
  const config = window.PUBLIC_NAV_CONFIG || {};

  const REGISTER_HREF = toRelativePath(config.registerHref || "/FARMERS%20UI/registration/rsbsa_check.html");
  const ADMIN_LOGIN_HREF = toRelativePath(config.adminLoginHref || "/ADMIN%20UI/adminLogin/adminLogin.html");
  const FARMER_LOGIN_HREF = toRelativePath(config.farmerLoginHref || "/FARMERS%20UI/farmerLogin/farmerlogin.html");
  const HOME_HREF = toRelativePath(config.homeHref || "/index.html");
  const NAV_ITEMS = (config.items || [
    { key: "about", label: "About Us", href: "/FARMERS%20UI/farmerLanding/about.html" },
    { key: "faq", label: "FAQ", href: "/FARMERS%20UI/farmerLanding/faq.html" },
    { key: "contact", label: "Contact", href: "/FARMERS%20UI/farmerLanding/contact.html" },
    { key: "map", label: "Map", href: "/FARMERS%20UI/farmerLanding/map.html" }
  ]).map((item) => ({
    key: item.key,
    label: item.label,
    href: toRelativePath(item.href)
  }));

  const brandIcon = `
    <svg class="public-nav__brand-svg" width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
    </svg>`;

  const adminIcon = `
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
    </svg>`;

  const farmerIcon = `
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
    </svg>`;

  const menuIcon = `
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18M3 10h18M3 16h18"></path>
    </svg>`;

  function buildLinks(activeKey) {
    return NAV_ITEMS.map((item) => {
      const activeClass = item.key === activeKey ? " is-active" : "";
      return `<a href="${item.href}" class="public-nav__link${activeClass}">${item.label}</a>`;
    }).join("");
  }

  function buildMobileLinks(activeKey) {
    return NAV_ITEMS.map((item) => {
      const activeClass = item.key === activeKey ? " is-active" : "";
      return `<a href="${item.href}" class="public-nav__mobile-link${activeClass}">${item.label}</a>`;
    }).join("");
  }

  function renderNavbar(host) {
    const activePage = host.dataset.activePage || "";
    const variant = host.dataset.navVariant === "static" ? " public-nav--static" : "";
    const mobileExtraLabel = host.dataset.mobileExtraLabel || "";
    const mobileExtraAction = host.dataset.mobileExtraAction || "";
    const registerHref = toRelativePath(host.dataset.registerHref || REGISTER_HREF);
    const brandTitle = host.dataset.brandTitle || config.brandTitle || "SCAIMS";
    const brandSubtitle = host.dataset.brandSubtitle || config.brandSubtitle || "Lipa City Agriculture";

    host.innerHTML = `
      <nav id="navbar" class="public-nav${variant}" data-public-nav>
        <div class="public-nav__container">
          <a href="${HOME_HREF}" class="public-nav__brand">
            <span class="public-nav__brand-badge">${brandIcon}</span>
            <span class="public-nav__brand-copy">
              <span class="public-nav__brand-title">${brandTitle}</span>
              <span class="public-nav__brand-subtitle">${brandSubtitle}</span>
            </span>
          </a>

          <div class="public-nav__desktop">
            <div class="public-nav__links">${buildLinks(activePage)}</div>
            <div class="public-nav__actions">
              <div class="public-nav__dropdown" id="loginDropdownWrap" data-public-dropdown>
                <button class="public-nav__dropdown-toggle" id="loginDropdownBtn" type="button" aria-expanded="false">
                  <span>Login</span>
                  <svg class="public-nav__chevron" id="loginChevron" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path>
                  </svg>
                </button>
                <div id="loginDropdown" class="public-nav__dropdown-menu opacity-0 scale-95 pointer-events-none">
                  <a href="${ADMIN_LOGIN_HREF}" class="public-nav__dropdown-link public-nav__dropdown-link--admin">
                    <span class="public-nav__dropdown-icon">${adminIcon}</span>
                    <span>Login as Admin</span>
                  </a>
                  <div class="public-nav__divider"></div>
                  <a href="${FARMER_LOGIN_HREF}" class="public-nav__dropdown-link public-nav__dropdown-link--farmer">
                    <span class="public-nav__dropdown-icon">${farmerIcon}</span>
                    <span>Login as Farmer</span>
                  </a>
                </div>
              </div>
              <a href="${registerHref}" class="public-nav__register">Register</a>
            </div>
          </div>

          <div class="public-nav__mobile-controls">
            ${mobileExtraLabel ? `<button type="button" class="public-nav__mobile-extra" data-mobile-extra-action="${mobileExtraAction}">${menuIcon}<span>${mobileExtraLabel}</span></button>` : ""}
            <button type="button" class="public-nav__mobile-toggle" data-public-mobile-toggle aria-expanded="false" aria-label="Open menu">
              <span></span>
              <span></span>
              <span></span>
            </button>
          </div>
        </div>

        <div id="mobileMenu" class="public-nav__mobile-menu translate-x-full" data-public-mobile-menu aria-hidden="true">
          ${buildMobileLinks(activePage)}
          <div class="public-nav__mobile-section">
            <div class="public-nav__mobile-label">Login As</div>
            <a href="${ADMIN_LOGIN_HREF}" class="public-nav__mobile-login">${adminIcon}<span>Admin</span></a>
            <a href="${FARMER_LOGIN_HREF}" class="public-nav__mobile-login">${farmerIcon}<span>Farmer</span></a>
          </div>
          <a href="${registerHref}" class="public-nav__mobile-register">Register</a>
        </div>
      </nav>
    `;
  }

  function bindNavbar(host) {
    const nav = host.querySelector("[data-public-nav]");
    if (!nav) return;

    const mobileMenu = nav.querySelector("[data-public-mobile-menu]");
    const mobileToggle = nav.querySelector("[data-public-mobile-toggle]");
    const dropdown = nav.querySelector("[data-public-dropdown]");
    const dropdownToggle = dropdown ? dropdown.querySelector(".public-nav__dropdown-toggle") : null;
    const dropdownMenu = nav.querySelector("#loginDropdown");
    const mobileExtra = nav.querySelector("[data-mobile-extra-action]");
    const isStatic = nav.classList.contains("public-nav--static");

    function setMobileMenuOpen(isOpen) {
      if (!mobileMenu || !mobileToggle) return;
      mobileMenu.classList.toggle("translate-x-full", !isOpen);
      mobileMenu.setAttribute("aria-hidden", String(!isOpen));
      mobileToggle.setAttribute("aria-expanded", String(isOpen));
      mobileToggle.setAttribute("aria-label", isOpen ? "Close menu" : "Open menu");
      document.body.classList.toggle("public-nav-lock", isOpen);
    }

    function closeMobileMenu() {
      setMobileMenuOpen(false);
    }

    function toggleMobileMenu() {
      if (!mobileMenu || !mobileToggle) return;
      const nextState = mobileMenu.classList.contains("translate-x-full");
      setMobileMenuOpen(nextState);
    }

    function closeDropdown() {
      if (!dropdown || !dropdownToggle || !dropdownMenu) return;
      dropdown.classList.remove("is-open");
      dropdownMenu.classList.add("opacity-0", "scale-95", "pointer-events-none");
      dropdownMenu.classList.remove("opacity-100", "scale-100");
      dropdownToggle.setAttribute("aria-expanded", "false");
    }

    if (mobileToggle) {
      mobileToggle.addEventListener("click", toggleMobileMenu);
    }

    nav.querySelectorAll(".public-nav__mobile-link, .public-nav__mobile-login, .public-nav__mobile-register").forEach((link) => {
      link.addEventListener("click", closeMobileMenu);
    });

    if (dropdown && dropdownToggle && dropdownMenu) {
      dropdownToggle.addEventListener("click", function () {
        const nextState = !dropdown.classList.contains("is-open");
        dropdown.classList.toggle("is-open", nextState);
        dropdownMenu.classList.toggle("opacity-0", !nextState);
        dropdownMenu.classList.toggle("scale-95", !nextState);
        dropdownMenu.classList.toggle("pointer-events-none", !nextState);
        dropdownMenu.classList.toggle("opacity-100", nextState);
        dropdownMenu.classList.toggle("scale-100", nextState);
        dropdownToggle.setAttribute("aria-expanded", String(nextState));
      });
    }

    if (mobileExtra) {
      mobileExtra.addEventListener("click", function () {
        const actionName = this.dataset.mobileExtraAction;
        if (actionName && typeof window[actionName] === "function") {
          window[actionName]();
        }
      });
    }

    document.addEventListener("click", function (event) {
      if (dropdown && !dropdown.contains(event.target)) {
        closeDropdown();
      }
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        closeDropdown();
        closeMobileMenu();
      }
    });

    if (!isStatic) {
      const syncShadow = function () {
        nav.classList.toggle("is-scrolled", window.scrollY > 50);
      };
      syncShadow();
      window.addEventListener("scroll", syncShadow, { passive: true });
    }
  }

  function init() {
    document.querySelectorAll("[data-public-navbar]").forEach((host) => {
      renderNavbar(host);
      bindNavbar(host);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
