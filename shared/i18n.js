(function (global) {
    'use strict';

    function create(options) {
        const storageKey = options.storageKey;
        const fallbackLanguage = options.fallbackLanguage || 'en';
        const translations = options.translations || {};
        const buttonSelector = options.buttonSelector || '.language-toggle-button';

        function getLanguage() {
            return localStorage.getItem(storageKey) || fallbackLanguage;
        }

        function t(key) {
            const language = getLanguage();
            return (translations[language] && translations[language][key])
                || (translations[fallbackLanguage] && translations[fallbackLanguage][key])
                || key;
        }

        function setActiveButton(language) {
            document.querySelectorAll(buttonSelector).forEach(function (button) {
                button.classList.toggle('active', button.dataset.language === language);
            });
        }

        function applyTranslations(language) {
            document.querySelectorAll('[data-i18n]').forEach(function (element) {
                const value = translations[language] && translations[language][element.dataset.i18n];
                if (value) element.textContent = value;
            });

            document.querySelectorAll('[data-i18n-html]').forEach(function (element) {
                const value = translations[language] && translations[language][element.dataset.i18nHtml];
                if (value) element.innerHTML = value;
            });

            document.querySelectorAll('[data-i18n-placeholder]').forEach(function (element) {
                const value = translations[language] && translations[language][element.dataset.i18nPlaceholder];
                if (value) element.placeholder = value;
            });

            document.querySelectorAll('[data-i18n-aria-label]').forEach(function (element) {
                const value = translations[language] && translations[language][element.dataset.i18nAriaLabel];
                if (value) element.setAttribute('aria-label', value);
            });

            document.documentElement.lang = language === 'tl' ? 'tl' : 'en';
            setActiveButton(language);
            document.dispatchEvent(new CustomEvent('dashboard:language-changed', {
                detail: { language: language },
            }));
        }

        function bindLanguageButtons() {
            document.querySelectorAll(buttonSelector).forEach(function (button) {
                button.addEventListener('click', function () {
                    const language = button.dataset.language || fallbackLanguage;
                    localStorage.setItem(storageKey, language);
                    applyTranslations(language);
                });
            });
        }

        return {
            applyTranslations: applyTranslations,
            bindLanguageButtons: bindLanguageButtons,
            getLanguage: getLanguage,
            t: t,
        };
    }

    global.SCAIMSI18n = { create: create };
}(window));
