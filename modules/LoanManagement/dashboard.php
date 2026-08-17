<?php
$pdo = Database::getConnection();

$businessId = $_SESSION['business_id'] ?? null;
$userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

if (!$businessId || !$userId) {
    header('Location: index.php?page=select_business');
    exit;
}

$businessId = (int)$businessId;
$userId = (int)$userId;

$upcomingDays = isset($_GET['upcoming_days']) ? (int)$_GET['upcoming_days'] : 15;

if (!in_array($upcomingDays, [15, 30], true)) {
    $upcomingDays = 15;
}


/*
|--------------------------------------------------------------------------
| ACTIVE LOANS
|--------------------------------------------------------------------------
| Only loans created by the currently logged-in user
| inside the currently selected business.
*/

$activeLoansStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_active,
        COALESCE(SUM(total_payable), 0) AS total_amount
    FROM loans
    WHERE business_id = ?
    AND created_by = ?
    AND status = 'active'
");

$activeLoansStmt->execute([
    $businessId,
    $userId
]);

$activeLoansData = $activeLoansStmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| BORROWERS
|--------------------------------------------------------------------------
| Only borrowers created by the currently logged-in user.
*/

$borrowersStmt = $pdo->prepare("
    SELECT COUNT(*) AS total_borrowers
    FROM loan_borrowers
    WHERE business_id = ?
    AND created_by = ?
");

$borrowersStmt->execute([
    $businessId,
    $userId
]);

$borrowersData = $borrowersStmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| ACCOUNTS
|--------------------------------------------------------------------------
| Only accounts created by the currently logged-in user.
*/

$accountsStmt = $pdo->prepare("
    SELECT COALESCE(SUM(balance), 0) AS total_funds
    FROM loan_accounts
    WHERE business_id = ?
    AND created_by = ?
");

$accountsStmt->execute([
    $businessId,
    $userId
]);

$accountsData = $accountsStmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| RECENT ACCOUNT TRANSACTIONS
|--------------------------------------------------------------------------
| Only transactions created by the currently logged-in user.
|
| The account is also checked against the current business/user.
*/

$txStmt = $pdo->prepare("
    SELECT t.*
    FROM loan_account_transactions t
    INNER JOIN loan_accounts a
        ON a.id = t.account_id
        AND a.business_id = t.business_id
        AND a.created_by = ?
    WHERE t.business_id = ?
    AND t.created_by = ?
    ORDER BY t.created_at DESC
    LIMIT 5
");

$txStmt->execute([
    $userId,
    $businessId,
    $userId
]);

$recentTransactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| UPCOMING PAYMENTS
|--------------------------------------------------------------------------
| Only schedules belonging to loans created by the current user.
|
| We filter l.created_by instead of s.created_by because the payment
| schedule belongs to the user's loan.
*/

$upcomingPaymentsStmt = $pdo->prepare("
    SELECT
        s.id AS schedule_id,
        s.loan_id,
        s.due_date,
        s.amount_due,
        s.status AS schedule_status,
        l.reference_number,
        l.status AS loan_status,
        CONCAT(b.first_name, ' ', b.last_name) AS borrower_name
    FROM loan_schedules s
    INNER JOIN loans l
        ON s.loan_id = l.id
        AND l.business_id = s.business_id
        AND l.created_by = ?
    INNER JOIN loan_borrowers b
        ON l.borrower_id = b.id
        AND b.business_id = l.business_id
        AND b.created_by = ?
    WHERE l.business_id = ?
    AND l.created_by = ?
    AND l.status = 'active'
    AND s.status IN ('unpaid', 'partially_paid')
    AND s.due_date >= CURDATE()
    AND s.due_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
    ORDER BY s.due_date ASC
    LIMIT 20
");

$upcomingPaymentsStmt->execute([
    $userId,
    $userId,
    $businessId,
    $userId,
    $upcomingDays
]);

$upcomingPayments = $upcomingPaymentsStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| OVERDUE PAYMENTS
|--------------------------------------------------------------------------
| Only overdue schedules belonging to the current user's loans.
*/

$overduePaymentsStmt = $pdo->prepare("
    SELECT
        s.id AS schedule_id,
        s.loan_id,
        s.due_date,
        s.amount_due,
        s.status AS schedule_status,
        l.reference_number,
        l.status AS loan_status,
        CONCAT(b.first_name, ' ', b.last_name) AS borrower_name
    FROM loan_schedules s
    INNER JOIN loans l
        ON s.loan_id = l.id
        AND l.business_id = s.business_id
        AND l.created_by = ?
    INNER JOIN loan_borrowers b
        ON l.borrower_id = b.id
        AND b.business_id = l.business_id
        AND b.created_by = ?
    WHERE l.business_id = ?
    AND l.created_by = ?
    AND l.status IN ('active', 'overdue')
    AND s.status IN ('unpaid', 'partially_paid', 'overdue')
    AND s.due_date < CURDATE()
    ORDER BY s.due_date ASC
    LIMIT 20
");

$overduePaymentsStmt->execute([
    $userId,
    $userId,
    $businessId,
    $userId
]);

$overduePayments = $overduePaymentsStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| TOTALS
|--------------------------------------------------------------------------
*/

$upcomingTotal = array_sum(
    array_column($upcomingPayments, 'amount_due')
);

$overdueTotal = array_sum(
    array_column($overduePayments, 'amount_due')
);


$activePage = 'dashboard';
$pageTitle = 'Dashboard - Loan Management';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">

<title><?= htmlspecialchars($pageTitle) ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<script>
(function () {
    const theme = localStorage.getItem('bs-theme') || 'light';
    document.documentElement.setAttribute('data-bs-theme', theme);
})();
</script>

<style>
body{min-height:100vh;overflow-x:hidden}
.dashboard-main{min-width:0;width:100%}
.dashboard-header{padding-bottom:4px}
.metric-card{border:0;border-radius:16px;transition:transform .2s ease,box-shadow .2s ease}
.metric-card:hover{transform:translateY(-2px)}
.metric-icon{width:48px;height:48px;flex-shrink:0;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.25rem}

.payment-card{border:0;border-radius:16px;overflow:hidden}
.payment-card-header{padding:20px 24px}
.payment-table th,.table th{font-size:.72rem;letter-spacing:.04em;white-space:nowrap}
.payment-table td,.table td{padding-top:14px;padding-bottom:14px}
.payment-date{font-weight:700}
.due-soon-badge{font-size:.72rem}
.overdue-card{border-left:4px solid var(--bs-danger)!important}
.upcoming-card{border-left:4px solid var(--bs-primary)!important}

.payment-mobile{display:none}
.transaction-card{border:0;border-radius:16px;overflow:hidden}
.transaction-mobile{display:none}
.empty-state{padding:50px 20px}

.mobile-payment{
    border:1px solid var(--bs-border-color);
    border-radius:14px;
    padding:15px;
    margin-bottom:10px;
    background:var(--bs-body-bg);
}

.mobile-payment:last-child{margin-bottom:0}

.mobile-payment-top{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    margin-bottom:12px;
}

.mobile-payment-borrower{
    font-size:.9rem;
    font-weight:700;
    line-height:1.35;
    word-break:break-word;
}

.mobile-payment-reference{
    font-size:.7rem;
    color:var(--bs-secondary-color);
    margin-top:3px;
}

.mobile-payment-date{
    font-size:.76rem;
    font-weight:600;
    margin-top:10px;
}

.mobile-payment-info{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:8px;
    padding-top:12px;
    border-top:1px solid var(--bs-border-color);
}

.mobile-payment-info-item{min-width:0}

.mobile-payment-label{
    display:block;
    font-size:.65rem;
    color:var(--bs-secondary-color);
    text-transform:uppercase;
    letter-spacing:.04em;
    margin-bottom:3px;
}

.mobile-payment-value{
    font-size:.82rem;
    font-weight:600;
    word-break:break-word;
}

.mobile-payment-amount{
    font-size:1rem;
    font-weight:700;
    text-align:right;
    white-space:nowrap;
}

.mobile-transaction{
    border:1px solid var(--bs-border-color);
    border-radius:12px;
    padding:13px;
    margin-bottom:10px;
    background:var(--bs-body-bg);
}

.mobile-transaction:last-child{margin-bottom:0}

.mobile-transaction-top{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:10px;
    margin-bottom:9px;
}

.mobile-transaction-description{
    font-size:.82rem;
    font-weight:600;
    line-height:1.35;
    word-break:break-word;
}

.mobile-transaction-date{
    font-size:.68rem;
    color:var(--bs-secondary-color);
}

.mobile-transaction-bottom{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    padding-top:9px;
    border-top:1px solid var(--bs-border-color);
}

.mobile-transaction-amount{
    font-size:.9rem;
    font-weight:700;
    white-space:nowrap;
}

@media(max-width:991.98px){
    .dashboard-main{width:100%}
}

@media(max-width:767.98px){
    .payment-table-wrapper{display:none}
    .payment-mobile{display:block}

    .payment-card-header{padding:17px}

    .payment-card-header h5{font-size:1rem!important}
    .payment-card-header p{font-size:.72rem}

    .payment-filter{width:100%}

    .payment-filter .btn-group{width:100%}
    .payment-filter .btn{flex:1}

    .transaction-table{display:none}
    .transaction-mobile{display:block;padding:0 12px 12px}

    .transaction-header{padding:16px!important}
    .transaction-header h5{font-size:.95rem!important}
}

@media(max-width:575.98px){
    .dashboard-main{padding:0!important}

    .dashboard-main>.p-3,
    .dashboard-main>.p-md-4{
        padding:14px!important;
    }

    .dashboard-header{margin-bottom:16px!important}
    .dashboard-header h2{font-size:1.35rem}
    .dashboard-header p{font-size:.78rem;line-height:1.4}

    .issue-loan-btn{
        width:100%;
        justify-content:center;
    }

    .metric-row{
        --bs-gutter-x:10px;
        --bs-gutter-y:10px;
    }

    .metric-card{border-radius:14px}
    .metric-card .card-body{padding:16px}

    .metric-icon{
        width:42px;
        height:42px;
        border-radius:12px;
        font-size:1.1rem;
    }

    .metric-label{
        font-size:.68rem!important;
        margin-bottom:4px!important;
    }

    .metric-value{font-size:1.25rem!important}
    .metric-description{font-size:.68rem!important}

    .payment-card{border-radius:14px}
    .payment-card-header{padding:15px!important}
    .payment-card-header h5{font-size:.95rem!important}

    .payment-card-header .d-flex{width:100%}
    .payment-card-header .btn-group{width:100%}
    .payment-card-header .btn{flex:1}

    .mobile-payment{padding:13px}
    .mobile-payment-top{gap:8px}
    .mobile-payment-borrower{font-size:.84rem}
    .mobile-payment-reference{font-size:.66rem}
    .mobile-payment-date{font-size:.72rem}

    .mobile-payment-info{grid-template-columns:1fr 1fr}
    .mobile-payment-label{font-size:.6rem}
    .mobile-payment-value{font-size:.76rem}
    .mobile-payment-amount{font-size:.9rem}

    .transaction-card{border-radius:14px}
    .transaction-header{padding:16px!important}
    .transaction-header h5{font-size:.95rem!important}
    .transaction-mobile{padding:0 12px 12px}

    .empty-state{padding:35px 15px}
}
</style>
</head>

<body class="bg-body-tertiary">

<div class="d-flex flex-column flex-lg-row min-vh-100">

<?php include __DIR__ . '/../../resources/partials/loansidebar.php'; ?>

<main class="dashboard-main flex-grow-1 bg-body-tertiary">

<div class="p-3 p-md-4">

<!-- HEADER -->
<div class="dashboard-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

<div>

<div class="d-flex align-items-center gap-2 mb-1">

<div class="d-lg-none bg-primary bg-opacity-10 text-primary rounded-3 p-2">
<i class="bi bi-speedometer2"></i>
</div>

<h2 class="fw-bold text-body mb-0">Dashboard</h2>

</div>

<p class="text-muted small mb-0">
Welcome back. Managing
<span class="fw-semibold text-primary">
<?= htmlspecialchars($_SESSION['business_name'] ?? 'Business') ?>
</span>
</p>

</div>

<a href="index.php?page=loans"
class="issue-loan-btn btn btn-primary fw-bold px-3 py-2 rounded-3 shadow-sm d-flex align-items-center gap-2 text-nowrap">

<i class="bi bi-plus-lg"></i>
<span>Issue New Loan</span>

</a>

</div>

<!-- METRICS -->
<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 metric-row g-3 mb-4">

<div class="col">
<div class="card metric-card shadow-sm bg-body h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start gap-3">

<div>

<div class="metric-label text-muted fw-bold mb-2">
ACTIVE LOANS
</div>

<div class="metric-value fw-bold text-primary fs-3">
<?= number_format($activeLoansData['total_active']) ?>
</div>

<div class="metric-description text-muted mt-1">
Total value:
<span class="fw-semibold text-body">
₱<?= number_format($activeLoansData['total_amount'], 2) ?>
</span>
</div>

</div>

<div class="metric-icon bg-primary bg-opacity-10 text-primary">
<i class="bi bi-file-earmark-text"></i>
</div>

</div>

</div>
</div>
</div>

<div class="col">
<div class="card metric-card shadow-sm bg-body h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start gap-3">

<div>

<div class="metric-label text-muted fw-bold mb-2">
AVAILABLE FUNDS
</div>

<div class="metric-value fw-bold text-success fs-3 text-truncate">
₱<?= number_format($accountsData['total_funds'], 2) ?>
</div>

<div class="metric-description text-muted mt-1">
Across your accounts
</div>

</div>

<div class="metric-icon bg-success bg-opacity-10 text-success">
<i class="bi bi-wallet2"></i>
</div>

</div>

</div>
</div>
</div>

<div class="col">
<div class="card metric-card shadow-sm bg-body h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start gap-3">

<div>

<div class="metric-label text-muted fw-bold mb-2">
TOTAL BORROWERS
</div>

<div class="metric-value fw-bold text-warning fs-3">
<?= number_format($borrowersData['total_borrowers']) ?>
</div>

<div class="metric-description text-muted mt-1">
Your registered clients
</div>

</div>

<div class="metric-icon bg-warning bg-opacity-10 text-warning">
<i class="bi bi-people"></i>
</div>

</div>

</div>
</div>
</div>

</div>

<!-- UPCOMING PAYMENTS -->
<div class="card payment-card upcoming-card shadow-sm bg-body mb-4">

<div class="payment-card-header">

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

<div>

<div class="d-flex align-items-center gap-2">

<div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2">
<i class="bi bi-calendar-check"></i>
</div>

<div>

<h5 class="fw-bold mb-1 text-body">
Upcoming Payments
</h5>

<p class="text-muted small mb-0">
Your loans due within the selected period
</p>

</div>

</div>

</div>

<div class="payment-filter d-flex align-items-center gap-2">

<span class="small text-muted text-nowrap">
Show next:
</span>

<div class="btn-group">

<a href="index.php?page=dashboard&upcoming_days=15"
class="btn btn-sm <?= $upcomingDays === 15 ? 'btn-primary' : 'btn-outline-primary' ?> fw-semibold">
15 Days
</a>

<a href="index.php?page=dashboard&upcoming_days=30"
class="btn btn-sm <?= $upcomingDays === 30 ? 'btn-primary' : 'btn-outline-primary' ?> fw-semibold">
30 Days
</a>

</div>

</div>

</div>

<div class="mt-3">

<span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2">
<i class="bi bi-cash-stack me-1"></i>
<?= count($upcomingPayments) ?> payment(s)
</span>

<span class="badge bg-success bg-opacity-10 text-success px-3 py-2 ms-1">
₱<?= number_format($upcomingTotal, 2) ?> expected
</span>

</div>

</div>

<!-- DESKTOP TABLE -->
<div class="payment-table-wrapper">

<table class="table payment-table table-hover align-middle mb-0">

<thead class="table-light text-uppercase text-muted">

<tr>
<th class="py-3 ps-4">Payment Date</th>
<th class="py-3">Borrower</th>
<th class="py-3">Loan Reference</th>
<th class="py-3 text-end">Amount Due</th>
<th class="py-3 text-center">Status</th>
<th class="py-3 text-end pe-4">Days</th>
</tr>

</thead>

<tbody>

<?php if (empty($upcomingPayments)): ?>

<tr>
<td colspan="6">

<div class="empty-state text-center text-muted">

<div class="mb-3">
<i class="bi bi-calendar2-check display-6 opacity-50"></i>
</div>

<div class="fw-semibold mb-1">
No upcoming payments
</div>

<div class="small">
There are no unpaid scheduled payments within the next <?= $upcomingDays ?> days.
</div>

</div>

</td>
</tr>

<?php else: ?>

<?php foreach ($upcomingPayments as $payment):

$dueDate = new DateTime($payment['due_date']);
$today = new DateTime(date('Y-m-d'));
$daysRemaining = (int) $today->diff($dueDate)->format('%r%a');

?>

<tr>

<td class="ps-4">

<div class="payment-date text-body">

<i class="bi bi-calendar-event text-primary me-1"></i>

<?= date('M d, Y', strtotime($payment['due_date'])) ?>

</div>

</td>

<td>

<div class="fw-bold text-body">

<?= htmlspecialchars($payment['borrower_name']) ?>

</div>

</td>

<td>

<span class="badge bg-secondary bg-opacity-10 text-body fw-semibold">

<?= htmlspecialchars($payment['reference_number'] ?? '#' . $payment['loan_id']) ?>

</span>

</td>

<td class="text-end">

<span class="fw-bold text-body">

₱<?= number_format($payment['amount_due'], 2) ?>

</span>

</td>

<td class="text-center">

<?php if ($daysRemaining === 0): ?>

<span class="badge bg-warning bg-opacity-10 text-warning due-soon-badge">
<i class="bi bi-exclamation-circle me-1"></i>
Due Today
</span>

<?php elseif ($daysRemaining <= 3): ?>

<span class="badge bg-warning bg-opacity-10 text-warning due-soon-badge">
<i class="bi bi-clock me-1"></i>
Due Soon
</span>

<?php else: ?>

<span class="badge bg-primary bg-opacity-10 text-primary due-soon-badge">
Upcoming
</span>

<?php endif; ?>

</td>

<td class="text-end pe-4">

<?php if ($daysRemaining === 0): ?>

<span class="fw-bold text-warning">
Today
</span>

<?php else: ?>

<span class="text-muted small">
<?= $daysRemaining ?> day(s)
</span>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

</tbody>
</table>

</div>

<!-- MOBILE -->
<div class="payment-mobile">

<?php if (empty($upcomingPayments)): ?>

<div class="empty-state text-center text-muted">

<div class="mb-3">
<i class="bi bi-calendar2-check display-6 opacity-50"></i>
</div>

<div class="fw-semibold mb-1">
No upcoming payments
</div>

<div class="small">
There are no unpaid scheduled payments within the next <?= $upcomingDays ?> days.
</div>

</div>

<?php else: ?>

<?php foreach ($upcomingPayments as $payment):

$dueDate = new DateTime($payment['due_date']);
$today = new DateTime(date('Y-m-d'));
$daysRemaining = (int) $today->diff($dueDate)->format('%r%a');

?>

<div class="mobile-payment">

<div class="mobile-payment-top">

<div class="flex-grow-1 min-width-0">

<div class="mobile-payment-borrower">
<?= htmlspecialchars($payment['borrower_name']) ?>
</div>

<div class="mobile-payment-reference">
<?= htmlspecialchars($payment['reference_number'] ?? '#' . $payment['loan_id']) ?>
</div>

<div class="mobile-payment-date text-primary">

<i class="bi bi-calendar-event me-1"></i>

<?= date('M d, Y', strtotime($payment['due_date'])) ?>

</div>

</div>

<div class="mobile-payment-amount text-body">
₱<?= number_format($payment['amount_due'], 2) ?>
</div>

</div>

<div class="mobile-payment-info">

<div class="mobile-payment-info-item">

<span class="mobile-payment-label">
Status
</span>

<?php if ($daysRemaining === 0): ?>

<span class="badge bg-warning bg-opacity-10 text-warning">
Due Today
</span>

<?php elseif ($daysRemaining <= 3): ?>

<span class="badge bg-warning bg-opacity-10 text-warning">
Due Soon
</span>

<?php else: ?>

<span class="badge bg-primary bg-opacity-10 text-primary">
Upcoming
</span>

<?php endif; ?>

</div>

<div class="mobile-payment-info-item text-end">

<span class="mobile-payment-label">
Days
</span>

<?php if ($daysRemaining === 0): ?>

<span class="mobile-payment-value text-warning">
Today
</span>

<?php else: ?>

<span class="mobile-payment-value">
<?= $daysRemaining ?> day(s)
</span>

<?php endif; ?>

</div>

</div>

</div>

<?php endforeach; ?>

<?php endif; ?>

</div>

</div>

<!-- OVERDUE PAYMENTS -->
<div class="card payment-card overdue-card shadow-sm bg-body mb-4">

<div class="payment-card-header">

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

<div>

<div class="d-flex align-items-center gap-2">

<div class="bg-danger bg-opacity-10 text-danger rounded-3 p-2">
<i class="bi bi-exclamation-triangle"></i>
</div>

<div>

<h5 class="fw-bold mb-1 text-body">
Overdue Payments
</h5>

<p class="text-muted small mb-0">
Your loans with payments past their due date
</p>

</div>

</div>

</div>

<div class="text-md-end">

<div class="fw-bold text-danger fs-5">
₱<?= number_format($overdueTotal, 2) ?>
</div>

<div class="small text-muted">
<?= count($overduePayments) ?> overdue payment(s)
</div>

</div>

</div>

</div>

<!-- DESKTOP -->
<div class="payment-table-wrapper">

<table class="table payment-table table-hover align-middle mb-0">

<thead class="table-light text-uppercase text-muted">

<tr>
<th class="py-3 ps-4">Due Date</th>
<th class="py-3">Borrower</th>
<th class="py-3">Loan Reference</th>
<th class="py-3 text-end">Amount Due</th>
<th class="py-3 text-center">Status</th>
<th class="py-3 text-end pe-4">Overdue</th>
</tr>

</thead>

<tbody>

<?php if (empty($overduePayments)): ?>

<tr>
<td colspan="6">

<div class="empty-state text-center text-muted">

<div class="mb-3">
<i class="bi bi-check-circle display-6 text-success opacity-75"></i>
</div>

<div class="fw-semibold text-success mb-1">
No overdue payments
</div>

<div class="small">
Great! All your scheduled payments are currently up to date.
</div>

</div>

</td>
</tr>

<?php else: ?>

<?php foreach ($overduePayments as $payment):

$dueDate = new DateTime($payment['due_date']);
$today = new DateTime(date('Y-m-d'));
$daysOverdue = (int) $dueDate->diff($today)->format('%a');

?>

<tr>

<td class="ps-4">

<div class="payment-date text-danger">

<i class="bi bi-calendar-x me-1"></i>

<?= date('M d, Y', strtotime($payment['due_date'])) ?>

</div>

</td>

<td>

<div class="fw-bold text-body">
<?= htmlspecialchars($payment['borrower_name']) ?>
</div>

</td>

<td>

<span class="badge bg-secondary bg-opacity-10 text-body fw-semibold">

<?= htmlspecialchars($payment['reference_number'] ?? '#' . $payment['loan_id']) ?>

</span>

</td>

<td class="text-end">

<span class="fw-bold text-danger">
₱<?= number_format($payment['amount_due'], 2) ?>
</span>

</td>

<td class="text-center">

<?php if ($payment['schedule_status'] === 'partially_paid'): ?>

<span class="badge bg-warning bg-opacity-10 text-warning">
Partially Paid
</span>

<?php else: ?>

<span class="badge bg-danger bg-opacity-10 text-danger">
Overdue
</span>

<?php endif; ?>

</td>

<td class="text-end pe-4">

<span class="fw-bold text-danger">
<?= $daysOverdue ?> day(s)
</span>

</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

</tbody>
</table>

</div>

<!-- MOBILE -->
<div class="payment-mobile">

<?php if (empty($overduePayments)): ?>

<div class="empty-state text-center text-muted">

<div class="mb-3">
<i class="bi bi-check-circle display-6 text-success opacity-75"></i>
</div>

<div class="fw-semibold text-success mb-1">
No overdue payments
</div>

<div class="small">
Great! All your scheduled payments are currently up to date.
</div>

</div>

<?php else: ?>

<?php foreach ($overduePayments as $payment):

$dueDate = new DateTime($payment['due_date']);
$today = new DateTime(date('Y-m-d'));
$daysOverdue = (int) $dueDate->diff($today)->format('%a');

?>

<div class="mobile-payment">

<div class="mobile-payment-top">

<div class="flex-grow-1 min-width-0">

<div class="mobile-payment-borrower">
<?= htmlspecialchars($payment['borrower_name']) ?>
</div>

<div class="mobile-payment-reference">
<?= htmlspecialchars($payment['reference_number'] ?? '#' . $payment['loan_id']) ?>
</div>

<div class="mobile-payment-date text-danger">

<i class="bi bi-calendar-x me-1"></i>

<?= date('M d, Y', strtotime($payment['due_date'])) ?>

</div>

</div>

<div class="mobile-payment-amount text-danger">
₱<?= number_format($payment['amount_due'], 2) ?>
</div>

</div>

<div class="mobile-payment-info">

<div class="mobile-payment-info-item">

<span class="mobile-payment-label">
Status
</span>

<?php if ($payment['schedule_status'] === 'partially_paid'): ?>

<span class="badge bg-warning bg-opacity-10 text-warning">
Partially Paid
</span>

<?php else: ?>

<span class="badge bg-danger bg-opacity-10 text-danger">
Overdue
</span>

<?php endif; ?>

</div>

<div class="mobile-payment-info-item text-end">

<span class="mobile-payment-label">
Overdue
</span>

<span class="mobile-payment-value text-danger">
<?= $daysOverdue ?> day(s)
</span>

</div>

</div>

</div>

<?php endforeach; ?>

<?php endif; ?>

</div>

</div>

<!-- RECENT TRANSACTIONS -->
<div class="card transaction-card shadow-sm bg-body">

<div class="transaction-header card-header bg-transparent border-0 px-4 pt-4 pb-3">

<div class="d-flex justify-content-between align-items-center gap-3">

<div>

<h5 class="fw-bold mb-1 text-body">
Recent Transactions
</h5>

<p class="text-muted small mb-0">
Latest activity from your accounts
</p>

</div>

<a href="index.php?page=loan_accounts"
class="small text-decoration-none fw-semibold text-nowrap">

View All
<i class="bi bi-arrow-right ms-1"></i>

</a>

</div>

</div>

<!-- DESKTOP -->
<div class="transaction-table table-responsive">

<table class="table table-hover align-middle mb-0">

<thead class="table-light text-uppercase text-muted">

<tr>

<th class="py-3 ps-4">
Date / Time
</th>

<th class="py-3">
Type
</th>

<th class="py-3">
Description
</th>

<th class="py-3 text-end pe-4">
Amount
</th>

</tr>

</thead>

<tbody>

<?php if (empty($recentTransactions)): ?>

<tr>

<td colspan="4">

<div class="empty-state text-center text-muted">

<div class="mb-3">
<i class="bi bi-receipt display-6 opacity-50"></i>
</div>

<div class="fw-semibold mb-1">
No recent transactions
</div>

<div class="small">
Transactions will appear here when you issue loans or record payments.
</div>

</div>

</td>

</tr>

<?php else: ?>

<?php foreach ($recentTransactions as $tx):

$isCredit = strtoupper($tx['type']) === 'CREDIT';
$typeClass = $isCredit ? 'success' : 'danger';

?>

<tr>

<td class="ps-4 text-muted small">

<i class="bi bi-calendar3 me-1"></i>

<?= date('M d, Y', strtotime($tx['created_at'])) ?>

<div class="ms-4 small opacity-75">

<?= date('h:i A', strtotime($tx['created_at'])) ?>

</div>

</td>

<td>

<span class="badge bg-<?= $typeClass ?> bg-opacity-10 text-<?= $typeClass ?> px-2 py-1 fw-semibold">

<?= htmlspecialchars($tx['type']) ?>

</span>

</td>

<td class="fw-semibold text-body">

<?= htmlspecialchars($tx['description']) ?>

</td>

<td class="text-end pe-4 fw-bold text-<?= $typeClass ?>">

<?= $isCredit ? '+' : '-' ?>₱<?= number_format($tx['amount'], 2) ?>

</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

</tbody>

</table>

</div>

<!-- MOBILE -->
<div class="transaction-mobile">

<?php if (empty($recentTransactions)): ?>

<div class="empty-state text-center text-muted">

<div class="mb-3">
<i class="bi bi-receipt display-6 opacity-50"></i>
</div>

<div class="fw-semibold mb-1">
No recent transactions
</div>

<div class="small">
Transactions will appear here automatically.
</div>

</div>

<?php else: ?>

<?php foreach ($recentTransactions as $tx):

$isCredit = strtoupper($tx['type']) === 'CREDIT';
$typeClass = $isCredit ? 'success' : 'danger';

?>

<div class="mobile-transaction">

<div class="mobile-transaction-top">

<div class="flex-grow-1 min-width-0">

<div class="mobile-transaction-description text-body">

<?= htmlspecialchars($tx['description']) ?>

</div>

<div class="mobile-transaction-date mt-1">

<i class="bi bi-calendar3 me-1"></i>

<?= date('M d, Y h:i A', strtotime($tx['created_at'])) ?>

</div>

</div>

<span class="badge bg-<?= $typeClass ?> bg-opacity-10 text-<?= $typeClass ?> fw-semibold">

<?= htmlspecialchars($tx['type']) ?>

</span>

</div>

<div class="mobile-transaction-bottom">

<span class="small text-muted">
Account Transaction
</span>

<span class="mobile-transaction-amount text-<?= $typeClass ?>">

<?= $isCredit ? '+' : '-' ?>₱<?= number_format($tx['amount'], 2) ?>

</span>

</div>

</div>

<?php endforeach; ?>

<?php endif; ?>

</div>

</div>

</div>

</main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
