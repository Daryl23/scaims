document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const form = document.getElementById('registrationForm');
    const personalStep = document.getElementById('personalStep');
    const accountStep = document.getElementById('accountStep');
    const nextButton = document.getElementById('nextButton');
    const createButton = document.getElementById('createAccountButton');
    const backButton = document.getElementById('backButton');
    const cancelButton = document.getElementById('cancelButton');
    const error = document.getElementById('registrationError');
    let currentStep = 1;
    let busy = false;

    form.querySelectorAll('[required]').forEach(function (field) {
        const label = form.querySelector('label[for="' + field.id + '"]');
        if (label) {
            const marker = document.createElement('span');
            marker.className = 'required';
            marker.setAttribute('aria-hidden', 'true');
            marker.textContent = ' *';
            label.appendChild(marker);
        }
    });

    function showError(message) {
        error.textContent = message;
        error.hidden = !message;
        if (message) error.scrollIntoView({ block: 'center' });
    }

    function setBusy(value) {
        busy = value;
        [nextButton, createButton, backButton, cancelButton].forEach(button => { button.disabled = value; });
        form.setAttribute('aria-busy', String(value));
        nextButton.textContent = value ? 'Checking...' : 'Next: Account Creation';
        createButton.textContent = value ? 'Creating Account...' : 'Create Account';
    }

    function showStep(step) {
        currentStep = step;
        personalStep.hidden = step !== 1;
        accountStep.hidden = step !== 2;
        // Hidden controls must not block validation of the visible step.
        personalStep.disabled = step !== 1;
        accountStep.disabled = step !== 2;
        nextButton.hidden = step !== 1;
        createButton.hidden = step !== 2;
        backButton.hidden = step !== 2;
        document.getElementById('personalProgress').classList.toggle('is-complete', step === 2);
        ['personalProgress', 'accountProgress'].forEach(function (id, index) {
            const item = document.getElementById(id);
            if (index + 1 === step) item.setAttribute('aria-current', 'step');
            else item.removeAttribute('aria-current');
        });
        showError('');
        const heading = (step === 1 ? personalStep : accountStep).querySelector('legend');
        heading.focus({ preventScroll: true });
        form.scrollIntoView({ block: 'start' });
    }

    function validatePersonal() {
        ['surname', 'firstName', 'middleName'].forEach(function (id) {
            const field = document.getElementById(id);
            field.setCustomValidity(field.value.trim() && !/^[A-Za-z\s]+$/.test(field.value.trim())
                ? 'Please use letters and spaces only.' : '');
        });
        const contact = document.getElementById('contactNumber');
        contact.setCustomValidity(contact.value && !/^09\d{9}$/.test(contact.value.trim())
            ? 'Enter an 11-digit Philippine mobile number starting with 09.' : '');
        const dob = document.getElementById('dob');
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        dob.setCustomValidity(dob.value && new Date(dob.value + 'T00:00:00') >= today
            ? 'Birth date must be before today.' : '');
        const rsbsa = document.getElementById('rsbsaNumber');
        rsbsa.setCustomValidity(rsbsa.value && !/^\d{2}-\d{2}-\d{2}-\d{3}-\d{6}$/.test(rsbsa.value.trim())
            ? 'Use the RSBSA format XX-XX-XX-XXX-XXXXXX.' : '');
        ['surname', 'firstName', 'address'].forEach(function (id) {
            const field = document.getElementById(id);
            if (!field.value.trim()) field.setCustomValidity('Please fill out this field.');
        });
        personalStep.classList.add('was-validated');
        return form.reportValidity();
    }

    function validateAccount() {
        const username = document.getElementById('username');
        username.setCustomValidity(username.value.trim() ? '' : 'Please enter your username.');
        const password = document.getElementById('password');
        const strongPassword = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_\-+={}\[\]:;'"<>,.?/\\|`~]).{8,}$/;
        password.setCustomValidity(strongPassword.test(password.value.trim()) ? ''
            : 'Use at least 8 characters with uppercase, lowercase, a number, and a special character.');
        const confirmation = document.getElementById('confirmPassword');
        confirmation.setCustomValidity(confirmation.value === password.value ? '' : 'Passwords do not match.');
        accountStep.classList.add('was-validated');
        return form.reportValidity();
    }

    form.addEventListener('input', function (event) {
        if (typeof event.target.setCustomValidity === 'function') event.target.setCustomValidity('');
    });
    form.querySelectorAll('[data-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            const field = document.getElementById(button.dataset.password);
            const visible = field.type === 'password';
            field.type = visible ? 'text' : 'password';
            button.textContent = visible ? 'Hide' : 'Show';
            button.setAttribute('aria-pressed', String(visible));
            button.setAttribute('aria-label', (visible ? 'Hide ' : 'Show ') + (field.id === 'password' ? 'password' : 'confirm password'));
        });
    });
    document.getElementById('rsbsaNumber').addEventListener('input', function (event) {
        const digits = event.target.value.replace(/\D/g, '').slice(0, 15);
        event.target.value = [digits.slice(0, 2), digits.slice(2, 4), digits.slice(4, 6), digits.slice(6, 9), digits.slice(9)]
            .filter(Boolean).join('-');
    });

    backButton.addEventListener('click', function () { if (!busy) showStep(1); });
    cancelButton.addEventListener('click', function () {
        if (busy || !window.confirm('Are you sure you want to cancel registration? All information entered in both parts will be discarded.')) return;
        form.reset();
        window.location.href = window.resolveAppPath('/index.html');
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (busy) return;
        showError('');
        if (currentStep === 1) {
            if (!validatePersonal()) return;
            setBusy(true);
            try {
                const response = await fetch('check_rsbsa.php', {
                    method: 'POST',
                    body: new URLSearchParams({ rsbsa_number: document.getElementById('rsbsaNumber').value.trim() })
                });
                const result = (await response.text()).trim();
                if (!response.ok || !['unique', 'duplicate'].includes(result)) throw new Error('Unable to check your RSBSA number. Please try again.');
                if (result === 'duplicate') throw new Error('This RSBSA Number is already registered in the system.');
                showStep(2);
            } catch (failure) {
                showError(failure.message || 'Unable to continue. Please try again.');
            } finally { setBusy(false); }
            return;
        }
        if (!validateAccount()) return;
        // Include both steps in the final request without erasing values or files.
        personalStep.disabled = false;
        const payload = new FormData(form);
        personalStep.disabled = true;
        setBusy(true);
        try {
            const response = await fetch(form.action, { method: 'POST', body: payload });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Unable to create your account. Please try again.');
            form.reset();
            window.alert('Account successfully created! You can now log in with your username and password.');
            window.location.replace(window.resolveAppPath('/FARMERS%20UI/farmerLogin/farmerlogin.html'));
        } catch (failure) {
            showError(failure.message || 'Unable to create your account. Please try again.');
        } finally { setBusy(false); }
    });
});
