
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

$activePage = 'pos_dashboard';
$pageTitle = 'POS Dashboard';

/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$todaySales = 0;
$todayTransactions = 0;
$todayItems = 0;
$totalProducts = 0;
$lowStockProducts = 0;
$outOfStockProducts = 0;
$inventoryValue = 0;

$recentTransactions = [];
$topProducts = [];
$lowStockList = [];
$paymentSummary = [];
$salesChart = [];
$recentExpenses = [];

/*
|--------------------------------------------------------------------------
| TODAY'S SALES
|--------------------------------------------------------------------------
*/

try {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS transaction_count,
            COALESCE(SUM(total_amount), 0) AS sales_total
        FROM pos_sales
        WHERE business_id = ?
        AND DATE(sale_date) = CURDATE()
        AND sale_status = 'completed'
    ");

    $stmt->execute([$businessId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $todayTransactions = (int)($row['transaction_count'] ?? 0);
        $todaySales = (float)($row['sales_total'] ?? 0);
    }
} catch (Throwable $e) {
    $todayTransactions = 0;
    $todaySales = 0;
}

/*
|--------------------------------------------------------------------------
| TODAY'S ITEMS SOLD
|--------------------------------------------------------------------------
*/

try {
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(si.quantity), 0) AS total_items
        FROM pos_sale_items si
        INNER JOIN pos_sales s
            ON s.id = si.sale_id
        WHERE s.business_id = ?
        AND DATE(s.sale_date) = CURDATE()
        AND s.sale_status = 'completed'
    ");

    $stmt->execute([$businessId]);

    $todayItems = (int)($stmt->fetchColumn() ?? 0);
} catch (Throwable $e) {
    $todayItems = 0;
}

/*
|--------------------------------------------------------------------------
| INVENTORY SUMMARY
|--------------------------------------------------------------------------
*/

try {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_products,

            COALESCE(SUM(
                CASE
                    WHEN current_stock <= 0 THEN 1
                    ELSE 0
                END
            ), 0) AS out_of_stock,

            COALESCE(SUM(
                CASE
                    WHEN current_stock > 0
                    AND current_stock <= minimum_stock
                    THEN 1
                    ELSE 0
                END
            ), 0) AS low_stock,

            COALESCE(SUM(
                current_stock * cost_price
            ), 0) AS inventory_value

        FROM pos_products
        WHERE business_id = ?
        AND status = 'active'
    ");

    $stmt->execute([$businessId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $totalProducts = (int)($row['total_products'] ?? 0);
        $lowStockProducts = (int)($row['low_stock'] ?? 0);
        $outOfStockProducts = (int)($row['out_of_stock'] ?? 0);
        $inventoryValue = (float)($row['inventory_value'] ?? 0);
    }
} catch (Throwable $e) {
    $totalProducts = 0;
    $lowStockProducts = 0;
    $outOfStockProducts = 0;
    $inventoryValue = 0;
}

/*
|--------------------------------------------------------------------------
| RECENT TRANSACTIONS
|--------------------------------------------------------------------------
*/

try {
    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.invoice_number,
            s.sale_date,
            s.total_amount,
            s.amount_paid,
            s.change_amount,
            s.payment_status,
            s.sale_status,
            c.first_name,
            c.last_name
        FROM pos_sales s
        LEFT JOIN pos_customers c
            ON c.id = s.customer_id
            AND c.business_id = s.business_id
        WHERE s.business_id = ?
        ORDER BY s.sale_date DESC
        LIMIT 8
    ");

    $stmt->execute([$businessId]);

    $recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $recentTransactions = [];
}

/*
|--------------------------------------------------------------------------
| TOP SELLING PRODUCTS
|--------------------------------------------------------------------------
*/

try {
    $stmt = $pdo->prepare("
        SELECT
            si.product_id,
            si.product_name,
            SUM(si.quantity) AS quantity_sold,
            SUM(si.total) AS sales_amount
        FROM pos_sale_items si

        INNER JOIN pos_sales s
            ON s.id = si.sale_id

        WHERE s.business_id = ?
        AND s.sale_status = 'completed'

        GROUP BY
            si.product_id,
            si.product_name

        ORDER BY
            quantity_sold DESC

        LIMIT 5
    ");

    $stmt->execute([$businessId]);

    $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $topProducts = [];
}

/*
|--------------------------------------------------------------------------
| LOW STOCK PRODUCTS
|--------------------------------------------------------------------------
*/

try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            sku,
            current_stock,
            minimum_stock,
            maximum_stock,
            unit_id
        FROM pos_products
        WHERE business_id = ?
        AND status = 'active'
        AND current_stock <= minimum_stock
        ORDER BY
            current_stock ASC,
            name ASC
        LIMIT 8
    ");

    $stmt->execute([$businessId]);

    $lowStockList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $lowStockList = [];
}

/*
|--------------------------------------------------------------------------
| PAYMENT SUMMARY TODAY
|--------------------------------------------------------------------------
*/

try {
    $stmt = $pdo->prepare("
        SELECT
            payment_method,
            COALESCE(SUM(amount), 0) AS total_amount
        FROM pos_payments
        WHERE business_id = ?
        AND DATE(payment_date) = CURDATE()
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ");

    $stmt->execute([$businessId]);

    $paymentSummary = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $paymentSummary = [];
}

/*
|--------------------------------------------------------------------------
| 7-DAY SALES
|--------------------------------------------------------------------------
*/

try {
    $stmt = $pdo->prepare("
        SELECT
            DATE(sale_date) AS sale_day,
            COALESCE(SUM(total_amount), 0) AS total_sales,
            COUNT(*) AS transaction_count
        FROM pos_sales
        WHERE business_id = ?
        AND sale_status = 'completed'
        AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(sale_date)
        ORDER BY sale_day ASC
    ");

    $stmt->execute([$businessId]);

    $salesRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $salesMap = [];

    foreach ($salesRows as $row) {
        $salesMap[$row['sale_day']] = [
            'sales' => (float)$row['total_sales'],
            'transactions' => (int)$row['transaction_count']
        ];
    }

    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));

        $salesChart[] = [
            'date' => $date,
            'label' => date('M d', strtotime($date)),
            'sales' => $salesMap[$date]['sales'] ?? 0,
            'transactions' => $salesMap[$date]['transactions'] ?? 0
        ];
    }
} catch (Throwable $e) {
    $salesChart = [];
}

/*
|--------------------------------------------------------------------------
| RECENT EXPENSES
|--------------------------------------------------------------------------
*/

try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            description,
            amount,
            expense_date,
            category
        FROM pos_expenses
        WHERE business_id = ?
        ORDER BY expense_date DESC, id DESC
        LIMIT 5
    ");

    $stmt->execute([$businessId]);

    $recentExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $recentExpenses = [];
}

/*
|--------------------------------------------------------------------------
| PAYMENT LABELS
|--------------------------------------------------------------------------
*/

$paymentLabels = [
    'cash' => 'Cash',
    'card' => 'Card',
    'gcash' => 'GCash',
    'bank_transfer' => 'Bank Transfer',
    'other' => 'Other'
];

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

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
    overflow-x: hidden;
}

.pos-main {
    min-width: 0;
    width: 100%;
}

.pos-header h2 {
    font-size: 1.5rem;
}

.stat-card {
    border: 0;
    border-radius: 16px;
    transition: transform .15s ease, box-shadow .15s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pos-card {
    border: 0;
    border-radius: 16px;
}

.pos-card-header {
    padding: 18px 20px;
    border-bottom: 1px solid var(--bs-border-color);
}

.quick-action {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 14px;
    border: 1px solid var(--bs-border-color);
    border-radius: 12px;
    text-decoration: none;
    color: var(--bs-body-color);
    transition: all .15s ease;
}

.quick-action:hover {
    border-color: var(--bs-primary);
    background: var(--bs-primary-bg-subtle);
    color: var(--bs-primary);
    transform: translateY(-1px);
}

.quick-action-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bs-primary-bg-subtle);
    color: var(--bs-primary);
    flex-shrink: 0;
}

.pos-table th {
    font-size: .7rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--bs-secondary-color);
    white-space: nowrap;
}

.pos-table td {
    vertical-align: middle;
    white-space: nowrap;
}

.transaction-reference {
    font-weight: 700;
    font-size: .82rem;
}

.transaction-date {
    font-size: .7rem;
    color: var(--bs-secondary-color);
}

.pos-status {
    display: inline-flex;
    align-items: center;
    padding: .3rem .6rem;
    border-radius: 50rem;
    font-size: .68rem;
    font-weight: 700;
}

.pos-status-success {
    background: rgba(25, 135, 84, .12);
    color: var(--bs-success);
}

.pos-status-warning {
    background: rgba(255, 193, 7, .15);
    color: var(--bs-warning-text);
}

.pos-status-danger {
    background: rgba(220, 53, 69, .12);
    color: var(--bs-danger);
}

.low-stock-item {
    padding: 13px 0;
    border-bottom: 1px solid var(--bs-border-color);
}

.low-stock-item:last-child {
    border-bottom: 0;
}

.stock-number {
    font-weight: 700;
}

.stock-danger {
    color: var(--bs-danger);
}

.stock-warning {
    color: var(--bs-warning-text);
}

.dashboard-empty {
    padding: 35px 20px;
}

.sales-chart {
    height: 250px;
    display: flex;
    align-items: flex-end;
    gap: 12px;
    padding: 20px 10px 10px;
}

.sales-bar-wrapper {
    flex: 1;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    align-items: center;
    gap: 6px;
}

.sales-bar {
    width: 100%;
    max-width: 48px;
    min-height: 3px;
    border-radius: 7px 7px 2px 2px;
    background: var(--bs-primary);
    transition: height .3s ease;
}

.sales-bar:hover {
    opacity: .8;
}

.sales-label {
    font-size: .65rem;
    color: var(--bs-secondary-color);
    white-space: nowrap;
}

.sales-value {
    font-size: .62rem;
    font-weight: 700;
    color: var(--bs-body-color);
}

.payment-item {
    padding: 12px 0;
    border-bottom: 1px solid var(--bs-border-color);
}

.payment-item:last-child {
    border-bottom: 0;
}

.payment-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bs-primary-bg-subtle);
    color: var(--bs-primary);
}

.expense-item {
    padding: 12px 0;
    border-bottom: 1px solid var(--bs-border-color);
}

.expense-item:last-child {
    border-bottom: 0;
}

@media (max-width: 575.98px) {

    .pos-header h2 {
        font-size: 1.3rem;
    }

    .stat-card,
    .pos-card {
        border-radius: 14px;
    }

    .sales-chart {
        gap: 5px;
    }

}

</style>

</head>

<body class="bg-body-tertiary">

<div class="d-flex flex-column flex-lg-row min-vh-100">

<?php

$sidebarPath =
    __DIR__ . '/../../resources/partials/POSSidebar.php';

if (file_exists($sidebarPath)) {
    include $sidebarPath;
}

?>

<main class="pos-main flex-grow-1 bg-body-tertiary">

<div class="p-3 p-md-4">

<!-- HEADER -->

<div class="pos-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

<div>

<div class="d-flex align-items-center gap-2 mb-1">

<div class="d-lg-none bg-primary bg-opacity-10 text-primary rounded-3 p-2">
<i class="bi bi-cart3"></i>
</div>

<h2 class="fw-bold text-body mb-0">
POS Dashboard
</h2>

</div>

<p class="text-muted small mb-0">

Welcome back to

<span class="fw-semibold text-primary">
<?= htmlspecialchars($businessName) ?>
</span>

</p>

</div>

<div class="d-flex gap-2">

<a
    href="index.php?page=pos_sales"
    class="btn btn-primary fw-bold rounded-3"
>

<i class="bi bi-cart-plus me-1"></i>
New Sale

</a>

</div>

</div>

<!-- STATISTICS -->

<div class="row g-3 mb-4">

<div class="col-12 col-sm-6 col-xl-3">

<div class="card stat-card shadow-sm h-100 bg-body">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start">

<div>

<div class="text-muted small fw-semibold mb-2">
Today's Sales
</div>

<div class="fs-3 fw-bold">
₱<?= number_format($todaySales, 2) ?>
</div>

<div class="small text-muted mt-1">
Completed sales today
</div>

</div>

<div class="stat-icon bg-success bg-opacity-10 text-success">
<i class="bi bi-cash-stack fs-4"></i>
</div>

</div>

</div>

</div>

</div>

<div class="col-12 col-sm-6 col-xl-3">

<div class="card stat-card shadow-sm h-100 bg-body">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start">

<div>

<div class="text-muted small fw-semibold mb-2">
Transactions
</div>

<div class="fs-3 fw-bold">
<?= number_format($todayTransactions) ?>
</div>

<div class="small text-muted mt-1">
Completed today
</div>

</div>

<div class="stat-icon bg-primary bg-opacity-10 text-primary">
<i class="bi bi-receipt fs-4"></i>
</div>

</div>

</div>

</div>

</div>

<div class="col-12 col-sm-6 col-xl-3">

<div class="card stat-card shadow-sm h-100 bg-body">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start">

<div>

<div class="text-muted small fw-semibold mb-2">
Items Sold
</div>

<div class="fs-3 fw-bold">
<?= number_format($todayItems) ?>
</div>

<div class="small text-muted mt-1">
Units sold today
</div>

</div>

<div class="stat-icon bg-info bg-opacity-10 text-info">
<i class="bi bi-box-seam fs-4"></i>
</div>

</div>

</div>

</div>

</div>

<div class="col-12 col-sm-6 col-xl-3">

<div class="card stat-card shadow-sm h-100 bg-body">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start">

<div>

<div class="text-muted small fw-semibold mb-2">
Low Stock
</div>

<div class="fs-3 fw-bold text-warning">
<?= number_format($lowStockProducts) ?>
</div>

<div class="small text-muted mt-1">
Products needing attention
</div>

</div>

<div class="stat-icon bg-warning bg-opacity-10 text-warning">
<i class="bi bi-exclamation-triangle fs-4"></i>
</div>

</div>

</div>

</div>

</div>

</div>

<!-- QUICK ACTIONS -->

<div class="card pos-card shadow-sm bg-body mb-4">

<div class="pos-card-header">

<h5 class="fw-bold mb-1">
Quick Actions
</h5>

<p class="text-muted small mb-0">
Frequently used POS functions
</p>

</div>

<div class="card-body p-3">

<div class="row g-3">

<div class="col-12 col-sm-6 col-lg-3">

<a href="index.php?page=pos_sales" class="quick-action">

<div class="quick-action-icon">
<i class="bi bi-cart-plus fs-5"></i>
</div>

<div>
<div class="fw-bold small">New Sale</div>
<div class="text-muted" style="font-size:.7rem;">
Start a transaction
</div>
</div>

</a>

</div>

<div class="col-12 col-sm-6 col-lg-3">

<a href="index.php?page=pos_transactions" class="quick-action">

<div class="quick-action-icon">
<i class="bi bi-receipt fs-5"></i>
</div>

<div>
<div class="fw-bold small">Transactions</div>
<div class="text-muted" style="font-size:.7rem;">
View sales history
</div>
</div>

</a>

</div>

<div class="col-12 col-sm-6 col-lg-3">

<a href="index.php?page=pos_products" class="quick-action">

<div class="quick-action-icon">
<i class="bi bi-box-seam fs-5"></i>
</div>

<div>
<div class="fw-bold small">Products</div>
<div class="text-muted" style="font-size:.7rem;">
Manage POS products
</div>
</div>

</a>

</div>

<div class="col-12 col-sm-6 col-lg-3">

<a href="index.php?page=pos_stock_adjustments" class="quick-action">

<div class="quick-action-icon">
<i class="bi bi-boxes fs-5"></i>
</div>

<div>
<div class="fw-bold small">Stock Adjustment</div>
<div class="text-muted" style="font-size:.7rem;">
Adjust inventory
</div>
</div>

</a>

</div>

</div>

</div>

</div>

<!-- SALES OVERVIEW -->

<div class="card pos-card shadow-sm bg-body mb-4">

<div class="pos-card-header d-flex justify-content-between align-items-center">

<div>

<h5 class="fw-bold mb-1">
Sales Overview
</h5>

<p class="text-muted small mb-0">
Sales performance for the last 7 days
</p>

</div>

<div class="text-end">

<div class="small text-muted">
Today
</div>

<div class="fw-bold">
₱<?= number_format($todaySales, 2) ?>
</div>

</div>

</div>

<div class="card-body">

<?php

$maxSales = 0;

foreach ($salesChart as $day) {
    $maxSales = max($maxSales, (float)$day['sales']);
}

?>

<?php if (!$salesChart || $maxSales <= 0): ?>

<div class="dashboard-empty text-center">

<i class="bi bi-bar-chart display-6 text-muted opacity-50"></i>

<div class="fw-semibold mt-3">
No sales data yet
</div>

<div class="text-muted small">
Completed sales will appear in this chart.

</div>

</div>

<?php else: ?>

<div class="sales-chart">

<?php foreach ($salesChart as $day): ?>

<?php

$height = $maxSales > 0
    ? ($day['sales'] / $maxSales) * 100
    : 0;

?>

<div
    class="sales-bar-wrapper"
    title="<?= htmlspecialchars($day['label']) ?>: ₱<?= number_format($day['sales'], 2) ?>"
>

<div class="sales-value">
₱<?= number_format($day['sales'], 0) ?>
</div>

<div
    class="sales-bar"
    style="height: <?= max(3, $height) ?>%;"
></div>

<div class="sales-label">
<?= htmlspecialchars($day['label']) ?>
</div>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>

</div>

<!-- MAIN CONTENT -->

<div class="row g-4">

<!-- RECENT TRANSACTIONS -->

<div class="col-12 col-xl-8">

<div class="card pos-card shadow-sm bg-body h-100">

<div class="pos-card-header d-flex justify-content-between align-items-center">

<div>

<h5 class="fw-bold mb-1">
Recent Transactions
</h5>

<p class="text-muted small mb-0">
Latest transactions for this business
</p>

</div>

<a
    href="index.php?page=pos_transactions"
    class="btn btn-sm btn-outline-primary rounded-3"
>
View All
</a>

</div>

<?php if (!$recentTransactions): ?>

<div class="dashboard-empty text-center">

<i class="bi bi-receipt display-6 text-muted opacity-50"></i>

<div class="fw-semibold mt-3">
No transactions yet
</div>

<div class="text-muted small mb-3">
Create your first POS sale.
</div>

<a
    href="index.php?page=pos_sales"
    class="btn btn-primary btn-sm fw-bold rounded-3"
>
<i class="bi bi-cart-plus me-1"></i>
Create Sale
</a>

</div>

<?php else: ?>

<div class="table-responsive">

<table class="table table-hover pos-table mb-0">

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

<th class="py-3 text-end">
Amount
</th>

<th class="py-3 pe-4 text-end">
Status
</th>

</tr>

</thead>

<tbody>

<?php foreach ($recentTransactions as $transaction): ?>

<?php

$status = strtolower(
    $transaction['sale_status'] ?? 'completed'
);

$customerName = trim(
    ($transaction['first_name'] ?? '') . ' ' .
    ($transaction['last_name'] ?? '')
);

if ($customerName === '') {
    $customerName = 'Walk-in Customer';
}

?>

<tr>

<td class="ps-4">

<div class="transaction-reference">
<?= htmlspecialchars($transaction['invoice_number']) ?>
</div>

</td>

<td>

<div class="small">
<?= htmlspecialchars($customerName) ?>
</div>

</td>

<td>

<div class="transaction-date">

<?= !empty($transaction['sale_date'])
    ? date(
        'M d, Y h:i A',
        strtotime($transaction['sale_date'])
    )
    : '-'
?>

</div>

</td>

<td class="text-end">

<span class="fw-bold">

₱<?= number_format(
    (float)$transaction['total_amount'],
    2
) ?>

</span>

</td>

<td class="text-end pe-4">

<?php if ($status === 'completed'): ?>

<span class="pos-status pos-status-success">

<i class="bi bi-check-circle me-1"></i>
Completed

</span>

<?php elseif ($status === 'voided'): ?>

<span class="pos-status pos-status-danger">

<i class="bi bi-x-circle me-1"></i>
Voided

</span>

<?php elseif ($status === 'refunded'): ?>

<span class="pos-status pos-status-warning">

<i class="bi bi-arrow-counterclockwise me-1"></i>
Refunded

</span>

<?php else: ?>

<span class="pos-status pos-status-warning">

<?= htmlspecialchars(ucfirst($status)) ?>

</span>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</div>

</div>

<!-- STOCK ALERTS -->

<div class="col-12 col-xl-4">

<div class="card pos-card shadow-sm bg-body h-100">

<div class="pos-card-header d-flex justify-content-between align-items-center">

<div>

<h5 class="fw-bold mb-1">
Stock Alerts
</h5>

<p class="text-muted small mb-0">
Products needing attention
</p>

</div>

<a
    href="index.php?page=pos_products"
    class="btn btn-sm btn-outline-primary rounded-3"
>
View
</a>

</div>

<div class="card-body p-3">

<?php if (!$lowStockList): ?>

<div class="dashboard-empty text-center">

<i class="bi bi-check-circle display-6 text-success opacity-75"></i>

<div class="fw-semibold mt-3">
Stock looks good
</div>

<div class="text-muted small">
No low-stock products found.
</div>

</div>

<?php else: ?>

<?php foreach ($lowStockList as $product): ?>

<?php

$stock = (float)$product['current_stock'];
$minimum = (float)$product['minimum_stock'];
$isOut = $stock <= 0;

?>

<div class="low-stock-item d-flex justify-content-between align-items-center">

<div class="min-w-0">

<div class="fw-semibold small text-truncate">

<?= htmlspecialchars($product['name']) ?>

</div>

<?php if (!empty($product['sku'])): ?>

<div
    class="text-muted"
    style="font-size:.68rem;"
>

SKU:
<?= htmlspecialchars($product['sku']) ?>

</div>

<?php endif; ?>

</div>

<div class="text-end ms-3">

<div
    class="stock-number <?= $isOut ? 'stock-danger' : 'stock-warning' ?>"
>

<?= number_format($stock, 2) ?>

</div>

<div
    class="text-muted"
    style="font-size:.65rem;"
>

Min:
<?= number_format($minimum, 2) ?>

</div>

</div>

</div>

<?php endforeach; ?>

<?php endif; ?>

</div>

</div>

</div>

</div>

<!-- BOTTOM ROW -->

<div class="row g-4 mt-1">

<!-- TOP PRODUCTS -->

<div class="col-12 col-lg-6">

<div class="card pos-card shadow-sm bg-body h-100">

<div class="pos-card-header">

<h5 class="fw-bold mb-1">
Top Selling Products
</h5>

<p class="text-muted small mb-0">
Best-performing products
</p>

</div>

<?php if (!$topProducts): ?>

<div class="dashboard-empty text-center">

<i class="bi bi-bar-chart display-6 text-muted opacity-50"></i>

<div class="fw-semibold mt-3">
No sales data yet
</div>

<div class="text-muted small">
Products will appear here after sales are recorded.
</div>

</div>

<?php else: ?>

<div class="card-body">

<?php foreach ($topProducts as $index => $product): ?>

<div class="d-flex align-items-center gap-3 py-2">

<div
    class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold"
    style="width:36px;height:36px;"
>

<?= $index + 1 ?>

</div>

<div class="flex-grow-1">

<div class="fw-semibold small">

<?= htmlspecialchars($product['product_name']) ?>

</div>

<div
    class="text-muted"
    style="font-size:.68rem;"
>

Sales:
₱<?= number_format(
    (float)$product['sales_amount'],
    2
) ?>

</div>

</div>

<div class="text-end">

<div class="fw-bold small">

<?= number_format(
    (float)$product['quantity_sold'],
    0
) ?>

</div>

<div
    class="text-muted"
    style="font-size:.65rem;"
>
units
</div>

</div>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>

</div>

<!-- PAYMENT METHODS -->

<div class="col-12 col-lg-6">

<div class="card pos-card shadow-sm bg-body h-100">

<div class="pos-card-header">

<h5 class="fw-bold mb-1">
Today's Payments
</h5>

<p class="text-muted small mb-0">
Payment methods used today
</p>

</div>

<div class="card-body">

<?php if (!$paymentSummary): ?>

<div class="dashboard-empty text-center">

<i class="bi bi-credit-card display-6 text-muted opacity-50"></i>

<div class="fw-semibold mt-3">
No payments today
</div>

<div class="text-muted small">
Payment breakdown will appear here.
</div>

</div>

<?php else: ?>

<?php foreach ($paymentSummary as $payment): ?>

<?php

$method = $payment['payment_method'];

$label =
    $paymentLabels[$method]
    ?? ucfirst(
        str_replace(
            '_',
            ' ',
            $method
        )
    );

?>

<div class="payment-item d-flex align-items-center gap-3">

<div class="payment-icon">

<?php if ($method === 'cash'): ?>

<i class="bi bi-cash-stack"></i>

<?php elseif ($method === 'card'): ?>

<i class="bi bi-credit-card"></i>

<?php elseif ($method === 'gcash'): ?>

<i class="bi bi-phone"></i>

<?php elseif ($method === 'bank_transfer'): ?>

<i class="bi bi-bank"></i>

<?php else: ?>

<i class="bi bi-wallet2"></i>

<?php endif; ?>

</div>

<div class="flex-grow-1">

<div class="fw-semibold small">
<?= htmlspecialchars($label) ?>
</div>

</div>

<div class="fw-bold">

₱<?= number_format(
    (float)$payment['total_amount'],
    2
) ?>

</div>

</div>

<?php endforeach; ?>

<?php endif; ?>

</div>

</div>

</div>

</div>

<!-- INVENTORY SUMMARY -->

<div class="row g-4 mt-1">

<div class="col-12">

<div class="card pos-card shadow-sm bg-body">

<div class="pos-card-header">

<h5 class="fw-bold mb-1">
Inventory Summary
</h5>

<p class="text-muted small mb-0">
Current inventory for this business
</p>

</div>

<div class="card-body">

<div class="row g-3">

<div class="col-12 col-sm-6 col-lg-3">

<div class="border rounded-3 p-3 h-100">

<div class="text-muted small mb-2">
Products
</div>

<div class="fs-4 fw-bold">
<?= number_format($totalProducts) ?>
</div>

<div class="text-muted" style="font-size:.68rem;">
Active products
</div>

</div>

</div>

<div class="col-12 col-sm-6 col-lg-3">

<div class="border rounded-3 p-3 h-100">

<div class="text-muted small mb-2">
Low Stock
</div>

<div class="fs-4 fw-bold text-warning">
<?= number_format($lowStockProducts) ?>
</div>

<div class="text-muted" style="font-size:.68rem;">
Need attention
</div>

</div>

</div>

<div class="col-12 col-sm-6 col-lg-3">

<div class="border rounded-3 p-3 h-100">

<div class="text-muted small mb-2">
Out of Stock
</div>

<div class="fs-4 fw-bold text-danger">
<?= number_format($outOfStockProducts) ?>
</div>

<div class="text-muted" style="font-size:.68rem;">
No stock
</div>

</div>

</div>

<div class="col-12 col-sm-6 col-lg-3">

<div class="border rounded-3 p-3 h-100">

<div class="text-muted small mb-2">
Inventory Value
</div>

<div class="fs-4 fw-bold">
₱<?= number_format($inventoryValue, 2) ?>
</div>

<div class="text-muted" style="font-size:.68rem;">
Based on cost price
</div>

</div>

</div>

</div>

<div class="mt-3">

<a
    href="index.php?page=pos_products"
    class="btn btn-outline-primary w-100 rounded-3 fw-semibold"
>

<i class="bi bi-boxes me-1"></i>

Manage POS Inventory

</a>

</div>

</div>

</div>

</div>

</div>

<!-- RECENT EXPENSES -->

<div class="row g-4 mt-1">

<div class="col-12">

<div class="card pos-card shadow-sm bg-body">

<div class="pos-card-header d-flex justify-content-between align-items-center">

<div>

<h5 class="fw-bold mb-1">
Recent Expenses
</h5>

<p class="text-muted small mb-0">
Latest expenses recorded for this business
</p>

</div>

<a
    href="index.php?page=pos_expenses"
    class="btn btn-sm btn-outline-primary rounded-3"
>
View All
</a>

</div>

<div class="card-body">

<?php if (!$recentExpenses): ?>

<div class="dashboard-empty text-center">

<i class="bi bi-wallet2 display-6 text-muted opacity-50"></i>

<div class="fw-semibold mt-3">
No expenses recorded
</div>

<div class="text-muted small">
Business expenses will appear here.
</div>

</div>

<?php else: ?>

<div class="row g-3">

<?php foreach ($recentExpenses as $expense): ?>

<div class="col-12 col-md-6 col-lg-4">

<div class="border rounded-3 p-3 h-100">

<div class="d-flex justify-content-between gap-3">

<div class="min-w-0">

<div class="fw-semibold small text-truncate">

<?= htmlspecialchars(
    $expense['description']
) ?>

</div>

<?php if (!empty($expense['category'])): ?>

<div
    class="text-muted"
    style="font-size:.68rem;"
>

<?= htmlspecialchars(
    $expense['category']
) ?>

</div>

<?php endif; ?>

<div
    class="text-muted mt-1"
    style="font-size:.68rem;"
>

<?= !empty($expense['expense_date'])
    ? date(
        'M d, Y',
        strtotime($expense['expense_date'])
    )
    : '-'
?>

</div>

</div>

<div class="fw-bold text-danger">

-₱<?= number_format(
    (float)$expense['amount'],
    2
) ?>

</div>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>

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
