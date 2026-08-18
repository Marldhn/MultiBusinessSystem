<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/db.php';

if (!isset($pdo)) {
    $pdo = Database::getConnection();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$businessId = (int)($_SESSION['business_id'] ?? 0);
$businessName = $_SESSION['business_name'] ?? 'Business';

if (!$businessId) {
    header('Location: index.php?page=select_business');
    exit;
}

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$paymentMethod = trim($_GET['payment_method'] ?? '');

$page = max(1, (int)($_GET['page_no'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

/*
|--------------------------------------------------------------------------
| PAYMENT METHODS
|--------------------------------------------------------------------------
*/

$paymentMethods = [];

try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT payment_method
        FROM pos_sales
        WHERE business_id = ?
        AND payment_method IS NOT NULL
        AND payment_method <> ''
        ORDER BY payment_method ASC
    ");

    $stmt->execute([$businessId]);

    $paymentMethods = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (Throwable $e) {
    $paymentMethods = [];
}

/*
|--------------------------------------------------------------------------
| BUILD FILTER
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
        OR s.customer_name LIKE ?
    )";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
}

if ($dateFrom !== '') {

    $where[] = "DATE(s.created_at) >= ?";

    $params[] = $dateFrom;
}

if ($dateTo !== '') {

    $where[] = "DATE(s.created_at) <= ?";

    $params[] = $dateTo;
}

if ($paymentMethod !== '') {

    $where[] = "s.payment_method = ?";

    $params[] = $paymentMethod;
}

$whereSql = implode(' AND ', $where);

/*
|--------------------------------------------------------------------------
| TOTAL SALES COUNT
|--------------------------------------------------------------------------
*/

$totalSales = 0;

try {

    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM pos_sales s
        WHERE $whereSql
    ");

    $countStmt->execute($params);

    $totalSales = (int)$countStmt->fetchColumn();

} catch (Throwable $e) {

    $error = $e->getMessage();

}

/*
|--------------------------------------------------------------------------
| TOTAL PAGES
|--------------------------------------------------------------------------
*/

$totalPages = max(
    1,
    (int)ceil($totalSales / $perPage)
);

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

/*
|--------------------------------------------------------------------------
| SALES HISTORY
|--------------------------------------------------------------------------
*/

$sales = [];

try {

    $salesStmt = $pdo->prepare("
        SELECT
            s.*
        FROM pos_sales s
        WHERE $whereSql
        ORDER BY s.id DESC
        LIMIT $perPage OFFSET $offset
    ");

    $salesStmt->execute($params);

    $sales = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $error = $e->getMessage();

}

/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

$summary = [
    'sales_count' => 0,
    'gross_sales' => 0,
    'cash_received' => 0
];

try {

    $summaryStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS sales_count,
            COALESCE(SUM(s.total_amount), 0) AS gross_sales,
            COALESCE(SUM(s.amount_paid), 0) AS cash_received
        FROM pos_sales s
        WHERE $whereSql
    ");

    $summaryStmt->execute($params);

    $summaryResult = $summaryStmt->fetch(PDO::FETCH_ASSOC);

    if ($summaryResult) {
        $summary = $summaryResult;
    }

} catch (Throwable $e) {
    // Keep default summary values.
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function money($value)
{
    return '₱' . number_format(
        (float)$value,
        2
    );
}

function safe($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function paymentBadge($method)
{
    $method = strtolower(trim((string)$method));

    if ($method === 'cash') {

        return '
            <span class="payment-badge payment-cash">
                <i class="bi bi-cash-coin me-1"></i>
                Cash
            </span>
        ';

    }

    if ($method === 'card') {

        return '
            <span class="payment-badge payment-card">
                <i class="bi bi-credit-card me-1"></i>
                Card
            </span>
        ';

    }

    if (
        $method === 'gcash' ||
        $method === 'maya' ||
        $method === 'ewallet'
    ) {

        return '
            <span class="payment-badge payment-ewallet">
                <i class="bi bi-phone me-1"></i>
                ' . safe(ucfirst($method)) . '
            </span>
        ';

    }

    return '
        <span class="payment-badge payment-other">
            ' . safe(
                $method !== ''
                    ? ucfirst($method)
                    : 'Unknown'
            ) . '
        </span>
    ';
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

<title>
    Sales History - <?= safe($businessName) ?>
</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
    rel="stylesheet"
>

<style>

body {
    min-height: 100vh;
    overflow-x: hidden;
}

.pos-main {
    min-width: 0;
    width: 100%;
}

.page-header {
    margin-bottom: 24px;
}

.page-header h2 {
    font-size: 1.5rem;
}

.summary-card {
    border: 0;
    border-radius: 16px;
    transition: transform .15s ease,
                box-shadow .15s ease;
}

.summary-card:hover {
    transform: translateY(-2px);
}

.summary-icon {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 13px;
}

.filter-card {
    border: 0;
    border-radius: 16px;
}

.history-card {
    border: 0;
    border-radius: 16px;
    overflow: hidden;
}

.history-header {
    padding: 20px 24px;
}

.sales-table {
    width: 100%;
}

.sales-table th {
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    white-space: nowrap;
}

.sales-table td {
    vertical-align: middle;
    white-space: nowrap;
}

.invoice-number {
    font-weight: 700;
}

.customer-name {
    font-weight: 600;
}

.customer-subtext {
    font-size: .7rem;
    color: var(--bs-secondary-color);
}

.sale-total {
    font-weight: 700;
}

.payment-badge {
    display: inline-flex;
    align-items: center;
    padding: .35rem .65rem;
    border-radius: 50rem;
    font-size: .7rem;
    font-weight: 700;
}

.payment-cash {
    background: rgba(25, 135, 84, .12);
    color: var(--bs-success);
}

.payment-card {
    background: rgba(13, 110, 253, .12);
    color: var(--bs-primary);
}

.payment-ewallet {
    background: rgba(111, 66, 193, .12);
    color: #6f42c1;
}

.payment-other {
    background: rgba(108, 117, 125, .12);
    color: var(--bs-secondary-color);
}

.empty-state {
    padding: 60px 20px;
}

.filter-input {
    border-radius: 10px;
}

.pagination .page-link {
    border-radius: 8px;
    margin: 0 2px;
}

@media (max-width: 575.98px) {

    .pos-main > .p-3,
    .pos-main > .p-md-4 {
        padding: 14px !important;
    }

    .page-header h2 {
        font-size: 1.35rem;
    }

    .history-header {
        padding: 15px;
    }

}

</style>

</head>

<body class="bg-body-tertiary">

<div class="d-flex flex-column flex-lg-row min-vh-100">

<?php

/*
|--------------------------------------------------------------------------
| POS SIDEBAR
|--------------------------------------------------------------------------
*/

$sidebarPath =
    __DIR__ .
    '/../../../resources/partials/POSSidebar.php';

if (file_exists($sidebarPath)) {
    include $sidebarPath;
}

?>

<main class="pos-main flex-grow-1 bg-body-tertiary">

<div class="p-3 p-md-4">

<!-- =====================================================
     HEADER
====================================================== -->

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

<div>

<div class="d-flex align-items-center gap-2 mb-1">

<div class="d-lg-none bg-primary bg-opacity-10 text-primary rounded-3 p-2">

<i class="bi bi-receipt"></i>

</div>

<h2 class="fw-bold text-body mb-0">
Sales History
</h2>

</div>

<p class="text-muted small mb-0">

View and manage sales transactions for

<span class="fw-semibold text-primary">
<?= safe($businessName) ?>
</span>

</p>

</div>

</div>

<!-- =====================================================
     ERROR
====================================================== -->

<?php if ($error !== ''): ?>

<div
    class="alert alert-danger border-0 shadow-sm rounded-3 small"
>

<i class="bi bi-exclamation-triangle-fill me-2"></i>

<?= safe($error) ?>

</div>

<?php endif; ?>

<!-- =====================================================
     SUMMARY
====================================================== -->

<div class="row g-3 mb-4">

<div class="col-12 col-md-4">

<div class="card summary-card shadow-sm bg-body h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start">

<div>

<div class="text-muted small fw-semibold mb-2">
Total Transactions
</div>

<div class="fs-3 fw-bold">
<?= number_format((int)$summary['sales_count']) ?>
</div>

<div class="small text-muted mt-1">
Transactions matching filters
</div>

</div>

<div class="summary-icon bg-primary bg-opacity-10 text-primary">

<i class="bi bi-receipt fs-4"></i>

</div>

</div>

</div>

</div>

</div>


<div class="col-12 col-md-4">

<div class="card summary-card shadow-sm bg-body h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start">

<div>

<div class="text-muted small fw-semibold mb-2">
Total Sales
</div>

<div class="fs-3 fw-bold text-success">
<?= money($summary['gross_sales']) ?>
</div>

<div class="small text-muted mt-1">
Sales amount
</div>

</div>

<div class="summary-icon bg-success bg-opacity-10 text-success">

<i class="bi bi-graph-up-arrow fs-4"></i>

</div>

</div>

</div>

</div>

</div>


<div class="col-12 col-md-4">

<div class="card summary-card shadow-sm bg-body h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start">

<div>

<div class="text-muted small fw-semibold mb-2">
Amount Paid
</div>

<div class="fs-3 fw-bold text-primary">
<?= money($summary['cash_received']) ?>
</div>

<div class="small text-muted mt-1">
Payments received
</div>

</div>

<div class="summary-icon bg-info bg-opacity-10 text-info">

<i class="bi bi-wallet2 fs-4"></i>

</div>

</div>

</div>

</div>

</div>

</div>

<!-- =====================================================
     FILTERS
====================================================== -->

<div class="card filter-card shadow-sm mb-4">

<div class="card-body p-3 p-md-4">

<form method="GET">

<input
    type="hidden"
    name="page"
    value="sales_history"
>

<div class="row g-3">

<div class="col-12 col-lg-4">

<label class="form-label small fw-semibold">
Search
</label>

<div class="input-group">

<span class="input-group-text bg-body">

<i class="bi bi-search"></i>

</span>

<input
    type="text"
    name="search"
    class="form-control filter-input"
    value="<?= safe($search) ?>"
    placeholder="Invoice number or customer..."
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
    class="form-control filter-input"
    value="<?= safe($dateFrom) ?>"
>

</div>


<div class="col-12 col-sm-6 col-lg-2">

<label class="form-label small fw-semibold">
To
</label>

<input
    type="date"
    name="date_to"
    class="form-control filter-input"
    value="<?= safe($dateTo) ?>"
>

</div>


<div class="col-12 col-sm-6 col-lg-2">

<label class="form-label small fw-semibold">
Payment
</label>

<select
    name="payment_method"
    class="form-select filter-input"
>

<option value="">
All Payments
</option>

<?php foreach ($paymentMethods as $method): ?>

<option
    value="<?= safe($method) ?>"
    <?= $paymentMethod === $method ? 'selected' : '' ?>
>

<?= safe(ucfirst($method)) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="col-12 col-sm-6 col-lg-2 d-flex align-items-end">

<div class="d-flex gap-2 w-100">

<button
    type="submit"
    class="btn btn-primary fw-bold rounded-3 flex-grow-1"
>

<i class="bi bi-search me-1"></i>

Filter

</button>

<a
    href="index.php?page=sales_history"
    class="btn btn-outline-secondary rounded-3"
    title="Clear filters"
>

<i class="bi bi-arrow-counterclockwise"></i>

</a>

</div>

</div>

</div>

</form>

</div>

</div>

<!-- =====================================================
     SALES TABLE
====================================================== -->

<div class="card history-card shadow-sm bg-body">

<div class="history-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">

<div>

<h5 class="fw-bold mb-1">
Sales Transactions
</h5>

<p class="text-muted small mb-0">

Showing

<?= number_format(count($sales)) ?>

of

<?= number_format($totalSales) ?>

transactions

</p>

</div>

</div>


<?php if (!$sales): ?>

<div class="empty-state text-center text-muted">

<div class="mb-3">

<i class="bi bi-receipt display-5 opacity-50"></i>

</div>

<div class="fw-semibold mb-1">
No sales found
</div>

<div class="small">
There are no sales transactions matching your filters.
</div>

</div>

<?php else: ?>

<div class="table-responsive">

<table class="table table-hover sales-table align-middle mb-0">

<thead class="table-light">

<tr>

<th class="ps-4 py-3">
#
</th>

<th class="py-3">
Invoice
</th>

<th class="py-3">
Customer
</th>

<th class="py-3">
Date
</th>

<th class="py-3">
Payment
</th>

<th class="py-3">
Total
</th>

<th class="py-3">
Paid
</th>

<th class="py-3">
Change
</th>

<th class="py-3 pe-4 text-end">
Action
</th>

</tr>

</thead>

<tbody>

<?php foreach ($sales as $sale): ?>

<?php

$invoiceNumber =
    $sale['invoice_number']
    ?? ('SALE-' . $sale['id']);

$customerName =
    $sale['customer_name']
    ?? 'Walk-in Customer';

$createdAt =
    $sale['created_at']
    ?? null;

$totalAmount =
    (float)($sale['total_amount'] ?? 0);

$amountPaid =
    (float)($sale['amount_paid'] ?? 0);

$changeAmount =
    (float)($sale['change_amount'] ?? 0);

$method =
    $sale['payment_method']
    ?? 'cash';

?>

<tr>

<td class="ps-4">

<span class="text-muted small">
#<?= (int)$sale['id'] ?>
</span>

</td>


<td>

<div class="invoice-number">

<?= safe($invoiceNumber) ?>

</div>

</td>


<td>

<div class="customer-name">

<?= safe($customerName) ?>

</div>

</td>


<td>

<?php if ($createdAt): ?>

<div class="small">

<?= date(
    'M d, Y',
    strtotime($createdAt)
) ?>

</div>

<div class="customer-subtext">

<?= date(
    'h:i A',
    strtotime($createdAt)
) ?>

</div>

<?php else: ?>

<span class="text-muted">
-
</span>

<?php endif; ?>

</td>


<td>

<?= paymentBadge($method) ?>

</td>


<td>

<span class="sale-total">

<?= money($totalAmount) ?>

</span>

</td>


<td>

<span class="small fw-semibold">

<?= money($amountPaid) ?>

</span>

</td>


<td>

<span class="small">

<?= money($changeAmount) ?>

</span>

</td>


<td class="text-end pe-4">

<button
    type="button"
    class="btn btn-sm btn-outline-primary rounded-3"
    onclick="viewSale(<?= htmlspecialchars(
        json_encode(
            $sale,
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_QUOT |
            JSON_HEX_AMP
        ),
        ENT_QUOTES,
        'UTF-8'
    ) ?>)"
>

<i class="bi bi-eye me-1"></i>

View

</button>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

<!-- =====================================================
     PAGINATION
====================================================== -->

<?php if ($totalPages > 1): ?>

<div class="p-3 border-top">

<nav>

<ul class="pagination justify-content-center mb-0">

<?php if ($page > 1): ?>

<li class="page-item">

<a
    class="page-link"
    href="<?= safe(
        'index.php?' .
        http_build_query([
            'page' => 'sales_history',
            'search' => $search,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'payment_method' => $paymentMethod,
            'page_no' => $page - 1
        ])
    ) ?>"
>

<i class="bi bi-chevron-left"></i>

</a>

</li>

<?php endif; ?>


<?php

$startPage = max(1, $page - 2);
$endPage = min($totalPages, $page + 2);

for ($i = $startPage; $i <= $endPage; $i++):
?>

<li
    class="page-item <?= $i === $page ? 'active' : '' ?>"
>

<a
    class="page-link"
    href="<?= safe(
        'index.php?' .
        http_build_query([
            'page' => 'sales_history',
            'search' => $search,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'payment_method' => $paymentMethod,
            'page_no' => $i
        ])
    ) ?>"
>

<?= $i ?>

</a>

</li>

<?php endfor; ?>


<?php if ($page < $totalPages): ?>

<li class="page-item">

<a
    class="page-link"
    href="<?= safe(
        'index.php?' .
        http_build_query([
            'page' => 'sales_history',
            'search' => $search,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'payment_method' => $paymentMethod,
            'page_no' => $page + 1
        ])
    ) ?>"
>

<i class="bi bi-chevron-right"></i>

</a>

</li>

<?php endif; ?>

</ul>

</nav>

</div>

<?php endif; ?>

</div>

</div>

</main>

</div>


<!-- =========================================================
     VIEW SALE MODAL
========================================================= -->

<div
    class="modal fade"
    id="saleDetailsModal"
    tabindex="-1"
>

<div class="modal-dialog modal-dialog-centered">

<div class="modal-content border-0 rounded-4 shadow-lg">

<div class="modal-header">

<h5 class="modal-title fw-bold">

<i class="bi bi-receipt text-primary me-2"></i>

Sale Details

</h5>

<button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal"
></button>

</div>

<div class="modal-body">

<div class="row g-3">

<div class="col-6">

<div class="text-muted small">
Invoice
</div>

<div
    id="viewInvoice"
    class="fw-bold"
>
-
</div>

</div>


<div class="col-6">

<div class="text-muted small">
Customer
</div>

<div
    id="viewCustomer"
    class="fw-bold"
>
-
</div>

</div>


<div class="col-6">

<div class="text-muted small">
Payment Method
</div>

<div
    id="viewPayment"
    class="fw-bold"
>
-
</div>

</div>


<div class="col-6">

<div class="text-muted small">
Date
</div>

<div
    id="viewDate"
    class="fw-bold"
>
-
</div>

</div>


<div class="col-12">

<hr>

</div>


<div class="col-6">

<div class="text-muted small">
Total
</div>

<div
    id="viewTotal"
    class="fw-bold fs-5 text-success"
>
-
</div>

</div>


<div class="col-6">

<div class="text-muted small">
Amount Paid
</div>

<div
    id="viewPaid"
    class="fw-bold fs-5"
>
-
</div>

</div>


<div class="col-6">

<div class="text-muted small">
Change
</div>

<div
    id="viewChange"
    class="fw-bold"
>
-
</div>

</div>

</div>

</div>

<div class="modal-footer">

<button
    type="button"
    class="btn btn-light rounded-3"
    data-bs-dismiss="modal"
>

Close

</button>

</div>

</div>

</div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>

function money(value)
{
    const number = parseFloat(value || 0);

    return '₱' + number.toLocaleString(
        undefined,
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }
    );
}


function viewSale(sale)
{
    document.getElementById('viewInvoice').textContent =
        sale.invoice_number ||
        ('SALE-' + sale.id);

    document.getElementById('viewCustomer').textContent =
        sale.customer_name ||
        'Walk-in Customer';

    document.getElementById('viewPayment').textContent =
        sale.payment_method ||
        'Cash';

    let dateText = '-';

    if (sale.created_at) {

        const date = new Date(
            sale.created_at.replace(' ', 'T')
        );

        if (!isNaN(date.getTime())) {

            dateText =
                date.toLocaleDateString(
                    undefined,
                    {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    }
                ) +
                ' ' +
                date.toLocaleTimeString(
                    undefined,
                    {
                        hour: 'numeric',
                        minute: '2-digit'
                    }
                );

        } else {

            dateText = sale.created_at;

        }
    }

    document.getElementById('viewDate').textContent =
        dateText;

    document.getElementById('viewTotal').textContent =
        money(sale.total_amount);

    document.getElementById('viewPaid').textContent =
        money(sale.amount_paid);

    document.getElementById('viewChange').textContent =
        money(sale.change_amount);

    bootstrap.Modal
        .getOrCreateInstance(
            document.getElementById('saleDetailsModal')
        )
        .show();
}

</script>

</body>

</html>