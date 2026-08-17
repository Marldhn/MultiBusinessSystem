<?php

$pdo = Database::getConnection();

$userId = $_SESSION['user_id'] ?? null;
$businessId = $_SESSION['business_id'] ?? null;
$businessName = $_SESSION['business_name'] ?? 'Business';

if (!$userId) {
    header('Location: index.php?page=login');
    exit;
}

if (!$businessId) {
    header('Location: index.php?page=select_business');
    exit;
}

$activePage = 'pos_transactions';
$pageTitle = 'Sales Transactions';

$search = trim($_GET['search'] ?? '');
$paymentStatus = trim($_GET['payment_status'] ?? '');
$saleStatus = trim($_GET['sale_status'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$transactions = [];
$totalTransactions = 0;
$totalSales = 0;
$totalPaid = 0;
$totalPending = 0;
$error = '';
$selectedSale = null;
$selectedItems = [];
$selectedPayments = [];

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| VIEW TRANSACTION
|--------------------------------------------------------------------------
|
| IMPORTANT:
| business_id is ALWAYS included.
| This prevents another business from viewing a transaction by changing
| the ID in the URL.
|
*/

$viewId = (int)($_GET['view'] ?? 0);

if ($viewId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                s.*,
                CONCAT(
                    COALESCE(c.first_name, ''),
                    CASE
                        WHEN c.middle_name IS NOT NULL
                        AND c.middle_name != ''
                        THEN CONCAT(' ', c.middle_name)
                        ELSE ''
                    END,
                    CASE
                        WHEN c.last_name IS NOT NULL
                        AND c.last_name != ''
                        THEN CONCAT(' ', c.last_name)
                        ELSE ''
                    END
                ) AS customer_name,
                c.email AS customer_email,
                c.phone AS customer_phone,
                c.address AS customer_address
            FROM pos_sales s
            LEFT JOIN pos_customers c
                ON c.id = s.customer_id
                AND c.business_id = s.business_id
            WHERE s.id = ?
            AND s.business_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $viewId,
            $businessId
        ]);

        $selectedSale = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($selectedSale) {

            $stmt = $pdo->prepare("
                SELECT
                    si.*,
                    p.name AS current_product_name,
                    p.barcode AS current_barcode
                FROM pos_sale_items si
                LEFT JOIN pos_products p
                    ON p.id = si.product_id
                    AND p.business_id = ?
                WHERE si.sale_id = ?
                ORDER BY si.id ASC
            ");

            $stmt->execute([
                $businessId,
                $viewId
            ]);

            $selectedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (tableExistsForTransactions($pdo, 'pos_payments')) {

                $stmt = $pdo->prepare("
                    SELECT *
                    FROM pos_payments
                    WHERE sale_id = ?
                    AND business_id = ?
                    ORDER BY payment_date DESC, id DESC
                ");

                $stmt->execute([
                    $viewId,
                    $businessId
                ]);

                $selectedPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

    } catch (Throwable $e) {
        $selectedSale = null;
        $selectedItems = [];
        $selectedPayments = [];
    }
}

/*
|--------------------------------------------------------------------------
| BUILD FILTERS
|--------------------------------------------------------------------------
*/

$where = [
    's.business_id = ?'
];

$params = [
    $businessId
];

if ($search !== '') {

    $where[] = "(
        s.invoice_number LIKE ?
        OR CONCAT(
            COALESCE(c.first_name, ''),
            ' ',
            COALESCE(c.last_name, '')
        ) LIKE ?
        OR c.phone LIKE ?
    )";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

if (in_array($paymentStatus, ['unpaid', 'partial', 'paid'], true)) {

    $where[] = 's.payment_status = ?';
    $params[] = $paymentStatus;
}

if (in_array($saleStatus, ['completed', 'voided', 'refunded'], true)) {

    $where[] = 's.sale_status = ?';
    $params[] = $saleStatus;
}

if ($dateFrom !== '') {

    $dateObject = DateTime::createFromFormat('Y-m-d', $dateFrom);

    if ($dateObject && $dateObject->format('Y-m-d') === $dateFrom) {

        $where[] = 'DATE(s.sale_date) >= ?';
        $params[] = $dateFrom;
    }
}

if ($dateTo !== '') {

    $dateObject = DateTime::createFromFormat('Y-m-d', $dateTo);

    if ($dateObject && $dateObject->format('Y-m-d') === $dateTo) {

        $where[] = 'DATE(s.sale_date) <= ?';
        $params[] = $dateTo;
    }
}

$whereSql = implode(' AND ', $where);

/*
|--------------------------------------------------------------------------
| TOTAL SUMMARY
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS transaction_count,
            COALESCE(SUM(s.total_amount), 0) AS total_sales,
            COALESCE(SUM(s.amount_paid), 0) AS total_paid,
            COALESCE(
                SUM(
                    CASE
                        WHEN s.payment_status != 'paid'
                        THEN GREATEST(
                            s.total_amount - s.amount_paid,
                            0
                        )
                        ELSE 0
                    END
                ),
                0
            ) AS total_pending
        FROM pos_sales s
        LEFT JOIN pos_customers c
            ON c.id = s.customer_id
            AND c.business_id = s.business_id
        WHERE {$whereSql}
    ");

    $stmt->execute($params);

    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($summary) {

        $totalTransactions = (int)($summary['transaction_count'] ?? 0);
        $totalSales = (float)($summary['total_sales'] ?? 0);
        $totalPaid = (float)($summary['total_paid'] ?? 0);
        $totalPending = (float)($summary['total_pending'] ?? 0);
    }

} catch (Throwable $e) {

    $totalTransactions = 0;
    $totalSales = 0;
    $totalPaid = 0;
    $totalPending = 0;
}

/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$totalPages = max(
    1,
    (int)ceil($totalTransactions / $perPage)
);

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

/*
|--------------------------------------------------------------------------
| LOAD TRANSACTIONS
|--------------------------------------------------------------------------
*/

try {

    $sql = "
        SELECT
            s.id,
            s.invoice_number,
            s.sale_date,
            s.subtotal,
            s.discount,
            s.tax,
            s.total_amount,
            s.amount_paid,
            s.change_amount,
            s.payment_status,
            s.sale_status,
            s.notes,
            CONCAT(
                COALESCE(c.first_name, ''),
                CASE
                    WHEN c.last_name IS NOT NULL
                    AND c.last_name != ''
                    THEN CONCAT(' ', c.last_name)
                    ELSE ''
                END
            ) AS customer_name,
            c.phone AS customer_phone,
            (
                SELECT COALESCE(SUM(si.quantity), 0)
                FROM pos_sale_items si
                WHERE si.sale_id = s.id
            ) AS total_items
        FROM pos_sales s
        LEFT JOIN pos_customers c
            ON c.id = s.customer_id
            AND c.business_id = s.business_id
        WHERE {$whereSql}
        ORDER BY s.sale_date DESC, s.id DESC
        LIMIT {$perPage} OFFSET {$offset}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $transactions = [];
    $error = 'Unable to load sales transactions.';
}

/*
|--------------------------------------------------------------------------
| PAGINATION URL
|--------------------------------------------------------------------------
*/

function transactionPageUrl($pageNumber)
{
    $query = $_GET;
    $query['p'] = $pageNumber;

    return 'index.php?' . http_build_query($query);
}

/*
|--------------------------------------------------------------------------
| CHECK TABLE
|--------------------------------------------------------------------------
*/

function tableExistsForTransactions(PDO $pdo, string $table): bool
{
    try {

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
            AND table_name = ?
        ");

        $stmt->execute([$table]);

        return (bool)$stmt->fetchColumn();

    } catch (Throwable $e) {

        return false;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title><?= e($pageTitle) ?></title>

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
    overflow-x: hidden;
}

.pos-main {
    min-width: 0;
    width: 100%;
}

.pos-card {
    border: 0;
    border-radius: 16px;
}

.pos-card-header {
    padding: 18px 20px;
    border-bottom: 1px solid var(--bs-border-color);
}

.stat-card {
    border: 0;
    border-radius: 16px;
}

.stat-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.transaction-number {
    font-weight: 700;
    font-size: .85rem;
}

.customer-name {
    font-size: .82rem;
    font-weight: 600;
}

.customer-phone {
    font-size: .7rem;
    color: var(--bs-secondary-color);
}

.transaction-date {
    font-size: .75rem;
    color: var(--bs-secondary-color);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: .3rem .6rem;
    border-radius: 50rem;
    font-size: .68rem;
    font-weight: 700;
}

.status-paid {
    background: rgba(25, 135, 84, .12);
    color: var(--bs-success);
}

.status-partial {
    background: rgba(255, 193, 7, .15);
    color: var(--bs-warning-text);
}

.status-unpaid {
    background: rgba(220, 53, 69, .12);
    color: var(--bs-danger);
}

.status-completed {
    background: rgba(25, 135, 84, .12);
    color: var(--bs-success);
}

.status-voided,
.status-refunded {
    background: rgba(220, 53, 69, .12);
    color: var(--bs-danger);
}

.table > :not(caption) > * > * {
    vertical-align: middle;
}

.empty-state {
    padding: 70px 20px;
}

.summary-number {
    font-size: 1.35rem;
    font-weight: 700;
}

.detail-label {
    font-size: .68rem;
    color: var(--bs-secondary-color);
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: .04em;
}

.detail-value {
    font-weight: 600;
}

.invoice-title {
    font-size: 1.2rem;
    font-weight: 700;
}

.sale-item-table th {
    font-size: .7rem;
    text-transform: uppercase;
    color: var(--bs-secondary-color);
}

.sale-item-table td {
    font-size: .82rem;
}

@media (max-width: 575.98px) {

    .pos-card {
        border-radius: 14px;
    }

    .summary-number {
        font-size: 1.15rem;
    }

}

</style>

</head>

<body class="bg-body-tertiary">

<div class="d-flex flex-column flex-lg-row min-vh-100">

<?php

$sidebarPath =
    __DIR__ .
    '/../../../resources/partials/POSSidebar.php';

if (file_exists($sidebarPath)) {
    include $sidebarPath;
}

?>

<main class="pos-main flex-grow-1 bg-body-tertiary">

<div class="p-3 p-md-4">

<!-- HEADER -->

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

<div>

<div class="d-flex align-items-center gap-2 mb-1">

<div class="d-lg-none bg-primary bg-opacity-10 text-primary rounded-3 p-2">

<i class="bi bi-receipt"></i>

</div>

<h2 class="fw-bold mb-0">

Sales Transactions

</h2>

</div>

<p class="text-muted small mb-0">

View and manage sales transactions for

<span class="fw-semibold text-primary">

<?= e($businessName) ?>

</span>

</p>

</div>

<a
    href="index.php?page=pos_sales"
    class="btn btn-primary rounded-3 fw-bold"
>

<i class="bi bi-cart-plus me-1"></i>

New Sale

</a>

</div>


<?php if ($error): ?>

<div class="alert alert-danger rounded-3">

<i class="bi bi-exclamation-triangle me-2"></i>

<?= e($error) ?>

</div>

<?php endif; ?>


<!-- SUMMARY -->

<div class="row g-3 mb-4">

<div class="col-12 col-sm-6 col-xl-3">

<div class="card stat-card shadow-sm bg-body h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start">

<div>

<div class="text-muted small fw-semibold mb-2">

Transactions

</div>

<div class="summary-number">

<?= number_format($totalTransactions) ?>

</div>

<div class="text-muted small">

Filtered transactions

</div>

</div>

<div class="stat-icon bg-primary bg-opacity-10 text-primary">

<i class="bi bi-receipt fs-5"></i>

</div>

</div>

</div>

</div>

</div>


<div class="col-12 col-sm-6 col-xl-3">

<div class="card stat-card shadow-sm bg-body h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start">

<div>

<div class="text-muted small fw-semibold mb-2">

Total Sales

</div>

<div class="summary-number">

₱<?= number_format($totalSales, 2) ?>

</div>

<div class="text-muted small">

Gross sales amount

</div>

</div>

<div class="stat-icon bg-success bg-opacity-10 text-success">

<i class="bi bi-cash-stack fs-5"></i>

</div>

</div>

</div>

</div>

</div>


<div class="col-12 col-sm-6 col-xl-3">

<div class="card stat-card shadow-sm bg-body h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start">

<div>

<div class="text-muted small fw-semibold mb-2">

Amount Paid

</div>

<div class="summary-number">

₱<?= number_format($totalPaid, 2) ?>

</div>

<div class="text-muted small">

Payments received

</div>

</div>

<div class="stat-icon bg-info bg-opacity-10 text-info">

<i class="bi bi-wallet2 fs-5"></i>

</div>

</div>

</div>

</div>

</div>


<div class="col-12 col-sm-6 col-xl-3">

<div class="card stat-card shadow-sm bg-body h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start">

<div>

<div class="text-muted small fw-semibold mb-2">

Outstanding

</div>

<div class="summary-number text-danger">

₱<?= number_format($totalPending, 2) ?>

</div>

<div class="text-muted small">

Unpaid / partial

</div>

</div>

<div class="stat-icon bg-danger bg-opacity-10 text-danger">

<i class="bi bi-exclamation-circle fs-5"></i>

</div>

</div>

</div>

</div>

</div>

</div>


<!-- FILTERS -->

<div class="card pos-card shadow-sm bg-body mb-4">

<div class="pos-card-header">

<h5 class="fw-bold mb-1">

<i class="bi bi-funnel me-2"></i>

Filter Transactions

</h5>

<p class="text-muted small mb-0">

Search and filter your sales records.

</p>

</div>

<div class="card-body">

<form method="GET" action="index.php">

<input
    type="hidden"
    name="page"
    value="pos_transactions"
>

<div class="row g-3">

<div class="col-12 col-lg-4">

<label class="form-label small fw-semibold">

Search

</label>

<div class="input-group">

<span class="input-group-text">

<i class="bi bi-search"></i>

</span>

<input
    type="text"
    name="search"
    class="form-control"
    placeholder="Invoice, customer or phone..."
    value="<?= e($search) ?>"
>

</div>

</div>


<div class="col-12 col-sm-6 col-lg-2">

<label class="form-label small fw-semibold">

From

</label>

<input
    type="date"
    name="date_from"
    class="form-control"
    value="<?= e($dateFrom) ?>"
>

</div>


<div class="col-12 col-sm-6 col-lg-2">

<label class="form-label small fw-semibold">

To

</label>

<input
    type="date"
    name="date_to"
    class="form-control"
    value="<?= e($dateTo) ?>"
>

</div>


<div class="col-12 col-sm-6 col-lg-2">

<label class="form-label small fw-semibold">

Payment

</label>

<select
    name="payment_status"
    class="form-select"
>

<option value="">All Payments</option>

<option
    value="paid"
    <?= $paymentStatus === 'paid' ? 'selected' : '' ?>
>
Paid
</option>

<option
    value="partial"
    <?= $paymentStatus === 'partial' ? 'selected' : '' ?>
>
Partial
</option>

<option
    value="unpaid"
    <?= $paymentStatus === 'unpaid' ? 'selected' : '' ?>
>
Unpaid
</option>

</select>

</div>


<div class="col-12 col-sm-6 col-lg-2">

<label class="form-label small fw-semibold">

Sale Status

</label>

<select
    name="sale_status"
    class="form-select"
>

<option value="">All Status</option>

<option
    value="completed"
    <?= $saleStatus === 'completed' ? 'selected' : '' ?>
>
Completed
</option>

<option
    value="voided"
    <?= $saleStatus === 'voided' ? 'selected' : '' ?>
>
Voided
</option>

<option
    value="refunded"
    <?= $saleStatus === 'refunded' ? 'selected' : '' ?>
>
Refunded
</option>

</select>

</div>


<div class="col-12 d-flex gap-2">

<button
    type="submit"
    class="btn btn-primary rounded-3 fw-semibold"
>

<i class="bi bi-search me-1"></i>

Apply Filters

</button>

<a
    href="index.php?page=pos_transactions"
    class="btn btn-outline-secondary rounded-3"
>

<i class="bi bi-arrow-counterclockwise me-1"></i>

Reset

</a>

</div>

</div>

</form>

</div>

</div>


<!-- TRANSACTIONS -->

<div class="card pos-card shadow-sm bg-body">

<div class="pos-card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">

<div>

<h5 class="fw-bold mb-1">

Sales Transactions

</h5>

<p class="text-muted small mb-0">

<?= number_format($totalTransactions) ?>

transaction(s) found

</p>

</div>

</div>


<?php if (!$transactions): ?>

<div class="empty-state text-center">

<div class="mb-3">

<i class="bi bi-receipt display-4 text-muted opacity-50"></i>

</div>

<h6 class="fw-bold">

No transactions found

</h6>

<p class="text-muted small mb-3">

There are no sales matching your current filters.

</p>

<a
    href="index.php?page=pos_sales"
    class="btn btn-primary rounded-3 fw-bold"
>

<i class="bi bi-cart-plus me-1"></i>

Create New Sale

</a>

</div>

<?php else: ?>

<div class="table-responsive">

<table class="table table-hover mb-0">

<thead class="table-light">

<tr>

<th class="ps-4 py-3">

Invoice

</th>

<th class="py-3">

Customer

</th>

<th class="py-3">

Date

</th>

<th class="py-3 text-center">

Items

</th>

<th class="py-3 text-end">

Total

</th>

<th class="py-3">

Payment

</th>

<th class="py-3">

Sale Status

</th>

<th class="py-3 pe-4 text-end">

Action

</th>

</tr>

</thead>

<tbody>

<?php foreach ($transactions as $transaction): ?>

<?php

$payment =
    strtolower(
        $transaction['payment_status'] ?? 'unpaid'
    );

$sale =
    strtolower(
        $transaction['sale_status'] ?? 'completed'
    );

$customer =
    trim(
        $transaction['customer_name'] ?? ''
    );

if ($customer === '') {
    $customer = 'Walk-in Customer';
}

?>

<tr>

<td class="ps-4">

<div class="transaction-number">

<?= e($transaction['invoice_number']) ?>

</div>

</td>


<td>

<div class="customer-name">

<?= e($customer) ?>

</div>

<?php if (!empty($transaction['customer_phone'])): ?>

<div class="customer-phone">

<?= e($transaction['customer_phone']) ?>

</div>

<?php endif; ?>

</td>


<td>

<div class="transaction-date">

<?= date(
    'M d, Y',
    strtotime($transaction['sale_date'])
) ?>

</div>

<div class="transaction-date">

<?= date(
    'h:i A',
    strtotime($transaction['sale_date'])
) ?>

</div>

</td>


<td class="text-center">

<span class="fw-semibold">

<?= number_format(
    (float)($transaction['total_items'] ?? 0),
    0
) ?>

</span>

</td>


<td class="text-end">

<div class="fw-bold">

₱<?= number_format(
    (float)$transaction['total_amount'],
    2
) ?>

</div>

<?php if (
    (float)$transaction['amount_paid']
    <
    (float)$transaction['total_amount']
): ?>

<div
    class="text-danger"
    style="font-size:.68rem;"
>

Due:
₱<?= number_format(
    max(
        0,
        (float)$transaction['total_amount']
        -
        (float)$transaction['amount_paid']
    ),
    2
) ?>

</div>

<?php endif; ?>

</td>


<td>

<?php if ($payment === 'paid'): ?>

<span class="status-badge status-paid">

<i class="bi bi-check-circle"></i>

Paid

</span>

<?php elseif ($payment === 'partial'): ?>

<span class="status-badge status-partial">

<i class="bi bi-clock"></i>

Partial

</span>

<?php else: ?>

<span class="status-badge status-unpaid">

<i class="bi bi-exclamation-circle"></i>

Unpaid

</span>

<?php endif; ?>

</td>


<td>

<?php if ($sale === 'completed'): ?>

<span class="status-badge status-completed">

<i class="bi bi-check-circle"></i>

Completed

</span>

<?php elseif ($sale === 'voided'): ?>

<span class="status-badge status-voided">

<i class="bi bi-x-circle"></i>

Voided

</span>

<?php else: ?>

<span class="status-badge status-refunded">

<i class="bi bi-arrow-counterclockwise"></i>

Refunded

</span>

<?php endif; ?>

</td>


<td class="text-end pe-4">

<a
    href="index.php?page=pos_transactions&view=<?= (int)$transaction['id'] ?>"
    class="btn btn-sm btn-outline-primary rounded-3"
>

<i class="bi bi-eye me-1"></i>

View

</a>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>


<!-- PAGINATION -->

<?php if ($totalPages > 1): ?>

<div class="card-footer bg-body border-top p-3">

<div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">

<div class="text-muted small">

Page <?= $page ?>

of <?= $totalPages ?>

</div>

<nav>

<ul class="pagination pagination-sm mb-0">

<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">

<a
    class="page-link"
    href="<?= $page > 1 ? e(transactionPageUrl($page - 1)) : '#' ?>"
>

<i class="bi bi-chevron-left"></i>

</a>

</li>


<?php

$startPage = max(1, $page - 2);
$endPage = min($totalPages, $page + 2);

for (
    $paginationPage = $startPage;
    $paginationPage <= $endPage;
    $paginationPage++
):

?>

<li
    class="page-item <?= $paginationPage === $page ? 'active' : '' ?>"
>

<a
    class="page-link"
    href="<?= e(transactionPageUrl($paginationPage)) ?>"
>

<?= $paginationPage ?>

</a>

</li>

<?php endfor; ?>


<li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">

<a
    class="page-link"
    href="<?= $page < $totalPages ? e(transactionPageUrl($page + 1)) : '#' ?>"
>

<i class="bi bi-chevron-right"></i>

</a>

</li>

</ul>

</nav>

</div>

</div>

<?php endif; ?>

<?php endif; ?>

</div>

</div>

</main>

</div>


<!-- TRANSACTION DETAILS MODAL -->

<?php if ($selectedSale): ?>

<div
    class="modal fade"
    id="transactionModal"
    tabindex="-1"
    aria-hidden="true"
>

<div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">

<div class="modal-content">

<div class="modal-header">

<div>

<div class="invoice-title">

<?= e($selectedSale['invoice_number']) ?>

</div>

<div class="text-muted small">

<?= date(
    'M d, Y h:i A',
    strtotime($selectedSale['sale_date'])
) ?>

</div>

</div>

<button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal"
></button>

</div>


<div class="modal-body">


<!-- SALE INFORMATION -->

<div class="row g-3 mb-4">

<div class="col-12 col-md-4">

<div class="border rounded-3 p-3 h-100">

<div class="detail-label mb-1">

Customer

</div>

<div class="detail-value">

<?php

$detailCustomer =
    trim(
        $selectedSale['customer_name'] ?? ''
    );

echo e(
    $detailCustomer !== ''
        ? $detailCustomer
        : 'Walk-in Customer'
);

?>

</div>

<?php if (!empty($selectedSale['customer_phone'])): ?>

<div class="text-muted small mt-1">

<i class="bi bi-telephone me-1"></i>

<?= e($selectedSale['customer_phone']) ?>

</div>

<?php endif; ?>

<?php if (!empty($selectedSale['customer_email'])): ?>

<div class="text-muted small mt-1">

<i class="bi bi-envelope me-1"></i>

<?= e($selectedSale['customer_email']) ?>

</div>

<?php endif; ?>

</div>

</div>


<div class="col-12 col-md-4">

<div class="border rounded-3 p-3 h-100">

<div class="detail-label mb-1">

Payment Status

</div>

<div>

<?php

$detailPayment =
    strtolower(
        $selectedSale['payment_status'] ?? 'unpaid'
    );

?>

<?php if ($detailPayment === 'paid'): ?>

<span class="status-badge status-paid">

<i class="bi bi-check-circle"></i>

Paid

</span>

<?php elseif ($detailPayment === 'partial'): ?>

<span class="status-badge status-partial">

<i class="bi bi-clock"></i>

Partial

</span>

<?php else: ?>

<span class="status-badge status-unpaid">

<i class="bi bi-exclamation-circle"></i>

Unpaid

</span>

<?php endif; ?>

</div>

</div>

</div>


<div class="col-12 col-md-4">

<div class="border rounded-3 p-3 h-100">

<div class="detail-label mb-1">

Sale Status

</div>

<div>

<?php

$detailSaleStatus =
    strtolower(
        $selectedSale['sale_status'] ?? 'completed'
    );

?>

<?php if ($detailSaleStatus === 'completed'): ?>

<span class="status-badge status-completed">

<i class="bi bi-check-circle"></i>

Completed

</span>

<?php elseif ($detailSaleStatus === 'voided'): ?>

<span class="status-badge status-voided">

<i class="bi bi-x-circle"></i>

Voided

</span>

<?php else: ?>

<span class="status-badge status-refunded">

<i class="bi bi-arrow-counterclockwise"></i>

Refunded

</span>

<?php endif; ?>

</div>

</div>

</div>

</div>


<!-- ITEMS -->

<div class="mb-4">

<h6 class="fw-bold mb-3">

Items

</h6>

<div class="table-responsive border rounded-3">

<table class="table sale-item-table mb-0">

<thead class="table-light">

<tr>

<th class="ps-3">

Product

</th>

<th>

SKU

</th>

<th class="text-end">

Qty

</th>

<th class="text-end">

Unit Price

</th>

<th class="text-end">

Discount

</th>

<th class="text-end pe-3">

Total

</th>

</tr>

</thead>

<tbody>

<?php if (!$selectedItems): ?>

<tr>

<td
    colspan="6"
    class="text-center text-muted py-4"
>

No items found.

</td>

</tr>

<?php else: ?>

<?php foreach ($selectedItems as $item): ?>

<?php

$productName =
    $item['product_name']
    ??
    $item['current_product_name']
    ??
    'Product';

?>

<tr>

<td class="ps-3">

<div class="fw-semibold">

<?= e($productName) ?>

</div>

</td>

<td>

<?= e($item['sku'] ?? '-') ?>

</td>

<td class="text-end">

<?= number_format(
    (float)$item['quantity'],
    2
) ?>

</td>

<td class="text-end">

₱<?= number_format(
    (float)$item['unit_price'],
    2
) ?>

</td>

<td class="text-end">

₱<?= number_format(
    (float)$item['discount'],
    2
) ?>

</td>

<td class="text-end pe-3 fw-bold">

₱<?= number_format(
    (float)$item['total'],
    2
) ?>

</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

</tbody>

</table>

</div>

</div>


<!-- TOTALS -->

<div class="row justify-content-end">

<div class="col-12 col-md-5">

<div class="border rounded-3 p-3">

<div class="d-flex justify-content-between mb-2">

<span class="text-muted">

Subtotal

</span>

<span>

₱<?= number_format(
    (float)$selectedSale['subtotal'],
    2
) ?>

</span>

</div>


<div class="d-flex justify-content-between mb-2">

<span class="text-muted">

Discount

</span>

<span>

₱<?= number_format(
    (float)$selectedSale['discount'],
    2
) ?>

</span>

</div>


<div class="d-flex justify-content-between mb-2">

<span class="text-muted">

Tax

</span>

<span>

₱<?= number_format(
    (float)$selectedSale['tax'],
    2
) ?>

</span>

</div>


<hr>


<div class="d-flex justify-content-between mb-2">

<span class="fw-bold">

Total

</span>

<span class="fw-bold fs-5">

₱<?= number_format(
    (float)$selectedSale['total_amount'],
    2
) ?>

</span>

</div>


<div class="d-flex justify-content-between mb-2">

<span class="text-muted">

Amount Paid

</span>

<span>

₱<?= number_format(
    (float)$selectedSale['amount_paid'],
    2
) ?>

</span>

</div>


<div class="d-flex justify-content-between">

<span class="text-muted">

Change

</span>

<span>

₱<?= number_format(
    (float)$selectedSale['change_amount'],
    2
) ?>

</span>

</div>

<?php

$balance =
    max(
        0,
        (float)$selectedSale['total_amount']
        -
        (float)$selectedSale['amount_paid']
    );

?>

<?php if ($balance > 0): ?>

<hr>

<div class="d-flex justify-content-between">

<span class="fw-bold text-danger">

Balance Due

</span>

<span class="fw-bold text-danger">

₱<?= number_format(
    $balance,
    2
) ?>

</span>

</div>

<?php endif; ?>

</div>

</div>

</div>


<!-- PAYMENTS -->

<?php if ($selectedPayments): ?>

<div class="mt-4">

<h6 class="fw-bold mb-3">

Payment History

</h6>

<div class="table-responsive border rounded-3">

<table class="table table-sm mb-0">

<thead class="table-light">

<tr>

<th class="ps-3">

Date

</th>

<th>

Method

</th>

<th>

Reference

</th>

<th class="text-end pe-3">

Amount

</th>

</tr>

</thead>

<tbody>

<?php foreach ($selectedPayments as $payment): ?>

<tr>

<td class="ps-3">

<?= date(
    'M d, Y h:i A',
    strtotime(
        $payment['payment_date']
        ??
        $payment['created_at']
    )
) ?>

</td>

<td>

<?= e(
    ucwords(
        str_replace(
            '_',
            ' ',
            $payment['payment_method']
            ?? 'cash'
        )
    )
) ?>

</td>

<td>

<?= e(
    $payment['reference_number']
    ??
    '-'
) ?>

</td>

<td class="text-end pe-3 fw-semibold">

₱<?= number_format(
    (float)$payment['amount'],
    2
) ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

<?php endif; ?>


<?php if (!empty($selectedSale['notes'])): ?>

<div class="mt-4">

<h6 class="fw-bold mb-2">

Notes

</h6>

<div class="bg-body-tertiary rounded-3 p-3 small">

<?= nl2br(e($selectedSale['notes'])) ?>

</div>

</div>

<?php endif; ?>

</div>


<div class="modal-footer">

<button
    type="button"
    class="btn btn-secondary rounded-3"
    data-bs-dismiss="modal"
>

Close

</button>

<button
    type="button"
    class="btn btn-primary rounded-3"
    onclick="window.print()"
>

<i class="bi bi-printer me-1"></i>

Print

</button>

</div>

</div>

</div>

</div>

<?php endif; ?>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>

<?php if ($selectedSale): ?>

<script>

document.addEventListener('DOMContentLoaded', function () {

    const modalElement =
        document.getElementById('transactionModal');

    if (modalElement) {

        const modal =
            new bootstrap.Modal(modalElement);

        modal.show();

    }

});

</script>

<?php endif; ?>

</body>

</html>