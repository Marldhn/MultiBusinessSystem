<?php
$pdo = Database::getConnection();

$businessId = $_SESSION['business_id'] ?? null;
$userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

if (!$businessId || !$userId) {
    header('Location: index.php?page=select_business');
    exit;
}

$activePage = 'reports';
$pageTitle = 'Reports & Analytics - Loan Management';

$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

$dateFilterSql = '';
$dateParams = [];

if ($startDate && $endDate) {
    $dateFilterSql = ' AND l.loan_date BETWEEN ? AND ?';
    $dateParams = [$startDate, $endDate];
}

/*
|--------------------------------------------------------------------------
| 1. OVERALL LOAN SUMMARY
|--------------------------------------------------------------------------
| IMPORTANT:
| business_id + created_by
|
| This prevents User 1 from seeing loans created by User 3.
*/
$statsStmt = $pdo->prepare("
    SELECT
        COUNT(l.id) AS total_loans_count,
        COALESCE(SUM(l.principal_amount), 0) AS total_principal,
        COALESCE(SUM(l.total_payable), 0) AS total_payable,
        COALESCE(SUM(p_totals.total_paid), 0) AS total_collected,
        COALESCE(SUM(l.total_payable), 0)
        - COALESCE(SUM(p_totals.total_paid), 0) AS total_remaining_balance
    FROM loans l
    LEFT JOIN (
        SELECT
            loan_id,
            SUM(payment_amount) AS total_paid
        FROM loan_payments
        WHERE business_id = ?
        GROUP BY loan_id
    ) p_totals ON l.id = p_totals.loan_id
    WHERE l.business_id = ?
      AND l.created_by = ?
      {$dateFilterSql}
");

$statsParams = [$businessId, $businessId, $userId];
$statsParams = array_merge($statsParams, $dateParams);

$statsStmt->execute($statsParams);
$overallStats = $statsStmt->fetch(PDO::FETCH_ASSOC);

if (!$overallStats) {
    $overallStats = [
        'total_loans_count' => 0,
        'total_principal' => 0,
        'total_payable' => 0,
        'total_collected' => 0,
        'total_remaining_balance' => 0
    ];
}

/*
|--------------------------------------------------------------------------
| 2. ACCOUNT / WALLET BALANCES
|--------------------------------------------------------------------------
| Only show accounts created by the logged-in user.
*/
$accountsStmt = $pdo->prepare("
    SELECT
        id,
        account_name,
        balance
    FROM loan_accounts
    WHERE business_id = ?
      AND created_by = ?
    ORDER BY account_name ASC
");

$accountsStmt->execute([$businessId, $userId]);
$accounts = $accountsStmt->fetchAll(PDO::FETCH_ASSOC);

$totalLiquidity = array_sum(array_column($accounts, 'balance'));

/*
|--------------------------------------------------------------------------
| 3. RECENT PAYMENTS
|--------------------------------------------------------------------------
| We filter through loans.created_by.
|
| This is important because the payment itself may not have a created_by
| column, while the loan does.
*/
$paymentsStmt = $pdo->prepare("
    SELECT
        p.*,
        CONCAT(b.first_name, ' ', b.last_name) AS borrower_name,
        l.reference_number
    FROM loan_payments p
    INNER JOIN loans l
        ON p.loan_id = l.id
    INNER JOIN loan_borrowers b
        ON l.borrower_id = b.id
    WHERE p.business_id = ?
      AND l.business_id = ?
      AND l.created_by = ?
      {$dateFilterSql}
    ORDER BY p.payment_date DESC, p.created_at DESC
    LIMIT 15
");

$paymentParams = [$businessId, $businessId, $userId];
$paymentParams = array_merge($paymentParams, $dateParams);

$paymentsStmt->execute($paymentParams);
$recentPayments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <script>
        (function () {
            const savedTheme = localStorage.getItem('bs-theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>

    <style>
        body {
            min-height: 100vh;
            overflow-x: hidden;
        }

        .report-main {
            min-width: 0;
            width: 100%;
        }

        .report-container {
            width: 100%;
            max-width: 1600px;
            margin: 0 auto;
        }

        .metric-card {
            border: 0;
            border-radius: 16px;
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .metric-card:hover {
            transform: translateY(-2px);
        }

        .account-card,
        .payment-card {
            border: 0;
            border-radius: 16px;
            overflow: hidden;
        }

        .table th {
            font-size: .72rem;
            letter-spacing: .04em;
            white-space: nowrap;
        }

        .table td {
            padding-top: 13px;
            padding-bottom: 13px;
        }

        .empty-state {
            padding: 45px 20px;
        }

        .mobile-payment-list {
            display: none;
        }

        .payment-mobile-card {
            border: 1px solid var(--bs-border-color);
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 10px;
            background: var(--bs-body-bg);
        }

        .payment-mobile-card:last-child {
            margin-bottom: 0;
        }

        .payment-mobile-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
        }

        .payment-mobile-name {
            font-size: .9rem;
            font-weight: 700;
            line-height: 1.3;
        }

        .payment-mobile-date {
            font-size: .72rem;
            color: var(--bs-secondary-color);
            margin-top: 4px;
        }

        .payment-mobile-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            padding-top: 12px;
            border-top: 1px solid var(--bs-border-color);
        }

        .payment-mobile-label {
            display: block;
            font-size: .65rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--bs-secondary-color);
            margin-bottom: 3px;
        }

        .payment-mobile-value {
            font-size: .8rem;
            font-weight: 600;
        }

        .payment-mobile-notes {
            grid-column: 1 / -1;
            padding-top: 10px;
            border-top: 1px solid var(--bs-border-color);
        }

        .mobile-account-list {
            display: none;
        }

        .account-mobile-card {
            border: 1px solid var(--bs-border-color);
            border-radius: 13px;
            padding: 14px;
            margin-bottom: 10px;
            background: var(--bs-body-bg);
        }

        .account-mobile-card:last-child {
            margin-bottom: 0;
        }

        .account-mobile-name {
            font-size: .85rem;
            font-weight: 700;
        }

        .account-mobile-balance {
            font-size: .9rem;
            font-weight: 700;
        }

        .account-mobile-total {
            border: 0;
            border-radius: 13px;
            padding: 14px;
            margin-top: 10px;
            background: var(--bs-tertiary-bg);
        }

        .filter-card {
            border-radius: 16px;
        }

        .filter-actions {
            display: flex;
            gap: 8px;
        }

        .filter-actions .btn {
            white-space: nowrap;
        }

        @media (max-width: 991.98px) {
            .report-main {
                width: 100%;
            }

            .report-container {
                max-width: 100%;
            }
        }

        @media (max-width: 767.98px) {
            .report-main {
                padding: 0 !important;
            }

            .report-container {
                padding: 14px !important;
            }

            .report-header {
                margin-bottom: 18px !important;
            }

            .report-header h2 {
                font-size: 1.35rem;
            }

            .report-header p {
                font-size: .74rem;
                line-height: 1.4;
            }

            .report-header .btn {
                width: 100%;
            }

            .filter-card {
                border-radius: 14px;
            }

            .filter-card .card-body {
                padding: 14px !important;
            }

            .filter-actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }

            .filter-actions .btn {
                width: 100%;
            }

            .metric-row {
                --bs-gutter-x: 10px;
                --bs-gutter-y: 10px;
            }

            .metric-card {
                border-radius: 14px;
            }

            .metric-card .card-body {
                padding: 14px !important;
            }

            .metric-label {
                font-size: .65rem !important;
            }

            .metric-value {
                font-size: 1.05rem !important;
                word-break: break-word;
            }

            .metric-description {
                font-size: .67rem !important;
                line-height: 1.3;
            }

            .metric-card .bg-primary,
            .metric-card .bg-success,
            .metric-card .bg-warning,
            .metric-card .bg-info {
                width: 32px;
                height: 32px;
                padding: 7px !important;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            .metric-card i {
                font-size: .85rem;
            }

            .account-card,
            .payment-card {
                border-radius: 14px;
            }

            .account-card .card-body {
                padding: 14px !important;
            }

            .account-card h5,
            .payment-card h5 {
                font-size: .92rem;
            }

            .account-card p,
            .payment-card p {
                font-size: .7rem !important;
            }

            .account-table-wrapper {
                display: none;
            }

            .mobile-account-list {
                display: block;
            }

            .payment-table-wrapper {
                display: none;
            }

            .mobile-payment-list {
                display: block;
                padding: 12px;
            }

            .payment-card-header {
                padding: 14px !important;
            }

            .payment-card-header .badge {
                font-size: .65rem;
            }

            .empty-state {
                padding: 35px 15px;
            }

            .empty-state i {
                font-size: 2rem !important;
            }
        }

        @media (max-width: 575.98px) {
            .report-container {
                padding: 10px !important;
            }

            .report-header h2 {
                font-size: 1.2rem;
            }

            .report-header p {
                font-size: .7rem;
            }

            .filter-card {
                margin-bottom: 14px !important;
            }

            .metric-row {
                --bs-gutter-x: 8px;
                --bs-gutter-y: 8px;
            }

            .metric-card .card-body {
                padding: 12px !important;
            }

            .metric-label {
                font-size: .6rem !important;
            }

            .metric-value {
                font-size: .95rem !important;
            }

            .metric-description {
                font-size: .62rem !important;
            }

            .metric-card .bg-primary,
            .metric-card .bg-success,
            .metric-card .bg-warning,
            .metric-card .bg-info {
                width: 28px;
                height: 28px;
                padding: 6px !important;
            }

            .account-mobile-card,
            .account-mobile-total,
            .payment-mobile-card {
                padding: 12px;
            }

            .payment-mobile-details {
                grid-template-columns: 1fr 1fr;
            }

            .payment-mobile-name {
                font-size: .82rem;
            }

            .payment-mobile-value {
                font-size: .75rem;
            }

            .payment-mobile-label {
                font-size: .6rem;
            }
        }

        @media (max-width: 360px) {
            .report-container {
                padding: 8px !important;
            }

            .metric-card .card-body {
                padding: 10px !important;
            }

            .metric-value {
                font-size: .88rem !important;
            }

            .metric-description {
                font-size: .58rem !important;
            }

            .metric-card .bg-primary,
            .metric-card .bg-success,
            .metric-card .bg-warning,
            .metric-card .bg-info {
                display: none !important;
            }

            .payment-mobile-details {
                grid-template-columns: 1fr;
            }

            .payment-mobile-notes {
                grid-column: auto;
            }
        }

        @media print {
            .sidebar,
            button,
            .no-print {
                display: none !important;
            }

            body {
                background: #fff !important;
            }

            .report-main {
                width: 100% !important;
            }

            .card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }

            .mobile-payment-list,
            .mobile-account-list {
                display: none !important;
            }

            .account-table-wrapper,
            .payment-table-wrapper {
                display: block !important;
            }
        }
    </style>
</head>

<body class="bg-body-tertiary">

<div class="d-flex flex-column flex-lg-row min-vh-100">

    <?php include __DIR__ . '/../../../resources/partials/loansidebar.php'; ?>

    <main class="report-main flex-grow-1 bg-body-tertiary">

        <div class="report-container p-3 p-md-4">

            <!-- HEADER -->
            <div class="report-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

                <div>
                    <h2 class="fw-bold text-body mb-1">
                        Reports & Analytics
                    </h2>

                    <p class="text-muted small mb-0">
                        Financial performance and collection summaries for
                        <span class="fw-bold text-primary">
                            <?= htmlspecialchars($_SESSION['business_name'] ?? '') ?>
                        </span>
                    </p>
                </div>

                <div class="d-flex gap-2 no-print">

                    <button
                        onclick="window.print()"
                        class="btn btn-outline-secondary btn-sm fw-semibold px-3 py-2 rounded-3 shadow-sm"
                    >
                        <i class="bi bi-printer me-1"></i>
                        Print Report
                    </button>

                </div>

            </div>

            <!-- DATE FILTER -->
            <div class="card filter-card border-0 shadow-sm bg-body mb-4 no-print">

                <div class="card-body p-3">

                    <form method="GET" action="index.php">

                        <input type="hidden" name="page" value="reports">

                        <div class="row g-2 align-items-end">

                            <div class="col-12 col-md-4">

                                <label class="form-label small fw-semibold mb-1">
                                    Start Date
                                </label>

                                <input
                                    type="date"
                                    name="start_date"
                                    value="<?= htmlspecialchars($startDate) ?>"
                                    class="form-control form-control-sm"
                                >

                            </div>

                            <div class="col-12 col-md-4">

                                <label class="form-label small fw-semibold mb-1">
                                    End Date
                                </label>

                                <input
                                    type="date"
                                    name="end_date"
                                    value="<?= htmlspecialchars($endDate) ?>"
                                    class="form-control form-control-sm"
                                >

                            </div>

                            <div class="col-12 col-md-auto">

                                <div class="filter-actions">

                                    <button
                                        type="submit"
                                        class="btn btn-primary btn-sm fw-semibold"
                                    >
                                        <i class="bi bi-funnel me-1"></i>
                                        Apply Filter
                                    </button>

                                    <a
                                        href="index.php?page=reports"
                                        class="btn btn-outline-secondary btn-sm fw-semibold"
                                    >
                                        Reset
                                    </a>

                                </div>

                            </div>

                        </div>

                    </form>

                </div>

            </div>

            <!-- METRIC CARDS -->
            <div class="row g-3 metric-row mb-4">

                <!-- TOTAL DISBURSED -->
                <div class="col-6 col-xl-3">

                    <div class="card metric-card shadow-sm bg-body h-100">

                        <div class="card-body p-4">

                            <div class="d-flex align-items-center justify-content-between mb-2">

                                <span class="metric-label text-muted small fw-semibold">
                                    Total Disbursed
                                </span>

                                <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2">

                                    <i class="bi bi-wallet2"></i>

                                </div>

                            </div>

                            <h4 class="metric-value fw-bold text-body mb-1">
                                ₱<?= number_format($overallStats['total_principal'], 2) ?>
                            </h4>

                            <span class="metric-description text-muted small">
                                <?= number_format($overallStats['total_loans_count']) ?>
                                Total Loans Issued
                            </span>

                        </div>

                    </div>

                </div>

                <!-- TOTAL COLLECTED -->
                <div class="col-6 col-xl-3">

                    <div class="card metric-card shadow-sm bg-body h-100">

                        <div class="card-body p-4">

                            <div class="d-flex align-items-center justify-content-between mb-2">

                                <span class="metric-label text-muted small fw-semibold">
                                    Total Collected
                                </span>

                                <div class="bg-success bg-opacity-10 text-success rounded-3 p-2">

                                    <i class="bi bi-cash-coin"></i>

                                </div>

                            </div>

                            <h4 class="metric-value fw-bold text-success mb-1">
                                ₱<?= number_format($overallStats['total_collected'], 2) ?>
                            </h4>

                            <span class="metric-description text-muted small">
                                Verified payments received
                            </span>

                        </div>

                    </div>

                </div>

                <!-- OUTSTANDING -->
                <div class="col-6 col-xl-3">

                    <div class="card metric-card shadow-sm bg-body h-100">

                        <div class="card-body p-4">

                            <div class="d-flex align-items-center justify-content-between mb-2">

                                <span class="metric-label text-muted small fw-semibold">
                                    Outstanding Balance
                                </span>

                                <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-2">

                                    <i class="bi bi-hourglass-split"></i>

                                </div>

                            </div>

                            <h4 class="metric-value fw-bold text-warning mb-1">
                                ₱<?= number_format($overallStats['total_remaining_balance'], 2) ?>
                            </h4>

                            <span class="metric-description text-muted small">
                                Pending collection amount
                            </span>

                        </div>

                    </div>

                </div>

                <!-- LIQUIDITY -->
                <div class="col-6 col-xl-3">

                    <div class="card metric-card shadow-sm bg-body h-100">

                        <div class="card-body p-4">

                            <div class="d-flex align-items-center justify-content-between mb-2">

                                <span class="metric-label text-muted small fw-semibold">
                                    Current Liquidity
                                </span>

                                <div class="bg-info bg-opacity-10 text-info rounded-3 p-2">

                                    <i class="bi bi-bank"></i>

                                </div>

                            </div>

                            <h4 class="metric-value fw-bold text-info mb-1">
                                ₱<?= number_format($totalLiquidity, 2) ?>
                            </h4>

                            <span class="metric-description text-muted small">
                                Your accounts only
                            </span>

                        </div>

                    </div>

                </div>

            </div>

            <!-- ACCOUNT BALANCES -->
            <div class="card account-card shadow-sm bg-body mb-4">

                <div class="card-body p-3 p-md-4">

                    <div class="d-flex justify-content-between align-items-center mb-3">

                        <div>

                            <h5 class="fw-bold text-body mb-1">

                                <i class="bi bi-pie-chart-fill text-primary me-2"></i>

                                Account / Wallet Liquidity

                            </h5>

                            <p class="text-muted small mb-0">
                                Accounts belonging to your user
                            </p>

                        </div>

                    </div>

                    <!-- DESKTOP ACCOUNT TABLE -->
                    <div class="account-table-wrapper table-responsive">

                        <table class="table table-hover align-middle mb-0">

                            <thead class="table-light text-uppercase text-muted">

                                <tr>

                                    <th class="py-3 ps-3">
                                        Account Name
                                    </th>

                                    <th class="py-3 text-end pe-3">
                                        Available Balance
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php if (empty($accounts)): ?>

                                <tr>

                                    <td
                                        colspan="2"
                                        class="text-center text-muted py-5"
                                    >

                                        <i class="bi bi-wallet2 display-6 opacity-50"></i>

                                        <div class="fw-semibold mt-2">
                                            No accounts found
                                        </div>

                                        <div class="small">
                                            You do not have any loan accounts yet.
                                        </div>

                                    </td>

                                </tr>

                            <?php else: ?>

                                <?php foreach ($accounts as $account): ?>

                                    <tr>

                                        <td class="ps-3">

                                            <div class="fw-semibold text-body">

                                                <?= htmlspecialchars($account['account_name']) ?>

                                            </div>

                                        </td>

                                        <td class="text-end pe-3">

                                            <span class="fw-bold text-success">

                                                ₱<?= number_format($account['balance'], 2) ?>

                                            </span>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                                <tr class="table-light">

                                    <td class="ps-3 fw-bold">
                                        Total
                                    </td>

                                    <td class="text-end pe-3 fw-bold text-success">

                                        ₱<?= number_format($totalLiquidity, 2) ?>

                                    </td>

                                </tr>

                            <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                    <!-- MOBILE ACCOUNT CARDS -->
                    <div class="mobile-account-list">

                        <?php if (empty($accounts)): ?>

                            <div class="empty-state text-center text-muted">

                                <i class="bi bi-wallet2 display-6 opacity-50"></i>

                                <div class="fw-semibold mt-2">
                                    No accounts found
                                </div>

                                <div class="small">
                                    You do not have any loan accounts yet.
                                </div>

                            </div>

                        <?php else: ?>

                            <?php foreach ($accounts as $account): ?>

                                <div class="account-mobile-card">

                                    <div class="d-flex justify-content-between align-items-center gap-3">

                                        <div class="account-mobile-name text-body">

                                            <i class="bi bi-wallet2 text-primary me-2"></i>

                                            <?= htmlspecialchars($account['account_name']) ?>

                                        </div>

                                        <div class="account-mobile-balance text-success text-nowrap">

                                            ₱<?= number_format($account['balance'], 2) ?>

                                        </div>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                            <div class="account-mobile-total">

                                <div class="d-flex justify-content-between align-items-center">

                                    <span class="fw-bold text-body">
                                        Total
                                    </span>

                                    <span class="fw-bold text-success">
                                        ₱<?= number_format($totalLiquidity, 2) ?>
                                    </span>

                                </div>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

            <!-- RECENT PAYMENTS -->
            <div class="card payment-card shadow-sm bg-body">

                <div class="payment-card-header p-3 p-md-4 border-bottom">

                    <div class="d-flex justify-content-between align-items-center gap-3">

                        <div>

                            <h5 class="fw-bold text-body mb-1">

                                <i class="bi bi-clock-history text-success me-2"></i>

                                Recent Collections / Payments

                            </h5>

                            <p class="text-muted small mb-0">
                                Payments from your loans only
                            </p>

                        </div>

                        <span class="badge bg-success bg-opacity-10 text-success px-2 py-1">

                            Latest Activity

                        </span>

                    </div>

                </div>

                <!-- DESKTOP PAYMENT TABLE -->
                <div class="payment-table-wrapper table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light text-uppercase text-muted">

                            <tr>

                                <th class="py-3 ps-4">
                                    Date
                                </th>

                                <th class="py-3">
                                    Borrower
                                </th>

                                <th class="py-3">
                                    Ref #
                                </th>

                                <th class="py-3">
                                    Amount Paid
                                </th>

                                <th class="py-3 pe-4">
                                    Notes
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php if (empty($recentPayments)): ?>

                            <tr>

                                <td
                                    colspan="5"
                                    class="text-center py-5 text-muted"
                                >

                                    <div class="mb-2">

                                        <i class="bi bi-receipt display-6 opacity-50"></i>

                                    </div>

                                    <p class="mb-1 fw-semibold">
                                        No payment records found
                                    </p>

                                    <p class="small text-muted mb-0">
                                        Payments from your loans will appear here.
                                    </p>

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($recentPayments as $payment): ?>

                                <tr>

                                    <td class="ps-4 text-muted small">

                                        <?= date(
                                            'M d, Y',
                                            strtotime($payment['payment_date'])
                                        ) ?>

                                    </td>

                                    <td class="fw-bold text-body">

                                        <?= htmlspecialchars(
                                            $payment['borrower_name']
                                        ) ?>

                                    </td>

                                    <td>

                                        <span class="badge bg-secondary bg-opacity-10 text-secondary">

                                            <?= htmlspecialchars(
                                                $payment['reference_number'] ?? '—'
                                            ) ?>

                                        </span>

                                    </td>

                                    <td class="fw-bold text-success">

                                        ₱<?= number_format(
                                            $payment['payment_amount'],
                                            2
                                        ) ?>

                                    </td>

                                    <td class="pe-4 text-muted small">

                                        <?= htmlspecialchars(
                                            $payment['notes'] ?? '—'
                                        ) ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

                <!-- MOBILE PAYMENT CARDS -->
                <div class="mobile-payment-list">

                    <?php if (empty($recentPayments)): ?>

                        <div class="empty-state text-center text-muted">

                            <div class="mb-2">

                                <i class="bi bi-receipt display-6 opacity-50"></i>

                            </div>

                            <p class="mb-1 fw-semibold">
                                No payment records found
                            </p>

                            <p class="small text-muted mb-0">
                                Payments from your loans will appear here.
                            </p>

                        </div>

                    <?php else: ?>

                        <?php foreach ($recentPayments as $payment): ?>

                            <div class="payment-mobile-card">

                                <div class="payment-mobile-top">

                                    <div class="min-width-0">

                                        <div class="payment-mobile-name text-body">

                                            <?= htmlspecialchars(
                                                $payment['borrower_name']
                                            ) ?>

                                        </div>

                                        <div class="payment-mobile-date">

                                            <i class="bi bi-calendar3 me-1"></i>

                                            <?= date(
                                                'M d, Y',
                                                strtotime($payment['payment_date'])
                                            ) ?>

                                        </div>

                                    </div>

                                    <span class="badge bg-success bg-opacity-10 text-success fw-semibold text-nowrap">

                                        ₱<?= number_format(
                                            $payment['payment_amount'],
                                            2
                                        ) ?>

                                    </span>

                                </div>

                                <div class="payment-mobile-details">

                                    <div>

                                        <span class="payment-mobile-label">
                                            Reference
                                        </span>

                                        <div class="payment-mobile-value text-body">

                                            <?= htmlspecialchars(
                                                $payment['reference_number'] ?? '—'
                                            ) ?>

                                        </div>

                                    </div>

                                    <div>

                                        <span class="payment-mobile-label">
                                            Amount Paid
                                        </span>

                                        <div class="payment-mobile-value text-success">

                                            ₱<?= number_format(
                                                $payment['payment_amount'],
                                                2
                                            ) ?>

                                        </div>

                                    </div>

                                    <div class="payment-mobile-notes">

                                        <span class="payment-mobile-label">
                                            Notes
                                        </span>

                                        <div class="payment-mobile-value text-muted">

                                            <?= htmlspecialchars(
                                                $payment['notes'] ?? '—'
                                            ) ?>

                                        </div>

                                    </div>

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