<?php
$pdo = Database::getConnection();
$businessId = $_SESSION['business_id'] ?? null;
$accountId = $_GET['id'] ?? null;

if (!$businessId || !$accountId) {
    header('Location: index.php?page=loan_accounts');
    exit;
}

// Fetch Account Info
$stmt = $pdo->prepare("
    SELECT *
    FROM loan_accounts
    WHERE id = ? AND business_id = ?
");
$stmt->execute([$accountId, $businessId]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    header('Location: index.php?page=loan_accounts');
    exit;
}

// Fetch Transaction History
$txStmt = $pdo->prepare("
    SELECT *
    FROM loan_account_transactions
    WHERE account_id = ? AND business_id = ?
    ORDER BY created_at DESC
");
$txStmt->execute([$accountId, $businessId]);
$transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);

$activePage = 'loan_accounts';
$pageTitle = $account['account_name'] . " - Account Details";

// Calculate transaction totals
$totalCredits = 0;
$totalDebits = 0;

foreach ($transactions as $tx) {
    if ($tx['type'] === 'CREDIT') {
        $totalCredits += (float)$tx['amount'];
    } else {
        $totalDebits += (float)$tx['amount'];
    }
}

$totalTransactions = count($transactions);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle) ?></title>

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
            const savedTheme = localStorage.getItem('bs-theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>

    <style>
        body {
            min-height: 100vh;
        }

        .main-content {
            min-width: 0;
        }

        .back-link {
            transition: all 0.2s ease;
        }

        .back-link:hover {
            color: var(--bs-primary) !important;
        }

        .balance-card {
            min-width: 220px;
        }

        .summary-card {
            transition: all 0.2s ease;
        }

        .summary-card:hover {
            transform: translateY(-2px);
        }

        .transaction-icon {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
        }

        .transaction-row {
            transition: background-color 0.15s ease;
        }

        .amount {
            letter-spacing: -0.3px;
        }

        @media (max-width: 575.98px) {

            .page-title {
                font-size: 1.35rem !important;
            }

            .balance-card {
                width: 100%;
                min-width: 0;
            }

            .summary-card {
                padding: 1rem !important;
            }

            .mobile-amount {
                font-size: 1rem;
            }
        }
    </style>
</head>

<body class="bg-body-tertiary">

<div class="d-flex flex-column flex-lg-row min-vh-100">

    <!-- Sidebar -->
    <?php include __DIR__ . '/../../../resources/partials/loansidebar.php'; ?>


    <!-- Main Content -->
    <main class="main-content flex-grow-1 p-3 p-md-4">

        <!-- Back Button -->
        <a
            href="index.php?page=loan_accounts"
            class="back-link text-decoration-none small fw-semibold text-muted d-inline-flex align-items-center gap-1 mb-3"
        >
            <i class="bi bi-arrow-left"></i>
            Back to Accounts
        </a>


        <!-- Page Header -->
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">

            <div class="d-flex align-items-center gap-3">

                <div
                    class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center"
                    style="width: 48px; height: 48px;"
                >
                    <i class="bi bi-wallet2 fs-4"></i>
                </div>

                <div>
                    <h2 class="page-title fw-bold text-body mb-1">
                        <?= htmlspecialchars($account['account_name']) ?>
                    </h2>

                    <p class="text-muted small mb-0">
                        Wallet ledger and transaction history
                    </p>
                </div>

            </div>


            <!-- Balance -->
            <div class="balance-card card border-0 shadow-sm rounded-4 bg-body">

                <div class="card-body px-3 py-2">

                    <div class="d-flex align-items-center gap-3">

                        <div
                            class="transaction-icon rounded-3 bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center"
                        >
                            <i class="bi bi-cash-stack"></i>
                        </div>

                        <div>
                            <span class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.68rem;">
                                Current Balance
                            </span>

                            <h4 class="fw-bold text-success mb-0">
                                ₱<?= number_format($account['balance'], 2) ?>
                            </h4>
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- Summary Cards -->
        <div class="row g-3 mb-4">

            <!-- Credits -->
            <div class="col-12 col-md-4">

                <div class="summary-card card border-0 shadow-sm rounded-4 bg-body h-100">

                    <div class="card-body p-3">

                        <div class="d-flex justify-content-between align-items-center">

                            <div>
                                <span class="text-muted small fw-semibold d-block mb-1">
                                    TOTAL CREDITS
                                </span>

                                <h5 class="fw-bold text-success mb-0">
                                    +₱<?= number_format($totalCredits, 2) ?>
                                </h5>

                                <span class="text-muted" style="font-size: 0.72rem;">
                                    Money added
                                </span>
                            </div>

                            <div
                                class="transaction-icon rounded-3 bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center"
                            >
                                <i class="bi bi-arrow-down-left"></i>
                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Debits -->
            <div class="col-12 col-md-4">

                <div class="summary-card card border-0 shadow-sm rounded-4 bg-body h-100">

                    <div class="card-body p-3">

                        <div class="d-flex justify-content-between align-items-center">

                            <div>
                                <span class="text-muted small fw-semibold d-block mb-1">
                                    TOTAL DEBITS
                                </span>

                                <h5 class="fw-bold text-danger mb-0">
                                    -₱<?= number_format($totalDebits, 2) ?>
                                </h5>

                                <span class="text-muted" style="font-size: 0.72rem;">
                                    Money released
                                </span>
                            </div>

                            <div
                                class="transaction-icon rounded-3 bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center"
                            >
                                <i class="bi bi-arrow-up-right"></i>
                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Transactions -->
            <div class="col-12 col-md-4">

                <div class="summary-card card border-0 shadow-sm rounded-4 bg-body h-100">

                    <div class="card-body p-3">

                        <div class="d-flex justify-content-between align-items-center">

                            <div>
                                <span class="text-muted small fw-semibold d-block mb-1">
                                    TRANSACTIONS
                                </span>

                                <h5 class="fw-bold text-body mb-0">
                                    <?= number_format($totalTransactions) ?>
                                </h5>

                                <span class="text-muted" style="font-size: 0.72rem;">
                                    Recorded activities
                                </span>
                            </div>

                            <div
                                class="transaction-icon rounded-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center"
                            >
                                <i class="bi bi-receipt"></i>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- Transaction Section -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-body">

            <!-- Header -->
            <div class="card-header bg-transparent border-0 px-3 px-md-4 pt-4 pb-3">

                <div class="d-flex justify-content-between align-items-center">

                    <div>
                        <h5 class="fw-bold text-body mb-1">
                            Transaction History
                        </h5>

                        <p class="text-muted small mb-0">
                            All activity recorded for this account
                        </p>
                    </div>

                    <span class="badge bg-body-tertiary text-muted border rounded-pill px-3 py-2">
                        <?= number_format($totalTransactions) ?>
                    </span>

                </div>

            </div>


            <!-- Desktop Table -->
            <div class="table-responsive d-none d-md-block">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light text-uppercase text-muted">
                        <tr>

                            <th class="py-3 ps-4 small">
                                Date / Time
                            </th>

                            <th class="py-3 small">
                                Type
                            </th>

                            <th class="py-3 small">
                                Description
                            </th>

                            <th class="py-3 text-end pe-4 small">
                                Amount
                            </th>

                        </tr>
                    </thead>

                    <tbody>

                        <?php if (empty($transactions)): ?>

                            <tr>

                                <td colspan="4" class="text-center py-5">

                                    <div
                                        class="mx-auto mb-3 rounded-circle bg-body-tertiary text-muted d-flex align-items-center justify-content-center"
                                        style="width: 60px; height: 60px;"
                                    >
                                        <i class="bi bi-receipt fs-3"></i>
                                    </div>

                                    <h6 class="fw-bold text-body mb-1">
                                        No transactions yet
                                    </h6>

                                    <p class="small text-muted mb-0 mx-auto" style="max-width: 450px;">
                                        Transactions will appear here when loans are issued
                                        or payments are collected using this account.
                                    </p>

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($transactions as $tx): ?>

                                <?php
                                $isCredit = ($tx['type'] === 'CREDIT');
                                $color = $isCredit ? 'success' : 'danger';
                                ?>

                                <tr class="transaction-row">

                                    <!-- Date -->
                                    <td class="ps-4">

                                        <div class="fw-semibold text-body small">
                                            <?= date('M d, Y', strtotime($tx['created_at'])) ?>
                                        </div>

                                        <div class="text-muted" style="font-size: 0.72rem;">
                                            <?= date('h:i A', strtotime($tx['created_at'])) ?>
                                        </div>

                                    </td>


                                    <!-- Type -->
                                    <td>

                                        <span
                                            class="badge bg-<?= $color ?> bg-opacity-10 text-<?= $color ?> border border-<?= $color ?> border-opacity-10 rounded-pill px-2 py-1"
                                        >
                                            <i class="bi <?= $isCredit ? 'bi-arrow-down-left' : 'bi-arrow-up-right' ?> me-1"></i>
                                            <?= htmlspecialchars($tx['type']) ?>
                                        </span>

                                    </td>


                                    <!-- Description -->
                                    <td>

                                        <div class="d-flex align-items-center gap-2">

                                            <div
                                                class="transaction-icon rounded-3 bg-<?= $color ?> bg-opacity-10 text-<?= $color ?> d-flex align-items-center justify-content-center"
                                            >
                                                <i class="bi <?= $isCredit ? 'bi-plus-lg' : 'bi-dash-lg' ?>"></i>
                                            </div>

                                            <div>

                                                <div class="fw-semibold text-body small">
                                                    <?= htmlspecialchars($tx['description']) ?>
                                                </div>

                                                <div class="text-muted" style="font-size: 0.7rem;">
                                                    Account transaction
                                                </div>

                                            </div>

                                        </div>

                                    </td>


                                    <!-- Amount -->
                                    <td class="text-end pe-4">

                                        <span class="amount fw-bold text-<?= $color ?>">
                                            <?= $isCredit ? '+' : '-' ?>₱<?= number_format($tx['amount'], 2) ?>
                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>


            <!-- Mobile Transaction Cards -->
            <div class="d-md-none">

                <?php if (empty($transactions)): ?>

                    <div class="text-center py-5 px-3">

                        <div
                            class="mx-auto mb-3 rounded-circle bg-body-tertiary text-muted d-flex align-items-center justify-content-center"
                            style="width: 60px; height: 60px;"
                        >
                            <i class="bi bi-receipt fs-3"></i>
                        </div>

                        <h6 class="fw-bold text-body mb-1">
                            No transactions yet
                        </h6>

                        <p class="small text-muted mb-0">
                            Transactions will appear here when account activity occurs.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="list-group list-group-flush">

                        <?php foreach ($transactions as $tx): ?>

                            <?php
                            $isCredit = ($tx['type'] === 'CREDIT');
                            $color = $isCredit ? 'success' : 'danger';
                            ?>

                            <div class="list-group-item bg-transparent px-3 py-3">

                                <div class="d-flex align-items-center gap-3">

                                    <!-- Icon -->
                                    <div
                                        class="transaction-icon rounded-3 bg-<?= $color ?> bg-opacity-10 text-<?= $color ?> d-flex align-items-center justify-content-center"
                                    >
                                        <i class="bi <?= $isCredit ? 'bi-arrow-down-left' : 'bi-arrow-up-right' ?>"></i>
                                    </div>


                                    <!-- Description -->
                                    <div class="flex-grow-1 overflow-hidden">

                                        <div class="fw-semibold text-body small text-truncate">
                                            <?= htmlspecialchars($tx['description']) ?>
                                        </div>

                                        <div class="d-flex align-items-center gap-2 mt-1">

                                            <span class="text-muted" style="font-size: 0.7rem;">
                                                <?= date('M d, Y h:i A', strtotime($tx['created_at'])) ?>
                                            </span>

                                            <span
                                                class="badge bg-<?= $color ?> bg-opacity-10 text-<?= $color ?>"
                                                style="font-size: 0.6rem;"
                                            >
                                                <?= htmlspecialchars($tx['type']) ?>
                                            </span>

                                        </div>

                                    </div>


                                    <!-- Amount -->
                                    <div class="text-end">

                                        <div class="fw-bold text-<?= $color ?> mobile-amount">
                                            <?= $isCredit ? '+' : '-' ?>₱<?= number_format($tx['amount'], 2) ?>
                                        </div>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </main>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>