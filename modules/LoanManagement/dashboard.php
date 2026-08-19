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

$businessName = $_SESSION['business_name'] ?? 'Business';

$activePage = 'dashboard';
$pageTitle = 'Dashboard - Loan Management';

/*
|--------------------------------------------------------------------------
| HELPERS
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

/*
|--------------------------------------------------------------------------
| DASHBOARD SUMMARY
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| 1. TOTAL ACTIVE LOANS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM loans
    WHERE business_id = ?
      AND created_by = ?
      AND status = 'active'
");

$stmt->execute([
    $businessId,
    $userId
]);

$totalActiveLoans = (int)($stmt->fetchColumn() ?: 0);


/*
|--------------------------------------------------------------------------
| 2. TOTAL PRINCIPAL RELEASED
|--------------------------------------------------------------------------
|
| Principal released = principal_amount of active loans.
|
*/

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(principal_amount), 0)
    FROM loans
    WHERE business_id = ?
      AND created_by = ?
      AND status IN ('active', 'overdue', 'completed')
");

$stmt->execute([
    $businessId,
    $userId
]);

$totalPrincipalReleased = (float)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| 3. TOTAL OUTSTANDING BALANCE
|--------------------------------------------------------------------------
|
| We use loan_schedules.balance_due.
|
*/

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(s.balance_due), 0)
    FROM loan_schedules s
    INNER JOIN loans l
        ON l.id = s.loan_id
       AND l.business_id = s.business_id
    WHERE s.business_id = ?
      AND l.created_by = ?
      AND l.status IN ('active', 'overdue')
      AND s.status IN ('unpaid', 'partially_paid', 'overdue')
");

$stmt->execute([
    $businessId,
    $userId
]);

$totalOutstanding = (float)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| 4. TOTAL COLLECTED
|--------------------------------------------------------------------------
|
| Payments are taken directly from loan_payments.
|
*/

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(payment_amount), 0)
    FROM loan_payments
    WHERE business_id = ?
      AND created_by = ?
");

$stmt->execute([
    $businessId,
    $userId
]);

$totalCollected = (float)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| 5. OVERDUE AMOUNT
|--------------------------------------------------------------------------
|
| balance_due is used instead of amount_due so partially-paid
| schedules only count their remaining balance.
|
*/

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(s.balance_due), 0)
    FROM loan_schedules s
    INNER JOIN loans l
        ON l.id = s.loan_id
       AND l.business_id = s.business_id
    WHERE s.business_id = ?
      AND l.created_by = ?
      AND l.status IN ('active', 'overdue')
      AND s.status IN ('unpaid', 'partially_paid', 'overdue')
      AND s.due_date < CURDATE()
");

$stmt->execute([
    $businessId,
    $userId
]);

$overdueAmount = (float)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| 6. ACTIVE BORROWERS
|--------------------------------------------------------------------------
|
| Borrowers with at least one active/overdue loan.
|
*/

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT l.borrower_id)
    FROM loans l
    WHERE l.business_id = ?
      AND l.created_by = ?
      AND l.status IN ('active', 'overdue')
");

$stmt->execute([
    $businessId,
    $userId
]);

$activeBorrowers = (int)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| 7. LOANS DUE TODAY
|--------------------------------------------------------------------------
|
| Counts schedules due today that are still unpaid/partially paid.
|
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM loan_schedules s
    INNER JOIN loans l
        ON l.id = s.loan_id
       AND l.business_id = s.business_id
    WHERE s.business_id = ?
      AND l.created_by = ?
      AND l.status IN ('active', 'overdue')
      AND s.due_date = CURDATE()
      AND s.status IN ('unpaid', 'partially_paid', 'overdue')
");

$stmt->execute([
    $businessId,
    $userId
]);

$loansDueToday = (int)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| 8. TODAY'S COLLECTION
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(payment_amount), 0)
    FROM loan_payments
    WHERE business_id = ?
      AND created_by = ?
      AND payment_date = CURDATE()
");

$stmt->execute([
    $businessId,
    $userId
]);

$todayCollected = (float)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| COLLECTION PERFORMANCE
|--------------------------------------------------------------------------
|
| Last 7 days.
|
*/

$stmt = $pdo->prepare("
    SELECT
        payment_date,
        COALESCE(SUM(payment_amount), 0) AS total
    FROM loan_payments
    WHERE business_id = ?
      AND created_by = ?
      AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      AND payment_date <= CURDATE()
    GROUP BY payment_date
    ORDER BY payment_date ASC
");

$stmt->execute([
    $businessId,
    $userId
]);

$collectionRows = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| BUILD LAST 7 DAYS
|--------------------------------------------------------------------------
*/

$collectionLabels = [];
$collectionValues = [];

$collectionMap = [];

foreach ($collectionRows as $row) {
    $collectionMap[$row['payment_date']] = (float)$row['total'];
}

for ($i = 6; $i >= 0; $i--) {

    $date = date(
        'Y-m-d',
        strtotime("-{$i} days")
    );

    $collectionLabels[] = date(
        'M d',
        strtotime($date)
    );

    $collectionValues[] = $collectionMap[$date] ?? 0;
}


/*
|--------------------------------------------------------------------------
| 9. RECENT PAYMENTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.payment_amount,
        p.payment_date,
        p.created_at,
        p.notes,
        l.id AS loan_id,
        l.reference_number,
        CONCAT(
            b.first_name,
            ' ',
            b.last_name
        ) AS borrower_name
    FROM loan_payments p

    INNER JOIN loans l
        ON l.id = p.loan_id
       AND l.business_id = p.business_id
       AND l.created_by = ?

    INNER JOIN loan_borrowers b
        ON b.id = l.borrower_id
       AND b.business_id = l.business_id
       AND b.created_by = ?

    WHERE p.business_id = ?
      AND p.created_by = ?

    ORDER BY p.payment_date DESC, p.created_at DESC

    LIMIT 8
");

$stmt->execute([
    $userId,
    $userId,
    $businessId,
    $userId
]);

$recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| 10. LOANS DUE TODAY LIST
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        s.id AS schedule_id,
        s.loan_id,
        s.installment_number,
        s.amount_due,
        s.amount_paid,
        s.balance_due,
        s.status AS schedule_status,
        l.reference_number,
        CONCAT(
            b.first_name,
            ' ',
            b.last_name
        ) AS borrower_name
    FROM loan_schedules s

    INNER JOIN loans l
        ON l.id = s.loan_id
       AND l.business_id = s.business_id
       AND l.created_by = ?

    INNER JOIN loan_borrowers b
        ON b.id = l.borrower_id
       AND b.business_id = l.business_id
       AND b.created_by = ?

    WHERE s.business_id = ?
      AND l.created_by = ?
      AND l.status IN ('active', 'overdue')
      AND s.due_date = CURDATE()
      AND s.status IN ('unpaid', 'partially_paid', 'overdue')

    ORDER BY s.balance_due DESC
    LIMIT 10
");

$stmt->execute([
    $userId,
    $userId,
    $businessId,
    $userId
]);

$todayDuePayments = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| 11. RECENT OVERDUE PAYMENTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        s.id AS schedule_id,
        s.loan_id,
        s.due_date,
        s.balance_due,
        s.status AS schedule_status,
        l.reference_number,
        CONCAT(
            b.first_name,
            ' ',
            b.last_name
        ) AS borrower_name

    FROM loan_schedules s

    INNER JOIN loans l
        ON l.id = s.loan_id
       AND l.business_id = s.business_id
       AND l.created_by = ?

    INNER JOIN loan_borrowers b
        ON b.id = l.borrower_id
       AND b.business_id = l.business_id
       AND b.created_by = ?

    WHERE s.business_id = ?
      AND l.created_by = ?
      AND l.status IN ('active', 'overdue')
      AND s.status IN ('unpaid', 'partially_paid', 'overdue')
      AND s.due_date < CURDATE()

    ORDER BY s.due_date ASC

    LIMIT 8
");

$stmt->execute([
    $userId,
    $userId,
    $businessId,
    $userId
]);

$recentOverdue = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| COLLECTION RATE
|--------------------------------------------------------------------------
|
| Collected / (Collected + Outstanding)
|
*/

$collectionDenominator =
    $totalCollected + $totalOutstanding;

$collectionRate = $collectionDenominator > 0
    ? ($totalCollected / $collectionDenominator) * 100
    : 0;

$collectionRate = min(100, max(0, $collectionRate));

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title><?= e($pageTitle) ?></title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
    rel="stylesheet"
>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
(function () {

    const theme =
        localStorage.getItem('bs-theme') || 'light';

    document.documentElement.setAttribute(
        'data-bs-theme',
        theme
    );

})();
</script>

<style>

body {
    min-height: 100vh;
    overflow-x: hidden;
}

.dashboard-link-card {
    display: block;
    color: inherit;
    text-decoration: none;
}

.dashboard-link-card .metric-card {
    cursor: pointer;
}

.dashboard-link-card .metric-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.08) !important;
}

.dashboard-link-card .metric-card:active {
    transform: translateY(-1px);
}

.dashboard-link-card .metric-card .click-indicator {
    opacity: 0;
    transition: opacity .2s ease;
}

.dashboard-link-card:hover .click-indicator {
    opacity: 1;
}

@media (max-width: 575.98px) {
    .dashboard-link-card .metric-card {
        min-height: 130px;
    }

    .dashboard-link-card .metric-card .click-indicator {
        opacity: 1;
    }
}
.dashboard-main {
    min-width: 0;
    width: 100%;
}

.dashboard-container {
    width: 100%;
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


/* =========================================================
   METRIC CARDS
========================================================= */

.metric-card {
    border: 1px solid var(--bs-border-color);
    border-radius: 18px;
    overflow: hidden;
    transition:
        transform .2s ease,
        box-shadow .2s ease;
}

.metric-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.08) !important;
}

.metric-card .card-body {
    padding: 21px;
}

.metric-icon {
    width: 46px;
    height: 46px;
    min-width: 46px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
}

.metric-label {
    font-size: .66rem;
    letter-spacing: .07em;
    text-transform: uppercase;
}

.metric-value {
    font-size: 1.55rem;
    line-height: 1.2;
}

.metric-description {
    font-size: .72rem;
}


/* =========================================================
   MAIN CARDS
========================================================= */

.dashboard-card {
    border: 1px solid var(--bs-border-color);
    border-radius: 18px;
    overflow: hidden;
}

.dashboard-card-header {
    padding: 20px 22px;
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


/* =========================================================
   CHART
========================================================= */

.chart-wrapper {
    position: relative;
    width: 100%;
    height: 300px;
}

.chart-summary {
    border: 1px solid var(--bs-border-color);
    border-radius: 13px;
    background: var(--bs-tertiary-bg);
}

.chart-summary-item {
    padding: 12px 15px;
}

.chart-summary-label {
    display: block;
    font-size: .62rem;
    color: var(--bs-secondary-color);
    text-transform: uppercase;
    letter-spacing: .05em;
}

.chart-summary-value {
    display: block;
    font-size: .9rem;
    font-weight: 700;
    margin-top: 2px;
}


/* =========================================================
   TABLES
========================================================= */

.dashboard-table th {
    font-size: .65rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--bs-secondary-color);
    white-space: nowrap;
}

.dashboard-table td {
    padding-top: 14px;
    padding-bottom: 14px;
}

.dashboard-table tbody tr {
    transition: background .15s ease;
}

.dashboard-table tbody tr:hover {
    background: var(--bs-tertiary-bg);
}


/* =========================================================
   MOBILE PAYMENT CARDS
========================================================= */

.mobile-list {
    display: none;
}

.mobile-item {
    border: 1px solid var(--bs-border-color);
    border-radius: 14px;
    padding: 14px;
    margin-bottom: 10px;
    background: var(--bs-body-bg);
}

.mobile-item:last-child {
    margin-bottom: 0;
}

.mobile-item-top {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.mobile-item-name {
    font-size: .84rem;
    font-weight: 700;
    line-height: 1.35;
}

.mobile-item-reference {
    color: var(--bs-secondary-color);
    font-size: .65rem;
    margin-top: 3px;
}

.mobile-item-amount {
    font-size: .9rem;
    font-weight: 700;
    white-space: nowrap;
    text-align: right;
}

.mobile-item-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-top: 12px;
    padding-top: 11px;
    border-top: 1px solid var(--bs-border-color);
}

.mobile-label {
    display: block;
    font-size: .59rem;
    color: var(--bs-secondary-color);
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-bottom: 3px;
}

.mobile-value {
    font-size: .75rem;
    font-weight: 600;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state {
    padding: 42px 20px;
}

.empty-icon {
    width: 55px;
    height: 55px;
    border-radius: 15px;
    margin: 0 auto 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
}


/* =========================================================
   COLLECTION PROGRESS
========================================================= */

.collection-progress {
    height: 8px;
    border-radius: 20px;
}

.collection-rate {
    font-size: 1.7rem;
    font-weight: 700;
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 767.98px) {

    .dashboard-container {
        padding: 14px !important;
    }

    .dashboard-header {
        margin-bottom: 18px !important;
    }

    .dashboard-header h2 {
        font-size: 1.35rem;
    }

    .dashboard-header p {
        font-size: .74rem;
        line-height: 1.4;
    }

    .metric-row {
        --bs-gutter-x: 10px;
        --bs-gutter-y: 10px;
    }

    .metric-card {
        border-radius: 14px;
    }

    .metric-card .card-body {
        padding: 15px;
    }

    .metric-icon {
        width: 39px;
        height: 39px;
        min-width: 39px;
        border-radius: 10px;
        font-size: .95rem;
    }

    .metric-label {
        font-size: .58rem;
    }

    .metric-value {
        font-size: 1.12rem;
    }

    .metric-description {
        font-size: .61rem;
    }

    .dashboard-card {
        border-radius: 14px;
    }

    .dashboard-card-header {
        padding: 16px;
    }

    .dashboard-card-header h5 {
        font-size: .94rem;
    }

    .dashboard-card-header p {
        font-size: .68rem;
    }

    .chart-wrapper {
        height: 240px;
    }

    .dashboard-table-wrapper {
        display: none;
    }

    .mobile-list {
        display: block;
        padding: 0 12px 12px;
    }

    .collection-rate {
        font-size: 1.4rem;
    }

}


/* =========================================================
   VERY SMALL PHONES
========================================================= */

@media (max-width: 380px) {

    .dashboard-container {
        padding: 10px !important;
    }

    .metric-card .card-body {
        padding: 12px;
    }

    .metric-value {
        font-size: 1rem;
    }

    .metric-icon {
        width: 34px;
        height: 34px;
        min-width: 34px;
        font-size: .85rem;
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

<div class="dashboard-header d-flex align-items-center gap-3 mb-4">

    <div class="welcome-icon bg-primary bg-opacity-10 text-primary d-lg-none">
        <i class="bi bi-speedometer2"></i>
    </div>

    <div>

        <h2 class="fw-bold text-body mb-1">
            Dashboard
        </h2>

        <p class="text-muted small mb-0">
            Loan portfolio overview for
            <span class="fw-semibold text-primary">
                <?= e($businessName) ?>
            </span>
        </p>

    </div>

</div>


<!-- =========================================================
     TOP METRICS
========================================================= -->

<div class="row row-cols-2 row-cols-xl-4 metric-row g-3 mb-4">


    <!-- ACTIVE LOANS -->

    <div class="col">

        <div class="card metric-card shadow-sm bg-body h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between gap-2">

                    <div class="min-width-0">

                        <div class="metric-label text-muted fw-bold mb-2">
                            Active Loans
                        </div>

                        <div class="metric-value fw-bold text-primary">
                            <?= number_format($totalActiveLoans) ?>
                        </div>

                        <div class="metric-description text-muted mt-2">
                            Currently running
                        </div>

                    </div>

                    <div class="metric-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- PRINCIPAL RELEASED -->

    <div class="col">

        <div class="card metric-card shadow-sm bg-body h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between gap-2">

                    <div class="min-width-0">

                        <div class="metric-label text-muted fw-bold mb-2">
                            Principal Released
                        </div>

                        <div class="metric-value fw-bold text-success text-truncate">
                            <?= money($totalPrincipalReleased) ?>
                        </div>

                        <div class="metric-description text-muted mt-2">
                            Loan principal
                        </div>

                    </div>

                    <div class="metric-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-cash-stack"></i>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- OUTSTANDING -->

    <div class="col">

        <div class="card metric-card shadow-sm bg-body h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between gap-2">

                    <div class="min-width-0">

                        <div class="metric-label text-muted fw-bold mb-2">
                            Outstanding
                        </div>

                        <div class="metric-value fw-bold text-warning text-truncate">
                            <?= money($totalOutstanding) ?>
                        </div>

                        <div class="metric-description text-muted mt-2">
                            Remaining balance
                        </div>

                    </div>

                    <div class="metric-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-wallet2"></i>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- COLLECTED -->

    <div class="col">

        <div class="card metric-card shadow-sm bg-body h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between gap-2">

                    <div class="min-width-0">

                        <div class="metric-label text-muted fw-bold mb-2">
                            Total Collected
                        </div>

                        <div class="metric-value fw-bold text-info text-truncate">
                            <?= money($totalCollected) ?>
                        </div>

                        <div class="metric-description text-muted mt-2">
                            All recorded payments
                        </div>

                    </div>

                    <div class="metric-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     SECONDARY METRICS
========================================================= -->

<div class="row row-cols-2 row-cols-md-3 g-3 mb-4">


    <!-- OVERDUE -->

    <div class="col">

        <div class="card metric-card shadow-sm bg-body h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between gap-2">

                    <div class="min-width-0">

                        <div class="metric-label text-muted fw-bold mb-2">
                            Overdue Amount
                        </div>

                        <div class="metric-value fw-bold text-danger text-truncate">
                            <?= money($overdueAmount) ?>
                        </div>

                        <div class="metric-description text-muted mt-2">
                            Past due balance
                        </div>

                    </div>

                    <div class="metric-icon bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- ACTIVE BORROWERS -->

    <div class="col">

        <div class="card metric-card shadow-sm bg-body h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between gap-2">

                    <div class="min-width-0">

                        <div class="metric-label text-muted fw-bold mb-2">
                            Active Borrowers
                        </div>

                        <div class="metric-value fw-bold text-primary">
                            <?= number_format($activeBorrowers) ?>
                        </div>

                        <div class="metric-description text-muted mt-2">
                            With active loans
                        </div>

                    </div>

                    <div class="metric-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-people"></i>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- DUE TODAY -->

    <div class="col">

        <div class="card metric-card shadow-sm bg-body h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between gap-2">

                    <div class="min-width-0">

                        <div class="metric-label text-muted fw-bold mb-2">
                            Due Today
                        </div>

                        <div class="metric-value fw-bold text-warning">
                            <?= number_format($loansDueToday) ?>
                        </div>

                        <div class="metric-description text-muted mt-2">
                            Payment schedules
                        </div>

                    </div>

                    <div class="metric-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-calendar-event"></i>
                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     COLLECTION PERFORMANCE + SUMMARY
========================================================= -->

<div class="row g-3 mb-4">


    <!-- CHART -->

    <div class="col-12 col-xl-8">

        <div class="card dashboard-card shadow-sm bg-body h-100">

            <div class="dashboard-card-header">

                <div class="d-flex align-items-center gap-3">

                    <div class="section-icon bg-success bg-opacity-10 text-success">

                        <i class="bi bi-bar-chart-line"></i>

                    </div>

                    <div>

                        <h5 class="fw-bold mb-1">
                            Collection Performance
                        </h5>

                        <p class="text-muted small mb-0">
                            Payments collected over the last 7 days
                        </p>

                    </div>

                </div>

            </div>


            <div class="card-body pt-0">

                <div class="chart-wrapper">

                    <canvas id="collectionChart"></canvas>

                </div>

            </div>

        </div>

    </div>


    <!-- COLLECTION SUMMARY -->

    <div class="col-12 col-xl-4">

        <div class="card dashboard-card shadow-sm bg-body h-100">

            <div class="dashboard-card-header">

                <div class="d-flex align-items-center gap-3">

                    <div class="section-icon bg-primary bg-opacity-10 text-primary">

                        <i class="bi bi-clipboard-data"></i>

                    </div>

                    <div>

                        <h5 class="fw-bold mb-1">
                            Collection Summary
                        </h5>

                        <p class="text-muted small mb-0">
                            Current collection position
                        </p>

                    </div>

                </div>

            </div>


            <div class="card-body pt-0">


                <div class="mb-3">

                    <div class="d-flex justify-content-between align-items-center mb-2">

                        <span class="small text-muted">
                            Collection Rate
                        </span>

                        <span class="collection-rate text-primary">
                            <?= number_format($collectionRate, 1) ?>%
                        </span>

                    </div>

                    <div class="progress collection-progress">

                        <div
                            class="progress-bar bg-primary"
                            style="width: <?= $collectionRate ?>%"
                        ></div>

                    </div>

                </div>


                <div class="chart-summary">

                    <div class="chart-summary-item d-flex justify-content-between">

                        <span class="chart-summary-label">
                            Collected
                        </span>

                        <span class="chart-summary-value text-success">
                            <?= money($totalCollected) ?>
                        </span>

                    </div>


                    <div class="chart-summary-item d-flex justify-content-between border-top">

                        <span class="chart-summary-label">
                            Outstanding
                        </span>

                        <span class="chart-summary-value text-warning">
                            <?= money($totalOutstanding) ?>
                        </span>

                    </div>


                    <div class="chart-summary-item d-flex justify-content-between border-top">

                        <span class="chart-summary-label">
                            Overdue
                        </span>

                        <span class="chart-summary-value text-danger">
                            <?= money($overdueAmount) ?>
                        </span>

                    </div>


                    <div class="chart-summary-item d-flex justify-content-between border-top">

                        <span class="chart-summary-label">
                            Today's Collection
                        </span>

                        <span class="chart-summary-value text-primary">
                            <?= money($todayCollected) ?>
                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     DUE TODAY
========================================================= -->

<div class="card dashboard-card shadow-sm bg-body mb-4">

    <div class="dashboard-card-header">

        <div class="d-flex justify-content-between align-items-center gap-3">

            <div class="d-flex align-items-center gap-3">

                <div class="section-icon bg-warning bg-opacity-10 text-warning">

                    <i class="bi bi-calendar-day"></i>

                </div>

                <div>

                    <h5 class="fw-bold mb-1">
                        Loans Due Today
                    </h5>

                    <p class="text-muted small mb-0">
                        Payments requiring attention today
                    </p>

                </div>

            </div>

            <span class="badge bg-warning bg-opacity-10 text-warning">
                <?= $loansDueToday ?> Due
            </span>

        </div>

    </div>


    <?php if (empty($todayDuePayments)): ?>

        <div class="empty-state text-center text-muted">

            <div class="empty-icon bg-success bg-opacity-10 text-success">

                <i class="bi bi-check-circle"></i>

            </div>

            <div class="fw-semibold text-success mb-1">
                Nothing due today
            </div>

            <div class="small">
                There are no unpaid loan schedules due today.
            </div>

        </div>

    <?php else: ?>


        <!-- DESKTOP -->

        <div class="dashboard-table-wrapper table-responsive">

            <table class="table dashboard-table table-hover align-middle mb-0">

                <thead class="table-light">

                    <tr>

                        <th class="ps-4">
                            Borrower
                        </th>

                        <th>
                            Loan
                        </th>

                        <th>
                            Installment
                        </th>

                        <th class="text-end">
                            Amount Due
                        </th>

                        <th class="text-end pe-4">
                            Balance
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($todayDuePayments as $payment): ?>

                    <tr>

                        <td class="ps-4">

                            <div class="fw-bold">
                                <?= e($payment['borrower_name']) ?>
                            </div>

                        </td>

                        <td>

                            <span class="badge bg-secondary bg-opacity-10 text-body">
                                <?= e($payment['reference_number'] ?: '#' . $payment['loan_id']) ?>
                            </span>

                        </td>

                        <td>

                            <span class="small">
                                #<?= (int)$payment['installment_number'] ?>
                            </span>

                        </td>

                        <td class="text-end fw-semibold">

                            <?= money($payment['amount_due']) ?>

                        </td>

                        <td class="text-end pe-4 fw-bold text-warning">

                            <?= money($payment['balance_due']) ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>


        <!-- MOBILE -->

        <div class="mobile-list">

            <?php foreach ($todayDuePayments as $payment): ?>

                <div class="mobile-item">

                    <div class="mobile-item-top">

                        <div class="min-width-0">

                            <div class="mobile-item-name">
                                <?= e($payment['borrower_name']) ?>
                            </div>

                            <div class="mobile-item-reference">

                                <?= e(
                                    $payment['reference_number']
                                    ?: '#' . $payment['loan_id']
                                ) ?>

                                · Installment
                                #<?= (int)$payment['installment_number'] ?>

                            </div>

                        </div>

                        <div class="mobile-item-amount text-warning">

                            <?= money($payment['balance_due']) ?>

                        </div>

                    </div>


                    <div class="mobile-item-info">

                        <div>

                            <span class="mobile-label">
                                Amount Due
                            </span>

                            <span class="mobile-value">
                                <?= money($payment['amount_due']) ?>
                            </span>

                        </div>

                        <div class="text-end">

                            <span class="mobile-label">
                                Remaining
                            </span>

                            <span class="mobile-value text-warning">
                                <?= money($payment['balance_due']) ?>
                            </span>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>


<!-- =========================================================
     RECENT PAYMENTS + OVERDUE
========================================================= -->

<div class="row g-3 mb-4">


    <!-- RECENT PAYMENTS -->

    <div class="col-12 col-xl-7">

        <div class="card dashboard-card shadow-sm bg-body h-100">

            <div class="dashboard-card-header">

                <div class="d-flex justify-content-between align-items-center gap-3">

                    <div class="d-flex align-items-center gap-3">

                        <div class="section-icon bg-success bg-opacity-10 text-success">

                            <i class="bi bi-cash-coin"></i>

                        </div>

                        <div>

                            <h5 class="fw-bold mb-1">
                                Recent Payments
                            </h5>

                            <p class="text-muted small mb-0">
                                Latest loan collections
                            </p>

                        </div>

                    </div>

                    <a
                        href="index.php?page=payments"
                        class="small text-decoration-none fw-semibold text-nowrap"
                    >
                        View All
                        <i class="bi bi-arrow-right ms-1"></i>
                    </a>

                </div>

            </div>


            <?php if (empty($recentPayments)): ?>

                <div class="empty-state text-center text-muted">

                    <div class="empty-icon bg-secondary bg-opacity-10 text-secondary">

                        <i class="bi bi-receipt"></i>

                    </div>

                    <div class="fw-semibold mb-1">
                        No payments yet
                    </div>

                    <div class="small">
                        Recorded loan payments will appear here.
                    </div>

                </div>

            <?php else: ?>


                <!-- DESKTOP -->

                <div class="dashboard-table-wrapper table-responsive">

                    <table class="table dashboard-table table-hover align-middle mb-0">

                        <thead class="table-light">

                            <tr>

                                <th class="ps-4">
                                    Date
                                </th>

                                <th>
                                    Borrower
                                </th>

                                <th>
                                    Loan
                                </th>

                                <th class="text-end pe-4">
                                    Amount
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($recentPayments as $payment): ?>

                            <tr>

                                <td class="ps-4 text-muted small">

                                    <?= date(
                                        'M d, Y',
                                        strtotime($payment['payment_date'])
                                    ) ?>

                                </td>

                                <td>

                                    <div class="fw-semibold">
                                        <?= e($payment['borrower_name']) ?>
                                    </div>

                                </td>

                                <td>

                                    <span class="badge bg-secondary bg-opacity-10 text-body">

                                        <?= e(
                                            $payment['reference_number']
                                            ?: '#' . $payment['loan_id']
                                        ) ?>

                                    </span>

                                </td>

                                <td class="text-end pe-4">

                                    <span class="fw-bold text-success">

                                        +<?= money($payment['payment_amount']) ?>

                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


                <!-- MOBILE -->

                <div class="mobile-list">

                    <?php foreach ($recentPayments as $payment): ?>

                        <div class="mobile-item">

                            <div class="mobile-item-top">

                                <div class="min-width-0">

                                    <div class="mobile-item-name">

                                        <?= e($payment['borrower_name']) ?>

                                    </div>

                                    <div class="mobile-item-reference">

                                        <?= e(
                                            $payment['reference_number']
                                            ?: '#' . $payment['loan_id']
                                        ) ?>

                                    </div>

                                </div>

                                <div class="mobile-item-amount text-success">

                                    +<?= money($payment['payment_amount']) ?>

                                </div>

                            </div>

                            <div class="mobile-item-info">

                                <div>

                                    <span class="mobile-label">
                                        Payment Date
                                    </span>

                                    <span class="mobile-value">

                                        <?= date(
                                            'M d, Y',
                                            strtotime($payment['payment_date'])
                                        ) ?>

                                    </span>

                                </div>

                                <div class="text-end">

                                    <span class="mobile-label">
                                        Status
                                    </span>

                                    <span class="badge bg-success bg-opacity-10 text-success">
                                        Paid
                                    </span>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- OVERDUE -->

    <div class="col-12 col-xl-5">

        <div class="card dashboard-card shadow-sm bg-body h-100">

            <div class="dashboard-card-header">

                <div class="d-flex align-items-center gap-3">

                    <div class="section-icon bg-danger bg-opacity-10 text-danger">

                        <i class="bi bi-exclamation-triangle"></i>

                    </div>

                    <div>

                        <h5 class="fw-bold mb-1">
                            Overdue Loans
                        </h5>

                        <p class="text-muted small mb-0">
                            Accounts needing collection
                        </p>

                    </div>

                </div>

            </div>


            <?php if (empty($recentOverdue)): ?>

                <div class="empty-state text-center text-muted">

                    <div class="empty-icon bg-success bg-opacity-10 text-success">

                        <i class="bi bi-check-circle"></i>

                    </div>

                    <div class="fw-semibold text-success mb-1">
                        No overdue payments
                    </div>

                    <div class="small">
                        Your loan schedules are up to date.
                    </div>

                </div>

            <?php else: ?>


                <div class="mobile-list d-block p-3">

                    <?php foreach ($recentOverdue as $payment): ?>

                        <?php

                        $dueDate = new DateTime(
                            $payment['due_date']
                        );

                        $today = new DateTime(
                            date('Y-m-d')
                        );

                        $daysOverdue =
                            (int)$dueDate
                                ->diff($today)
                                ->format('%a');

                        ?>

                        <div class="mobile-item">

                            <div class="mobile-item-top">

                                <div class="min-width-0">

                                    <div class="mobile-item-name">

                                        <?= e(
                                            $payment['borrower_name']
                                        ) ?>

                                    </div>

                                    <div class="mobile-item-reference">

                                        <?= e(
                                            $payment['reference_number']
                                            ?: '#' . $payment['loan_id']
                                        ) ?>

                                    </div>

                                </div>

                                <div class="mobile-item-amount text-danger">

                                    <?= money(
                                        $payment['balance_due']
                                    ) ?>

                                </div>

                            </div>


                            <div class="mobile-item-info">

                                <div>

                                    <span class="mobile-label">
                                        Due Date
                                    </span>

                                    <span class="mobile-value text-danger">

                                        <?= date(
                                            'M d, Y',
                                            strtotime(
                                                $payment['due_date']
                                            )
                                        ) ?>

                                    </span>

                                </div>

                                <div class="text-end">

                                    <span class="mobile-label">
                                        Overdue
                                    </span>

                                    <span class="mobile-value text-danger">

                                        <?= $daysOverdue ?>
                                        day(s)

                                    </span>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>


</div>

</main>

</div>


<!-- =========================================================
     BOOTSTRAP
========================================================= -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


<!-- =========================================================
     COLLECTION CHART
========================================================= -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const canvas =
        document.getElementById('collectionChart');

    if (!canvas) {
        return;
    }

    const labels =
        <?= json_encode($collectionLabels) ?>;

    const values =
        <?= json_encode($collectionValues) ?>;

    const isDark =
        document.documentElement.getAttribute(
            'data-bs-theme'
        ) === 'dark';

    const textColor =
        isDark
            ? '#adb5bd'
            : '#6c757d';

    const gridColor =
        isDark
            ? 'rgba(255,255,255,.08)'
            : 'rgba(0,0,0,.06)';

    new Chart(canvas, {

        type: 'bar',

        data: {

            labels: labels,

            datasets: [

                {

                    label: 'Collected',

                    data: values,

                    borderRadius: 7,

                    borderSkipped: false,

                    maxBarThickness: 38

                }

            ]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            interaction: {

                intersect: false,

                mode: 'index'

            },

            plugins: {

                legend: {
                    display: false
                },

                tooltip: {

                    callbacks: {

                        label: function (context) {

                            return ' ₱' +
                                Number(
                                    context.raw
                                ).toLocaleString(
                                    'en-PH',
                                    {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    }
                                );

                        }

                    }

                }

            },

            scales: {

                x: {

                    grid: {
                        display: false
                    },

                    ticks: {
                        color: textColor,
                        font: {
                            size: 11
                        }
                    }

                },

                y: {

                    beginAtZero: true,

                    grid: {
                        color: gridColor
                    },

                    ticks: {

                        color: textColor,

                        font: {
                            size: 10
                        },

                        callback: function (value) {

                            return '₱' +
                                Number(value)
                                    .toLocaleString(
                                        'en-PH'
                                    );

                        }

                    }

                }

            }

        }

    });

});

</script>

</body>

</html>