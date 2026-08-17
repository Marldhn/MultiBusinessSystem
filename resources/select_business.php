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

                $_SESSION['business_id'] = $businessData['id'];
                $_SESSION['business_name'] = $businessData['name'];
                $_SESSION['business_role'] = $businessRole;

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

<div class="workspace-page">

    <div class="workspace-container">

        <!-- HEADER -->
        <div class="workspace-header">

            <div>
                <div class="eyebrow">
                    <i class="bi bi-grid-1x2-fill"></i>
                    WORKSPACE
                </div>

                <h1>Select your business</h1>

                <p>
                    Choose a business workspace to continue.
                </p>
            </div>

            <?php if ($userRole === 'super_admin'): ?>

                <a href="index.php?page=admin_portal" class="admin-link">
                    <i class="bi bi-shield-check"></i>
                    Admin Portal
                </a>

            <?php endif; ?>

        </div>


        <!-- ERROR -->
        <?php if ($error): ?>

            <div class="workspace-alert">
                <div class="alert-icon">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>

                <div>
                    <strong>Unable to open workspace</strong>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            </div>

        <?php endif; ?>


        <!-- BUSINESS LIST -->
        <?php if (empty($businesses)): ?>

            <div class="empty-workspace">

                <div class="empty-icon">
                    <i class="bi bi-buildings"></i>
                </div>

                <h3>No businesses available</h3>

                <p>
                    You are not currently assigned to any active business.
                    Please contact an administrator.
                </p>

            </div>

        <?php else: ?>

            <div class="business-list">

                <?php foreach ($businesses as $biz): ?>

                    <?php

                    $businessId = (int)$biz['id'];
                    $businessName = strtolower(trim($biz['name']));

                    /*
                    |--------------------------------------------------------------------------
                    | DETERMINE MODULE
                    |--------------------------------------------------------------------------
                    */

                    $module = 'loan';

                    if (strpos($businessName, 'inventory') !== false) {
                        $module = 'inventory';
                    } elseif (strpos($businessName, 'pos') !== false) {
                        $module = 'pos';
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | MODULE DETAILS
                    |--------------------------------------------------------------------------
                    */

                    switch ($module) {

                        case 'inventory':

                            $moduleTitle = 'Inventory Management';
                            $moduleDescription = 'Products, stock, categories, brands and suppliers.';
                            $moduleIcon = 'bi-box-seam';
                            $moduleClass = 'inventory';
                            $moduleButton = 'Open Inventory';

                            break;

                        case 'pos':

                            $moduleTitle = 'Point of Sale';
                            $moduleDescription = 'Sales, checkout, customers and transactions.';
                            $moduleIcon = 'bi-cart3';
                            $moduleClass = 'pos';
                            $moduleButton = 'Open POS';

                            break;

                        default:

                            $moduleTitle = 'Loan Management';
                            $moduleDescription = 'Borrowers, loans, payments, accounts and reports.';
                            $moduleIcon = 'bi-cash-stack';
                            $moduleClass = 'loan';
                            $moduleButton = 'Open Loan Management';

                            break;
                    }

                    ?>

                    <div class="business-item">

                        <!-- BUSINESS HEADER -->
                        <div class="business-main">

                            <div class="business-logo">
                                <i class="bi bi-shop"></i>
                            </div>

                            <div class="business-info">

                                <div class="business-title-row">

                                    <h2>
                                        <?= htmlspecialchars($biz['name']) ?>
                                    </h2>

                                    <?php if (!empty($biz['user_business_role'])): ?>

                                        <span class="role-badge">
                                            <?= htmlspecialchars($biz['user_business_role']) ?>
                                        </span>

                                    <?php endif; ?>

                                </div>

                                <div class="business-meta">

                                    <?php if (!empty($biz['email'])): ?>

                                        <span>
                                            <i class="bi bi-envelope"></i>
                                            <?= htmlspecialchars($biz['email']) ?>
                                        </span>

                                    <?php endif; ?>

                                    <?php if (!empty($biz['phone'])): ?>

                                        <span>
                                            <i class="bi bi-telephone"></i>
                                            <?= htmlspecialchars($biz['phone']) ?>
                                        </span>

                                    <?php endif; ?>

                                    <?php if (!empty($biz['address'])): ?>

                                        <span>
                                            <i class="bi bi-geo-alt"></i>
                                            <?= htmlspecialchars($biz['address']) ?>
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </div>

                            <div class="active-status">
                                <span></span>
                                Active
                            </div>

                        </div>


                        <!-- MODULE -->
                        <div class="module-row">

                            <div class="module-left">

                                <div class="module-symbol <?= $moduleClass ?>">
                                    <i class="bi <?= $moduleIcon ?>"></i>
                                </div>

                                <div class="module-details">

                                    <span class="module-label">
                                        WORKSPACE MODULE
                                    </span>

                                    <h3>
                                        <?= htmlspecialchars($moduleTitle) ?>
                                    </h3>

                                    <p>
                                        <?= htmlspecialchars($moduleDescription) ?>
                                    </p>

                                </div>

                            </div>


                            <form method="POST" class="module-form">

                                <input
                                    type="hidden"
                                    name="business_id"
                                    value="<?= $businessId ?>"
                                >

                                <input
                                    type="hidden"
                                    name="module"
                                    value="<?= htmlspecialchars($module) ?>"
                                >

                                <button
                                    type="submit"
                                    class="open-button <?= $moduleClass ?>"
                                >
                                    <?= htmlspecialchars($moduleButton) ?>
                                    <i class="bi bi-arrow-right"></i>
                                </button>

                            </form>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>


        <!-- FOOTER -->
        <div class="workspace-footer">

            <a href="index.php?page=logout">
                <i class="bi bi-box-arrow-right"></i>
                Sign out
            </a>

        </div>

    </div>

</div>


<style>

:root {
    --workspace-bg: #f5f7fb;
    --workspace-border: #e8ebf1;
    --workspace-text: #172033;
    --workspace-muted: #737b8c;
}

.workspace-page {
    min-height: calc(100vh - 70px);
    background: var(--workspace-bg);
    padding: 55px 20px 70px;
}

.workspace-container {
    max-width: 1000px;
    margin: 0 auto;
}

/* HEADER */

.workspace-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 25px;
    margin-bottom: 38px;
}

.eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: #64748b;
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .12em;
    margin-bottom: 9px;
}

.workspace-header h1 {
    margin: 0;
    color: var(--workspace-text);
    font-size: 2rem;
    font-weight: 800;
    letter-spacing: -.035em;
}

.workspace-header p {
    margin: 7px 0 0;
    color: var(--workspace-muted);
    font-size: .95rem;
}

.admin-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 15px;
    border: 1px solid var(--workspace-border);
    border-radius: 10px;
    background: #fff;
    color: #475569;
    text-decoration: none;
    font-size: .85rem;
    font-weight: 700;
    transition: .2s ease;
}

.admin-link:hover {
    color: #2563eb;
    border-color: #cbd5e1;
    background: #f8fafc;
}

/* ALERT */

.workspace-alert {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 15px 17px;
    margin-bottom: 24px;
    background: #fff;
    border: 1px solid #fecaca;
    border-radius: 12px;
    color: #991b1b;
}

.alert-icon {
    width: 38px;
    height: 38px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 9px;
    background: #fef2f2;
    color: #dc2626;
}

.workspace-alert strong,
.workspace-alert span {
    display: block;
}

.workspace-alert strong {
    font-size: .85rem;
}

.workspace-alert span {
    margin-top: 2px;
    font-size: .8rem;
    color: #b91c1c;
}

/* BUSINESS LIST */

.business-list {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.business-item {
    background: #fff;
    border: 1px solid var(--workspace-border);
    border-radius: 16px;
    overflow: hidden;
    transition:
        transform .2s ease,
        box-shadow .2s ease,
        border-color .2s ease;
}

.business-item:hover {
    transform: translateY(-2px);
    border-color: #dce2eb;
    box-shadow: 0 12px 35px rgba(15, 23, 42, .07);
}

/* BUSINESS INFO */

.business-main {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 23px 25px;
}

.business-logo {
    width: 52px;
    height: 52px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 13px;
    background: #eef4ff;
    color: #2563eb;
    font-size: 1.35rem;
}

.business-info {
    min-width: 0;
    flex: 1;
}

.business-title-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 9px;
}

.business-title-row h2 {
    margin: 0;
    color: var(--workspace-text);
    font-size: 1.05rem;
    font-weight: 750;
}

.role-badge {
    padding: 4px 8px;
    border-radius: 6px;
    background: #f1f5f9;
    color: #64748b;
    font-size: .63rem;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
}

.business-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 13px;
    margin-top: 7px;
}

.business-meta span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: #8a92a2;
    font-size: .76rem;
}

.business-meta i {
    color: #a0a8b6;
}

.active-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 9px;
    border-radius: 7px;
    background: #f0fdf4;
    color: #15803d;
    font-size: .68rem;
    font-weight: 800;
}

.active-status span {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #22c55e;
}

/* MODULE */

.module-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 25px;
    padding: 20px 25px;
    background: #fafbfc;
    border-top: 1px solid #edf0f4;
}

.module-left {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
}

.module-symbol {
    width: 46px;
    height: 46px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    font-size: 1.15rem;
}

.module-symbol.loan {
    background: #eff6ff;
    color: #2563eb;
}

.module-symbol.inventory {
    background: #ecfdf5;
    color: #059669;
}

.module-symbol.pos {
    background: #fffbeb;
    color: #d97706;
}

.module-details {
    min-width: 0;
}

.module-label {
    display: block;
    margin-bottom: 3px;
    color: #9aa2b1;
    font-size: .6rem;
    font-weight: 800;
    letter-spacing: .09em;
}

.module-details h3 {
    margin: 0;
    color: #253047;
    font-size: .9rem;
    font-weight: 750;
}

.module-details p {
    margin: 3px 0 0;
    color: #8a92a2;
    font-size: .76rem;
}

.module-form {
    flex-shrink: 0;
}

.open-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    min-width: 165px;
    padding: 10px 15px;
    border: 0;
    border-radius: 9px;
    color: #fff;
    font-size: .78rem;
    font-weight: 750;
    cursor: pointer;
    transition: .2s ease;
}

.open-button.loan {
    background: #2563eb;
}

.open-button.inventory {
    background: #059669;
}

.open-button.pos {
    background: #d97706;
}

.open-button:hover {
    color: #fff;
    transform: translateY(-1px);
    filter: brightness(.94);
}

.open-button i {
    transition: transform .2s ease;
}

.open-button:hover i {
    transform: translateX(3px);
}

/* EMPTY */

.empty-workspace {
    padding: 70px 25px;
    text-align: center;
    background: #fff;
    border: 1px solid var(--workspace-border);
    border-radius: 16px;
}

.empty-icon {
    width: 65px;
    height: 65px;
    margin: 0 auto 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 16px;
    background: #f1f5f9;
    color: #94a3b8;
    font-size: 1.7rem;
}

.empty-workspace h3 {
    margin: 0;
    color: #263247;
    font-size: 1.05rem;
    font-weight: 750;
}

.empty-workspace p {
    max-width: 420px;
    margin: 8px auto 0;
    color: #8992a2;
    font-size: .82rem;
    line-height: 1.6;
}

/* FOOTER */

.workspace-footer {
    text-align: center;
    margin-top: 30px;
}

.workspace-footer a {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #8b93a2;
    font-size: .78rem;
    font-weight: 700;
    text-decoration: none;
    transition: .2s ease;
}

.workspace-footer a:hover {
    color: #dc2626;
}

/* MOBILE */

@media (max-width: 767.98px) {

    .workspace-page {
        padding: 35px 14px 50px;
    }

    .workspace-header {
        align-items: flex-start;
        flex-direction: column;
        margin-bottom: 27px;
    }

    .workspace-header h1 {
        font-size: 1.65rem;
    }

    .admin-link {
        width: 100%;
        justify-content: center;
    }

    .business-main {
        align-items: flex-start;
        padding: 20px;
    }

    .active-status {
        display: none;
    }

    .business-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }

    .module-row {
        align-items: stretch;
        flex-direction: column;
        padding: 18px 20px 20px;
        gap: 17px;
    }

    .module-details p {
        line-height: 1.5;
    }

    .module-form {
        width: 100%;
    }

    .open-button {
        width: 100%;
    }

}

@media (max-width: 430px) {

    .business-main {
        gap: 12px;
    }

    .business-logo {
        width: 45px;
        height: 45px;
        font-size: 1.15rem;
    }

    .business-title-row h2 {
        font-size: .95rem;
    }

    .business-meta span {
        font-size: .7rem;
    }

}

</style>

<?php include __DIR__ . '/partials/footer.php'; ?>