function resolveAppPath(path) {
    var pathname = window.location.pathname || "";
    var basePath = (pathname === "/scaims" || pathname.indexOf("/scaims/") === 0) ? "/scaims" : "";
    return path.charAt(0) === "/" ? basePath + path : path;
}

function togglePasswordVisibility(inputId, iconId) {
    var inputField = document.getElementById(inputId);
    var icon = document.getElementById(iconId);

    if (inputField.type === "password") {
        inputField.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        inputField.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}

function validateForm() {
    var username = document.getElementById("username").value;
    var password = document.getElementById("password").value;
    var confirmPassword = document.getElementById("confirmPassword").value;

    if (username === "") {
        alert("Please enter your full name.");
        return false;
    }

    var xhr = new XMLHttpRequest();
    xhr.open("POST", "checkUsername.php", false); // Synchronous request
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.send("username=" + encodeURIComponent(username));
    
    if (xhr.responseText.trim() === 'exists') {
        alert("Username already exists. Please choose a different username.");
        return false;
    }

    var passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_\-+={}[\]:;'"<>,.?\/\\|`~]).{8,}$/;
    if (!passwordPattern.test(password)) {
        alert("Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character.");
        return false;
    }

    if (password !== confirmPassword) {
        alert("Passwords do not match. Please try again.");
        return false;
    }

    return confirm("Account Successfully Created! Kindly remember your username and password to avoid any inconvenience.");
}

function confirmCancel() {
    if (confirm("Are you sure you do not want to proceed?")) {
        window.location.href = resolveAppPath("/index.html");
    }
}
