document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editProfileForm');
    const profileInput = document.getElementById('profileImage');
    const profilePreview = document.getElementById('profilePreview');
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    const currentPassword = document.getElementById('currentPassword');
    const username = document.getElementById('username');
    const originalUsername = username.value;

    function setText(selector, key) {
        const element = document.querySelector(selector);
        if (element) {
            element.textContent = window.dashboardI18n.t(key);
        }
    }

    function applyProfileLanguage() {
        setText('.file-input-label', 'profile_image_button');
        setText('.account-section h3', 'profile_account_info');
        setText('label[for="username"]', 'profile_username');
        setText('label[for="currentPassword"]', 'profile_current_password');
        setText('label[for="newPassword"]', 'profile_new_password');
        setText('label[for="confirmPassword"]', 'profile_confirm_password');
        setText('.password-hint', 'profile_password_hint');
        setText('label[for="surname"]', 'profile_surname');
        setText('label[for="firstName"]', 'profile_first_name');
        setText('label[for="middleName"]', 'profile_middle_name');
        setText('label[for="extensionName"]', 'profile_extension_name');
        setText('label[for="dob"]', 'profile_birth_date');
        setText('label[for="sex"]', 'profile_sex');
        setText('label[for="contactNumber"]', 'profile_contact_number');
        setText('label[for="address"]', 'profile_address');
        setText('label[for="barangay"]', 'profile_barangay');
        setText('label[for="rsbsaNumber"]', 'profile_rsbsa');

        const buttons = document.querySelectorAll('.button-container button');
        if (buttons[0]) {
            buttons[0].textContent = window.dashboardI18n.t('profile_save');
        }
        if (buttons[1]) {
            buttons[1].textContent = window.dashboardI18n.t('profile_cancel');
        }
    }

    // Preview image before upload
    profileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                profilePreview.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    });

    // Add this password validation function
    function isStrongPassword(password) {
        // At least 8 characters long
        // Contains at least one uppercase letter
        // Contains at least one lowercase letter
        // Contains at least one number
        // Contains at least one special character
        const strongPasswordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?])[A-Za-z\d!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]{8,}$/;
        return strongPasswordRegex.test(password);
    }

    // Update the validatePasswords function
    function validatePasswords() {
        if (newPassword.value || confirmPassword.value) {
            if (!currentPassword.value) {
                alert('Please enter your current password to change password');
                currentPassword.focus();
                return false;
            }
            if (!isStrongPassword(newPassword.value)) {
                alert('Password must be at least 8 characters long and contain:\n' +
                      '- At least one uppercase letter\n' +
                      '- At least one lowercase letter\n' +
                      '- At least one number\n' +
                      '- At least one special character');
                newPassword.focus();
                return false;
            }
            if (newPassword.value !== confirmPassword.value) {
                alert('New passwords do not match');
                confirmPassword.focus();
                return false;
            }
        }
        return true;
    }

    // Username validation
    function validateUsername() {
        if (username.value !== originalUsername && !currentPassword.value) {
            alert('Please enter your current password to change username');
            currentPassword.focus();
            return false;
        }
        return true;
    }

    // Add these validation functions
    function validateBirthDate() {
        const dob = document.getElementById('dob');
        const today = new Date();
        const selectedDate = new Date(dob.value);
        
        // Reset time components to midnight for accurate date comparison
        today.setHours(0, 0, 0, 0);
        selectedDate.setHours(0, 0, 0, 0);

        if (selectedDate >= today) {
            alert('Birth date cannot be today or a future date');
            dob.focus();
            return false;
        }
        return true;
    }

    function validateContactNumber() {
        const contactNumber = document.getElementById('contactNumber');
        const phoneRegex = /^09\d{9}$/;  // Matches "09" followed by 9 digits

        if (!phoneRegex.test(contactNumber.value)) {
            alert('Contact number must start with 09 and contain 11 digits');
            contactNumber.focus();
            return false;
        }
        return true;
    }

    // Add these new functions
    function validateRSBSANumber() {
        const rsbsaNumber = document.getElementById('rsbsaNumber');
        const rsbsaValue = rsbsaNumber.value.trim();
        const originalRSBSA = rsbsaNumber.getAttribute('data-original');
        
        // If empty, return true as it's optional
        if (!rsbsaValue) {
            return true;
        }
        
        // If value hasn't changed from original, return true
        if (rsbsaValue === originalRSBSA) {
            return true;
        }
        
        // Check format: XX-XX-XX-XXX-XXXXXX (where X are numbers)
        const rsbsaFormat = /^\d{2}-\d{2}-\d{2}-\d{3}-\d{6}$/;
        
        if (!rsbsaFormat.test(rsbsaValue)) {
            alert('RSBSA Number should follow the format: XX-XX-XX-XXX-XXXXXX\nExample: 12-34-56-789-012345');
            rsbsaNumber.focus();
            return false;
        }
        
        return true;
    }

    // Add auto-formatting for RSBSA number
    const rsbsaInput = document.getElementById('rsbsaNumber');
    if (rsbsaInput) {
        // Store original value as data attribute
        rsbsaInput.setAttribute('data-original', rsbsaInput.value);
        
        rsbsaInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
            let formattedValue = '';
            
            if (value.length > 0) formattedValue += value.substr(0,2);
            if (value.length > 2) formattedValue += '-' + value.substr(2,2);
            if (value.length > 4) formattedValue += '-' + value.substr(4,2);
            if (value.length > 6) formattedValue += '-' + value.substr(6,3);
            if (value.length > 9) formattedValue += '-' + value.substr(9,6);
            
            e.target.value = formattedValue;
        });
    }

    // Handle form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        if (!validatePasswords() || !validateUsername() || !validateBirthDate() || !validateContactNumber() || !validateRSBSANumber()) {
            return;
        }

        // Check RSBSA uniqueness if changed
        const rsbsaNumber = document.getElementById('rsbsaNumber');
        const originalRSBSA = rsbsaNumber.getAttribute('data-original');
        const newRSBSA = rsbsaNumber.value.trim();

        if (newRSBSA && newRSBSA !== originalRSBSA) {
            checkRSBSAUniqueness(newRSBSA, function(isUnique) {
                if (isUnique) {
                    proceedWithSubmission(e, form);
                } else {
                    alert('This RSBSA Number is already registered in the system.');
                }
            });
        } else {
            proceedWithSubmission(e, form);
        }
    });

    // Add event listeners for password visibility toggles
    document.querySelectorAll('.show-password-icon').forEach(icon => {
        icon.addEventListener('click', function() {
            const inputId = this.previousElementSibling.id;
            const iconId = this.querySelector('i').id;
            togglePasswordVisibility(inputId, iconId);
        });
    });

    // Update the password visibility toggle function
    function togglePasswordVisibility(inputId, iconId) {
        const inputField = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        
        if (inputField.type === "password") {
            inputField.type = "text";
            icon.classList.remove("bi-eye");
            icon.classList.add("bi-eye-slash");
        } else {
            inputField.type = "password";
            icon.classList.remove("bi-eye-slash");
            icon.classList.add("bi-eye");
        }
    }

    // Add cancel button handler
    document.querySelector('button[type="button"]').addEventListener('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure you do not want to proceed?')) {
            window.location.href = '../home/home.php';
        }
    });

    applyProfileLanguage();
    document.addEventListener('dashboard:language-changed', applyProfileLanguage);
});

function checkRSBSAUniqueness(rsbsaNumber, callback) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'check_rsbsa.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            console.log('RSBSA check response:', xhr.responseText); // Debug log
            const response = xhr.responseText.trim();
            if (response === 'unique') {
                callback(true);
            } else if (response === 'duplicate') {
                callback(false);
            } else {
                console.error('Unexpected response:', response);
                alert('Error checking RSBSA number. Please try again.');
                callback(false);
            }
        } else {
            console.error('Server error:', xhr.status);
            alert('Error checking RSBSA number. Please try again.');
            callback(false);
        }
    };
    
    xhr.send('rsbsa_number=' + encodeURIComponent(rsbsaNumber));
}

function proceedWithSubmission(event, form) {
    const formData = new FormData(form); // Create a FormData object from the form
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'updateProfile.php', true); // Change to your actual update script

    xhr.onload = function() {
        if (xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            if (response.success) {
                alert('Profile updated successfully!');
                // Optionally, redirect or refresh the page
                window.location.reload(); // Reload the page to reflect changes
            } else {
                alert('Error updating profile: ' + response.message);
            }
        } else {
            alert('An error occurred while updating the profile. Please try again.');
        }
    };

    xhr.send(formData); // Send the form data
}
