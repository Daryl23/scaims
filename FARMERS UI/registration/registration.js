const resolveRegistrationPath = typeof window.resolveAppPath === 'function'
    ? window.resolveAppPath
    : function(path) { return path; };

const homePath = resolveRegistrationPath('/index.html');
const accountCreationPath = resolveRegistrationPath('/FARMERS%20UI/accountCreation/accountCreation.html');

const header = document.getElementById('header');
if (header) {
    header.onclick = function() {
        window.location.href = homePath;
    };
}

function clearFormAndRedirect() {
    const confirmation = confirm("Are you sure you want to clear the form and cancel the registration?");
    if (confirmation) {
        document.getElementById('registrationForm').reset();
        window.location.href = homePath;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded'); // Debug log
    
    const form = document.getElementById('registrationForm');
    if (form) {
        console.log('Form found'); // Debug log
        
        // Remove any existing event listeners
        form.removeEventListener('submit', handleFormSubmit);
        // Add new event listener
        form.addEventListener('submit', handleFormSubmit);
    } else {
        console.error('Registration form not found!');
    }

    // Add input event listeners to all required fields
    const requiredFields = document.querySelectorAll('input[required], select[required]');
    requiredFields.forEach(field => {
        field.addEventListener('input', function() {
            validateField(this);
        });
        field.addEventListener('change', function() {
            validateField(this);
        });
    });
});

function handleFormSubmit(event) {
    console.log('Form submission attempted');
    event.preventDefault();
    
    if (!validateForm() || !validateRSBSANumber()) {
        console.log('Validation failed');
        return false;
    }
    
    // Check RSBSA uniqueness before proceeding
    const rsbsaNumber = document.getElementById('rsbsaNumber').value.trim();
    if (rsbsaNumber) {
        checkRSBSAUniqueness(rsbsaNumber, function(isUnique) {
            if (isUnique) {
                proceedWithSubmission();
            } else {
                alert('This RSBSA Number is already registered in the system.');
            }
        });
    } else {
        proceedWithSubmission();
    }
}

function submitForm(formData) {
    console.log('Submitting form...'); // Debug log
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'submit_registration.php', true);
    
    xhr.onload = function() {
        console.log('Response received:', xhr.responseText); // Debug log
        if (xhr.status === 200) {
            if (xhr.responseText === "success") {
                alert("Registration successful! Redirecting...");
                window.location.href = accountCreationPath;
            } else {
                alert('Error submitting form. Please try again.');
            }
        } else {
            alert('Error submitting form. Please try again.');
        }
    };
    
    xhr.onerror = function() {
        console.error('XHR error:', xhr.statusText); // Debug log
        alert('Error submitting form. Please try again.');
    };
    
    xhr.send(formData);
}

function validateForm() {
    console.log('Starting form validation'); // Debug log
    
    // Name fields validation
    const nameFields = [
        { id: 'surname', label: 'Surname' },
        { id: 'firstName', label: 'First Name' },
        { id: 'middleName', label: 'Middle Name' }
    ];

    const nameRegex = /^[A-Za-z\s]+$/;

    for (const field of nameFields) {
        const element = document.getElementById(field.id);
        console.log('Validating', field.id); // Debug log
        
        if (element) {
            const value = element.value.trim();
            console.log(field.id, 'value:', value); // Debug log
            
            // Skip validation for middle name if it's empty (since it's optional)
            if (field.id === 'middleName' && value === '') {
                continue;
            }

            if (!nameRegex.test(value)) {
                alert('Please enter a valid ' + field.label.toLowerCase() + ' (letters and spaces only).');
                element.focus();
                return false;
            }
        }
    }

    // Contact number validation
    const contactNumber = document.getElementById('contactNumber');
    if (contactNumber) {
        const contactValue = contactNumber.value.trim();
        const philippineNumberRegex = /^09\d{9}$/;
        
        if (!philippineNumberRegex.test(contactValue)) {
            alert('Please enter a valid Philippine mobile number starting with 09 (11 digits total).');
            contactNumber.focus();
            return false;
        }
    }

    // Date validation
    const dob = document.getElementById('dob');
    if (dob) {
        const birthDate = new Date(dob.value);
        const today = new Date();
        
        // Reset time portions for accurate date comparison
        birthDate.setHours(0, 0, 0, 0);
        today.setHours(0, 0, 0, 0);
        
        if (isNaN(birthDate.getTime())) {
            alert('Please enter a valid birth date.');
            dob.focus();
            return false;
        }
        
        if (birthDate >= today) {
            alert('Birth date cannot be today or in the future.');
            dob.focus();
            return false;
        }
    }

    // Required fields validation
    const requiredFields = ['surname', 'firstName', 'sex', 'address', 'barangay'];
    for (const fieldId of requiredFields) {
        const field = document.getElementById(fieldId);
        if (!field || !field.value.trim()) {
            alert(`Please fill out ${fieldId.replace(/([A-Z])/g, ' $1').toLowerCase()}.`);
            if (field) field.focus();
            return false;
        }
    }

    // Terms checkbox validation
    const termsCheckbox = document.getElementById('terms');
    if (!termsCheckbox || !termsCheckbox.checked) {
        alert('Please agree to the Terms of Service and Privacy Policy.');
        if (termsCheckbox) termsCheckbox.focus();
        return false;
    }

    console.log('All validations passed'); // Debug log
    return true;
}

function confirmSubmission() {
    return confirm("Are you sure you want to proceed with the registration?");
}

// Add this new validation function
function validateRSBSANumber() {
    const rsbsaNumber = document.getElementById('rsbsaNumber');
    const rsbsaValue = rsbsaNumber.value.trim();
    
    // If empty, return true as it's optional
    if (!rsbsaValue) {
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

// Add auto-formatting as user types
const rsbsaInput = document.getElementById('rsbsaNumber');
if (rsbsaInput) {
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

function checkRSBSAUniqueness(rsbsaNumber, callback) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'check_rsbsa.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            console.log('RSBSA check response:', xhr.responseText); // Debug log
            const response = xhr.responseText.trim(); // Trim any whitespace
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
    
    xhr.onerror = function() {
        console.error('Request failed');
        alert('Error checking RSBSA number. Please try again.');
        callback(false);
    };
    
    xhr.send('rsbsa_number=' + encodeURIComponent(rsbsaNumber));
}

function proceedWithSubmission() {
    if (confirmSubmission()) {
        console.log('Submission confirmed');
        const formData = new FormData(document.getElementById('registrationForm'));
        submitForm(formData);
    } else {
        console.log('Submission cancelled by user');
    }
}

function validateField(field) {
    if (field.value.trim() !== '') {
        // For select elements
        if (field.tagName === 'SELECT') {
            if (field.value !== '') {
                field.style.border = '1px solid #674636';
            }
        } 
        // For text inputs
        else {
            field.style.border = '1px solid #674636';
        }
    } else {
        field.style.border = '2px solid red';
    }
}
