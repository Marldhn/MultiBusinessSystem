<?php
// =========================================================
// ADMIN USER DETAILS
// USER APPROVAL + BUSINESS + MODULE ACCESS MANAGEMENT
// =========================================================

$pdo = Database::getConnection();

$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'user';

if (!$userId || $userRole !== 'super_admin') {
    header('Location: index.php?page=select_business');
    exit;
}

$targetUserId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($targetUserId <= 0) {
    header('Location: index.php?page=admin_portal');
    exit;
}

$error = '';
$success = '';


// =========================================================
// HANDLE POST ACTIONS
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    try {

        // =====================================================
        // APPROVE USER
        // =====================================================

        if ($action === 'approve_user') {

            $stmt = $pdo->prepare("
                UPDATE users
                SET is_approved = 1
                WHERE id = ?
            ");

            $stmt->execute([$targetUserId]);

            $success = 'User approved successfully.';
        }


        // =====================================================
        // REJECT USER
        // =====================================================

        elseif ($action === 'reject_user') {

            $stmt = $pdo->prepare("
                UPDATE users
                SET is_approved = 0
                WHERE id = ?
            ");

            $stmt->execute([$targetUserId]);

            $success = 'User rejected successfully.';
        }


        // =====================================================
        // UPDATE BUSINESS / MODULE ACCESS
        // =====================================================

        elseif ($action === 'save_access') {

            $businesses = $_POST['businesses'] ?? [];

            // Get all businesses
            $businessStmt = $pdo->query("
                SELECT id
                FROM businesses
            ");

            $allBusinessIds = $businessStmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($allBusinessIds as $businessId) {

                $businessId = (int)$businessId;

                $businessActive = isset($businesses[$businessId]['active']);

                // -------------------------------------------------
                // BUSINESS ACCESS
                // -------------------------------------------------

                $businessStatus = $businessActive
                    ? 'active'
                    : 'inactive';

                $stmt = $pdo->prepare("
                    INSERT INTO user_businesses
                        (user_id, business_id, status)
                    VALUES
                        (?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        status = VALUES(status)
                ");

                $stmt->execute([
                    $targetUserId,
                    $businessId,
                    $businessStatus
                ]);


                // -------------------------------------------------
                // MODULE ACCESS
                // -------------------------------------------------

                $modules = [
                    'loan',
                    'pos',
                    'inventory'
                ];

                foreach ($modules as $module) {

                    $moduleActive =
                        isset($businesses[$businessId]['modules'][$module]);

                    $moduleStatus = $moduleActive
                        ? 'active'
                        : 'inactive';

                    $stmt = $pdo->prepare("
                        INSERT INTO user_business_modules
                            (user_id, business_id, module, status)
                        VALUES
                            (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            status = VALUES(status)
                    ");

                    $stmt->execute([
                        $targetUserId,
                        $businessId,
                        $module,
                        $moduleStatus
                    ]);
                }
            }

            $success = 'Business and module access updated successfully.';
        }

    } catch (PDOException $e) {

        $error = $e->getMessage();
    }
}


// =========================================================
// FETCH USER
// =========================================================

$stmt = $pdo->prepare("
    SELECT *
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$targetUserId]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: index.php?page=admin_portal');
    exit;
}


// =========================================================
// FETCH BUSINESSES
// =========================================================

$businessStmt = $pdo->query("
    SELECT *
    FROM businesses
    ORDER BY business_name ASC
");

$businesses = $businessStmt->fetchAll(PDO::FETCH_ASSOC);


// =========================================================
// FETCH USER BUSINESS ACCESS
// =========================================================

$accessStmt = $pdo->prepare("
    SELECT business_id, status
    FROM user_businesses
    WHERE user_id = ?
");

$accessStmt->execute([$targetUserId]);

$businessAccess = [];

foreach ($accessStmt->fetchAll(PDO::FETCH_ASSOC) as $access) {

    $businessAccess[(int)$access['business_id']]
        = $access['status'];
}


// =========================================================
// FETCH USER MODULE ACCESS
// =========================================================

$moduleStmt = $pdo->prepare("
    SELECT business_id, module, status
    FROM user_business_modules
    WHERE user_id = ?
");

$moduleStmt->execute([$targetUserId]);

$moduleAccess = [];

foreach ($moduleStmt->fetchAll(PDO::FETCH_ASSOC) as $module) {

    $businessId = (int)$module['business_id'];
    $moduleName = $module['module'];

    $moduleAccess[$businessId][$moduleName]
        = $module['status'];
}


$pageTitle = "User Details";

include __DIR__ . '/partials/header.php';
?>

<div class="container py-4">

    <!-- =====================================================
         PAGE HEADER
         ===================================================== -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                User Details
            </h2>

            <p class="text-muted mb-0">
                Review user registration and manage business access.
            </p>

        </div>

        <a href="index.php?page=admin_portal"
           class="btn btn-outline-secondary btn-sm">

            <i class="bi bi-arrow-left me-1"></i>
            Back to Admin Portal

        </a>

    </div>


    <!-- =====================================================
         ALERTS
         ===================================================== -->

    <?php if ($success): ?>

        <div class="alert alert-success rounded-4 shadow-sm">
            <i class="bi bi-check-circle me-2"></i>
            <?= htmlspecialchars($success) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert alert-danger rounded-4 shadow-sm">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <!-- =====================================================
         USER INFORMATION
         ===================================================== -->

    <div class="card shadow-sm rounded-4 mb-4 overflow-hidden">

        <div class="card-header bg-white py-3">

            <h5 class="fw-bold mb-0">

                <i class="bi bi-person-circle me-2"></i>

                Registration Information

            </h5>

        </div>


        <div class="card-body">

            <div class="row g-4">

                <!-- NAME -->

                <div class="col-md-6">

                    <label class="text-muted small">
                        Full Name
                    </label>

                    <div class="fw-bold fs-5">
                        <?= htmlspecialchars($user['name'] ?? '-') ?>
                    </div>

                </div>


                <!-- EMAIL -->

                <div class="col-md-6">

                    <label class="text-muted small">
                        Email Address
                    </label>

                    <div class="fw-bold fs-5">
                        <?= htmlspecialchars($user['email'] ?? '-') ?>
                    </div>

                </div>


                <!-- ROLE -->

                <div class="col-md-4">

                    <label class="text-muted small">
                        Role
                    </label>

                    <div>

                        <span class="badge bg-primary">

                            <?= htmlspecialchars(
                                ucfirst($user['role'] ?? 'user')
                            ) ?>

                        </span>

                    </div>

                </div>


                <!-- STATUS -->

                <div class="col-md-4">

                    <label class="text-muted small">
                        Approval Status
                    </label>

                    <div>

                        <?php if ((int)($user['is_approved'] ?? 0) === 1): ?>

                            <span class="badge bg-success">
                                <i class="bi bi-check-circle me-1"></i>
                                Approved
                            </span>

                        <?php else: ?>

                            <span class="badge bg-warning text-dark">
                                <i class="bi bi-clock me-1"></i>
                                Pending
                            </span>

                        <?php endif; ?>

                    </div>

                </div>


                <!-- REGISTERED -->

                <div class="col-md-4">

                    <label class="text-muted small">
                        Registered Date
                    </label>

                    <div class="fw-semibold">

                        <?= htmlspecialchars(
                            $user['created_at'] ?? '-'
                        ) ?>

                    </div>

                </div>

            </div>

        </div>


        <!-- APPROVAL ACTIONS -->

        <div class="card-footer bg-light">

            <div class="d-flex justify-content-end gap-2">

                <?php if ((int)($user['is_approved'] ?? 0) === 1): ?>

                    <form method="POST"
                          onsubmit="return confirm('Are you sure you want to reject this user?');">

                        <input type="hidden"
                               name="action"
                               value="reject_user">

                        <button type="submit"
                                class="btn btn-outline-danger">

                            <i class="bi bi-x-circle me-1"></i>
                            Reject User

                        </button>

                    </form>

                <?php else: ?>

                    <form method="POST">

                        <input type="hidden"
                               name="action"
                               value="approve_user">

                        <button type="submit"
                                class="btn btn-success">

                            <i class="bi bi-check-circle me-1"></i>
                            Approve User

                        </button>

                    </form>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <!-- =====================================================
         BUSINESS & MODULE ACCESS
         ===================================================== -->

    <form method="POST">

        <input type="hidden"
               name="action"
               value="save_access">


        <div class="card shadow-sm rounded-4 overflow-hidden">

            <div class="card-header bg-white py-3">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <h5 class="fw-bold mb-1">

                            <i class="bi bi-buildings me-2"></i>

                            Business & Module Access

                        </h5>

                        <small class="text-muted">

                            Activate the businesses and modules this user
                            is allowed to access.

                        </small>

                    </div>

                </div>

            </div>


            <?php if (empty($businesses)): ?>

                <div class="p-4 text-center">

                    <p class="text-muted mb-0">
                        No businesses found.
                    </p>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table align-middle mb-0">

                        <thead class="table-light">

                            <tr>

                                <th>
                                    Business
                                </th>

                                <th class="text-center">
                                    Business Access
                                </th>

                                <th class="text-center">
                                    Loan
                                </th>

                                <th class="text-center">
                                    POS
                                </th>

                                <th class="text-center">
                                    Inventory
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($businesses as $business): ?>

                                <?php

                                $businessId =
                                    (int)$business['id'];

                                $businessIsActive =
                                    ($businessAccess[$businessId] ?? 'inactive')
                                    === 'active';

                                $loanActive =
                                    ($moduleAccess[$businessId]['loan'] ?? 'inactive')
                                    === 'active';

                                $posActive =
                                    ($moduleAccess[$businessId]['pos'] ?? 'inactive')
                                    === 'active';

                                $inventoryActive =
                                    ($moduleAccess[$businessId]['inventory'] ?? 'inactive')
                                    === 'active';

                                ?>

                                <tr>

                                    <!-- BUSINESS -->

                                    <td>

                                        <div class="fw-bold">

                                            <?= htmlspecialchars(
                                                $business['business_name']
                                            ) ?>

                                        </div>

                                        <?php if (!empty($business['slug'])): ?>

                                            <small class="text-muted">

                                                <?= htmlspecialchars(
                                                    $business['slug']
                                                ) ?>

                                            </small>

                                        <?php endif; ?>

                                    </td>


                                    <!-- BUSINESS ACCESS -->

                                    <td class="text-center">

                                        <div class="form-check form-switch d-inline-block">

                                            <input
                                                class="form-check-input business-toggle"
                                                type="checkbox"
                                                name="businesses[<?= $businessId ?>][active]"
                                                value="1"
                                                data-business-id="<?= $businessId ?>"
                                                <?= $businessIsActive ? 'checked' : '' ?>
                                            >

                                        </div>

                                        <div>

                                            <small class="business-status text-muted">

                                                <?= $businessIsActive
                                                    ? 'Active'
                                                    : 'Inactive'
                                                ?>

                                            </small>

                                        </div>

                                    </td>


                                    <!-- LOAN -->

                                    <td class="text-center">

                                        <div class="form-check form-switch d-inline-block">

                                            <input
                                                class="form-check-input module-toggle module-<?= $businessId ?>"
                                                type="checkbox"
                                                name="businesses[<?= $businessId ?>][modules][loan]"
                                                value="1"
                                                <?= $loanActive ? 'checked' : '' ?>
                                                <?= !$businessIsActive ? 'disabled' : '' ?>
                                            >

                                        </div>

                                        <div>

                                            <small class="text-muted">
                                                Loan
                                            </small>

                                        </div>

                                    </td>


                                    <!-- POS -->

                                    <td class="text-center">

                                        <div class="form-check form-switch d-inline-block">

                                            <input
                                                class="form-check-input module-toggle module-<?= $businessId ?>"
                                                type="checkbox"
                                                name="businesses[<?= $businessId ?>][modules][pos]"
                                                value="1"
                                                <?= $posActive ? 'checked' : '' ?>
                                                <?= !$businessIsActive ? 'disabled' : '' ?>
                                            >

                                        </div>

                                        <div>

                                            <small class="text-muted">
                                                POS
                                            </small>

                                        </div>

                                    </td>


                                    <!-- INVENTORY -->

                                    <td class="text-center">

                                        <div class="form-check form-switch d-inline-block">

                                            <input
                                                class="form-check-input module-toggle module-<?= $businessId ?>"
                                                type="checkbox"
                                                name="businesses[<?= $businessId ?>][modules][inventory]"
                                                value="1"
                                                <?= $inventoryActive ? 'checked' : '' ?>
                                                <?= !$businessIsActive ? 'disabled' : '' ?>
                                            >

                                        </div>

                                        <div>

                                            <small class="text-muted">
                                                Inventory
                                            </small>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


                <!-- SAVE -->

                <div class="card-footer bg-white">

                    <div class="d-flex justify-content-end">

                        <button type="submit"
                                class="btn btn-primary px-4">

                            <i class="bi bi-save me-1"></i>
                            Save Access Settings

                        </button>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </form>

</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('.business-toggle').forEach(function (toggle) {

        toggle.addEventListener('change', function () {

            const businessId = this.dataset.businessId;
            const modules = document.querySelectorAll(
                '.module-' + businessId
            );

            const row = this.closest('td');
            const status = row.querySelector('.business-status');

            modules.forEach(function (module) {

                module.disabled = !toggle.checked;

                if (!toggle.checked) {
                    module.checked = false;
                }

            });

            if (status) {

                status.textContent =
                    toggle.checked
                        ? 'Active'
                        : 'Inactive';

            }

        });

    });

});
</script>


<?php include __DIR__ . '/partials/footer.php'; ?>