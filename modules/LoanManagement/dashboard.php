<?php
$pdo = Database::getConnection();

$businessId = $_SESSION['business_id'] ?? null;
$userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

if (!$businessId || !$userId) {
    header('Location: index.php?page=select_business');
    exit;
}

$businessId = (int)$businessId;
$userId = (int)$userId;

$upcomingDays = isset($_GET['upcoming_days']) ? (int)$_GET['upcoming_days'] : 15;

if (!in_array($upcomingDays, [15, 30], true)) {
    $upcomingDays = 15;
}

$businessName = $_SESSION['business_name'] ?? 'Business';

/*
|--------------------------------------------------------------------------
| ACTIVE LOANS
|--------------------------------------------------------------------------
*/

$activeLoansStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_active,
        COALESCE(SUM(total_payable), 0) AS total_amount
    FROM loans
    WHERE business_id = ?
    AND created_by = ?
    AND status = 'active'
");

$activeLoansStmt->execute([
    $businessId,
    $userId
]);

$activeLoansData = $activeLoansStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total_active' => 0,
    'total_amount' => 0
];

/*
|--------------------------------------------------------------------------
| BORROWERS
|--------------------------------------------------------------------------
*/

$borrowersStmt = $pdo->prepare("
    SELECT COUNT(*) AS total_borrowers
    FROM loan_borrowers
    WHERE business_id = ?
    AND created_by = ?
");

$borrowersStmt->execute([
    $businessId,
    $userId
]);

$borrowersData = $borrowersStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total_borrowers' => 0
];

/*
|--------------------------------------------------------------------------
| ACCOUNTS / AVAILABLE FUNDS
|--------------------------------------------------------------------------
*/

$accountsStmt = $pdo->prepare("
    SELECT COALESCE(SUM(balance), 0) AS total_funds
    FROM loan_accounts
    WHERE business_id = ?
    AND created_by = ?
");

$accountsStmt->execute([
    $businessId,
    $userId
]);

$accountsData = $accountsStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total_funds' => 0
];

/*
|--------------------------------------------------------------------------
| RECENT ACCOUNT TRANSACTIONS
|--------------------------------------------------------------------------
*/

$txStmt = $pdo->prepare("
    SELECT t.*
    FROM loan_account_transactions t
    INNER JOIN loan_accounts a
        ON a.id = t.account_id
        AND a.business_id = t.business_id
        AND a.created_by = ?
    WHERE t.business_id = ?
    AND t.created_by = ?
    ORDER BY t.created_at DESC
    LIMIT 5
");

$txStmt->execute([
    $userId,
    $businessId,
    $userId
]);

$recentTransactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| UPCOMING PAYMENTS
|--------------------------------------------------------------------------
*/

$upcomingPaymentsStmt = $pdo->prepare("
    SELECT
        s.id AS schedule_id,
        s.loan_id,
        s.due_date,
        s.amount_due,
        s.status AS schedule_status,
        l.reference_number,
        l.status AS loan_status,
        CONCAT(b.first_name, ' ', b.last_name) AS borrower_name
    FROM loan_schedules s
    INNER JOIN loans l
        ON s.loan_id = l.id
        AND l.business_id = s.business_id
        AND l.created_by = ?
    INNER JOIN loan_borrowers b
        ON l.borrower_id = b.id
        AND b.business_id = l.business_id
        AND b.created_by = ?
    WHERE l.business_id = ?
    AND l.created_by = ?
    AND l.status = 'active'
    AND s.status IN ('unpaid', 'partially_paid')
    AND s.due_date >= CURDATE()
    AND s.due_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
    ORDER BY s.due_date ASC
    LIMIT 20
");

$upcomingPaymentsStmt->execute([
    $userId,
    $userId,
    $businessId,
    $userId,
    $upcomingDays
]);

$upcomingPayments = $upcomingPaymentsStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| OVERDUE PAYMENTS
|--------------------------------------------------------------------------
*/

$overduePaymentsStmt = $pdo->prepare("
    SELECT
        s.id AS schedule_id,
        s.loan_id,
        s.due_date,
        s.amount_due,
        s.status AS schedule_status,
        l.reference_number,
        l.status AS loan_status,
        CONCAT(b.first_name, ' ', b.last_name) AS borrower_name
    FROM loan_schedules s
    INNER JOIN loans l
        ON s.loan_id = l.id
        AND l.business_id = s.business_id
        AND l.created_by = ?
    INNER JOIN loan_borrowers b
        ON l.borrower_id = b.id
        AND b.business_id = l.business_id
        AND b.created_by = ?
    WHERE l.business_id = ?
    AND l.created_by = ?
    AND l.status IN ('active', 'overdue')
    AND s.status IN ('unpaid', 'partially_paid', 'overdue')
    AND s.due_date < CURDATE()
    ORDER BY s.due_date ASC
    LIMIT 20
");

$overduePaymentsStmt->execute([
    $userId,
    $userId,
    $businessId,
    $userId
]);

$overduePayments = $overduePaymentsStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| TOTALS
|--------------------------------------------------------------------------
*/

$upcomingTotal = array_sum(
    array_map(
        'floatval',
        array_column($upcomingPayments, 'amount_due')
    )
);

$overdueTotal = array_sum(
    array_map(
        'floatval',
        array_column($overduePayments, 'amount_due')
    )
);

$activeLoanCount = (int)($activeLoansData['total_active'] ?? 0);
$activeLoanAmount = (float)($activeLoansData['total_amount'] ?? 0);
$totalBorrowers = (int)($borrowersData['total_borrowers'] ?? 0);
$totalFunds = (float)($accountsData['total_funds'] ?? 0);

$upcomingCount = count($upcomingPayments);
$overdueCount = count($overduePayments);

$activePage = 'dashboard';
$pageTitle = 'Dashboard - Loan Management';

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money($value): string
{
    return '₱' . number_format((float)$value, 2);
}

function paymentStatusClass(int $daysRemaining): string
{
    if ($daysRemaining === 0) {
        return 'warning';
    }

    if ($daysRemaining <= 3) {
        return 'warning';
    }

    return 'primary';
}

function paymentStatusLabel(int $daysRemaining): string
{
    if ($daysRemaining === 0) {
        return 'Due Today';
    }

    if ($daysRemaining <= 3) {
        return 'Due Soon';
    }

    return 'Upcoming';
}

function loanReference(array $payment): string
{
    return !empty($payment['reference_number'])
        ? $payment['reference_number']
        : '#' . ($payment['loan_id'] ?? '');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= e($pageTitle) ?></title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
    rel="stylesheet"
>

<script>
(function () {
    const theme = localStorage.getItem('bs-theme') || 'light';
    document.documentElement.setAttribute('data-bs-theme', theme);
})();
</script>

<style>
body {
    min-height: 100vh;
    overflow-x: hidden;
}

.dashboard-main {
    min-width: 0;
    width: 100%;
}

.dashboard-container {
    max-width: 1600px;
    margin: 0 auto;
}

.dashboard-header {
    padding-bottom: 2px;
}

.welcome-icon {
    width: 44px;
    height: 44px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.metric-card {
    border: 1px solid var(--bs-border-color);
    border-radius: 18px;
    overflow: hidden;
    transition:
        transform .2s ease,
        box-shadow .2s ease,
        border-color .2s ease;
}

.metric-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.08)!important;
}

.metric-card .card-body {
    padding: 22px;
}

.metric-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.metric-label {
    font-size: .68rem;
    letter-spacing: .07em;
    text-transform: uppercase;
}

.metric-value {
    font-size: 1.65rem;
    line-height: 1.15;
}

.metric-description {
    font-size: .75rem;
}

.quick-actions {
    border: 1px solid var(--bs-border-color);
    border-radius: 18px;
    overflow: hidden;
}

.quick-action {
    min-height: 92px;
    border: 0;
    border-radius: 13px;
    transition: all .2s ease;
}

.quick-action:hover {
    transform: translateY(-2px);
    background: var(--bs-tertiary-bg);
}

.quick-action-icon {
    width: 40px;
    height: 40px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.payment-card {
    border: 1px solid var(--bs-border-color);
    border-radius: 18px;
    overflow: hidden;
}

.payment-card.upcoming-card {
    border-left: 4px solid var(--bs-primary);
}

.payment-card.overdue-card {
    border-left: 4px solid var(--bs-danger);
}

.payment-card-header {
    padding: 21px 24px;
}

.section-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.payment-table th,
.transaction-table th {
    font-size: .68rem;
    letter-spacing: .06em;
    white-space: nowrap;
}

.payment-table td,
.transaction-table td {
    padding-top: 14px;
    padding-bottom: 14px;
}

.payment-table tbody tr,
.transaction-table tbody tr {
    transition: background .15s ease;
}

.payment-table tbody tr:hover,
.transaction-table tbody tr:hover {
    background: var(--bs-tertiary-bg);
}

.payment-date {
    font-weight: 700;
}

.due-soon-badge {
    font-size: .68rem;
}

.empty-state {
    padding: 48px 20px;
}

.empty-icon {
    width: 58px;
    height: 58px;
    border-radius: 16px;
    margin: 0 auto 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.payment-mobile,
.transaction-mobile {
    display: none;
}

.mobile-payment {
    border: 1px solid var(--bs-border-color);
    border-radius: 14px;
    padding: 14px;
    margin-bottom: 10px;
    background: var(--bs-body-bg);
}

.mobile-payment:last-child {
    margin-bottom: 0;
}

.mobile-payment-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 12px;
}

.mobile-payment-borrower {
    font-size: .88rem;
    font-weight: 700;
    line-height: 1.35;
    word-break: break-word;
}

.mobile-payment-reference {
    font-size: .68rem;
    color: var(--bs-secondary-color);
    margin-top: 3px;
}

.mobile-payment-date {
    font-size: .74rem;
    font-weight: 600;
    margin-top: 9px;
}

.mobile-payment-amount {
    font-size: .95rem;
    font-weight: 700;
    text-align: right;
    white-space: nowrap;
}

.mobile-payment-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    padding-top: 11px;
    border-top: 1px solid var(--bs-border-color);
}

.mobile-payment-label {
    display: block;
    font-size: .61rem;
    color: var(--bs-secondary-color);
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-bottom: 3px;
}

.mobile-payment-value {
    font-size: .78rem;
    font-weight: 600;
}

.mobile-transaction {
    border: 1px solid var(--bs-border-color);
    border-radius: 13px;
    padding: 13px;
    margin-bottom: 10px;
    background: var(--bs-body-bg);
}

.mobile-transaction:last-child {
    margin-bottom: 0;
}

.mobile-transaction-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 9px;
}

.mobile-transaction-description {
    font-size: .82rem;
    font-weight: 600;
    line-height: 1.35;
    word-break: break-word;
}

.mobile-transaction-date {
    font-size: .68rem;
    color: var(--bs-secondary-color);
}

.mobile-transaction-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    padding-top: 9px;
    border-top: 1px solid var(--bs-border-color);
}

.mobile-transaction-amount {
    font-size: .9rem;
    font-weight: 700;
    white-space: nowrap;
}

.summary-strip {
    border: 1px solid var(--bs-border-color);
    border-radius: 14px;
    background: var(--bs-tertiary-bg);
}

.summary-item {
    padding: 12px 16px;
}

.summary-label {
    display: block;
    font-size: .64rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--bs-secondary-color);
}

.summary-value {
    display: block;
    font-size: .9rem;
    font-weight: 700;
    margin-top: 2px;
}

@media (max-width: 767.98px) {
    .payment-table-wrapper {
        display: none;
    }

    .payment-mobile {
        display: block;
        padding: 0 12px 12px;
    }

    .payment-card-header {
        padding: 17px;
    }

    .payment-filter {
        width: 100%;
    }

    .payment-filter .btn-group {
        width: 100%;
    }

    .payment-filter .btn {
        flex: 1;
    }

    .transaction-table {
        display: none;
    }

    .transaction-mobile {
        display: block;
        padding: 0 12px 12px;
    }

    .transaction-header {
        padding: 16px !important;
    }
}

@media (max-width: 575.98px) {
    .dashboard-main {
        padding: 0 !important;
    }

    .dashboard-main > .dashboard-container {
        padding: 14px !important;
    }

    .dashboard-header {
        margin-bottom: 18px !important;
    }

    .dashboard-header h2 {
        font-size: 1.35rem;
    }

    .dashboard-header p {
        font-size: .76rem;
        line-height: 1.4;
    }

    .issue-loan-btn {
        width: 100%;
        justify-content: center;
    }

    .metric-row {
        --bs-gutter-x: 10px;
        --bs-gutter-y: 10px;
    }

    .metric-card {
        border-radius: 14px;
    }

    .metric-card .card-body {
        padding: 16px;
    }

    .metric-icon {
        width: 40px;
        height: 40px;
        border-radius: 11px;
        font-size: 1rem;
    }

    .metric-label {
        font-size: .61rem;
        margin-bottom: 4px !important;
    }

    .metric-value {
        font-size: 1.2rem;
    }

    .metric-description {
        font-size: .64rem;
    }

    .quick-actions {
        border-radius: 14px;
    }

    .quick-action {
        min-height: 78px;
    }

    .quick-action-icon {
        width: 36px;
        height: 36px;
    }

    .payment-card {
        border-radius: 14px;
    }

    .payment-card-header {
        padding: 15px !important;
    }

    .payment-card-header h5 {
        font-size: .95rem !important;
    }

    .payment-card-header p {
        font-size: .69rem;
    }

    .payment-card-header .btn-group {
        width: 100%;
    }

    .payment-card-header .btn {
        flex: 1;
    }

    .mobile-payment {
        padding: 13px;
    }

    .mobile-payment-borrower {
        font-size: .83rem;
    }

    .mobile-payment-reference {
        font-size: .65rem;
    }

    .mobile-payment-date {
        font-size: .7rem;
    }

    .mobile-payment-label {
        font-size: .59rem;
    }

    .mobile-payment-value {
        font-size: .74rem;
    }

    .mobile-payment-amount {
        font-size: .87rem;
    }

    .transaction-card {
        border-radius: 14px;
    }

    .transaction-header {
        padding: 15px !important;
    }

    .transaction-header h5 {
        font-size: .94rem !important;
    }

    .summary-item {
        padding: 10px 12px;
    }

    .summary-label {
        font-size: .58rem;
    }

    .summary-value {
        font-size: .78rem;
    }

    .empty-state {
        padding: 35px 15px;
    }
}
</style>
</head>

<body class="bg-body-tertiary">

<div class="d-flex flex-column flex-lg-row min-vh-100">

<?php include __DIR__ . '/../../resources/partials/loansidebar.php'; ?>

<main class="dashboard-main flex-grow-1 bg-body-tertiary">

<div class="dashboard-container p-3 p-md-4">

<!-- =========================================================
     HEADER
========================================================= -->

<div class="dashboard-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

    <div class="d-flex align-items-center gap-3">

        <div class="welcome-icon bg-primary bg-opacity-10 text-primary d-lg-none">
            <i class="bi bi-speedometer2"></i>
        </div>

        <div>
            <h2 class="fw-bold text-body mb-1">
                Dashboard
            </h2>

            <p class="text-muted small mb-0">
                Welcome back. Managing
                <span class="fw-semibold text-primary">
                    <?= e($businessName) ?>
                </span>
            </p>
        </div>

    </div>



</div>

<!-- =========================================================
     METRICS
========================================================= -->

<div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 metric-row g-3 mb-4">

    <!-- ACTIVE LOANS -->
    <div class="col">
        <div class="card metric-card shadow-sm bg-body h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between align-items-start gap-3">

                    <div class="min-width-0">

                        <div class="metric-label text-muted fw-bold mb-2">
                            Active Loans
                        </div>

                        <div class="metric-value fw-bold text-primary">
                            <?= number_format($activeLoanCount) ?>
                        </div>

                        <div class="metric-description text-muted mt-2">
                            Total value:
                            <span class="fw-semibold text-body">
                                <?= money($activeLoanAmount) ?>
                            </span>
                        </div>

                    </div>

                    <div class="metric-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>

                </div>

            </div>

        </div>
    </div>

    <!-- AVAILABLE FUNDS -->
    <div class="col">
        <div class="card metric-card shadow-sm bg-body h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between align-items-start gap-3">

                    <div class="min-width-0">

                        <div class="metric-label text-muted fw-bold mb-2">
                            Available Funds
                        </div>

                        <div class="metric-value fw-bold text-success text-truncate">
                            <?= money($totalFunds) ?>
                        </div>

                        <div class="metric-description text-muted mt-2">
                            Across your accounts
                        </div>

                    </div>

                    <div class="metric-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-wallet2"></i>
                    </div>

                </div>

            </div>

        </div>
    </div>

    <!-- BORROWERS -->
    <div class="col">
        <div class="card metric-card shadow-sm bg-body h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between align-items-start gap-3">

                    <div class="min-width-0">

                        <div class="metric-label text-muted fw-bold mb-2">
                            Total Borrowers
                        </div>

                        <div class="metric-value fw-bold text-warning">
                            <?= number_format($totalBorrowers) ?>
                        </div>

                        <div class="metric-description text-muted mt-2">
                            Your registered clients
                        </div>

                    </div>

                    <div class="metric-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-people"></i>
                    </div>

                </div>

            </div>

        </div>
    </div>

    <!-- OVERDUE -->
    <div class="col">
        <div class="card metric-card shadow-sm bg-body h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between align-items-start gap-3">

                    <div class="min-width-0">

                        <div class="metric-label text-muted fw-bold mb-2">
                            Overdue Amount
                        </div>

                        <div class="metric-value fw-bold text-danger text-truncate">
                            <?= money($overdueTotal) ?>
                        </div>

                        <div class="metric-description text-muted mt-2">

                            <?php if ($overdueCount > 0): ?>

                                <span class="text-danger fw-semibold">
                                    <?= $overdueCount ?>
                                </span>
                                overdue payment(s)

                            <?php else: ?>

                                All payments are up to date

                            <?php endif; ?>

                        </div>

                    </div>

                    <div class="metric-icon bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>

                </div>

            </div>

        </div>
    </div>

</div>

<!-- =========================================================
     QUICK ACTIONS
========================================================= -->

<div class="card quick-actions shadow-sm bg-body mb-4">

    <div class="card-body p-3 p-md-4">

        <div class="d-flex align-items-center justify-content-between mb-3">

            <div>

                <h6 class="fw-bold mb-1">
                    Quick Actions
                </h6>

                <p class="text-muted small mb-0">
                    Frequently used loan management actions
                </p>

            </div>

            <i class="bi bi-lightning-charge text-warning fs-5"></i>

        </div>

        <div class="row g-2">

            <div class="col-6 col-md-3">

                <a
                    href="index.php?page=loans"
                    class="quick-action d-flex align-items-center gap-3 p-3 text-decoration-none text-body"
                >

                    <div class="quick-action-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-plus-lg"></i>
                    </div>

                    <div class="min-width-0">

                        <div class="fw-bold small">
                            New Loan
                        </div>

                        <div class="text-muted small d-none d-sm-block">
                            Issue loan
                        </div>

                    </div>

                </a>

            </div>

            <div class="col-6 col-md-3">

                <a
                    href="index.php?page=borrowers"
                    class="quick-action d-flex align-items-center gap-3 p-3 text-decoration-none text-body"
                >

                    <div class="quick-action-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-person-plus"></i>
                    </div>

                    <div class="min-width-0">

                        <div class="fw-bold small">
                            Borrowers
                        </div>

                        <div class="text-muted small d-none d-sm-block">
                            Manage clients
                        </div>

                    </div>

                </a>

            </div>

            <div class="col-6 col-md-3">

                <a
                    href="index.php?page=loan_accounts"
                    class="quick-action d-flex align-items-center gap-3 p-3 text-decoration-none text-body"
                >

                    <div class="quick-action-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-wallet2"></i>
                    </div>

                    <div class="min-width-0">

                        <div class="fw-bold small">
                            Accounts
                        </div>

                        <div class="text-muted small d-none d-sm-block">
                            Manage funds
                        </div>

                    </div>

                </a>

            </div>

            <div class="col-6 col-md-3">

                <a
                    href="index.php?page=loans"
                    class="quick-action d-flex align-items-center gap-3 p-3 text-decoration-none text-body"
                >

                    <div class="quick-action-icon bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-search"></i>
                    </div>

                    <div class="min-width-0">

                        <div class="fw-bold small">
                            View Loans
                        </div>

                        <div class="text-muted small d-none d-sm-block">
                            Review portfolio
                        </div>

                    </div>

                </a>

            </div>

        </div>

    </div>

</div>

<!-- =========================================================
     UPCOMING PAYMENTS
========================================================= -->

<div class="card payment-card upcoming-card shadow-sm bg-body mb-4">

    <div class="payment-card-header">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

            <div>

                <div class="d-flex align-items-center gap-3">

                    <div class="section-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-calendar-check"></i>
                    </div>

                    <div>

                        <h5 class="fw-bold mb-1 text-body">
                            Upcoming Payments
                        </h5>

                        <p class="text-muted small mb-0">
                            Payments due within the selected period
                        </p>

                    </div>

                </div>

            </div>

            <div class="payment-filter d-flex align-items-center gap-2">

                <span class="small text-muted text-nowrap">
                    Show next:
                </span>

                <div class="btn-group">

                    <a
                        href="index.php?page=dashboard&upcoming_days=15"
                        class="btn btn-sm <?= $upcomingDays === 15 ? 'btn-primary' : 'btn-outline-primary' ?> fw-semibold"
                    >
                        15 Days
                    </a>

                    <a
                        href="index.php?page=dashboard&upcoming_days=30"
                        class="btn btn-sm <?= $upcomingDays === 30 ? 'btn-primary' : 'btn-outline-primary' ?> fw-semibold"
                    >
                        30 Days
                    </a>

                </div>

            </div>

        </div>

        <div class="summary-strip d-flex flex-wrap mt-3">

            <div class="summary-item flex-fill">

                <span class="summary-label">
                    Payments
                </span>

                <span class="summary-value text-primary">
                    <?= $upcomingCount ?>
                </span>

            </div>

            <div class="summary-item flex-fill">

                <span class="summary-label">
                    Expected
                </span>

                <span class="summary-value text-success">
                    <?= money($upcomingTotal) ?>
                </span>

            </div>

            <div class="summary-item flex-fill">

                <span class="summary-label">
                    Period
                </span>

                <span class="summary-value">
                    Next <?= $upcomingDays ?> days
                </span>

            </div>

        </div>

    </div>

    <!-- DESKTOP -->

    <div class="payment-table-wrapper">

        <table class="table payment-table table-hover align-middle mb-0">

            <thead class="table-light text-uppercase text-muted">

                <tr>

                    <th class="py-3 ps-4">
                        Payment Date
                    </th>

                    <th class="py-3">
                        Borrower
                    </th>

                    <th class="py-3">
                        Loan Reference
                    </th>

                    <th class="py-3 text-end">
                        Amount Due
                    </th>

                    <th class="py-3 text-center">
                        Status
                    </th>

                    <th class="py-3 text-end pe-4">
                        Days
                    </th>

                </tr>

            </thead>

            <tbody>

            <?php if (empty($upcomingPayments)): ?>

                <tr>

                    <td colspan="6">

                        <div class="empty-state text-center text-muted">

                            <div class="empty-icon bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-calendar2-check"></i>
                            </div>

                            <div class="fw-semibold mb-1">
                                No upcoming payments
                            </div>

                            <div class="small">
                                There are no unpaid scheduled payments within the next
                                <?= $upcomingDays ?> days.
                            </div>

                        </div>

                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($upcomingPayments as $payment): ?>

                    <?php
                    $dueDate = new DateTime($payment['due_date']);
                    $today = new DateTime(date('Y-m-d'));
                    $daysRemaining = (int)$today->diff($dueDate)->format('%r%a');

                    $statusClass = paymentStatusClass($daysRemaining);
                    $statusLabel = paymentStatusLabel($daysRemaining);
                    ?>

                    <tr>

                        <td class="ps-4">

                            <div class="payment-date text-body">

                                <i class="bi bi-calendar-event text-primary me-1"></i>

                                <?= date('M d, Y', strtotime($payment['due_date'])) ?>

                            </div>

                        </td>

                        <td>

                            <div class="fw-bold text-body">
                                <?= e($payment['borrower_name']) ?>
                            </div>

                        </td>

                        <td>

                            <span class="badge bg-secondary bg-opacity-10 text-body fw-semibold">
                                <?= e(loanReference($payment)) ?>
                            </span>

                        </td>

                        <td class="text-end">

                            <span class="fw-bold text-body">
                                <?= money($payment['amount_due']) ?>
                            </span>

                        </td>

                        <td class="text-center">

                            <span class="badge bg-<?= $statusClass ?> bg-opacity-10 text-<?= $statusClass ?> due-soon-badge">

                                <?php if ($daysRemaining === 0): ?>

                                    <i class="bi bi-exclamation-circle me-1"></i>

                                <?php elseif ($daysRemaining <= 3): ?>

                                    <i class="bi bi-clock me-1"></i>

                                <?php endif; ?>

                                <?= $statusLabel ?>

                            </span>

                        </td>

                        <td class="text-end pe-4">

                            <?php if ($daysRemaining === 0): ?>

                                <span class="fw-bold text-warning">
                                    Today
                                </span>

                            <?php else: ?>

                                <span class="text-muted small">
                                    <?= $daysRemaining ?> day(s)
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

    <!-- MOBILE -->

    <div class="payment-mobile">

        <?php if (empty($upcomingPayments)): ?>

            <div class="empty-state text-center text-muted">

                <div class="empty-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-calendar2-check"></i>
                </div>

                <div class="fw-semibold mb-1">
                    No upcoming payments
                </div>

                <div class="small">
                    No unpaid scheduled payments within the next
                    <?= $upcomingDays ?> days.
                </div>

            </div>

        <?php else: ?>

            <?php foreach ($upcomingPayments as $payment): ?>

                <?php
                $dueDate = new DateTime($payment['due_date']);
                $today = new DateTime(date('Y-m-d'));
                $daysRemaining = (int)$today->diff($dueDate)->format('%r%a');

                $statusClass = paymentStatusClass($daysRemaining);
                $statusLabel = paymentStatusLabel($daysRemaining);
                ?>

                <div class="mobile-payment">

                    <div class="mobile-payment-top">

                        <div class="flex-grow-1 min-width-0">

                            <div class="mobile-payment-borrower">
                                <?= e($payment['borrower_name']) ?>
                            </div>

                            <div class="mobile-payment-reference">
                                <?= e(loanReference($payment)) ?>
                            </div>

                            <div class="mobile-payment-date text-primary">

                                <i class="bi bi-calendar-event me-1"></i>

                                <?= date('M d, Y', strtotime($payment['due_date'])) ?>

                            </div>

                        </div>

                        <div class="mobile-payment-amount text-body">
                            <?= money($payment['amount_due']) ?>
                        </div>

                    </div>

                    <div class="mobile-payment-info">

                        <div>

                            <span class="mobile-payment-label">
                                Status
                            </span>

                            <span class="badge bg-<?= $statusClass ?> bg-opacity-10 text-<?= $statusClass ?>">
                                <?= $statusLabel ?>
                            </span>

                        </div>

                        <div class="text-end">

                            <span class="mobile-payment-label">
                                Days
                            </span>

                            <?php if ($daysRemaining === 0): ?>

                                <span class="mobile-payment-value text-warning">
                                    Today
                                </span>

                            <?php else: ?>

                                <span class="mobile-payment-value">
                                    <?= $daysRemaining ?> day(s)
                                </span>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>

<!-- =========================================================
     OVERDUE PAYMENTS
========================================================= -->

<div class="card payment-card overdue-card shadow-sm bg-body mb-4">

    <div class="payment-card-header">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

            <div>

                <div class="d-flex align-items-center gap-3">

                    <div class="section-icon bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>

                    <div>

                        <h5 class="fw-bold mb-1 text-body">
                            Overdue Payments
                        </h5>

                        <p class="text-muted small mb-0">
                            Payments that have passed their due date
                        </p>

                    </div>

                </div>

            </div>

            <div class="text-md-end">

                <div class="fw-bold text-danger fs-5">
                    <?= money($overdueTotal) ?>
                </div>

                <div class="small text-muted">
                    <?= $overdueCount ?> overdue payment(s)
                </div>

            </div>

        </div>

    </div>

    <!-- DESKTOP -->

    <div class="payment-table-wrapper">

        <table class="table payment-table table-hover align-middle mb-0">

            <thead class="table-light text-uppercase text-muted">

                <tr>

                    <th class="py-3 ps-4">
                        Due Date
                    </th>

                    <th class="py-3">
                        Borrower
                    </th>

                    <th class="py-3">
                        Loan Reference
                    </th>

                    <th class="py-3 text-end">
                        Amount Due
                    </th>

                    <th class="py-3 text-center">
                        Status
                    </th>

                    <th class="py-3 text-end pe-4">
                        Overdue
                    </th>

                </tr>

            </thead>

            <tbody>

            <?php if (empty($overduePayments)): ?>

                <tr>

                    <td colspan="6">

                        <div class="empty-state text-center text-muted">

                            <div class="empty-icon bg-success bg-opacity-10 text-success">
                                <i class="bi bi-check-circle"></i>
                            </div>

                            <div class="fw-semibold text-success mb-1">
                                No overdue payments
                            </div>

                            <div class="small">
                                Great! All your scheduled payments are currently up to date.
                            </div>

                        </div>

                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($overduePayments as $payment): ?>

                    <?php
                    $dueDate = new DateTime($payment['due_date']);
                    $today = new DateTime(date('Y-m-d'));
                    $daysOverdue = (int)$dueDate->diff($today)->format('%a');
                    ?>

                    <tr>

                        <td class="ps-4">

                            <div class="payment-date text-danger">

                                <i class="bi bi-calendar-x me-1"></i>

                                <?= date('M d, Y', strtotime($payment['due_date'])) ?>

                            </div>

                        </td>

                        <td>

                            <div class="fw-bold text-body">
                                <?= e($payment['borrower_name']) ?>
                            </div>

                        </td>

                        <td>

                            <span class="badge bg-secondary bg-opacity-10 text-body fw-semibold">
                                <?= e(loanReference($payment)) ?>
                            </span>

                        </td>

                        <td class="text-end">

                            <span class="fw-bold text-danger">
                                <?= money($payment['amount_due']) ?>
                            </span>

                        </td>

                        <td class="text-center">

                            <?php if ($payment['schedule_status'] === 'partially_paid'): ?>

                                <span class="badge bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-clock-history me-1"></i>
                                    Partially Paid
                                </span>

                            <?php else: ?>

                                <span class="badge bg-danger bg-opacity-10 text-danger">
                                    <i class="bi bi-exclamation-circle me-1"></i>
                                    Overdue
                                </span>

                            <?php endif; ?>

                        </td>

                        <td class="text-end pe-4">

                            <span class="fw-bold text-danger">
                                <?= $daysOverdue ?> day(s)
                            </span>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

    <!-- MOBILE -->

    <div class="payment-mobile">

        <?php if (empty($overduePayments)): ?>

            <div class="empty-state text-center text-muted">

                <div class="empty-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-check-circle"></i>
                </div>

                <div class="fw-semibold text-success mb-1">
                    No overdue payments
                </div>

                <div class="small">
                    Great! All your scheduled payments are currently up to date.
                </div>

            </div>

        <?php else: ?>

            <?php foreach ($overduePayments as $payment): ?>

                <?php
                $dueDate = new DateTime($payment['due_date']);
                $today = new DateTime(date('Y-m-d'));
                $daysOverdue = (int)$dueDate->diff($today)->format('%a');
                ?>

                <div class="mobile-payment">

                    <div class="mobile-payment-top">

                        <div class="flex-grow-1 min-width-0">

                            <div class="mobile-payment-borrower">
                                <?= e($payment['borrower_name']) ?>
                            </div>

                            <div class="mobile-payment-reference">
                                <?= e(loanReference($payment)) ?>
                            </div>

                            <div class="mobile-payment-date text-danger">

                                <i class="bi bi-calendar-x me-1"></i>

                                <?= date('M d, Y', strtotime($payment['due_date'])) ?>

                            </div>

                        </div>

                        <div class="mobile-payment-amount text-danger">
                            <?= money($payment['amount_due']) ?>
                        </div>

                    </div>

                    <div class="mobile-payment-info">

                        <div>

                            <span class="mobile-payment-label">
                                Status
                            </span>

                            <?php if ($payment['schedule_status'] === 'partially_paid'): ?>

                                <span class="badge bg-warning bg-opacity-10 text-warning">
                                    Partially Paid
                                </span>

                            <?php else: ?>

                                <span class="badge bg-danger bg-opacity-10 text-danger">
                                    Overdue
                                </span>

                            <?php endif; ?>

                        </div>

                        <div class="text-end">

                            <span class="mobile-payment-label">
                                Overdue
                            </span>

                            <span class="mobile-payment-value text-danger">
                                <?= $daysOverdue ?> day(s)
                            </span>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>

<!-- =========================================================
     RECENT TRANSACTIONS
========================================================= -->

<div class="card transaction-card shadow-sm bg-body">

    <div class="transaction-header card-header bg-transparent border-0 px-4 pt-4 pb-3">

        <div class="d-flex justify-content-between align-items-center gap-3">

            <div>

                <h5 class="fw-bold mb-1 text-body">
                    Recent Transactions
                </h5>

                <p class="text-muted small mb-0">
                    Latest activity from your accounts
                </p>

            </div>

            <a
                href="index.php?page=loan_accounts"
                class="small text-decoration-none fw-semibold text-nowrap"
            >
                View All
                <i class="bi bi-arrow-right ms-1"></i>
            </a>

        </div>

    </div>

    <!-- DESKTOP -->

    <div class="transaction-table table-responsive">

        <table class="table table-hover align-middle mb-0">

            <thead class="table-light text-uppercase text-muted">

                <tr>

                    <th class="py-3 ps-4">
                        Date / Time
                    </th>

                    <th class="py-3">
                        Type
                    </th>

                    <th class="py-3">
                        Description
                    </th>

                    <th class="py-3 text-end pe-4">
                        Amount
                    </th>

                </tr>

            </thead>

            <tbody>

            <?php if (empty($recentTransactions)): ?>

                <tr>

                    <td colspan="4">

                        <div class="empty-state text-center text-muted">

                            <div class="empty-icon bg-secondary bg-opacity-10 text-secondary">
                                <i class="bi bi-receipt"></i>
                            </div>

                            <div class="fw-semibold mb-1">
                                No recent transactions
                            </div>

                            <div class="small">
                                Transactions will appear here when account activity occurs.
                            </div>

                        </div>

                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($recentTransactions as $tx): ?>

                    <?php
                    $isCredit = strtoupper((string)$tx['type']) === 'CREDIT';
                    $typeClass = $isCredit ? 'success' : 'danger';
                    ?>

                    <tr>

                        <td class="ps-4 text-muted small">

                            <i class="bi bi-calendar3 me-1"></i>

                            <?= date('M d, Y', strtotime($tx['created_at'])) ?>

                            <div class="ms-4 small opacity-75">
                                <?= date('h:i A', strtotime($tx['created_at'])) ?>
                            </div>

                        </td>

                        <td>

                            <span class="badge bg-<?= $typeClass ?> bg-opacity-10 text-<?= $typeClass ?> px-2 py-1 fw-semibold">

                                <i class="bi <?= $isCredit ? 'bi-arrow-down-left' : 'bi-arrow-up-right' ?> me-1"></i>

                                <?= e($tx['type']) ?>

                            </span>

                        </td>

                        <td class="fw-semibold text-body">

                            <?= e($tx['description']) ?>

                        </td>

                        <td class="text-end pe-4 fw-bold text-<?= $typeClass ?>">

                            <?= $isCredit ? '+' : '-' ?><?= money($tx['amount']) ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

    <!-- MOBILE -->

    <div class="transaction-mobile">

        <?php if (empty($recentTransactions)): ?>

            <div class="empty-state text-center text-muted">

                <div class="empty-icon bg-secondary bg-opacity-10 text-secondary">
                    <i class="bi bi-receipt"></i>
                </div>

                <div class="fw-semibold mb-1">
                    No recent transactions
                </div>

                <div class="small">
                    Transactions will appear here automatically.
                </div>

            </div>

        <?php else: ?>

            <?php foreach ($recentTransactions as $tx): ?>

                <?php
                $isCredit = strtoupper((string)$tx['type']) === 'CREDIT';
                $typeClass = $isCredit ? 'success' : 'danger';
                ?>

                <div class="mobile-transaction">

                    <div class="mobile-transaction-top">

                        <div class="flex-grow-1 min-width-0">

                            <div class="mobile-transaction-description text-body">
                                <?= e($tx['description']) ?>
                            </div>

                            <div class="mobile-transaction-date mt-1">

                                <i class="bi bi-calendar3 me-1"></i>

                                <?= date('M d, Y h:i A', strtotime($tx['created_at'])) ?>

                            </div>

                        </div>

                        <span class="badge bg-<?= $typeClass ?> bg-opacity-10 text-<?= $typeClass ?> fw-semibold">

                            <?= e($tx['type']) ?>

                        </span>

                    </div>

                    <div class="mobile-transaction-bottom">

                        <span class="small text-muted">
                            Account Transaction
                        </span>

                        <span class="mobile-transaction-amount text-<?= $typeClass ?>">

                            <?= $isCredit ? '+' : '-' ?><?= money($tx['amount']) ?>

                        </span>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>

</div>

</main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>