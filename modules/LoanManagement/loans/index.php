<?php

$pdo = Database::getConnection();

$businessId = $_SESSION['business_id'] ?? null;

if (!$businessId) {
    header('Location: index.php?page=select_business');
    exit;
}

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| RECORD PAYMENT
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {

    $paymentLoanId = intval($_POST['loan_id'] ?? 0);
    $paymentAmount = floatval($_POST['payment_amount'] ?? 0);
    $paymentDate   = $_POST['payment_date'] ?? date('Y-m-d');
    $paymentNotes  = trim($_POST['payment_notes'] ?? '');

    if ($paymentLoanId > 0 && $paymentAmount > 0) {

        $pdo->beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | Get loan
            |--------------------------------------------------------------------------
            */

            $loanStmt = $pdo->prepare("
                SELECT *
                FROM loans
                WHERE id = ?
                AND business_id = ?
            ");

            $loanStmt->execute([
                $paymentLoanId,
                $businessId
            ]);

            $loan = $loanStmt->fetch(PDO::FETCH_ASSOC);

            if (!$loan) {
                throw new Exception("Loan record not found.");
            }

            /*
            |--------------------------------------------------------------------------
            | Calculate current paid amount
            |--------------------------------------------------------------------------
            */

            $sumStmt = $pdo->prepare("
                SELECT COALESCE(SUM(payment_amount), 0) AS total_paid
                FROM loan_payments
                WHERE loan_id = ?
                AND business_id = ?
            ");

            $sumStmt->execute([
                $paymentLoanId,
                $businessId
            ]);

            $totalPaidBefore = floatval(
                $sumStmt->fetchColumn()
            );

            $remainingBefore =
                floatval($loan['total_payable']) -
                $totalPaidBefore;

            /*
            |--------------------------------------------------------------------------
            | Prevent overpayment
            |--------------------------------------------------------------------------
            */

            if ($paymentAmount > $remainingBefore) {
                throw new Exception(
                    "Payment cannot be greater than the remaining balance of ₱" .
                    number_format($remainingBefore, 2)
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Insert payment
            |--------------------------------------------------------------------------
            */

            $payStmt = $pdo->prepare("
                INSERT INTO loan_payments
                (
                    business_id,
                    loan_id,
                    payment_amount,
                    payment_date,
                    notes
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $payStmt->execute([
                $businessId,
                $paymentLoanId,
                $paymentAmount,
                $paymentDate,
                $paymentNotes
            ]);

            /*
            |--------------------------------------------------------------------------
            | Calculate new total paid
            |--------------------------------------------------------------------------
            */

            $totalPaidAfter =
                $totalPaidBefore +
                $paymentAmount;

            /*
            |--------------------------------------------------------------------------
            | Determine loan status
            |--------------------------------------------------------------------------
            */

            if (
                $totalPaidAfter >=
                floatval($loan['total_payable'])
            ) {

                $newStatus = 'completed';

            } else {

                $dueDateTimestamp =
                    strtotime($loan['due_date']);

                $paymentDateTimestamp =
                    strtotime($paymentDate);

                if (
                    $dueDateTimestamp &&
                    $paymentDateTimestamp > $dueDateTimestamp
                ) {
                    $newStatus = 'overdue';
                } else {
                    $newStatus = 'active';
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Update loan status
            |--------------------------------------------------------------------------
            */

            $updateLoanStmt = $pdo->prepare("
                UPDATE loans
                SET status = ?
                WHERE id = ?
                AND business_id = ?
            ");

            $updateLoanStmt->execute([
                $newStatus,
                $paymentLoanId,
                $businessId
            ]);

            /*
            |--------------------------------------------------------------------------
            | Credit payment back to funding account
            |--------------------------------------------------------------------------
            */

            $updateAcc = $pdo->prepare("
                UPDATE loan_accounts
                SET balance = balance + ?
                WHERE id = ?
                AND business_id = ?
            ");

            $updateAcc->execute([
                $paymentAmount,
                $loan['account_id'],
                $businessId
            ]);

            /*
            |--------------------------------------------------------------------------
            | Record account transaction
            |--------------------------------------------------------------------------
            */

            $txStmt = $pdo->prepare("
                INSERT INTO loan_account_transactions
                (
                    business_id,
                    account_id,
                    type,
                    amount,
                    description
                )
                VALUES (?, ?, 'CREDIT', ?, ?)
            ");

            $txStmt->execute([
                $businessId,
                $loan['account_id'],
                $paymentAmount,
                "Payment received for Loan #{$paymentLoanId}"
            ]);

            $pdo->commit();

            header(
                'Location: index.php?page=loans&success_payment=1'
            );

            exit;

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error =
                "Failed to record payment: " .
                $e->getMessage();
        }

    } else {

        $error =
            "Please provide a valid payment amount.";
    }
}


/*
|--------------------------------------------------------------------------
| ISSUE NEW LOAN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_loan'])) {

    $borrowerId =
        intval($_POST['borrower_id'] ?? 0);

    $accountId =
        intval($_POST['account_id'] ?? 0);

    $referenceNumber =
        !empty($_POST['reference_number'])
            ? trim($_POST['reference_number'])
            : null;

    $principalAmount =
        floatval($_POST['principal_amount'] ?? 0);

    $interestRate =
        floatval($_POST['interest_rate'] ?? 0);

    $termValue =
        intval($_POST['term_value'] ?? 30);

    $termUnit =
        $_POST['term_unit'] ?? 'days';

    $loanDate =
        $_POST['loan_date'] ?? date('Y-m-d');

    /*
    |--------------------------------------------------------------------------
    | Collateral
    |--------------------------------------------------------------------------
    */

    $collateralItem =
        trim($_POST['collateral_item'] ?? '');

    $collateralDesc =
        trim($_POST['collateral_description'] ?? '');

    $collateralValue =
        floatval($_POST['collateral_value'] ?? 0);


    if (
        $borrowerId > 0 &&
        $accountId > 0 &&
        $principalAmount > 0
    ) {

        /*
        |--------------------------------------------------------------------------
        | Calculate Due Date
        |--------------------------------------------------------------------------
        */

        try {

            $dateObj =
                new DateTime($loanDate);

            if ($termUnit === 'months') {

                $dateObj->modify(
                    "+{$termValue} months"
                );

            } else {

                $dateObj->modify(
                    "+{$termValue} days"
                );
            }

            $dueDate =
                $dateObj->format('Y-m-d');

        } catch (Exception $e) {

            $error =
                "Invalid loan date.";
            $dueDate = null;
        }


        if (!$error) {

            /*
            |--------------------------------------------------------------------------
            | Check funding account
            |--------------------------------------------------------------------------
            */

            $accStmt = $pdo->prepare("
                SELECT *
                FROM loan_accounts
                WHERE id = ?
                AND business_id = ?
            ");

            $accStmt->execute([
                $accountId,
                $businessId
            ]);

            $account =
                $accStmt->fetch(PDO::FETCH_ASSOC);


            if (!$account) {

                $error =
                    "Selected funding account was not found.";

            } elseif (
                floatval($account['balance']) <
                $principalAmount
            ) {

                $error =
                    "Insufficient funds in the selected account/wallet.";

            } else {

                $pdo->beginTransaction();

                try {

                    /*
                    |--------------------------------------------------------------------------
                    | Calculate total payable
                    |--------------------------------------------------------------------------
                    */

                    $interestAmount =
                        $principalAmount *
                        ($interestRate / 100);

                    $totalPayable =
                        $principalAmount +
                        $interestAmount;


                    /*
                    |--------------------------------------------------------------------------
                    | Insert Loan
                    |--------------------------------------------------------------------------
                    */

                    $loanStmt = $pdo->prepare("
                        INSERT INTO loans
                        (
                            business_id,
                            borrower_id,
                            account_id,
                            reference_number,
                            principal_amount,
                            interest_rate,
                            term_days,
                            term_unit,
                            total_payable,
                            loan_date,
                            due_date,
                            status
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            'active'
                        )
                    ");

                    $loanStmt->execute([
                        $businessId,
                        $borrowerId,
                        $accountId,
                        $referenceNumber,
                        $principalAmount,
                        $interestRate,
                        $termValue,
                        $termUnit,
                        $totalPayable,
                        $loanDate,
                        $dueDate
                    ]);

                    $loanId =
                        $pdo->lastInsertId();


                    /*
                    |--------------------------------------------------------------------------
                    | Handle Collateral Image
                    |--------------------------------------------------------------------------
                    */

                    $imagePath = null;


                    if (
                        !empty($collateralItem) &&
                        isset($_FILES['collateral_image'])
                    ) {

                        $uploadError =
                            $_FILES['collateral_image']['error'];


                        if (
                            $uploadError ===
                            UPLOAD_ERR_OK
                        ) {

                            $fileTmpPath =
                                $_FILES['collateral_image']['tmp_name'];

                            $fileName =
                                $_FILES['collateral_image']['name'];

                            $fileExtension =
                                strtolower(
                                    pathinfo(
                                        $fileName,
                                        PATHINFO_EXTENSION
                                    )
                                );


                            $allowedExtensions = [
                                'jpg',
                                'jpeg',
                                'png',
                                'webp'
                            ];


                            if (
                                !in_array(
                                    $fileExtension,
                                    $allowedExtensions,
                                    true
                                )
                            ) {

                                throw new Exception(
                                    'Invalid collateral image format. Allowed: JPG, JPEG, PNG, WEBP.'
                                );
                            }


                            $newFileName =
                                'col_' .
                                $loanId .
                                '_' .
                                time() .
                                '.' .
                                $fileExtension;


                            $uploadFileDir =
                                __DIR__ .
                                '/../../../public/uploads/collaterals/';


                            if (
                                !is_dir(
                                    $uploadFileDir
                                )
                            ) {

                                mkdir(
                                    $uploadFileDir,
                                    0755,
                                    true
                                );
                            }


                            $destPath =
                                $uploadFileDir .
                                $newFileName;


                            if (
                                !move_uploaded_file(
                                    $fileTmpPath,
                                    $destPath
                                )
                            ) {

                                throw new Exception(
                                    'Failed to move uploaded collateral image.'
                                );
                            }


                            $imagePath =
                                'uploads/collaterals/' .
                                $newFileName;

                        } elseif (
                            $uploadError !==
                            UPLOAD_ERR_NO_FILE
                        ) {

                            throw new Exception(
                                'Collateral image upload failed. Error code: ' .
                                $uploadError
                            );
                        }
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Insert Collateral
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !empty($collateralItem)
                    ) {

                        $colStmt = $pdo->prepare("
                            INSERT INTO loan_collaterals
                            (
                                business_id,
                                loan_id,
                                item_name,
                                description,
                                estimated_value,
                                image_path
                            )
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");

                        $colStmt->execute([
                            $businessId,
                            $loanId,
                            $collateralItem,
                            $collateralDesc,
                            $collateralValue,
                            $imagePath
                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Deduct Funding Account
                    |--------------------------------------------------------------------------
                    */

                    $updateAcc = $pdo->prepare("
                        UPDATE loan_accounts
                        SET balance = balance - ?
                        WHERE id = ?
                        AND business_id = ?
                    ");

                    $updateAcc->execute([
                        $principalAmount,
                        $accountId,
                        $businessId
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | Record DEBIT transaction
                    |--------------------------------------------------------------------------
                    */

                    $txStmt = $pdo->prepare("
                        INSERT INTO loan_account_transactions
                        (
                            business_id,
                            account_id,
                            type,
                            amount,
                            description
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            'DEBIT',
                            ?,
                            ?
                        )
                    ");

                    $txStmt->execute([
                        $businessId,
                        $accountId,
                        $principalAmount,
                        "Loan #{$loanId} disbursement"
                    ]);


                    $pdo->commit();


                    header(
                        'Location: index.php?page=loans&success=1'
                    );

                    exit;

                } catch (Exception $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $error =
                        "Failed to issue loan: " .
                        $e->getMessage();
                }
            }
        }

    } else {

        $error =
            "Please fill in all required fields correctly.";
    }
}


/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGES
|--------------------------------------------------------------------------
*/

if (isset($_GET['success'])) {

    $success =
        "Loan issued successfully!";
}


if (isset($_GET['success_payment'])) {

    $success =
        "Payment recorded successfully!";
}


$activePage =
    'loans';

$pageTitle =
    "Loans Management - Loan Management";


/*
|--------------------------------------------------------------------------
| SEARCH / FILTER / SORT
|--------------------------------------------------------------------------
*/

$search =
    trim($_GET['search'] ?? '');

$statusFilter =
    strtolower(
        trim($_GET['status'] ?? 'all')
    );

$sort =
    $_GET['sort'] ?? 'newest';


/*
|--------------------------------------------------------------------------
| Fetch Loans
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        l.*,

        CONCAT(
            b.first_name,
            ' ',
            b.last_name
        ) AS borrower_name,

        a.account_name,

        c.item_name,
        c.description AS collateral_desc,
        c.estimated_value,
        c.image_path,

        COALESCE(
            SUM(p.payment_amount),
            0
        ) AS total_paid,

        (
            l.total_payable -
            COALESCE(
                SUM(p.payment_amount),
                0
            )
        ) AS remaining_balance

    FROM loans l

    JOIN loan_borrowers b
        ON l.borrower_id = b.id

    JOIN loan_accounts a
        ON l.account_id = a.id

    LEFT JOIN loan_collaterals c
        ON l.id = c.loan_id

    LEFT JOIN loan_payments p
        ON l.id = p.loan_id

    WHERE l.business_id = ?

    GROUP BY l.id

    ORDER BY l.created_at DESC
");

$stmt->execute([
    $businessId
]);

$loans =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Automatically determine overdue status
|--------------------------------------------------------------------------
*/

foreach ($loans as &$loan) {

    $remaining =
        floatval(
            $loan['remaining_balance']
        );

    $dueDate =
        strtotime(
            $loan['due_date']
        );

    $today =
        strtotime(
            date('Y-m-d')
        );


    if ($remaining <= 0) {

        $loan['display_status'] =
            'completed';

    } elseif (
        $dueDate &&
        $dueDate < $today
    ) {

        $loan['display_status'] =
            'overdue';

    } else {

        $loan['display_status'] =
            'active';
    }
}

unset($loan);


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $loans = array_filter(
        $loans,
        function ($loan) use ($search) {

            $searchLower =
                strtolower($search);

            $borrower =
                strtolower(
                    $loan['borrower_name'] ?? ''
                );

            $reference =
                strtolower(
                    $loan['reference_number'] ?? ''
                );

            $account =
                strtolower(
                    $loan['account_name'] ?? ''
                );

            $collateral =
                strtolower(
                    $loan['item_name'] ?? ''
                );


            return
                strpos(
                    $borrower,
                    $searchLower
                ) !== false
                ||
                strpos(
                    $reference,
                    $searchLower
                ) !== false
                ||
                strpos(
                    $account,
                    $searchLower
                ) !== false
                ||
                strpos(
                    $collateral,
                    $searchLower
                ) !== false;
        }
    );
}


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if (
    in_array(
        $statusFilter,
        [
            'active',
            'overdue',
            'completed'
        ],
        true
    )
) {

    $loans = array_filter(
        $loans,
        function ($loan) use ($statusFilter) {

            return
                $loan['display_status'] ===
                $statusFilter;
        }
    );
}


/*
|--------------------------------------------------------------------------
| Sorting
|--------------------------------------------------------------------------
*/

usort(
    $loans,
    function ($a, $b) use ($sort) {

        switch ($sort) {

            case 'oldest':

                return
                    strtotime(
                        $a['created_at']
                    )
                    <=>
                    strtotime(
                        $b['created_at']
                    );


            case 'amount_asc':

                return
                    floatval(
                        $a['principal_amount']
                    )
                    <=>
                    floatval(
                        $b['principal_amount']
                    );


            case 'amount_desc':

                return
                    floatval(
                        $b['principal_amount']
                    )
                    <=>
                    floatval(
                        $a['principal_amount']
                    );


            case 'due_asc':

                return
                    strtotime(
                        $a['due_date']
                    )
                    <=>
                    strtotime(
                        $b['due_date']
                    );


            case 'due_desc':

                return
                    strtotime(
                        $b['due_date']
                    )
                    <=>
                    strtotime(
                        $a['due_date']
                    );


            case 'newest':

            default:

                return
                    strtotime(
                        $b['created_at']
                    )
                    <=>
                    strtotime(
                        $a['created_at']
                    );
        }
    }
);


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalLoans =
    count($loans);

$activeLoans = 0;
$overdueLoans = 0;
$completedLoans = 0;

$totalPrincipal = 0;
$totalRemaining = 0;
$totalPaid = 0;


foreach ($loans as $loan) {

    $totalPrincipal +=
        floatval(
            $loan['principal_amount']
        );

    $totalRemaining +=
        max(
            0,
            floatval(
                $loan['remaining_balance']
            )
        );

    $totalPaid +=
        floatval(
            $loan['total_paid']
        );


    if (
        $loan['display_status'] ===
        'active'
    ) {

        $activeLoans++;

    } elseif (
        $loan['display_status'] ===
        'overdue'
    ) {

        $overdueLoans++;

    } elseif (
        $loan['display_status'] ===
        'completed'
    ) {

        $completedLoans++;
    }
}


/*
|--------------------------------------------------------------------------
| Fetch Borrowers
|--------------------------------------------------------------------------
*/

$borrowersStmt = $pdo->prepare("
    SELECT
        id,
        first_name,
        last_name
    FROM loan_borrowers
    WHERE business_id = ?
    ORDER BY first_name ASC
");

$borrowersStmt->execute([
    $businessId
]);

$borrowers =
    $borrowersStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| Fetch Accounts
|--------------------------------------------------------------------------
*/

$accountsStmt = $pdo->prepare("
    SELECT
        id,
        account_name,
        balance
    FROM loan_accounts
    WHERE business_id = ?
    ORDER BY account_name ASC
");

$accountsStmt->execute([
    $businessId
]);

$accounts =
    $accountsStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    <?= htmlspecialchars($pageTitle) ?>
</title>


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

    const savedTheme =
        localStorage.getItem('bs-theme') || 'light';

    document.documentElement.setAttribute(
        'data-bs-theme',
        savedTheme
    );

})();

</script>


<style>

body {
    font-size: 0.9rem;
}

.loan-stat {
    border: 0;
    border-radius: 14px;
    transition: transform 0.15s ease;
}

.loan-stat:hover {
    transform: translateY(-2px);
}

.loan-table th {
    font-size: 0.7rem;
    letter-spacing: 0.04em;
    white-space: nowrap;
}

.loan-table td {
    vertical-align: middle;
}

.status-badge {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 5px 8px;
    border-radius: 6px;
}

.loan-card {
    border: 0;
    border-radius: 14px;
}

.money-box {
    border-radius: 10px;
}

.filter-card {
    border: 0;
    border-radius: 14px;
}

.table-action-btn {
    min-width: 32px;
}

@media (max-width: 767px) {

    body {
        font-size: 0.85rem;
    }

}

</style>

</head>


<body
    class="bg-body-tertiary"
    style="min-height:100vh;"
>


<div
    class="d-flex flex-column flex-lg-row"
    style="min-height:100vh;"
>


<?php

include __DIR__ .
    '/../../../resources/partials/loansidebar.php';

?>


<div
    class="p-3 p-md-4 flex-grow-1 bg-body-tertiary overflow-hidden"
>


<!-- =========================================================
     HEADER
========================================================= -->

<div
    class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4"
>

<div>

<h2
    class="fw-bold text-body mb-1"
>
    Loans
</h2>

<p
    class="text-muted small mb-0"
>

    Manage loans, payments and collateral for

    <span class="fw-bold text-primary">
        <?= htmlspecialchars(
            $_SESSION['business_name'] ?? ''
        ) ?>
    </span>

</p>

</div>


<button
    type="button"
    class="btn btn-primary fw-bold px-3 py-2 rounded-3 shadow-sm"
    data-bs-toggle="modal"
    data-bs-target="#issueLoanModal"
>

<i class="bi bi-plus-circle-fill me-1"></i>

Issue New Loan

</button>

</div>


<!-- =========================================================
     ALERTS
========================================================= -->

<?php if (!empty($success)): ?>

<div
    class="alert alert-success border-0 shadow-sm py-2 small"
>
    <i class="bi bi-check-circle-fill me-1"></i>

    <?= htmlspecialchars($success) ?>
</div>

<?php endif; ?>


<?php if (!empty($error)): ?>

<div
    class="alert alert-danger border-0 shadow-sm py-2 small"
>
    <i class="bi bi-exclamation-circle-fill me-1"></i>

    <?= htmlspecialchars($error) ?>
</div>

<?php endif; ?>


<!-- =========================================================
     SUMMARY
========================================================= -->

<div class="row g-3 mb-4">


<div class="col-6 col-xl-3">

<div
    class="card loan-stat shadow-sm bg-body h-100"
>

<div class="card-body p-3">

<div
    class="d-flex justify-content-between align-items-center"
>

<div>

<div class="text-muted small mb-1">
    Total Loans
</div>

<div class="fs-5 fw-bold text-body">
    <?= number_format($totalLoans) ?>
</div>

</div>

<div
    class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center"
    style="width:38px;height:38px;"
>

<i class="bi bi-file-earmark-text"></i>

</div>

</div>

</div>

</div>

</div>


<div class="col-6 col-xl-3">

<div
    class="card loan-stat shadow-sm bg-body h-100"
>

<div class="card-body p-3">

<div
    class="d-flex justify-content-between align-items-center"
>

<div>

<div class="text-muted small mb-1">
    Active
</div>

<div class="fs-5 fw-bold text-success">
    <?= number_format($activeLoans) ?>
</div>

</div>

<div
    class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center"
    style="width:38px;height:38px;"
>

<i class="bi bi-clock"></i>

</div>

</div>

</div>

</div>

</div>


<div class="col-6 col-xl-3">

<div
    class="card loan-stat shadow-sm bg-body h-100"
>

<div class="card-body p-3">

<div
    class="d-flex justify-content-between align-items-center"
>

<div>

<div class="text-muted small mb-1">
    Overdue
</div>

<div class="fs-5 fw-bold text-danger">
    <?= number_format($overdueLoans) ?>
</div>

</div>

<div
    class="rounded-circle bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center"
    style="width:38px;height:38px;"
>

<i class="bi bi-exclamation-triangle"></i>

</div>

</div>

</div>

</div>

</div>


<div class="col-6 col-xl-3">

<div
    class="card loan-stat shadow-sm bg-body h-100"
>

<div class="card-body p-3">

<div
    class="d-flex justify-content-between align-items-center"
>

<div>

<div class="text-muted small mb-1">
    Remaining
</div>

<div class="fs-5 fw-bold text-primary">
    ₱<?= number_format($totalRemaining, 2) ?>
</div>

</div>

<div
    class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center"
    style="width:38px;height:38px;"
>

<i class="bi bi-cash-stack"></i>

</div>

</div>

</div>

</div>

</div>

</div>


<!-- =========================================================
     SEARCH / FILTER
========================================================= -->

<div
    class="card filter-card shadow-sm bg-body mb-4"
>

<div class="card-body p-3">


<form
    method="GET"
    class="row g-2 align-items-end"
>

<input
    type="hidden"
    name="page"
    value="loans"
>


<div class="col-12 col-md-5">

<label
    class="form-label small fw-semibold text-muted mb-1"
>
    Search
</label>

<div class="input-group">

<span class="input-group-text bg-body border-end-0">
    <i class="bi bi-search text-muted"></i>
</span>

<input
    type="text"
    name="search"
    value="<?= htmlspecialchars($search) ?>"
    class="form-control border-start-0 shadow-none"
    placeholder="Borrower, reference, account..."
>

</div>

</div>


<div class="col-6 col-md-3">

<label
    class="form-label small fw-semibold text-muted mb-1"
>
    Status
</label>

<select
    name="status"
    class="form-select shadow-none"
>

<option
    value="all"
    <?= $statusFilter === 'all' ? 'selected' : '' ?>
>
    All Status
</option>

<option
    value="active"
    <?= $statusFilter === 'active' ? 'selected' : '' ?>
>
    Active
</option>

<option
    value="overdue"
    <?= $statusFilter === 'overdue' ? 'selected' : '' ?>
>
    Overdue
</option>

<option
    value="completed"
    <?= $statusFilter === 'completed' ? 'selected' : '' ?>
>
    Paid / Completed
</option>

</select>

</div>


<div class="col-6 col-md-3">

<label
    class="form-label small fw-semibold text-muted mb-1"
>
    Sort
</label>

<select
    name="sort"
    class="form-select shadow-none"
>

<option
    value="newest"
    <?= $sort === 'newest' ? 'selected' : '' ?>
>
    Newest First
</option>

<option
    value="oldest"
    <?= $sort === 'oldest' ? 'selected' : '' ?>
>
    Oldest First
</option>

<option
    value="amount_desc"
    <?= $sort === 'amount_desc' ? 'selected' : '' ?>
>
    Amount: High to Low
</option>

<option
    value="amount_asc"
    <?= $sort === 'amount_asc' ? 'selected' : '' ?>
>
    Amount: Low to High
</option>

<option
    value="due_asc"
    <?= $sort === 'due_asc' ? 'selected' : '' ?>
>
    Due Date: Earliest
</option>

<option
    value="due_desc"
    <?= $sort === 'due_desc' ? 'selected' : '' ?>
>
    Due Date: Latest
</option>

</select>

</div>


<div class="col-12 col-md-1">

<button
    type="submit"
    class="btn btn-primary w-100"
>

<i class="bi bi-funnel"></i>

<span class="d-md-none ms-1">
    Apply
</span>

</button>

</div>


</form>


<?php if (
    $search !== '' ||
    $statusFilter !== 'all'
): ?>

<div
    class="mt-2 small text-muted"
>

Showing
<strong>
    <?= count($loans) ?>
</strong>
loan(s)

<?php if ($search !== ''): ?>

for
<strong>
    "<?= htmlspecialchars($search) ?>"
</strong>

<?php endif; ?>

</div>

<?php endif; ?>


</div>

</div>


<!-- =========================================================
     MOBILE VIEW
========================================================= -->

<div class="d-block d-md-none">


<?php if (empty($loans)): ?>

<div
    class="card shadow-sm border-0 rounded-4 text-center py-5"
>

<div class="mb-2">

<i
    class="bi bi-file-earmark-text display-5 text-muted opacity-50"
></i>

</div>

<h6 class="fw-bold">
    No loans found
</h6>

<p class="small text-muted mb-3">
    No loans match your current search or filter.
</p>

<button
    type="button"
    class="btn btn-sm btn-primary fw-bold"
    data-bs-toggle="modal"
    data-bs-target="#issueLoanModal"
>

<i class="bi bi-plus-circle me-1"></i>

Issue Loan

</button>

</div>

<?php else: ?>


<div class="d-flex flex-column gap-3">


<?php foreach ($loans as $loan): ?>


<?php

$status =
    $loan['display_status'];

$isCompleted =
    $status === 'completed';

$isOverdue =
    $status === 'overdue';

$isActive =
    $status === 'active';


if ($isCompleted) {

    $statusClass =
        'success';

    $statusText =
        'Paid';

} elseif ($isOverdue) {

    $statusClass =
        'danger';

    $statusText =
        'Overdue';

} else {

    $statusClass =
        'primary';

    $statusText =
        'Active';
}

?>


<div
    class="card loan-card shadow-sm bg-body"
>


<div class="card-body p-3">


<!-- TOP -->

<div
    class="d-flex justify-content-between align-items-start gap-2 mb-3"
>

<div
    class="text-truncate"
>

<div
    class="small text-muted mb-1"
>

<?= htmlspecialchars(
    $loan['reference_number']
    ?: 'No Reference'
) ?>

</div>

<h6
    class="fw-bold mb-0 text-truncate"
>

<?= htmlspecialchars(
    $loan['borrower_name']
) ?>

</h6>

</div>


<span
    class="status-badge bg-<?= $statusClass ?> bg-opacity-10 text-<?= $statusClass ?> flex-shrink-0"
>

<?= $statusText ?>

</span>

</div>


<!-- MONEY -->

<div
    class="row g-2 mb-3"
>


<div class="col-6">

<div
    class="money-box bg-body-tertiary p-2"
>

<div
    class="text-muted"
    style="font-size:.68rem;"
>
    Principal
</div>

<div
    class="fw-bold"
>

₱<?= number_format(
    $loan['principal_amount'],
    2
) ?>

</div>

</div>

</div>


<div class="col-6">

<div
    class="money-box bg-body-tertiary p-2"
>

<div
    class="text-muted"
    style="font-size:.68rem;"
>
    Remaining
</div>

<div
    class="fw-bold text-<?= $isOverdue ? 'danger' : 'success' ?>"
>

₱<?= number_format(
    max(
        0,
        $loan['remaining_balance']
    ),
    2
) ?>

</div>

</div>

</div>


</div>


<!-- PAID -->

<div
    class="d-flex justify-content-between small mb-3"
>

<span class="text-muted">
    Paid:
    <strong class="text-body">
        ₱<?= number_format(
            $loan['total_paid'],
            2
        ) ?>
    </strong>
</span>

<span class="text-muted">
    Total:
    <strong class="text-body">
        ₱<?= number_format(
            $loan['total_payable'],
            2
        ) ?>
    </strong>
</span>

</div>


<!-- DETAILS -->

<div
    class="border-top pt-2 mb-2"
>


<div
    class="d-flex justify-content-between small text-muted mb-1"
>

<span>

<i class="bi bi-wallet2 me-1"></i>

<?= htmlspecialchars(
    $loan['account_name']
) ?>

</span>

</div>


<div
    class="d-flex justify-content-between small"
>

<span class="text-muted">

<i class="bi bi-calendar-event me-1"></i>

Issued:
<?= date(
    'M d, Y',
    strtotime($loan['loan_date'])
) ?>

</span>


<span
    class="<?= $isOverdue ? 'text-danger fw-bold' : 'text-muted' ?>"
>

<i class="bi bi-calendar-check me-1"></i>

Due:
<?= date(
    'M d, Y',
    strtotime($loan['due_date'])
) ?>

</span>

</div>


</div>


<!-- COLLATERAL -->

<?php if (
    !empty($loan['item_name'])
): ?>

<div
    class="border-top pt-2 mt-2 mb-2 d-flex align-items-center gap-2"
>


<?php if (
    !empty($loan['image_path'])
): ?>

<img
    src="<?= htmlspecialchars(
        $loan['image_path']
    ) ?>"
    alt="Collateral"
    class="rounded border"
    style="
        width:36px;
        height:36px;
        object-fit:cover;
    "
>

<?php else: ?>

<div
    class="rounded bg-body-tertiary d-flex align-items-center justify-content-center"
    style="
        width:36px;
        height:36px;
    "
>

<i class="bi bi-box-seam text-muted"></i>

</div>

<?php endif; ?>


<div class="text-truncate">

<div
    class="fw-semibold small text-truncate"
>

<?= htmlspecialchars(
    $loan['item_name']
) ?>

</div>

<div
    class="text-muted"
    style="font-size:.68rem;"
>

Value:
₱<?= number_format(
    $loan['estimated_value'],
    2
) ?>

</div>

</div>


</div>

<?php endif; ?>


<!-- ACTIONS -->

<div
    class="d-flex justify-content-end gap-2 mt-3"
>


<?php if (
    !$isCompleted
): ?>

<button
    type="button"
    class="btn btn-sm btn-success fw-semibold"
    data-bs-toggle="modal"
    data-bs-target="#paymentModal<?= $loan['id'] ?>"
>

<i class="bi bi-cash-stack me-1"></i>

Payment

</button>

<?php endif; ?>


<a
    href="index.php?page=loan_details&id=<?= (int)$loan['id'] ?>"
    class="btn btn-sm btn-light border fw-semibold"
>

<i class="bi bi-eye me-1"></i>

Details

</a>


</div>


</div>

</div>


<?php endforeach; ?>


</div>


<?php endif; ?>


</div>


<!-- =========================================================
     DESKTOP TABLE
========================================================= -->

<div
    class="d-none d-md-block card border-0 shadow-sm rounded-4 overflow-hidden"
>


<div class="table-responsive">


<table
    class="table table-hover align-middle mb-0 loan-table"
>


<thead class="table-light">

<tr>

<th class="ps-4 py-3">
Reference
</th>

<th class="py-3">
Borrower
</th>

<th class="py-3">
Principal
</th>

<th class="py-3">
Remaining
</th>

<th class="py-3">
Collateral
</th>

<th class="py-3">
Due Date
</th>

<th class="py-3">
Status
</th>

<th class="py-3 text-end pe-4">
Action
</th>

</tr>

</thead>


<tbody>


<?php if (
    empty($loans)
): ?>


<tr>

<td
    colspan="8"
    class="text-center py-5 text-muted"
>

<i
    class="bi bi-search display-6 opacity-50"
></i>

<div class="fw-semibold mt-2">
    No loans found
</div>

<div class="small">
    Try changing your search or filter.
</div>

</td>

</tr>


<?php else: ?>


<?php foreach (
    $loans
    as $loan
): ?>


<?php

$status =
    $loan['display_status'];

$isCompleted =
    $status === 'completed';

$isOverdue =
    $status === 'overdue';

$isActive =
    $status === 'active';


if ($isCompleted) {

    $statusClass =
        'success';

    $statusText =
        'Paid';

} elseif ($isOverdue) {

    $statusClass =
        'danger';

    $statusText =
        'Overdue';

} else {

    $statusClass =
        'primary';

    $statusText =
        'Active';
}

?>


<tr>


<!-- REFERENCE -->

<td class="ps-4">

<div
    class="fw-semibold"
>

<?= htmlspecialchars(
    $loan['reference_number']
    ?: '—'
) ?>

</div>

<div
    class="text-muted"
    style="font-size:.68rem;"
>

#<?= (int)$loan['id'] ?>

</div>

</td>


<!-- BORROWER -->

<td>

<div class="fw-bold">

<?= htmlspecialchars(
    $loan['borrower_name']
) ?>

</div>

<div
    class="text-muted"
    style="font-size:.7rem;"
>

<i class="bi bi-wallet2 me-1"></i>

<?= htmlspecialchars(
    $loan['account_name']
) ?>

</div>

</td>


<!-- PRINCIPAL -->

<td>

<div class="fw-semibold">

₱<?= number_format(
    $loan['principal_amount'],
    2
) ?>

</div>

<div
    class="text-muted"
    style="font-size:.68rem;"
>

Paid:
₱<?= number_format(
    $loan['total_paid'],
    2
) ?>

</div>

</td>


<!-- REMAINING -->

<td>

<div
    class="fw-bold text-<?= $isOverdue ? 'danger' : 'success' ?>"
>

₱<?= number_format(
    max(
        0,
        $loan['remaining_balance']
    ),
    2
) ?>

</div>

<div
    class="text-muted"
    style="font-size:.68rem;"
>

of
₱<?= number_format(
    $loan['total_payable'],
    2
) ?>

</div>

</td>


<!-- COLLATERAL -->

<td>

<?php if (
    !empty($loan['item_name'])
): ?>

<div
    class="d-flex align-items-center gap-2"
>


<?php if (
    !empty($loan['image_path'])
): ?>

<img
    src="<?= htmlspecialchars(
        $loan['image_path']
    ) ?>"
    alt="Collateral"
    class="rounded border"
    style="
        width:34px;
        height:34px;
        object-fit:cover;
    "
>

<?php else: ?>

<div
    class="rounded bg-body-tertiary d-flex align-items-center justify-content-center"
    style="
        width:34px;
        height:34px;
    "
>

<i
    class="bi bi-box-seam text-muted"
></i>

</div>

<?php endif; ?>


<div>

<div
    class="fw-semibold"
    style="font-size:.78rem;"
>

<?= htmlspecialchars(
    $loan['item_name']
) ?>

</div>

<div
    class="text-muted"
    style="font-size:.65rem;"
>

₱<?= number_format(
    $loan['estimated_value'],
    2
) ?>

</div>

</div>


</div>


<?php else: ?>

<span
    class="text-muted small"
>
—
</span>

<?php endif; ?>

</td>


<!-- DUE DATE -->

<td>

<div
    class="<?= $isOverdue ? 'text-danger fw-bold' : 'text-body' ?>"
    style="font-size:.78rem;"
>

<?= date(
    'M d, Y',
    strtotime(
        $loan['due_date']
    )
) ?>

</div>

<div
    class="text-muted"
    style="font-size:.65rem;"
>

Issued:
<?= date(
    'M d, Y',
    strtotime(
        $loan['loan_date']
    )
) ?>

</div>

</td>


<!-- STATUS -->

<td>

<span
    class="status-badge bg-<?= $statusClass ?> bg-opacity-10 text-<?= $statusClass ?>"
>

<?= $statusText ?>

</span>

</td>


<!-- ACTION -->

<td class="text-end pe-4">

<div
    class="d-flex justify-content-end gap-1"
>


<?php if (
    !$isCompleted
): ?>

<button
    type="button"
    class="btn btn-sm btn-success table-action-btn"
    title="Record Payment"
    data-bs-toggle="modal"
    data-bs-target="#paymentModal<?= $loan['id'] ?>"
>

<i class="bi bi-cash-stack"></i>

</button>

<?php endif; ?>


<a
    href="index.php?page=loan_details&id=<?= (int)$loan['id'] ?>"
    class="btn btn-sm btn-light border table-action-btn"
    title="View Details"
>

<i class="bi bi-eye"></i>

</a>


</div>

</td>


</tr>


<?php endforeach; ?>


<?php endif; ?>


</tbody>

</table>

</div>

</div>


</div>

</div>


<!-- =========================================================
     PAYMENT MODALS
========================================================= -->

<?php foreach (
    $loans
    as $loan
): ?>


<?php

if (
    $loan['display_status'] ===
    'completed'
) {
    continue;
}

?>


<div
    class="modal fade"
    id="paymentModal<?= $loan['id'] ?>"
    tabindex="-1"
    aria-hidden="true"
>


<div
    class="modal-dialog modal-dialog-centered"
>


<div
    class="modal-content border-0 shadow-lg rounded-4"
>


<div
    class="modal-header border-bottom px-4 py-3"
>


<h5
    class="modal-title fw-bold fs-6"
>

<i
    class="bi bi-cash-coin text-success me-2"
></i>

Record Payment

<span class="text-muted">

(
<?= htmlspecialchars(
    $loan['reference_number']
    ?: '#' . $loan['id']
) ?>
)

</span>

</h5>


<button
    type="button"
    class="btn-close shadow-none"
    data-bs-dismiss="modal"
></button>


</div>


<form method="POST">


<input
    type="hidden"
    name="record_payment"
    value="1"
>


<input
    type="hidden"
    name="loan_id"
    value="<?= (int)$loan['id'] ?>"
>


<div class="modal-body p-4">


<div class="mb-3">

<label
    class="small text-muted d-block"
>
Borrower
</label>

<strong>
<?= htmlspecialchars(
    $loan['borrower_name']
) ?>
</strong>

</div>


<div
    class="p-3 rounded-3 bg-success bg-opacity-10 border border-success border-opacity-25 mb-3"
>

<div
    class="small text-muted"
>
Remaining Balance
</div>

<div
    class="fs-4 fw-bold text-success"
>

₱<?= number_format(
    max(
        0,
        $loan['remaining_balance']
    ),
    2
) ?>

</div>

</div>


<div class="mb-3">

<label
    class="form-label fw-semibold small"
>

Payment Amount (₱)

<span class="text-danger">
*
</span>

</label>

<input
    type="number"
    step="0.01"
    min="0.01"
    max="<?= max(
        0,
        floatval(
            $loan['remaining_balance']
        )
    ) ?>"
    name="payment_amount"
    class="form-control shadow-none"
    required
    placeholder="Enter amount paid"
>

</div>


<div class="mb-3">

<label
    class="form-label fw-semibold small"
>

Payment Date

<span class="text-danger">
*
</span>

</label>

<input
    type="date"
    name="payment_date"
    class="form-control shadow-none"
    value="<?= date('Y-m-d') ?>"
    required
>

</div>


<div class="mb-0">

<label
    class="form-label fw-semibold small"
>
Notes / Remarks
</label>

<textarea
    name="payment_notes"
    class="form-control shadow-none"
    rows="2"
    placeholder="Optional payment notes..."
></textarea>

</div>


</div>


<div
    class="modal-footer border-top px-4 py-3"
>


<button
    type="button"
    class="btn btn-light fw-semibold"
    data-bs-dismiss="modal"
>
Cancel
</button>


<button
    type="submit"
    class="btn btn-success fw-bold"
>

<i
    class="bi bi-check-circle me-1"
></i>

Save Payment

</button>


</div>


</form>


</div>

</div>

</div>


<?php endforeach; ?>


<!-- =========================================================
     ISSUE NEW LOAN MODAL
========================================================= -->

<div
    class="modal fade"
    id="issueLoanModal"
    tabindex="-1"
    aria-hidden="true"
>


<div
    class="modal-dialog modal-dialog-centered modal-lg"
>


<div
    class="modal-content border-0 shadow-lg rounded-4"
>


<div
    class="modal-header border-bottom px-4 py-3"
>


<h5
    class="modal-title fw-bold fs-6"
>

<i
    class="bi bi-file-earmark-plus-fill text-primary me-2"
></i>

Issue New Loan

</h5>


<button
    type="button"
    class="btn-close shadow-none"
    data-bs-dismiss="modal"
></button>


</div>


<form
    method="POST"
    enctype="multipart/form-data"
>


<input
    type="hidden"
    name="issue_loan"
    value="1"
>


<div class="modal-body p-4">


<!-- BORROWER / ACCOUNT -->

<div class="row">

<div class="col-md-6 mb-3">

<label
    class="form-label fw-semibold small"
>

Borrower

<span class="text-danger">
*
</span>

</label>


<select
    name="borrower_id"
    class="form-select shadow-none"
    required
>

<option value="">
    -- Choose Borrower --
</option>


<?php foreach (
    $borrowers
    as $b
): ?>

<option
    value="<?= (int)$b['id'] ?>"
>

<?= htmlspecialchars(
    $b['first_name'] .
    ' ' .
    $b['last_name']
) ?>

</option>

<?php endforeach; ?>


</select>

</div>


<div class="col-md-6 mb-3">

<label
    class="form-label fw-semibold small"
>

Funding Account / Wallet

<span class="text-danger">
*
</span>

</label>


<select
    name="account_id"
    class="form-select shadow-none"
    required
>

<option value="">
    -- Choose Account --
</option>


<?php foreach (
    $accounts
    as $acc
): ?>

<option
    value="<?= (int)$acc['id'] ?>"
>

<?= htmlspecialchars(
    $acc['account_name']
) ?>

— ₱<?= number_format(
    $acc['balance'],
    2
) ?>

</option>

<?php endforeach; ?>


</select>

</div>

</div>


<!-- REFERENCE / DATE -->

<div class="row">

<div class="col-md-6 mb-3">

<label
    class="form-label fw-semibold small"
>

Reference Number

<span
    class="text-muted"
>
(Optional)
</span>

</label>


<input
    type="text"
    name="reference_number"
    class="form-control shadow-none"
    placeholder="e.g. LOAN-2026-001"
>

</div>


<div class="col-md-6 mb-3">

<label
    class="form-label fw-semibold small"
>

Loan Date
<span class="text-danger">*</span>

</label>


<input
    type="date"
    name="loan_date"
    id="loanDate"
    class="form-control shadow-none"
    value="<?= date('Y-m-d') ?>"
    required
>

</div>

</div>


<!-- LOAN AMOUNT -->

<div class="row">


<div class="col-md-4 mb-3">

<label
    class="form-label fw-semibold small"
>

Principal Amount (₱)

<span class="text-danger">
*
</span>

</label>


<input
    type="number"
    step="0.01"
    min="0.01"
    name="principal_amount"
    class="form-control shadow-none"
    required
    placeholder="500.00"
>

</div>


<div class="col-md-4 mb-3">

<label
    class="form-label fw-semibold small"
>

Interest Rate (%)

</label>


<input
    type="number"
    step="0.01"
    min="0"
    name="interest_rate"
    class="form-control shadow-none"
    value="0"
    placeholder="5"
>

</div>


<div class="col-md-4 mb-3">

<label
    class="form-label fw-semibold small"
>

Loan Term

</label>


<div class="input-group">


<input
    type="number"
    min="1"
    name="term_value"
    id="termValue"
    class="form-control shadow-none"
    value="15"
    required
>


<select
    name="term_unit"
    id="termUnit"
    class="form-select shadow-none"
>

<option value="days">
    Days
</option>

<option value="months">
    Months
</option>

</select>


</div>

</div>


</div>


<!-- DUE DATE -->

<div
    class="p-3 rounded-3 bg-body-tertiary border mb-4"
>


<div
    class="d-flex justify-content-between align-items-center"
>

<span
    class="small fw-semibold text-muted"
>

Calculated Due Date

</span>


<span
    id="displayDueDate"
    class="fw-bold text-primary"
>

--

</span>


</div>


</div>


<hr class="my-4 opacity-25">


<!-- COLLATERAL -->

<h6
    class="fw-bold mb-3"
>

<i
    class="bi bi-shield-check text-success me-2"
></i>

Collateral

<span
    class="text-muted fw-normal small"
>
(Optional)
</span>

</h6>


<div class="row">


<div class="col-md-6 mb-3">

<label
    class="form-label fw-semibold small"
>

Item Name / Title

</label>


<input
    type="text"
    name="collateral_item"
    class="form-control shadow-none"
    placeholder="e.g. Smartphone, Jewelry, OR/CR"
>

</div>


<div class="col-md-6 mb-3">

<label
    class="form-label fw-semibold small"
>

Estimated Value (₱)

</label>


<input
    type="number"
    step="0.01"
    min="0"
    name="collateral_value"
    class="form-control shadow-none"
    placeholder="3000.00"
>

</div>


</div>


<div class="row">


<div class="col-md-7 mb-3">

<label
    class="form-label fw-semibold small"
>

Description / Specs / Serial #

</label>


<textarea
    name="collateral_description"
    class="form-control shadow-none"
    rows="3"
    placeholder="Brand, model, serial number, condition..."
></textarea>

</div>


<div class="col-md-5 mb-3">

<label
    class="form-label fw-semibold small"
>

Collateral Photo

</label>


<input
    type="file"
    name="collateral_image"
    class="form-control shadow-none"
    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
>


<div
    class="form-text"
>

JPG, PNG or WEBP

</div>

</div>


</div>


</div>


<div
    class="modal-footer border-top px-4 py-3"
>


<button
    type="button"
    class="btn btn-light fw-semibold"
    data-bs-dismiss="modal"
>

Cancel

</button>


<button
    type="submit"
    class="btn btn-primary fw-bold"
>

<i
    class="bi bi-check-circle me-1"
></i>

Issue Loan

</button>


</div>


</form>


</div>

</div>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


<script>

/*
|--------------------------------------------------------------------------
| CALCULATE DUE DATE
|--------------------------------------------------------------------------
*/

function calculateDueDate() {

    const loanDateInput =
        document.getElementById(
            'loanDate'
        );

    const termValueInput =
        document.getElementById(
            'termValue'
        );

    const termUnitSelect =
        document.getElementById(
            'termUnit'
        );

    const displayDueDate =
        document.getElementById(
            'displayDueDate'
        );


    if (
        !loanDateInput ||
        !termValueInput ||
        !termUnitSelect ||
        !displayDueDate
    ) {
        return;
    }


    const loanDate =
        loanDateInput.value;

    const termValue =
        parseInt(
            termValueInput.value
        ) || 0;

    const termUnit =
        termUnitSelect.value;


    if (
        !loanDate ||
        termValue <= 0
    ) {

        displayDueDate.textContent =
            '--';

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Use local date to avoid timezone shifting
    |--------------------------------------------------------------------------
    */

    const parts =
        loanDate.split('-');

    let date =
        new Date(
            parseInt(parts[0]),
            parseInt(parts[1]) - 1,
            parseInt(parts[2])
        );


    if (
        termUnit === 'months'
    ) {

        date.setMonth(
            date.getMonth() +
            termValue
        );

    } else {

        date.setDate(
            date.getDate() +
            termValue
        );
    }


    const options = {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    };


    displayDueDate.textContent =
        date.toLocaleDateString(
            'en-US',
            options
        );
}


/*
|--------------------------------------------------------------------------
| Event Listeners
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const loanDate =
            document.getElementById(
                'loanDate'
            );

        const termValue =
            document.getElementById(
                'termValue'
            );

        const termUnit =
            document.getElementById(
                'termUnit'
            );


        if (loanDate) {

            loanDate.addEventListener(
                'change',
                calculateDueDate
            );
        }


        if (termValue) {

            termValue.addEventListener(
                'input',
                calculateDueDate
            );
        }


        if (termUnit) {

            termUnit.addEventListener(
                'change',
                calculateDueDate
            );
        }


        calculateDueDate();

    }
);

</script>


</body>

</html>