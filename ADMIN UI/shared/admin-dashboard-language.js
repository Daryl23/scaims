document.addEventListener('DOMContentLoaded', function () {
    const storageKey = 'adminDashboardLanguage';
    const fallbackLanguage = 'en';
    const translations = {
        en: {
            language_label: 'Language / Wika',
            toggle_navigation: 'Toggle navigation',
            admin_portal: 'Admin Portal',
            admin_role: 'Administrator',
            nav_section_main: 'Main Menu',
            nav_home: 'Home',
            nav_users: 'User Management',
            nav_parcel: 'Farm Parcel',
            nav_reports: 'Reports',
            nav_crops: 'Crop Management',
            nav_posts: 'Post Updates',
            nav_suggestions: 'Suggestions',
            nav_logout: 'Logout',
            sidebar_tip: 'Open a section to continue managing records and services.',
            footer_text: 'Lipa City Agriculture Office <br> &copy; 2024 All rights reserved',
            admin_home_title: 'ADMIN OVERVIEW',
            admin_home_help: 'Track registrations, parcels, reports, and quick admin tasks from one place.',
            admin_stats_label: 'System Summary',
            admin_stat_registrations_label: 'TOTAL REGISTRATIONS',
            admin_stat_registrations_meta: 'Farmers',
            admin_stat_parcels_label: 'TOTAL FARM PARCELS',
            admin_stat_parcels_meta: 'Parcels',
            admin_stat_reports_label: 'TOTAL REPORTS SUBMITTED',
            admin_stat_reports_meta: 'Reports',
            admin_quick_label: 'Quick Actions',
            admin_action_users_title: 'Review Farmer Accounts',
            admin_action_users_copy: 'Check registrations, verify records, and manage account status.',
            admin_action_parcel_title: 'Inspect Farm Parcels',
            admin_action_parcel_copy: 'Browse parcel coverage, crop types, and farmer locations.',
            admin_action_reports_title: 'Open Report Analytics',
            admin_action_reports_copy: 'Compare crop and damage report totals with the latest data.',
            admin_action_posts_title: 'Post Farmer Updates',
            admin_action_posts_copy: 'Publish announcements and reminders farmers can read right away.',
            admin_action_suggestions_title: 'Read Suggestions',
            admin_action_suggestions_copy: 'Review new feedback and export suggestion records when needed.',
        },
        tl: {
            language_label: 'Wika / Language',
            toggle_navigation: 'Buksan ang menu',
            admin_portal: 'Admin Portal',
            admin_role: 'Administrador',
            nav_section_main: 'Pangunahing Menu',
            nav_home: 'Home',
            nav_users: 'Pamamahala ng User',
            nav_parcel: 'Sakahan',
            nav_reports: 'Mga Ulat',
            nav_crops: 'Pamamahala ng Pananim',
            nav_posts: 'Mga Update',
            nav_suggestions: 'Mga Mungkahi',
            nav_logout: 'Mag-logout',
            sidebar_tip: 'Buksan ang isang seksyon para ipagpatuloy ang pamamahala ng records at serbisyo.',
            footer_text: 'Tanggapan ng Agrikultura ng Lipa City <br> &copy; 2024 All rights reserved',
            admin_home_title: 'PANGKALAHATANG TINGIN NG ADMIN',
            admin_home_help: 'Dito masusubaybayan ang registrations, parcels, reports, at mahahalagang admin tasks sa iisang lugar.',
            admin_stats_label: 'Buod ng Sistema',
            admin_stat_registrations_label: 'KABUUANG REHISTRASYON',
            admin_stat_registrations_meta: 'Mga magsasaka',
            admin_stat_parcels_label: 'KABUUANG FARM PARCEL',
            admin_stat_parcels_meta: 'Mga Parcel',
            admin_stat_reports_label: 'KABUUANG NAIPASANG ULAT',
            admin_stat_reports_meta: 'Mga Ulat',
            admin_quick_label: 'Mabilisang Gawain',
            admin_action_users_title: 'Suriin ang Farmer Accounts',
            admin_action_users_copy: 'Tingnan ang registrations, beripikahin ang records, at ayusin ang account status.',
            admin_action_parcel_title: 'Siyasatin ang Farm Parcels',
            admin_action_parcel_copy: 'Tingnan ang parcel coverage, uri ng pananim, at lokasyon ng mga magsasaka.',
            admin_action_reports_title: 'Buksan ang Report Analytics',
            admin_action_reports_copy: 'Ihambing ang kabuuan ng crop at damage reports gamit ang pinakabagong datos.',
            admin_action_posts_title: 'Mag-post ng Update',
            admin_action_posts_copy: 'Maglabas ng anunsyo at paalala na mababasa agad ng mga magsasaka.',
            admin_action_suggestions_title: 'Basahin ang Mga Mungkahi',
            admin_action_suggestions_copy: 'Suriin ang bagong feedback at mag-export ng suggestion records kung kailangan.',
        },
    };

    function getLanguage() {
        return localStorage.getItem(storageKey) || fallbackLanguage;
    }

    function setActiveButton(language) {
        document.querySelectorAll('.language-toggle-button').forEach(function (button) {
            button.classList.toggle('active', button.dataset.language === language);
        });
    }

    function applyTranslations(language) {
        document.querySelectorAll('[data-i18n]').forEach(function (element) {
            const key = element.dataset.i18n;
            if (translations[language] && translations[language][key]) {
                element.textContent = translations[language][key];
            }
        });

        document.querySelectorAll('[data-i18n-html]').forEach(function (element) {
            const key = element.dataset.i18nHtml;
            if (translations[language] && translations[language][key]) {
                element.innerHTML = translations[language][key];
            }
        });

        document.querySelectorAll('[data-i18n-placeholder]').forEach(function (element) {
            const key = element.dataset.i18nPlaceholder;
            if (translations[language] && translations[language][key]) {
                element.placeholder = translations[language][key];
            }
        });

        document.querySelectorAll('[data-i18n-aria-label]').forEach(function (element) {
            const key = element.dataset.i18nAriaLabel;
            if (translations[language] && translations[language][key]) {
                element.setAttribute('aria-label', translations[language][key]);
            }
        });

        document.documentElement.lang = language === 'tl' ? 'tl' : 'en';
        setActiveButton(language);
        document.dispatchEvent(new CustomEvent('dashboard:language-changed', { detail: { language: language } }));
    }

    document.querySelectorAll('.language-toggle-button').forEach(function (button) {
        button.addEventListener('click', function () {
            const language = button.dataset.language || fallbackLanguage;
            localStorage.setItem(storageKey, language);
            applyTranslations(language);
        });
    });

    window.adminDashboardI18n = {
        applyTranslations: applyTranslations,
        getLanguage: getLanguage,
        t: function (key) {
            const language = getLanguage();
            return (translations[language] && translations[language][key]) || translations.en[key] || key;
        },
    };

    applyTranslations(getLanguage());
});
