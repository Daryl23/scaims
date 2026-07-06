<?php
require_once dirname(__DIR__, 3) . '/shared/admin_auth.php';
require_once dirname(__DIR__, 3) . '/shared/crop_catalog.php';

scaims_require_super_admin('../home/home.php');

$activePage = 'crops';
$assetPath = '../../assets/';
$loginPath = '../../adminLogin/adminLogin.html';
$sharedPath = '../../shared/';
$cssVersion = @filemtime(__DIR__ . '/cropManagement.css') ?: time();
$jsVersion = @filemtime(__DIR__ . '/cropManagement.js') ?: time();
$rootSharedVersion = @filemtime(dirname(__DIR__, 3) . '/shared/crop-catalog.js') ?: time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crop Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="cropManagement.css?v=<?php echo $cssVersion; ?>">
    <script src="../../../shared/app-path.js"></script>
    <script src="../../../shared/crop-catalog.js?v=<?php echo $rootSharedVersion; ?>"></script>
    <script src="cropManagement.js?v=<?php echo $jsVersion; ?>" defer></script>
</head>
<body>
    <div class="container">
        <?php include '../../shared/admin-sidebar.php'; ?>

        <main class="content">
            <section class="crop-header">
                <div>
                    <p class="eyebrow">Super Admin</p>
                    <h1>Crop Management</h1>
                    <p class="header-copy">Add, update, deactivate, and restore crop choices used by report forms, parcel forms, filters, and exports.</p>
                </div>
                <div class="crop-stats" aria-label="Crop catalog summary">
                    <div>
                        <strong id="activeCropCount">0</strong>
                        <span>Active crops</span>
                    </div>
                    <div>
                        <strong id="inactiveCropCount">0</strong>
                        <span>Inactive</span>
                    </div>
                </div>
            </section>

            <section class="crop-workspace">
                <form id="cropForm" class="crop-form" autocomplete="off">
                    <input type="hidden" id="cropId" name="id">
                    <div class="form-header">
                        <h2 id="formTitle">Add Crop</h2>
                        <button type="button" id="resetFormButton" class="ghost-button">Clear</button>
                    </div>

                    <label for="cropName">Crop Name</label>
                    <input type="text" id="cropName" name="crop_name" maxlength="120" required placeholder="Example: Rice">

                    <label for="cropCommodity">Commodity</label>
                    <select id="cropCommodity" name="commodity" required>
                        <option value="grains">Grains</option>
                        <option value="plantation">Plantation Crops</option>
                        <option value="root">Root Crops</option>
                        <option value="vegetables">Vegetables</option>
                        <option value="fruits">Fruits</option>
                    </select>

                    <label for="cropSortOrder">Display Order</label>
                    <input type="number" id="cropSortOrder" name="sort_order" min="0" step="1" value="100">

                    <label class="checkbox-row">
                        <input type="checkbox" id="cropIsActive" name="is_active" checked>
                        <span>Show this crop in forms and filters</span>
                    </label>

                    <button type="submit" class="primary-button" id="saveCropButton">Save Crop</button>
                    <p id="formMessage" class="form-message" role="status"></p>
                </form>

                <section class="crop-list-panel">
                    <div class="list-toolbar">
                        <div>
                            <h2>Crop Catalog</h2>
                            <p>Inactive crops are hidden from new submissions but old reports remain untouched.</p>
                        </div>
                        <input type="search" id="cropSearch" placeholder="Search crops">
                    </div>

                    <div id="cropTableWrap" class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Crop</th>
                                    <th>Commodity</th>
                                    <th>Status</th>
                                    <th>Order</th>
                                    <th class="actions-col">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="cropTableBody">
                                <tr><td colspan="5" class="empty-state">Loading crops...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>
        </main>
    </div>
</body>
</html>
