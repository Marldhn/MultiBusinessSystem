<?php

$pdo = Database::getConnection();

$businessId = $_SESSION['business_id'] ?? null;
$borrowerId = $_GET['id'] ?? null;

if (!$businessId || !$borrowerId) {
    header('Location: index.php?page=borrowers');
    exit;
}

$error = '';
$success = '';

// Handle update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_borrower'])) {

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($firstName !== '' && $lastName !== '') {

        $updateStmt = $pdo->prepare("
            UPDATE loan_borrowers
            SET
                first_name = ?,
                last_name = ?,
                date_of_birth = ?,
                occupation = ?,
                phone = ?,
                address = ?
            WHERE id = ?
              AND business_id = ?
        ");

        $updateStmt->execute([
            $firstName,
            $lastName,
            $dateOfBirth !== '' ? $dateOfBirth : null,
            $occupation !== '' ? $occupation : null,
            $phone,
            $address,
            $borrowerId,
            $businessId
        ]);

        header("Location: index.php?page=borrower_details&id=$borrowerId&success=1");
        exit;

    } else {
        $error = 'Both First Name and Last Name are required.';
    }
}

// Fetch borrower profile
$stmt = $pdo->prepare("
    SELECT *
    FROM loan_borrowers
    WHERE id = ?
      AND business_id = ?
");

$stmt->execute([
    $borrowerId,
    $businessId
]);

$borrower = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$borrower) {
    header('Location: index.php?page=borrowers');
    exit;
}

// Fetch borrower's loans
$loansStmt = $pdo->prepare("
    SELECT
        l.*,
        COALESCE(p_totals.total_paid, 0) AS total_paid
    FROM loans l
    LEFT JOIN (
        SELECT
            loan_id,
            SUM(payment_amount) AS total_paid
        FROM loan_payments
        WHERE business_id = ?
        GROUP BY loan_id
    ) p_totals ON l.id = p_totals.loan_id
    WHERE l.borrower_id = ?
      AND l.business_id = ?
    ORDER BY l.created_at DESC
");

$loansStmt->execute([
    $businessId,
    $borrowerId,
    $businessId
]);

$loans = $loansStmt->fetchAll(PDO::FETCH_ASSOC);

// Loan summary
$totalLoans = count($loans);
$activeLoans = 0;
$totalBorrowed = 0;
$totalPayable = 0;
$totalPaid = 0;

foreach ($loans as $loan) {

    $status = strtolower(trim($loan['status'] ?? 'active'));

    if (
        $status === 'active' ||
        $status === 'ongoing' ||
        $status === 'approved'
    ) {
        $activeLoans++;
    }

    $totalBorrowed += (float)($loan['principal_amount'] ?? 0);
    $totalPayable += (float)($loan['total_payable'] ?? 0);
    $totalPaid += (float)($loan['total_paid'] ?? 0);
}

// Remaining balance
$totalBalance = max(0, $totalPayable - $totalPaid);

$activePage = 'borrowers';

$pageTitle = htmlspecialchars(
    ($borrower['first_name'] ?? '') . ' ' . ($borrower['last_name'] ?? '')
) . ' - Borrower Details';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= $pageTitle ?></title>

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

        .profile-card,
        .loan-card,
        .summary-card {
            border: 0;
            border-radius: 16px;
        }

        .summary-card {
            height: 100%;
        }

        .summary-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .summary-label {
            font-size: 0.7rem;
            color: var(--bs-secondary-color);
            margin-bottom: 3px;
            white-space: nowrap;
        }

        .summary-value {
            font-size: 1.15rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .form-label {
            font-size: 0.78rem;
        }

        .form-control {
            font-size: 0.85rem;
        }

        .loan-table {
            font-size: 0.85rem;
        }

        .loan-table th {
            font-size: 0.7rem;
            letter-spacing: 0.04em;
            white-space: nowrap;
        }

        .loan-table td {
            white-space: nowrap;
        }

        .remaining-card {
            border-left: 4px solid var(--bs-danger) !important;
        }

        @media (max-width: 1199.98px) {

            .summary-value {
                font-size: 1rem;
            }

            .summary-icon {
                width: 38px;
                height: 38px;
            }

        }

        @media (max-width: 575.98px) {

            .main-content {
                padding: 14px !important;
            }

            .page-title {
                font-size: 1.35rem !important;
            }

            .loan-card,
            .profile-card,
            .summary-card {
                border-radius: 14px;
            }

            .profile-card {
                padding: 16px !important;
            }

            .summary-value {
                font-size: 1rem;
            }

            .summary-label {
                font-size: 0.65rem;
            }

            .summary-icon {
                width: 36px;
                height: 36px;
                font-size: 0.95rem;
            }

            .issue-loan-btn {
                width: 100%;
            }

        }

    </style>

</head>

<body class="bg-body-tertiary">

<div class="d-flex flex-column flex-lg-row min-vh-100">

    <?php include __DIR__ . '/../../../resources/partials/loansidebar.php'; ?>

    <main class="flex-grow-1 bg-body-tertiary">

        <div class="main-content p-3 p-md-4">

            <!-- Page Header -->

            <div class="mb-4">

                <a
                    href="index.php?page=borrowers"
                    class="text-decoration-none small fw-bold text-muted mb-2 d-inline-block"
                >
                    <i class="bi bi-arrow-left me-1"></i>
                    Back to Borrowers
                </a>

                <h2 class="page-title fw-bold text-body mb-1 fs-3">

                    <?= htmlspecialchars(
                        ($borrower['first_name'] ?? '') . ' ' .
                        ($borrower['last_name'] ?? '')
                    ) ?>

                </h2>

                <p class="text-muted small mb-0">
                    Client Profile & Loan History
                </p>

            </div>

            <!-- Success Message -->

            <?php if (isset($_GET['success'])): ?>

                <div
                    class="alert alert-success border-0 shadow-sm py-2 px-3 small mb-4"
                >
                    <i class="bi bi-check-circle-fill me-2"></i>
                    Borrower details updated successfully!
                </div>

            <?php endif; ?>

            <!-- Error Message -->

            <?php if (!empty($error)): ?>

                <div
                    class="alert alert-danger border-0 shadow-sm py-2 px-3 small mb-4"
                >
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>

            <!-- Loan Summary Cards -->

            <div class="row g-3 mb-4">

                <!-- Total Loans -->

                <div class="col-6 col-xl">

                    <div class="summary-card card shadow-sm p-3">

                        <div class="d-flex align-items-center gap-3">

                            <div class="summary-icon bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-file-earmark-text"></i>
                            </div>

                            <div class="min-width-0">

                                <div class="summary-label">
                                    Total Loans
                                </div>

                                <div class="summary-value text-body">
                                    <?= number_format($totalLoans) ?>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- Active Loans -->

                <div class="col-6 col-xl">

                    <div class="summary-card card shadow-sm p-3">

                        <div class="d-flex align-items-center gap-3">

                            <div class="summary-icon bg-success bg-opacity-10 text-success">
                                <i class="bi bi-arrow-repeat"></i>
                            </div>

                            <div class="min-width-0">

                                <div class="summary-label">
                                    Active Loans
                                </div>

                                <div class="summary-value text-body">
                                    <?= number_format($activeLoans) ?>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- Total Borrowed -->

                <div class="col-6 col-xl">

                    <div class="summary-card card shadow-sm p-3">

                        <div class="d-flex align-items-center gap-3">

                            <div class="summary-icon bg-info bg-opacity-10 text-info">
                                <i class="bi bi-cash-stack"></i>
                            </div>

                            <div class="min-width-0">

                                <div class="summary-label">
                                    Total Borrowed
                                </div>

                                <div class="summary-value text-body">
                                    ₱<?= number_format($totalBorrowed, 2) ?>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- Total Paid -->

                <div class="col-6 col-xl">

                    <div class="summary-card card shadow-sm p-3">

                        <div class="d-flex align-items-center gap-3">

                            <div class="summary-icon bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-wallet2"></i>
                            </div>

                            <div class="min-width-0">

                                <div class="summary-label">
                                    Total Paid
                                </div>

                                <div class="summary-value text-body">
                                    ₱<?= number_format($totalPaid, 2) ?>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- Remaining Balance -->

                <div class="col-6 col-xl">

                    <div class="summary-card remaining-card card shadow-sm p-3">

                        <div class="d-flex align-items-center gap-3">

                            <div class="summary-icon bg-danger bg-opacity-10 text-danger">
                                <i class="bi bi-wallet2"></i>
                            </div>

                            <div class="min-width-0">

                                <div class="summary-label">
                                    Remaining Balance
                                </div>

                                <div class="summary-value text-danger">
                                    ₱<?= number_format($totalBalance, 2) ?>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <!-- Borrower + Loan History -->

            <div class="row g-4">

                <!-- Borrower Information -->

                <div class="col-lg-4">

                    <div class="profile-card card shadow-sm p-4">

                        <h5 class="fw-bold mb-3 fs-6 text-primary">

                            <i class="bi bi-person-vcard me-2"></i>
                            Edit Borrower Info

                        </h5>

                        <form method="POST">

                            <input
                                type="hidden"
                                name="update_borrower"
                                value="1"
                            >

                            <!-- First Name -->

                            <div class="mb-3">

                                <label class="form-label fw-semibold">
                                    First Name
                                </label>

                                <input
                                    type="text"
                                    name="first_name"
                                    class="form-control shadow-none"
                                    value="<?= htmlspecialchars(
                                        $borrower['first_name'] ?? ''
                                    ) ?>"
                                    required
                                >

                            </div>

                            <!-- Last Name -->

                            <div class="mb-3">

                                <label class="form-label fw-semibold">
                                    Last Name
                                </label>

                                <input
                                    type="text"
                                    name="last_name"
                                    class="form-control shadow-none"
                                    value="<?= htmlspecialchars(
                                        $borrower['last_name'] ?? ''
                                    ) ?>"
                                    required
                                >

                            </div>

                            <!-- Date of Birth -->

                            <div class="mb-3">

                                <label class="form-label fw-semibold">
                                    Date of Birth
                                </label>

                                <input
                                    type="date"
                                    name="date_of_birth"
                                    class="form-control shadow-none"
                                    value="<?= htmlspecialchars(
                                        $borrower['date_of_birth'] ?? ''
                                    ) ?>"
                                >

                            </div>

                            <!-- Occupation -->

                            <div class="mb-3">

                                <label class="form-label fw-semibold">
                                    Occupation
                                </label>

                                <input
                                    type="text"
                                    name="occupation"
                                    class="form-control shadow-none"
                                    value="<?= htmlspecialchars(
                                        $borrower['occupation'] ?? ''
                                    ) ?>"
                                    placeholder="e.g. Teacher"
                                >

                            </div>

                            <!-- Phone -->

                            <div class="mb-3">

                                <label class="form-label fw-semibold">
                                    Phone Number
                                </label>

                                <input
                                    type="text"
                                    name="phone"
                                    class="form-control shadow-none"
                                    value="<?= htmlspecialchars(
                                        $borrower['phone'] ?? ''
                                    ) ?>"
                                    placeholder="e.g. 09123456789"
                                >

                            </div>

                            <!-- Address -->

                            <div class="mb-3">

                                <label class="form-label fw-semibold">
                                    Address
                                </label>

                                <textarea
                                    name="address"
                                    class="form-control shadow-none"
                                    rows="3"
                                    placeholder="Complete address"
                                ><?= htmlspecialchars(
                                    $borrower['address'] ?? ''
                                ) ?></textarea>

                            </div>

                            <button
                                type="submit"
                                class="btn btn-primary w-100 fw-bold shadow-sm py-2"
                            >
                                <i class="bi bi-check-lg me-1"></i>
                                Update Profile
                            </button>

                        </form>

                    </div>

                </div>

                <!-- Loan History -->

                <div class="col-lg-8">

                    <div class="loan-card card shadow-sm p-4">

                        <div
                            class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-3"
                        >

                            <div>

                                <h5 class="fw-bold mb-1 fs-6">
                                    Active & Past Loans
                                </h5>

                                <div class="small text-muted">
                                    Loan history for this borrower
                                </div>

                            </div>

                            <a
                                href="index.php?page=create_loan&borrower_id=<?= (int)$borrower['id'] ?>"
                                class="issue-loan-btn btn btn-sm btn-outline-primary fw-semibold"
                            >
                                <i class="bi bi-plus-lg me-1"></i>
                                Issue New Loan
                            </a>

                        </div>

                        <?php if (empty($loans)): ?>

                            <div class="text-center py-5 text-muted">

                                <div class="mb-2">
                                    <i class="bi bi-file-earmark-text display-6 opacity-50"></i>
                                </div>

                                <p class="mb-1 fw-semibold">
                                    No loans issued to this borrower yet.
                                </p>

                                <p class="small text-muted mb-0">
                                    Create a new loan using the button above.
                                </p>

                            </div>

                        <?php else: ?>

                            <div class="table-responsive">

                                <table
                                    class="loan-table table table-hover align-middle mb-0"
                                >

                                    <thead
                                        class="table-light text-uppercase text-muted"
                                    >

                                        <tr>

                                            <th class="py-2 ps-3">
                                                Ref #
                                            </th>

                                            <th class="py-2">
                                                Principal
                                            </th>

                                            <th class="py-2">
                                                Total Payable
                                            </th>

                                            <th class="py-2">
                                                Paid
                                            </th>

                                            <th class="py-2">
                                                Balance
                                            </th>

                                            <th class="py-2">
                                                Status
                                            </th>

                                            <th class="py-2 text-end pe-3">
                                                Action
                                            </th>

                                        </tr>

                                    </thead>

                                    <tbody>

                                    <?php foreach ($loans as $loan): ?>

                                        <?php

                                        $loanPaid = (float)($loan['total_paid'] ?? 0);
                                        $loanPayable = (float)($loan['total_payable'] ?? 0);
                                        $loanBalance = max(0, $loanPayable - $loanPaid);

                                        $status = strtolower(
                                            trim($loan['status'] ?? 'active')
                                        );

                                        $badgeBg =
                                            'bg-warning bg-opacity-10 text-warning';

                                        if (
                                            $status === 'completed' ||
                                            $status === 'paid'
                                        ) {
                                            $badgeBg =
                                                'bg-success bg-opacity-10 text-success';
                                        }

                                        if ($status === 'defaulted') {
                                            $badgeBg =
                                                'bg-danger bg-opacity-10 text-danger';
                                        }

                                        ?>

                                        <tr>

                                            <!-- Reference -->

                                            <td class="ps-3 fw-bold text-body">

                                                <?= htmlspecialchars(
                                                    $loan['reference_number']
                                                    ?? 'L-' . $loan['id']
                                                ) ?>

                                                <div
                                                    class="text-muted fw-normal"
                                                    style="font-size: 0.7rem;"
                                                >
                                                    <?= date(
                                                        'M d, Y',
                                                        strtotime(
                                                            $loan['loan_date']
                                                            ?? $loan['created_at']
                                                        )
                                                    ) ?>
                                                </div>

                                            </td>

                                            <!-- Principal -->

                                            <td>
                                                ₱<?= number_format(
                                                    $loan['principal_amount'] ?? 0,
                                                    2
                                                ) ?>
                                            </td>

                                            <!-- Total Payable -->

                                            <td class="fw-semibold">

                                                ₱<?= number_format(
                                                    $loanPayable,
                                                    2
                                                ) ?>

                                            </td>

                                            <!-- Paid -->

                                            <td class="text-success fw-bold">

                                                ₱<?= number_format(
                                                    $loanPaid,
                                                    2
                                                ) ?>

                                            </td>

                                            <!-- Balance -->

                                            <td
                                                class="<?= $loanBalance > 0
                                                    ? 'text-danger'
                                                    : 'text-success' ?> fw-bold"
                                            >

                                                ₱<?= number_format(
                                                    $loanBalance,
                                                    2
                                                ) ?>

                                            </td>

                                            <!-- Status -->

                                            <td>

                                                <span
                                                    class="badge <?= $badgeBg ?> text-capitalize"
                                                    style="font-size: 0.7rem;"
                                                >
                                                    <?= htmlspecialchars(
                                                        $loan['status'] ?? 'Active'
                                                    ) ?>
                                                </span>

                                            </td>

                                            <!-- Action -->

                                            <td class="text-end pe-3">

                                                <a
                                                    href="index.php?page=loan_details&id=<?= (int)$loan['id'] ?>"
                                                    class="btn btn-sm btn-light border fw-semibold px-2 py-1"
                                                    style="font-size: 0.75rem;"
                                                >
                                                    View
                                                    <i class="bi bi-chevron-right ms-1"></i>
                                                </a>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </div>

    </main>

</div>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>

</body>
</html>