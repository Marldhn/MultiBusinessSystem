<?php
$pdo = Database::getConnection();
$businessId = $_SESSION['business_id'] ?? null;

if (!$businessId) {
    header('Location: index.php?page=select_business');
    exit;
}

$activePage = 'reports';
$pageTitle = "Reports & Analytics - Loan Management";

// Date filter inputs (default to current month or all-time)
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// Build date filter clause for loans and payments
$dateFilterSql = "";
$params = [$businessId];

if (!empty($startDate) && !empty($endDate)) {
    $dateFilterSql = " AND l.loan_date BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}

// 1. Overall Summary Statistics
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(l.id) AS total_loans_count,
        COALESCE(SUM(l.principal_amount), 0) AS total_principal,
        COALESCE(SUM(l.total_payable), 0) AS total_payable,
        COALESCE(SUM(p_totals.total_paid), 0) AS total_collected,
        COALESCE(SUM(l.total_payable), 0) - COALESCE(SUM(p_totals.total_paid), 0) AS total_remaining_balance
    FROM loans l
    LEFT JOIN (
        SELECT loan_id, SUM(payment_amount) AS total_paid 
        FROM loan_payments 
        WHERE business_id = ? 
        GROUP BY loan_id
    ) p_totals ON l.id = p_totals.loan_id
    WHERE l.business_id = ?
");
$statsStmt->execute([$businessId, $businessId]);
$overallStats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// 2. Account / Wallet Balances Summary
$accountsStmt = $pdo->prepare("
    SELECT id, account_name, balance 
    FROM loan_accounts 
    WHERE business_id = ? 
    ORDER BY account_name
");
$accountsStmt->execute([$businessId]);
$accounts = $accountsStmt->fetchAll(PDO::FETCH_ASSOC);

// Total liquidity across accounts
$totalLiquidity = array_sum(array_column($accounts, 'balance'));

// 3. Recent Payments / Collections Log
$paymentsStmt = $pdo->prepare("
    SELECT p.*, 
           CONCAT(b.first_name, ' ', b.last_name) AS borrower_name,
           a.account_name,
           l.reference_number
    FROM loan_payments p
    JOIN loans l ON p.loan_id = l.id
    JOIN loan_borrowers b ON l.borrower_id = b.id
    JOIN loan_accounts a ON p.account_id = a.id
    WHERE p.business_id = ?
    ORDER BY p.payment_date DESC, p.created_at DESC
    LIMIT 15
");
$paymentsStmt->execute([$businessId]);
$recentPayments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script>
        (function() {
            const savedTheme = localStorage.getItem('bs-theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
</head>
<body class="bg-body-tertiary" style="min-height: 100vh;">

<div class="d-flex flex-column flex-lg-row" style="min-height: 100vh;">
    <?php include __DIR__ . '/../../../../resources/partials/loansidebar.php'; ?>

    <div class="p-2 p-md-4 flex-grow-1 bg-body-tertiary overflow-hidden">
        <!-- Header Section -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
            <div>
                <h2 class="fw-bold text-body mb-1 fs-4 fs-md-2">Reports & Analytics</h2>
                <p class="text-muted small mb-0" style="font-size: 0.8rem;">Financial performance and collection summaries for <span class="fw-bold text-primary"><?= htmlspecialchars($_SESSION['business_name'] ?? '') ?></span></p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print();" class="btn btn-outline-secondary btn-sm fw-semibold px-3 py-2 rounded-3 shadow-sm">
                    <i class="bi bi-printer me-1"></i> Print Report
                </button>
            </div>
        </div>

        <!-- Metric Cards Grid -->
        <div class="row g-3 mb-4">
            <!-- Total Disbursed -->
            <div class="col-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-body h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold" style="font-size: 0.75rem;">Total Disbursed</span>
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="bi bi-wallet2" style="font-size: 0.9rem;"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold text-body mb-1" style="font-size: 1.25rem;">₱<?= number_format($overallStats['total_principal'], 2) ?></h4>
                    <span class="text-muted" style="font-size: 0.7rem;"><?= $overallStats['total_loans_count'] ?> Total Loans Issued</span>
                </div>
            </div>

            <!-- Total Collected -->
            <div class="col-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-body h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold" style="font-size: 0.75rem;">Total Collected</span>
                        <div class="bg-success bg-opacity-10 text-success rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="bi bi-cash-coin" style="font-size: 0.9rem;"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold text-success mb-1" style="font-size: 1.25rem;">₱<?= number_format($overallStats['total_collected'], 2) ?></h4>
                    <span class="text-muted" style="font-size: 0.7rem;">Verified payments received</span>
                </div>
            </div>

            <!-- Outstanding Balance -->
            <div class="col-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-body h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold" style="font-size: 0.75rem;">Outstanding Balance</span>
                        <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="bi bi-hourglass-split" style="font-size: 0.9rem;"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold text-warning mb-1" style="font-size: 1.25rem;">₱<?= number_format($overallStats['total_remaining_balance'], 2) ?></h4>
                    <span class="text-muted" style="font-size: 0.7rem;">Pending collection amount</span>
                </div>
            </div>

            <!-- Total Wallet Liquidity -->
            <div class="col-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-body h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold" style="font-size: 0.75rem;">Current Liquidity</span>
                        <div class="bg-info bg-opacity-10 text-info rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="bi bi-bank" style="font-size: 0.9rem;"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold text-info mb-1" style="font-size: 1.25rem;">₱<?= number_format($totalLiquidity, 2) ?></h4>
                    <span class="text-muted" style="font-size: 0.7rem;">Total across all accounts</span>
                </div>
            </div>
        </div>

        <!-- Section: Account Balances Breakdown -->
        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-body">
                    <h5 class="fw-bold text-body mb-3 fs-6"><i class="bi bi-pie-chart-fill text-primary me-2"></i> Account / Wallet Liquidity Breakdown</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                            <thead class="table-light text-uppercase text-muted" style="font-size: 0.7rem;">
                                <tr>
                                    <th class="py-2 ps-3">Account Name</th>
                                    <th class="py-2 text-end pe-3">Available Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($accounts)): ?>
                                    <tr>
                                        <td colspan="2" class="text-center py-3 text-muted small">No accounts found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($accounts as $acc): ?>
                                        <tr>
                                            <td class="ps-3 fw-semibold text-body"><?= htmlspecialchars($acc['account_name']) ?></td>
                                            <td class="text-end pe-3 fw-bold text-success">₱<?= number_format($acc['balance'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section: Recent Collections Log -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-body">
            <div class="p-3 p-md-4 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-body mb-0 fs-6"><i class="bi bi-clock-history text-success me-2"></i> Recent Collections / Payments</h5>
                <span class="badge bg-success bg-opacity-10 text-success px-2 py-1" style="font-size: 0.75rem;">Latest Activity</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                    <thead class="table-light text-uppercase text-muted" style="font-size: 0.7rem;">
                        <tr>
                            <th class="py-3 ps-4">Date</th>
                            <th class="py-3">Borrower</th>
                            <th class="py-3">Ref #</th>
                            <th class="py-3">Deposit Account</th>
                            <th class="py-3">Amount Paid</th>
                            <th class="py-3 pe-4">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentPayments)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <div class="mb-2"><i class="bi bi-receipt display-6 opacity-50"></i></div>
                                    <p class="mb-1 fw-semibold">No payment records found yet</p>
                                    <p class="small text-muted mb-0">Payments recorded against active loans will appear here.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentPayments as $payment): ?>
                                <tr>
                                    <td class="ps-4 text-muted small"><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                                    <td class="fw-bold text-body"><?= htmlspecialchars($payment['borrower_name']) ?></td>
                                    <td><span class="badge bg-secondary bg-opacity-10 text-secondary" style="font-size: 0.7rem;"><?= htmlspecialchars($payment['reference_number'] ?? '—') ?></span></td>
                                    <td class="text-muted small"><i class="bi bi-wallet2 me-1"></i><?= htmlspecialchars($payment['account_name']) ?></td>
                                    <td class="fw-bold text-success">₱<?= number_format($payment['payment_amount'], 2) ?></td>
                                    <td class="pe-4 text-muted small text-truncate" style="max-width: 200px;"><?= htmlspecialchars($payment['notes'] ?: '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>