<?php

$pdo = Database::getConnection();

$businessId = $_SESSION['business_id'] ?? null;
$userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

if (!$businessId) {
    header('Location: index.php?page=select_business');
    exit;
}

if (!$userId) {
    header('Location: index.php?page=login');
    exit;
}

$loanId = (int)($_GET['id'] ?? 0);

if ($loanId <= 0) {
    header('Location: index.php?page=loans');
    exit;
}

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| ADD PENALTY
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_penalty') {

    $penaltyType = $_POST['penalty_type'] ?? 'fixed';
    $penaltyValue = (float)($_POST['penalty_value'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $penaltyDate = $_POST['penalty_date'] ?? date('Y-m-d');
    $scheduleId = !empty($_POST['schedule_id'])
        ? (int)$_POST['schedule_id']
        : null;

    if (!in_array($penaltyType, ['fixed', 'percentage'], true)) {
        $error = 'Invalid penalty type.';
    } elseif ($penaltyValue <= 0) {
        $error = 'Penalty amount or percentage must be greater than zero.';
    } elseif (empty($reason)) {
        $error = 'Please enter a reason for the penalty.';
    } else {

        try {

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | FETCH CURRENT LOAN
            |--------------------------------------------------------------------------
            */

            $penaltyLoanStmt = $pdo->prepare("
                SELECT
                    id,
                    principal_amount,
                    total_payable,
                    status,
                    due_date
                FROM loans
                WHERE id = ?
                  AND business_id = ?
                  AND created_by = ?
                LIMIT 1
                FOR UPDATE
            ");

            $penaltyLoanStmt->execute([
                $loanId,
                $businessId,
                $userId
            ]);

            $penaltyLoan = $penaltyLoanStmt->fetch(PDO::FETCH_ASSOC);

            if (!$penaltyLoan) {
                throw new Exception('Loan not found or you do not have permission to modify this loan.');
            }

            /*
            |--------------------------------------------------------------------------
            | CHECK OVERDUE
            |--------------------------------------------------------------------------
            |
            | Penalties are intended for overdue loans.
            |
            */

            $isOverdue = false;

            if (!empty($penaltyLoan['due_date'])) {
                $isOverdue = strtotime($penaltyLoan['due_date']) < strtotime(date('Y-m-d'));
            }

            if (!$isOverdue && strtolower($penaltyLoan['status'] ?? '') !== 'overdue') {
                throw new Exception('A penalty can only be added to an overdue loan.');
            }

            /*
            |--------------------------------------------------------------------------
            | CALCULATE PENALTY
            |--------------------------------------------------------------------------
            */

            $principal = (float)$penaltyLoan['principal_amount'];
            $currentTotalPayable = (float)$penaltyLoan['total_payable'];

            if ($penaltyType === 'percentage') {

                /*
                 * Example:
                 * Principal = ₱10,000
                 * Percentage = 5%
                 * Penalty = ₱500
                 */

                $penaltyAmount = ($principal * $penaltyValue) / 100;

            } else {

                /*
                 * Fixed penalty.
                 */

                $penaltyAmount = $penaltyValue;
            }

            $penaltyAmount = round($penaltyAmount, 2);

            if ($penaltyAmount <= 0) {
                throw new Exception('Calculated penalty amount must be greater than zero.');
            }

            /*
            |--------------------------------------------------------------------------
            | CHECK SCHEDULE
            |--------------------------------------------------------------------------
            */

            if ($scheduleId !== null) {

                $scheduleCheckStmt = $pdo->prepare("
                    SELECT id
                    FROM loan_schedules
                    WHERE id = ?
                      AND loan_id = ?
                      AND business_id = ?
                    LIMIT 1
                ");

                $scheduleCheckStmt->execute([
                    $scheduleId,
                    $loanId,
                    $businessId
                ]);

                if (!$scheduleCheckStmt->fetchColumn()) {
                    throw new Exception('Selected payment schedule does not belong to this loan.');
                }
            }

            /*
            |--------------------------------------------------------------------------
            | ADD PENALTY TO loan_penalties
            |--------------------------------------------------------------------------
            */

            $insertPenaltyStmt = $pdo->prepare("
                INSERT INTO loan_penalties (
                    business_id,
                    loan_id,
                    schedule_id,
                    amount,
                    reason,
                    penalty_date,
                    status,
                    created_by
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, 'unpaid', ?
                )
            ");

            $insertPenaltyStmt->execute([
                $businessId,
                $loanId,
                $scheduleId,
                $penaltyAmount,
                $reason,
                $penaltyDate,
                $userId
            ]);

            /*
            |--------------------------------------------------------------------------
            | AUTOMATICALLY INCREASE TOTAL PAYABLE
            |--------------------------------------------------------------------------
            */

            $newTotalPayable = round(
                $currentTotalPayable + $penaltyAmount,
                2
            );

            $updateLoanStmt = $pdo->prepare("
                UPDATE loans
                SET total_payable = ?
                WHERE id = ?
                  AND business_id = ?
            ");

            $updateLoanStmt->execute([
                $newTotalPayable,
                $loanId,
                $businessId
            ]);

            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $pdo->commit();

            header(
                'Location: index.php?page=loan_details&id=' .
                $loanId .
                '&penalty_added=1'
            );
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGE
|--------------------------------------------------------------------------
*/

if (isset($_GET['penalty_added']) && $_GET['penalty_added'] == '1') {
    $success = 'Penalty added successfully and Total Payable has been updated.';
}

/*
|--------------------------------------------------------------------------
| FETCH LOAN
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        l.*,
        CONCAT(b.first_name, ' ', b.last_name) AS borrower_name,
        b.first_name,
        b.last_name,
        a.account_name,
        a.balance AS account_balance,
        c.id AS collateral_id,
        c.item_name AS collateral_item,
        c.description AS collateral_description,
        c.estimated_value AS collateral_value,
        c.image_path AS collateral_image
    FROM loans l

    INNER JOIN loan_borrowers b
        ON l.borrower_id = b.id
        AND b.business_id = l.business_id

    INNER JOIN loan_accounts a
        ON l.account_id = a.id
        AND a.business_id = l.business_id

    LEFT JOIN loan_collaterals c
        ON l.id = c.loan_id
        AND c.business_id = l.business_id

    WHERE l.id = ?
      AND l.business_id = ?
      AND l.created_by = ?

    LIMIT 1
");

$stmt->execute([
    $loanId,
    $businessId,
    $userId
]);

$loan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$loan) {
    header('Location: index.php?page=loans');
    exit;
}

/*
|--------------------------------------------------------------------------
| FINANCIAL VALUES
|--------------------------------------------------------------------------
*/

$principalAmount = (float)($loan['principal_amount'] ?? 0);
$interestRate = (float)($loan['interest_rate'] ?? 0);
$totalPayable = (float)($loan['total_payable'] ?? 0);

$interestAmount = max(
    0,
    $totalPayable - $principalAmount
);

/*
|--------------------------------------------------------------------------
| PAYMENT INFORMATION
|--------------------------------------------------------------------------
*/

$paymentStmt = $pdo->prepare("
    SELECT
        p.id,
        p.payment_amount,
        p.payment_date,
        p.notes,
        p.schedule_id,
        p.created_at,
        p.created_by
    FROM loan_payments p
    WHERE p.loan_id = ?
      AND p.business_id = ?
    ORDER BY p.payment_date DESC, p.id DESC
");

$paymentStmt->execute([
    $loanId,
    $businessId
]);

$payments = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

$totalPaid = 0;

foreach ($payments as $payment) {
    $totalPaid += (float)$payment['payment_amount'];
}

$totalPaid = round($totalPaid, 2);

$remainingBalance = max(
    0,
    $totalPayable - $totalPaid
);

/*
|--------------------------------------------------------------------------
| PAYMENT SCHEDULE
|--------------------------------------------------------------------------
*/

$scheduleStmt = $pdo->prepare("
    SELECT
        id,
        due_date,
        amount_due,
        status
    FROM loan_schedules
    WHERE loan_id = ?
      AND business_id = ?
    ORDER BY due_date ASC, id ASC
");

$scheduleStmt->execute([
    $loanId,
    $businessId
]);

$schedules = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| PENALTIES
|--------------------------------------------------------------------------
*/

$penaltyStmt = $pdo->prepare("
    SELECT
        id,
        schedule_id,
        amount,
        reason,
        penalty_date,
        status,
        created_by,
        created_at
    FROM loan_penalties
    WHERE loan_id = ?
      AND business_id = ?
    ORDER BY penalty_date DESC, id DESC
");

$penaltyStmt->execute([
    $loanId,
    $businessId
]);

$penalties = $penaltyStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| PENALTY TOTAL
|--------------------------------------------------------------------------
*/

$totalPenalties = 0;

foreach ($penalties as $penalty) {
    if (strtolower($penalty['status'] ?? '') !== 'waived') {
        $totalPenalties += (float)$penalty['amount'];
    }
}

$totalPenalties = round($totalPenalties, 2);

/*
|--------------------------------------------------------------------------
| COLLATERAL IMAGE
|--------------------------------------------------------------------------
*/

$imagePath = '';

if (!empty($loan['collateral_image'])) {

    if (strpos($loan['collateral_image'], 'uploads/') === 0) {
        $imagePath = $loan['collateral_image'];
    } else {
        $imagePath = 'uploads/collaterals/' .
            basename($loan['collateral_image']);
    }
}

/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$status = strtolower($loan['status'] ?? 'unknown');

if ($remainingBalance <= 0) {

    $displayStatus = 'completed';

} elseif (
    !empty($loan['due_date']) &&
    strtotime($loan['due_date']) < strtotime(date('Y-m-d'))
) {

    $displayStatus = 'overdue';

} else {

    $displayStatus = $status;
}

switch ($displayStatus) {

    case 'active':
        $statusClass = 'success';
        $statusText = 'Active';
        break;

    case 'overdue':
        $statusClass = 'danger';
        $statusText = 'Overdue';
        break;

    case 'completed':
    case 'paid':
        $statusClass = 'primary';
        $statusText = 'Paid';
        break;

    case 'cancelled':
        $statusClass = 'dark';
        $statusText = 'Cancelled';
        break;

    case 'pending':
        $statusClass = 'warning';
        $statusText = 'Pending';
        break;

    default:
        $statusClass = 'secondary';
        $statusText = ucfirst($displayStatus);
        break;
}

/*
|--------------------------------------------------------------------------
| FREQUENCY
|--------------------------------------------------------------------------
*/

$frequencyLabels = [
    'daily' => 'Daily',
    'weekly' => 'Weekly',
    'biweekly' => 'Bi-weekly',
    'monthly' => 'Monthly'
];

$paymentFrequency = $frequencyLabels[
    $loan['payment_frequency'] ?? 'monthly'
] ?? 'Monthly';

$fixedPaymentAmount = (float)(
    $loan['fixed_payment_amount'] ?? 0
);

$activePage = 'loans';
$pageTitle = 'Loan Details - Loan Management';

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">

<title><?=htmlspecialchars($pageTitle)?></title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet">

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
    rel="stylesheet">

<script>
(function(){
    const savedTheme = localStorage.getItem('bs-theme') || 'light';
    document.documentElement.setAttribute(
        'data-bs-theme',
        savedTheme
    );
})();
</script>

<style>

body{
    font-size:.9rem;
}

.detail-card{
    border:0;
    border-radius:14px;
    box-shadow:0 .125rem .5rem rgba(0,0,0,.06);
}

.detail-label{
    font-size:.75rem;
    color:var(--bs-secondary-color);
    margin-bottom:.2rem;
}

.detail-value{
    font-weight:600;
    color:var(--bs-body-color);
}

.loan-header{
    background:var(--bs-body-bg);
    border-radius:14px;
}

.collateral-image{
    width:100%;
    max-height:350px;
    object-fit:contain;
    border-radius:10px;
    background:var(--bs-tertiary-bg);
}

.financial-number{
    font-size:1.4rem;
    font-weight:700;
}

.schedule-row{
    border:1px solid var(--bs-border-color);
    border-radius:10px;
    padding:12px;
}

.penalty-row{
    border:1px solid var(--bs-border-color);
    border-radius:12px;
    padding:14px;
}

.penalty-icon{
    width:42px;
    height:42px;
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:rgba(var(--bs-danger-rgb),.1);
    color:var(--bs-danger);
    flex-shrink:0;
}

</style>

</head>

<body class="bg-body-tertiary" style="min-height:100vh">

<div
    class="d-flex flex-column flex-lg-row"
    style="min-height:100vh"
>

<?php include __DIR__.'/../../../resources/partials/loansidebar.php'; ?>

<div class="p-3 p-md-4 flex-grow-1 bg-body-tertiary">

<!-- HEADER -->

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

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

<h2 class="fw-bold text-body mb-1">
Loan Details
</h2>

<p class="text-muted small mb-0">
Complete information for this loan and its collateral.
</p>

</div>

<div class="d-flex gap-2 flex-wrap">

<a
    href="index.php?page=loans"
    class="btn btn-light border fw-semibold"
>

<i class="bi bi-arrow-left me-1"></i>
Back

</a>

<a href="index.php?page=edit_loan&id=<?= $loanId ?>"
   class="btn btn-warning fw-semibold">
    <i class="bi bi-pencil-square me-1"></i>
    Edit Loan
</a>

<?php if ($displayStatus === 'overdue'): ?>

<button
    type="button"
    class="btn btn-danger fw-semibold"
    data-bs-toggle="modal"
    data-bs-target="#addPenaltyModal"
>

<i class="bi bi-exclamation-triangle me-1"></i>
Add Penalty

</button>

<?php endif; ?>

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

<!-- ALERTS -->

<?php if (!empty($error)): ?>

<div class="alert alert-danger alert-dismissible fade show" role="alert">

<i class="bi bi-exclamation-circle me-2"></i>

<?=htmlspecialchars($error)?>

<button
    type="button"
    class="btn-close"
    data-bs-dismiss="alert"
></button>

</div>

<?php endif; ?>

<?php if (!empty($success)): ?>

<div class="alert alert-success alert-dismissible fade show" role="alert">

<i class="bi bi-check-circle me-2"></i>

<?=htmlspecialchars($success)?>

<button
    type="button"
    class="btn-close"
    data-bs-dismiss="alert"
></button>

</div>

<?php endif; ?>

<!-- LOAN HEADER -->

<div class="card detail-card loan-header mb-4">

<div class="card-body p-4">

<div class="d-flex flex-column flex-md-row justify-content-between gap-3">

<div>

<div class="text-muted small mb-1">
Loan Reference
</div>

<h3 class="fw-bold mb-2">

<?=htmlspecialchars(
    $loan['reference_number']
    ?: 'LOAN #'.$loan['id']
)?>

</h3>

<div class="text-muted small">
Loan ID: #<?=htmlspecialchars($loan['id'])?>
</div>

</div>

<div class="text-md-end">

<span class="badge bg-<?=$statusClass?> fs-6 px-3 py-2">

<?=htmlspecialchars($statusText)?>

</span>

</div>

</div>

</div>

</div>

<!-- FINANCIAL SUMMARY -->

<div class="row g-3 mb-4">

<div class="col-md-6 col-xl-3">

<div class="card detail-card h-100">

<div class="card-body">

<div class="detail-label">
Principal Amount
</div>

<div class="financial-number text-body">

₱<?=number_format($principalAmount,2)?>

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

₱<?=number_format($interestAmount,2)?>

</div>

<div class="small text-muted">

<?=number_format($interestRate,2)?>%

</div>

</div>

</div>

</div>

<div class="col-md-6 col-xl-3">

<div class="card detail-card h-100">

<div class="card-body">

<div class="detail-label">
Penalties
</div>

<div class="financial-number text-danger">

₱<?=number_format($totalPenalties,2)?>

</div>

<div class="small text-muted">
Added penalties
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

₱<?=number_format($totalPayable,2)?>

</div>

</div>

</div>

</div>

</div>

<div class="row g-4">

<!-- LEFT -->

<div class="col-lg-8">

<!-- BORROWER -->
<!-- PAYMENT HISTORY -->

<div class="card detail-card mb-4">

    <div class="card-header bg-transparent border-0 px-4 pt-4">

        <div class="d-flex justify-content-between align-items-center gap-2">

            <h5 class="fw-bold mb-0">

                <i class="bi bi-clock-history text-success me-2"></i>

                Payment History

            </h5>

            <span class="badge bg-success-subtle text-success">

                <?=count($payments)?> Payment<?=count($payments) !== 1 ? 's' : ''?>

            </span>

        </div>

    </div>

    <div class="card-body px-4 pb-4">

        <?php if (empty($payments)): ?>

            <div class="text-center py-5 text-muted">

                <i class="bi bi-cash-stack display-5 opacity-50"></i>

                <div class="fw-semibold mt-3">

                    No payments recorded.

                </div>

                <div class="small mt-1">

                    Payment transactions will appear here once a payment is made.

                </div>

            </div>

        <?php else: ?>

            <div class="d-flex flex-column gap-3">

                <?php foreach ($payments as $index => $payment): ?>

                    <?php

                    $paymentAmount = (float)$payment['payment_amount'];

                    $paymentDate = !empty($payment['payment_date'])
                        ? date('M d, Y', strtotime($payment['payment_date']))
                        : 'N/A';

                    $createdAt = !empty($payment['created_at'])
                        ? date('M d, Y h:i A', strtotime($payment['created_at']))
                        : 'N/A';

                    ?>

                    <div class="payment-history-row">

                        <div class="d-flex justify-content-between align-items-start gap-3">

                            <div class="d-flex gap-3">

                                <div class="payment-icon">

                                    <i class="bi bi-check-lg"></i>

                                </div>

                                <div>

                                    <div class="fw-bold">

                                        Payment #<?=count($payments) - $index?>

                                    </div>

                                    <div class="small text-muted mt-1">

                                        <i class="bi bi-calendar3 me-1"></i>

                                        <?=htmlspecialchars($paymentDate)?>

                                    </div>

                                    <?php if (!empty($payment['schedule_id'])): ?>

                                        <div class="small text-muted">

                                            <i class="bi bi-calendar-check me-1"></i>

                                            Installment #<?=htmlspecialchars($payment['schedule_id'])?>

                                        </div>

                                    <?php endif; ?>

                                    <?php if (!empty($payment['notes'])): ?>

                                        <div class="small text-muted mt-2">

                                            <i class="bi bi-chat-left-text me-1"></i>

                                            <?=htmlspecialchars($payment['notes'])?>

                                        </div>

                                    <?php endif; ?>

                                    <div class="small text-muted mt-1">

                                        Recorded:
                                        <?=htmlspecialchars($createdAt)?>

                                    </div>

                                </div>

                            </div>

                            <div class="text-end">

                                <div class="fw-bold text-success fs-5">

                                    ₱<?=number_format($paymentAmount, 2)?>

                                </div>

                                <span class="badge bg-success">

                                    Paid

                                </span>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

            <div class="d-flex justify-content-between align-items-center border-top mt-4 pt-3">

                <span class="fw-bold">

                    Total Payments

                </span>

                <span class="fw-bold text-success fs-5">

                    ₱<?=number_format($totalPaid, 2)?>

                </span>

            </div>

        <?php endif; ?>

    </div>

</div>
<div class="card detail-card mb-4">

<div class="card-header bg-transparent border-0 px-4 pt-4">

<h5 class="fw-bold mb-0">

<i class="bi bi-person-fill text-primary me-2"></i>

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

<?=htmlspecialchars($loan['borrower_name'])?>

</div>

</div>

<div class="col-md-6">

<div class="detail-label">
Contact Number
</div>

<div class="detail-value text-muted">

<i class="bi bi-telephone me-1"></i>
N/A

</div>

</div>

</div>

</div>

</div>

<!-- LOAN INFORMATION -->

<div class="card detail-card mb-4">

<div class="card-header bg-transparent border-0 px-4 pt-4">

<h5 class="fw-bold mb-0">

<i class="bi bi-file-earmark-text-fill text-primary me-2"></i>

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

<i class="bi bi-calendar-event me-1 text-primary"></i>

<?=!empty($loan['loan_date'])
    ?date('M d, Y',strtotime($loan['loan_date']))
    :'N/A'?>

</div>

</div>

<div class="col-md-6">

<div class="detail-label">
Due Date
</div>

<div class="detail-value">

<i class="bi bi-calendar-check me-1 text-danger"></i>

<?=!empty($loan['due_date'])
    ?date('M d, Y',strtotime($loan['due_date']))
    :'N/A'?>

</div>

</div>

<div class="col-md-6">

<div class="detail-label">
Interest Rate
</div>

<div class="detail-value">

<?=number_format($interestRate,2)?>%

</div>

</div>

<div class="col-md-6">

<div class="detail-label">
Payment Frequency
</div>

<div class="detail-value">

<?=htmlspecialchars($paymentFrequency)?>

</div>

</div>

<div class="col-md-6">

<div class="detail-label">
Loan Term
</div>

<div class="detail-value">

<?=htmlspecialchars($loan['term_days'] ?? 0)?>

<?=htmlspecialchars(
    ucfirst($loan['term_unit'] ?? 'days')
)?>

</div>

</div>

<div class="col-md-6">

<div class="detail-label">
Fixed Payment
</div>

<div class="detail-value text-primary">

₱<?=number_format($fixedPaymentAmount,2)?>

</div>

</div>

<div class="col-md-6">

<div class="detail-label">
Status
</div>

<span class="badge bg-<?=$statusClass?>">

<?=htmlspecialchars($statusText)?>

</span>

</div>

</div>

</div>

</div>

<!-- PENALTIES -->

<div class="card detail-card mb-4">

<div class="card-header bg-transparent border-0 px-4 pt-4">

<div class="d-flex justify-content-between align-items-center gap-2">

<h5 class="fw-bold mb-0">

<i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>

Penalties

</h5>

<?php if ($displayStatus === 'overdue'): ?>

<button
    type="button"
    class="btn btn-sm btn-danger"
    data-bs-toggle="modal"
    data-bs-target="#addPenaltyModal"
>

<i class="bi bi-plus-lg me-1"></i>
Add Penalty

</button>

<?php endif; ?>

</div>

</div>

<div class="card-body px-4 pb-4">

<?php if (empty($penalties)): ?>

<div class="text-center py-5 text-muted">

<i class="bi bi-shield-check display-5 opacity-50"></i>

<div class="fw-semibold mt-3">
No penalties added to this loan.
</div>

<div class="small mt-1">
When this loan becomes overdue, you can add a penalty here.
</div>

<?php if ($displayStatus === 'overdue'): ?>

<button
    type="button"
    class="btn btn-danger mt-3"
    data-bs-toggle="modal"
    data-bs-target="#addPenaltyModal"
>

<i class="bi bi-plus-circle me-1"></i>
Create First Penalty

</button>

<?php endif; ?>

</div>

<?php else: ?>

<div class="d-flex flex-column gap-3">

<?php foreach ($penalties as $penalty): ?>

<?php

$penaltyStatus = strtolower(
    $penalty['status'] ?? 'unpaid'
);

if ($penaltyStatus === 'paid') {
    $penaltyBadge = 'success';
    $penaltyStatusText = 'Paid';
} elseif ($penaltyStatus === 'waived') {
    $penaltyBadge = 'secondary';
    $penaltyStatusText = 'Waived';
} else {
    $penaltyBadge = 'danger';
    $penaltyStatusText = 'Unpaid';
}

?>

<div class="penalty-row">

<div class="d-flex justify-content-between align-items-start gap-3">

<div class="d-flex gap-3">

<div class="penalty-icon">

<i class="bi bi-exclamation-lg fs-5"></i>

</div>

<div>

<div class="fw-bold">

<?=htmlspecialchars(
    $penalty['reason'] ?: 'Penalty'
)?>

</div>

<div class="small text-muted mt-1">

<i class="bi bi-calendar3 me-1"></i>

<?=!empty($penalty['penalty_date'])
    ?date(
        'M d, Y',
        strtotime($penalty['penalty_date'])
    )
    :'N/A'?>

</div>

<?php if (!empty($penalty['schedule_id'])): ?>

<div class="small text-muted">

<i class="bi bi-calendar-check me-1"></i>

Schedule #<?=htmlspecialchars($penalty['schedule_id'])?>

</div>

<?php endif; ?>

</div>

</div>

<div class="text-end">

<div class="fw-bold text-danger fs-5">

₱<?=number_format(
    (float)$penalty['amount'],
    2
)?>

</div>

<span class="badge bg-<?=$penaltyBadge?>">

<?=$penaltyStatusText?>

</span>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

<div class="d-flex justify-content-between align-items-center border-top mt-4 pt-3">

<span class="fw-bold">
Total Penalties
</span>

<span class="fw-bold text-danger fs-5">

₱<?=number_format($totalPenalties,2)?>

</span>

</div>

<?php endif; ?>

</div>

</div>

<!-- PAYMENT SCHEDULE -->

<div class="card detail-card mb-4">

<div class="card-header bg-transparent border-0 px-4 pt-4">

<h5 class="fw-bold mb-0">

<i class="bi bi-calendar-check text-primary me-2"></i>

Payment Schedule

</h5>

</div>

<div class="card-body px-4 pb-4">

<?php if(empty($schedules)): ?>

<div class="text-center py-4 text-muted">

<i class="bi bi-calendar-x display-6 opacity-50"></i>

<div class="fw-semibold mt-2">
No payment schedule found.
</div>

</div>

<?php else: ?>

<div class="d-flex flex-column gap-2">

<?php foreach($schedules as $index => $schedule): ?>

<?php

$scheduleStatus = strtolower(
    $schedule['status'] ?? 'unpaid'
);

$scheduleAmount = (float)$schedule['amount_due'];

if ($scheduleStatus === 'paid') {

    $scheduleClass = 'success';
    $scheduleText = 'Paid';

} elseif ($scheduleStatus === 'partially_paid') {

    $scheduleClass = 'warning';
    $scheduleText = 'Partially Paid';

} else {

    $scheduleClass = 'secondary';
    $scheduleText = 'Unpaid';
}

?>

<div class="schedule-row">

<div class="d-flex justify-content-between align-items-center gap-3">

<div>

<div class="fw-bold">

Installment <?=($index + 1)?>

</div>

<div class="small text-muted">

Due:

<?=date(
    'M d, Y',
    strtotime($schedule['due_date'])
)?>

</div>

</div>

<div class="text-end">

<div class="fw-bold">

₱<?=number_format(
    $scheduleAmount,
    2
)?>

</div>

<span class="badge bg-<?=$scheduleClass?>">

<?=$scheduleText?>

</span>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>

</div>

<!-- COLLATERAL -->

<div class="card detail-card mb-4">

<div class="card-header bg-transparent border-0 px-4 pt-4">

<h5 class="fw-bold mb-0">

<i class="bi bi-shield-fill-check text-success me-2"></i>

Collateral

</h5>

</div>

<div class="card-body px-4 pb-4">

<?php if(!empty($loan['collateral_item'])): ?>

<div class="row g-4">

<div class="col-md-5">

<?php if(!empty($imagePath)): ?>

<a
    href="<?=htmlspecialchars($imagePath)?>"
    target="_blank"
>

<img
    src="<?=htmlspecialchars($imagePath)?>"
    alt="Collateral"
    class="collateral-image border"
>

</a>

<div class="text-center mt-2">

<a
    href="<?=htmlspecialchars($imagePath)?>"
    target="_blank"
    class="small text-decoration-none"
>

<i class="bi bi-box-arrow-up-right me-1"></i>

Open Full Image

</a>

</div>

<?php else: ?>

<div
    class="bg-body-tertiary border rounded-3 d-flex flex-column align-items-center justify-content-center text-muted"
    style="height:250px"
>

<i class="bi bi-image fs-1 mb-2"></i>

<span class="small">
No collateral photo
</span>

</div>

<?php endif; ?>

</div>

<div class="col-md-7">

<div class="mb-4">

<div class="detail-label">
Item Name
</div>

<div class="fs-5 fw-bold">

<?=htmlspecialchars(
    $loan['collateral_item']
)?>

</div>

</div>

<div class="mb-4">

<div class="detail-label">
Estimated Value
</div>

<div class="fs-4 fw-bold text-success">

₱<?=number_format(
    (float)$loan['collateral_value'],
    2
)?>

</div>

</div>

<div>

<div class="detail-label">
Description / Specifications
</div>

<div class="bg-body-tertiary border rounded-3 p-3 small">

<?php if(!empty($loan['collateral_description'])): ?>

<?=nl2br(
    htmlspecialchars(
        $loan['collateral_description']
    )
)?>

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

<i class="bi bi-shield-x display-5 opacity-50"></i>

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

<!-- RIGHT -->

<div class="col-lg-4">

<!-- FUNDING ACCOUNT -->

<div class="card detail-card mb-4">

<div class="card-header bg-transparent border-0 px-4 pt-4">

<h5 class="fw-bold mb-0">

<i class="bi bi-wallet2 text-primary me-2"></i>

Funding Account

</h5>

</div>

<div class="card-body px-4 pb-4">

<div class="mb-3">

<div class="detail-label">
Account
</div>

<div class="detail-value fs-5">

<?=htmlspecialchars(
    $loan['account_name']
)?>

</div>

</div>

<div>

<div class="detail-label">
Current Account Balance
</div>

<div class="fw-bold text-primary fs-4">

₱<?=number_format(
    (float)$loan['account_balance'],
    2
)?>

</div>

</div>

</div>

</div>

<!-- PAYMENT SUMMARY -->

<div class="card detail-card mb-4">

<div class="card-header bg-transparent border-0 px-4 pt-4">

<h5 class="fw-bold mb-0">

<i class="bi bi-cash-stack text-success me-2"></i>

Payment Summary

</h5>

</div>

<div class="card-body px-4 pb-4">

<div class="d-flex justify-content-between py-2 border-bottom">

<span class="text-muted">
Principal
</span>

<span class="fw-semibold">

₱<?=number_format(
    $principalAmount,
    2
)?>

</span>

</div>

<div class="d-flex justify-content-between py-2 border-bottom">

<span class="text-muted">
Interest
</span>

<span class="fw-semibold">

₱<?=number_format(
    $interestAmount,
    2
)?>

</span>

</div>

<div class="d-flex justify-content-between py-2 border-bottom">

<span class="text-muted">
Penalties
</span>

<span class="fw-semibold text-danger">

₱<?=number_format(
    $totalPenalties,
    2
)?>

</span>

</div>

<div class="d-flex justify-content-between py-2 border-bottom">

<span class="text-muted">
Total Payable
</span>

<span class="fw-semibold text-primary">

₱<?=number_format(
    $totalPayable,
    2
)?>

</span>

</div>

<div class="d-flex justify-content-between py-2 border-bottom">

<span class="text-muted">
Total Paid
</span>

<span class="fw-semibold text-success">

₱<?=number_format(
    $totalPaid,
    2
)?>

</span>

</div>

<div class="d-flex justify-content-between py-3">

<span class="fw-bold">
Remaining
</span>

<span class="fw-bold text-danger">

₱<?=number_format(
    $remainingBalance,
    2
)?>

</span>

</div>

</div>

</div>

<!-- IMPORTANT DATES -->

<div class="card detail-card mb-4">

<div class="card-header bg-transparent border-0 px-4 pt-4">

<h5 class="fw-bold mb-0">

<i class="bi bi-calendar3 text-primary me-2"></i>

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

<?=!empty($loan['loan_date'])
    ?date(
        'M d, Y',
        strtotime($loan['loan_date'])
    )
    :'N/A'?>

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

<?=!empty($loan['due_date'])
    ?date(
        'M d, Y',
        strtotime($loan['due_date'])
    )
    :'N/A'?>

</div>

</div>

</div>

</div>

</div>

</div>

</div>

</div>

</div>

<!-- ADD PENALTY MODAL -->

<div
    class="modal fade"
    id="addPenaltyModal"
    tabindex="-1"
    aria-hidden="true"
>

<div class="modal-dialog modal-dialog-centered">

<div class="modal-content border-0 shadow-lg">

<div class="modal-header">

<div>

<h5 class="modal-title fw-bold">

<i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>

Add Penalty

</h5>

<div class="small text-muted mt-1">

Loan:
<?=htmlspecialchars(
    $loan['reference_number']
    ?: 'LOAN #'.$loan['id']
)?>

</div>

</div>

<button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal"
></button>

</div>

<form method="POST">

<div class="modal-body">

<input
    type="hidden"
    name="action"
    value="add_penalty"
>

<!-- PENALTY TYPE -->

<div class="mb-3">

<label class="form-label fw-semibold">
Penalty Type
</label>

<div class="row g-2">

<div class="col-6">

<input
    type="radio"
    class="btn-check"
    name="penalty_type"
    id="penaltyFixed"
    value="fixed"
    checked
>

<label
    class="btn btn-outline-danger w-100 py-3"
    for="penaltyFixed"
>

<i class="bi bi-cash-stack d-block fs-4 mb-1"></i>

Fixed Amount

</label>

</div>

<div class="col-6">

<input
    type="radio"
    class="btn-check"
    name="penalty_type"
    id="penaltyPercentage"
    value="percentage"
>

<label
    class="btn btn-outline-danger w-100 py-3"
    for="penaltyPercentage"
>

<i class="bi bi-percent d-block fs-4 mb-1"></i>

Percentage

</label>

</div>

</div>

</div>

<!-- PENALTY VALUE -->

<div class="mb-3">

<label
    for="penaltyValue"
    class="form-label fw-semibold"
>

<span id="penaltyValueLabel">
Penalty Amount
</span>

</label>

<div class="input-group">

<span
    class="input-group-text"
    id="penaltyPrefix"
>
₱
</span>

<input
    type="number"
    name="penalty_value"
    id="penaltyValue"
    class="form-control"
    min="0.01"
    step="0.01"
    placeholder="0.00"
    required
>

<span
    class="input-group-text d-none"
    id="penaltySuffix"
>
%
</span>

</div>

<div class="form-text">

For percentage, the calculation is based on the
loan principal of
<strong>
₱<?=number_format($principalAmount,2)?>
</strong>.

</div>

</div>

<!-- CALCULATED AMOUNT -->

<div
    class="alert alert-danger d-none"
    id="penaltyCalculation"
>

<div class="d-flex justify-content-between">

<span>
Calculated Penalty
</span>

<strong id="calculatedPenalty">
₱0.00
</strong>

</div>

<div class="small mt-1">
This amount will be added to Total Payable.
</div>

</div>

<!-- SCHEDULE -->

<div class="mb-3">

<label
    for="scheduleId"
    class="form-label fw-semibold"
>

Payment Schedule
<span class="text-muted fw-normal">
(optional)
</span>

</label>

<select
    name="schedule_id"
    id="scheduleId"
    class="form-select"
>

<option value="">
General Loan Penalty
</option>

<?php foreach ($schedules as $index => $schedule): ?>

<option value="<?=htmlspecialchars($schedule['id'])?>">

Installment <?=($index + 1)?>
-
<?=date(
    'M d, Y',
    strtotime($schedule['due_date'])
)?>
-
₱<?=number_format(
    (float)$schedule['amount_due'],
    2
)?>

</option>

<?php endforeach; ?>

</select>

</div>

<!-- DATE -->

<div class="mb-3">

<label
    for="penaltyDate"
    class="form-label fw-semibold"
>

Penalty Date

</label>

<input
    type="date"
    name="penalty_date"
    id="penaltyDate"
    class="form-control"
    value="<?=date('Y-m-d')?>"
    required
>

</div>

<!-- REASON -->

<div class="mb-3">

<label
    for="penaltyReason"
    class="form-label fw-semibold"
>

Reason

</label>

<textarea
    name="reason"
    id="penaltyReason"
    class="form-control"
    rows="3"
    placeholder="Example: Late payment penalty"
    required
></textarea>

</div>

<div class="alert alert-warning small mb-0">

<i class="bi bi-info-circle me-1"></i>

The penalty will be inserted as
<strong>Unpaid</strong> and will immediately increase
the loan's <strong>Total Payable</strong>.

</div>

</div>

<div class="modal-footer">

<button
    type="button"
    class="btn btn-light border"
    data-bs-dismiss="modal"
>

Cancel

</button>

<button
    type="submit"
    class="btn btn-danger fw-semibold"
>

<i class="bi bi-plus-circle me-1"></i>

Add Penalty

</button>

</div>

</form>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>

/*
|--------------------------------------------------------------------------
| PENALTY CALCULATOR
|--------------------------------------------------------------------------
*/

const penaltyFixed = document.getElementById('penaltyFixed');
const penaltyPercentage = document.getElementById('penaltyPercentage');
const penaltyValue = document.getElementById('penaltyValue');

const penaltyValueLabel =
    document.getElementById('penaltyValueLabel');

const penaltyPrefix =
    document.getElementById('penaltyPrefix');

const penaltySuffix =
    document.getElementById('penaltySuffix');

const penaltyCalculation =
    document.getElementById('penaltyCalculation');

const calculatedPenalty =
    document.getElementById('calculatedPenalty');

const principalAmount =
    <?=json_encode($principalAmount)?>;


function updatePenaltyUI() {

    const isPercentage =
        penaltyPercentage.checked;

    if (isPercentage) {

        penaltyValueLabel.textContent =
            'Penalty Percentage';

        penaltyPrefix.classList.add('d-none');

        penaltySuffix.classList.remove('d-none');

        penaltyValue.placeholder = '5.00';

    } else {

        penaltyValueLabel.textContent =
            'Penalty Amount';

        penaltyPrefix.classList.remove('d-none');

        penaltySuffix.classList.add('d-none');

        penaltyValue.placeholder = '500.00';
    }

    calculatePenalty();
}


function calculatePenalty() {

    const value =
        parseFloat(penaltyValue.value) || 0;

    let amount = 0;

    if (penaltyPercentage.checked) {

        amount =
            (principalAmount * value) / 100;

    } else {

        amount = value;
    }

    amount =
        Math.round(amount * 100) / 100;

    if (value > 0 && amount > 0) {

        calculatedPenalty.textContent =
            '₱' +
            amount.toLocaleString(
                'en-PH',
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );

        penaltyCalculation.classList.remove(
            'd-none'
        );

    } else {

        penaltyCalculation.classList.add(
            'd-none'
        );
    }
}


penaltyFixed.addEventListener(
    'change',
    updatePenaltyUI
);

penaltyPercentage.addEventListener(
    'change',
    updatePenaltyUI
);

penaltyValue.addEventListener(
    'input',
    calculatePenalty
);

updatePenaltyUI();

</script>

<style>

@media print {

    body{
        background:#fff!important;
    }

    .sidebar,
    .btn,
    a{
        display:none!important;
    }

    .card{
        box-shadow:none!important;
        border:1px solid #ddd!important;
    }

}

.penalty-row{
    border:1px solid var(--bs-border-color);
    border-radius:12px;
    padding:14px;
}

.payment-history-row{
    border:1px solid var(--bs-border-color);
    border-radius:12px;
    padding:14px;
}

.payment-icon{
    width:42px;
    height:42px;
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:rgba(var(--bs-success-rgb),.1);
    color:var(--bs-success);
    flex-shrink:0;
}

</style>

</body>

</html>