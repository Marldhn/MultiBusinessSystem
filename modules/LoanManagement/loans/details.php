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
        COALESCE(SUM(payment_amount), 0) AS total_paid
    FROM loan_payments
    WHERE loan_id = ?
      AND business_id = ?
");

$paymentStmt->execute([
    $loanId,
    $businessId
]);

$totalPaid = (float)$paymentStmt->fetchColumn();

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
| COLLATERAL IMAGE
|--------------------------------------------------------------------------
*/

$imagePath = '';

if (!empty($loan['collateral_image'])) {
    if (strpos($loan['collateral_image'], 'uploads/') === 0) {
        $imagePath = $loan['collateral_image'];
    } else {
        $imagePath = 'uploads/collaterals/' . basename(
            $loan['collateral_image']
        );
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
    const savedTheme=localStorage.getItem('bs-theme')||'light';
    document.documentElement.setAttribute('data-bs-theme',savedTheme);
})();
</script>

<style>
body{font-size:.9rem}
.detail-card{border:0;border-radius:14px;box-shadow:0 .125rem .5rem rgba(0,0,0,.06)}
.detail-label{font-size:.75rem;color:var(--bs-secondary-color);margin-bottom:.2rem}
.detail-value{font-weight:600;color:var(--bs-body-color)}
.loan-header{background:var(--bs-body-bg);border-radius:14px}
.collateral-image{width:100%;max-height:350px;object-fit:contain;border-radius:10px;background:var(--bs-tertiary-bg)}
.financial-number{font-size:1.4rem;font-weight:700}
.schedule-row{border:1px solid var(--bs-border-color);border-radius:10px;padding:12px}
</style>
</head>

<body class="bg-body-tertiary" style="min-height:100vh">

<div class="d-flex flex-column flex-lg-row" style="min-height:100vh">

<?php include __DIR__.'/../../../resources/partials/loansidebar.php'; ?>

<div class="p-3 p-md-4 flex-grow-1 bg-body-tertiary">

<!-- HEADER -->

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

<div>

<div class="mb-2">
<a
    href="index.php?page=loans"
    class="text-decoration-none text-muted small">
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

<div class="d-flex gap-2">

<a
    href="index.php?page=loans"
    class="btn btn-light border fw-semibold">
    <i class="bi bi-arrow-left me-1"></i>
    Back
</a>

<button
    type="button"
    onclick="window.print()"
    class="btn btn-primary fw-semibold">
    <i class="bi bi-printer me-1"></i>
    Print
</button>

</div>

</div>

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
Total Payable
</div>

<div class="financial-number text-primary">
₱<?=number_format($totalPayable,2)?>
</div>

</div>
</div>

</div>

<div class="col-md-6 col-xl-3">

<div class="card detail-card h-100">

<div class="card-body">

<div class="detail-label">
Remaining Balance
</div>

<div class="financial-number text-success">
₱<?=number_format($remainingBalance,2)?>
</div>

<div class="small text-muted">
Paid: ₱<?=number_format($totalPaid,2)?>
</div>

</div>
</div>

</div>

</div>

<div class="row g-4">

<!-- LEFT -->

<div class="col-lg-8">

<!-- BORROWER -->

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

<?php foreach($schedules as $index=>$schedule):

$scheduleStatus=strtolower(
    $schedule['status']??'unpaid'
);

$scheduleAmount=(float)$schedule['amount_due'];

if($scheduleStatus==='paid'){
    $scheduleClass='success';
    $scheduleText='Paid';
}elseif($scheduleStatus==='partially_paid'){
    $scheduleClass='warning';
    $scheduleText='Partially Paid';
}else{
    $scheduleClass='secondary';
    $scheduleText='Unpaid';
}

?>

<div class="schedule-row">

<div class="d-flex justify-content-between align-items-center gap-3">

<div>

<div class="fw-bold">
Installment <?=($index+1)?>
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
₱<?=number_format($scheduleAmount,2)?>
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
    target="_blank">

<img
    src="<?=htmlspecialchars($imagePath)?>"
    alt="Collateral"
    class="collateral-image border">

</a>

<div class="text-center mt-2">

<a
    href="<?=htmlspecialchars($imagePath)?>"
    target="_blank"
    class="small text-decoration-none">

<i class="bi bi-box-arrow-up-right me-1"></i>
Open Full Image

</a>

</div>

<?php else: ?>

<div
    class="bg-body-tertiary border rounded-3 d-flex flex-column align-items-center justify-content-center text-muted"
    style="height:250px">

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
<?=htmlspecialchars($loan['collateral_item'])?>
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
<?=htmlspecialchars($loan['account_name'])?>
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
₱<?=number_format($principalAmount,2)?>
</span>

</div>

<div class="d-flex justify-content-between py-2 border-bottom">

<span class="text-muted">
Interest
</span>

<span class="fw-semibold">
₱<?=number_format($interestAmount,2)?>
</span>

</div>

<div class="d-flex justify-content-between py-2 border-bottom">

<span class="text-muted">
Total Payable
</span>

<span class="fw-semibold text-primary">
₱<?=number_format($totalPayable,2)?>
</span>

</div>

<div class="d-flex justify-content-between py-2 border-bottom">

<span class="text-muted">
Total Paid
</span>

<span class="fw-semibold text-success">
₱<?=number_format($totalPaid,2)?>
</span>

</div>

<div class="d-flex justify-content-between py-3">

<span class="fw-bold">
Remaining
</span>

<span class="fw-bold text-danger">
₱<?=number_format($remainingBalance,2)?>
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
    ?date('M d, Y',strtotime($loan['loan_date']))
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
    ?date('M d, Y',strtotime($loan['due_date']))
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<style>
@media print{
    body{background:#fff!important}
    .sidebar,.btn,a{display:none!important}
    .card{
        box-shadow:none!important;
        border:1px solid #ddd!important;
    }
}
</style>

</body>
</html>