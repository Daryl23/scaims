/* ============================================================
   APGORRS — Choice cards
   ------------------------------------------------------------
   Progressive enhancement for short dropdowns. Mark a select
   with data-choice-cards and it is presented as a row of cards.

   The <select> itself is kept in the form and stays the source
   of truth:

     - picking a card sets select.value and fires a "change"
       event, so existing onchange handlers and listeners run
       exactly as before;
     - anything that sets select.value from elsewhere is picked
       up and the cards re-sync;
     - the field still submits under its own name, and a
       required select still blocks submission.

   Without JavaScript the original dropdown is shown untouched.
   ============================================================ */

(function () {
    'use strict';

    var SELECTOR = 'select[data-choice-cards]';

    function optionsOf(select) {
        // A disabled option with no value is a placeholder ("Choose one"),
        // not a choice. An enabled empty option is a real choice, such as
        // "Do not include coordinates".
        return Array.prototype.filter.call(select.options, function (opt) {
            return !(opt.disabled && opt.value === '');
        });
    }

    function labelFor(select) {
        if (select.id) {
            var lab = document.querySelector('label[for="' + select.id + '"]');
            if (lab) { return lab; }
        }
        return select.closest('label');
    }

    function build(select) {
        if (select.dataset.ccReady === 'true') { return; }

        var choices = optionsOf(select);
        if (choices.length < 2 || choices.length > 3) { return; }

        var group = document.createElement('div');
        group.className = 'cc-group';
        group.setAttribute('role', 'radiogroup');
        group.dataset.count = String(choices.length);

        var label = labelFor(select);
        if (label) {
            if (!label.id) {
                label.id = (select.id || select.name || 'cc') + '-label';
            }
            group.setAttribute('aria-labelledby', label.id);
        }

        var cards = choices.map(function (opt) {
            var card = document.createElement('button');
            card.type = 'button';
            card.className = 'cc-card';
            card.setAttribute('role', 'radio');
            card.dataset.value = opt.value;

            var mark = document.createElement('i');
            mark.className = 'bi bi-check-circle-fill cc-mark';
            mark.setAttribute('aria-hidden', 'true');

            var text = document.createElement('span');
            text.textContent = opt.textContent.trim();

            card.appendChild(mark);
            card.appendChild(text);
            group.appendChild(card);

            card.addEventListener('click', function () {
                if (select.disabled) { return; }
                choose(opt.value, true);
            });

            return card;
        });

        function sync() {
            var current = select.value;
            var anyChecked = false;
            // Report and parcel forms lock their fields until the farmer taps
            // Edit, by disabling the select. The cards must follow.
            var locked = select.disabled;
            group.classList.toggle('cc-locked', locked);
            group.setAttribute('aria-disabled', locked ? 'true' : 'false');
            cards.forEach(function (card) {
                var on = card.dataset.value === current;
                if (on) { anyChecked = true; }
                card.setAttribute('aria-checked', on ? 'true' : 'false');
                card.disabled = locked;
                // Only the selected card is in the tab order; arrow keys move
                // between them, which is how a radio group is expected to work.
                card.tabIndex = on && !locked ? 0 : -1;
            });
            if (!anyChecked && cards.length) {
                cards[0].tabIndex = 0;
            }
            if (anyChecked) {
                group.classList.remove('cc-invalid');
                clearError();
            }
        }

        function choose(value, focus) {
            if (select.disabled) { return; }
            if (select.value === value) {
                sync();
                return;
            }
            select.value = value;
            sync();
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
            if (focus) {
                var active = group.querySelector('[aria-checked="true"]');
                if (active) { active.focus(); }
            }
        }

        group.addEventListener('keydown', function (event) {
            if (select.disabled) { return; }
            var keys = ['ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp', 'Home', 'End'];
            if (keys.indexOf(event.key) === -1) { return; }
            event.preventDefault();

            var index = cards.indexOf(document.activeElement);
            if (index === -1) { index = 0; }

            if (event.key === 'Home') {
                index = 0;
            } else if (event.key === 'End') {
                index = cards.length - 1;
            } else if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                index = (index + 1) % cards.length;
            } else {
                index = (index - 1 + cards.length) % cards.length;
            }

            choose(cards[index].dataset.value, true);
        });

        // ---- required-field reporting -------------------------------
        var errorEl = null;

        function clearError() {
            if (errorEl) {
                errorEl.remove();
                errorEl = null;
            }
        }

        function showError(message) {
            group.classList.add('cc-invalid');
            if (!errorEl) {
                errorEl = document.createElement('p');
                errorEl.className = 'cc-error';
                errorEl.setAttribute('role', 'alert');
                group.insertAdjacentElement('afterend', errorEl);
            }
            errorEl.textContent = message;
        }

        select.addEventListener('invalid', function (event) {
            // The native bubble would point at a hidden control, so show the
            // message on the cards instead and put focus somewhere visible.
            event.preventDefault();
            showError(select.validationMessage || 'Please choose one.');
            group.scrollIntoView({ block: 'center', behavior: 'smooth' });
            if (cards.length) { cards[0].focus(); }
        });

        select.addEventListener('change', sync);

        if (typeof MutationObserver === 'function') {
            new MutationObserver(sync).observe(select, {
                attributes: true,
                attributeFilter: ['disabled']
            });
        }

        select.classList.add('cc-native');
        select.dataset.ccReady = 'true';
        select.insertAdjacentElement('afterend', group);
        sync();
    }

    function scan(root) {
        var scope = root && root.querySelectorAll ? root : document;
        Array.prototype.forEach.call(scope.querySelectorAll(SELECTOR), build);
        if (root && root.matches && root.matches(SELECTOR)) { build(root); }
    }

    function start() {
        scan(document);

        // Some forms add rows after load (extra crops on a parcel), so pick
        // up any marked select that appears later.
        if (typeof MutationObserver === 'function') {
            new MutationObserver(function (records) {
                records.forEach(function (record) {
                    Array.prototype.forEach.call(record.addedNodes, function (node) {
                        if (node.nodeType === 1) { scan(node); }
                    });
                });
            }).observe(document.documentElement, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }

    window.choiceCards = { scan: scan };
}());
