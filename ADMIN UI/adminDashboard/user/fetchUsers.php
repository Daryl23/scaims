<?php
require_once __DIR__ . '/../../../shared/admin_auth.php';
scaims_require_admin('../../adminLogin/adminLogin.html');
require_once 'db_connection.php';

$filter = $_POST['filter'] ?? '';
$barangay = $_POST['barangay'] ?? '';
$search = $_POST['search'] ?? '';
$page = $_POST['page'] ?? 1;
$usersPerPage = 10;
$offset = ($page - 1) * $usersPerPage;
$rsbsa = $_POST['rsbsa'] ?? '';

// Modify the pagination parameters to handle both tables
$newPage = $_POST['newPage'] ?? 1;
$oldPage = $_POST['oldPage'] ?? 1;
$newOffset = ($newPage - 1) * $usersPerPage;
$oldOffset = ($oldPage - 1) * $usersPerPage;

$output = '';
$whereClause = [];
$params = [];
$types = '';

// Add RSBSA filter logic
if ($rsbsa === 'has') {
    $whereClause[] = "f.RSBSA_Number IS NOT NULL AND f.RSBSA_Number != ''"; // Filter for farmers with RSBSA number
} elseif ($rsbsa === 'no') {
    $whereClause[] = "f.RSBSA_Number IS NULL OR f.RSBSA_Number = ''"; // Filter for farmers without RSBSA number
}

if ($filter === 'Farmer') {
    // Base query for farmers
    if (!empty($search)) {
        $whereClause[] = "(FirstName LIKE ? OR Surname LIKE ? OR CONCAT(FirstName, ' ', Surname) LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $types .= 'sss';
    }
    
    if (!empty($barangay)) {
        $whereClause[] = "Barangay = ?";
        $params[] = $barangay;
        $types .= 's';
    }

    $whereSQL = !empty($whereClause) ? ' AND ' . implode(' AND ', $whereClause) : '';
    
    // Count total records
    $countSql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN registration_date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_count,
                    SUM(CASE WHEN registration_date < DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY) THEN 1 ELSE 0 END) as old_count
                 FROM farmer f
                 WHERE 1=1" . $whereSQL;
    
    $stmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $totalResult = $stmt->get_result();
    $counts = $totalResult->fetch_assoc();
    
    // Get new registrations (last 7 days)
    $sql = "SELECT 
            f.FarmerID,
            CONCAT(f.FirstName, ' ', f.Surname) as Name,
            f.ContactNo,
            f.Barangay,
            DATE_FORMAT(f.registration_date, '%Y-%m-%d') as RegistrationDate,
            f.is_active,
            f.RSBSA_Number
        FROM farmer f
        WHERE registration_date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)" . $whereSQL . "
        ORDER BY f.registration_date DESC
        LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($sql);
    $types .= 'ii';
    $params[] = $usersPerPage;
    $params[] = $newOffset;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $newResult = $stmt->get_result();
    
    // Reset params array and types for old registrations query
    $params = array_slice($params, 0, -2); // Remove the last two parameters (LIMIT and OFFSET)
    $types = substr($types, 0, -2); // Remove the last two types

    // Get old registrations
    $sql = "SELECT 
            f.FarmerID,
            CONCAT(f.FirstName, ' ', f.Surname) as Name,
            f.ContactNo,
            f.Barangay,
            DATE_FORMAT(f.registration_date, '%Y-%m-%d') as RegistrationDate,
            f.is_active,
            f.RSBSA_Number
        FROM farmer f
        WHERE registration_date < DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)" . $whereSQL . "
        ORDER BY f.registration_date DESC
        LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($sql);
    $types .= 'ii';
    $params[] = $usersPerPage;
    $params[] = $oldOffset;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $oldResult = $stmt->get_result();

    // Build output for new registrations
    $output .= '<div class="registration-section">';
    $output .= '<div class="registration-header">NEW REGISTRATIONS (last 7 days)</div>';
    if ($newResult->num_rows > 0) {
        $output .= '<table class="user-table">';
        $output .= '<thead><tr>
                    <th>Name</th>
                    <th>Contact Number</th>
                    <th>Barangay</th>
                    <th>Registration Date</th>
                    <th>Status</th>
                    <th>RSBSA Number</th>
                    <th>Action</th>
                    </tr></thead><tbody>';
        while ($row = $newResult->fetch_assoc()) {
            $output .= buildTableRow($row);
        }
        $output .= '</tbody></table>';
        
        $newTotalPages = ceil($counts['new_count'] / $usersPerPage);
        $output .= '<div class="pagination new-pagination">
                        <button id="new-prev-page"' . ($newPage <= 1 ? ' disabled' : '') . '>Previous</button>
                        <span id="new-current-page">Page ' . $newPage . ' of ' . $newTotalPages . '</span>
                        <button id="new-next-page"' . ($newPage >= $newTotalPages ? ' disabled' : '') . '>Next</button>
                    </div>';
    } else {
        $output .= '<div class="no-records">No new registrations in the last 7 days</div>';
    }
    $output .= '</div>';

    // Build output for old registrations
    $output .= '<div class="registration-section">';
    $output .= '<div class="registration-header">PREVIOUS REGISTRATIONS</div>';
    if ($oldResult->num_rows > 0) {
        $output .= '<table class="user-table">';
        $output .= '<thead><tr>
                    <th>Name</th>
                    <th>Contact Number</th>
                    <th>Barangay</th>
                    <th>Registration Date</th>
                    <th>Status</th>
                    <th>RSBSA Number</th>
                    <th>Action</th>
                    </tr></thead><tbody>';
        while ($row = $oldResult->fetch_assoc()) {
            $output .= buildTableRow($row);
        }
        $output .= '</tbody></table>';
        
        $oldTotalPages = ceil($counts['old_count'] / $usersPerPage);
        $output .= '<div class="pagination old-pagination">
                        <button id="old-prev-page"' . ($oldPage <= 1 ? ' disabled' : '') . '>Previous</button>
                        <span id="old-current-page">Page ' . $oldPage . ' of ' . $oldTotalPages . '</span>
                        <button id="old-next-page"' . ($oldPage >= $oldTotalPages ? ' disabled' : '') . '>Next</button>
                    </div>';
    } else {
        $output .= '<div class="no-records">No previous registrations found</div>';
    }
    $output .= '</div>';

} else {
    // Base query for admins (no sorting needed)
    if (!empty($search)) {
        $whereClause[] = "AdminName LIKE ?";
        $params[] = "%$search%";
        $types .= 's';
    }

    $whereSQL = !empty($whereClause) ? ' AND ' . implode(' AND ', $whereClause) : '';
    
    // Count total records
    $countSql = "SELECT COUNT(*) as total FROM admin WHERE 1=1" . $whereSQL;
    $stmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $totalResult = $stmt->get_result();
    $counts = $totalResult->fetch_assoc();
    
    // Get paginated data for admins
    $sql = "SELECT AdminID, AdminName as Name, is_active FROM admin WHERE 1=1" . $whereSQL . " LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $types .= 'ii';
    $params[] = $usersPerPage;
    $params[] = $offset;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    // Build output for admin table
    $output .= '<div class="registration-section">';
    $output .= '<div class="registration-header">System Administrators</div>';
    $output .= '<table class="user-table">';
    $output .= '<thead><tr>
                <th>Name</th>
                <th>Status</th>
                <th>Action</th>
                </tr></thead><tbody>';

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $status = $row['is_active'] ? 'Active' : 'Inactive';
            $statusBtnText = $row['is_active'] ? 'Deactivate' : 'Activate';
            $btnClass = $row['is_active'] ? 'deactivate' : 'activate';
            $output .= "<tr>
                        <td>{$row['Name']}</td>
                        <td>{$status}</td>
                        <td>
                            <button class='toggle-status-btn {$btnClass}' data-id='{$row['AdminID']}' data-status='{$row['is_active']}'>{$statusBtnText}</button>
                        </td>
                    </tr>";
        }
    } else {
        $output .= "<tr><td colspan='3' class='no-records'>No administrators found</td></tr>";
    }
    
    $output .= '</tbody></table></div>';
}

// Modify the JSON response to include pagination info for both tables
echo json_encode([
    'output' => $output,
    'total' => $counts['total'] ?? 0,
    'newCount' => $counts['new_count'] ?? 0,
    'oldCount' => $counts['old_count'] ?? 0,
    'newCurrentPage' => $newPage,
    'oldCurrentPage' => $oldPage,
    'newTotalPages' => ceil(($counts['new_count'] ?? 0) / $usersPerPage),
    'oldTotalPages' => ceil(($counts['old_count'] ?? 0) / $usersPerPage)
]);

$stmt->close();
$conn->close();

// Helper function to build table rows
function buildTableRow($row) {
    $status = $row['is_active'] ? 'Active' : 'Inactive';
    $statusBtnText = $row['is_active'] ? 'Deactivate' : 'Activate';
    $btnClass = $row['is_active'] ? 'deactivate' : 'activate';
    return "<tr>
            <td>{$row['Name']}</td>
            <td>{$row['ContactNo']}</td>
            <td>" . ucfirst(strtolower($row['Barangay'])) . "</td>
            <td>{$row['RegistrationDate']}</td>
            <td>{$status}</td>
            <td>{$row['RSBSA_Number']}</td>
            <td>
                <div class='action-buttons'>
                    <button class='edit-btn' data-id='{$row['FarmerID']}'>Edit</button>
                    <button class='toggle-status-btn {$btnClass}' data-id='{$row['FarmerID']}' data-status='{$row['is_active']}'>{$statusBtnText}</button>
                </div>
            </td>
        </tr>";
}
?>