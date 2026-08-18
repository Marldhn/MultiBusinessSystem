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

$businessId = (int)($_SESSION['business_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$businessName = $_SESSION['business_name'] ?? 'Business';

if (!$businessId) {
    header('Location: index.php?page=select_business');
    exit;
}

$success = '';
$error = '';

try {

    /*
    |--------------------------------------------------------------------------
    | TOTAL PRODUCTS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM inventory_products
        WHERE business_id = ?
        AND created_by = ?
    ");

    $stmt->execute([
        $businessId,
        $userId
    ]);

    $totalProducts = (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TOTAL STOCK
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(current_stock), 0)
        FROM inventory_products
        WHERE business_id = ?
        AND created_by = ?
    ");

    $stmt->execute([
        $businessId,
        $userId
    ]);

    $totalStock = (float)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | LOW STOCK
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM inventory_products
        WHERE business_id = ?
        AND created_by = ?
        AND current_stock > 0
        AND current_stock <= minimum_stock
    ");

    $stmt->execute([
        $businessId,
        $userId
    ]);

    $lowStock = (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | OUT OF STOCK
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM inventory_products
        WHERE business_id = ?
        AND created_by = ?
        AND current_stock <= 0
    ");

    $stmt->execute([
        $businessId,
        $userId
    ]);

    $outOfStock = (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | INVENTORY VALUE
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT COALESCE(
            SUM(current_stock * cost_price),
            0
        )
        FROM inventory_products
        WHERE business_id = ?
        AND created_by = ?
    ");

    $stmt->execute([
        $businessId,
        $userId
    ]);

    $inventoryValue = (float)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | ACTIVE PRODUCTS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM inventory_products
        WHERE business_id = ?
        AND created_by = ?
        AND status = 'active'
    ");

    $stmt->execute([
        $businessId,
        $userId
    ]);

    $activeProducts = (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | LOW STOCK PRODUCTS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.name,
            p.sku,
            p.current_stock,
            p.minimum_stock,
            p.cost_price,
            p.selling_price,
            c.name AS category_name
        FROM inventory_products p

        LEFT JOIN inventory_categories c
            ON c.id = p.category_id
            AND c.business_id = p.business_id
            AND c.created_by = p.created_by

        WHERE p.business_id = ?
        AND p.created_by = ?
        AND p.current_stock <= p.minimum_stock

        ORDER BY p.current_stock ASC
        LIMIT 10
    ");

    $stmt->execute([
        $businessId,
        $userId
    ]);

    $lowStockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | RECENT PRODUCTS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.name,
            p.sku,
            p.current_stock,
            p.minimum_stock,
            p.status,
            p.cost_price,
            p.selling_price,
            c.name AS category_name
        FROM inventory_products p

        LEFT JOIN inventory_categories c
            ON c.id = p.category_id
            AND c.business_id = p.business_id
            AND c.created_by = p.created_by

        WHERE p.business_id = ?
        AND p.created_by = ?

        ORDER BY p.id DESC
        LIMIT 10
    ");

    $stmt->execute([
        $businessId,
        $userId
    ]);

    $recentProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $error = $e->getMessage();

    $totalProducts = 0;
    $totalStock = 0;
    $lowStock = 0;
    $outOfStock = 0;
    $inventoryValue = 0;
    $activeProducts = 0;
    $lowStockProducts = [];
    $recentProducts = [];
}

$pageTitle = 'Inventory Dashboard';

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

<style>

body {
    min-height: 100vh;
    overflow-x: hidden;
}

.inventory-main {
    min-width: 0;
}

.summary-card {
    border: 0;
    border-radius: 16px;
    transition: .2s;
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

.dashboard-card {
    border: 0;
    border-radius: 16px;
}

.product-row td {
    vertical-align: middle;
}

.stock-good {
    color: #198754;
    font-weight: 700;
}

.stock-low {
    color: #fd7e14;
    font-weight: 700;
}

.stock-out {
    color: #dc3545;
    font-weight: 700;
}

.status-badge {
    padding: 5px 10px;
    border-radius: 50px;
    font-size: .72rem;
    font-weight: 700;
}

.status-active {
    background: rgba(25,135,84,.12);
    color: #198754;
}

.status-inactive {
    background: rgba(220,53,69,.12);
    color: #dc3545;
}

</style>

</head>

<body class="bg-body-tertiary">

<div class="d-flex flex-column flex-lg-row min-vh-100">

<?php

$sidebarPath =
    __DIR__ . '/../../../resources/partials/InventorySidebar.php';

if (file_exists($sidebarPath)) {
    include $sidebarPath;
}

?>

<main class="inventory-main flex-grow-1">

<div class="p-3 p-md-4">

<!-- HEADER -->

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

<div>

<h2 class="fw-bold mb-1">

<i class="bi bi-box-seam text-primary me-2"></i>

Inventory Dashboard

</h2>

<p class="text-muted mb-0">

Manage inventory for

<span class="fw-semibold text-primary">

<?= htmlspecialchars($businessName) ?>

</span>

</p>

</div>

<a
    href="index.php?page=inventory_products"
    class="btn btn-primary rounded-3 fw-bold"
>

<i class="bi bi-box-seam me-1"></i>

Manage Products

</a>

</div>


<!-- ERROR -->

<?php if ($error !== ''): ?>

<div class="alert alert-danger">

<i class="bi bi-exclamation-triangle me-2"></i>

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>


<!-- SUMMARY -->

<div class="row g-3 mb-4">

<div class="col-12 col-md-6 col-xl-3">

<div class="card summary-card shadow-sm h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between">

<div>

<div class="text-muted small fw-semibold">
Total Products
</div>

<div class="fs-3 fw-bold mt-2">

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


<div class="col-12 col-md-6 col-xl-3">

<div class="card summary-card shadow-sm h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between">

<div>

<div class="text-muted small fw-semibold">
Total Stock
</div>

<div class="fs-3 fw-bold mt-2">

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


<div class="col-12 col-md-6 col-xl-3">

<div class="card summary-card shadow-sm h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between">

<div>

<div class="text-muted small fw-semibold">
Low Stock
</div>

<div class="fs-3 fw-bold text-warning mt-2">

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


<div class="col-12 col-md-6 col-xl-3">

<div class="card summary-card shadow-sm h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between">

<div>

<div class="text-muted small fw-semibold">
Out of Stock
</div>

<div class="fs-3 fw-bold text-danger mt-2">

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


<!-- SECONDARY STATS -->

<div class="row g-3 mb-4">

<div class="col-12 col-md-6">

<div class="card dashboard-card shadow-sm h-100">

<div class="card-body p-4">

<div class="text-muted small fw-semibold">
Inventory Value
</div>

<div class="fs-3 fw-bold mt-2">

₱<?= number_format($inventoryValue, 2) ?>

</div>

<div class="small text-muted mt-1">

Based on current stock × cost price

</div>

</div>

</div>

</div>


<div class="col-12 col-md-6">

<div class="card dashboard-card shadow-sm h-100">

<div class="card-body p-4">

<div class="text-muted small fw-semibold">
Active Products
</div>

<div class="fs-3 fw-bold mt-2">

<?= number_format($activeProducts) ?>

</div>

<div class="small text-muted mt-1">

Currently active inventory items

</div>

</div>

</div>

</div>

</div>


<div class="row g-4">

<!-- LOW STOCK -->

<div class="col-12 col-xl-6">

<div class="card dashboard-card shadow-sm h-100">

<div class="card-header bg-body border-0 p-4">

<div class="d-flex justify-content-between align-items-center">

<div>

<h5 class="fw-bold mb-1">

Low Stock Products

</h5>

<div class="small text-muted">

Products that need attention

</div>

</div>

<a
    href="index.php?page=inventory_products"
    class="btn btn-sm btn-outline-primary rounded-3"
>

View All

</a>

</div>

</div>


<div class="card-body p-0">

<?php if (!$lowStockProducts): ?>

<div class="text-center text-muted p-5">

<i class="bi bi-check-circle display-5 text-success"></i>

<div class="fw-semibold mt-3">

All products have sufficient stock.

</div>

</div>

<?php else: ?>

<div class="table-responsive">

<table class="table table-hover mb-0">

<thead>

<tr>

<th class="ps-4">
Product
</th>

<th>
Category
</th>

<th>
Stock
</th>

<th class="pe-4">
Minimum
</th>

</tr>

</thead>

<tbody>

<?php foreach ($lowStockProducts as $product): ?>

<?php

$stock =
    (float)($product['current_stock'] ?? 0);

$minimum =
    (float)($product['minimum_stock'] ?? 0);

?>

<tr class="product-row">

<td class="ps-4">

<div class="fw-semibold">

<?= htmlspecialchars($product['name']) ?>

</div>

<?php if (!empty($product['sku'])): ?>

<div class="small text-muted">

SKU:
<?= htmlspecialchars($product['sku']) ?>

</div>

<?php endif; ?>

</td>

<td>

<?= htmlspecialchars(
    $product['category_name'] ?? '-'
) ?>

</td>

<td>

<?php if ($stock <= 0): ?>

<span class="stock-out">

0.00

</span>

<?php else: ?>

<span class="stock-low">

<?= number_format($stock, 2) ?>

</span>

<?php endif; ?>

</td>

<td class="pe-4">

<?= number_format($minimum, 2) ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</div>

</div>

</div>


<!-- RECENT PRODUCTS -->

<div class="col-12 col-xl-6">

<div class="card dashboard-card shadow-sm h-100">

<div class="card-header bg-body border-0 p-4">

<div class="d-flex justify-content-between align-items-center">

<div>

<h5 class="fw-bold mb-1">

Recent Products

</h5>

<div class="small text-muted">

Recently added inventory products

</div>

</div>

<a
    href="index.php?page=inventory_products"
    class="btn btn-sm btn-outline-primary rounded-3"
>

View All

</a>

</div>

</div>


<div class="card-body p-0">

<?php if (!$recentProducts): ?>

<div class="text-center text-muted p-5">

<i class="bi bi-box-seam display-5 opacity-50"></i>

<div class="fw-semibold mt-3">

No products yet.

</div>

<a
    href="index.php?page=inventory_products"
    class="btn btn-primary btn-sm mt-3"
>

<i class="bi bi-plus-lg me-1"></i>

Add Product

</a>

</div>

<?php else: ?>

<div class="table-responsive">

<table class="table table-hover mb-0">

<thead>

<tr>

<th class="ps-4">
Product
</th>

<th>
Stock
</th>

<th>
Status
</th>

<th class="pe-4">
Selling
</th>

</tr>

</thead>

<tbody>

<?php foreach ($recentProducts as $product): ?>

<?php

$stock =
    (float)($product['current_stock'] ?? 0);

$minimum =
    (float)($product['minimum_stock'] ?? 0);

if ($stock <= 0) {
    $stockClass = 'stock-out';
} elseif ($stock <= $minimum) {
    $stockClass = 'stock-low';
} else {
    $stockClass = 'stock-good';
}

?>

<tr class="product-row">

<td class="ps-4">

<div class="fw-semibold">

<?= htmlspecialchars($product['name']) ?>

</div>

<?php if (!empty($product['sku'])): ?>

<div class="small text-muted">

SKU:
<?= htmlspecialchars($product['sku']) ?>

</div>

<?php endif; ?>

</td>

<td>

<span class="<?= $stockClass ?>">

<?= number_format($stock, 2) ?>

</span>

</td>

<td>

<?php if ($product['status'] === 'active'): ?>

<span class="status-badge status-active">

Active

</span>

<?php else: ?>

<span class="status-badge status-inactive">

Inactive

</span>

<?php endif; ?>

</td>

<td class="pe-4">

₱<?= number_format(
    (float)$product['selling_price'],
    2
) ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</div>

</div>

</div>

</div>

</div>

</main>

</div>

</body>

</html>