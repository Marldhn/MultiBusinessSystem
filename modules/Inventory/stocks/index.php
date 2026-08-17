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

/* =========================================================
   STOCK MOVEMENT
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'stock_movement') {

    $productId = (int)($_POST['product_id'] ?? 0);
    $movementType = $_POST['movement_type'] ?? '';
    $quantity = (float)($_POST['quantity'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($productId <= 0) {
        $error = 'Please select a product.';
    } elseif (!in_array($movementType, ['stock_in', 'stock_out', 'adjustment'], true)) {
        $error = 'Invalid stock movement type.';
    } elseif ($quantity <= 0) {
        $error = 'Quantity must be greater than zero.';
    } elseif ($reason === '') {
        $error = 'Reason is required.';
    } else {

        try {

            $pdo->beginTransaction();

            /* =====================================================
               LOCK PRODUCT
            ===================================================== */

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    name,
                    current_stock,
                    cost_price
                FROM inventory_products
                WHERE id = ?
                AND business_id = ?
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                $productId,
                $businessId
            ]);

            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new Exception('Product not found.');
            }

            $previousStock = (float)$product['current_stock'];
            $unitCost = (float)$product['cost_price'];

            /* =====================================================
               CALCULATE NEW STOCK
            ===================================================== */

            if ($movementType === 'stock_in') {

                $newStock = $previousStock + $quantity;

            } elseif ($movementType === 'stock_out') {

                if ($quantity > $previousStock) {
                    throw new Exception(
                        'Stock Out quantity cannot be greater than current stock.'
                    );
                }

                $newStock = $previousStock - $quantity;

            } else {

                /*
                 * Adjustment:
                 * quantity represents the NEW stock quantity.
                 */

                $newStock = $quantity;
            }

            $newStock = max(0, $newStock);

            /* =====================================================
               UPDATE PRODUCT STOCK
            ===================================================== */

            $stmt = $pdo->prepare("
                UPDATE inventory_products
                SET current_stock = ?,
                    updated_at = NOW()
                WHERE id = ?
                AND business_id = ?
            ");

            $stmt->execute([
                $newStock,
                $productId,
                $businessId
            ]);

            /* =====================================================
               SAVE STOCK MOVEMENT
            ===================================================== */

            $movementDatabaseType = $movementType;

            $stmt = $pdo->prepare("
                INSERT INTO inventory_stock_movements (
                    business_id,
                    product_id,
                    movement_type,
                    quantity,
                    unit_cost,
                    previous_stock,
                    new_stock,
                    reference_type,
                    reference_id,
                    notes,
                    created_by
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, 'manual', ?, ?, ?
                )
            ");

            $movementNotes = $reason;

            if ($notes !== '') {
                $movementNotes .= ' | Notes: ' . $notes;
            }

            $stmt->execute([
                $businessId,
                $productId,
                $movementDatabaseType,
                $quantity,
                $unitCost,
                $previousStock,
                $newStock,
                $productId,
                $movementNotes,
                $userId
            ]);

            $pdo->commit();

            $success =
                'Stock updated successfully. ' .
                htmlspecialchars($product['name']) .
                ' is now at ' .
                number_format($newStock, 2) .
                ' stock.';

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e->getMessage();
        }
    }
}


/* =========================================================
   LOAD PRODUCTS
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.name,
        p.sku,
        p.barcode,
        p.current_stock,
        p.minimum_stock,
        p.maximum_stock,
        p.cost_price,
        p.status,

        c.name AS category_name,
        u.name AS unit_name,
        u.abbreviation AS unit_abbreviation

    FROM inventory_products p

    LEFT JOIN inventory_categories c
        ON c.id = p.category_id
        AND c.business_id = p.business_id

    LEFT JOIN inventory_units u
        ON u.id = p.unit_id
        AND u.business_id = p.business_id

    WHERE p.business_id = ?

    ORDER BY p.name ASC
");

$stmt->execute([$businessId]);

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   LOAD STOCK MOVEMENTS
========================================================= */

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
        m.notes,
        m.created_at,

        p.name AS product_name,
        p.sku,

        u.name AS unit_name,
        u.abbreviation AS unit_abbreviation

    FROM inventory_stock_movements m

    INNER JOIN inventory_products p
        ON p.id = m.product_id
        AND p.business_id = m.business_id

    LEFT JOIN inventory_units u
        ON u.id = p.unit_id
        AND u.business_id = p.business_id

    WHERE m.business_id = ?

    ORDER BY m.id DESC

    LIMIT 200
");

$stmt->execute([$businessId]);

$movements = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   STOCK SUMMARY
========================================================= */

$totalProducts = count($products);

$totalStock = 0;
$lowStockProducts = 0;
$outOfStockProducts = 0;
$inactiveProducts = 0;

foreach ($products as $product) {

    $stock = (float)($product['current_stock'] ?? 0);
    $minimum = (float)($product['minimum_stock'] ?? 0);

    $totalStock += $stock;

    if ($stock <= 0) {
        $outOfStockProducts++;
    } elseif ($stock <= $minimum) {
        $lowStockProducts++;
    }

    if (($product['status'] ?? '') !== 'active') {
        $inactiveProducts++;
    }
}


/* =========================================================
   PAGE
========================================================= */

$activePage = 'inventory_stock';
$pageTitle = 'Stock - Inventory';
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

/* =========================================================
   HEADER
========================================================= */

.inventory-header {
    padding-bottom: 4px;
}

.inventory-header h2 {
    font-size: 1.5rem;
}

/* =========================================================
   SUMMARY
========================================================= */

.summary-card {
    border: 0;
    border-radius: 16px;
    transition: transform .15s ease, box-shadow .15s ease;
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

/* =========================================================
   SECTION
========================================================= */

.stock-section {
    border: 0;
    border-radius: 16px;
    overflow: hidden;
}

.stock-section-header {
    padding: 20px 24px;
}

/* =========================================================
   SEARCH
========================================================= */

.search-wrapper {
    border: 1px solid var(--bs-border-color);
    border-radius: 10px;
    overflow: hidden;
}

.search-wrapper .input-group-text {
    border: 0;
    background: transparent;
}

.search-wrapper .form-control {
    border: 0;
    background: transparent;
}

.search-wrapper .form-control:focus {
    box-shadow: none;
}

.filter-select {
    border-radius: 10px;
}

/* =========================================================
   TABLE
========================================================= */

.stock-table {
    width: 100%;
}

.stock-table th {
    font-size: .72rem;
    letter-spacing: .04em;
    white-space: nowrap;
}

.stock-table td {
    vertical-align: middle;
    white-space: nowrap;
    padding-top: 13px;
    padding-bottom: 13px;
}

.product-name {
    font-weight: 700;
}

.product-subtext {
    font-size: .7rem;
    color: var(--bs-secondary-color);
    margin-top: 2px;
}

/* =========================================================
   STOCK
========================================================= */

.stock-ok {
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

/* =========================================================
   MOVEMENT
========================================================= */

.movement-badge {
    display: inline-flex;
    align-items: center;
    padding: .35rem .65rem;
    border-radius: 50rem;
    font-size: .7rem;
    font-weight: 700;
}

.movement-in {
    background: rgba(25, 135, 84, .12);
    color: var(--bs-success);
}

.movement-out {
    background: rgba(220, 53, 69, .12);
    color: var(--bs-danger);
}

.movement-adjustment {
    background: rgba(13, 110, 253, .12);
    color: var(--bs-primary);
}

/* =========================================================
   MODAL
========================================================= */

.stock-modal .modal-content {
    border: 0;
    border-radius: 16px;
}

.stock-modal .modal-header {
    padding: 18px 22px;
}

.stock-modal .modal-body {
    padding: 22px;
}

.stock-modal .modal-footer {
    padding: 15px 22px;
}

/* =========================================================
   CURRENT STOCK DISPLAY
========================================================= */

.current-stock-box {
    border: 1px solid var(--bs-border-color);
    border-radius: 12px;
    padding: 15px;
    background: var(--bs-tertiary-bg);
}

.current-stock-value {
    font-size: 1.7rem;
    font-weight: 800;
}

/* =========================================================
   EMPTY
========================================================= */

.empty-state {
    padding: 55px 20px;
}

/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 575.98px) {

    .inventory-main > .p-3,
    .inventory-main > .p-md-4 {
        padding: 14px !important;
    }

    .inventory-header h2 {
        font-size: 1.35rem;
    }

    .stock-section {
        border-radius: 14px;
    }

    .stock-section-header {
        padding: 15px;
    }

    .stock-table th,
    .stock-table td {
        font-size: .75rem;
    }

    .stock-modal .modal-dialog {
        margin: 10px;
    }

    .stock-modal .modal-content {
        border-radius: 15px;
    }

    .stock-modal .modal-header {
        padding: 15px 16px;
    }

    .stock-modal .modal-body {
        padding: 16px;
    }

    .stock-modal .modal-footer {
        padding: 12px 16px;
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

<div class="inventory-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

<div>

<div class="d-flex align-items-center gap-2 mb-1">

<div class="d-lg-none bg-primary bg-opacity-10 text-primary rounded-3 p-2">

<i class="bi bi-boxes"></i>

</div>

<h2 class="fw-bold text-body mb-0">
Stock
</h2>

</div>

<p class="text-muted small mb-0">

Manage stock movements for

<span class="fw-semibold text-primary">
<?= htmlspecialchars($businessName) ?>
</span>

</p>

</div>

<button
    type="button"
    class="btn btn-primary fw-bold px-3 py-2 rounded-3 shadow-sm d-flex align-items-center gap-2"
    onclick="openStockModal()"
>

<i class="bi bi-arrow-left-right"></i>

<span>
Adjust Stock
</span>

</button>

</div>


<!-- =====================================================
     SUCCESS
====================================================== -->

<?php if (!empty($success)): ?>

<div
    class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 small"
>

<i class="bi bi-check-circle-fill me-2"></i>

<?= $success ?>

<button
    type="button"
    class="btn-close"
    data-bs-dismiss="alert"
></button>

</div>

<?php endif; ?>


<!-- =====================================================
     ERROR
====================================================== -->

<?php if (!empty($error)): ?>

<div
    class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 small"
>

<i class="bi bi-exclamation-triangle-fill me-2"></i>

<?= htmlspecialchars($error) ?>

<button
    type="button"
    class="btn-close"
    data-bs-dismiss="alert"
></button>

</div>

<?php endif; ?>


<!-- =====================================================
     SUMMARY
====================================================== -->

<div class="row g-3 mb-4">

<!-- TOTAL PRODUCTS -->

<div class="col-12 col-md-6 col-xl-3">

<div class="card summary-card shadow-sm h-100 bg-body">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start">

<div>

<div class="text-muted small fw-semibold mb-2">
Products
</div>

<div class="fs-3 fw-bold">
<?= number_format($totalProducts) ?>
</div>

<div class="small text-muted mt-1">
Registered products
</div>

</div>

<div class="summary-icon bg-primary bg-opacity-10 text-primary">

<i class="bi bi-box-seam fs-4"></i>

</div>

</div>

</div>

</div>

</div>


<!-- TOTAL STOCK -->

<div class="col-12 col-md-6 col-xl-3">

<div class="card summary-card shadow-sm h-100 bg-body">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start">

<div>

<div class="text-muted small fw-semibold mb-2">
Total Stock
</div>

<div class="fs-3 fw-bold text-success">
<?= number_format($totalStock, 2) ?>
</div>

<div class="small text-muted mt-1">
Across all products
</div>

</div>

<div class="summary-icon bg-success bg-opacity-10 text-success">

<i class="bi bi-boxes fs-4"></i>

</div>

</div>

</div>

</div>

</div>


<!-- LOW STOCK -->

<div class="col-12 col-md-6 col-xl-3">

<div class="card summary-card shadow-sm h-100 bg-body">

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
Needs attention
</div>

</div>

<div class="summary-icon bg-warning bg-opacity-10 text-warning">

<i class="bi bi-exclamation-triangle fs-4"></i>

</div>

</div>

</div>

</div>

</div>


<!-- OUT OF STOCK -->

<div class="col-12 col-md-6 col-xl-3">

<div class="card summary-card shadow-sm h-100 bg-body">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start">

<div>

<div class="text-muted small fw-semibold mb-2">
Out of Stock
</div>

<div class="fs-3 fw-bold text-danger">
<?= number_format($outOfStockProducts) ?>
</div>

<div class="small text-muted mt-1">
No stock available
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
     PRODUCT STOCK
====================================================== -->

<div class="card stock-section shadow-sm bg-body mb-4">

<div class="stock-section-header">

<div class="d-flex flex-column flex-lg-row justify-content-between gap-3">

<div>

<h5 class="fw-bold mb-1">
Current Stock
</h5>

<p class="text-muted small mb-0">
View and manage current inventory quantities.
</p>

</div>

</div>


<!-- SEARCH -->

<div class="row g-2 mt-3">

<div class="col-12 col-lg-5">

<div class="search-wrapper input-group">

<span class="input-group-text text-muted">

<i class="bi bi-search"></i>

</span>

<input
    type="text"
    id="stockSearch"
    class="form-control"
    placeholder="Search product, SKU, barcode..."
    autocomplete="off"
>

<button
    type="button"
    id="clearStockSearch"
    class="btn d-none"
>

<i class="bi bi-x-lg"></i>

</button>

</div>

</div>


<div class="col-12 col-sm-6 col-lg-3">

<select
    id="stockStatusFilter"
    class="form-select filter-select"
>

<option value="all">
All Stock
</option>

<option value="in_stock">
In Stock
</option>

<option value="low_stock">
Low Stock
</option>

<option value="out_of_stock">
Out of Stock
</option>

</select>

</div>


<div class="col-12 col-sm-6 col-lg-4">

<select
    id="productFilter"
    class="form-select filter-select"
>

<option value="all">
All Products
</option>

<?php foreach ($products as $product): ?>

<option value="<?= (int)$product['id'] ?>">

<?= htmlspecialchars($product['name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>

</div>


<div class="d-flex justify-content-between align-items-center mt-3">

<div
    id="stockResultText"
    class="small text-muted"
>
Showing <?= number_format($totalProducts) ?> products
</div>

<button
    type="button"
    id="clearStockFilters"
    class="btn btn-sm btn-outline-secondary rounded-3"
>

<i class="bi bi-arrow-counterclockwise me-1"></i>

Clear

</button>

</div>

</div>


<?php if (!$products): ?>

<div class="empty-state text-center text-muted">

<div class="mb-3">

<i class="bi bi-box-seam display-6 opacity-50"></i>

</div>

<div class="fw-semibold mb-1">
No products found
</div>

<div class="small">
Create a product first before managing stock.
</div>

</div>

<?php else: ?>

<div class="table-responsive">

<table class="stock-table table table-hover align-middle mb-0">

<thead class="table-light text-uppercase text-muted">

<tr>

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
Current Stock
</th>

<th class="py-3">
Minimum
</th>

<th class="py-3">
Maximum
</th>

<th class="py-3">
Status
</th>

<th class="py-3 pe-4 text-end">
Action
</th>

</tr>

</thead>

<tbody id="stockProductBody">

<?php foreach ($products as $product): ?>

<?php

$stock = (float)($product['current_stock'] ?? 0);
$minimum = (float)($product['minimum_stock'] ?? 0);
$maximum = $product['maximum_stock'] !== null
    ? (float)$product['maximum_stock']
    : null;

if ($stock <= 0) {

    $stockStatus = 'out_of_stock';
    $stockClass = 'stock-out';
    $stockLabel = 'Out of stock';

} elseif ($stock <= $minimum) {

    $stockStatus = 'low_stock';
    $stockClass = 'stock-low';
    $stockLabel = 'Low stock';

} else {

    $stockStatus = 'in_stock';
    $stockClass = 'stock-ok';
    $stockLabel = 'In stock';

}

$searchText = strtolower(
    ($product['name'] ?? '') . ' ' .
    ($product['sku'] ?? '') . ' ' .
    ($product['barcode'] ?? '') . ' ' .
    ($product['category_name'] ?? '')
);

?>

<tr
    class="stock-product-row"
    data-search="<?= htmlspecialchars($searchText) ?>"
    data-stock-status="<?= htmlspecialchars($stockStatus) ?>"
    data-product-id="<?= (int)$product['id'] ?>"
>

<td class="ps-4">

<div class="product-name">

<?= htmlspecialchars($product['name']) ?>

</div>

<?php if (!empty($product['barcode'])): ?>

<div class="product-subtext">

<i class="bi bi-upc-scan me-1"></i>

<?= htmlspecialchars($product['barcode']) ?>

</div>

<?php endif; ?>

</td>


<td>

<span class="small">

<?= htmlspecialchars($product['sku'] ?: '-') ?>

</span>

</td>


<td>

<span class="small">

<?= htmlspecialchars($product['category_name'] ?: '-') ?>

</span>

</td>


<td>

<span class="<?= $stockClass ?>">

<?= number_format($stock, 2) ?>

</span>

<?php if (!empty($product['unit_abbreviation'])): ?>

<span class="text-muted small">

<?= htmlspecialchars($product['unit_abbreviation']) ?>

</span>

<?php endif; ?>

<div
    class="<?= $stockStatus === 'out_of_stock'
        ? 'text-danger'
        : ($stockStatus === 'low_stock'
            ? 'text-warning'
            : 'text-success') ?>"
    style="font-size:.68rem;"
>

<?= htmlspecialchars($stockLabel) ?>

</div>

</td>


<td>

<span class="small">

<?= number_format($minimum, 2) ?>

</span>

</td>


<td>

<span class="small">

<?= $maximum !== null
    ? number_format($maximum, 2)
    : '-' ?>

</span>

</td>


<td>

<?php if ($product['status'] === 'active'): ?>

<span class="badge bg-success-subtle text-success rounded-pill">
Active
</span>

<?php else: ?>

<span class="badge bg-danger-subtle text-danger rounded-pill">
Inactive
</span>

<?php endif; ?>

</td>


<td class="text-end pe-4">

<button
    type="button"
    class="btn btn-sm btn-primary rounded-3"
    onclick="openStockModal(
        <?= (int)$product['id'] ?>,
        '<?= htmlspecialchars(
            addslashes($product['name']),
            ENT_QUOTES
        ) ?>',
        <?= $stock ?>
    )"
>

<i class="bi bi-arrow-left-right me-1"></i>

Adjust

</button>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>


<div
    id="noStockResults"
    class="empty-state text-center text-muted d-none"
>

<div class="mb-3">

<i class="bi bi-search display-6 opacity-50"></i>

</div>

<div class="fw-semibold mb-1">
No products found
</div>

<div class="small">
Try changing your search or filter.
</div>

</div>

<?php endif; ?>

</div>


<!-- =====================================================
     STOCK MOVEMENT HISTORY
====================================================== -->

<div class="card stock-section shadow-sm bg-body">

<div class="stock-section-header">

<div>

<h5 class="fw-bold mb-1">
Stock Movement History
</h5>

<p class="text-muted small mb-0">
Latest stock transactions.
</p>

</div>

</div>


<?php if (!$movements): ?>

<div class="empty-state text-center text-muted">

<div class="mb-3">

<i class="bi bi-clock-history display-6 opacity-50"></i>

</div>

<div class="fw-semibold mb-1">
No stock movements yet
</div>

<div class="small">
Stock movements will appear here after you adjust inventory.
</div>

</div>

<?php else: ?>

<div class="table-responsive">

<table class="table table-hover align-middle mb-0">

<thead class="table-light text-uppercase text-muted">

<tr>

<th class="ps-4 py-3">
Date
</th>

<th class="py-3">
Product
</th>

<th class="py-3">
Movement
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
Reason / Notes
</th>

</tr>

</thead>

<tbody>

<?php foreach ($movements as $movement): ?>

<?php

$type = $movement['movement_type'];

if ($type === 'stock_in') {

    $movementClass = 'movement-in';
    $movementIcon = 'bi-arrow-down-circle';
    $movementLabel = 'Stock In';

} elseif ($type === 'stock_out') {

    $movementClass = 'movement-out';
    $movementIcon = 'bi-arrow-up-circle';
    $movementLabel = 'Stock Out';

} elseif ($type === 'adjustment') {

    $movementClass = 'movement-adjustment';
    $movementIcon = 'bi-sliders';
    $movementLabel = 'Adjustment';

} else {

    $movementClass = 'movement-adjustment';
    $movementIcon = 'bi-box-seam';
    $movementLabel = ucwords(
        str_replace('_', ' ', $type)
    );

}

?>

<tr>

<td class="ps-4">

<div class="small fw-semibold">

<?= htmlspecialchars(
    date('M d, Y', strtotime($movement['created_at']))
) ?>

</div>

<div class="text-muted" style="font-size:.68rem;">

<?= htmlspecialchars(
    date('h:i A', strtotime($movement['created_at']))
) ?>

</div>

</td>


<td>

<div class="fw-semibold small">

<?= htmlspecialchars($movement['product_name']) ?>

</div>

<?php if (!empty($movement['sku'])): ?>

<div class="text-muted" style="font-size:.68rem;">

SKU:
<?= htmlspecialchars($movement['sku']) ?>

</div>

<?php endif; ?>

</td>


<td>

<span class="movement-badge <?= $movementClass ?>">

<i class="bi <?= $movementIcon ?> me-1"></i>

<?= htmlspecialchars($movementLabel) ?>

</span>

</td>


<td>

<span class="fw-bold">

<?= number_format(
    (float)$movement['quantity'],
    2
) ?>

</span>

<?php if (!empty($movement['unit_abbreviation'])): ?>

<span class="text-muted small">

<?= htmlspecialchars(
    $movement['unit_abbreviation']
) ?>

</span>

<?php endif; ?>

</td>


<td>

<span class="text-muted">

<?= number_format(
    (float)$movement['previous_stock'],
    2
) ?>

</span>

</td>


<td>

<span class="fw-bold">

<?= number_format(
    (float)$movement['new_stock'],
    2
) ?>

</span>

</td>


<td>

<div
    class="small text-truncate"
    style="max-width:260px;"
    title="<?= htmlspecialchars(
        $movement['notes'] ?? ''
    ) ?>"
>

<?= htmlspecialchars(
    $movement['notes'] ?: '-'
) ?>

</div>

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


<!-- =========================================================
     STOCK MOVEMENT MODAL
========================================================= -->

<div
    class="modal fade stock-modal"
    id="stockModal"
    tabindex="-1"
    aria-labelledby="stockModalLabel"
    aria-hidden="true"
>

<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">

<div class="modal-content shadow-lg">

<div class="modal-header border-bottom">

<h5
    class="modal-title fw-bold"
    id="stockModalLabel"
>

<i class="bi bi-boxes text-primary me-2"></i>

Adjust Stock

</h5>

<button
    type="button"
    class="btn-close shadow-none"
    data-bs-dismiss="modal"
></button>

</div>


<form method="POST" id="stockMovementForm">

<input
    type="hidden"
    name="action"
    value="stock_movement"
>

<input
    type="hidden"
    name="product_id"
    id="stockProductId"
>


<div class="modal-body">

<!-- PRODUCT -->

<div class="mb-3">

<label class="form-label fw-semibold">
Product
</label>

<select
    class="form-select"
    id="stockProductSelect"
    onchange="selectStockProduct()"
>

<option value="">
Select Product
</option>

<?php foreach ($products as $product): ?>

<option
    value="<?= (int)$product['id'] ?>"
    data-stock="<?= (float)$product['current_stock'] ?>"
>

<?= htmlspecialchars($product['name']) ?>

<?php if (!empty($product['sku'])): ?>

- <?= htmlspecialchars($product['sku']) ?>

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<!-- CURRENT STOCK -->

<div class="current-stock-box mb-4">

<div class="d-flex justify-content-between align-items-center">

<div>

<div class="small text-muted fw-semibold">
Current Stock
</div>

<div
    class="current-stock-value text-primary"
    id="currentStockDisplay"
>
0.00
</div>

</div>

<div class="text-end">

<div class="small text-muted">
Product
</div>

<div
    class="fw-bold"
    id="currentProductName"
>
-
</div>

</div>

</div>

</div>


<!-- MOVEMENT TYPE -->

<div class="mb-3">

<label class="form-label fw-semibold">

Stock Action

<span class="text-danger">*</span>

</label>

<select
    name="movement_type"
    id="movementType"
    class="form-select"
    required
    onchange="updateStockPreview()"
>

<option value="stock_in">
Stock In
</option>

<option value="stock_out">
Stock Out
</option>

<option value="adjustment">
Adjustment
</option>

</select>

<div class="form-text">

Stock In adds quantity.

Stock Out removes quantity.

Adjustment sets the stock to the exact quantity entered.

</div>

</div>


<!-- QUANTITY -->

<div class="mb-3">

<label
    class="form-label fw-semibold"
    id="quantityLabel"
>

Quantity

<span class="text-danger">*</span>

</label>

<input
    type="number"
    name="quantity"
    id="stockQuantity"
    class="form-control"
    min="0.01"
    step="0.01"
    value="1"
    required
    oninput="updateStockPreview()"
>

</div>


<!-- PREVIEW -->

<div
    class="alert alert-primary border-0 rounded-3"
    id="stockPreview"
>

<div class="d-flex justify-content-between">

<span>
New Stock
</span>

<strong id="newStockPreview">
0.00
</strong>

</div>

</div>


<!-- REASON -->

<div class="mb-3">

<label class="form-label fw-semibold">

Reason

<span class="text-danger">*</span>

</label>

<select
    name="reason"
    class="form-select"
    required
>

<option value="">
Select Reason
</option>

<option value="Purchase">
Purchase
</option>

<option value="Customer Return">
Customer Return
</option>

<option value="Supplier Return">
Supplier Return
</option>

<option value="Damaged">
Damaged
</option>

<option value="Expired">
Expired
</option>

<option value="Lost">
Lost
</option>

<option value="Physical Count">
Physical Count
</option>

<option value="Correction">
Correction
</option>

<option value="Transfer">
Transfer
</option>

<option value="Other">
Other
</option>

</select>

</div>


<!-- NOTES -->

<div class="mb-2">

<label class="form-label fw-semibold">

Notes

<span class="text-muted fw-normal">
(Optional)
</span>

</label>

<textarea
    name="notes"
    class="form-control"
    rows="3"
    placeholder="Add additional notes..."
></textarea>

</div>

</div>


<div class="modal-footer border-top">

<button
    type="button"
    class="btn btn-light fw-semibold"
    data-bs-dismiss="modal"
>

Cancel

</button>

<button
    type="submit"
    class="btn btn-primary fw-bold"
>

<i class="bi bi-check-lg me-1"></i>

Save Stock Movement

</button>

</div>

</form>

</div>

</div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


<script>

/* =========================================================
   STOCK SEARCH + FILTER
========================================================= */

document.addEventListener('DOMContentLoaded', function () {

    const search =
        document.getElementById('stockSearch');

    const clearSearch =
        document.getElementById('clearStockSearch');

    const stockFilter =
        document.getElementById('stockStatusFilter');

    const productFilter =
        document.getElementById('productFilter');

    const clearFilters =
        document.getElementById('clearStockFilters');

    const rows =
        document.querySelectorAll('.stock-product-row');

    const resultText =
        document.getElementById('stockResultText');

    const noResults =
        document.getElementById('noStockResults');


    function applyStockFilters() {

        const searchTerm =
            (search?.value || '')
                .toLowerCase()
                .trim();

        const selectedStock =
            stockFilter?.value || 'all';

        const selectedProduct =
            productFilter?.value || 'all';

        let visibleCount = 0;


        rows.forEach(function (row) {

            const rowSearch =
                row.getAttribute('data-search') || '';

            const rowStock =
                row.getAttribute('data-stock-status') || '';

            const rowProduct =
                row.getAttribute('data-product-id') || '';


            const matchesSearch =
                searchTerm === '' ||
                rowSearch.includes(searchTerm);

            const matchesStock =
                selectedStock === 'all' ||
                rowStock === selectedStock;

            const matchesProduct =
                selectedProduct === 'all' ||
                rowProduct === selectedProduct;


            const matches =
                matchesSearch &&
                matchesStock &&
                matchesProduct;


            if (matches) {

                row.style.display = '';
                visibleCount++;

            } else {

                row.style.display = 'none';

            }

        });


        if (clearSearch) {

            if (searchTerm !== '') {
                clearSearch.classList.remove('d-none');
            } else {
                clearSearch.classList.add('d-none');
            }

        }


        if (resultText) {

            resultText.textContent =
                'Showing ' +
                visibleCount.toLocaleString() +
                ' product' +
                (visibleCount === 1 ? '' : 's');

        }


        if (noResults) {

            if (visibleCount === 0 && rows.length > 0) {
                noResults.classList.remove('d-none');
            } else {
                noResults.classList.add('d-none');
            }

        }

    }


    if (search) {

        search.addEventListener(
            'input',
            applyStockFilters
        );

    }


    if (stockFilter) {

        stockFilter.addEventListener(
            'change',
            applyStockFilters
        );

    }


    if (productFilter) {

        productFilter.addEventListener(
            'change',
            applyStockFilters
        );

    }


    if (clearSearch) {

        clearSearch.addEventListener('click', function () {

            if (search) {
                search.value = '';
            }

            applyStockFilters();

            if (search) {
                search.focus();
            }

        });

    }


    if (clearFilters) {

        clearFilters.addEventListener('click', function () {

            if (search) {
                search.value = '';
            }

            if (stockFilter) {
                stockFilter.value = 'all';
            }

            if (productFilter) {
                productFilter.value = 'all';
            }

            applyStockFilters();

        });

    }


    applyStockFilters();

});


/* =========================================================
   STOCK MODAL
========================================================= */

function openStockModal(
    productId = '',
    productName = '',
    currentStock = 0
) {

    const modalElement =
        document.getElementById('stockModal');

    if (!modalElement) {
        return;
    }


    const productIdInput =
        document.getElementById('stockProductId');

    const productSelect =
        document.getElementById('stockProductSelect');

    const productNameDisplay =
        document.getElementById('currentProductName');

    const currentStockDisplay =
        document.getElementById('currentStockDisplay');


    if (productId) {

        productIdInput.value = productId;

        productSelect.value = productId;

        productNameDisplay.textContent =
            productName || '-';

        currentStockDisplay.textContent =
            Number(currentStock).toLocaleString(
                undefined,
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );

    } else {

        productIdInput.value = '';

        productSelect.value = '';

        productNameDisplay.textContent = '-';

        currentStockDisplay.textContent = '0.00';

    }


    document.getElementById('movementType').value =
        'stock_in';

    document.getElementById('stockQuantity').value =
        '1';


    const modal =
        bootstrap.Modal.getOrCreateInstance(
            modalElement
        );

    updateStockPreview();

    modal.show();

}


function selectStockProduct() {

    const select =
        document.getElementById('stockProductSelect');

    const selectedOption =
        select.options[select.selectedIndex];

    const productId =
        select.value;

    const currentStock =
        parseFloat(
            selectedOption?.dataset.stock || 0
        );

    const productName =
        selectedOption?.textContent.trim() || '-';


    document.getElementById('stockProductId').value =
        productId;

    document.getElementById('currentProductName').textContent =
        productName;

    document.getElementById('currentStockDisplay').textContent =
        formatNumber(currentStock);

    updateStockPreview();

}


function updateStockPreview() {

    const currentStock =
        parseFloat(
            document
                .getElementById('currentStockDisplay')
                .textContent
                .replace(/,/g, '')
        ) || 0;

    const quantity =
        parseFloat(
            document.getElementById('stockQuantity').value
        ) || 0;

    const movementType =
        document.getElementById('movementType').value;


    let newStock = currentStock;


    if (movementType === 'stock_in') {

        newStock =
            currentStock + quantity;

    } else if (movementType === 'stock_out') {

        newStock =
            currentStock - quantity;

    } else if (movementType === 'adjustment') {

        newStock =
            quantity;

    }


    const preview =
        document.getElementById('newStockPreview');


    if (newStock < 0) {

        preview.textContent =
            'Insufficient stock';

        preview.classList.add('text-danger');

    } else {

        preview.textContent =
            formatNumber(newStock);

        preview.classList.remove('text-danger');

    }


    const quantityLabel =
        document.getElementById('quantityLabel');


    if (movementType === 'adjustment') {

        quantityLabel.innerHTML =
            'New Stock Quantity <span class="text-danger">*</span>';

    } else {

        quantityLabel.innerHTML =
            'Quantity <span class="text-danger">*</span>';

    }

}


function formatNumber(number) {

    return Number(number).toLocaleString(
        undefined,
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }
    );

}

</script>

</body>

</html>
