<?php

$pdo = Database::getConnection();

$businessId = $_SESSION['business_id'] ?? null;
$userId = (int)($_SESSION['user_id'] ?? 0);

if (!$businessId) {
    header('Location: index.php?page=select_business');
    exit;
}

$businessName = $_SESSION['business_name'] ?? 'Business';

$activePage = 'inventory_dashboard';
$pageTitle = 'Inventory Dashboard';

/*
|--------------------------------------------------------------------------
| INVENTORY SUMMARY
|--------------------------------------------------------------------------
*/

$totalProducts = 0;
$lowStockProducts = 0;
$outOfStockProducts = 0;
$inventoryValue = 0;

try {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_products,

            SUM(
                CASE
                    WHEN current_stock > 0
                    AND current_stock <= minimum_stock
                    THEN 1
                    ELSE 0
                END
            ) AS low_stock_products,

            SUM(
                CASE
                    WHEN current_stock <= 0
                    THEN 1
                    ELSE 0
                END
            ) AS out_of_stock_products,

            COALESCE(
                SUM(
                    current_stock * cost_price
                ),
                0
            ) AS inventory_value

        FROM inventory_products

        WHERE business_id = ? AND created_by = ?
    ");

    $stmt->execute([$businessId, $userId]);

    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($summary) {
        $totalProducts = (int)($summary['total_products'] ?? 0);
        $lowStockProducts = (int)($summary['low_stock_products'] ?? 0);
        $outOfStockProducts = (int)($summary['out_of_stock_products'] ?? 0);
        $inventoryValue = (float)($summary['inventory_value'] ?? 0);
    }

} catch (Throwable $e) {
    $totalProducts = 0;
    $lowStockProducts = 0;
    $outOfStockProducts = 0;
    $inventoryValue = 0;
}


/*
|--------------------------------------------------------------------------
| LOW STOCK PRODUCTS
|--------------------------------------------------------------------------
*/

$lowStockList = [];

try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            sku,
            current_stock,
            minimum_stock,
            cost_price,
            status

        FROM inventory_products

        WHERE business_id = ? 
        AND created_by = ?
        AND current_stock > 0
        AND current_stock <= minimum_stock

        ORDER BY current_stock ASC, name ASC

        LIMIT 10
    ");

    $stmt->execute([$businessId, $userId]);

    $lowStockList = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $lowStockList = [];
}


/*
|--------------------------------------------------------------------------
| RECENT STOCK MOVEMENTS
|--------------------------------------------------------------------------
*/

$recentMovements = [];

try {
    $stmt = $pdo->prepare("
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
            m.created_by,
            p.name AS product_name,
            p.sku AS product_sku

        FROM inventory_stock_movements m

        INNER JOIN inventory_products p
            ON p.id = m.product_id
            AND p.business_id = m.business_id
            AND p.created_by = m.created_by

        WHERE m.business_id = ? AND m.created_by = ?

        ORDER BY m.id DESC

        LIMIT 10
    ");

    $stmt->execute([$businessId, $userId]);

    $recentMovements = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $recentMovements = [];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width,initial-scale=1.0"
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
(function(){
    const theme = localStorage.getItem('bs-theme') || 'light';
    document.documentElement.setAttribute('data-bs-theme', theme);
})();
</script>

<style>

body{
    min-height:100vh;
    overflow-x:hidden;
}

.inventory-main{
    min-width:0;
    width:100%;
}

.inventory-header{
    padding-bottom:4px;
}

.inventory-card{
    border:0;
    border-radius:16px;
    transition:transform .2s ease,box-shadow .2s ease;
}

.inventory-card:hover{
    transform:translateY(-2px);
}

.inventory-icon{
    width:48px;
    height:48px;
    border-radius:14px;
    display:flex;
    align-items:center;
    justify-content:center;
    flex-shrink:0;
    font-size:1.25rem;
}

.inventory-value{
    font-size:1.8rem;
    font-weight:700;
}

.inventory-section{
    border:0;
    border-radius:16px;
    overflow:hidden;
}

.inventory-section-header{
    padding:20px 24px;
}

.empty-state{
    padding:50px 20px;
}

.quick-action{
    display:flex;
    align-items:center;
    gap:12px;
    padding:15px;
    border:1px solid var(--bs-border-color);
    border-radius:12px;
    text-decoration:none;
    color:var(--bs-body-color);
    transition:all .15s ease;
    height:100%;
}

.quick-action:hover{
    color:var(--bs-primary);
    border-color:var(--bs-primary);
    background:rgba(var(--bs-primary-rgb),.04);
}

.quick-action-icon{
    width:42px;
    height:42px;
    border-radius:11px;
    display:flex;
    align-items:center;
    justify-content:center;
    flex-shrink:0;
}

.inventory-table{
    width:100%;
}

.inventory-table th{
    font-size:.72rem;
    letter-spacing:.04em;
    white-space:nowrap;
}

.inventory-table td{
    vertical-align:middle;
}

.stock-low{
    color:var(--bs-warning);
    font-weight:700;
}

.stock-out{
    color:var(--bs-danger);
    font-weight:700;
}

.stock-ok{
    color:var(--bs-success);
    font-weight:700;
}

.movement-in{
    color:var(--bs-success);
    font-weight:700;
}

.movement-out{
    color:var(--bs-danger);
    font-weight:700;
}

@media(max-width:575.98px){

    .inventory-main>.p-3,
    .inventory-main>.p-md-4{
        padding:14px!important;
    }

    .inventory-header{
        margin-bottom:16px!important;
    }

    .inventory-header h2{
        font-size:1.35rem;
    }

    .inventory-header p{
        font-size:.75rem;
    }

    .inventory-card{
        border-radius:14px;
    }

    .inventory-card .card-body{
        padding:16px;
    }

    .inventory-icon{
        width:42px;
        height:42px;
        border-radius:12px;
    }

    .inventory-value{
        font-size:1.4rem;
    }

    .inventory-section{
        border-radius:14px;
    }

    .inventory-section-header{
        padding:16px;
    }

}

</style>

</head>

<body class="bg-body-tertiary">

<div class="d-flex flex-column flex-lg-row min-vh-100">

<?php

$sidebarPath = __DIR__ . '/../../resources/partials/InventorySidebar.php';

if (file_exists($sidebarPath)) {
    include $sidebarPath;
}

?>

<main class="inventory-main flex-grow-1 bg-body-tertiary">

<div class="p-3 p-md-4">

<!-- =====================================================
     HEADER
====================================================== -->

<div class="inventory-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

<div>

<div class="d-flex align-items-center gap-2 mb-1">

<div class="d-lg-none bg-primary bg-opacity-10 text-primary rounded-3 p-2">

<i class="bi bi-boxes"></i>

</div>

<h2 class="fw-bold text-body mb-0">
Inventory Dashboard
</h2>

</div>

<p class="text-muted small mb-0">

Manage your products and stock for

<span class="fw-semibold text-primary">
<?= htmlspecialchars($businessName) ?>
</span>

</p>

</div>

<a
    href="index.php?page=inventory_products_create"
    class="btn btn-primary fw-bold px-3 py-2 rounded-3 shadow-sm d-flex align-items-center gap-2"
>

<i class="bi bi-plus-lg"></i>

<span>
Add Product
</span>

</a>

</div>


<!-- =====================================================
     STATISTICS
====================================================== -->

<div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3 mb-4">


<!-- TOTAL PRODUCTS -->

<div class="col">

<div class="card inventory-card shadow-sm bg-body h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start gap-3">

<div>

<div class="text-muted fw-bold small mb-2">
TOTAL PRODUCTS
</div>

<div class="inventory-value text-primary">
<?= number_format($totalProducts) ?>
</div>

<div class="text-muted small mt-1">
Registered products
</div>

</div>

<div class="inventory-icon bg-primary bg-opacity-10 text-primary">

<i class="bi bi-box-seam"></i>

</div>

</div>

</div>

</div>

</div>


<!-- LOW STOCK -->

<div class="col">

<div class="card inventory-card shadow-sm bg-body h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start gap-3">

<div>

<div class="text-muted fw-bold small mb-2">
LOW STOCK
</div>

<div class="inventory-value text-warning">
<?= number_format($lowStockProducts) ?>
</div>

<div class="text-muted small mt-1">
Need replenishment
</div>

</div>

<div class="inventory-icon bg-warning bg-opacity-10 text-warning">

<i class="bi bi-exclamation-triangle"></i>

</div>

</div>

</div>

</div>

</div>


<!-- OUT OF STOCK -->

<div class="col">

<div class="card inventory-card shadow-sm bg-body h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start gap-3">

<div>

<div class="text-muted fw-bold small mb-2">
OUT OF STOCK
</div>

<div class="inventory-value text-danger">
<?= number_format($outOfStockProducts) ?>
</div>

<div class="text-muted small mt-1">
Currently unavailable
</div>

</div>

<div class="inventory-icon bg-danger bg-opacity-10 text-danger">

<i class="bi bi-box2"></i>

</div>

</div>

</div>

</div>

</div>


<!-- INVENTORY VALUE -->

<div class="col">

<div class="card inventory-card shadow-sm bg-body h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start gap-3">

<div class="min-width-0">

<div class="text-muted fw-bold small mb-2">
INVENTORY VALUE
</div>

<div class="inventory-value text-success text-truncate">

₱<?= number_format($inventoryValue,2) ?>

</div>

<div class="text-muted small mt-1">
Based on cost price
</div>

</div>

<div class="inventory-icon bg-success bg-opacity-10 text-success">

<i class="bi bi-cash-stack"></i>

</div>

</div>

</div>

</div>

</div>

</div>


<!-- =====================================================
     QUICK ACTIONS
====================================================== -->

<div class="card inventory-section shadow-sm bg-body mb-4">

<div class="inventory-section-header">

<div>

<h5 class="fw-bold mb-1">
Quick Actions
</h5>

<p class="text-muted small mb-0">
Common inventory operations
</p>

</div>

</div>

<div class="card-body pt-0">

<div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3">


<div class="col">

<a
    href="index.php?page=inventory_products"
    class="quick-action"
>

<div class="quick-action-icon bg-primary bg-opacity-10 text-primary">

<i class="bi bi-plus-lg"></i>

</div>

<div>

<div class="fw-bold">
Add Product
</div>

<div class="text-muted small">
Create a new product
</div>

</div>

</a>

</div>


<div class="col">

<a
    href="index.php?page=inventory_stock_in"
    class="quick-action"
>

<div class="quick-action-icon bg-success bg-opacity-10 text-success">

<i class="bi bi-box-arrow-in-down"></i>

</div>

<div>

<div class="fw-bold">
Stock In
</div>

<div class="text-muted small">
Add inventory stock
</div>

</div>

</a>

</div>


<div class="col">

<a
    href="index.php?page=inventory_stock_out"
    class="quick-action"
>

<div class="quick-action-icon bg-danger bg-opacity-10 text-danger">

<i class="bi bi-box-arrow-up"></i>

</div>

<div>

<div class="fw-bold">
Stock Out
</div>

<div class="text-muted small">
Remove inventory stock
</div>

</div>

</a>

</div>


<div class="col">

<a
    href="index.php?page=inventory_products"
    class="quick-action"
>

<div class="quick-action-icon bg-info bg-opacity-10 text-info">

<i class="bi bi-search"></i>

</div>

<div>

<div class="fw-bold">
View Products
</div>

<div class="text-muted small">
Browse inventory
</div>

</div>

</a>

</div>

</div>

</div>

</div>


<!-- =====================================================
     LOW STOCK PRODUCTS
====================================================== -->

<div class="card inventory-section shadow-sm bg-body mb-4">

<div class="inventory-section-header d-flex justify-content-between align-items-center gap-3">

<div>

<h5 class="fw-bold mb-1">
Low Stock Products
</h5>

<p class="text-muted small mb-0">
Products that need replenishment
</p>

</div>

<a
    href="index.php?page=inventory_products&filter=low_stock"
    class="btn btn-sm btn-outline-primary fw-semibold"
>

View All

<i class="bi bi-arrow-right ms-1"></i>

</a>

</div>


<?php if (!$lowStockList): ?>

<div class="empty-state text-center text-muted">

<div class="mb-3">

<i class="bi bi-check-circle display-6 opacity-50"></i>

</div>

<div class="fw-semibold mb-1">
No low-stock products
</div>

<div class="small">
All products currently have sufficient stock.
</div>

</div>

<?php else: ?>

<div class="table-responsive">

<table class="table inventory-table table-hover align-middle mb-0">

<thead class="table-light text-uppercase text-muted">

<tr>

<th class="ps-4">
Product
</th>

<th>
SKU
</th>

<th>
Current Stock
</th>

<th>
Minimum Stock
</th>

<th>
Status
</th>

</tr>

</thead>

<tbody>

<?php foreach ($lowStockList as $product): ?>

<tr>

<td class="ps-4">

<div class="fw-bold">

<?= htmlspecialchars($product['name']) ?>

</div>

</td>

<td>

<span class="small text-muted">

<?= htmlspecialchars($product['sku'] ?? '-') ?>

</span>

</td>

<td>

<span class="stock-low">

<?= number_format(
    (float)$product['current_stock'],
    2
) ?>

</span>

</td>

<td>

<span class="small">

<?= number_format(
    (float)$product['minimum_stock'],
    2
) ?>

</span>

</td>

<td>

<span class="badge text-bg-warning">
Low Stock
</span>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</div>


<!-- =====================================================
     RECENT STOCK MOVEMENTS
====================================================== -->

<div class="card inventory-section shadow-sm bg-body">

<div class="inventory-section-header d-flex justify-content-between align-items-center gap-3">

<div>

<h5 class="fw-bold mb-1">
Recent Stock Movements
</h5>

<p class="text-muted small mb-0">
Latest inventory activity
</p>

</div>

<a
    href="index.php?page=inventory_stock_history"
    class="small text-decoration-none fw-semibold text-nowrap"
>

View All

<i class="bi bi-arrow-right ms-1"></i>

</a>

</div>


<?php if (!$recentMovements): ?>

<div class="empty-state text-center text-muted">

<div class="mb-3">

<i class="bi bi-clock-history display-6 opacity-50"></i>

</div>

<div class="fw-semibold mb-1">
No stock movements yet
</div>

<div class="small">
Stock-in and stock-out activity will appear here.
</div>

</div>

<?php else: ?>

<div class="table-responsive">

<table class="table inventory-table table-hover align-middle mb-0">

<thead class="table-light text-uppercase text-muted">

<tr>

<th class="ps-4">
Product
</th>

<th>
Movement
</th>

<th>
Quantity
</th>

<th>
Previous
</th>

<th>
New Stock
</th>

<th>
Date
</th>

</tr>

</thead>

<tbody>

<?php foreach ($recentMovements as $movement): ?>

<?php

$movementType = $movement['movement_type'] ?? '';

$isStockIn = in_array(
    $movementType,
    ['stock_in', 'opening_stock'],
    true
);

$movementClass = $isStockIn
    ? 'movement-in'
    : 'movement-out';

$movementIcon = $isStockIn
    ? 'bi-arrow-down-circle'
    : 'bi-arrow-up-circle';

$movementLabel = match ($movementType) {

    'stock_in' => 'Stock In',

    'stock_out' => 'Stock Out',

    'opening_stock' => 'Opening Stock',

    default => ucwords(
        str_replace('_', ' ', $movementType)
    )

};

?>

<tr>

<td class="ps-4">

<div class="fw-bold">

<?= htmlspecialchars(
    $movement['product_name'] ?? 'Unknown Product'
) ?>

</div>

<?php if (!empty($movement['product_sku'])): ?>

<div class="small text-muted">

<?= htmlspecialchars(
    $movement['product_sku']
) ?>

</div>

<?php endif; ?>

</td>


<td>

<span class="<?= $movementClass ?>">

<i class="bi <?= $movementIcon ?> me-1"></i>

<?= htmlspecialchars($movementLabel) ?>

</span>

</td>


<td>

<span class="<?= $movementClass ?>">

<?= number_format(
    (float)$movement['quantity'],
    2
) ?>

</span>

</td>


<td>

<span class="small">

<?= number_format(
    (float)$movement['previous_stock'],
    2
) ?>

</span>

</td>


<td>

<span class="fw-semibold">

<?= number_format(
    (float)$movement['new_stock'],
    2
) ?>

</span>

</td>


<td>

<span class="small text-muted">

<?= !empty($movement['created_at'])
    ? date(
        'M d, Y h:i A',
        strtotime($movement['created_at'])
    )
    : '-'
?>

</span>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</div>

</div>

</main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>