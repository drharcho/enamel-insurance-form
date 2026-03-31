/**
 * Enamel Insurance Form — Frontend JS
 * Vanilla JS, no jQuery dependency.
 */
(function () {
    'use strict';

    // -----------------------------------------------------------------------
    // Bail if our config object isn't available
    // -----------------------------------------------------------------------
    if (typeof enamelIF === 'undefined') {
        return;
    }

    var AJAX_URL = enamelIF.ajaxurl;
    var NONCE    = enamelIF.nonce;

    // -----------------------------------------------------------------------
    // DOM references (set after DOMContentLoaded)
    // -----------------------------------------------------------------------
    var form, locationSelect, comboboxWrapper, comboboxInput, comboboxToggle,
        comboboxList, insuranceHidden, submitBtn, btnLabel, btnSpinner,
        resultDiv, otherWrap, otherInput;

    // Combobox state
    var allInsurances    = [];   // full list from server
    var filteredInsurances = []; // currently filtered
    var highlightedIndex = -1;

    // -----------------------------------------------------------------------
    // Init
    // -----------------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', function () {
        form            = document.getElementById('enamel-insurance-form');
        locationSelect  = document.getElementById('enamel-location');
        comboboxWrapper = document.getElementById('insurance-combobox');
        comboboxInput   = document.getElementById('enamel-insurance-input');
        comboboxToggle  = comboboxWrapper ? comboboxWrapper.querySelector('.enamel-combobox-toggle') : null;
        comboboxList    = document.getElementById('enamel-insurance-listbox');
        insuranceHidden = document.getElementById('enamel-insurance-value');
        submitBtn       = document.getElementById('enamel-submit-btn');
        btnLabel        = submitBtn ? submitBtn.querySelector('.enamel-btn-label') : null;
        btnSpinner      = submitBtn ? submitBtn.querySelector('.enamel-btn-spinner') : null;
        resultDiv       = document.getElementById('enamel-form-result');
        otherWrap       = document.getElementById('enamel-other-insurance-wrap');
        otherInput      = document.getElementById('enamel-other-insurance');

        if (!form) { return; }

        bindEvents();
    });

    // -----------------------------------------------------------------------
    // Event binding
    // -----------------------------------------------------------------------
    function bindEvents() {
        // Location change
        if (locationSelect) {
            locationSelect.addEventListener('change', handleLocationChange);
        }

        // Combobox input — filter on keyup
        if (comboboxInput) {
            comboboxInput.addEventListener('keyup', handleComboboxKeyup);
            comboboxInput.addEventListener('keydown', handleComboboxKeydown);
            comboboxInput.addEventListener('focus', handleComboboxFocus);
            comboboxInput.addEventListener('input', handleComboboxInput);
        }

        // Toggle button
        if (comboboxToggle) {
            comboboxToggle.addEventListener('click', handleToggleClick);
        }

        // Form submit
        if (form) {
            form.addEventListener('submit', handleFormSubmit);
        }

        // Click outside closes combobox
        document.addEventListener('click', function (e) {
            if (comboboxWrapper && !comboboxWrapper.contains(e.target)) {
                closeCombobox();
            }
        });
    }

    // -----------------------------------------------------------------------
    // Location change → fetch insurances
    // -----------------------------------------------------------------------
    function handleLocationChange() {
        var location = locationSelect.value;

        // Reset combobox
        resetCombobox();

        if (!location) {
            disableCombobox('Select a location first\u2026');
            return;
        }

        setComboboxLoading(true);

        var body = new FormData();
        body.append('action',   'enamel_get_insurances');
        body.append('nonce',    NONCE);
        body.append('location', location);

        fetch(AJAX_URL, {
            method:      'POST',
            credentials: 'same-origin',
            body:        body,
        })
        .then(function (res) { return res.json(); })
        .then(function (json) {
            setComboboxLoading(false);

            if (!json.success) {
                showComboboxError(json.data && json.data.message ? json.data.message : 'Could not load insurance list.');
                return;
            }

            allInsurances = json.data.insurances || [];
            populateCombobox(allInsurances);
            enableCombobox();
        })
        .catch(function () {
            setComboboxLoading(false);
            showComboboxError('Network error. Please try again.');
        });
    }

    // -----------------------------------------------------------------------
    // Combobox helpers
    // -----------------------------------------------------------------------
    function disableCombobox(placeholder) {
        comboboxWrapper.classList.add('enamel-combobox--disabled');
        comboboxInput.disabled    = true;
        comboboxToggle.disabled   = true;
        comboboxInput.placeholder = placeholder || 'Select a location first\u2026';
        comboboxInput.value       = '';
        insuranceHidden.value     = '';
        closeCombobox();
    }

    function enableCombobox() {
        comboboxWrapper.classList.remove('enamel-combobox--disabled');
        comboboxInput.disabled    = false;
        comboboxToggle.disabled   = false;
        comboboxInput.placeholder = 'Type to search your insurance\u2026';
    }

    function resetCombobox() {
        allInsurances      = [];
        filteredInsurances = [];
        highlightedIndex   = -1;
        comboboxInput.value    = '';
        insuranceHidden.value  = '';
        comboboxList.innerHTML = '';
        closeCombobox();
        clearFieldError('enamel-insurance-error');
        hideOtherField();
    }

    function setComboboxLoading(isLoading) {
        if (isLoading) {
            comboboxWrapper.classList.add('enamel-combobox--disabled');
            comboboxInput.disabled  = true;
            comboboxToggle.disabled = true;
            comboboxInput.placeholder = 'Loading\u2026';

            comboboxList.innerHTML = '<li class="enamel-combobox-loading" aria-live="polite">Loading insurance list\u2026</li>';
            openCombobox();
        } else {
            comboboxList.innerHTML = '';
            closeCombobox();
        }
    }

    function showComboboxError(msg) {
        disableCombobox('Could not load insurance list');
        setFieldError('enamel-insurance-error', msg);
    }

    function populateCombobox(items) {
        comboboxList.innerHTML = '';
        filteredInsurances     = items;
        highlightedIndex       = -1;

        if (items.length === 0) {
            comboboxList.innerHTML = '<li class="no-results">No insurances found for this location.</li>';
            return;
        }

        items.forEach(function (name, index) {
            var li       = document.createElement('li');
            li.textContent = name;
            li.setAttribute('role',          'option');
            li.setAttribute('data-index',    index);
            li.setAttribute('data-value',    name);
            li.setAttribute('aria-selected', 'false');

            li.addEventListener('click', function () {
                selectInsurance(name);
            });

            li.addEventListener('mouseenter', function () {
                setHighlighted(index);
            });

            comboboxList.appendChild(li);
        });

        // Always append "Other" at the bottom
        var otherLi = document.createElement('li');
        otherLi.textContent = 'Other';
        otherLi.setAttribute('role', 'option');
        otherLi.setAttribute('aria-selected', 'false');
        otherLi.classList.add('enamel-other-option');
        otherLi.addEventListener('click', function () {
            selectInsurance('Other');
        });
        otherLi.addEventListener('mouseenter', function () {
            setHighlighted(items.length);
        });
        comboboxList.appendChild(otherLi);
    }

    function selectInsurance(name) {
        comboboxInput.value   = name;
        insuranceHidden.value = name;
        comboboxInput.setAttribute('aria-expanded', 'false');
        clearFieldError('enamel-insurance-error');
        closeCombobox();

        if (name === 'Other') {
            showOtherField();
        } else {
            hideOtherField();
        }
    }

    function showOtherField() {
        if (otherWrap) {
            otherWrap.style.display = 'block';
            if (otherInput) { otherInput.focus(); }
        }
    }

    function hideOtherField() {
        if (otherWrap) {
            otherWrap.style.display = 'none';
            if (otherInput) { otherInput.value = ''; }
            clearFieldError('enamel-other-insurance-error');
        }
    }

    function openCombobox() {
        if (comboboxList.children.length === 0) { return; }
        comboboxList.style.display = 'block';
        comboboxInput.setAttribute('aria-expanded', 'true');
        comboboxToggle.classList.add('open');
    }

    function closeCombobox() {
        comboboxList.style.display = 'none';
        comboboxInput.setAttribute('aria-expanded', 'false');
        if (comboboxToggle) { comboboxToggle.classList.remove('open'); }
        highlightedIndex = -1;
        removeAllHighlights();
    }

    function handleComboboxFocus() {
        if (allInsurances.length > 0) {
            filterCombobox(comboboxInput.value);
            openCombobox();
        }
    }

    function handleComboboxInput() {
        // Clear hidden value when user edits text
        insuranceHidden.value = '';
    }

    function handleComboboxKeyup(e) {
        // Ignore navigation keys handled in keydown
        var ignored = [
            'ArrowDown', 'ArrowUp', 'Enter', 'Escape',
            'Tab', 'Shift', 'Control', 'Alt', 'Meta'
        ];
        if (ignored.indexOf(e.key) !== -1) { return; }

        filterCombobox(comboboxInput.value);

        if (filteredInsurances.length > 0) {
            openCombobox();
        }
    }

    function handleComboboxKeydown(e) {
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                openCombobox();
                moveHighlight(1);
                break;

            case 'ArrowUp':
                e.preventDefault();
                moveHighlight(-1);
                break;

            case 'Enter':
                e.preventDefault();
                if (highlightedIndex >= 0 && filteredInsurances[highlightedIndex]) {
                    selectInsurance(filteredInsurances[highlightedIndex]);
                }
                break;

            case 'Escape':
                closeCombobox();
                break;

            default:
                break;
        }
    }

    function handleToggleClick() {
        if (comboboxInput.disabled) { return; }

        if (comboboxList.style.display === 'none') {
            filterCombobox(comboboxInput.value);
            openCombobox();
            comboboxInput.focus();
        } else {
            closeCombobox();
        }
    }

    function filterCombobox(query) {
        var q = query.trim().toLowerCase();
        filteredInsurances = q
            ? allInsurances.filter(function (name) {
                  return name.toLowerCase().indexOf(q) !== -1;
              })
            : allInsurances.slice();

        populateCombobox(filteredInsurances);
    }

    function moveHighlight(direction) {
        var items = comboboxList.querySelectorAll('li:not(.no-results):not(.enamel-combobox-loading)');
        if (items.length === 0) { return; }

        var next = highlightedIndex + direction;
        if (next < 0)             { next = items.length - 1; }
        if (next >= items.length) { next = 0; }

        setHighlighted(next);

        // Scroll into view
        if (items[next]) {
            items[next].scrollIntoView({ block: 'nearest' });
        }
    }

    function setHighlighted(index) {
        removeAllHighlights();
        highlightedIndex = index;

        var items = comboboxList.querySelectorAll('li:not(.no-results):not(.enamel-combobox-loading)');
        if (items[index]) {
            items[index].classList.add('highlighted');
            items[index].setAttribute('aria-selected', 'true');
        }
    }

    function removeAllHighlights() {
        var items = comboboxList.querySelectorAll('li');
        items.forEach(function (li) {
            li.classList.remove('highlighted');
            li.setAttribute('aria-selected', 'false');
        });
    }

    // -----------------------------------------------------------------------
    // Form submission
    // -----------------------------------------------------------------------
    function handleFormSubmit(e) {
        e.preventDefault();

        clearAllErrors();

        var name      = document.getElementById('enamel-name')       ? document.getElementById('enamel-name').value.trim()       : '';
        var phone     = document.getElementById('enamel-phone')      ? document.getElementById('enamel-phone').value.trim()      : '';
        var email     = document.getElementById('enamel-email')      ? document.getElementById('enamel-email').value.trim()      : '';
        var location  = locationSelect ? locationSelect.value.trim() : '';
        var insurance = insuranceHidden ? insuranceHidden.value.trim() : '';

        var valid = true;

        if (!name) {
            setFieldError('enamel-name-error', 'Please enter your full name.');
            setInputError('enamel-name');
            valid = false;
        }

        if (!phone) {
            setFieldError('enamel-phone-error', 'Please enter your phone number.');
            setInputError('enamel-phone');
            valid = false;
        } else if (!isValidPhone(phone)) {
            setFieldError('enamel-phone-error', 'Please enter a valid phone number (e.g. 512-555-0100).');
            setInputError('enamel-phone');
            valid = false;
        }

        if (!email) {
            setFieldError('enamel-email-error', 'Please enter your email address.');
            setInputError('enamel-email');
            valid = false;
        } else if (!isValidEmail(email)) {
            setFieldError('enamel-email-error', 'Please enter a valid email address.');
            setInputError('enamel-email');
            valid = false;
        }

        if (!location) {
            setFieldError('enamel-location-error', 'Please select a location.');
            setInputError('enamel-location');
            valid = false;
        }

        if (!insurance) {
            setFieldError('enamel-insurance-error', 'Please select your insurance provider.');
            valid = false;
        }

        var insuranceOther = '';
        if (insurance === 'Other') {
            insuranceOther = otherInput ? otherInput.value.trim() : '';
            if (!insuranceOther) {
                setFieldError('enamel-other-insurance-error', 'Please describe your insurance plan.');
                if (otherInput) { otherInput.classList.add('has-error'); }
                valid = false;
            }
        }

        if (!valid) { return; }

        setLoadingState(true);

        var body = new FormData();
        body.append('action',           'enamel_submit_form');
        body.append('nonce',            NONCE);
        body.append('name',             name);
        body.append('phone',            phone);
        body.append('email',            email);
        body.append('location',         location);
        body.append('insurance',        insurance);
        body.append('insurance_other',  insuranceOther);

        fetch(AJAX_URL, {
            method:      'POST',
            credentials: 'same-origin',
            body:        body,
        })
        .then(function (res) { return res.json(); })
        .then(function (json) {
            setLoadingState(false);

            if (!json.success) {
                var msg = (json.data && json.data.message) ? json.data.message : 'Something went wrong. Please try again.';
                setFieldError('enamel-submit-error', msg);
                return;
            }

            var accepted   = json.data.accepted;
            var isOther    = json.data.other || false;
            var message    = json.data.message;
            var bookingUrl = json.data.booking_url || '';
            var phone      = json.data.phone || '';

            showResult(accepted, isOther, message, bookingUrl, phone);
        })
        .catch(function () {
            setLoadingState(false);
            setFieldError('enamel-submit-error', 'A network error occurred. Please check your connection and try again.');
        });
    }

    // -----------------------------------------------------------------------
    // Result display
    // -----------------------------------------------------------------------
    function showResult(accepted, isOther, message, bookingUrl, phone) {
        form.style.display = 'none';

        var icon, heading, cssClass;
        if (accepted) {
            icon     = '\u2713';
            heading  = 'You\u2019re covered!';
            cssClass = 'accepted';
        } else if (isOther) {
            icon     = '\u2709';
            heading  = 'We\u2019ll be in touch!';
            cssClass = 'not-accepted';
        } else {
            icon     = '\u2139';
            heading  = 'We\u2019ll help you out';
            cssClass = 'not-accepted';
        }

        var buttonsHtml = '';
        if (accepted || isOther) {
            if (bookingUrl) {
                buttonsHtml += '<a href="' + escapeHtml(bookingUrl) + '" class="enamel-action-btn enamel-book-btn" target="_blank" rel="noopener">Book Online</a>';
            }
            if (phone) {
                buttonsHtml += '<a href="tel:' + escapeHtml(phone.replace(/[^\d\+]/g, '')) + '" class="enamel-action-btn enamel-call-btn">Call Now</a>';
            }
            if (buttonsHtml) {
                buttonsHtml = '<div class="enamel-result-actions">' + buttonsHtml + '</div>';
            }
        }

        resultDiv.className = 'enamel-result ' + cssClass;
        resultDiv.innerHTML =
            '<span class="enamel-result-icon" aria-hidden="true">' + icon + '</span>' +
            '<h3>' + escapeHtml(heading) + '</h3>' +
            '<p>' + escapeHtml(message) + '</p>' +
            buttonsHtml;

        resultDiv.style.display = 'block';
        resultDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // -----------------------------------------------------------------------
    // Validation helpers
    // -----------------------------------------------------------------------
    function isValidPhone(phone) {
        // Accept digits, spaces, dashes, dots, parens, leading +
        var stripped = phone.replace(/[\s\-\.\(\)\+]/g, '');
        return /^\d{7,15}$/.test(stripped);
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
    }

    // -----------------------------------------------------------------------
    // UI state helpers
    // -----------------------------------------------------------------------
    function setLoadingState(loading) {
        if (!submitBtn) { return; }

        submitBtn.disabled = loading;

        if (btnLabel)   { btnLabel.style.display   = loading ? 'none'         : ''; }
        if (btnSpinner) { btnSpinner.style.display  = loading ? 'inline-block' : 'none'; }
    }

    function setFieldError(errorId, message) {
        var el = document.getElementById(errorId);
        if (el) { el.textContent = message; }
    }

    function clearFieldError(errorId) {
        var el = document.getElementById(errorId);
        if (el) { el.textContent = ''; }
    }

    function setInputError(inputId) {
        var el = document.getElementById(inputId);
        if (el) { el.classList.add('has-error'); }
    }

    function clearInputError(inputId) {
        var el = document.getElementById(inputId);
        if (el) { el.classList.remove('has-error'); }
    }

    function clearAllErrors() {
        var errorIds = [
            'enamel-name-error',
            'enamel-phone-error',
            'enamel-email-error',
            'enamel-location-error',
            'enamel-insurance-error',
            'enamel-submit-error',
        ];
        var inputIds = ['enamel-name', 'enamel-phone', 'enamel-email', 'enamel-location'];

        errorIds.forEach(clearFieldError);
        inputIds.forEach(clearInputError);
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g,  '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#039;');
    }

})();
