<?php
$pdo = Database::getConnection();
$businessId = $_SESSION['business_id'] ?? null;

if (!$businessId) {
    header('Location: index.php?page=select_business');
    exit;
}

$error = '';
$success = '';

// Handle Add Account Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_account'])) {
    $accountName = trim($_POST['account_name'] ?? '');
    $balance = trim($_POST['balance'] ?? 0);

    if (!empty($accountName) && is_numeric($balance)) {
        $stmt = $pdo->prepare("
            INSERT INTO loan_accounts (business_id, account_name, balance)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$businessId, $accountName, $balance]);

        header('Location: index.php?page=loan_accounts&success=1');
        exit;
    } else {
        $error = "Account name is required and balance must be a valid number.";
    }
}

if (isset($_GET['success'])) {
    $success = "Account added successfully!";
}

$activePage = 'loan_accounts';
$pageTitle = "Accounts & Wallets - Loan Management";

// Fetch accounts
$stmt = $pdo->prepare("
    SELECT *
    FROM loan_accounts
    WHERE business_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$businessId]);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total funds
$totalFunds = array_sum(array_column($accounts, 'balance'));
$totalAccounts = count($accounts);
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

        .account-card {
            transition: all 0.2s ease;
        }

        .account-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 .5rem 1.2rem rgba(0, 0, 0, 0.08) !important;
        }

        .wallet-icon {
            width: 44px;
            height: 44px;
            flex-shrink: 0;
        }

        .summary-card {
            transition: all 0.2s ease;
        }

        .summary-card:hover {
            transform: translateY(-2px);
        }

        .account-balance {
            letter-spacing: -0.5px;
        }

        @media (max-width: 575.98px) {
            .page-title {
                font-size: 1.35rem !important;
            }

            .page-subtitle {
                font-size: 0.78rem;
            }

            .add-account-btn {
                width: 100%;
                justify-content: center;
            }

            .summary-card {
                padding: 1rem !important;
            }

            .account-card {
                padding: 1rem !important;
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

        <!-- Page Header -->
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">

            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <div
                        class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center"
                        style="width: 38px; height: 38px;"
                    >
                        <i class="bi bi-wallet2 fs-5"></i>
                    </div>

                    <h2 class="page-title fw-bold text-body mb-0">
                        Accounts & Wallets
                    </h2>
                </div>

                <p class="page-subtitle text-muted mb-0 ms-sm-5">
                    Manage your funding sources for
                    <span class="fw-semibold text-primary">
                        <?= htmlspecialchars($_SESSION['business_name'] ?? '') ?>
                    </span>
                </p>
            </div>

            <button
                type="button"
                class="add-account-btn btn btn-primary fw-semibold px-3 py-2 rounded-3 shadow-sm d-flex align-items-center gap-2"
                data-bs-toggle="modal"
                data-bs-target="#addAccountModal"
            >
                <i class="bi bi-plus-lg"></i>
                Add Account
            </button>

        </div>

        <!-- Alerts -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-3 py-2 px-3 small mb-4">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-3 py-2 px-3 small mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>


        <!-- Financial Summary -->
        <div class="row g-3 mb-4">

            <!-- Total Funds -->
            <div class="col-12 col-md-7 col-xl-5">

                <div class="summary-card card border-0 shadow-sm rounded-4 bg-body h-100">

                    <div class="card-body p-3 p-md-4">

                        <div class="d-flex align-items-center justify-content-between">

                            <div>

                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="text-muted small fw-semibold">
                                        TOTAL AVAILABLE FUNDS
                                    </span>

                                    <span class="badge rounded-pill bg-success bg-opacity-10 text-success fw-semibold">
                                        <i class="bi bi-arrow-up-short"></i>
                                        Available
                                    </span>
                                </div>

                                <div class="d-flex align-items-baseline gap-1">
                                    <span class="text-muted fw-semibold">₱</span>

                                    <h3 class="fw-bold text-body mb-0">
                                        <?= number_format($totalFunds, 2) ?>
                                    </h3>
                                </div>

                                <div class="text-muted small mt-2">
                                    Across <?= number_format($totalAccounts) ?>
                                    <?= $totalAccounts == 1 ? 'account' : 'accounts' ?>
                                </div>

                            </div>

                            <div
                                class="wallet-icon rounded-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center"
                            >
                                <i class="bi bi-cash-stack fs-5"></i>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <!-- Account Count -->
            <div class="col-12 col-md-5 col-xl-3">

                <div class="summary-card card border-0 shadow-sm rounded-4 bg-body h-100">

                    <div class="card-body p-3 p-md-4">

                        <div class="d-flex justify-content-between align-items-center">

                            <div>
                                <span class="text-muted small fw-semibold d-block mb-2">
                                    FUNDING ACCOUNTS
                                </span>

                                <h3 class="fw-bold text-body mb-0">
                                    <?= number_format($totalAccounts) ?>
                                </h3>

                                <span class="text-muted small">
                                    Active wallets
                                </span>
                            </div>

                            <div
                                class="wallet-icon rounded-3 bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center"
                            >
                                <i class="bi bi-wallet2 fs-5"></i>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- Section Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">

            <div>
                <h5 class="fw-bold text-body mb-1">
                    Your Accounts
                </h5>

                <p class="text-muted small mb-0">
                    Select an account to view its transactions.
                </p>
            </div>

            <?php if (!empty($accounts)): ?>
                <span class="badge bg-body text-muted border rounded-pill px-3 py-2">
                    <?= number_format($totalAccounts) ?>
                    <?= $totalAccounts == 1 ? 'Account' : 'Accounts' ?>
                </span>
            <?php endif; ?>

        </div>


        <!-- Accounts -->
        <div class="row g-3">

            <?php if (empty($accounts)): ?>

                <!-- Empty State -->
                <div class="col-12">

                    <div class="card border-0 shadow-sm rounded-4 bg-body">

                        <div class="card-body text-center py-5 px-4">

                            <div
                                class="mx-auto mb-3 rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center"
                                style="width: 64px; height: 64px;"
                            >
                                <i class="bi bi-wallet2 fs-3"></i>
                            </div>

                            <h5 class="fw-bold text-body mb-2">
                                No funding accounts yet
                            </h5>

                            <p class="text-muted small mb-4 mx-auto" style="max-width: 430px;">
                                Add your first funding source such as GCash,
                                a bank account, or cash on hand to start
                                managing your loan funds.
                            </p>

                            <button
                                type="button"
                                class="btn btn-primary fw-semibold px-4"
                                data-bs-toggle="modal"
                                data-bs-target="#addAccountModal"
                            >
                                <i class="bi bi-plus-lg me-1"></i>
                                Add Your First Account
                            </button>

                        </div>

                    </div>

                </div>

            <?php else: ?>

                <?php foreach ($accounts as $acc): ?>

                    <div class="col-12 col-md-6 col-xl-4">

                        <a
                            href="index.php?page=loan_account_details&id=<?= $acc['id'] ?>"
                            class="text-decoration-none"
                        >

                            <div class="account-card card border-0 shadow-sm rounded-4 bg-body h-100">

                                <div class="card-body p-3 p-md-4">

                                    <!-- Account Header -->
                                    <div class="d-flex justify-content-between align-items-start mb-4">

                                        <div class="d-flex align-items-center gap-3">

                                            <div
                                                class="wallet-icon rounded-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center"
                                            >
                                                <i class="bi bi-wallet2 fs-5"></i>
                                            </div>

                                            <div class="overflow-hidden">

                                                <span class="text-muted d-block small mb-1">
                                                    Funding Account
                                                </span>

                                                <h5 class="fw-bold text-body mb-0 text-truncate">
                                                    <?= htmlspecialchars($acc['account_name']) ?>
                                                </h5>

                                            </div>

                                        </div>

                                        <i class="bi bi-three-dots-vertical text-muted"></i>

                                    </div>


                                    <!-- Balance -->
                                    <div class="mb-3">

                                        <span class="text-muted small d-block mb-1">
                                            Current Balance
                                        </span>

                                        <h3 class="account-balance fw-bold text-success mb-0">
                                            ₱<?= number_format($acc['balance'], 2) ?>
                                        </h3>

                                    </div>


                                    <!-- Footer -->
                                    <div class="border-top pt-3 d-flex justify-content-between align-items-center">

                                        <span class="text-muted small">
                                            <i class="bi bi-clock me-1"></i>
                                            Account details
                                        </span>

                                        <span class="text-primary small fw-semibold">
                                            View
                                            <i class="bi bi-arrow-right ms-1"></i>
                                        </span>

                                    </div>

                                </div>

                            </div>

                        </a>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </main>

</div>


<!-- Add Account Modal -->
<div
    class="modal fade"
    id="addAccountModal"
    tabindex="-1"
    aria-labelledby="addAccountModalLabel"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content border-0 shadow-lg rounded-4">

            <div class="modal-header border-bottom px-4 py-3">

                <div>
                    <h5
                        class="modal-title fw-bold text-body mb-1"
                        id="addAccountModalLabel"
                    >
                        <i class="bi bi-wallet2 text-primary me-2"></i>
                        Add Funding Account
                    </h5>

                    <small class="text-muted">
                        Add a wallet or account used for loan funding.
                    </small>
                </div>

                <button
                    type="button"
                    class="btn-close shadow-none"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>

            </div>


            <form method="POST">

                <input
                    type="hidden"
                    name="add_account"
                    value="1"
                >

                <div class="modal-body p-4">

                    <div class="mb-3">

                        <label class="form-label fw-semibold small">
                            Account Name
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            name="account_name"
                            class="form-control form-control-lg shadow-none"
                            required
                            placeholder="e.g. GCash, BDO Bank, Cash on Hand"
                        >

                    </div>


                    <div class="mb-2">

                        <label class="form-label fw-semibold small">
                            Initial Balance
                            <span class="text-danger">*</span>
                        </label>

                        <div class="input-group input-group-lg">

                            <span class="input-group-text bg-body-tertiary">
                                ₱
                            </span>

                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="balance"
                                class="form-control shadow-none"
                                required
                                placeholder="0.00"
                            >

                        </div>

                        <div class="form-text small">
                            You can manage the account balance and transactions later.
                        </div>

                    </div>

                </div>


                <div class="modal-footer border-top px-4 py-3">

                    <button
                        type="button"
                        class="btn btn-light px-4 fw-semibold"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary px-4 fw-bold shadow-sm"
                    >
                        <i class="bi bi-check-lg me-1"></i>
                        Save Account
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>