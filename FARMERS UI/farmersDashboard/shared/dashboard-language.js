document.addEventListener('DOMContentLoaded', function () {
    const storageKey = 'farmersDashboardLanguage';
    const fallbackLanguage = 'en';
    const translations = {
        en: {
            language_label: 'Language / Wika',
            nav_home: 'Home',
            nav_parcel: 'Farm Parcel',
            nav_report: 'Report Forms',
            nav_profile: 'Account Settings',
            nav_logout: 'Logout',
            nav_map: 'Crop Map',
            home_quick_title: 'What would you like to do?',
            home_report_choose: 'Choose report type',
            home_report_crop: 'Harvest Report',
            home_report_damage: 'Damaged Crop Report',
            sidebar_tip: 'Tap a menu item to continue.',
            footer_text: 'Lipa City Agriculture Office <br> &copy; 2024 All rights reserved',
            home_title: 'LATEST UPDATES',
            home_help: 'Check the newest announcements here before opening another menu.',
            report_title: 'REPORT FORM',
            report_help: 'Pick the report type first, then fill in one section at a time.',
            report_type_label: 'Type of Report',
            report_type_placeholder: 'Select a type of report',
            report_type_crop: 'Crop Harvest Report',
            report_type_damage: 'Crop Damage Report',
            report_crop_heading: 'Crop Harvest Report',
            report_damage_heading: 'Crop Damage Report',
            report_location: 'Location:',
            report_crop_commodity: 'Crop Commodity:',
            report_crop_type: 'Crop Type:',
            report_crop_variety: 'Crop Variety:',
            report_date_planted: 'Date Planted:',
            report_harvest_date: 'Harvest Date:',
            report_estimated_harvest: 'Estimated Harvest (kg):',
            report_crop_status: 'Crop Status:',
            report_number_of_trees: 'Number of Trees:',
            report_farm_gate_price: 'Farm Gate Price (P/kg):',
            report_damage_crop_commodity: 'Crop Commodity:',
            report_damage_crop_type: 'Crop Type:',
            report_damage_crop_variety: 'Crop Variety:',
            report_date_occurrence: 'Date of Occurrence:',
            report_damage_cause: 'Cause of Damage:',
            report_damage_percentage: 'Damage Percentage (%):',
            report_estimated_loss: 'Estimated Loss (kg):',
            report_damage_farm_gate_price: 'Farm Gate Price (P/kg):',
            report_submit: 'Submit Report',
            report_choose_location: 'Select a location',
            report_choose_commodity: 'Select a crop commodity',
            report_choose_crop_type: 'Select a crop type',
            report_choose_status: 'Select crop status',
            report_choose_damage_cause: 'Select the cause of damage',
            report_please_specify: 'Please specify',
            profile_title: 'EDIT PROFILE',
            profile_help: 'Update your account details here. Only change the fields you need.',
            profile_image_button: 'Choose Profile Image',
            profile_account_info: 'Account Information',
            profile_username: 'Username*',
            profile_current_password: 'Current Password*',
            profile_new_password: 'New Password',
            profile_confirm_password: 'Confirm New Password',
            profile_password_hint: "Leave blank if you don't want to change password",
            profile_surname: 'Surname*',
            profile_first_name: 'First Name*',
            profile_middle_name: 'Middle Name',
            profile_extension_name: 'Extension Name',
            profile_birth_date: 'Date of Birth*',
            profile_sex: 'Sex*',
            profile_contact_number: 'Contact Number*',
            profile_address: 'Address*',
            profile_barangay: 'Barangay*',
            profile_rsbsa: 'RSBSA Number (if registered)',
            profile_save: 'Save Changes',
            profile_cancel: 'Cancel',
            parcel_title: 'FARM PARCEL',
            parcel_help: 'Choose a parcel to view its farm details and crop information.',
            parcel_add_button: 'Add Parcel',
            parcel_edit_button: 'Edit Parcel',
            parcel_farmer_location: 'Farmer Location',
            parcel_total_farm_area: 'Total Farm Area',
            parcel_farm_type: 'Farm Type',
            parcel_organic_practitioner: 'Organic Practitioner',
            parcel_area_label: 'Parcel Area',
            parcel_ownership_type: 'Ownership Type',
            parcel_crops_information: 'Crops Information',
            parcel_crop_commodity: 'Crop Commodity',
            parcel_crop_type: 'Crop Type',
            parcel_crop_variety: 'Crop Variety',
            parcel_number_of_trees: 'Number of Trees',
            parcel_tree_status: 'Tree Status',
            parcel_previous: 'Previous',
            parcel_next: 'Next',
            parcel_no_crops: 'No crops found for this parcel.',
            posted_on: 'Posted on:',
            no_updates: 'No updates yet.',
        },
        tl: {
            language_label: 'Wika / Language',
            nav_home: 'Home',
            nav_parcel: 'Sakahan',
            nav_report: 'Mga Ulat',
            nav_profile: 'Aking Impormasyon',
            nav_logout: 'Mag-logout',
            nav_map: 'Mapa ng Pananim',
            home_quick_title: 'Ano ang nais ninyong gawin?',
            home_report_choose: 'Piliin ang uri ng ulat',
            home_report_crop: 'Ulat ng Ani',
            home_report_damage: 'Ulat ng Sirang Pananim',
            sidebar_tip: 'Pindutin ang menu para magpatuloy.',
            footer_text: 'Tanggapan ng Agrikultura ng Lipa City <br> &copy; 2024 All rights reserved',
            home_title: 'MGA BAGONG UPDATE',
            home_help: 'Tingnan muna rito ang mga bagong anunsyo bago pumunta sa ibang menu.',
            report_title: 'PAGPAPASA NG ULAT',
            report_help: 'Piliin muna ang uri ng ulat, pagkatapos ay sagutan ang bawat bahagi nang paisa-isa.',
            report_type_label: 'Uri ng Ulat',
            report_type_placeholder: 'Pumili ng uri ng ulat',
            report_type_crop: 'Ulat sa Inaning Pananim',
            report_type_damage: 'Ulat sa Nasirang Pananim',
            report_crop_heading: 'Ulat sa Inaning Pananim',
            report_damage_heading: 'Ulat sa Nasirang Pananim',
            report_location: 'Lokasyon:',
            report_crop_commodity: 'Uri ng Pananim:',
            report_crop_type: 'Klase ng Pananim:',
            report_crop_variety: 'Barayti ng Pananim:',
            report_date_planted: 'Petsa ng Pagtatanim:',
            report_harvest_date: 'Petsa ng Pag-ani:',
            report_estimated_harvest: 'Tinatayang Ani (kg):',
            report_crop_status: 'Kalagayan ng Pananim:',
            report_number_of_trees: 'Bilang ng Puno:',
            report_farm_gate_price: 'Presyo sa Farm Gate (P/kg):',
            report_damage_crop_commodity: 'Uri ng Pananim:',
            report_damage_crop_type: 'Klase ng Pananim:',
            report_damage_crop_variety: 'Barayti ng Pananim:',
            report_date_occurrence: 'Petsa ng Pangyayari:',
            report_damage_cause: 'Sanhi ng Pinsala:',
            report_damage_percentage: 'Porsyento ng Pinsala (%):',
            report_estimated_loss: 'Tinatayang Nawalang Ani (kg):',
            report_damage_farm_gate_price: 'Presyo sa Farm Gate (P/kg):',
            report_submit: 'Ipadala ang Ulat',
            report_choose_location: 'Pumili ng lokasyon',
            report_choose_commodity: 'Pumili ng uri ng pananim',
            report_choose_crop_type: 'Pumili ng klase ng pananim',
            report_choose_status: 'Pumili ng kalagayan ng pananim',
            report_choose_damage_cause: 'Pumili ng sanhi ng pinsala',
            report_please_specify: 'Pakilagay ang detalye',
            profile_title: 'AYUSIN ANG PROFILE',
            profile_help: 'Dito puwedeng baguhin ang inyong impormasyon. Palitan lang ang mga kailangang i-update.',
            profile_image_button: 'Pumili ng Larawan',
            profile_account_info: 'Impormasyon ng Account',
            profile_username: 'Username*',
            profile_current_password: 'Kasalukuyang Password*',
            profile_new_password: 'Bagong Password',
            profile_confirm_password: 'Kumpirmahin ang Bagong Password',
            profile_password_hint: 'Iwanang blangko kung hindi papalitan ang password',
            profile_surname: 'Apelyido*',
            profile_first_name: 'Pangalan*',
            profile_middle_name: 'Gitnang Pangalan',
            profile_extension_name: 'Extension Name',
            profile_birth_date: 'Petsa ng Kapanganakan*',
            profile_sex: 'Kasarian*',
            profile_contact_number: 'Numero ng Telepono*',
            profile_address: 'Address*',
            profile_barangay: 'Barangay*',
            profile_rsbsa: 'RSBSA Number (kung mayroon)',
            profile_save: 'I-save ang Pagbabago',
            profile_cancel: 'Kanselahin',
            parcel_title: 'SAKAHAN',
            parcel_help: 'Pumili ng parcel para makita ang detalye ng bukid at impormasyon ng pananim.',
            parcel_add_button: 'Magdagdag ng Parcel',
            parcel_edit_button: 'I-edit ang Parcel',
            parcel_farmer_location: 'Lokasyon ng Magsasaka',
            parcel_total_farm_area: 'Kabuuang Lawak ng Bukid',
            parcel_farm_type: 'Uri ng Bukid',
            parcel_organic_practitioner: 'Organic Practitioner',
            parcel_area_label: 'Lawak ng Parcel',
            parcel_ownership_type: 'Uri ng Pagmamay-ari',
            parcel_crops_information: 'Impormasyon ng Pananim',
            parcel_crop_commodity: 'Uri ng Pananim',
            parcel_crop_type: 'Klase ng Pananim',
            parcel_crop_variety: 'Barayti ng Pananim',
            parcel_number_of_trees: 'Bilang ng Puno',
            parcel_tree_status: 'Kalagayan ng Puno',
            parcel_previous: 'Nakaraan',
            parcel_next: 'Susunod',
            parcel_no_crops: 'Walang nakalistang pananim sa parcel na ito.',
            posted_on: 'Ipinost noong:',
            no_updates: 'Wala pang update.',
        },
    };

    function getLanguage() {
        return localStorage.getItem(storageKey) || fallbackLanguage;
    }

    function t(key) {
        const language = getLanguage();
        return (translations[language] && translations[language][key]) || translations.en[key] || key;
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

    window.dashboardI18n = {
        applyTranslations: applyTranslations,
        getLanguage: getLanguage,
        t: t,
    };

    applyTranslations(getLanguage());
});
