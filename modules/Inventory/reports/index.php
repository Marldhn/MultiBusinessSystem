
<?php

$pdo = Database::getConnection();

$businessId = $_SESSION['business_id'] ?? null;
$userId = $_SESSION['user_id'] ?? null;

if (!$businessId || !$userId) {
    header('Location: index.php?page=login');
    exit;
}

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$reportType = $_GET['report'] ?? 'current_stock';

$allowedReports = [
    'current_stock',
    'low_stock',
    'out_of_stock',
    'stock_in',
    'stock_out',
    'valuation'
];

if (!in_array($reportType, $allowedReports, true)) {
    $reportType = 'current_stock';
}

$search = trim($_GET['search'] ?? '');
$categoryId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

/*
|--------------------------------------------------------------------------
| LOAD CATEGORIES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, name
    FROM inventory_categories
    WHERE business_id = ?
    AND status = 'active'
    ORDER BY name ASC
");

$stmt->execute([$businessId]);

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_products,
        COALESCE(SUM(current_stock), 0) AS total_stock,
        COALESCE(SUM(current_stock * cost_price), 0) AS total_cost_value,
        COALESCE(SUM(current_stock * selling_price), 0) AS total_selling_value
    FROM inventory_products
    WHERE business_id = ?
    AND status = 'active'
");

$stmt->execute([$businessId]);

$summary = $stmt->fetch(PDO::FETCH_ASSOC);

$totalProducts = (int)($summary['total_products'] ?? 0);
$totalStock = (float)($summary['total_stock'] ?? 0);
$totalCostValue = (float)($summary['total_cost_value'] ?? 0);
$totalSellingValue = (float)($summary['total_selling_value'] ?? 0);

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM inventory_products
    WHERE business_id = ?
    AND status = 'active'
    AND current_stock <= 0
");

$stmt->execute([$businessId]);

$outOfStock = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM inventory_products
    WHERE business_id = ?
    AND status = 'active'
    AND current_stock > 0
    AND current_stock <= minimum_stock
");

$stmt->execute([$businessId]);

$lowStock = (int)$stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| STOCK MOVEMENT SUMMARY
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(
            CASE
                WHEN movement_type IN ('stock_in', 'purchase', 'opening_stock', 'adjustment_in')
                THEN quantity
                ELSE 0
            END
        ), 0) AS total_stock_in,

        COALESCE(SUM(
            CASE
                WHEN movement_type IN ('stock_out', 'sale', 'adjustment_out')
                THEN quantity
                ELSE 0
            END
        ), 0) AS total_stock_out

    FROM inventory_stock_movements
    WHERE business_id = ?
    AND DATE(created_at) BETWEEN ? AND ?
");

$stmt->execute([
    $businessId,
    $dateFrom,
    $dateTo
]);

$movementSummary = $stmt->fetch(PDO::FETCH_ASSOC);

$totalStockIn = (float)($movementSummary['total_stock_in'] ?? 0);
$totalStockOut = (float)($movementSummary['total_stock_out'] ?? 0);

/*
|--------------------------------------------------------------------------
| LOAD CURRENT STOCK REPORT
|--------------------------------------------------------------------------
*/

$currentStockRows = [];

if ($reportType === 'current_stock' || $reportType === 'valuation') {

    $sql = "
        SELECT
            p.id,
            p.name,
            p.sku,
            p.barcode,
            p.current_stock,
            p.minimum_stock,
            p.maximum_stock,
            p.cost_price,
            p.selling_price,
            p.wholesale_price,
            p.status,

            c.name AS category_name,
            b.name AS brand_name,
            u.name AS unit_name,
            u.abbreviation AS unit_abbreviation,
            s.name AS supplier_name

        FROM inventory_products p

        LEFT JOIN inventory_categories c
            ON c.id = p.category_id
            AND c.business_id = p.business_id

        LEFT JOIN inventory_brands b
            ON b.id = p.brand_id
            AND b.business_id = p.business_id

        LEFT JOIN inventory_units u
            ON u.id = p.unit_id
            AND u.business_id = p.business_id

        LEFT JOIN inventory_suppliers s
            ON s.id = p.supplier_id
            AND s.business_id = p.business_id

        WHERE p.business_id = ?
        AND p.status = 'active'
    ";

    $params = [$businessId];

    if ($search !== '') {

        $sql .= "
            AND (
                p.name LIKE ?
                OR p.sku LIKE ?
                OR p.barcode LIKE ?
                OR c.name LIKE ?
                OR b.name LIKE ?
            )
        ";

        $searchValue = '%' . $search . '%';

        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
    }

    if ($categoryId) {

        $sql .= "
            AND p.category_id = ?
        ";

        $params[] = $categoryId;
    }

    $sql .= "
        ORDER BY p.name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $currentStockRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/*
|--------------------------------------------------------------------------
| LOW STOCK REPORT
|--------------------------------------------------------------------------
*/

$lowStockRows = [];

if ($reportType === 'low_stock') {

    $sql = "
        SELECT
            p.id,
            p.name,
            p.sku,
            p.current_stock,
            p.minimum_stock,
            p.maximum_stock,
            p.cost_price,
            p.selling_price,

            c.name AS category_name,
            b.name AS brand_name,
            u.name AS unit_name,
            u.abbreviation AS unit_abbreviation

        FROM inventory_products p

        LEFT JOIN inventory_categories c
            ON c.id = p.category_id
            AND c.business_id = p.business_id

        LEFT JOIN inventory_brands b
            ON b.id = p.brand_id
            AND b.business_id = p.business_id

        LEFT JOIN inventory_units u
            ON u.id = p.unit_id
            AND u.business_id = p.business_id

        WHERE p.business_id = ?
        AND p.status = 'active'
        AND p.current_stock > 0
        AND p.current_stock <= p.minimum_stock
    ";

    $params = [$businessId];

    if ($search !== '') {

        $sql .= "
            AND (
                p.name LIKE ?
                OR p.sku LIKE ?
                OR p.barcode LIKE ?
                OR c.name LIKE ?
                OR b.name LIKE ?
            )
        ";

        $searchValue = '%' . $search . '%';

        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
    }

    if ($categoryId) {

        $sql .= " AND p.category_id = ? ";

        $params[] = $categoryId;
    }

    $sql .= "
        ORDER BY p.current_stock ASC, p.name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $lowStockRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/*
|--------------------------------------------------------------------------
| OUT OF STOCK REPORT
|--------------------------------------------------------------------------
*/

$outOfStockRows = [];

if ($reportType === 'out_of_stock') {

    $sql = "
        SELECT
            p.id,
            p.name,
            p.sku,
            p.current_stock,
            p.minimum_stock,
            p.cost_price,
            p.selling_price,

            c.name AS category_name,
            b.name AS brand_name,
            u.name AS unit_name,
            u.abbreviation AS unit_abbreviation

        FROM inventory_products p

        LEFT JOIN inventory_categories c
            ON c.id = p.category_id
            AND c.business_id = p.business_id

        LEFT JOIN inventory_brands b
            ON b.id = p.brand_id
            AND b.business_id = p.business_id

        LEFT JOIN inventory_units u
            ON u.id = p.unit_id
            AND u.business_id = p.business_id

        WHERE p.business_id = ?
        AND p.status = 'active'
        AND p.current_stock <= 0
    ";

    $params = [$businessId];

    if ($search !== '') {

        $sql .= "
            AND (
                p.name LIKE ?
                OR p.sku LIKE ?
                OR p.barcode LIKE ?
                OR c.name LIKE ?
                OR b.name LIKE ?
            )
        ";

        $searchValue = '%' . $search . '%';

        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
    }

    if ($categoryId) {

        $sql .= " AND p.category_id = ? ";

        $params[] = $categoryId;
    }

    $sql .= "
        ORDER BY p.name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $outOfStockRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/*
|--------------------------------------------------------------------------
| STOCK MOVEMENT REPORT
|--------------------------------------------------------------------------
*/

$movementRows = [];

if ($reportType === 'stock_in' || $reportType === 'stock_out') {

    $movementTypes = $reportType === 'stock_in'
        ? "('stock_in','purchase','opening_stock','adjustment_in')"
        : "('stock_out','sale','adjustment_out')";

    $sql = "
        SELECT
            m.id,
            m.product_id,
            m.movement_type,
            m.quantity,
            m.unit_cost,
            m.previous_stock,
            m.new_stock,
            m.reference_type,
            m.reference_id,
            m.notes,
            m.created_at,

            p.name AS product_name,
            p.sku,
            p.barcode,

            c.name AS category_name,

            u.name AS unit_name,
            u.abbreviation AS unit_abbreviation

        FROM inventory_stock_movements m

        INNER JOIN inventory_products p
            ON p.id = m.product_id
            AND p.business_id = m.business_id

        LEFT JOIN inventory_categories c
            ON c.id = p.category_id
            AND c.business_id = p.business_id

        LEFT JOIN inventory_units u
            ON u.id = p.unit_id
            AND u.business_id = p.business_id

        WHERE m.business_id = ?
        AND m.movement_type IN $movementTypes
        AND DATE(m.created_at) BETWEEN ? AND ?
    ";

    $params = [
        $businessId,
        $dateFrom,
        $dateTo
    ];

    if ($search !== '') {

        $sql .= "
            AND (
                p.name LIKE ?
                OR p.sku LIKE ?
                OR p.barcode LIKE ?
                OR c.name LIKE ?
                OR m.notes LIKE ?
            )
        ";

        $searchValue = '%' . $search . '%';

        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
    }

    if ($categoryId) {

        $sql .= "
            AND p.category_id = ?
        ";

        $params[] = $categoryId;
    }

    $sql .= "
        ORDER BY m.created_at DESC, m.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $movementRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/*
|--------------------------------------------------------------------------
| REPORT DATA
|--------------------------------------------------------------------------
*/

$reportTitle = 'Current Stock';
$reportDescription = 'View the current inventory quantity of all active products.';
$reportRows = [];

if ($reportType === 'current_stock') {

    $reportTitle = 'Current Stock';
    $reportDescription = 'View the current inventory quantity of all active products.';
    $reportRows = $currentStockRows;

} elseif ($reportType === 'low_stock') {

    $reportTitle = 'Low Stock';
    $reportDescription = 'Products that have reached or fallen below their minimum stock level.';
    $reportRows = $lowStockRows;

} elseif ($reportType === 'out_of_stock') {

    $reportTitle = 'Out of Stock';
    $reportDescription = 'Products that currently have zero or negative stock.';
    $reportRows = $outOfStockRows;

} elseif ($reportType === 'valuation') {

    $reportTitle = 'Inventory Valuation';
    $reportDescription = 'Estimated inventory value based on current stock and product prices.';
    $reportRows = $currentStockRows;

} elseif ($reportType === 'stock_in') {

    $reportTitle = 'Stock In';
    $reportDescription = 'Stock received, purchased, opened, or adjusted into inventory.';
    $reportRows = $movementRows;

} elseif ($reportType === 'stock_out') {

    $reportTitle = 'Stock Out';
    $reportDescription = 'Stock sold, issued, or adjusted out of inventory.';
    $reportRows = $movementRows;
}

$activePage = 'inventory_reports';
$pageTitle = 'Inventory Reports';
$businessName = $_SESSION['business_name'] ?? 'Business';

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

.inventory-main {
    min-width: 0;
    width: 100%;
}

.report-header h2 {
    font-size: 1.5rem;
}

.summary-card {
    border: 0;
    border-radius: 16px;
    transition: transform .15s ease, box-shadow .15s ease;
}

.summary-card:hover {
    transform: translateY(-2px);
}

.summary-icon {
    width: 48px;
    height: 48px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.report-card {
    border: 0;
    border-radius: 16px;
    transition: all .15s ease;
}

.report-card:hover {
    transform: translateY(-2px);
}

.report-card.active {
    border: 2px solid var(--bs-primary);
}

.report-icon {
    width: 44px;
    height: 44px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.filter-card {
    border: 0;
    border-radius: 16px;
}

.report-table {
    width: 100%;
}

.report-table th {
    font-size: .72rem;
    letter-spacing: .04em;
    white-space: nowrap;
}

.report-table td {
    vertical-align: middle;
    white-space: nowrap;
    padding-top: 12px;
    padding-bottom: 12px;
}

.product-name {
    font-weight: 700;
}

.product-subtext {
    font-size: .7rem;
    color: var(--bs-secondary-color);
}

.stock-good {
    color: var(--bs-success);
    font-weight: 700;
}

.stock-low {
    color: var(--bs-warning);
    font-weight: 700;
}

.stock-out {
    color: var(--bs-danger);
    font-weight: 700;
}

.movement-in {
    color: var(--bs-success);
    font-weight: 700;
}

.movement-out {
    color: var(--bs-danger);
    font-weight: 700;
}

.report-empty {
    padding: 60px 20px;
}

.report-modal .modal-content {
    border: 0;
    border-radius: 16px;
}

.report-modal .modal-header {
    padding: 18px 22px;
}

.report-modal .modal-body {
    padding: 22px;
}

@media print {

    body {
        background: #fff !important;
    }

    .inventory-main {
        width: 100%;
    }

    .no-print,
    .sidebar,
    nav,
    aside,
    button {
        display: none !important;
    }

    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }

    .table-responsive {
        overflow: visible !important;
    }

    .report-table {
        font-size: 11px;
    }

}

@media (max-width: 575.98px) {

    .report-header h2 {
        font-size: 1.35rem;
    }

    .summary-card .card-body {
        padding: 16px !important;
    }

    .report-card .card-body {
        padding: 16px !important;
    }

}

</style>

</head>

<body class="bg-body-tertiary">

<div class="d-flex flex-column flex-lg-row min-vh-100">

<?php

$sidebarPath = __DIR__ . '/../../../resources/partials/InventorySidebar.php';

if (file_exists($sidebarPath)) {
    include $sidebarPath;
}

?>

<main class="inventory-main flex-grow-1 bg-body-tertiary">

<div class="p-3 p-md-4">

<!-- =====================================================
     HEADER
====================================================== -->

<div class="report-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

<div>

<div class="d-flex align-items-center gap-2 mb-1">

<div class="d-lg-none bg-primary bg-opacity-10 text-primary rounded-3 p-2">

<i class="bi bi-bar-chart-line"></i>

</div>

<h2 class="fw-bold text-body mb-0">
Inventory Reports
</h2>

</div>

<p class="text-muted small mb-0">

Analyze inventory and stock movement for

<span class="fw-semibold text-primary">
<?= htmlspecialchars($businessName) ?>
</span>

</p>

</div>

<div class="d-flex gap-2 no-print">

<button
    type="button"
    class="btn btn-outline-secondary rounded-3 fw-semibold"
    onclick="window.print()"
>

<i class="bi bi-printer me-1"></i>

Print

</button>

</div>

</div>

<!-- =====================================================
     SUMMARY
====================================================== -->

<div class="row g-3 mb-4">

<div class="col-12 col-sm-6 col-xl-3">

<div class="card summary-card shadow-sm bg-body h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between">

<div>

<div class="small text-muted fw-semibold mb-2">
Total Products
</div>

<div class="fs-3 fw-bold">
<?= number_format($totalProducts) ?>
</div>

</div>

<div class="summary-icon bg-primary bg-opacity-10 text-primary">

<i class="bi bi-box-seam fs-4"></i>

</div>

</div>

</div>

</div>

</div>


<div class="col-12 col-sm-6 col-xl-3">

<div class="card summary-card shadow-sm bg-body h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between">

<div>

<div class="small text-muted fw-semibold mb-2">
Total Stock
</div>

<div class="fs-3 fw-bold">
<?= number_format($totalStock, 2) ?>
</div>

</div>

<div class="summary-icon bg-success bg-opacity-10 text-success">

<i class="bi bi-boxes fs-4"></i>

</div>

</div>

</div>

</div>

</div>


<div class="col-12 col-sm-6 col-xl-3">

<div class="card summary-card shadow-sm bg-body h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between">

<div>

<div class="small text-muted fw-semibold mb-2">
Low Stock
</div>

<div class="fs-3 fw-bold text-warning">
<?= number_format($lowStock) ?>
</div>

</div>

<div class="summary-icon bg-warning bg-opacity-10 text-warning">

<i class="bi bi-exclamation-triangle fs-4"></i>

</div>

</div>

</div>

</div>

</div>


<div class="col-12 col-sm-6 col-xl-3">

<div class="card summary-card shadow-sm bg-body h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between">

<div>

<div class="small text-muted fw-semibold mb-2">
Out of Stock
</div>

<div class="fs-3 fw-bold text-danger">
<?= number_format($outOfStock) ?>
</div>

</div>

<div class="summary-icon bg-danger bg-opacity-10 text-danger">

<i class="bi bi-box2 fs-4"></i>

</div>

</div>

</div>

</div>

</div>

</div>

<!-- =====================================================
     REPORT TYPES
====================================================== -->

<div class="row g-3 mb-4 no-print">

<?php

$reportCards = [

    'current_stock' => [
        'title' => 'Current Stock',
        'description' => 'Current quantity by product',
        'icon' => 'bi-box-seam',
        'color' => 'primary'
    ],

    'low_stock' => [
        'title' => 'Low Stock',
        'description' => 'Products needing attention',
        'icon' => 'bi-exclamation-triangle',
        'color' => 'warning'
    ],

    'out_of_stock' => [
        'title' => 'Out of Stock',
        'description' => 'Products with no stock',
        'icon' => 'bi-box2',
        'color' => 'danger'
    ],

    'valuation' => [
        'title' => 'Valuation',
        'description' => 'Current inventory value',
        'icon' => 'bi-cash-stack',
        'color' => 'success'
    ],

    'stock_in' => [
        'title' => 'Stock In',
        'description' => 'Incoming inventory',
        'icon' => 'bi-box-arrow-in-down',
        'color' => 'success'
    ],

    'stock_out' => [
        'title' => 'Stock Out',
        'description' => 'Outgoing inventory',
        'icon' => 'bi-box-arrow-up',
        'color' => 'danger'
    ]

];

?>

<?php foreach ($reportCards as $key => $card): ?>

<div class="col-12 col-sm-6 col-xl-4">

<a
    href="index.php?page=inventory_reports&report=<?= urlencode($key) ?>"
    class="text-decoration-none"
>

<div class="card report-card shadow-sm bg-body h-100 <?= $reportType === $key ? 'active' : '' ?>">

<div class="card-body p-3">

<div class="d-flex align-items-center gap-3">

<div class="report-icon bg-<?= $card['color'] ?> bg-opacity-10 text-<?= $card['color'] ?>">

<i class="bi <?= $card['icon'] ?> fs-5"></i>

</div>

<div class="flex-grow-1">

<div class="fw-bold text-body">
<?= htmlspecialchars($card['title']) ?>
</div>

<div class="small text-muted">
<?= htmlspecialchars($card['description']) ?>
</div>

</div>

<i class="bi bi-chevron-right text-muted"></i>

</div>

</div>

</div>

</a>

</div>

<?php endforeach; ?>

</div>

<!-- =====================================================
     DATE / FILTERS
====================================================== -->

<div class="card filter-card shadow-sm bg-body mb-4 no-print">

<div class="card-body p-3">

<form method="GET">

<input
    type="hidden"
    name="page"
    value="inventory_reports"
>

<input
    type="hidden"
    name="report"
    value="<?= htmlspecialchars($reportType) ?>"
>

<div class="row g-2">

<div class="col-12 col-lg-4">

<label class="form-label small fw-semibold">
Search
</label>

<input
    type="text"
    name="search"
    class="form-control"
    placeholder="Product, SKU, barcode..."
    value="<?= htmlspecialchars($search) ?>"
>

</div>


<div class="col-12 col-sm-6 col-lg-2">

<label class="form-label small fw-semibold">
Category
</label>

<select
    name="category_id"
    class="form-select"
>

<option value="">
All Categories
</option>

<?php foreach ($categories as $category): ?>

<option
    value="<?= (int)$category['id'] ?>"
    <?= $categoryId === (int)$category['id'] ? 'selected' : '' ?>
>

<?= htmlspecialchars($category['name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="col-12 col-sm-6 col-lg-2">

<label class="form-label small fw-semibold">
Date From
</label>

<input
    type="date"
    name="date_from"
    class="form-control"
    value="<?= htmlspecialchars($dateFrom) ?>"
>

</div>


<div class="col-12 col-sm-6 col-lg-2">

<label class="form-label small fw-semibold">
Date To
</label>

<input
    type="date"
    name="date_to"
    class="form-control"
    value="<?= htmlspecialchars($dateTo) ?>"
>

</div>


<div class="col-12 col-sm-6 col-lg-2 d-flex align-items-end gap-2">

<button
    type="submit"
    class="btn btn-primary fw-bold rounded-3 flex-grow-1"
>

<i class="bi bi-filter me-1"></i>

Apply

</button>

<a
    href="index.php?page=inventory_reports&report=<?= urlencode($reportType) ?>"
    class="btn btn-outline-secondary rounded-3"
    title="Clear filters"
>

<i class="bi bi-arrow-counterclockwise"></i>

</a>

</div>

</div>

</form>

</div>

</div>

<!-- =====================================================
     MOVEMENT SUMMARY
====================================================== -->

<?php if ($reportType === 'stock_in' || $reportType === 'stock_out'): ?>

<div class="row g-3 mb-4">

<div class="col-12 col-md-6">

<div class="card border-0 shadow-sm rounded-4">

<div class="card-body p-4">

<div class="small text-muted fw-semibold mb-2">
Stock In
</div>

<div class="fs-3 fw-bold text-success">

<?= number_format($totalStockIn, 2) ?>

</div>

<div class="small text-muted">
<?= htmlspecialchars($dateFrom) ?>
to
<?= htmlspecialchars($dateTo) ?>
</div>

</div>

</div>

</div>


<div class="col-12 col-md-6">

<div class="card border-0 shadow-sm rounded-4">

<div class="card-body p-4">

<div class="small text-muted fw-semibold mb-2">
Stock Out
</div>

<div class="fs-3 fw-bold text-danger">

<?= number_format($totalStockOut, 2) ?>

</div>

<div class="small text-muted">
<?= htmlspecialchars($dateFrom) ?>
to
<?= htmlspecialchars($dateTo) ?>
</div>

</div>

</div>

</div>

</div>

<?php endif; ?>

<!-- =====================================================
     VALUATION SUMMARY
====================================================== -->

<?php if ($reportType === 'valuation'): ?>

<div class="row g-3 mb-4">

<div class="col-12 col-md-4">

<div class="card border-0 shadow-sm rounded-4">

<div class="card-body p-4">

<div class="small text-muted fw-semibold mb-2">
Cost Value
</div>

<div class="fs-4 fw-bold">

₱<?= number_format($totalCostValue, 2) ?>

</div>

</div>

</div>

</div>


<div class="col-12 col-md-4">

<div class="card border-0 shadow-sm rounded-4">

<div class="card-body p-4">

<div class="small text-muted fw-semibold mb-2">
Selling Value
</div>

<div class="fs-4 fw-bold text-success">

₱<?= number_format($totalSellingValue, 2) ?>

</div>

</div>

</div>

</div>


<div class="col-12 col-md-4">

<div class="card border-0 shadow-sm rounded-4">

<div class="card-body p-4">

<div class="small text-muted fw-semibold mb-2">
Potential Gross Margin
</div>

<div class="fs-4 fw-bold text-primary">

₱<?= number_format(
    $totalSellingValue - $totalCostValue,
    2
) ?>

</div>

</div>

</div>

</div>

</div>

<?php endif; ?>

<!-- =====================================================
     REPORT TABLE
====================================================== -->

<div class="card border-0 shadow-sm rounded-4">

<div class="card-body p-0">

<div class="p-4 border-bottom">

<div class="d-flex flex-column flex-md-row justify-content-between gap-2">

<div>

<h5 class="fw-bold mb-1">

<?= htmlspecialchars($reportTitle) ?>

</h5>

<p class="small text-muted mb-0">

<?= htmlspecialchars($reportDescription) ?>

</p>

</div>

<div class="small text-muted">

<?php if ($reportType === 'stock_in' || $reportType === 'stock_out'): ?>

<?= count($reportRows) ?> movement<?= count($reportRows) === 1 ? '' : 's' ?>

<?php else: ?>

<?= count($reportRows) ?> product<?= count($reportRows) === 1 ? '' : 's' ?>

<?php endif; ?>

</div>

</div>

</div>

<?php if (!$reportRows): ?>

<div class="report-empty text-center text-muted">

<div class="mb-3">

<i class="bi bi-bar-chart-line display-6 opacity-50"></i>

</div>

<div class="fw-semibold mb-1">
No report data found
</div>

<div class="small">
Try changing your search, category, or date filters.
</div>

</div>

<?php else: ?>

<div class="table-responsive">

<table class="report-table table table-hover align-middle mb-0">

<thead class="table-light text-uppercase text-muted">

<tr>

<?php if ($reportType === 'stock_in' || $reportType === 'stock_out'): ?>

<th class="ps-4 py-3">
Date
</th>

<th class="py-3">
Product
</th>

<th class="py-3">
Type
</th>

<th class="py-3">
Quantity
</th>

<th class="py-3">
Previous
</th>

<th class="py-3">
New Stock
</th>

<th class="py-3">
Reason
</th>

<th class="py-3">
Notes
</th>

<th class="py-3 pe-4 text-end">
Action
</th>

<?php else: ?>

<th class="ps-4 py-3">
Product
</th>

<th class="py-3">
SKU
</th>

<th class="py-3">
Category
</th>

<th class="py-3">
Brand
</th>

<th class="py-3">
Stock
</th>

<th class="py-3">
Minimum
</th>

<th class="py-3">
Cost
</th>

<th class="py-3">
Selling
</th>

<?php if ($reportType === 'valuation'): ?>

<th class="py-3">
Cost Value
</th>

<th class="py-3">
Selling Value
</th>

<th class="py-3">
Potential Profit
</th>

<?php endif; ?>

<th class="py-3 pe-4 text-end">
Action
</th>

<?php endif; ?>

</tr>

</thead>

<tbody>

<?php foreach ($reportRows as $row): ?>

<?php if ($reportType === 'stock_in' || $reportType === 'stock_out'): ?>

<tr>

<td class="ps-4">

<div class="small fw-semibold">

<?= date(
    'M d, Y',
    strtotime($row['created_at'])
) ?>

</div>

<div class="text-muted" style="font-size:.68rem;">

<?= date(
    'h:i A',
    strtotime($row['created_at'])
) ?>

</div>

</td>


<td>

<div class="product-name">

<?= htmlspecialchars(
    $row['product_name']
) ?>

</div>

<div class="product-subtext">

<?= htmlspecialchars(
    $row['sku'] ?: '-'
) ?>

</div>

</td>


<td>

<?php

$movementLabel = ucwords(
    str_replace(
        '_',
        ' ',
        $row['movement_type']
    )
);

?>

<?php if ($reportType === 'stock_in'): ?>

<span class="badge text-bg-success">

<i class="bi bi-arrow-down me-1"></i>

<?= htmlspecialchars($movementLabel) ?>

</span>

<?php else: ?>

<span class="badge text-bg-danger">

<i class="bi bi-arrow-up me-1"></i>

<?= htmlspecialchars($movementLabel) ?>

</span>

<?php endif; ?>

</td>


<td>

<span class="<?= $reportType === 'stock_in'
    ? 'movement-in'
    : 'movement-out'
?>">

<?= $reportType === 'stock_in' ? '+' : '-' ?>

<?= number_format(
    (float)$row['quantity'],
    2
) ?>

<?php if (!empty($row['unit_abbreviation'])): ?>

<span class="text-muted fw-normal">

<?= htmlspecialchars(
    $row['unit_abbreviation']
) ?>

</span>

<?php endif; ?>

</span>

</td>


<td>

<?= number_format(
    (float)$row['previous_stock'],
    2
) ?>

</td>


<td>

<strong>

<?= number_format(
    (float)$row['new_stock'],
    2
) ?>

</strong>

</td>


<td>

<?php if (!empty($row['reference_type'])): ?>

<span class="small">

<?= htmlspecialchars(
    ucwords(
        str_replace(
            '_',
            ' ',
            $row['reference_type']
        )
    )
) ?>

</span>

<?php else: ?>

<span class="text-muted small">
-
</span>

<?php endif; ?>

</td>


<td>

<?php if (!empty($row['notes'])): ?>

<span
    class="small text-muted"
    title="<?= htmlspecialchars($row['notes']) ?>"
>

<?= htmlspecialchars(
    mb_strimwidth(
        $row['notes'],
        0,
        35,
        '...'
    )
) ?>

</span>

<?php else: ?>

<span class="text-muted small">
-
</span>

<?php endif; ?>

</td>


<td class="text-end pe-4">

<button
    type="button"
    class="btn btn-sm btn-outline-primary rounded-3"
    onclick='showMovementDetails(<?= json_encode(
        $row,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_AMP |
        JSON_HEX_QUOT
    ) ?>)'
>

<i class="bi bi-eye"></i>

</button>

</td>

</tr>

<?php else: ?>

<?php

$stock = (float)$row['current_stock'];
$minimum = (float)$row['minimum_stock'];

if ($stock <= 0) {

    $stockClass = 'stock-out';

} elseif ($stock <= $minimum) {

    $stockClass = 'stock-low';

} else {

    $stockClass = 'stock-good';
}

$costValue =
    $stock * (float)$row['cost_price'];

$sellingValue =
    $stock * (float)$row['selling_price'];

$potentialProfit =
    $sellingValue - $costValue;

?>

<tr>

<td class="ps-4">

<div class="product-name">

<?= htmlspecialchars(
    $row['name']
) ?>

</div>

<?php if (!empty($row['barcode'])): ?>

<div class="product-subtext">

<?= htmlspecialchars(
    $row['barcode']
) ?>

</div>

<?php endif; ?>

</td>


<td>

<?= htmlspecialchars(
    $row['sku'] ?: '-'
) ?>

</td>


<td>

<?= htmlspecialchars(
    $row['category_name'] ?: '-'
) ?>

</td>


<td>

<?= htmlspecialchars(
    $row['brand_name'] ?: '-'
) ?>

</td>


<td>

<span class="<?= $stockClass ?>">

<?= number_format(
    $stock,
    2
) ?>

</span>

<?php if ($stock <= 0): ?>

<div class="text-danger" style="font-size:.68rem;">

Out of stock

</div>

<?php elseif ($stock <= $minimum): ?>

<div class="text-warning" style="font-size:.68rem;">

Low stock

</div>

<?php endif; ?>

</td>


<td>

<?= number_format(
    $minimum,
    2
) ?>

</td>


<td>

₱<?= number_format(
    (float)$row['cost_price'],
    2
) ?>

</td>


<td>

₱<?= number_format(
    (float)$row['selling_price'],
    2
) ?>

</td>


<?php if ($reportType === 'valuation'): ?>

<td>

<strong>

₱<?= number_format(
    $costValue,
    2
) ?>

</strong>

</td>


<td>

<strong class="text-success">

₱<?= number_format(
    $sellingValue,
    2
) ?>

</strong>

</td>


<td>

<strong class="<?= $potentialProfit >= 0
    ? 'text-primary'
    : 'text-danger'
?>">

₱<?= number_format(
    $potentialProfit,
    2
) ?>

</strong>

</td>

<?php endif; ?>


<td class="text-end pe-4">

<button
    type="button"
    class="btn btn-sm btn-outline-primary rounded-3"
    onclick='showProductReportDetails(<?= json_encode(
        $row,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_AMP |
        JSON_HEX_QUOT
    ) ?>)'
>

<i class="bi bi-eye"></i>

</button>

</td>

</tr>

<?php endif; ?>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</div>

</div>

</div>

</main>

</div>


<!-- =========================================================
     PRODUCT REPORT MODAL
========================================================= -->

<div
    class="modal fade report-modal"
    id="productReportModal"
    tabindex="-1"
>

<div class="modal-dialog modal-dialog-centered">

<div class="modal-content shadow-lg">

<div class="modal-header">

<h5 class="modal-title fw-bold">

<i class="bi bi-box-seam text-primary me-2"></i>

Product Details

</h5>

<button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal"
></button>

</div>

<div class="modal-body">

<div class="row g-3">

<div class="col-12">

<div class="fw-bold fs-5" id="modalProductName">
-
</div>

<div class="text-muted small" id="modalProductSku">
-
</div>

</div>


<div class="col-6">

<div class="small text-muted">
Category
</div>

<div class="fw-semibold" id="modalProductCategory">
-
</div>

</div>


<div class="col-6">

<div class="small text-muted">
Brand
</div>

<div class="fw-semibold" id="modalProductBrand">
-
</div>

</div>


<div class="col-6">

<div class="small text-muted">
Current Stock
</div>

<div class="fw-bold fs-5" id="modalProductStock">
-
</div>

</div>


<div class="col-6">

<div class="small text-muted">
Minimum Stock
</div>

<div class="fw-semibold" id="modalProductMinimum">
-
</div>

</div>


<div class="col-6">

<div class="small text-muted">
Cost Price
</div>

<div class="fw-semibold" id="modalProductCost">
-
</div>

</div>


<div class="col-6">

<div class="small text-muted">
Selling Price
</div>

<div class="fw-semibold" id="modalProductSelling">
-
</div>

</div>


<div class="col-12">

<div class="alert alert-primary border-0 rounded-3 mb-0">

<div class="small text-muted">
Current Inventory Value
</div>

<div
    class="fw-bold fs-5"
    id="modalProductValue"
>
₱0.00
</div>

</div>

</div>

</div>

</div>

<div class="modal-footer">

<button
    type="button"
    class="btn btn-light fw-semibold"
    data-bs-dismiss="modal"
>

Close

</button>

</div>

</div>

</div>

</div>


<!-- =========================================================
     MOVEMENT MODAL
========================================================= -->

<div
    class="modal fade report-modal"
    id="movementModal"
    tabindex="-1"
>

<div class="modal-dialog modal-dialog-centered">

<div class="modal-content shadow-lg">

<div class="modal-header">

<h5 class="modal-title fw-bold">

<i class="bi bi-arrow-left-right text-primary me-2"></i>

Stock Movement Details

</h5>

<button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal"
></button>

</div>

<div class="modal-body">

<div class="row g-3">

<div class="col-12">

<div
    class="fw-bold fs-5"
    id="movementProduct"
>
-
</div>

<div
    class="text-muted small"
    id="movementSku"
>
-
</div>

</div>


<div class="col-6">

<div class="small text-muted">
Movement
</div>

<div
    class="fw-semibold"
    id="movementType"
>
-
</div>

</div>


<div class="col-6">

<div class="small text-muted">
Quantity
</div>

<div
    class="fw-bold"
    id="movementQuantity"
>
-
</div>

</div>


<div class="col-6">

<div class="small text-muted">
Previous Stock
</div>

<div
    class="fw-semibold"
    id="movementPrevious"
>
-
</div>

</div>


<div class="col-6">

<div class="small text-muted">
New Stock
</div>

<div
    class="fw-semibold"
    id="movementNew"
>
-
</div>

</div>


<div class="col-6">

<div class="small text-muted">
Date
</div>

<div
    class="fw-semibold"
    id="movementDate"
>
-
</div>

</div>


<div class="col-6">

<div class="small text-muted">
Unit Cost
</div>

<div
    class="fw-semibold"
    id="movementCost"
>
-
</div>

</div>


<div class="col-12">

<div class="small text-muted">
Reason / Reference
</div>

<div
    class="fw-semibold"
    id="movementReference"
>
-
</div>

</div>


<div class="col-12">

<div class="small text-muted">
Notes
</div>

<div
    class="p-3 bg-body-tertiary rounded-3 mt-1"
    id="movementNotes"
>
-
</div>

</div>

</div>

</div>

<div class="modal-footer">

<button
    type="button"
    class="btn btn-light fw-semibold"
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

/*
|--------------------------------------------------------------------------
| PRODUCT REPORT DETAILS
|--------------------------------------------------------------------------
*/

function showProductReportDetails(product) {

    document.getElementById('modalProductName').textContent =
        product.name || '-';

    document.getElementById('modalProductSku').textContent =
        'SKU: ' + (product.sku || '-');

    document.getElementById('modalProductCategory').textContent =
        product.category_name || '-';

    document.getElementById('modalProductBrand').textContent =
        product.brand_name || '-';

    document.getElementById('modalProductStock').textContent =
        Number(product.current_stock || 0).toLocaleString(
            undefined,
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        );

    document.getElementById('modalProductMinimum').textContent =
        Number(product.minimum_stock || 0).toLocaleString(
            undefined,
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        );

    document.getElementById('modalProductCost').textContent =
        '₱' +
        Number(product.cost_price || 0).toLocaleString(
            undefined,
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        );

    document.getElementById('modalProductSelling').textContent =
        '₱' +
        Number(product.selling_price || 0).toLocaleString(
            undefined,
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        );

    const value =
        Number(product.current_stock || 0) *
        Number(product.cost_price || 0);

    document.getElementById('modalProductValue').textContent =
        '₱' +
        value.toLocaleString(
            undefined,
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        );

    const modal =
        bootstrap.Modal.getOrCreateInstance(
            document.getElementById('productReportModal')
        );

    modal.show();
}


/*
|--------------------------------------------------------------------------
| MOVEMENT DETAILS
|--------------------------------------------------------------------------
*/

function showMovementDetails(movement) {

    document.getElementById('movementProduct').textContent =
        movement.product_name || '-';

    document.getElementById('movementSku').textContent =
        'SKU: ' + (movement.sku || '-');

    const type =
        (movement.movement_type || '')
            .replaceAll('_', ' ')
            .replace(/\b\w/g, function (letter) {
                return letter.toUpperCase();
            });

    document.getElementById('movementType').textContent =
        type || '-';

    document.getElementById('movementQuantity').textContent =
        Number(movement.quantity || 0).toLocaleString(
            undefined,
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        );

    document.getElementById('movementPrevious').textContent =
        Number(movement.previous_stock || 0).toLocaleString(
            undefined,
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        );

    document.getElementById('movementNew').textContent =
        Number(movement.new_stock || 0).toLocaleString(
            undefined,
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        );

    document.getElementById('movementCost').textContent =
        '₱' +
        Number(movement.unit_cost || 0).toLocaleString(
            undefined,
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        );

    if (movement.created_at) {

        const date =
            new Date(
                movement.created_at.replace(' ', 'T')
            );

        if (!isNaN(date.getTime())) {

            document.getElementById('movementDate').textContent =
                date.toLocaleString();

        } else {

            document.getElementById('movementDate').textContent =
                movement.created_at;

        }

    } else {

        document.getElementById('movementDate').textContent =
            '-';

    }

    const reference =
        movement.reference_type
            ? movement.reference_type
                .replaceAll('_', ' ')
                .replace(/\b\w/g, function (letter) {
                    return letter.toUpperCase();
                })
            : '-';

    document.getElementById('movementReference').textContent =
        reference;

    document.getElementById('movementNotes').textContent =
        movement.notes || 'No notes provided.';

    const modal =
        bootstrap.Modal.getOrCreateInstance(
            document.getElementById('movementModal')
        );

    modal.show();
}

</script>

</body>

</html>
