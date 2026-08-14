<?php
$pdo = Database::getConnection();

$businessId = $_SESSION['business_id'] ?? null;
$userId = $_SESSION['user_id'] ?? null;

if (!$businessId || !$userId) {
    header('Location: index.php?page=select_business');
    exit;
}

$error = '';
$success = '';

if (isset($_GET['success_payment'])) {
    $success = 'Payment recorded successfully!';
}

$activePage = 'payments';
$pageTitle = 'Payments History - Loan Management';

/*
|--------------------------------------------------------------------------
| PAYMENT HISTORY
|--------------------------------------------------------------------------
| IMPORTANT:
| Payments are filtered by:
|   1. business_id
|   2. created_by
|
| This prevents User 3 from seeing payments created by User 1.
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        p.*,
        l.reference_number,
        l.total_payable,
        l.principal_amount,
        l.status AS loan_status,
        CONCAT(b.first_name, ' ', b.last_name) AS borrower_name,
        a.account_name,
        ls.due_date AS schedule_due_date,
        ls.amount_due AS schedule_amount_due
    FROM loan_payments p
    INNER JOIN loans l
        ON p.loan_id = l.id
        AND l.business_id = p.business_id
    INNER JOIN loan_borrowers b
        ON l.borrower_id = b.id
        AND b.business_id = p.business_id
    INNER JOIN loan_accounts a
        ON l.account_id = a.id
        AND a.business_id = p.business_id
    LEFT JOIN loan_schedules ls
        ON p.schedule_id = ls.id
        AND ls.loan_id = p.loan_id
    WHERE p.business_id = ?
      AND p.created_by = ?
    ORDER BY p.payment_date DESC, p.created_at DESC
");

$stmt->execute([
    $businessId,
    $userId
]);

$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| ACTIVE LOANS
|--------------------------------------------------------------------------
| Only show loans belonging to the current user.
|--------------------------------------------------------------------------
*/

$activeLoansStmt = $pdo->prepare("
    SELECT
        l.id,
        l.reference_number,
        l.total_payable,
        l.principal_amount,
        l.loan_date,
        l.due_date,
        l.payment_type,
        l.payment_frequency,
        l.number_of_payments,
        l.fixed_payment_amount,
        CONCAT(b.first_name, ' ', b.last_name) AS borrower_name
    FROM loans l
    INNER JOIN loan_borrowers b
        ON l.borrower_id = b.id
        AND b.business_id = l.business_id
    WHERE l.business_id = ?
      AND l.created_by = ?
      AND l.status = 'active'
    ORDER BY b.first_name ASC, b.last_name ASC
");

$activeLoansStmt->execute([
    $businessId,
    $userId
]);

$activeLoans = $activeLoansStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| PAYMENT SCHEDULES
|--------------------------------------------------------------------------
*/

$loanSchedules = [];

if (!empty($activeLoans)) {

    $loanIds = array_column($activeLoans, 'id');

    $placeholders = implode(
        ',',
        array_fill(0, count($loanIds), '?')
    );

    $scheduleStmt = $pdo->prepare("
        SELECT
            id,
            loan_id,
            due_date,
            amount_due,
            status
        FROM loan_schedules
        WHERE loan_id IN ($placeholders)
        ORDER BY loan_id ASC, due_date ASC, id ASC
    ");

    $scheduleStmt->execute($loanIds);

    $scheduleRows = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($scheduleRows as $schedule) {
        $loanId = (int)$schedule['loan_id'];

        if (!isset($loanSchedules[$loanId])) {
            $loanSchedules[$loanId] = [];
        }

        $loanSchedules[$loanId][] = $schedule;
    }
}
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
    const theme = localStorage.getItem('bs-theme') || 'light';
    document.documentElement.setAttribute('data-bs-theme', theme);
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

.payment-card,
.schedule-summary {
    border-radius: 1rem;
}

.mobile-action-btn {
    white-space: nowrap;
}

.schedule-container {
    border: 1px solid var(--bs-border-color);
    border-radius: 1rem;
    overflow: hidden;
}

.schedule-header {
    background: var(--bs-tertiary-bg);
    padding: .85rem 1rem;
    border-bottom: 1px solid var(--bs-border-color);
}

.schedule-item {
    position: relative;
    padding: .9rem 1rem;
    border-bottom: 1px solid var(--bs-border-color);
    transition: background-color .15s ease;
}

.schedule-item:last-child {
    border-bottom: 0;
}

.schedule-item:hover {
    background: var(--bs-tertiary-bg);
}

.schedule-item.schedule-selected {
    background: rgba(var(--bs-primary-rgb), .08);
}

.schedule-checkbox {
    width: 1.25rem;
    height: 1.25rem;
    cursor: pointer;
}

.schedule-date,
.schedule-amount {
    font-weight: 700;
}

.schedule-paid {
    text-decoration: line-through;
    opacity: .6;
}

.selected-count {
    font-size: .8rem;
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
        font-size: .8rem;
        line-height: 1.4;
    }

    .payment-card {
        border-radius: .75rem;
    }

    .table-responsive {
        border-radius: .75rem;
    }

    .payments-table th,
    .payments-table td {
        padding-top: .7rem;
        padding-bottom: .7rem;
    }

    .modal-dialog {
        margin: .75rem;
    }

    .modal-content {
        border-radius: 1rem !important;
    }

    .modal-body {
        padding: 1rem !important;
    }

    .modal-footer {
        padding: .75rem 1rem !important;
    }

    .modal-footer .btn {
        flex: 1;
    }
}
</style>

</head>

<body class="bg-body-tertiary">

<div
    class="d-flex flex-column flex-lg-row"
    style="min-height:100vh"
>

<?php include __DIR__ . '/../../../resources/partials/loansidebar.php'; ?>

<div class="page-content p-3 p-md-4 flex-grow-1 bg-body-tertiary">

<!-- HEADER -->

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


<!-- SUCCESS -->

<?php if ($success): ?>

<div class="alert alert-success alert-dismissible fade show shadow-sm border-0 rounded-3 mb-4">

    <i class="bi bi-check-circle-fill me-2"></i>

    <?= htmlspecialchars($success) ?>

    <button
        type="button"
        class="btn-close"
        data-bs-dismiss="alert"
    ></button>

</div>

<?php endif; ?>


<!-- ERROR -->

<?php if ($error): ?>

<div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 rounded-3 mb-4">

    <i class="bi bi-exclamation-triangle-fill me-2"></i>

    <?= htmlspecialchars($error) ?>

    <button
        type="button"
        class="btn-close"
        data-bs-dismiss="alert"
    ></button>

</div>

<?php endif; ?>


<!-- PAYMENTS TABLE -->

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
Installment
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

<td
    colspan="8"
    class="text-center py-5 text-muted"
>

<div class="mb-2">

<i class="bi bi-wallet2 display-6 opacity-50"></i>

</div>

<p class="mb-1 fw-semibold">
No payments recorded yet
</p>

<p class="small text-muted mb-0">
Payments collected from your loans will appear here.
</p>

</td>

</tr>

<?php else: ?>


<?php foreach ($payments as $pay): ?>

<tr>

<!-- DATE -->

<td class="ps-4 text-muted small fw-semibold">

<i class="bi bi-calendar-event me-1"></i>

<?= date(
    'M d, Y',
    strtotime($pay['payment_date'])
) ?>

</td>


<!-- BORROWER -->

<td>

<div class="fw-bold text-body">

<?= htmlspecialchars(
    $pay['borrower_name']
) ?>

</div>

</td>


<!-- LOAN -->

<td>

<span class="badge bg-secondary bg-opacity-10 text-body fw-semibold px-2 py-1">

<?= htmlspecialchars(
    $pay['reference_number'] ?? '#' . $pay['loan_id']
) ?>

</span>

</td>


<!-- INSTALLMENT -->

<td>

<?php if (!empty($pay['schedule_id'])): ?>

<span class="badge bg-primary bg-opacity-10 text-primary">

<i class="bi bi-calendar-check me-1"></i>

Schedule #<?= intval($pay['schedule_id']) ?>

</span>

<?php else: ?>

<span class="text-muted small">
General Payment
</span>

<?php endif; ?>

</td>


<!-- AMOUNT -->

<td>

<div class="fw-bold text-success">

₱<?= number_format(
    $pay['payment_amount'],
    2
) ?>

</div>

</td>


<!-- ACCOUNT -->

<td class="text-muted small">

<i class="bi bi-wallet2 me-1"></i>

<?= htmlspecialchars(
    $pay['account_name']
) ?>

</td>


<!-- NOTES -->

<td class="notes-cell text-muted small">

<?php if (!empty($pay['notes'])): ?>

<?= htmlspecialchars($pay['notes']) ?>

<?php else: ?>

<span class="text-muted opacity-50">
—
</span>

<?php endif; ?>

</td>


<!-- ACTION -->

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


<!-- PAYMENT DETAILS MODAL -->

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
></button>

</div>


<div class="modal-body p-4 text-start">


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


<div class="row g-3">


<div class="col-12 col-sm-6">

<span class="text-muted small d-block">
Borrower Name
</span>

<strong>
<?= htmlspecialchars(
    $pay['borrower_name']
) ?>
</strong>

</div>


<div class="col-12 col-sm-6">

<span class="text-muted small d-block">
Loan Reference
</span>

<strong>

<?= htmlspecialchars(
    $pay['reference_number'] ??
    '#' . $pay['loan_id']
) ?>

</strong>

</div>


<div class="col-12 col-sm-6">

<span class="text-muted small d-block">
Payment Date
</span>

<strong>

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

<strong>

<?= htmlspecialchars(
    $pay['account_name']
) ?>

</strong>

</div>


<?php if (!empty($pay['schedule_due_date'])): ?>

<div class="col-12 col-sm-6">

<span class="text-muted small d-block">
Installment Due Date
</span>

<strong>

<?= date(
    'M d, Y',
    strtotime($pay['schedule_due_date'])
) ?>

</strong>

</div>


<div class="col-12 col-sm-6">

<span class="text-muted small d-block">
Scheduled Amount
</span>

<strong>

₱<?= number_format(
    $pay['schedule_amount_due'],
    2
) ?>

</strong>

</div>

<?php endif; ?>


<div class="col-12">

<span class="text-muted small d-block">
Total Loan Payable
</span>

<strong>

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

<?= !empty($pay['notes'])
    ? htmlspecialchars($pay['notes'])
    : 'No remarks provided.'
?>

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


<!-- RECORD PAYMENT MODAL -->

<div
    class="modal fade"
    id="recordPaymentModal"
    tabindex="-1"
    aria-hidden="true"
>

<div class="modal-dialog modal-dialog-centered modal-lg">

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
></button>

</div>


<form
    method="POST"
    action="index.php?page=loans"
    id="recordPaymentForm"
>

<input
    type="hidden"
    name="record_payment"
    value="1"
>


<div class="modal-body p-4 text-start">


<!-- LOAN -->

<div class="mb-4">

<label class="form-label fw-semibold small">

Select Active Loan

<span class="text-danger">*</span>

</label>


<select
    name="loan_id"
    id="paymentLoanId"
    class="form-select shadow-none"
    required
>

<option value="">
Choose active loan...
</option>


<?php foreach ($activeLoans as $al): ?>

<option value="<?= intval($al['id']) ?>">

<?= htmlspecialchars(
    $al['borrower_name']
) ?>

— Ref:

<?= htmlspecialchars(
    $al['reference_number'] ?? '#' . $al['id']
) ?>

— Due:

₱<?= number_format(
    $al['total_payable'],
    2
) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<!-- SCHEDULES -->

<div
    class="mb-4"
    id="paymentScheduleSection"
    style="display:none;"
>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-2">

<div>

<label class="form-label fw-semibold small mb-0">

Select Installment(s)

<span class="text-danger">*</span>

</label>

<div class="text-muted small">

You can select multiple installments if the borrower pays in advance.

</div>

</div>


<button
    type="button"
    class="btn btn-sm btn-outline-primary"
    id="selectAllSchedulesBtn"
>

<i class="bi bi-check2-square me-1"></i>

Select All Unpaid

</button>

</div>


<div class="schedule-container">


<div class="schedule-header d-flex justify-content-between align-items-center">

<span class="small fw-bold text-body">
Payment Schedule
</span>

<span
    class="selected-count text-muted"
    id="selectedScheduleCount"
>
0 selected
</span>

</div>


<div id="scheduleList"></div>


</div>


<div
    id="noScheduleMessage"
    class="alert alert-warning mt-3 mb-0 d-none"
>

<i class="bi bi-exclamation-triangle me-2"></i>

No payment schedules were found for this loan.

</div>

</div>


<!-- PAYMENT AMOUNT -->

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
    id="paymentAmount"
    class="form-control shadow-none"
    required
    placeholder="Select installment(s)"
>


<div class="form-text">

The amount will automatically update based on the selected installment(s).

</div>

</div>


<!-- DATE -->

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


<!-- NOTES -->

<div class="mb-3">

<label class="form-label fw-semibold small">

Notes / Remarks (Optional)

</label>


<textarea
    name="payment_notes"
    class="form-control shadow-none"
    rows="2"
    placeholder="e.g. Borrower paid September and October installments in advance"
></textarea>

</div>


<!-- SUMMARY -->

<div class="schedule-summary bg-success bg-opacity-10 border border-success border-opacity-25 p-3 mt-3">

<div class="d-flex justify-content-between align-items-center">

<div>

<span class="text-muted small d-block">
Selected Installments
</span>

<strong id="summaryCount">
0
</strong>

</div>


<div class="text-end">

<span class="text-muted small d-block">
Total Payment
</span>

<strong
    class="text-success fs-5"
    id="summaryTotal"
>
₱0.00
</strong>

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

Cancel

</button>


<button
    type="submit"
    class="btn btn-success px-4 fw-bold shadow-sm"
    id="savePaymentBtn"
    disabled
>

<i class="bi bi-check-lg me-1"></i>

Save Payment

</button>

</div>


</form>

</div>

</div>

</div>


<script>

const loanSchedules =
<?= json_encode(
    $loanSchedules,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_AMP |
    JSON_HEX_QUOT
) ?>;

const paymentLoanId =
document.getElementById('paymentLoanId');

const paymentScheduleSection =
document.getElementById('paymentScheduleSection');

const scheduleList =
document.getElementById('scheduleList');

const noScheduleMessage =
document.getElementById('noScheduleMessage');

const paymentAmount =
document.getElementById('paymentAmount');

const selectedScheduleCount =
document.getElementById('selectedScheduleCount');

const summaryCount =
document.getElementById('summaryCount');

const summaryTotal =
document.getElementById('summaryTotal');

const selectAllSchedulesBtn =
document.getElementById('selectAllSchedulesBtn');

const savePaymentBtn =
document.getElementById('savePaymentBtn');


function formatMoney(amount) {

    return Number(amount || 0).toLocaleString(
        'en-PH',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }
    );

}


function formatDate(dateString) {

    if (!dateString) {
        return '';
    }

    const parts = dateString.split('-');

    if (parts.length !== 3) {
        return dateString;
    }

    return new Date(
        Number(parts[0]),
        Number(parts[1]) - 1,
        Number(parts[2])
    ).toLocaleDateString(
        'en-US',
        {
            month: 'short',
            day: '2-digit',
            year: 'numeric'
        }
    );

}


function updateSummary() {

    const selected =
        document.querySelectorAll(
            '.schedule-checkbox:checked'
        );

    let total = 0;

    selected.forEach(function (checkbox) {

        total += Number(
            checkbox.dataset.amount || 0
        );

    });

    const count = selected.length;

    selectedScheduleCount.textContent =
        `${count} selected`;

    summaryCount.textContent =
        count;

    summaryTotal.textContent =
        `₱${formatMoney(total)}`;


    if (count > 0) {

        paymentAmount.value =
            total.toFixed(2);

        paymentAmount.readOnly = true;

        paymentAmount.classList.add(
            'bg-body-secondary'
        );

        savePaymentBtn.disabled = false;

    } else {

        paymentAmount.value = '';

        paymentAmount.readOnly = false;

        paymentAmount.classList.remove(
            'bg-body-secondary'
        );

        savePaymentBtn.disabled = true;

    }

}


function loadSchedules(loanId) {

    scheduleList.innerHTML = '';

    paymentScheduleSection.style.display =
        'none';

    noScheduleMessage.classList.add(
        'd-none'
    );

    paymentAmount.value = '';

    updateSummary();


    if (!loanId) {
        return;
    }


    const schedules =
        loanSchedules[loanId] || [];


    paymentScheduleSection.style.display =
        'block';


    if (!schedules.length) {

        noScheduleMessage.classList.remove(
            'd-none'
        );

        return;

    }


    schedules.forEach(function (schedule, index) {

        const isPaid =
            schedule.status === 'paid';


        const wrapper =
            document.createElement('div');

        wrapper.className =
            'schedule-item';


        if (isPaid) {

            wrapper.classList.add(
                'opacity-75'
            );

        }


        const row =
            document.createElement('div');

        row.className =
            'd-flex align-items-center gap-3';


        const checkboxWrapper =
            document.createElement('div');

        checkboxWrapper.className =
            'flex-shrink-0';


        const checkbox =
            document.createElement('input');

        checkbox.type =
            'checkbox';

        checkbox.className =
            'form-check-input schedule-checkbox';

        checkbox.name =
            'schedule_ids[]';

        checkbox.value =
            schedule.id;

        checkbox.dataset.amount =
            schedule.amount_due;

        checkbox.dataset.status =
            schedule.status;

        checkbox.disabled =
            isPaid;


        checkbox.addEventListener(
            'change',
            function () {

                wrapper.classList.toggle(
                    'schedule-selected',
                    checkbox.checked
                );

                updateSummary();

            }
        );


        checkboxWrapper.appendChild(
            checkbox
        );


        const installment =
            document.createElement('div');

        installment.className =
            'flex-grow-1';


        const title =
            document.createElement('div');

        title.className =
            'fw-bold text-body';

        title.textContent =
            `Installment ${index + 1}`;


        const date =
            document.createElement('div');

        date.className =
            'small text-muted';

        date.innerHTML =
            `<i class="bi bi-calendar3 me-1"></i>${formatDate(schedule.due_date)}`;


        installment.append(
            title,
            date
        );


        const statusDiv =
            document.createElement('div');

        statusDiv.className =
            'text-center flex-shrink-0';


        if (schedule.status === 'paid') {

            statusDiv.innerHTML =
                '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Paid</span>';

        } else if (
            schedule.status === 'partially_paid'
        ) {

            statusDiv.innerHTML =
                '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Partially Paid</span>';

        } else {

            statusDiv.innerHTML =
                '<span class="badge bg-secondary">Unpaid</span>';

        }


        const amountDiv =
            document.createElement('div');

        amountDiv.className =
            'text-end flex-shrink-0';

        amountDiv.innerHTML = `
            <div class="schedule-amount ${
                isPaid
                    ? 'schedule-paid'
                    : 'text-success'
            }">
                ₱${formatMoney(schedule.amount_due)}
            </div>
        `;


        row.append(
            checkboxWrapper,
            installment,
            statusDiv,
            amountDiv
        );


        wrapper.appendChild(row);

        scheduleList.appendChild(wrapper);

    });


    updateSummary();

}


paymentLoanId.addEventListener(
    'change',
    function () {

        loadSchedules(
            paymentLoanId.value
        );

    }
);


selectAllSchedulesBtn.addEventListener(
    'click',
    function () {

        const checkboxes =
            document.querySelectorAll(
                '.schedule-checkbox:not(:disabled)'
            );


        if (!checkboxes.length) {
            return;
        }


        const allSelected =
            [...checkboxes].every(
                checkbox => checkbox.checked
            );


        checkboxes.forEach(
            function (checkbox) {

                checkbox.checked =
                    !allSelected;


                const wrapper =
                    checkbox.closest(
                        '.schedule-item'
                    );


                if (wrapper) {

                    wrapper.classList.toggle(
                        'schedule-selected',
                        checkbox.checked
                    );

                }

            }
        );


        selectAllSchedulesBtn.innerHTML =
            allSelected
                ? '<i class="bi bi-check2-square me-1"></i> Select All Unpaid'
                : '<i class="bi bi-x-square me-1"></i> Clear Selection';


        updateSummary();

    }
);


document
    .getElementById('recordPaymentForm')
    .addEventListener(
        'submit',
        function (event) {

            const selected =
                document.querySelectorAll(
                    '.schedule-checkbox:checked'
                );


            if (!selected.length) {

                event.preventDefault();

                alert(
                    'Please select at least one unpaid installment.'
                );

                return;

            }


            if (
                !paymentAmount.value ||
                Number(paymentAmount.value) <= 0
            ) {

                event.preventDefault();

                alert(
                    'Please select a valid installment.'
                );

            }

        }
    );


document
    .getElementById('recordPaymentModal')
    .addEventListener(
        'hidden.bs.modal',
        function () {

            document
                .getElementById('recordPaymentForm')
                .reset();


            scheduleList.innerHTML = '';

            paymentScheduleSection.style.display =
                'none';

            noScheduleMessage.classList.add(
                'd-none'
            );


            paymentAmount.readOnly =
                false;

            paymentAmount.classList.remove(
                'bg-body-secondary'
            );

            paymentAmount.value = '';


            selectAllSchedulesBtn.innerHTML =
                '<i class="bi bi-check2-square me-1"></i> Select All Unpaid';


            updateSummary();

        }
    );

</script>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>

</body>
</html>