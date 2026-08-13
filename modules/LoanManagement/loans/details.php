<?php

$pdo = Database::getConnection();

$businessId = $_SESSION['business_id'] ?? null;

if (!$businessId) {
    header('Location: index.php?page=select_business');
    exit;
}

$loanId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($loanId <= 0) {
    header('Location: index.php?page=loans');
    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch Loan Details
|--------------------------------------------------------------------------
| We verify both loan ID and business ID so one business cannot access
| another business's loan.
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT 
        l.*,

        CONCAT(b.first_name, ' ', b.last_name) AS borrower_name,
        b.first_name,
        b.last_name,
        b.contact_no AS borrower_contact,

        a.account_name,
        a.balance AS account_balance,

        c.id AS collateral_id,
        c.item_name AS collateral_item,
        c.description AS collateral_description,
        c.estimated_value AS collateral_value,
        c.image_path AS collateral_image

    FROM loans l

    JOIN loan_borrowers b 
        ON l.borrower_id = b.id

    JOIN loan_accounts a 
        ON l.account_id = a.id

    LEFT JOIN loan_collaterals c 
        ON l.id = c.loan_id

    WHERE l.id = ?
      AND l.business_id = ?

    LIMIT 1
");

$stmt->execute([$loanId, $businessId]);

$loan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$loan) {
    header('Location: index.php?page=loans');
    exit;
}


/*
|--------------------------------------------------------------------------
| Calculate Financial Values
|--------------------------------------------------------------------------
*/

$principalAmount = (float)($loan['principal_amount'] ?? 0);
$interestRate   = (float)($loan['interest_rate'] ?? 0);
$totalPayable   = (float)($loan['total_payable'] ?? 0);

$interestAmount = $totalPayable - $principalAmount;


/*
|--------------------------------------------------------------------------
| Normalize Collateral Image Path
|--------------------------------------------------------------------------
*/

$imagePath = '';

if (!empty($loan['collateral_image'])) {

    if (strpos($loan['collateral_image'], 'uploads/') === 0) {

        $imagePath = $loan['collateral_image'];

    } else {

        $imagePath = 'uploads/collaterals/' . basename($loan['collateral_image']);

    }
}


/*
|--------------------------------------------------------------------------
| Status Badge
|--------------------------------------------------------------------------
*/

$status = strtolower($loan['status'] ?? '');

$statusClass = 'secondary';

switch ($status) {

    case 'active':
        $statusClass = 'success';
        break;

    case 'paid':
        $statusClass = 'primary';
        break;

    case 'overdue':
        $statusClass = 'danger';
        break;

    case 'cancelled':
        $statusClass = 'dark';
        break;

    case 'pending':
        $statusClass = 'warning';
        break;

    default:
        $statusClass = 'secondary';
        break;
}


$activePage = 'loans';

$pageTitle = "Loan Details - Loan Management";

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

        (function() {

            const savedTheme =
                localStorage.getItem('bs-theme') || 'light';

            document.documentElement.setAttribute(
                'data-bs-theme',
                savedTheme
            );

        })();

    </script>

    <style>

        .detail-card {
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 .125rem .5rem rgba(0,0,0,.06);
        }

        .detail-label {
            font-size: .75rem;
            color: var(--bs-secondary-color);
            margin-bottom: .2rem;
        }

        .detail-value {
            font-weight: 600;
            color: var(--bs-body-color);
        }

        .loan-header {
            background: var(--bs-body-bg);
            border-radius: 1rem;
        }

        .collateral-image {
            width: 100%;
            max-height: 350px;
            object-fit: contain;
            border-radius: .75rem;
            background: var(--bs-tertiary-bg);
        }

        .financial-number {
            font-size: 1.4rem;
            font-weight: 700;
        }

    </style>

</head>


<body class="bg-body-tertiary" style="min-height:100vh;">


<div
    class="d-flex flex-column flex-lg-row"
    style="min-height:100vh;"
>


    <!-- Sidebar -->

    <?php include __DIR__ . '/../../../resources/partials/loansidebar.php'; ?>


    <!-- Main Content -->

    <div class="p-3 p-md-4 flex-grow-1 bg-body-tertiary">


        <!-- Header -->

        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4"
        >

            <div>

                <div class="mb-2">

                    <a
                        href="index.php?page=loans"
                        class="text-decoration-none text-muted small"
                    >

                        <i class="bi bi-arrow-left me-1"></i>

                        Back to Loans

                    </a>

                </div>

                <h2 class="fw-bold text-body mb-1 fs-3">

                    Loan Details

                </h2>

                <p class="text-muted small mb-0">

                    Complete information for this loan and its collateral.

                </p>

            </div>


            <div class="d-flex gap-2">

                <a
                    href="index.php?page=loans"
                    class="btn btn-light border fw-semibold"
                >

                    <i class="bi bi-arrow-left me-1"></i>

                    Back

                </a>

                <button
                    type="button"
                    onclick="window.print()"
                    class="btn btn-primary fw-semibold"
                >

                    <i class="bi bi-printer me-1"></i>

                    Print

                </button>

            </div>

        </div>



        <!-- Loan Header -->

        <div class="card detail-card loan-header mb-4">

            <div class="card-body p-4">

                <div
                    class="d-flex flex-column flex-md-row justify-content-between gap-3"
                >

                    <div>

                        <div class="text-muted small mb-1">

                            Loan Reference

                        </div>

                        <h3 class="fw-bold mb-2">

                            <?= htmlspecialchars(
                                $loan['reference_number']
                                ?: 'LOAN #' . $loan['id']
                            ) ?>

                        </h3>

                        <div class="text-muted small">

                            Loan ID:
                            #<?= htmlspecialchars($loan['id']) ?>

                        </div>

                    </div>


                    <div class="text-md-end">

                        <span
                            class="badge bg-<?= $statusClass ?> fs-6 px-3 py-2"
                        >

                            <?= htmlspecialchars(
                                ucfirst($loan['status'] ?? 'Unknown')
                            ) ?>

                        </span>

                    </div>

                </div>

            </div>

        </div>



        <!-- Financial Summary -->

        <div class="row g-3 mb-4">


            <div class="col-md-6 col-xl-3">

                <div class="card detail-card h-100">

                    <div class="card-body">

                        <div class="detail-label">

                            Principal Amount

                        </div>

                        <div class="financial-number text-body">

                            ₱<?= number_format(
                                $principalAmount,
                                2
                            ) ?>

                        </div>

                    </div>

                </div>

            </div>


            <div class="col-md-6 col-xl-3">

                <div class="card detail-card h-100">

                    <div class="card-body">

                        <div class="detail-label">

                            Interest

                        </div>

                        <div class="financial-number text-warning">

                            ₱<?= number_format(
                                $interestAmount,
                                2
                            ) ?>

                        </div>

                        <div class="small text-muted">

                            <?= number_format(
                                $interestRate,
                                2
                            ) ?>%

                        </div>

                    </div>

                </div>

            </div>


            <div class="col-md-6 col-xl-3">

                <div class="card detail-card h-100">

                    <div class="card-body">

                        <div class="detail-label">

                            Total Payable

                        </div>

                        <div class="financial-number text-primary">

                            ₱<?= number_format(
                                $totalPayable,
                                2
                            ) ?>

                        </div>

                    </div>

                </div>

            </div>


            <div class="col-md-6 col-xl-3">

                <div class="card detail-card h-100">

                    <div class="card-body">

                        <div class="detail-label">

                            Term

                        </div>

                        <div class="financial-number text-body">

                            <?= htmlspecialchars(
                                $loan['term_days'] ?? 0
                            ) ?>

                            <span class="fs-6">

                                <?= htmlspecialchars(
                                    ucfirst(
                                        $loan['term_unit'] ?? 'days'
                                    )
                                ) ?>

                            </span>

                        </div>

                    </div>

                </div>

            </div>

        </div>



        <div class="row g-4">


            <!-- LEFT COLUMN -->

            <div class="col-lg-8">


                <!-- Borrower Information -->

                <div class="card detail-card mb-4">

                    <div class="card-header bg-transparent border-0 px-4 pt-4">

                        <h5 class="fw-bold mb-0">

                            <i
                                class="bi bi-person-fill text-primary me-2"
                            ></i>

                            Borrower Information

                        </h5>

                    </div>


                    <div class="card-body px-4 pb-4">

                        <div class="row g-4">


                            <div class="col-md-6">

                                <div class="detail-label">

                                    Borrower Name

                                </div>

                                <div class="detail-value">

                                    <?= htmlspecialchars(
                                        $loan['borrower_name']
                                    ) ?>

                                </div>

                            </div>


                            <div class="col-md-6">

                                <div class="detail-label">

                                    Contact Number

                                </div>

                                <div class="detail-value">

                                    <?php if (!empty($loan['borrower_contact'])): ?>

                                        <i
                                            class="bi bi-telephone me-1 text-primary"
                                        ></i>

                                        <?= htmlspecialchars(
                                            $loan['borrower_contact']
                                        ) ?>

                                    <?php else: ?>

                                        <span class="text-muted">

                                            N/A

                                        </span>

                                    <?php endif; ?>

                                </div>

                            </div>


                        </div>

                    </div>

                </div>



                <!-- Loan Information -->

                <div class="card detail-card mb-4">

                    <div class="card-header bg-transparent border-0 px-4 pt-4">

                        <h5 class="fw-bold mb-0">

                            <i
                                class="bi bi-file-earmark-text-fill text-primary me-2"
                            ></i>

                            Loan Information

                        </h5>

                    </div>


                    <div class="card-body px-4 pb-4">

                        <div class="row g-4">


                            <div class="col-md-6">

                                <div class="detail-label">

                                    Loan Date

                                </div>

                                <div class="detail-value">

                                    <i
                                        class="bi bi-calendar-event me-1 text-primary"
                                    ></i>

                                    <?= !empty($loan['loan_date'])
                                        ? date(
                                            'M d, Y',
                                            strtotime($loan['loan_date'])
                                        )
                                        : 'N/A'
                                    ?>

                                </div>

                            </div>


                            <div class="col-md-6">

                                <div class="detail-label">

                                    Due Date

                                </div>

                                <div class="detail-value">

                                    <i
                                        class="bi bi-calendar-check me-1 text-danger"
                                    ></i>

                                    <?= !empty($loan['due_date'])
                                        ? date(
                                            'M d, Y',
                                            strtotime($loan['due_date'])
                                        )
                                        : 'N/A'
                                    ?>

                                </div>

                            </div>


                            <div class="col-md-6">

                                <div class="detail-label">

                                    Interest Rate

                                </div>

                                <div class="detail-value">

                                    <?= number_format(
                                        $interestRate,
                                        2
                                    ) ?>%

                                </div>

                            </div>


                            <div class="col-md-6">

                                <div class="detail-label">

                                    Status

                                </div>

                                <div>

                                    <span
                                        class="badge bg-<?= $statusClass ?>"
                                    >

                                        <?= htmlspecialchars(
                                            ucfirst(
                                                $loan['status']
                                            )
                                        ) ?>

                                    </span>

                                </div>

                            </div>


                        </div>

                    </div>

                </div>



                <!-- Collateral -->

                <div class="card detail-card mb-4">

                    <div class="card-header bg-transparent border-0 px-4 pt-4">

                        <h5 class="fw-bold mb-0">

                            <i
                                class="bi bi-shield-fill-check text-success me-2"
                            ></i>

                            Collateral

                        </h5>

                    </div>


                    <div class="card-body px-4 pb-4">


                        <?php if (!empty($loan['collateral_item'])): ?>


                            <div class="row g-4">


                                <!-- Image -->

                                <div class="col-md-5">

                                    <?php if (!empty($imagePath)): ?>

                                        <a
                                            href="<?= htmlspecialchars($imagePath) ?>"
                                            target="_blank"
                                        >

                                            <img
                                                src="<?= htmlspecialchars($imagePath) ?>"
                                                alt="Collateral"
                                                class="collateral-image border"
                                            >

                                        </a>

                                        <div class="text-center mt-2">

                                            <a
                                                href="<?= htmlspecialchars($imagePath) ?>"
                                                target="_blank"
                                                class="small text-decoration-none"
                                            >

                                                <i
                                                    class="bi bi-box-arrow-up-right me-1"
                                                ></i>

                                                Open Full Image

                                            </a>

                                        </div>

                                    <?php else: ?>

                                        <div
                                            class="bg-body-tertiary border rounded-3 d-flex flex-column align-items-center justify-content-center text-muted"
                                            style="height:250px;"
                                        >

                                            <i
                                                class="bi bi-image fs-1 mb-2"
                                            ></i>

                                            <span class="small">

                                                No collateral photo

                                            </span>

                                        </div>

                                    <?php endif; ?>

                                </div>


                                <!-- Information -->

                                <div class="col-md-7">


                                    <div class="mb-4">

                                        <div class="detail-label">

                                            Item Name

                                        </div>

                                        <div class="fs-5 fw-bold">

                                            <?= htmlspecialchars(
                                                $loan['collateral_item']
                                            ) ?>

                                        </div>

                                    </div>


                                    <div class="mb-4">

                                        <div class="detail-label">

                                            Estimated Value

                                        </div>

                                        <div class="fs-4 fw-bold text-success">

                                            ₱<?= number_format(
                                                (float)$loan['collateral_value'],
                                                2
                                            ) ?>

                                        </div>

                                    </div>


                                    <div>

                                        <div class="detail-label">

                                            Description / Specifications

                                        </div>

                                        <div
                                            class="bg-body-tertiary border rounded-3 p-3 small"
                                        >

                                            <?php if (!empty($loan['collateral_description'])): ?>

                                                <?= nl2br(
                                                    htmlspecialchars(
                                                        $loan['collateral_description']
                                                    )
                                                ) ?>

                                            <?php else: ?>

                                                <span class="text-muted">

                                                    No description provided.

                                                </span>

                                            <?php endif; ?>

                                        </div>

                                    </div>


                                </div>


                            </div>


                        <?php else: ?>


                            <div class="text-center py-5 text-muted">

                                <i
                                    class="bi bi-shield-x display-5 opacity-50"
                                ></i>

                                <p class="fw-semibold mt-3 mb-1">

                                    No Collateral

                                </p>

                                <p class="small mb-0">

                                    This loan does not have a collateral item.

                                </p>

                            </div>


                        <?php endif; ?>


                    </div>

                </div>


            </div>



            <!-- RIGHT COLUMN -->

            <div class="col-lg-4">


                <!-- Funding Account -->

                <div class="card detail-card mb-4">

                    <div class="card-header bg-transparent border-0 px-4 pt-4">

                        <h5 class="fw-bold mb-0">

                            <i
                                class="bi bi-wallet2 text-primary me-2"
                            ></i>

                            Funding Account

                        </h5>

                    </div>


                    <div class="card-body px-4 pb-4">


                        <div class="mb-3">

                            <div class="detail-label">

                                Account

                            </div>

                            <div class="detail-value fs-5">

                                <?= htmlspecialchars(
                                    $loan['account_name']
                                ) ?>

                            </div>

                        </div>


                        <div>

                            <div class="detail-label">

                                Current Account Balance

                            </div>

                            <div class="fw-bold text-primary fs-4">

                                ₱<?= number_format(
                                    (float)$loan['account_balance'],
                                    2
                                ) ?>

                            </div>

                        </div>


                    </div>

                </div>



                <!-- Loan Summary -->

                <div class="card detail-card mb-4">

                    <div class="card-header bg-transparent border-0 px-4 pt-4">

                        <h5 class="fw-bold mb-0">

                            <i
                                class="bi bi-calculator text-primary me-2"
                            ></i>

                            Loan Summary

                        </h5>

                    </div>


                    <div class="card-body px-4 pb-4">


                        <div
                            class="d-flex justify-content-between py-2 border-bottom"
                        >

                            <span class="text-muted">

                                Principal

                            </span>

                            <span class="fw-semibold">

                                ₱<?= number_format(
                                    $principalAmount,
                                    2
                                ) ?>

                            </span>

                        </div>


                        <div
                            class="d-flex justify-content-between py-2 border-bottom"
                        >

                            <span class="text-muted">

                                Interest

                            </span>

                            <span class="fw-semibold">

                                ₱<?= number_format(
                                    $interestAmount,
                                    2
                                ) ?>

                            </span>

                        </div>


                        <div
                            class="d-flex justify-content-between py-3"
                        >

                            <span class="fw-bold">

                                Total Payable

                            </span>

                            <span class="fw-bold text-primary">

                                ₱<?= number_format(
                                    $totalPayable,
                                    2
                                ) ?>

                            </span>

                        </div>


                    </div>

                </div>



                <!-- Dates -->

                <div class="card detail-card mb-4">

                    <div class="card-header bg-transparent border-0 px-4 pt-4">

                        <h5 class="fw-bold mb-0">

                            <i
                                class="bi bi-calendar3 text-primary me-2"
                            ></i>

                            Important Dates

                        </h5>

                    </div>


                    <div class="card-body px-4 pb-4">


                        <div class="d-flex gap-3 mb-4">

                            <div class="text-primary">

                                <i class="bi bi-calendar-event fs-4"></i>

                            </div>

                            <div>

                                <div class="detail-label">

                                    Loan Date

                                </div>

                                <div class="fw-semibold">

                                    <?= !empty($loan['loan_date'])
                                        ? date(
                                            'M d, Y',
                                            strtotime($loan['loan_date'])
                                        )
                                        : 'N/A'
                                    ?>

                                </div>

                            </div>

                        </div>


                        <div class="d-flex gap-3">

                            <div class="text-danger">

                                <i class="bi bi-calendar-check fs-4"></i>

                            </div>

                            <div>

                                <div class="detail-label">

                                    Due Date

                                </div>

                                <div class="fw-semibold">

                                    <?= !empty($loan['due_date'])
                                        ? date(
                                            'M d, Y',
                                            strtotime($loan['due_date'])
                                        )
                                        : 'N/A'
                                    ?>

                                </div>

                            </div>

                        </div>


                    </div>

                </div>


            </div>


        </div>


    </div>

</div>



<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


<style>

    @media print {

        body {
            background: white !important;
        }

        .sidebar,
        .btn,
        a {
            display: none !important;
        }

        .card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
        }

    }

</style>


</body>

</html>