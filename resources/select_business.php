<?php
// =========================================================
// SELECT BUSINESS PAGE
// =========================================================

$pdo = Database::getConnection();

$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'staff';

if (!$userId) {
    header('Location: index.php?page=login');
    exit;
}

$error = '';


// =========================================================
// HANDLE BUSINESS SELECTION
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['business_id'])) {

    $selectedBusinessId = (int)$_POST['business_id'];

    if ($selectedBusinessId <= 0) {
        $error = 'Invalid business selected.';
    } else {

        $hasAccess = false;
        $businessRole = 'owner';

        // -----------------------------------------------------
        // SUPER ADMIN
        // -----------------------------------------------------

        if ($userRole === 'super_admin') {

            $hasAccess = true;
            $businessRole = 'super_admin';

        } else {

            // -------------------------------------------------
            // CHECK USER BUSINESS ASSIGNMENT
            // -------------------------------------------------

            $checkStmt = $pdo->prepare("
                SELECT role
                FROM business_users
                WHERE business_id = ?
                AND user_id = ?
                LIMIT 1
            ");

            $checkStmt->execute([
                $selectedBusinessId,
                $userId
            ]);

            $assignment = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($assignment) {

                $hasAccess = true;
                $businessRole = $assignment['role'];

            }
        }


        // -----------------------------------------------------
        // VERIFY BUSINESS
        // -----------------------------------------------------

        if ($hasAccess) {

            $bStmt = $pdo->prepare("
                SELECT *
                FROM businesses
                WHERE id = ?
                AND status = 'active'
                LIMIT 1
            ");

            $bStmt->execute([
                $selectedBusinessId
            ]);

            $businessData = $bStmt->fetch(PDO::FETCH_ASSOC);

            if ($businessData) {

                $_SESSION['business_id'] = $businessData['id'];
                $_SESSION['business_name'] = $businessData['business_name'];
                $_SESSION['business_role'] = $businessRole;

                header('Location: index.php?page=dashboard');
                exit;

            } else {

                $error = 'Selected business is inactive or does not exist.';
            }

        } else {

            $error = 'You do not have permission to access this business.';
        }
    }
}


// =========================================================
// FETCH BUSINESSES
// =========================================================

if ($userRole === 'super_admin') {

    // Super Admin sees all active businesses

    $stmt = $pdo->prepare("
        SELECT
            b.id,
            b.business_name,
            b.slug,
            b.status,
            b.created_at,
            'super_admin' AS user_business_role
        FROM businesses b
        WHERE b.status = 'active'
        ORDER BY b.business_name ASC
    ");

    $stmt->execute();

} else {

    // ---------------------------------------------------------
    // NORMAL USERS
    // ONLY SEE BUSINESSES ASSIGNED TO THEM
    // ---------------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT
            b.id,
            b.business_name,
            b.slug,
            b.status,
            b.created_at,
            bu.role AS user_business_role
        FROM businesses b
        INNER JOIN business_users bu
            ON bu.business_id = b.id
        WHERE bu.user_id = ?
        AND b.status = 'active'
        ORDER BY b.business_name ASC
    ");

    $stmt->execute([
        $userId
    ]);
}

$businesses = $stmt->fetchAll(PDO::FETCH_ASSOC);


// =========================================================
// FETCH MODULES FOR EACH BUSINESS
// =========================================================

$businessModules = [];

if (!empty($businesses)) {

    $businessIds = array_column($businesses, 'id');

    $placeholders = implode(
        ',',
        array_fill(0, count($businessIds), '?')
    );

    $moduleStmt = $pdo->prepare("
        SELECT
            business_id,
            module_name,
            is_active
        FROM business_modules
        WHERE business_id IN ($placeholders)
        AND is_active = 1
        ORDER BY module_name ASC
    ");

    $moduleStmt->execute($businessIds);

    foreach ($moduleStmt->fetchAll(PDO::FETCH_ASSOC) as $module) {

        $businessId = (int)$module['business_id'];

        $businessModules[$businessId][] =
            $module['module_name'];
    }
}


$pageTitle = "Select Business - Multibusiness System";

include __DIR__ . '/partials/header.php';
?>

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-md-9 col-lg-8">


            <!-- =================================================
                 HEADER
                 ================================================= -->

            <div class="d-flex justify-content-between align-items-center mb-4">

                <div>

                    <h2 class="fw-bold mb-1">
                        Select a Business
                    </h2>

                    <p class="text-muted small mb-0">
                        Choose the business workspace you want to access.
                    </p>

                </div>


                <?php if ($userRole === 'super_admin'): ?>

                    <a href="index.php?page=admin_portal"
                       class="btn btn-outline-primary btn-sm fw-bold">

                        <i class="bi bi-shield-lock me-1"></i>
                        Admin Portal

                    </a>

                <?php endif; ?>

            </div>


            <!-- =================================================
                 ERROR
                 ================================================= -->

            <?php if (!empty($error)): ?>

                <div class="alert alert-danger rounded-4 shadow-sm mb-4">

                    <i class="bi bi-exclamation-circle-fill me-2"></i>

                    <?= htmlspecialchars($error) ?>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 NO BUSINESSES
                 ================================================= -->

            <?php if (empty($businesses)): ?>

                <div class="card p-5 text-center shadow-sm rounded-4 border-0 bg-white">

                    <div class="text-muted mb-3"
                         style="font-size: 3rem;">

                        <i class="bi bi-buildings"></i>

                    </div>

                    <h5 class="fw-bold text-dark">
                        No Businesses Available
                    </h5>

                    <p class="text-muted small mb-0">

                        You are not currently assigned to any active
                        businesses. Please contact an administrator.

                    </p>

                </div>


            <?php else: ?>


                <!-- =================================================
                     BUSINESS CARDS
                     ================================================= -->

                <div class="row g-3">

                    <?php foreach ($businesses as $biz): ?>

                        <?php

                        $businessId = (int)$biz['id'];

                        $modules =
                            $businessModules[$businessId] ?? [];

                        ?>

                        <div class="col-md-6">

                            <div class="card h-100 shadow-sm rounded-4 border-0 transition-card">

                                <div class="card-body p-4 d-flex flex-column">


                                    <!-- =================================
                                         BUSINESS HEADER
                                         ================================= -->

                                    <div>

                                        <div class="d-flex align-items-center justify-content-between mb-3">

                                            <div class="rounded-3 bg-primary bg-opacity-10 text-primary p-3">

                                                <i class="bi bi-shop"
                                                   style="font-size: 1.5rem;">
                                                </i>

                                            </div>


                                            <!-- USER ROLE -->

                                            <?php if (!empty($biz['user_business_role'])): ?>

                                                <span class="badge bg-secondary text-uppercase px-2 py-1"
                                                      style="font-size: 0.65rem;">

                                                    <?= htmlspecialchars(
                                                        $biz['user_business_role']
                                                    ) ?>

                                                </span>

                                            <?php endif; ?>

                                        </div>


                                        <!-- BUSINESS NAME -->

                                        <h5 class="fw-bold text-dark mb-1">

                                            <?= htmlspecialchars(
                                                $biz['business_name']
                                            ) ?>

                                        </h5>


                                        <!-- SLUG -->

                                        <p class="text-muted small mb-3">

                                            <?= htmlspecialchars(
                                                $biz['slug']
                                            ) ?>

                                        </p>


                                        <!-- =================================
                                             MODULES
                                             ================================= -->

                                        <div class="mb-3">

                                            <div class="small fw-bold text-muted mb-2">

                                                Available Modules

                                            </div>


                                            <?php if (empty($modules)): ?>

                                                <span class="text-muted small">

                                                    No active modules

                                                </span>

                                            <?php else: ?>

                                                <div class="d-flex flex-wrap gap-2">

                                                    <?php foreach ($modules as $module): ?>

                                                        <?php

                                                        $moduleKey =
                                                            strtolower(
                                                                trim($module)
                                                            );

                                                        switch ($moduleKey) {

                                                            case 'loan':
                                                            case 'loans':
                                                            case 'lending':
                                                            case 'loan management':

                                                                $moduleLabel =
                                                                    'Loan Management';

                                                                $moduleIcon =
                                                                    'bi-cash-stack';

                                                                break;

                                                            case 'pos':
                                                            case 'point of sale':

                                                                $moduleLabel =
                                                                    'POS';

                                                                $moduleIcon =
                                                                    'bi-cart3';

                                                                break;

                                                            case 'inventory':

                                                                $moduleLabel =
                                                                    'Inventory';

                                                                $moduleIcon =
                                                                    'bi-box-seam';

                                                                break;

                                                            default:

                                                                $moduleLabel =
                                                                    ucfirst(
                                                                        $module
                                                                    );

                                                                $moduleIcon =
                                                                    'bi-grid';

                                                                break;
                                                        }

                                                        ?>

                                                        <span class="badge bg-light text-dark border module-badge">

                                                            <i class="bi <?= $moduleIcon ?> me-1"></i>

                                                            <?= htmlspecialchars(
                                                                $moduleLabel
                                                            ) ?>

                                                        </span>

                                                    <?php endforeach; ?>

                                                </div>

                                            <?php endif; ?>

                                        </div>

                                    </div>


                                    <!-- =================================
                                         OPEN WORKSPACE
                                         ================================= -->

                                    <form method="POST"
                                          class="mt-auto">

                                        <input type="hidden"
                                               name="business_id"
                                               value="<?= $businessId ?>">

                                        <button type="submit"
                                                class="btn btn-primary w-100 fw-bold rounded-3 py-2">

                                            <i class="bi bi-box-arrow-in-right me-1"></i>

                                            Open Workspace

                                        </button>

                                    </form>


                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 SIGN OUT
                 ================================================= -->

            <div class="text-center mt-4">

                <a href="index.php?page=logout"
                   class="text-danger small text-decoration-none fw-bold">

                    <i class="bi bi-box-arrow-right me-1"></i>

                    Sign Out

                </a>

            </div>


        </div>

    </div>

</div>


<style>

.transition-card {
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.transition-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.08) !important;
}

.module-badge {
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.45rem 0.6rem;
}

</style>


<?php include __DIR__ . '/partials/footer.php'; ?>