(function (global, $) {
    'use strict';

    const namespace = global.SCAIMSAdminReportView = global.SCAIMSAdminReportView || {};

    namespace.createPagination = function (options) {
        const settings = Object.assign({
            pageSize: 10,
            previousSelector: '#prev-page',
            nextSelector: '#next-page',
            labelSelector: '#current-page',
            scrollSelector: null,
            onPageChange: function () {}
        }, options || {});
        let currentPage = 1;

        function update(totalReports) {
            const totalPages = Math.ceil(Number(totalReports || 0) / settings.pageSize);
            $(settings.previousSelector).prop('disabled', currentPage === 1);
            $(settings.nextSelector).prop('disabled', currentPage >= totalPages || totalPages === 0);
            $(settings.labelSelector).text(totalPages === 0
                ? 'Page 0 of 0'
                : `Page ${currentPage} of ${totalPages}`);
        }

        function scrollToReports() {
            if (!settings.scrollSelector) {
                return;
            }
            const section = document.querySelector(settings.scrollSelector);
            if (section) {
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        $(settings.nextSelector + ', ' + settings.previousSelector).on('click', function () {
            if ($(this).prop('disabled')) {
                return;
            }

            if ($(this).is(settings.previousSelector)) {
                currentPage = Math.max(1, currentPage - 1);
            } else {
                currentPage += 1;
            }
            settings.onPageChange(currentPage);
            scrollToReports();
        });

        return {
            getPage: function () { return currentPage; },
            reset: function () { currentPage = 1; return currentPage; },
            update: update
        };
    };

    namespace.bindSearch = function (options) {
        const settings = Object.assign({ selector: '#search-bar', delay: 300 }, options || {});
        let debounceTimer;

        $(settings.selector).on('keypress', function (event) {
            if (event.which === 13) {
                event.preventDefault();
                settings.onSearch();
            }
        });

        $(settings.selector).on('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(settings.onSearch, settings.delay);
        });
    };

    namespace.request = function (options) {
        $.ajax({
            url: options.url,
            type: options.method || 'GET',
            dataType: options.dataType || 'json',
            data: options.data,
            success: options.onSuccess,
            error: options.onError
        });
    };
    namespace.fetchDetail = namespace.request;
    namespace.fetchList = namespace.request;

    namespace.setReportImage = function (options) {
        const panel = $(options.panelSelector);
        const image = $(options.imageSelector);
        const noImage = $(options.noImageSelector);

        panel.removeAttr('hidden').show();
        if (options.imageUrl) {
            image.attr('src', options.imageUrl).removeAttr('hidden').show();
            noImage.attr('hidden', true).hide();
            return;
        }

        image.removeAttr('src').attr('hidden', true).hide();
        noImage.removeAttr('hidden').show();
    };

    namespace.openModal = function (selector) {
        $(selector).css('display', 'block');
    };

    namespace.closeModal = function (selector) {
        $(selector).css('display', 'none');
    };

    namespace.bindModalDismiss = function (options) {
        $(options.closeSelector).on('click', function () {
            namespace.closeModal(options.modalSelector);
            if (typeof options.onClose === 'function') {
                options.onClose();
            }
        });

        $(window).on('click', function (event) {
            if ($(event.target).is(options.modalSelector)) {
                namespace.closeModal(options.modalSelector);
                if (typeof options.onClose === 'function') {
                    options.onClose();
                }
            }
        });
    };

    namespace.formatDate = function (dateString) {
        if (!dateString) {
            return 'N/A';
        }
        return new Date(dateString).toLocaleDateString();
    };

    namespace.replaceChart = function (existingChart, context, configuration) {
        if (existingChart && typeof existingChart.destroy === 'function') {
            existingChart.destroy();
        }
        return new Chart(context, configuration);
    };

    namespace.destroyChart = function (chart) {
        if (chart && typeof chart.destroy === 'function') {
            chart.destroy();
        }
        return null;
    };

    namespace.ensureExportButtons = function (modalSelector, options) {
        const settings = Object.assign({ justifyContent: null }, options || {});
        let container = $(modalSelector).find('#export-buttons-container');
        if (container.length) {
            return container;
        }

        const containerCss = { marginTop: '20px', display: 'flex', gap: '10px' };
        if (settings.justifyContent) {
            containerCss.justifyContent = settings.justifyContent;
        }
        const buttonCss = {
            padding: '8px 16px',
            backgroundColor: '#198754',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            cursor: 'pointer',
            fontFamily: 'Poppins'
        };
        container = $('<div>', { id: 'export-buttons-container', css: containerCss }).append(
            $('<button>', { id: 'export-pdf-button', class: 'export-button', type: 'button', text: 'Export as PDF', css: buttonCss }),
            $('<button>', { id: 'export-excel-button', class: 'export-button', type: 'button', text: 'Export as Excel', css: buttonCss })
        );
        $(modalSelector).find('.modal-content').append(container);
        return container;
    };

    namespace.downloadBlob = function (url, filename) {
        return fetch(url)
            .then(function (response) {
                if (!response.ok) {
                    return response.text().then(function (text) {
                        throw new Error(text || 'Export failed');
                    });
                }
                return response.blob();
            })
            .then(function (blob) {
                const objectUrl = global.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = objectUrl;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                global.URL.revokeObjectURL(objectUrl);
                link.remove();
            });
    };

    namespace.submitExportForm = function (options) {
        const form = $('<form>', {
            method: options.method || 'POST',
            action: options.url,
            style: 'display: none'
        });

        Object.keys(options.fields || {}).forEach(function (name) {
            const value = options.fields[name];
            if (value) {
                form.append($('<input>', { name: name, value: value }));
            }
        });

        $('body').append(form);
        form.submit();
        form.remove();
    };
})(window, window.jQuery);
