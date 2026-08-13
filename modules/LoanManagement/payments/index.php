<?php
$pdo = Database::getConnection();
$businessId = $_SESSION['business_id'] ?? null;

if (!$businessId) {
    header('Location: index.php?page=select_business');
    exit;
}

$error = '';
$success = '';

if (isset($_GET['success_payment'])) {
    $success = "Payment recorded successfully!";
}

$activePage = 'payments';
$pageTitle = "Payments History - Loan Management";

// Fetch all payments for this business
$stmt = $pdo->prepare("
    SELECT p.*,
           l.reference_number,
           l.total_payable,
           l.principal_amount,
           l.status AS loan_status,
           CONCAT(b.first_name, ' ', b.last_name) AS borrower_name,
           a.account_name
    FROM loan_payments p
    JOIN loans l ON p.loan_id = l.id
    JOIN loan_borrowers b ON l.borrower_id = b.id
    JOIN loan_accounts a ON l.account_id = a.id
    WHERE p.business_id = ?
    ORDER BY p.payment_date DESC, p.created_at DESC
");
$stmt->execute([$businessId]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active loans for the "Record Payment" modal dropdown
$activeLoansStmt = $pdo->prepare("
    SELECT
        l.id,
        l.reference_number,
        l.total_payable,
        CONCAT(b.first_name, ' ', b.last_name) AS borrower_name
    FROM loans l
    JOIN loan_borrowers b ON l.borrower_id = b.id
    WHERE l.business_id = ?
      AND l.status = 'active'
    ORDER BY b.first_name ASC
");
$activeLoansStmt->execute([$businessId]);
$activeLoans = $activeLoansStmt->fetchAll(PDO::FETCH_ASSOC);
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

        .page-content {
            min-width: 0;
        }

        .payments-table {
            min-width: 950px;
        }

        .payments-table th,
        .payments-table td {
            white-space: nowrap;
        }

        .payments-table td.notes-cell {
            white-space: normal;
            min-width: 180px;
            max-width: 250px;
        }

        .payment-card {
            border-radius: 1rem;
        }

        .mobile-action-btn {
            white-space: nowrap;
        }

        @media (max-width: 991.98px) {
            .page-content {
                width: 100%;
            }

            .page-header {
                align-items: stretch !important;
            }

            .page-header .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 575.98px) {
            .page-content {
                padding: 1rem !important;
            }

            .page-title {
                font-size: 1.35rem;
            }

            .page-description {
                font-size: 0.8rem;
                line-height: 1.4;
            }

            .payment-card {
                border-radius: 0.75rem;
            }

            .table-responsive {
                border-radius: 0.75rem;
            }

            .payments-table th,
            .payments-table td {
                padding-top: 0.7rem;
                padding-bottom: 0.7rem;
            }

            .modal-dialog {
                margin: 0.75rem;
            }

            .modal-content {
                border-radius: 1rem !important;
            }

            .modal-body {
                padding: 1rem !important;
            }

            .modal-footer {
                padding: 0.75rem 1rem !important;
            }

            .modal-footer .btn {
                flex: 1;
            }
        }
    </style>
</head>

<body class="bg-body-tertiary">

<div class="d-flex flex-column flex-lg-row" style="min-height: 100vh;">

    <?php include __DIR__ . '/../../../resources/partials/loansidebar.php'; ?>

    <div class="page-content p-3 p-md-4 flex-grow-1 bg-body-tertiary">

        <!-- Page Header -->
        <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

            <div>
                <h2 class="page-title fw-bold text-body mb-1">
                    Payments History
                </h2>

                <p class="page-description text-muted small mb-0">
                    Track all incoming loan collections and payment logs for
                    <span class="fw-bold text-primary">
                        <?= htmlspecialchars($_SESSION['business_name'] ?? '') ?>
                    </span>
                </p>
            </div>

            <button
                type="button"
                class="btn btn-primary fw-semibold px-3 py-2 shadow-sm d-flex align-items-center justify-content-center gap-2"
                data-bs-toggle="modal"
                data-bs-target="#recordPaymentModal"
            >
                <i class="bi bi-plus-lg"></i>
                Record Payment
            </button>

        </div>

        <!-- Success Message -->
        <?php if (!empty($success)): ?>

            <div
                class="alert alert-success alert-dismissible fade show shadow-sm border-0 rounded-3 mb-4"
                role="alert"
            >
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= htmlspecialchars($success) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"
                ></button>
            </div>

        <?php endif; ?>

        <!-- Error Message -->
        <?php if (!empty($error)): ?>

            <div
                class="alert alert-danger alert-dismissible fade show shadow-sm border-0 rounded-3 mb-4"
                role="alert"
            >
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= htmlspecialchars($error) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"
                ></button>
            </div>

        <?php endif; ?>


        <!-- Payments Table Card -->
        <div class="card payment-card border-0 shadow-sm overflow-hidden">

            <div class="table-responsive">

                <table class="table payments-table table-hover align-middle mb-0">

                    <thead class="table-light text-uppercase small text-muted">

                        <tr>
                            <th class="py-3 ps-4">
                                Payment Date
                            </th>

                            <th class="py-3">
                                Borrower
                            </th>

                            <th class="py-3">
                                Loan Ref
                            </th>

                            <th class="py-3">
                                Amount Paid
                            </th>

                            <th class="py-3">
                                Funding Account
                            </th>

                            <th class="py-3">
                                Notes / Remarks
                            </th>

                            <th class="py-3 text-end pe-4">
                                Actions
                            </th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if (empty($payments)): ?>

                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">

                                <div class="mb-2">
                                    <i class="bi bi-wallet2 display-6 opacity-50"></i>
                                </div>

                                <p class="mb-1 fw-semibold">
                                    No payments recorded yet
                                </p>

                                <p class="small text-muted mb-0">
                                    Payments collected from active loans will appear here.
                                </p>

                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($payments as $pay): ?>

                            <tr>

                                <!-- Payment Date -->
                                <td class="ps-4 text-muted small fw-semibold">

                                    <i class="bi bi-calendar-event me-1"></i>

                                    <?= date(
                                        'M d, Y',
                                        strtotime($pay['payment_date'])
                                    ) ?>

                                </td>


                                <!-- Borrower -->
                                <td>

                                    <div class="fw-bold text-body">
                                        <?= htmlspecialchars($pay['borrower_name']) ?>
                                    </div>

                                </td>


                                <!-- Loan Reference -->
                                <td>

                                    <span class="badge bg-secondary bg-opacity-10 text-body fw-semibold px-2 py-1">

                                        <?= htmlspecialchars(
                                            $pay['reference_number']
                                            ?? '#' . $pay['loan_id']
                                        ) ?>

                                    </span>

                                </td>


                                <!-- Amount -->
                                <td>

                                    <div class="fw-bold text-success">

                                        ₱<?= number_format(
                                            $pay['payment_amount'],
                                            2
                                        ) ?>

                                    </div>

                                </td>


                                <!-- Account -->
                                <td class="text-muted small">

                                    <i class="bi bi-wallet2 me-1"></i>

                                    <?= htmlspecialchars($pay['account_name']) ?>

                                </td>


                                <!-- Notes -->
                                <td class="notes-cell text-muted small">

                                    <?php if (!empty($pay['notes'])): ?>

                                        <?= htmlspecialchars($pay['notes']) ?>

                                    <?php else: ?>

                                        <span class="text-muted opacity-50">
                                            —
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- Actions -->
                                <td class="text-end pe-4">

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light border fw-semibold px-2 mobile-action-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#paymentDetailsModal<?= $pay['id'] ?>"
                                    >

                                        <i class="bi bi-eye me-1"></i>
                                        Details

                                    </button>

                                </td>

                            </tr>


                            <!-- Payment Details Modal -->
                            <div
                                class="modal fade"
                                id="paymentDetailsModal<?= $pay['id'] ?>"
                                tabindex="-1"
                                aria-hidden="true"
                            >

                                <div class="modal-dialog modal-dialog-centered">

                                    <div class="modal-content border-0 shadow-lg rounded-4">

                                        <div class="modal-header border-bottom px-4 py-3">

                                            <h5 class="modal-title fw-bold fs-6 text-body">

                                                <i class="bi bi-receipt text-primary me-2"></i>

                                                Payment Details

                                            </h5>

                                            <button
                                                type="button"
                                                class="btn-close shadow-none"
                                                data-bs-dismiss="modal"
                                                aria-label="Close"
                                            ></button>

                                        </div>


                                        <div class="modal-body p-4 text-start">

                                            <!-- Amount -->
                                            <div class="text-center mb-4 bg-success bg-opacity-10 p-3 rounded-4">

                                                <span class="text-muted small d-block">
                                                    Amount Received
                                                </span>

                                                <h3 class="fw-bold text-success mb-0">

                                                    ₱<?= number_format(
                                                        $pay['payment_amount'],
                                                        2
                                                    ) ?>

                                                </h3>

                                                <span class="badge bg-success bg-opacity-25 text-success mt-1">

                                                    Confirmed Collection

                                                </span>

                                            </div>


                                            <!-- Payment Information -->
                                            <div class="row g-3">

                                                <div class="col-12 col-sm-6">

                                                    <span class="text-muted small d-block">
                                                        Borrower Name
                                                    </span>

                                                    <strong class="text-body">

                                                        <?= htmlspecialchars(
                                                            $pay['borrower_name']
                                                        ) ?>

                                                    </strong>

                                                </div>


                                                <div class="col-12 col-sm-6">

                                                    <span class="text-muted small d-block">
                                                        Loan Reference
                                                    </span>

                                                    <strong class="text-body">

                                                        <?= htmlspecialchars(
                                                            $pay['reference_number']
                                                            ?? '#' . $pay['loan_id']
                                                        ) ?>

                                                    </strong>

                                                </div>


                                                <div class="col-12 col-sm-6">

                                                    <span class="text-muted small d-block">
                                                        Payment Date
                                                    </span>

                                                    <strong class="text-body">

                                                        <?= date(
                                                            'M d, Y',
                                                            strtotime($pay['payment_date'])
                                                        ) ?>

                                                    </strong>

                                                </div>


                                                <div class="col-12 col-sm-6">

                                                    <span class="text-muted small d-block">
                                                        Funding Account
                                                    </span>

                                                    <strong class="text-body">

                                                        <?= htmlspecialchars(
                                                            $pay['account_name']
                                                        ) ?>

                                                    </strong>

                                                </div>


                                                <div class="col-12 col-sm-6">

                                                    <span class="text-muted small d-block">
                                                        Total Loan Payable
                                                    </span>

                                                    <strong class="text-body">

                                                        ₱<?= number_format(
                                                            $pay['total_payable'],
                                                            2
                                                        ) ?>

                                                    </strong>

                                                </div>


                                                <div class="col-12">

                                                    <span class="text-muted small d-block">
                                                        Notes / Remarks
                                                    </span>

                                                    <div class="p-2 bg-body-tertiary rounded-3 text-body small mt-1">

                                                        <?php if (!empty($pay['notes'])): ?>

                                                            <?= htmlspecialchars($pay['notes']) ?>

                                                        <?php else: ?>

                                                            No remarks provided.

                                                        <?php endif; ?>

                                                    </div>

                                                </div>

                                            </div>

                                        </div>


                                        <div class="modal-footer border-top px-4 py-3">

                                            <button
                                                type="button"
                                                class="btn btn-secondary px-4 fw-semibold"
                                                data-bs-dismiss="modal"
                                            >
                                                Close
                                            </button>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>


<!-- Record Payment Modal -->
<div
    class="modal fade"
    id="recordPaymentModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content border-0 shadow-lg rounded-4">

            <div class="modal-header border-bottom px-4 py-3">

                <h5 class="modal-title fw-bold fs-6 text-body">

                    <i class="bi bi-cash-coin text-success me-2"></i>

                    Record Loan Payment

                </h5>

                <button
                    type="button"
                    class="btn-close shadow-none"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>

            </div>


            <form method="POST" action="index.php?page=loans">

                <input
                    type="hidden"
                    name="record_payment"
                    value="1"
                >


                <div class="modal-body p-4 text-start">

                    <!-- Loan -->
                    <div class="mb-3">

                        <label class="form-label fw-semibold small">

                            Select Active Loan

                            <span class="text-danger">*</span>

                        </label>

                        <select
                            name="loan_id"
                            class="form-select shadow-none"
                            required
                        >

                            <option value="">
                                Choose active loan...
                            </option>

                            <?php foreach ($activeLoans as $al): ?>

                                <option value="<?= $al['id'] ?>">

                                    <?= htmlspecialchars(
                                        $al['borrower_name']
                                    ) ?>

                                    —

                                    Ref:

                                    <?= htmlspecialchars(
                                        $al['reference_number']
                                        ?? '#' . $al['id']
                                    ) ?>

                                    (Due: ₱<?= number_format(
                                        $al['total_payable'],
                                        2
                                    ) ?>)

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- Payment Amount -->
                    <div class="mb-3">

                        <label class="form-label fw-semibold small">

                            Payment Amount (₱)

                            <span class="text-danger">*</span>

                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            name="payment_amount"
                            class="form-control shadow-none"
                            required
                            placeholder="Enter amount paid"
                        >

                    </div>


                    <!-- Payment Date -->
                    <div class="mb-3">

                        <label class="form-label fw-semibold small">

                            Payment Date

                            <span class="text-danger">*</span>

                        </label>

                        <input
                            type="date"
                            name="payment_date"
                            class="form-control shadow-none"
                            value="<?= date('Y-m-d') ?>"
                            required
                        >

                    </div>


                    <!-- Notes -->
                    <div class="mb-3">

                        <label class="form-label fw-semibold small">

                            Notes / Remarks (Optional)

                        </label>

                        <textarea
                            name="payment_notes"
                            class="form-control shadow-none"
                            rows="2"
                            placeholder="e.g. Daily collection cash payment"
                        ></textarea>

                    </div>

                </div>


                <div class="modal-footer border-top px-4 py-3">

                    <button
                        type="button"
                        class="btn btn-secondary px-4 fw-semibold"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn btn-success px-4 fw-bold shadow-sm"
                    >
                        <i class="bi bi-check-lg me-1"></i>
                        Save Payment
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>