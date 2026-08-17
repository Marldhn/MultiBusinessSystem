<?php

$pdo = Database::getConnection();

$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'staff';

if (!$userId) {
    header('Location: index.php?page=login');
    exit;
}

$error = '';

/*
|--------------------------------------------------------------------------
| OPEN MODULE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['business_id'])) {

    $selectedBusinessId = (int)$_POST['business_id'];
    $module = $_POST['module'] ?? '';

    if ($selectedBusinessId <= 0) {
        $error = 'Invalid business selected.';
    } else {

        $hasAccess = false;
        $businessRole = 'owner';

        /*
        |--------------------------------------------------------------------------
        | CHECK BUSINESS ACCESS
        |--------------------------------------------------------------------------
        */

        if ($userRole === 'super_admin') {

            $hasAccess = true;
            $businessRole = 'super_admin';

        } else {

            $checkStmt = $pdo->prepare("
                SELECT role
                FROM business_users
                WHERE business_id = ?
                AND user_id = ?
                AND status = 'active'
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

        /*
        |--------------------------------------------------------------------------
        | LOAD BUSINESS
        |--------------------------------------------------------------------------
        */

        if ($hasAccess) {

            $bStmt = $pdo->prepare("
                SELECT *
                FROM businesses
                WHERE id = ?
                AND status = 'active'
                LIMIT 1
            ");

            $bStmt->execute([$selectedBusinessId]);

            $businessData = $bStmt->fetch(PDO::FETCH_ASSOC);

            if ($businessData) {

                /*
                |--------------------------------------------------------------------------
                | SAVE BUSINESS SESSION
                |--------------------------------------------------------------------------
                */

                $_SESSION['business_id'] = $businessData['id'];
                $_SESSION['business_name'] = $businessData['name'];
                $_SESSION['business_role'] = $businessRole;

                /*
                |--------------------------------------------------------------------------
                | MODULE REDIRECT
                |--------------------------------------------------------------------------
                */

                switch ($module) {

                    case 'loan':

                        header('Location: index.php?page=dashboard');
                        exit;

                    case 'inventory':

                        header('Location: index.php?page=inventory_dashboard');
                        exit;

                    case 'pos':

                        header('Location: index.php?page=pos_dashboard');
                        exit;

                    default:

                        $error = 'Invalid module selected.';
                        break;
                }

            } else {

                $error = 'Selected business is inactive or does not exist.';
            }

        } else {

            $error = 'You do not have permission to access this business.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| LOAD BUSINESSES
|--------------------------------------------------------------------------
*/

if ($userRole === 'super_admin') {

    $stmt = $pdo->prepare("
        SELECT
            b.id,
            b.name,
            b.email,
            b.phone,
            b.address,
            b.status,
            b.created_at,
            'super_admin' AS user_business_role
        FROM businesses b
        WHERE b.status = 'active'
        ORDER BY b.name ASC
    ");

    $stmt->execute();

} else {

    $stmt = $pdo->prepare("
        SELECT
            b.id,
            b.name,
            b.email,
            b.phone,
            b.address,
            b.status,
            b.created_at,
            bu.role AS user_business_role
        FROM businesses b
        INNER JOIN business_users bu
            ON bu.business_id = b.id
        WHERE bu.user_id = ?
        AND bu.status = 'active'
        AND b.status = 'active'
        ORDER BY b.name ASC
    ");

    $stmt->execute([$userId]);
}

$businesses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Select Business - Multibusiness System';

include __DIR__ . '/partials/header.php';
?>

<div class="container py-5">

```
<div class="row justify-content-center">

    <div class="col-md-10 col-lg-9">

        <!-- =====================================================
             HEADER
        ====================================================== -->

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


        <!-- =====================================================
             ERROR
        ====================================================== -->

        <?php if ($error): ?>

            <div class="alert alert-danger rounded-4 shadow-sm mb-4">

                <i class="bi bi-exclamation-circle-fill me-2"></i>

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             NO BUSINESSES
        ====================================================== -->

        <?php if (empty($businesses)): ?>

            <div class="card p-5 text-center shadow-sm rounded-4 border-0 bg-white">

                <div class="text-muted mb-3"
                     style="font-size:3rem;">

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


            <!-- =====================================================
                 BUSINESS CARDS
            ====================================================== -->

            <div class="row g-4">

                <?php foreach ($businesses as $biz): ?>

                    <?php

                    $businessId = (int)$biz['id'];

                    /*
                    |--------------------------------------------------------------------------
                    | DETERMINE BUSINESS MODULE
                    |--------------------------------------------------------------------------
                    |
                    | Current setup:
                    |
                    | Loan Management System
                    | Inventory Management System
                    | POS Management System
                    |
                    */

                    $businessName = strtolower(trim($biz['name']));

                    $module = 'loan';

                    if (
                        strpos($businessName, 'inventory') !== false
                    ) {

                        $module = 'inventory';

                    } elseif (
                        strpos($businessName, 'pos') !== false
                    ) {

                        $module = 'pos';

                    }

                    /*
                    |--------------------------------------------------------------------------
                    | MODULE INFORMATION
                    |--------------------------------------------------------------------------
                    */

                    if ($module === 'inventory') {

                        $moduleTitle = 'Inventory Management';
                        $moduleDescription = 'Manage products, stock, categories, brands and suppliers.';
                        $moduleIcon = 'bi-box-seam';
                        $moduleButton = 'Open Inventory';
                        $moduleButtonClass = 'btn-success';
                        $moduleIconClass = 'bg-success bg-opacity-10 text-success';

                    } elseif ($module === 'pos') {

                        $moduleTitle = 'POS Management';
                        $moduleDescription = 'Manage sales, checkout, customers, products and transactions.';
                        $moduleIcon = 'bi-cart3';
                        $moduleButton = 'Open POS';
                        $moduleButtonClass = 'btn-warning';
                        $moduleIconClass = 'bg-warning bg-opacity-10 text-warning';

                    } else {

                        $moduleTitle = 'Loan Management';
                        $moduleDescription = 'Manage borrowers, loans, payments, accounts and reports.';
                        $moduleIcon = 'bi-cash-stack';
                        $moduleButton = 'Open Loan Management';
                        $moduleButtonClass = 'btn-primary';
                        $moduleIconClass = 'bg-primary bg-opacity-10 text-primary';
                    }

                    ?>

                    <div class="col-12">

                        <div class="card shadow-sm rounded-4 border-0 business-card">

                            <div class="card-body p-4">

                                <!-- =================================
                                     BUSINESS INFORMATION
                                ================================== -->

                                <div class="d-flex flex-column flex-md-row
                                            justify-content-between
                                            align-items-md-start
                                            gap-3">

                                    <div class="d-flex align-items-start gap-3">

                                        <div class="rounded-3 bg-primary
                                                    bg-opacity-10
                                                    text-primary
                                                    p-3">

                                            <i class="bi bi-shop"
                                               style="font-size:1.5rem;">
                                            </i>

                                        </div>

                                        <div>

                                            <div class="d-flex align-items-center gap-2 flex-wrap">

                                                <h5 class="fw-bold text-dark mb-0">

                                                    <?= htmlspecialchars($biz['name']) ?>

                                                </h5>

                                                <?php if (!empty($biz['user_business_role'])): ?>

                                                    <span class="badge bg-secondary text-uppercase"
                                                          style="font-size:.65rem;">

                                                        <?= htmlspecialchars($biz['user_business_role']) ?>

                                                    </span>

                                                <?php endif; ?>

                                            </div>

                                            <?php if (!empty($biz['email'])): ?>

                                                <p class="text-muted small mb-1 mt-2">

                                                    <i class="bi bi-envelope me-1"></i>

                                                    <?= htmlspecialchars($biz['email']) ?>

                                                </p>

                                            <?php endif; ?>

                                            <?php if (!empty($biz['phone'])): ?>

                                                <p class="text-muted small mb-1">

                                                    <i class="bi bi-telephone me-1"></i>

                                                    <?= htmlspecialchars($biz['phone']) ?>

                                                </p>

                                            <?php endif; ?>

                                            <?php if (!empty($biz['address'])): ?>

                                                <p class="text-muted small mb-0">

                                                    <i class="bi bi-geo-alt me-1"></i>

                                                    <?= htmlspecialchars($biz['address']) ?>

                                                </p>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                </div>


                                <!-- =================================
                                     MODULE
                                ================================== -->

                                <div class="border-top mt-4 pt-4">

                                    <div class="card module-card border rounded-4">

                                        <div class="card-body p-4">

                                            <div class="d-flex
                                                        align-items-start
                                                        gap-3
                                                        mb-3">

                                                <div class="module-icon <?= $moduleIconClass ?>">

                                                    <i class="bi <?= $moduleIcon ?>"></i>

                                                </div>

                                                <div>

                                                    <h6 class="fw-bold mb-1">

                                                        <?= htmlspecialchars($moduleTitle) ?>

                                                    </h6>

                                                    <p class="text-muted small mb-0">

                                                        <?= htmlspecialchars($moduleDescription) ?>

                                                    </p>

                                                </div>

                                            </div>


                                            <form method="POST">

                                                <input type="hidden"
                                                       name="business_id"
                                                       value="<?= $businessId ?>">

                                                <input type="hidden"
                                                       name="module"
                                                       value="<?= htmlspecialchars($module) ?>">

                                                <button type="submit"
                                                        class="btn <?= $moduleButtonClass ?>
                                                               w-100
                                                               fw-bold
                                                               rounded-3">

                                                    <i class="bi bi-box-arrow-in-right me-1"></i>

                                                    <?= htmlspecialchars($moduleButton) ?>

                                                </button>

                                            </form>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             SIGN OUT
        ====================================================== -->

        <div class="text-center mt-4">

            <a href="index.php?page=logout"
               class="text-danger small text-decoration-none fw-bold">

                <i class="bi bi-box-arrow-right me-1"></i>

                Sign Out

            </a>

        </div>

    </div>

</div>
```

</div>

<style>

.business-card {
    transition: transform .2s ease, box-shadow .2s ease;
}

.business-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(0,0,0,.08) !important;
}

.module-card {
    transition: transform .2s ease,
                box-shadow .2s ease;
}

.module-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 22px rgba(0,0,0,.08);
}

.module-icon {
    width: 48px;
    height: 48px;
    min-width: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

@media (max-width: 575.98px) {

    .container {
        padding-left: 14px;
        padding-right: 14px;
    }

    .business-card .card-body {
        padding: 18px !important;
    }

    .module-card .card-body {
        padding: 18px !important;
    }

}

</style>

<?php include __DIR__ . '/partials/footer.php'; ?>
