
$(document).ready(function () {
    createEditModal();
    createPinModal();
    
    let newCurrentPage = 1;
    let oldCurrentPage = 1;
    const usersPerPage = 10;

    function updatePagination(section, currentPage, totalPages) {
        const prevButton = $(`#${section}-prev-page`);
        const nextButton = $(`#${section}-next-page`);
        const pageDisplay = $(`#${section}-current-page`);

        if (prevButton.length && nextButton.length && pageDisplay.length) {
            prevButton.prop('disabled', currentPage <= 1);
            nextButton.prop('disabled', currentPage >= totalPages);
            pageDisplay.text(`Page ${currentPage} of ${totalPages}`);
        }
    }

    function loadUsers(filter, barangay, rsbsa) {
        if (filter === "Farmer") {
            $('#table-header').html(`
                <th>Name</th>
                <th>Contact Number</th>
                <th>Barangay</th>
                <th>Registration Date</th>
                <th>Status</th>
                <th>Action</th>
            `);
        } else {
            $('#table-header').html(`
                <th>Name</th>
                <th>Status</th>
                <th>Action</th>
            `);
        }

        $.ajax({
            url: 'fetchUsers.php',
            type: 'POST',
            data: { 
                filter: filter, 
                barangay: barangay, 
                rsbsa: rsbsa,
                search: $('#search-bar').val(),
                newPage: newCurrentPage,
                oldPage: oldCurrentPage
            },
            success: function (response) {
                try {
                    const data = JSON.parse(response);
                    $('#user-table-body').html(data.output);

                    if (filter === 'Farmer') {
                        // Update pagination for both sections
                        if (data.newCount > 0) {
                            updatePagination('new', data.newCurrentPage, data.newTotalPages);
                        }
                        if (data.oldCount > 0) {
                            updatePagination('old', data.oldCurrentPage, data.oldTotalPages);
                        }
                        $('#user-count').html(`Total Users: ${data.total} (${data.newCount} new, ${data.oldCount} previous)`);
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to load users:', error);
                alert('Failed to load users. Please try again.');
            }
        });
    }

    function fetchUsers() {
        newCurrentPage = 1;
        oldCurrentPage = 1;
        loadUsers($('#user-filter').val(), $('#barangay-filter').val(), $('#rsbsa-filter').val());
    }

    // Load initial data
    fetchUsers();

    // Event listeners for filter, search, and pagination
    $('#user-filter, #barangay-filter, #rsbsa-filter').on('change', function () {
        fetchUsers();
    });

    $('#search-bar').on('input', function () {
        fetchUsers();
    });

    $('#search-bar').on('keypress', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            fetchUsers();
        }
    });

    // Replace the direct button click handlers with event delegation
    $(document).on('click', '#new-next-page', function () {
        newCurrentPage++;
        loadUsers($('#user-filter').val(), $('#barangay-filter').val(), $('#rsbsa-filter').val());
    });

    $(document).on('click', '#new-prev-page', function () {
        newCurrentPage--;
        loadUsers($('#user-filter').val(), $('#barangay-filter').val(), $('#rsbsa-filter').val());
    });

    $(document).on('click', '#old-next-page', function () {
        oldCurrentPage++;
        loadUsers($('#user-filter').val(), $('#barangay-filter').val(), $('#rsbsa-filter').val());
    });

    $(document).on('click', '#old-prev-page', function () {
        oldCurrentPage--;
        loadUsers($('#user-filter').val(), $('#barangay-filter').val(), $('#rsbsa-filter').val());
    });

    $('#export-button').on('click', function() {
        const filter = $('#user-filter').val();
        const barangay = $('#barangay-filter').val();
        const search = $('#search-bar').val();

        // Create the URL with query parameters
        const url = `exportFarmers.php?filter=${encodeURIComponent(filter)}&barangay=${encodeURIComponent(barangay)}&search=${encodeURIComponent(search)}`;

        // Redirect to the export URL
        window.location.href = url;
    });
    
    $(document).on('click', '.edit-btn', function () {
        const farmerId = $(this).data('id');
        // Store farmerId for later use
        $('#pin-form').data('farmerId', farmerId);
        // Show PIN modal first
        $('.pin-modal-overlay').fadeIn();
    });

    // Add PIN verification handler
    $('#pin-form').on('submit', function(e) {
        e.preventDefault();
        const pin = $('#admin-pin').val();
        const farmerId = $(this).data('farmerId');

        $.ajax({
            url: 'verifyPin.php',
            type: 'POST',
            data: { pin: pin },
            success: function(response) {
                const data = JSON.parse(response);
                if (data.success) {
                    $('.pin-modal-overlay').fadeOut();
                    $('#admin-pin').val(''); // Clear PIN
                    // Proceed with loading farmer details
                    loadFarmerDetails(farmerId);
                } else {
                    alert('Invalid PIN. Please try again.');
                    $('#admin-pin').val(''); // Clear PIN
                }
            }
        });
    });

    function loadFarmerDetails(farmerId) {
        $.ajax({
            url: 'getFarmerDetails.php',
            type: 'POST',
            data: { id: farmerId },
            success: function (response) {
                const farmer = JSON.parse(response);
                $('#farmerId').val(farmer.FarmerID);
                $('#editFirstName').val(farmer.FirstName);
                $('#editSurname').val(farmer.Surname);
                $('#editMiddlename').val(farmer.MiddleName);
                $('#editSuffix').val(farmer.Suffix);
                $('#editSex').val(farmer.Sex);
                $('#editBirthdate').val(farmer.BirthDate);
                $('#editContactNo').val(farmer.ContactNo);
                $('#editBarangay').val(farmer.Barangay);
                $('#editAddress').val(farmer.Address);
                $('#editUsername').val(farmer.UName);
                $('#editPassword').val('');
                $('#editRsbsaNumber').val(farmer.RSBSA_Number);
                $('.modal-overlay').fadeIn();
            }
        });
    }

    function createPinModal() {
        $('body').append(`
            <div class="pin-modal-overlay" style="display: none;">
                <div class="pin-modal">
                    <div class="modal-header">
                        <h2>Enter Admin PIN</h2>
                        <button class="modal-close-btn">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="pin-form">
                            <div class="form-row">
                                <input type="password" id="admin-pin" required maxlength="6" placeholder="Enter 6-digit PIN">
                            </div>
                            <div class="button-row">
                                <button type="submit">Submit</button>
                                <button type="button" class="modal-cancel-btn">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `);
    }

    function createEditModal() {
        $('body').append(`
            <div class="modal-overlay" style="display: none;">
                <div class="modal">
                    <div class="modal-header">
                        <h2>Edit Farmer Details</h2>
                        <button class="modal-close-btn">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="edit-form">
                            <input type="hidden" id="farmerId" name="farmerId">
                            <div class="form-row">
                                <div>
                                    <label for="firstName">First Name</label>
                                    <input type="text" id="editFirstName" name="firstName" required>
                                </div>
                                <div>
                                    <label for="surname">Surname</label>
                                    <input type="text" id="editSurname" name="surname" required>
                                </div>
                                <div>
                                    <label for="middlename">Middle Name</label>
                                    <input type="text" id="editMiddlename" name="middlename" required>
                                </div>
                                <div>
                                    <label for="suffix">Suffix</label>
                                    <input type="text" id="editSuffix" name="suffix" required>
                                </div>
                                <div>
                                    <label for="sex">Sex</label>
                                    <input type="text" id="editSex" name="sex" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div>
                                    <label for="birthdate">Birthdate</label>
                                    <input type="date" id="editBirthdate" name="birthdate" required>
                                </div>
                                <div>
                                    <label for="contactNo">Contact Number</label>
                                    <input type="text" id="editContactNo" name="contactNo" required>
                                </div>
                                <div>
                                    <label for="barangay">Barangay</label>
                                    <input type="text" id="editBarangay" name="barangay" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div>
                                    <label for="address">Address</label>
                                    <input type="text" id="editAddress" name="address" required>
                                </div>
                                <div>
                                    <label for="rsbsaNumber">RSBSA Number (if registered)</label>
                                    <input type="text" id="editRsbsaNumber" name="rsbsaNumber" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div>
                                    <label for="username">Username</label>
                                    <input type="text" id="editUsername" name="username" required>
                                </div>
                                <div>
                                    <label for="password">Password</label>
                                    <div class="password-container">
                                        <input type="password" id="editPassword" name="password" autocomplete="new-password" placeholder="Leave blank to keep current password">
                                        <button type="button" class="toggle-password">👁️</button>
                                    </div>
                                </div>
                            </div>
                            <div class="button-row">
                                <button type="button" class="modal-submit-btn">Save Changes</button>
                                <button type="button" class="modal-cancel-btn">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `);
    }

    // Add password toggle functionality
    $(document).on('click', '.toggle-password', function() {
        const passwordField = $('#editPassword');
        const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
        passwordField.attr('type', type);
        $(this).text(type === 'password' ? '👁️' : '👁️‍🗨️');
    });

    // Separate handlers for each modal's cancel button
    $(document).on('click', '.pin-modal .modal-cancel-btn', function () {
        $('.pin-modal-overlay').fadeOut();
        $('#admin-pin').val(''); // Clear PIN input
    });

    $(document).on('click', '.modal .modal-cancel-btn', function () {
        const confirmDiscard = confirm("Discard Changes?");
        if (confirmDiscard) {
            $('#edit-form')[0].reset();
            $('.modal-overlay').fadeOut();
        }
    });

    // Update close button handlers to be specific to each modal
    $(document).on('click', '.pin-modal .modal-close-btn', function () {
        $('.pin-modal-overlay').fadeOut();
        $('#admin-pin').val(''); // Clear PIN input
    });

    $(document).on('click', '.modal .modal-close-btn', function () {
        $('.modal-overlay').fadeOut();
    });

    $(document).on('click', '.modal-submit-btn', function () {
        const rsbsaNumber = $('#editRsbsaNumber').val();
        
        // Validate RSBSA format before submitting
        if (rsbsaNumber && !rsbsaNumber.match(/^\d{2}-\d{2}-\d{2}-\d{3}-\d{6}$/)) {
            alert('Please enter a valid RSBSA number in the format: XX-XX-XX-XXX-XXXXXX');
            return;
        }

        const formData = $('#edit-form').serialize();

        $.ajax({
            url: 'updateFarmer.php',
            type: 'POST',
            data: formData,
            dataType: 'json', // Explicitly specify we're expecting JSON
            success: function (data) {
                if (data.success) {
                    alert(data.message);
                    $('.modal-overlay').fadeOut();
                    loadUsers($('#user-filter').val(), $('#barangay-filter').val(), $('#rsbsa-filter').val());
                } else {
                    if (data.errors) {
                        // If there are validation errors, display them
                        alert(data.errors.join('\n'));
                    } else {
                        alert(data.message);
                    }
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX error:', xhr.responseText);
                alert("An error occurred while updating the farmer details. Please try again.");
            }
        });
    });

    // Add this new event listener for delete button
    $(document).on('click', '.toggle-status-btn', function () {
        const userId = $(this).data('id');
        const userType = $('#user-filter').val();
        const currentStatus = $(this).data('status');
        const action = currentStatus === 1 ? 'deactivate' : 'activate';
        
        if (confirm(`Are you sure you want to ${action} this user? ${currentStatus === 1 ? 'They will no longer be able to login to the system.' : 'They will be able to login to the system again.'}`)) {
            $.ajax({
                url: 'toggleUserStatus.php',
                type: 'POST',
                data: { 
                    userId: userId,
                    userType: userType,
                    action: action
                },
                success: function (response) {
                    const data = JSON.parse(response);
                    alert(data.message);
                    if (data.success) {
                        loadUsers($('#user-filter').val(), $('#barangay-filter').val(), $('#rsbsa-filter').val());
                    }
                },
                error: function () {
                    alert(`An error occurred while ${action}ing the user.`);
                }
            });
        }
    });

    // Helper function to capitalize barangay names
    function capitalizeBarangay(barangay) {
        if (!barangay) return '';
        
        return barangay.split(' ')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
            .join('_');
    }

    // Add this to your existing modal creation function or where you handle the RSBSA input
    $('#editRsbsaNumber').on('input', function() {
        const input = $(this);
        const value = input.val();
        
        // Auto-format as user types
        let formatted = value.replace(/\D/g, ''); // Remove non-digits
        if (formatted.length > 2) formatted = formatted.slice(0,2) + '-' + formatted.slice(2);
        if (formatted.length > 5) formatted = formatted.slice(0,5) + '-' + formatted.slice(5);
        if (formatted.length > 8) formatted = formatted.slice(0,8) + '-' + formatted.slice(8);
        if (formatted.length > 12) formatted = formatted.slice(0,12) + '-' + formatted.slice(12);
        if (formatted.length > 19) formatted = formatted.slice(0,19);
        
        if (value !== formatted) {
            input.val(formatted);
        }
        
        // Validate format
        const isValid = /^\d{2}-\d{2}-\d{2}-\d{3}-\d{6}$/.test(formatted);
        
        // Visual feedback
        if (formatted.length > 0) {
            if (isValid) {
                input.css('border-color', 'green');
                input.next('.format-hint').remove();
            } else {
                input.css('border-color', 'red');
                if (!input.next('.format-hint').length) {
                    input.after('<div class="format-hint" style="color: red; font-size: 0.8em;">Format: XX-XX-XX-XXX-XXXXXX</div>');
                }
            }
        } else {
            input.css('border-color', ''); // Reset to default
            input.next('.format-hint').remove();
        }
    });
});