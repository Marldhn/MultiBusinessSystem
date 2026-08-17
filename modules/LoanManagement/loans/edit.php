<?php
$pdo = Database::getConnection();

$sessionBusinessId = (int)($_SESSION['business_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

if ($sessionBusinessId <= 0) {
    header('Location: index.php?page=select_business');
    exit;
}

if ($userId <= 0) {
    header('Location: index.php?page=login');
    exit;
}

$loanId = (int)($_GET['id'] ?? $_POST['loan_id'] ?? 0);

if ($loanId <= 0) {
    header('Location: index.php?page=loans');
    exit;
}

$error = '';
$success = '';

/* ============================================================
   HELPER FUNCTIONS
   ============================================================ */

function calculateDueDate($loanDate, $termValue, $termUnit)
{
    try {
        $date = new DateTime($loanDate);
    } catch (Throwable $e) {
        return null;
    }

    $termValue = max(1, (int)$termValue);

    switch (strtolower($termUnit)) {
        case 'days':
            $date->modify("+{$termValue} days");
            break;
        case 'weeks':
            $date->modify("+{$termValue} weeks");
            break;
        case 'months':
            $date->modify("+{$termValue} months");
            break;
        case 'years':
            $date->modify("+{$termValue} years");
            break;
        default:
            $date->modify("+{$termValue} months");
            break;
    }

    return $date->format('Y-m-d');
}

function calculateNumberOfPayments($loanDate, $dueDate, $frequency)
{
    try {
        $start = new DateTime($loanDate);
        $end = new DateTime($dueDate);
    } catch (Throwable $e) {
        return 1;
    }

    if ($end <= $start) {
        return 1;
    }

    $days = (int)$start->diff($end)->days;

    switch (strtolower($frequency)) {
        case 'daily':
            return max(1, $days);

        case 'weekly':
            return max(1, (int)ceil($days / 7));

        case 'biweekly':
            return max(1, (int)ceil($days / 14));

        case 'monthly':
        default:
            $months =
                ((int)$end->format('Y') - (int)$start->format('Y')) * 12 +
                ((int)$end->format('n') - (int)$start->format('n'));

            if ((int)$end->format('d') < (int)$start->format('d')) {
                $months--;
            }

            return max(1, $months);
    }
}

function getNextPaymentDate($date, $frequency)
{
    try {
        $next = new DateTime($date);
    } catch (Throwable $e) {
        return $date;
    }

    switch (strtolower($frequency)) {
        case 'daily':
            $next->modify('+1 day');
            break;

        case 'weekly':
            $next->modify('+1 week');
            break;

        case 'biweekly':
            $next->modify('+2 weeks');
            break;

        case 'monthly':
        default:
            $next->modify('+1 month');
            break;
    }

    return $next->format('Y-m-d');
}

/* ============================================================
   FETCH LOAN
   ============================================================ */

$loanStmt = $pdo->prepare("
    SELECT *
    FROM loans
    WHERE id = ?
      AND business_id = ?
      AND created_by = ?
    LIMIT 1
");

$loanStmt->execute([
    $loanId,
    $sessionBusinessId,
    $userId
]);

$loan = $loanStmt->fetch(PDO::FETCH_ASSOC);

if (!$loan) {
    header('Location: index.php?page=loans');
    exit;
}

/* ============================================================
   USE BUSINESS ID FROM ACTUAL LOAN
   ============================================================ */

$businessId = (int)($loan['business_id'] ?? 0);

if ($businessId <= 0) {
    die('This loan does not have a valid business_id.');
}

/* ============================================================
   VERIFY BUSINESS EXISTS
   ============================================================ */

$businessStmt = $pdo->prepare("
    SELECT id
    FROM businesses
    WHERE id = ?
    LIMIT 1
");

$businessStmt->execute([$businessId]);

$verifiedBusinessId = $businessStmt->fetchColumn();

if (!$verifiedBusinessId) {
    die(
        'The business assigned to this loan does not exist. Business ID: ' .
        $businessId
    );
}

$businessId = (int)$verifiedBusinessId;

/* ============================================================
   FETCH BORROWERS
   ============================================================ */

$borrowerStmt = $pdo->prepare("
    SELECT
        id,
        first_name,
        middle_name,
        last_name
    FROM loan_borrowers
    WHERE business_id = ?
      AND status = 'active'
    ORDER BY first_name, last_name
");

$borrowerStmt->execute([$businessId]);
$borrowers = $borrowerStmt->fetchAll(PDO::FETCH_ASSOC);

/* ============================================================
   FETCH ACCOUNTS
   ============================================================ */

$accountStmt = $pdo->prepare("
    SELECT
        id,
        account_name,
        account_type,
        balance
    FROM loan_accounts
    WHERE business_id = ?
      AND status = 'active'
    ORDER BY account_name
");

$accountStmt->execute([$businessId]);
$accounts = $accountStmt->fetchAll(PDO::FETCH_ASSOC);

/* ============================================================
   ALWAYS CALCULATE EXISTING PENALTIES
   IMPORTANT FIX:
   This runs even when the page is opened with GET.
   ============================================================ */

$totalPenalties = 0.00;

$penaltyStmt = $pdo->prepare("
    SELECT COALESCE(
        SUM(
            CASE
                WHEN LOWER(status) <> 'waived'
                THEN amount
                ELSE 0
            END
        ),
        0
    )
    FROM loan_penalties
    WHERE loan_id = ?
      AND business_id = ?
");

$penaltyStmt->execute([
    $loanId,
    $businessId
]);

$totalPenalties = (float)$penaltyStmt->fetchColumn();

/* ============================================================
   POST - UPDATE LOAN
   ============================================================ */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'update_loan'
) {
    $borrowerId = (int)($_POST['borrower_id'] ?? 0);
    $accountId = (int)($_POST['account_id'] ?? 0);

    $referenceNumber = trim($_POST['reference_number'] ?? '');
    $loanDate = trim($_POST['loan_date'] ?? '');

    $termValue = max(
        1,
        (int)($_POST['term_value'] ?? 1)
    );

    $termUnit = strtolower(
        trim($_POST['term_unit'] ?? 'months')
    );

    $principalAmount = (float)(
        $_POST['principal_amount'] ?? 0
    );

    $interestRate = (float)(
        $_POST['interest_rate'] ?? 0
    );

    $paymentFrequency = strtolower(
        trim($_POST['payment_frequency'] ?? 'monthly')
    );

    $paymentType = strtolower(
        trim($_POST['payment_type'] ?? 'installment')
    );

    $fixedPaymentAmount = (float)(
        $_POST['fixed_payment_amount'] ?? 0
    );



    $allowedTermUnits = [
        'days',
        'weeks',
        'months',
        'years'
    ];

    $allowedFrequencies = [
        'daily',
        'weekly',
        'biweekly',
        'monthly'
    ];

    $allowedPaymentTypes = [
        'installment',
        'lump_sum'
    ];

    try {
        if ($borrowerId <= 0) {
            throw new Exception('Please select a borrower.');
        }

        if ($accountId <= 0) {
            throw new Exception('Please select a funding account.');
        }

        if ($loanDate === '') {
            throw new Exception('Please select the loan date.');
        }

        if (!in_array($termUnit, $allowedTermUnits, true)) {
            throw new Exception('Invalid loan term unit.');
        }

        if (!in_array($paymentFrequency, $allowedFrequencies, true)) {
            throw new Exception('Invalid payment frequency.');
        }

        if (!in_array($paymentType, $allowedPaymentTypes, true)) {
            throw new Exception('Invalid payment type.');
        }

        if ($principalAmount <= 0) {
            throw new Exception(
                'Principal amount must be greater than zero.'
            );
        }

        if ($interestRate < 0) {
            throw new Exception(
                'Interest rate cannot be negative.'
            );
        }

        if ($fixedPaymentAmount < 0) {
            throw new Exception(
                'Fixed payment amount cannot be negative.'
            );
        }

        /* ========================================================
           VALIDATE DATE
           ======================================================== */

        try {
            $loanDateObject = new DateTime($loanDate);
        } catch (Throwable $e) {
            throw new Exception('Invalid loan date.');
        }

        /* ========================================================
           VALIDATE BORROWER
           ======================================================== */

        $borrowerCheck = $pdo->prepare("
            SELECT id
            FROM loan_borrowers
            WHERE id = ?
              AND business_id = ?
            LIMIT 1
        ");

        $borrowerCheck->execute([
            $borrowerId,
            $businessId
        ]);

        if (!$borrowerCheck->fetchColumn()) {
            throw new Exception(
                'The selected borrower does not belong to this business.'
            );
        }

        /* ========================================================
           VALIDATE ACCOUNT
           ======================================================== */

        $accountCheck = $pdo->prepare("
            SELECT id
            FROM loan_accounts
            WHERE id = ?
              AND business_id = ?
            LIMIT 1
        ");

        $accountCheck->execute([
            $accountId,
            $businessId
        ]);

        if (!$accountCheck->fetchColumn()) {
            throw new Exception(
                'The selected account does not belong to this business.'
            );
        }

        /* ========================================================
           CALCULATE DUE DATE
           ======================================================== */

        $dueDate = calculateDueDate(
            $loanDate,
            $termValue,
            $termUnit
        );

        if (!$dueDate) {
            throw new Exception(
                'Unable to calculate the due date.'
            );
        }

        /* ========================================================
           CALCULATE INTEREST
           ======================================================== */

        $interestAmount = round(
            $principalAmount * ($interestRate / 100),
            2
        );

        $baseTotalPayable = round(
            $principalAmount + $interestAmount,
            2
        );

        /* ========================================================
           REFRESH PENALTIES
           ======================================================== */

        $penaltyStmt = $pdo->prepare("
            SELECT COALESCE(
                SUM(
                    CASE
                        WHEN LOWER(status) <> 'waived'
                        THEN amount
                        ELSE 0
                    END
                ),
                0
            )
            FROM loan_penalties
            WHERE loan_id = ?
              AND business_id = ?
        ");

        $penaltyStmt->execute([
            $loanId,
            $businessId
        ]);

        $totalPenalties = (float)$penaltyStmt->fetchColumn();

        $totalPayable = round(
            $baseTotalPayable + $totalPenalties,
            2
        );

        /* ========================================================
           CALCULATE NUMBER OF PAYMENTS
           ======================================================== */

        if ($paymentType === 'lump_sum') {
            $numberOfPayments = 1;
            $installmentAmount = $totalPayable;
        } else {
            $numberOfPayments = calculateNumberOfPayments(
                $loanDate,
                $dueDate,
                $paymentFrequency
            );

            if ($fixedPaymentAmount > 0) {
                $installmentAmount = round(
                    $fixedPaymentAmount,
                    2
                );
            } else {
                $installmentAmount = round(
                    $totalPayable / max(1, $numberOfPayments),
                    2
                );
            }
        }

        /* ========================================================
           BEGIN TRANSACTION
           ======================================================== */

        $pdo->beginTransaction();

        /* ========================================================
           LOCK LOAN
           ======================================================== */

        $lockLoanStmt = $pdo->prepare("
            SELECT *
            FROM loans
            WHERE id = ?
              AND business_id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $lockLoanStmt->execute([
            $loanId,
            $businessId
        ]);

        $lockedLoan = $lockLoanStmt->fetch(PDO::FETCH_ASSOC);

        if (!$lockedLoan) {
            throw new Exception(
                'Loan could not be locked for editing.'
            );
        }

        /* ========================================================
           IMPORTANT:
           Re-confirm business ID from locked loan.
           ======================================================== */

        $lockedBusinessId = (int)(
            $lockedLoan['business_id'] ?? 0
        );

        if ($lockedBusinessId <= 0) {
            throw new Exception(
                'The loan has an invalid business ID.'
            );
        }

        /* ========================================================
           MAKE SURE BUSINESS STILL EXISTS
           ======================================================== */

        $verifyLockedBusiness = $pdo->prepare("
            SELECT id
            FROM businesses
            WHERE id = ?
            LIMIT 1
        ");

        $verifyLockedBusiness->execute([
            $lockedBusinessId
        ]);

        if (!$verifyLockedBusiness->fetchColumn()) {
            throw new Exception(
                'The business associated with this loan no longer exists.'
            );
        }

        $businessId = $lockedBusinessId;

        /* ========================================================
           UPDATE LOAN
           ======================================================== */

        $updateLoanStmt = $pdo->prepare("
            UPDATE loans
            SET
                borrower_id = ?,
                account_id = ?,
                reference_number = ?,
                loan_date = ?,
                due_date = ?,
                principal_amount = ?,
                interest_rate = ?,
                total_payable = ?,
                term_days = ?,
                term_unit = ?,
                payment_frequency = ?,
                payment_type = ?,
                fixed_payment_amount = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
              AND business_id = ?
        ");

        $updateLoanStmt->execute([
            $borrowerId,
            $accountId,
            $referenceNumber !== '' ? $referenceNumber : null,
            $loanDate,
            $dueDate,
            $principalAmount,
            $interestRate,
            $totalPayable,
            $termValue,
            $termUnit,
            $paymentFrequency,
            $paymentType,
            $fixedPaymentAmount,
            $loanId,
            $businessId
        ]);

        /* ========================================================
           CHECK EXISTING PAYMENTS
           ======================================================== */

        $paymentCheckStmt = $pdo->prepare("
            SELECT
                COUNT(*) AS payment_count,
                COALESCE(SUM(payment_amount), 0) AS total_paid
            FROM loan_payments
            WHERE loan_id = ?
              AND business_id = ?
        ");

        $paymentCheckStmt->execute([
            $loanId,
            $businessId
        ]);

        $paymentInfo = $paymentCheckStmt->fetch(PDO::FETCH_ASSOC);

        $paymentCount = (int)(
            $paymentInfo['payment_count'] ?? 0
        );

        $totalPaid = (float)(
            $paymentInfo['total_paid'] ?? 0
        );

        /* ========================================================
           CHECK EXISTING SCHEDULES
           ======================================================== */

        $oldSchedulesStmt = $pdo->prepare("
            SELECT
                id,
                installment_number,
                due_date,
                amount_due,
                penalty_amount,
                total_due,
                amount_paid,
                balance_due,
                status
            FROM loan_schedules
            WHERE loan_id = ?
              AND business_id = ?
            ORDER BY installment_number ASC, id ASC
        ");

        $oldSchedulesStmt->execute([
            $loanId,
            $businessId
        ]);

        $oldSchedules = $oldSchedulesStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        /* ========================================================
           IF PAYMENTS EXIST
           DO NOT DELETE SCHEDULES
           ======================================================== */

        if ($paymentCount > 0) {
            if (!$oldSchedules) {
                throw new Exception(
                    'This loan already has payments but no payment schedules were found. Please review the loan before editing.'
                );
            }

            $scheduleUpdateStmt = $pdo->prepare("
                UPDATE loan_schedules
                SET
                    due_date = ?,
                    amount_due = ?,
                    total_due = ?,
                    balance_due = GREATEST(
                        0,
                        ? - amount_paid
                    ),
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
                  AND loan_id = ?
                  AND business_id = ?
            ");

            $currentDate = $loanDate;

            foreach ($oldSchedules as $index => $oldSchedule) {
                $installmentNumber = (int)(
                    $oldSchedule['installment_number'] ?? ($index + 1)
                );

                if ($paymentType === 'lump_sum') {
                    $scheduleDueDate = $dueDate;
                    $scheduleAmount = $totalPayable;
                } else {
                    if ($index === 0) {
                        $scheduleDueDate = getNextPaymentDate(
                            $loanDate,
                            $paymentFrequency
                        );
                    } else {
                        $scheduleDueDate = getNextPaymentDate(
                            $currentDate,
                            $paymentFrequency
                        );
                    }

                    $currentDate = $scheduleDueDate;

                    if ($installmentNumber >= $numberOfPayments) {
                        $scheduleDueDate = $dueDate;
                    }

                    if ($installmentNumber >= $numberOfPayments) {
                        $scheduleAmount = round(
                            $totalPayable -
                            (
                                $installmentAmount *
                                max(
                                    0,
                                    $numberOfPayments - 1
                                )
                            ),
                            2
                        );
                    } else {
                        $scheduleAmount = $installmentAmount;
                    }

                    if ($scheduleAmount < 0) {
                        $scheduleAmount = 0;
                    }
                }

                $schedulePenalty = (float)(
                    $oldSchedule['penalty_amount'] ?? 0
                );

                $scheduleTotal = round(
                    $scheduleAmount + $schedulePenalty,
                    2
                );

                $scheduleUpdateStmt->execute([
                    $scheduleDueDate,
                    $scheduleAmount,
                    $scheduleTotal,
                    $scheduleTotal,
                    (int)$oldSchedule['id'],
                    $loanId,
                    $businessId
                ]);
            }
        } else {
            /* ====================================================
               NO PAYMENTS:
               SAFE TO REBUILD SCHEDULES
               ==================================================== */

            $deleteSchedulesStmt = $pdo->prepare("
                DELETE FROM loan_schedules
                WHERE loan_id = ?
                  AND business_id = ?
            ");

            $deleteSchedulesStmt->execute([
                $loanId,
                $businessId
            ]);

            /* ====================================================
               CREATE SCHEDULE INSERT STATEMENT
               ==================================================== */

            $insertScheduleStmt = $pdo->prepare("
                INSERT INTO loan_schedules (
                    business_id,
                    loan_id,
                    installment_number,
                    due_date,
                    amount_due,
                    penalty_amount,
                    total_due,
                    amount_paid,
                    balance_due,
                    status
                ) VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    0.00,
                    ?,
                    0.00,
                    ?,
                    'unpaid'
                )
            ");

            if ($paymentType === 'lump_sum') {
                $insertScheduleStmt->execute([
                    $businessId,
                    $loanId,
                    1,
                    $dueDate,
                    $totalPayable,
                    $totalPayable,
                    $totalPayable
                ]);
            } else {
                $scheduleDate = $loanDate;
                $remainingAmount = $totalPayable;

                for ($i = 1; $i <= $numberOfPayments; $i++) {
                    $scheduleDate = getNextPaymentDate(
                        $scheduleDate,
                        $paymentFrequency
                    );

                    if ($i === $numberOfPayments) {
                        $scheduleDate = $dueDate;
                    }

                    if ($i === $numberOfPayments) {
                        $amountDue = round(
                            $remainingAmount,
                            2
                        );
                    } else {
                        $amountDue = round(
                            min(
                                $installmentAmount,
                                $remainingAmount
                            ),
                            2
                        );
                    }

                    if ($amountDue < 0) {
                        $amountDue = 0;
                    }

                    $remainingAmount = round(
                        $remainingAmount - $amountDue,
                        2
                    );

                    $insertScheduleStmt->execute([
                        $businessId,
                        $loanId,
                        $i,
                        $scheduleDate,
                        $amountDue,
                        $amountDue,
                        $amountDue
                    ]);
                }
            }
        }

        /* ========================================================
           UPDATE SCHEDULE STATUS
           ======================================================== */

        $statusStmt = $pdo->prepare("
            UPDATE loan_schedules
            SET
                status = CASE
                    WHEN balance_due <= 0
                        THEN 'paid'
                    WHEN amount_paid > 0
                         AND balance_due > 0
                        THEN 'partially_paid'
                    WHEN due_date < CURDATE()
                         AND balance_due > 0
                        THEN 'overdue'
                    ELSE 'unpaid'
                END,
                updated_at = CURRENT_TIMESTAMP
            WHERE loan_id = ?
              AND business_id = ?
        ");

        $statusStmt->execute([
            $loanId,
            $businessId
        ]);

        /* ========================================================
           UPDATE LOAN STATUS
           ======================================================== */

        $remainingLoanBalance = max(
            0,
            $totalPayable - $totalPaid
        );

        if ($remainingLoanBalance <= 0) {
            $loanStatus = 'completed';
        } elseif (strtotime($dueDate) < strtotime(date('Y-m-d'))) {
            $loanStatus = 'overdue';
        } else {
            $loanStatus = 'active';
        }

        $statusLoanStmt = $pdo->prepare("
            UPDATE loans
            SET status = ?
            WHERE id = ?
              AND business_id = ?
        ");

        $statusLoanStmt->execute([
            $loanStatus,
            $loanId,
            $businessId
        ]);

        /* ========================================================
           COMMIT
           ======================================================== */

        $pdo->commit();

        header(
            'Location: index.php?page=loan_details&id=' .
            $loanId .
            '&updated=1'
        );
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e->getMessage();
    }
}

/* ============================================================
   SUCCESS MESSAGE
   ============================================================ */

if (
    isset($_GET['updated']) &&
    $_GET['updated'] === '1'
) {
    $success =
        'Loan updated successfully. Due date and payment schedule were recalculated.';
}

/* ============================================================
   REFRESH LOAN
   ============================================================ */

$loanStmt->execute([
    $loanId,
    $businessId,
    $userId
]);

$loan = $loanStmt->fetch(PDO::FETCH_ASSOC);

if (!$loan) {
    header('Location: index.php?page=loans');
    exit;
}

/* ============================================================
   REFRESH PENALTIES AFTER UPDATE
   ============================================================ */

$totalPenalties = 0.00;

$penaltyStmt = $pdo->prepare("
    SELECT COALESCE(
        SUM(
            CASE
                WHEN LOWER(status) <> 'waived'
                THEN amount
                ELSE 0
            END
        ),
        0
    )
    FROM loan_penalties
    WHERE loan_id = ?
      AND business_id = ?
");

$penaltyStmt->execute([
    $loanId,
    $businessId
]);

$totalPenalties = (float)$penaltyStmt->fetchColumn();

/* ============================================================
   FORM VALUES
   ============================================================ */

$loanDateValue = $loan['loan_date'] ?? date('Y-m-d');

$termValueValue = max(
    1,
    (int)($loan['term_days'] ?? 1)
);

$termUnitValue = strtolower(
    $loan['term_unit'] ?? 'months'
);

$paymentFrequencyValue = strtolower(
    $loan['payment_frequency'] ?? 'monthly'
);

$paymentTypeValue = strtolower(
    $loan['payment_type'] ?? 'installment'
);

$principalValue = (float)(
    $loan['principal_amount'] ?? 0
);

$interestRateValue = (float)(
    $loan['interest_rate'] ?? 0
);

$fixedPaymentValue = (float)(
    $loan['fixed_payment_amount'] ?? 0
);

$dueDateValue = $loan['due_date'] ??
    calculateDueDate(
        $loanDateValue,
        $termValueValue,
        $termUnitValue
    );

$interestPreview = round(
    $principalValue *
    ($interestRateValue / 100),
    2
);

$totalPayablePreview = round(
    $principalValue +
    $interestPreview +
    $totalPenalties,
    2
);

$activePage = 'loans';
$pageTitle = 'Edit Loan - Loan Management';
?>

<!DOCTYPE html>

<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
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
body{font-size:.9rem}
.card{border:0;border-radius:14px;box-shadow:0 .125rem .5rem rgba(0,0,0,.06)}
.form-label{font-weight:600}
</style>

</head>

<body class="bg-body-tertiary" style="min-height:100vh">

<div class="d-flex flex-column flex-lg-row" style="min-height:100vh">

<?php
$sidebarPath = __DIR__ . '/../../../resources/partials/loansidebar.php';

if (file_exists($sidebarPath)) {
    include $sidebarPath;
}
?>

<div class="p-3 p-md-4 flex-grow-1">

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

<div>
<a
    href="index.php?page=loan_details&id=<?= htmlspecialchars($loanId) ?>"
    class="text-decoration-none text-muted small"
>
<i class="bi bi-arrow-left me-1"></i>
Back to Loan Details
</a>

<h2 class="fw-bold mt-2 mb-1">Edit Loan</h2>

<p class="text-muted mb-0">
Modify the loan information and automatically recalculate the payment schedule.
</p>
</div>

<div>
<a
    href="index.php?page=loan_details&id=<?= htmlspecialchars($loanId) ?>"
    class="btn btn-light border fw-semibold"
>
<i class="bi bi-x-lg me-1"></i>
Cancel
</a>
</div>

</div>

<?php if ($error !== ''): ?>

<div class="alert alert-danger alert-dismissible fade show">
<i class="bi bi-exclamation-circle me-2"></i>
<?= htmlspecialchars($error) ?>
<button
    type="button"
    class="btn-close"
    data-bs-dismiss="alert"
></button>
</div>
<?php endif; ?>

<?php if ($success !== ''): ?>

<div class="alert alert-success alert-dismissible fade show">
<i class="bi bi-check-circle me-2"></i>
<?= htmlspecialchars($success) ?>
<button
    type="button"
    class="btn-close"
    data-bs-dismiss="alert"
></button>
</div>
<?php endif; ?>

<div class="alert alert-info">
<i class="bi bi-info-circle me-2"></i>
Changing the loan date, term, payment frequency, principal, or interest rate will recalculate the loan's due date and payment schedule.
</div>

<form method="POST" id="editLoanForm">

<input type="hidden" name="action" value="update_loan">
<input type="hidden" name="loan_id" value="<?= htmlspecialchars($loanId) ?>">

<div class="row g-4">

<div class="col-lg-8">

<div class="card mb-4">

<div class="card-header bg-transparent border-0 px-4 pt-4">
<h5 class="fw-bold mb-0">
<i class="bi bi-file-earmark-text text-primary me-2"></i>
Loan Information
</h5>
</div>

<div class="card-body px-4 pb-4">

<div class="row g-3">

<div class="col-md-6">
<label class="form-label">Reference Number</label>
<input
    type="text"
    name="reference_number"
    class="form-control"
    value="<?= htmlspecialchars($loan['reference_number'] ?? '') ?>"
    placeholder="Loan reference"
>
</div>

<div class="col-md-6">
<label class="form-label">Borrower</label>

<select name="borrower_id" class="form-select" required>
<option value="">Select Borrower</option>

<?php foreach ($borrowers as $borrower): ?>

<?php
$borrowerName = trim(
    ($borrower['first_name'] ?? '') . ' ' .
    ($borrower['middle_name'] ?? '') . ' ' .
    ($borrower['last_name'] ?? '')
);
?>

<option
    value="<?= htmlspecialchars($borrower['id']) ?>"
    <?= ((int)$loan['borrower_id'] === (int)$borrower['id']) ? 'selected' : '' ?>
>
<?= htmlspecialchars($borrowerName) ?>
</option>

<?php endforeach; ?>

</select>
</div>

<div class="col-md-6">
<label class="form-label">Funding Account</label>

<select name="account_id" class="form-select" required>
<option value="">Select Account</option>

<?php foreach ($accounts as $account): ?>

<option
    value="<?= htmlspecialchars($account['id']) ?>"
    <?= ((int)$loan['account_id'] === (int)$account['id']) ? 'selected' : '' ?>
>
<?= htmlspecialchars($account['account_name']) ?>
- ₱<?= number_format((float)$account['balance'], 2) ?>
</option>

<?php endforeach; ?>

</select>
</div>

<div class="col-md-6">
<label class="form-label">Loan Date</label>

<input
type="date"
name="loan_date"
id="loanDate"
class="form-control"
value="<?= htmlspecialchars($loanDateValue) ?>"
required

>

</div>

<div class="col-md-6">
<label class="form-label">Principal Amount</label>

<div class="input-group">
<span class="input-group-text">₱</span>

<input
type="number"
name="principal_amount"
id="principalAmount"
class="form-control"
min="0.01"
step="0.01"
value="<?= htmlspecialchars($principalValue) ?>"
required

>

</div>
</div>

<div class="col-md-6">
<label class="form-label">Interest Rate</label>

<div class="input-group">

<input
type="number"
name="interest_rate"
id="interestRate"
class="form-control"
min="0"
step="0.01"
value="<?= htmlspecialchars($interestRateValue) ?>"
required

>

<span class="input-group-text">%</span>

</div>
</div>

<div class="col-md-6">
<label class="form-label">Loan Term</label>

<div class="input-group">

<input
type="number"
name="term_value"
id="termValue"
class="form-control"
min="1"
step="1"
value="<?= htmlspecialchars($termValueValue) ?>"
required

>

<select
name="term_unit"
id="termUnit"
class="form-select"

>

<option value="days" <?= $termUnitValue === 'days' ? 'selected' : '' ?>>Days</option>
<option value="weeks" <?= $termUnitValue === 'weeks' ? 'selected' : '' ?>>Weeks</option>
<option value="months" <?= $termUnitValue === 'months' ? 'selected' : '' ?>>Months</option>
<option value="years" <?= $termUnitValue === 'years' ? 'selected' : '' ?>>Years</option>
</select>

</div>
</div>

<div class="col-md-6">
<label class="form-label">Payment Frequency</label>

<select
name="payment_frequency"
id="paymentFrequency"
class="form-select"

>

<option value="daily" <?= $paymentFrequencyValue === 'daily' ? 'selected' : '' ?>>Daily</option>
<option value="weekly" <?= $paymentFrequencyValue === 'weekly' ? 'selected' : '' ?>>Weekly</option>
<option value="biweekly" <?= $paymentFrequencyValue === 'biweekly' ? 'selected' : '' ?>>Bi-weekly</option>
<option value="monthly" <?= $paymentFrequencyValue === 'monthly' ? 'selected' : '' ?>>Monthly</option>
</select>
</div>

<div class="col-md-6">
<label class="form-label">Payment Type</label>

<select
name="payment_type"
id="paymentType"
class="form-select"

>

<option value="installment" <?= $paymentTypeValue === 'installment' ? 'selected' : '' ?>>Installment</option>
<option value="lump_sum" <?= $paymentTypeValue === 'lump_sum' ? 'selected' : '' ?>>Lump Sum</option>
</select>
</div>

<div class="col-md-6">
<label class="form-label">Fixed Payment Amount</label>

<div class="input-group">
<span class="input-group-text">₱</span>

<input
type="number"
name="fixed_payment_amount"
id="fixedPaymentAmount"
class="form-control"
min="0"
step="0.01"
value="<?= htmlspecialchars($fixedPaymentValue) ?>"

>

</div>

<div class="form-text">
Leave 0 to automatically divide the total payable across the installments.
</div>
</div>


</div>
</div>
</div>

<div class="card mb-4">

<div class="card-header bg-transparent border-0 px-4 pt-4">
<h5 class="fw-bold mb-0">
<i class="bi bi-calendar-check text-primary me-2"></i>
Automatic Loan Dates
</h5>
</div>

<div class="card-body px-4 pb-4">

<div class="row g-3">

<div class="col-md-6">
<div class="border rounded-3 p-3 bg-body-tertiary">
<div class="small text-muted">Loan Date</div>

<div class="fw-bold fs-5" id="previewLoanDate">
<?= date('M d, Y', strtotime($loanDateValue)) ?>
</div>
</div>
</div>

<div class="col-md-6">
<div class="border rounded-3 p-3 bg-body-tertiary">
<div class="small text-muted">
Automatically Calculated Due Date
</div>

<div class="fw-bold fs-5 text-danger" id="previewDueDate">
<?= !empty($dueDateValue)
    ? date('M d, Y', strtotime($dueDateValue))
    : 'N/A' ?>
</div>
</div>
</div>

</div>
</div>
</div>

</div>

<div class="col-lg-4">

<div class="card mb-4">

<div class="card-header bg-transparent border-0 px-4 pt-4">
<h5 class="fw-bold mb-0">
<i class="bi bi-calculator text-primary me-2"></i>
Loan Calculation
</h5>
</div>

<div class="card-body px-4 pb-4">

<div class="d-flex justify-content-between py-2 border-bottom">
<span class="text-muted">Principal</span>
<strong id="previewPrincipal">
₱<?= number_format($principalValue, 2) ?>
</strong>
</div>

<div class="d-flex justify-content-between py-2 border-bottom">
<span class="text-muted">Interest</span>
<strong id="previewInterest">
₱<?= number_format($interestPreview, 2) ?>
</strong>
</div>

<div class="d-flex justify-content-between py-2 border-bottom">
<span class="text-muted">Existing Penalties</span>

<strong class="text-danger" id="previewPenalties">
₱<?= number_format($totalPenalties, 2) ?>
</strong>
</div>

<div class="d-flex justify-content-between py-3">
<span class="fw-bold">Total Payable</span>

<strong
class="text-primary fs-5"
id="previewTotal"

>

₱<?= number_format($totalPayablePreview, 2) ?> </strong>

</div>

</div>
</div>

<div class="card mb-4">

<div class="card-header bg-transparent border-0 px-4 pt-4">
<h5 class="fw-bold mb-0">
<i class="bi bi-calendar3 text-primary me-2"></i>
Schedule Preview
</h5>
</div>

<div class="card-body px-4 pb-4">

<div class="d-flex justify-content-between mb-2">
<span class="text-muted">Number of Payments</span>
<strong id="previewPayments">-</strong>
</div>

<div class="d-flex justify-content-between">
<span class="text-muted">Estimated Payment</span>
<strong
    class="text-primary"
    id="previewInstallment"
>
₱0.00
</strong>
</div>

</div>
</div>

<div class="card">

<div class="card-body p-4">

<button
type="submit"
class="btn btn-primary btn-lg w-100 fw-semibold"

>

<i class="bi bi-check-circle me-1"></i>
Save Changes </button>

<a
href="index.php?page=loan_details&id=<?= htmlspecialchars($loanId) ?>"
class="btn btn-light border w-100 mt-2"

>

Cancel </a>

</div>
</div>

</div>
</div>

</form>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
const existingPenalties = <?= json_encode($totalPenalties) ?>;

const loanDateInput = document.getElementById('loanDate');
const termValueInput = document.getElementById('termValue');
const termUnitInput = document.getElementById('termUnit');
const principalInput = document.getElementById('principalAmount');
const interestInput = document.getElementById('interestRate');
const frequencyInput = document.getElementById('paymentFrequency');
const paymentTypeInput = document.getElementById('paymentType');
const fixedPaymentInput = document.getElementById('fixedPaymentAmount');

const previewLoanDate = document.getElementById('previewLoanDate');
const previewDueDate = document.getElementById('previewDueDate');
const previewPrincipal = document.getElementById('previewPrincipal');
const previewInterest = document.getElementById('previewInterest');
const previewTotal = document.getElementById('previewTotal');
const previewPayments = document.getElementById('previewPayments');
const previewInstallment = document.getElementById('previewInstallment');

function money(value) {
    return '₱' + Number(value || 0).toLocaleString(
        'en-PH',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }
    );
}

function formatDate(date) {
    return date.toLocaleDateString(
        'en-US',
        {
            month: 'short',
            day: '2-digit',
            year: 'numeric'
        }
    );
}

function calculateDueDatePreview() {
    if (!loanDateInput.value) {
        return null;
    }

    const date = new Date(
        loanDateInput.value + 'T00:00:00'
    );

    const value = Math.max(
        1,
        parseInt(termValueInput.value || 1, 10)
    );

    const unit = termUnitInput.value;

    if (unit === 'days') {
        date.setDate(date.getDate() + value);
    } else if (unit === 'weeks') {
        date.setDate(date.getDate() + (value * 7));
    } else if (unit === 'months') {
        date.setMonth(date.getMonth() + value);
    } else if (unit === 'years') {
        date.setFullYear(date.getFullYear() + value);
    }

    return date;
}

function calculatePayments(
    loanDate,
    dueDate,
    frequency
) {
    if (!loanDate || !dueDate) {
        return 1;
    }

    const milliseconds =
        dueDate.getTime() -
        loanDate.getTime();

    const days = Math.max(
        1,
        Math.ceil(
            milliseconds /
            (1000 * 60 * 60 * 24)
        )
    );

    switch (frequency) {
        case 'daily':
            return Math.max(1, days);

        case 'weekly':
            return Math.max(
                1,
                Math.ceil(days / 7)
            );

        case 'biweekly':
            return Math.max(
                1,
                Math.ceil(days / 14)
            );

        case 'monthly':
        default:
            let months =
                (
                    dueDate.getFullYear() -
                    loanDate.getFullYear()
                ) * 12;

            months +=
                dueDate.getMonth() -
                loanDate.getMonth();

            if (
                dueDate.getDate() <
                loanDate.getDate()
            ) {
                months--;
            }

            return Math.max(1, months);
    }
}

function updatePreview() {
    const principal =
        parseFloat(principalInput.value) || 0;

    const interestRate =
        parseFloat(interestInput.value) || 0;

    const interest =
        principal *
        interestRate /
        100;

    const total =
        principal +
        interest +
        existingPenalties;

    previewPrincipal.textContent =
        money(principal);

    previewInterest.textContent =
        money(interest);

    previewTotal.textContent =
        money(total);

    if (loanDateInput.value) {
        const loanDate = new Date(
            loanDateInput.value +
            'T00:00:00'
        );

        previewLoanDate.textContent =
            formatDate(loanDate);
    }

    const dueDate =
        calculateDueDatePreview();

    if (!dueDate) {
        previewDueDate.textContent = 'N/A';
        previewPayments.textContent = '-';
        previewInstallment.textContent = '₱0.00';
        return;
    }

    previewDueDate.textContent =
        formatDate(dueDate);

    const loanDate = new Date(
        loanDateInput.value +
        'T00:00:00'
    );

    const payments =
        paymentTypeInput.value === 'lump_sum'
            ? 1
            : calculatePayments(
                loanDate,
                dueDate,
                frequencyInput.value
            );

    previewPayments.textContent =
        payments;

    const fixedPayment =
        parseFloat(
            fixedPaymentInput.value
        ) || 0;

    let installment = 0;

    if (paymentTypeInput.value === 'lump_sum') {
        installment = total;
    } else if (fixedPayment > 0) {
        installment = fixedPayment;
    } else {
        installment =
            total /
            Math.max(1, payments);
    }

    previewInstallment.textContent =
        money(installment);
}

[
    loanDateInput,
    termValueInput,
    termUnitInput,
    principalInput,
    interestInput,
    frequencyInput,
    paymentTypeInput,
    fixedPaymentInput
].forEach(function (element) {
    if (!element) {
        return;
    }

    element.addEventListener(
        'input',
        updatePreview
    );

    element.addEventListener(
        'change',
        updatePreview
    );
});

updatePreview();
</script>

<style>
@media print {
    .sidebar,
    .btn {
        display: none !important;
    }
}
</style>

</body>
</html>
