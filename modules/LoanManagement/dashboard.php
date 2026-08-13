<?php
// Session & DB check
$pdo = Database::getConnection();
$businessId = $_SESSION['business_id'] ?? null;

if (!$businessId) {
    header('Location: index.php?page=select_business');
    exit;
}

// 1. Fetch Total Active Loans Count & Total Outstanding Amount
$activeLoansStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_active,
        COALESCE(SUM(total_payable), 0) as total_amount
    FROM loans
    WHERE business_id = ? 
    AND status = 'active'
");
$activeLoansStmt->execute([$businessId]);
$activeLoansData = $activeLoansStmt->fetch(PDO::FETCH_ASSOC);

// 2. Fetch Total Borrowers Count
$borrowersStmt = $pdo->prepare("
    SELECT COUNT(*) as total_borrowers
    FROM loan_borrowers
    WHERE business_id = ?
");
$borrowersStmt->execute([$businessId]);
$borrowersData = $borrowersStmt->fetch(PDO::FETCH_ASSOC);

// 3. Fetch Total Available Funds
$accountsStmt = $pdo->prepare("
    SELECT COALESCE(SUM(balance), 0) as total_funds
    FROM loan_accounts
    WHERE business_id = ?
");
$accountsStmt->execute([$businessId]);
$accountsData = $accountsStmt->fetch(PDO::FETCH_ASSOC);

// 4. Fetch Recent Transactions
$txStmt = $pdo->prepare("
    SELECT *
    FROM loan_account_transactions
    WHERE business_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$txStmt->execute([$businessId]);
$recentTransactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);

$activePage = 'dashboard';
$pageTitle = "Dashboard - Loan Management";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Apply saved theme before page renders -->
    <script>
        (function () {
            const savedTheme = localStorage.getItem('bs-theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>

    <style>
        body {
            min-height: 100vh;
        }

        .dashboard-main {
            min-width: 0;
        }

        .dashboard-header {
            padding-bottom: 4px;
        }

        .metric-card {
            border: 0;
            border-radius: 16px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .metric-card:hover {
            transform: translateY(-2px);
        }

        .metric-icon {
            width: 48px;
            height: 48px;
            flex-shrink: 0;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .transaction-card {
            border: 0;
            border-radius: 16px;
            overflow: hidden;
        }

        .transaction-mobile {
            display: none;
        }

        .table th {
            font-size: 0.72rem;
            letter-spacing: 0.04em;
            white-space: nowrap;
        }

        .table td {
            padding-top: 14px;
            padding-bottom: 14px;
        }

        .empty-state {
            padding: 50px 20px;
        }

        @media (max-width: 991.98px) {
            .dashboard-main {
                width: 100%;
            }
        }

        @media (max-width: 575.98px) {

            .dashboard-main {
                padding: 14px !important;
            }

            .dashboard-header {
                margin-bottom: 16px !important;
            }

            .dashboard-header h2 {
                font-size: 1.35rem;
            }

            .dashboard-header p {
                font-size: 0.78rem;
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
                width: 42px;
                height: 42px;
                border-radius: 12px;
                font-size: 1.1rem;
            }

            .metric-label {
                font-size: 0.68rem !important;
                margin-bottom: 4px !important;
            }

            .metric-value {
                font-size: 1.25rem !important;
            }

            .metric-description {
                font-size: 0.68rem !important;
            }

            .transaction-card {
                border-radius: 14px;
            }

            .transaction-header {
                padding: 16px !important;
            }

            .transaction-header h5 {
                font-size: 0.95rem !important;
            }

            /* Hide desktop table on phones */
            .transaction-table {
                display: none;
            }

            /* Show mobile transaction cards */
            .transaction-mobile {
                display: block;
                padding: 0 12px 12px;
            }

            .mobile-transaction {
                border: 1px solid var(--bs-border-color);
                border-radius: 12px;
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
                font-size: 0.82rem;
                font-weight: 600;
                line-height: 1.35;
            }

            .mobile-transaction-date {
                font-size: 0.68rem;
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
                font-size: 0.9rem;
                font-weight: 700;
            }

            .empty-state {
                padding: 35px 15px;
            }
        }
    </style>
</head>

<body class="bg-body-tertiary">

<div class="d-flex flex-column flex-lg-row min-vh-100">

    <!-- Sidebar -->
    <?php include __DIR__ . '/../../resources/partials/loansidebar.php'; ?>

    <!-- Main Content -->
    <main class="dashboard-main flex-grow-1 bg-body-tertiary">

        <div class="p-3 p-md-4">

            <!-- =========================
                 PAGE HEADER
            ========================== -->
            <div class="dashboard-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

                <div class="min-width-0">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <div class="d-lg-none bg-primary bg-opacity-10 text-primary rounded-3 p-2">
                            <i class="bi bi-speedometer2"></i>
                        </div>

                        <h2 class="fw-bold text-body mb-0">
                            Dashboard
                        </h2>
                    </div>

                    <p class="text-muted small mb-0">
                        Welcome back. Managing
                        <span class="fw-semibold text-primary">
                            <?= htmlspecialchars($_SESSION['business_name'] ?? 'Business') ?>
                        </span>
                    </p>
                </div>

                <a href="index.php?page=loans"
                   class="issue-loan-btn btn btn-primary fw-bold px-3 py-2 rounded-3 shadow-sm d-flex align-items-center gap-2 text-nowrap">
                    <i class="bi bi-plus-lg"></i>
                    <span>Issue New Loan</span>
                </a>

            </div>


            <!-- =========================
                 METRIC CARDS
            ========================== -->
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 metric-row g-3 mb-4">

                <!-- Active Loans -->
                <div class="col">
                    <div class="card metric-card shadow-sm bg-body h-100">
                        <div class="card-body p-4">

                            <div class="d-flex justify-content-between align-items-start gap-3">

                                <div class="min-width-0">
                                    <div class="metric-label text-muted fw-bold mb-2">
                                        ACTIVE LOANS
                                    </div>

                                    <div class="metric-value fw-bold text-primary fs-3">
                                        <?= number_format($activeLoansData['total_active']) ?>
                                    </div>

                                    <div class="metric-description text-muted mt-1">
                                        Total value:
                                        <span class="fw-semibold text-body">
                                            ₱<?= number_format($activeLoansData['total_amount'], 2) ?>
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


                <!-- Available Funds -->
                <div class="col">
                    <div class="card metric-card shadow-sm bg-body h-100">
                        <div class="card-body p-4">

                            <div class="d-flex justify-content-between align-items-start gap-3">

                                <div class="min-width-0">
                                    <div class="metric-label text-muted fw-bold mb-2">
                                        AVAILABLE FUNDS
                                    </div>

                                    <div class="metric-value fw-bold text-success fs-3 text-truncate">
                                        ₱<?= number_format($accountsData['total_funds'], 2) ?>
                                    </div>

                                    <div class="metric-description text-muted mt-1">
                                        Across all accounts
                                    </div>
                                </div>

                                <div class="metric-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-wallet2"></i>
                                </div>

                            </div>

                        </div>
                    </div>
                </div>


                <!-- Borrowers -->
                <div class="col">
                    <div class="card metric-card shadow-sm bg-body h-100">
                        <div class="card-body p-4">

                            <div class="d-flex justify-content-between align-items-start gap-3">

                                <div class="min-width-0">
                                    <div class="metric-label text-muted fw-bold mb-2">
                                        TOTAL BORROWERS
                                    </div>

                                    <div class="metric-value fw-bold text-warning fs-3">
                                        <?= number_format($borrowersData['total_borrowers']) ?>
                                    </div>

                                    <div class="metric-description text-muted mt-1">
                                        Registered clients
                                    </div>
                                </div>

                                <div class="metric-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-people"></i>
                                </div>

                            </div>

                        </div>
                    </div>
                </div>

            </div>


            <!-- =========================
                 RECENT TRANSACTIONS
            ========================== -->
            <div class="card transaction-card shadow-sm bg-body">

                <!-- Header -->
                <div class="transaction-header card-header bg-transparent border-0 px-4 pt-4 pb-3">

                    <div class="d-flex justify-content-between align-items-center gap-3">

                        <div>
                            <h5 class="fw-bold mb-1 text-body">
                                Recent Transactions
                            </h5>

                            <p class="text-muted small mb-0">
                                Latest account activity
                            </p>
                        </div>

                        <a href="index.php?page=loan_accounts"
                           class="small text-decoration-none fw-semibold text-nowrap">
                            View All
                            <i class="bi bi-arrow-right ms-1"></i>
                        </a>

                    </div>

                </div>


                <!-- Desktop / Tablet Table -->
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

                                        <div class="mb-3">
                                            <i class="bi bi-receipt display-6 opacity-50"></i>
                                        </div>

                                        <div class="fw-semibold mb-1">
                                            No recent transactions
                                        </div>

                                        <div class="small">
                                            Transactions will appear here when loans are issued
                                            or payments are recorded.
                                        </div>

                                    </div>

                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($recentTransactions as $tx): ?>

                                <?php
                                $isCredit = strtoupper($tx['type']) === 'CREDIT';
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
                                            <?= htmlspecialchars($tx['type']) ?>
                                        </span>
                                    </td>

                                    <td class="fw-semibold text-body">
                                        <?= htmlspecialchars($tx['description']) ?>
                                    </td>

                                    <td class="text-end pe-4 fw-bold text-<?= $typeClass ?>">
                                        <?= $isCredit ? '+' : '-' ?>
                                        ₱<?= number_format($tx['amount'], 2) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>


                <!-- =========================
                     MOBILE TRANSACTION CARDS
                ========================== -->
                <div class="transaction-mobile">

                    <?php if (empty($recentTransactions)): ?>

                        <div class="empty-state text-center text-muted">

                            <div class="mb-3">
                                <i class="bi bi-receipt display-6 opacity-50"></i>
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
                            $isCredit = strtoupper($tx['type']) === 'CREDIT';
                            $typeClass = $isCredit ? 'success' : 'danger';
                            ?>

                            <div class="mobile-transaction">

                                <div class="mobile-transaction-top">

                                    <div class="flex-grow-1 min-width-0">

                                        <div class="mobile-transaction-description text-body">
                                            <?= htmlspecialchars($tx['description']) ?>
                                        </div>

                                        <div class="mobile-transaction-date mt-1">
                                            <i class="bi bi-calendar3 me-1"></i>
                                            <?= date('M d, Y h:i A', strtotime($tx['created_at'])) ?>
                                        </div>

                                    </div>

                                    <span class="badge bg-<?= $typeClass ?> bg-opacity-10 text-<?= $typeClass ?> fw-semibold">
                                        <?= htmlspecialchars($tx['type']) ?>
                                    </span>

                                </div>


                                <div class="mobile-transaction-bottom">

                                    <span class="small text-muted">
                                        Account Transaction
                                    </span>

                                    <span class="mobile-transaction-amount text-<?= $typeClass ?>">
                                        <?= $isCredit ? '+' : '-' ?>
                                        ₱<?= number_format($tx['amount'], 2) ?>
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


<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>