

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

$user_id = (int)($_SESSION['user_id'] ?? 0);
$business_id = (int)($_SESSION['business_id'] ?? 0);
$businessName = $_SESSION['business_name'] ?? 'Business';

$success = '';
$error = '';

if (!$business_id) {
    header('Location: index.php?page=select_business');
    exit;
}


/*
|--------------------------------------------------------------------------
| PROCESS POST REQUESTS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    try {

        /*
        |--------------------------------------------------------------------------
        | CREATE PRODUCT
        |--------------------------------------------------------------------------
        */

        if ($action === 'create_product') {

            $name = trim($_POST['name'] ?? '');
            $sku = trim($_POST['sku'] ?? '');
            $barcode = trim($_POST['barcode'] ?? '');
            $description = trim($_POST['description'] ?? '');

            $category_id = !empty($_POST['category_id'])
                ? (int)$_POST['category_id']
                : null;

            $brand_id = !empty($_POST['brand_id'])
                ? (int)$_POST['brand_id']
                : null;

            $unit_id = !empty($_POST['unit_id'])
                ? (int)$_POST['unit_id']
                : null;

            $supplier_id = !empty($_POST['supplier_id'])
                ? (int)$_POST['supplier_id']
                : null;

            $cost_price = (float)($_POST['cost_price'] ?? 0);
            $selling_price = (float)($_POST['selling_price'] ?? 0);
            $wholesale_price = (float)($_POST['wholesale_price'] ?? 0);

            $minimum_stock = (float)($_POST['minimum_stock'] ?? 0);
            $current_stock = (float)($_POST['current_stock'] ?? 0);

            $maximum_stock = (
                isset($_POST['maximum_stock']) &&
                $_POST['maximum_stock'] !== ''
            )
                ? (float)$_POST['maximum_stock']
                : null;


            /*
            |--------------------------------------------------------------------------
            | VALIDATION
            |--------------------------------------------------------------------------
            */

            if ($name === '') {
                throw new Exception('Product name is required.');
            }

            if ($cost_price < 0) {
                throw new Exception('Cost price cannot be negative.');
            }

            if ($selling_price < 0) {
                throw new Exception('Selling price cannot be negative.');
            }

            if ($wholesale_price < 0) {
                throw new Exception('Wholesale price cannot be negative.');
            }

            if ($minimum_stock < 0) {
                throw new Exception('Minimum stock cannot be negative.');
            }

            if ($current_stock < 0) {
                throw new Exception('Opening stock cannot be negative.');
            }

            if ($maximum_stock !== null && $maximum_stock < 0) {
                throw new Exception('Maximum stock cannot be negative.');
            }

            if (
                $maximum_stock !== null &&
                $maximum_stock > 0 &&
                $current_stock > $maximum_stock
            ) {
                throw new Exception(
                    'Opening stock cannot be greater than maximum stock.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CHECK DUPLICATE SKU
            |--------------------------------------------------------------------------
            */

            if ($sku !== '') {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM inventory_products
                    WHERE business_id = ?
                    AND created_by = ?
                    AND sku = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $business_id,
                    $user_id,
                    $sku
                ]);

                if ($stmt->fetch()) {
                    throw new Exception(
                        'The SKU already exists for this business.'
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | CHECK DUPLICATE BARCODE
            |--------------------------------------------------------------------------
            */

            if ($barcode !== '') {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM inventory_products
                    WHERE business_id = ?
                    AND created_by = ?
                    AND barcode = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $business_id,
                    $user_id,
                    $barcode
                ]);

                if ($stmt->fetch()) {
                    throw new Exception(
                        'The barcode already exists for this business.'
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | INSERT PRODUCT
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO inventory_products (
                    business_id,
                    name,
                    sku,
                    barcode,
                    description,
                    category_id,
                    brand_id,
                    unit_id,
                    supplier_id,
                    cost_price,
                    selling_price,
                    wholesale_price,
                    current_stock,
                    minimum_stock,
                    maximum_stock,
                    status,
                    created_by
                ) VALUES (
                    :business_id,
                    :name,
                    :sku,
                    :barcode,
                    :description,
                    :category_id,
                    :brand_id,
                    :unit_id,
                    :supplier_id,
                    :cost_price,
                    :selling_price,
                    :wholesale_price,
                    :current_stock,
                    :minimum_stock,
                    :maximum_stock,
                    'active',
                    :created_by
                )
            ");

            $stmt->execute([
                ':business_id' => $business_id,
                ':name' => $name,
                ':sku' => $sku !== '' ? $sku : null,
                ':barcode' => $barcode !== '' ? $barcode : null,
                ':description' => $description !== '' ? $description : null,
                ':category_id' => $category_id,
                ':brand_id' => $brand_id,
                ':unit_id' => $unit_id,
                ':supplier_id' => $supplier_id,
                ':cost_price' => $cost_price,
                ':selling_price' => $selling_price,
                ':wholesale_price' => $wholesale_price,
                ':current_stock' => $current_stock,
                ':minimum_stock' => $minimum_stock,
                ':maximum_stock' => $maximum_stock,
                ':created_by' => $user_id
            ]);

            $success = 'Product "' . $name . '" was created successfully.';
        }

        /*
        |--------------------------------------------------------------------------
        | DELETE PRODUCT
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'delete_product') {

            $product_id = (int)($_POST['product_id'] ?? 0);

            if ($product_id <= 0) {
                throw new Exception('Invalid product ID for deletion.');
            }

            $stmt = $pdo->prepare("
                DELETE FROM inventory_products
                WHERE id = ?
                AND business_id = ?
                AND created_by = ?
            ");

            $stmt->execute([
                $product_id,
                $business_id,
                $user_id
            ]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Product could not be deleted or was not found.');
            }

            $success = 'Product deleted successfully.';
        }
    }

    catch (Throwable $e) {

        $error = $e->getMessage();

        error_log(
            'Inventory Product Error: ' .
            $e->getMessage()
        );
    }
}


/*
|--------------------------------------------------------------------------
| FETCH CATEGORIES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, name
    FROM inventory_categories
    WHERE business_id = ?
    AND created_by = ?
    ORDER BY name ASC
");

$stmt->execute([
    $business_id,
    $user_id
]);

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| FETCH BRANDS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, name
    FROM inventory_brands
    WHERE business_id = ?
    AND created_by = ?
    ORDER BY name ASC
");

$stmt->execute([
    $business_id,
    $user_id
]);

$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| FETCH UNITS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, name, abbreviation
    FROM inventory_units
    WHERE business_id = ?
    AND created_by = ?
    ORDER BY name ASC
");

$stmt->execute([
    $business_id,
    $user_id
]);

$units = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| FETCH SUPPLIERS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, name
    FROM inventory_suppliers
    WHERE business_id = ?
    AND created_by = ?
    ORDER BY name ASC
");

$stmt->execute([
    $business_id,
    $user_id
]);

$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| FETCH PRODUCTS
|--------------------------------------------------------------------------
*/

$productStmt = $pdo->prepare("
    SELECT
        p.*,
        c.name AS category_name,
        b.name AS brand_name,
        u.name AS unit_name,
        u.abbreviation AS unit_abbreviation,
        s.name AS supplier_name
    FROM inventory_products p

    LEFT JOIN inventory_categories c
        ON p.category_id = c.id
        AND c.business_id = p.business_id
        AND c.created_by = p.created_by

    LEFT JOIN inventory_brands b
        ON p.brand_id = b.id
        AND b.business_id = p.business_id
        AND b.created_by = p.created_by

    LEFT JOIN inventory_units u
        ON p.unit_id = u.id
        AND u.business_id = p.business_id
        AND u.created_by = p.created_by

    LEFT JOIN inventory_suppliers s
        ON p.supplier_id = s.id
        AND s.business_id = p.business_id
        AND s.created_by = p.created_by

    WHERE p.business_id = ?
    AND p.created_by = ?

    ORDER BY p.id DESC
");

$productStmt->execute([
    $business_id,
    $user_id
]);

$products = $productStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| CALCULATE DASHBOARD METRICS
|--------------------------------------------------------------------------
*/

$totalProducts = count($products);

$totalStock = 0; // <-- Added initialization to prevent the undefined variable warning
$lowStockProducts = 0;
$outOfStockProducts = 0;

foreach ($products as $p) {

    $stock = (float)($p['current_stock'] ?? 0);
    $minStock = (float)($p['minimum_stock'] ?? 0);

    $totalStock += $stock;

    if ($stock <= 0) {
        $outOfStockProducts++;
    } elseif ($stock <= $minStock) {
        $lowStockProducts++;
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

.inventory-header {
    padding-bottom: 4px;
}

.inventory-header h2 {
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
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 13px;
}

.product-section {
    border: 0;
    border-radius: 16px;
    overflow: hidden;
}

.product-section-header {
    padding: 20px 24px;
}

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

.product-table {
    width: 100%;
}

.product-table th {
    font-size: .72rem;
    letter-spacing: .04em;
    white-space: nowrap;
}

.product-table td {
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

.product-price {
    font-weight: 600;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: .35rem .65rem;
    border-radius: 50rem;
    font-size: .7rem;
    font-weight: 700;
}

.status-active {
    background: rgba(25, 135, 84, .12);
    color: var(--bs-success);
}

.status-inactive {
    background: rgba(220, 53, 69, .12);
    color: var(--bs-danger);
}

.stock-low {
    color: var(--bs-warning);
    font-weight: 700;
}

.stock-out {
    color: var(--bs-danger);
    font-weight: 700;
}

.stock-ok {
    color: var(--bs-success);
    font-weight: 700;
}

.empty-state {
    padding: 55px 20px;
}

.product-modal .modal-content {
    border: 0;
    border-radius: 16px;
}

.product-modal .modal-header {
    padding: 18px 22px;
}

.product-modal .modal-body {
    padding: 22px;
}

.product-modal .modal-footer {
    padding: 15px 22px;
}

.form-label {
    font-size: .78rem;
}

.form-control,
.form-select {
    border-radius: 9px;
}

.form-control:focus,
.form-select:focus {
    box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .12);
}

.detail-label {
    font-size: .7rem;
    color: var(--bs-secondary-color);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .03em;
    margin-bottom: 3px;
}

.detail-value {
    font-weight: 600;
    word-break: break-word;
}

.action-menu .dropdown-item {
    padding: 9px 14px;
    font-size: .85rem;
}

.action-menu .dropdown-item i {
    width: 20px;
}

.stock-preview {
    border-radius: 12px;
    padding: 15px;
}

.stock-preview-current {
    font-size: 1.5rem;
    font-weight: 800;
}

.stock-preview-new {
    font-size: 1.5rem;
    font-weight: 800;
}

.product-image-preview {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 12px;
    border: 1px solid var(--bs-border-color);
}

@media (max-width: 575.98px) {

    .inventory-main > .p-3,
    .inventory-main > .p-md-4 {
        padding: 14px !important;
    }

    .inventory-header {
        margin-bottom: 16px !important;
    }

    .inventory-header h2 {
        font-size: 1.35rem;
    }

    .inventory-header p {
        font-size: .75rem;
        line-height: 1.4;
    }

    .product-section {
        border-radius: 14px;
    }

    .product-section-header {
        padding: 15px;
    }

    .product-table th,
    .product-table td {
        font-size: .75rem;
    }

    .empty-state {
        padding: 40px 15px;
    }

    .product-modal .modal-dialog {
        margin: 10px;
    }

    .product-modal .modal-content {
        border-radius: 15px;
    }

    .product-modal .modal-header {
        padding: 15px 16px;
    }

    .product-modal .modal-body {
        padding: 16px;
    }

    .product-modal .modal-footer {
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

<i class="bi bi-box-seam"></i>

</div>

<h2 class="fw-bold text-body mb-0">
Products
</h2>

</div>

<p class="text-muted small mb-0">

Manage products and inventory for

<span class="fw-semibold text-primary">
<?= htmlspecialchars($businessName) ?>
</span>

</p>

</div>

<button
    type="button"
    class="btn btn-primary fw-bold px-3 py-2 rounded-3 shadow-sm d-flex align-items-center gap-2"
    onclick="openProductModal()"
>

<i class="bi bi-plus-lg"></i>

<span>
Add Product
</span>

</button>

</div>

<!-- =====================================================
     ALERTS
====================================================== -->

<?php if (!empty($success)): ?>

<div
    class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 small"
    role="alert"
>

<i class="bi bi-check-circle-fill me-2"></i>

<?= htmlspecialchars($success) ?>

<button
    type="button"
    class="btn-close"
    data-bs-dismiss="alert"
></button>

</div>

<?php endif; ?>

<?php if (!empty($error)): ?>

<div
    class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 small"
    role="alert"
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
     SUMMARY CARDS
====================================================== -->

<div class="row g-3 mb-4">

<div class="col-12 col-md-4">

<div class="card summary-card shadow-sm h-100 bg-body">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start">

<div>

<div class="text-muted small fw-semibold mb-2">
Total Products
</div>

<div class="fs-3 fw-bold text-body">
<?= number_format($totalProducts) ?>
</div>

<div class="small text-muted mt-1">
All registered products
</div>

</div>

<div class="summary-icon bg-primary bg-opacity-10 text-primary">

<i class="bi bi-box-seam fs-4"></i>

</div>

</div>

</div>

</div>

</div>


<div class="col-12 col-md-4">

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
Products needing attention
</div>

</div>

<div class="summary-icon bg-warning bg-opacity-10 text-warning">

<i class="bi bi-exclamation-triangle fs-4"></i>

</div>

</div>

</div>

</div>

</div>


<div class="col-12 col-md-4">

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
Products with no stock
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
     SEARCH AND FILTER
====================================================== -->

<div class="card border-0 shadow-sm rounded-4 mb-4">

<div class="card-body p-3">

<div class="row g-2">

<div class="col-12 col-lg-6">

<div class="search-wrapper input-group">

<span class="input-group-text text-muted">

<i class="bi bi-search"></i>

</span>

<input
    type="text"
    id="productSearch"
    class="form-control"
    placeholder="Search product, SKU, barcode, category, brand..."
    autocomplete="off"
>

<button
    type="button"
    id="clearSearch"
    class="btn d-none"
>

<i class="bi bi-x-lg"></i>

</button>

</div>

</div>


<div class="col-12 col-sm-6 col-lg-2">

<select
    id="stockFilter"
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


<div class="col-12 col-sm-6 col-lg-2">

<select
    id="statusFilter"
    class="form-select filter-select"
>

<option value="all">
All Status
</option>

<option value="active">
Active
</option>

<option value="inactive">
Inactive
</option>

</select>

</div>


<div class="col-12 col-lg-2">

<select
    id="categoryFilter"
    class="form-select filter-select"
>

<option value="all">
All Categories
</option>

<?php foreach ($categories as $category): ?>

<option value="<?= (int)$category['id'] ?>">

<?= htmlspecialchars($category['name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>

</div>

<div class="d-flex justify-content-between align-items-center mt-3">

<div
    id="filterResultText"
    class="small text-muted"
>
Showing <?= number_format($totalProducts) ?> products
</div>

<button
    type="button"
    id="clearFilters"
    class="btn btn-sm btn-outline-secondary rounded-3"
>

<i class="bi bi-arrow-counterclockwise me-1"></i>

Clear Filters

</button>

</div>

</div>

</div>

<!-- =====================================================
     PRODUCT TABLE
====================================================== -->

<div class="card product-section shadow-sm bg-body">

<div class="product-section-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">

<div>

<h5 class="fw-bold mb-1">
Product List
</h5>

<p class="text-muted small mb-0">

<?= count($products) ?>

product<?= count($products) === 1 ? '' : 's' ?>

registered

</p>

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

<div class="small mb-3">
Add your first product to start managing inventory.
</div>

<button
    type="button"
    class="btn btn-primary btn-sm fw-bold"
    onclick="openProductModal()"
>

<i class="bi bi-plus-lg me-1"></i>

Add Product

</button>

</div>

<?php else: ?>

<div class="table-responsive">

<table class="product-table table table-hover align-middle mb-0">

<thead class="table-light text-uppercase text-muted">

<tr>

<th class="ps-4 py-3">
ID
</th>

<th class="py-3">
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
Unit
</th>

<th class="py-3">
Supplier
</th>

<th class="py-3">
Cost
</th>

<th class="py-3">
Selling
</th>

<th class="py-3">
Wholesale
</th>

<th class="py-3">
Stock
</th>

<th class="py-3">
Status
</th>

<th class="py-3 pe-4 text-end">
Action
</th>

</tr>

</thead>

<tbody id="productTableBody">

<?php foreach ($products as $product): ?>

<?php

$currentStock = (float)($product['current_stock'] ?? 0);
$minimumStock = (float)($product['minimum_stock'] ?? 0);

if ($currentStock <= 0) {

    $stockFilterValue = 'out_of_stock';
    $stockClass = 'stock-out';
    $stockLabel = 'Out of stock';

} elseif ($currentStock <= $minimumStock) {

    $stockFilterValue = 'low_stock';
    $stockClass = 'stock-low';
    $stockLabel = 'Low stock';

} else {

    $stockFilterValue = 'in_stock';
    $stockClass = 'stock-ok';
    $stockLabel = '';

}

$searchText = strtolower(
    ($product['name'] ?? '') . ' ' .
    ($product['sku'] ?? '') . ' ' .
    ($product['barcode'] ?? '') . ' ' .
    ($product['category_name'] ?? '') . ' ' .
    ($product['brand_name'] ?? '') . ' ' .
    ($product['unit_name'] ?? '') . ' ' .
    ($product['supplier_name'] ?? '')
);

$productJson = htmlspecialchars(
    json_encode(
        $product,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_HEX_AMP
    ),
    ENT_QUOTES,
    'UTF-8'
);

?>

<tr
    class="product-row"
    data-search="<?= htmlspecialchars($searchText) ?>"
    data-stock="<?= htmlspecialchars($stockFilterValue) ?>"
    data-status="<?= htmlspecialchars($product['status']) ?>"
    data-category="<?= (int)$product['category_id'] ?>"
>

<td class="ps-4">

<span class="text-muted small">
#<?= (int)$product['id'] ?>
</span>

</td>


<td>

<div class="product-name text-body">

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

<?= htmlspecialchars(
    $product['sku'] ?? '-'
) ?>

</span>

</td>


<td>

<span class="small">

<?= htmlspecialchars(
    $product['category_name'] ?? '-'
) ?>

</span>

</td>


<td>

<span class="small">

<?= htmlspecialchars(
    $product['brand_name'] ?? '-'
) ?>

</span>

</td>


<td>

<?php if (!empty($product['unit_name'])): ?>

<span class="small">

<?= htmlspecialchars(
    $product['unit_name']
) ?>

<?php if (!empty($product['unit_abbreviation'])): ?>

<span class="text-muted">

(
<?= htmlspecialchars(
    $product['unit_abbreviation']
) ?>
)

</span>

<?php endif; ?>

</span>

<?php else: ?>

<span class="text-muted small">
-
</span>

<?php endif; ?>

</td>


<td>

<span class="small">

<?= htmlspecialchars(
    $product['supplier_name'] ?? '-'
) ?>

</span>

</td>


<td>

<span class="product-price small">

₱<?= number_format(
    (float)$product['cost_price'],
    2
) ?>

</span>

</td>


<td>

<span class="product-price small">

₱<?= number_format(
    (float)$product['selling_price'],
    2
) ?>

</span>

</td>


<td>

<span class="product-price small">

₱<?= number_format(
    (float)$product['wholesale_price'],
    2
) ?>

</span>

</td>


<td>

<span class="<?= $stockClass ?>">

<?= number_format(
    $currentStock,
    2
) ?>

</span>

<?php if ($stockLabel !== ''): ?>

<div
    class="<?= $stockFilterValue === 'out_of_stock'
        ? 'text-danger'
        : 'text-warning'
    ?>"
    style="font-size:.68rem;"
>

<i class="bi bi-exclamation-triangle me-1"></i>

<?= htmlspecialchars($stockLabel) ?>

</div>

<?php endif; ?>

</td>


<td>

<?php if ($product['status'] === 'active'): ?>

<span class="status-badge status-active">

<i class="bi bi-check-circle me-1"></i>

Active

</span>

<?php else: ?>

<span class="status-badge status-inactive">

<i class="bi bi-x-circle me-1"></i>

Inactive

</span>

<?php endif; ?>

</td>


<!-- ACTION -->

<td class="text-end pe-4">

<div class="dropdown action-menu">

<button
    type="button"
    class="btn btn-sm btn-primary rounded-3 dropdown-toggle"
    data-bs-toggle="dropdown"
    aria-expanded="false"
>

<i class="bi bi-three-dots-vertical me-1"></i>

Action

</button>

<ul class="dropdown-menu dropdown-menu-end shadow-sm">

<li>

<button
    type="button"
    class="dropdown-item"
    onclick='viewProduct(<?= $productJson ?>)'
>

<i class="bi bi-eye text-primary"></i>

View Details

</button>

</li>

<li>

<button
    type="button"
    class="dropdown-item"
    onclick='editProduct(<?= $productJson ?>)'
>

<i class="bi bi-pencil-square text-primary"></i>

Edit Product

</button>

</li>

<li>

<button
    type="button"
    class="dropdown-item"
    onclick='openStockModal(<?= $productJson ?>)'
>

<i class="bi bi-box-arrow-in-down text-success"></i>

Adjust Stock

</button>

</li>

<li>
<hr class="dropdown-divider">
</li>

<li>

<form method="POST">

<input
    type="hidden"
    name="action"
    value="toggle_product_status"
>

<input
    type="hidden"
    name="product_id"
    value="<?= (int)$product['id'] ?>"
>

<button
    type="submit"
    class="dropdown-item"
>

<?php if ($product['status'] === 'active'): ?>

<i class="bi bi-pause-circle text-warning"></i>

Disable Product

<?php else: ?>

<i class="bi bi-play-circle text-success"></i>

Enable Product

<?php endif; ?>

</button>

</form>

</li>

<li>

<form
    method="POST"
    onsubmit="return confirm('Duplicate this product? The duplicate will have zero stock.');"
>

<input
    type="hidden"
    name="action"
    value="duplicate_product"
>

<input
    type="hidden"
    name="product_id"
    value="<?= (int)$product['id'] ?>"
>

<button
    type="submit"
    class="dropdown-item"
>

<i class="bi bi-copy text-info"></i>

Duplicate Product

</button>

</form>

</li>

<li>
<hr class="dropdown-divider">
</li>

<li>

<form
    method="POST"
    onsubmit="return confirm('Are you sure you want to permanently delete this product? This will also delete its stock movement history.');"
>

<input
    type="hidden"
    name="action"
    value="delete_product"
>

<input
    type="hidden"
    name="product_id"
    value="<?= (int)$product['id'] ?>"
>

<button
    type="submit"
    class="dropdown-item text-danger"
>

<i class="bi bi-trash text-danger"></i>

Delete Product

</button>

</form>

</li>

</ul>

</div>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>


<div
    id="noSearchResults"
    class="empty-state text-center text-muted d-none"
>

<div class="mb-3">

<i class="bi bi-search display-6 opacity-50"></i>

</div>

<div class="fw-semibold mb-1">
No products found
</div>

<div class="small">
Try changing your search or filter options.
</div>

</div>

<?php endif; ?>

</div>

</div>

</main>

</div>


<!-- =========================================================
     ADD PRODUCT MODAL
========================================================= -->

<div
    class="modal fade product-modal"
    id="productModal"
    tabindex="-1"
>

<div class="modal-dialog modal-dialog-centered modal-lg">

<div class="modal-content shadow-lg">

<div class="modal-header">

<h5 class="modal-title fw-bold">

<i class="bi bi-box-seam text-primary me-2"></i>

Add New Product

</h5>

<button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal"
></button>

</div>

<form method="POST">

<input
    type="hidden"
    name="action"
    value="create_product"
>

<div class="modal-body">

<div class="row g-3">

<div class="col-12">

<label class="form-label fw-semibold">

Product Name

<span class="text-danger">*</span>

</label>

<input
    type="text"
    name="name"
    class="form-control"
    required
    placeholder="Enter product name"
>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
SKU
</label>

<input
    type="text"
    name="sku"
    class="form-control"
    placeholder="Product SKU"
>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
Barcode
</label>

<input
    type="text"
    name="barcode"
    class="form-control"
    placeholder="Barcode"
>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
Category
</label>

<select
    name="category_id"
    class="form-select"
>

<option value="">
Select Category
</option>

<?php foreach ($categories as $category): ?>

<option value="<?= (int)$category['id'] ?>">

<?= htmlspecialchars($category['name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
Brand
</label>

<select
    name="brand_id"
    class="form-select"
>

<option value="">
Select Brand
</option>

<?php foreach ($brands as $brand): ?>

<option value="<?= (int)$brand['id'] ?>">

<?= htmlspecialchars($brand['name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
Unit
</label>

<select
    name="unit_id"
    class="form-select"
>

<option value="">
Select Unit
</option>

<?php foreach ($units as $unit): ?>

<option value="<?= (int)$unit['id'] ?>">

<?= htmlspecialchars($unit['name']) ?>

<?php if (!empty($unit['abbreviation'])): ?>

(<?= htmlspecialchars($unit['abbreviation']) ?>)

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
Supplier
</label>

<select
    name="supplier_id"
    class="form-select"
>

<option value="">
Select Supplier
</option>

<?php foreach ($suppliers as $supplier): ?>

<option value="<?= (int)$supplier['id'] ?>">

<?= htmlspecialchars($supplier['name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
Cost Price
</label>

<div class="input-group">

<span class="input-group-text">
₱
</span>

<input
    type="number"
    name="cost_price"
    class="form-control"
    min="0"
    step="0.01"
    value="0"
>

</div>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
Selling Price
</label>

<div class="input-group">

<span class="input-group-text">
₱
</span>

<input
    type="number"
    name="selling_price"
    class="form-control"
    min="0"
    step="0.01"
    value="0"
>

</div>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
Wholesale Price
</label>

<div class="input-group">

<span class="input-group-text">
₱
</span>

<input
    type="number"
    name="wholesale_price"
    class="form-control"
    min="0"
    step="0.01"
    value="0"
>

</div>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
Minimum Stock
</label>

<input
    type="number"
    name="minimum_stock"
    class="form-control"
    min="0"
    step="0.01"
    value="0"
>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
Maximum Stock
</label>

<input
    type="number"
    name="maximum_stock"
    class="form-control"
    min="0"
    step="0.01"
    placeholder="Optional"
>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
Opening Stock
</label>

<input
    type="number"
    name="current_stock"
    class="form-control"
    min="0"
    step="0.01"
    value="0"
>

<div class="form-text">
Initial quantity available.
</div>

</div>


<div class="col-12">

<label class="form-label fw-semibold">
Description
</label>

<textarea
    name="description"
    class="form-control"
    rows="3"
    placeholder="Product description"
></textarea>

</div>

</div>

</div>

<div class="modal-footer">

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

Save Product

</button>

</div>

</form>

</div>

</div>

</div>


<!-- =========================================================
     VIEW PRODUCT DETAILS MODAL
========================================================= -->

<div
    class="modal fade product-modal"
    id="detailsModal"
    tabindex="-1"
>

<div class="modal-dialog modal-dialog-centered modal-lg">

<div class="modal-content shadow-lg">

<div class="modal-header">

<h5 class="modal-title fw-bold">

<i class="bi bi-info-circle text-primary me-2"></i>

Product Details

</h5>

<button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal"
></button>

</div>

<div class="modal-body">

<div class="row g-4">

<div class="col-12">

<div class="d-flex align-items-center gap-3">

<div
    id="detailsIcon"
    class="bg-primary bg-opacity-10 text-primary rounded-4 d-flex align-items-center justify-content-center"
    style="width:70px;height:70px;"
>

<i class="bi bi-box-seam fs-2"></i>

</div>

<div>

<h4
    id="detailsName"
    class="fw-bold mb-1"
>
-
</h4>

<div
    id="detailsStatus"
>
</div>

</div>

</div>

</div>


<div class="col-md-6">

<div class="detail-label">
SKU
</div>

<div
    id="detailsSku"
    class="detail-value"
>
-
</div>

</div>


<div class="col-md-6">

<div class="detail-label">
Barcode
</div>

<div
    id="detailsBarcode"
    class="detail-value"
>
-
</div>

</div>


<div class="col-md-6">

<div class="detail-label">
Category
</div>

<div
    id="detailsCategory"
    class="detail-value"
>
-
</div>

</div>


<div class="col-md-6">

<div class="detail-label">
Brand
</div>

<div
    id="detailsBrand"
    class="detail-value"
>
-
</div>

</div>


<div class="col-md-6">

<div class="detail-label">
Unit
</div>

<div
    id="detailsUnit"
    class="detail-value"
>
-
</div>

</div>


<div class="col-md-6">

<div class="detail-label">
Supplier
</div>

<div
    id="detailsSupplier"
    class="detail-value"
>
-
</div>

</div>


<div class="col-md-4">

<div class="detail-label">
Cost Price
</div>

<div
    id="detailsCost"
    class="detail-value"
>
-
</div>

</div>


<div class="col-md-4">

<div class="detail-label">
Selling Price
</div>

<div
    id="detailsSelling"
    class="detail-value"
>
-
</div>

</div>


<div class="col-md-4">

<div class="detail-label">
Wholesale Price
</div>

<div
    id="detailsWholesale"
    class="detail-value"
>
-
</div>

</div>


<div class="col-md-4">

<div class="detail-label">
Current Stock
</div>

<div
    id="detailsStock"
    class="detail-value fs-5"
>
-
</div>

</div>


<div class="col-md-4">

<div class="detail-label">
Minimum Stock
</div>

<div
    id="detailsMinimum"
    class="detail-value"
>
-
</div>

</div>


<div class="col-md-4">

<div class="detail-label">
Maximum Stock
</div>

<div
    id="detailsMaximum"
    class="detail-value"
>
-
</div>

</div>


<div class="col-12">

<div class="detail-label">
Description
</div>

<div
    id="detailsDescription"
    class="detail-value"
>
-
</div>

</div>

</div>

</div>

<div class="modal-footer">

<button
    type="button"
    class="btn btn-light"
    data-bs-dismiss="modal"
>
Close
</button>

</div>

</div>

</div>

</div>


<!-- =========================================================
     EDIT PRODUCT MODAL
========================================================= -->

<div
    class="modal fade product-modal"
    id="editProductModal"
    tabindex="-1"
>

<div class="modal-dialog modal-dialog-centered modal-lg">

<div class="modal-content shadow-lg">

<div class="modal-header">

<h5 class="modal-title fw-bold">

<i class="bi bi-pencil-square text-primary me-2"></i>

Edit Product

</h5>

<button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal"
></button>

</div>

<form method="POST">

<input
    type="hidden"
    name="action"
    value="edit_product"
>

<input
    type="hidden"
    name="product_id"
    id="editProductId"
>

<div class="modal-body">

<div class="row g-3">

<div class="col-12">

<label class="form-label fw-semibold">

Product Name

<span class="text-danger">*</span>

</label>

<input
    type="text"
    name="name"
    id="editName"
    class="form-control"
    required
>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
SKU
</label>

<input
    type="text"
    name="sku"
    id="editSku"
    class="form-control"
>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
Barcode
</label>

<input
    type="text"
    name="barcode"
    id="editBarcode"
    class="form-control"
>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
Category
</label>

<select
    name="category_id"
    id="editCategory"
    class="form-select"
>

<option value="">
Select Category
</option>

<?php foreach ($categories as $category): ?>

<option value="<?= (int)$category['id'] ?>">

<?= htmlspecialchars($category['name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
Brand
</label>

<select
    name="brand_id"
    id="editBrand"
    class="form-select"
>

<option value="">
Select Brand
</option>

<?php foreach ($brands as $brand): ?>

<option value="<?= (int)$brand['id'] ?>">

<?= htmlspecialchars($brand['name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
Unit
</label>

<select
    name="unit_id"
    id="editUnit"
    class="form-select"
>

<option value="">
Select Unit
</option>

<?php foreach ($units as $unit): ?>

<option value="<?= (int)$unit['id'] ?>">

<?= htmlspecialchars($unit['name']) ?>

<?php if (!empty($unit['abbreviation'])): ?>

(<?= htmlspecialchars($unit['abbreviation']) ?>)

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
Supplier
</label>

<select
    name="supplier_id"
    id="editSupplier"
    class="form-select"
>

<option value="">
Select Supplier
</option>

<?php foreach ($suppliers as $supplier): ?>

<option value="<?= (int)$supplier['id'] ?>">

<?= htmlspecialchars($supplier['name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
Cost Price
</label>

<div class="input-group">

<span class="input-group-text">
₱
</span>

<input
    type="number"
    name="cost_price"
    id="editCostPrice"
    class="form-control"
    min="0"
    step="0.01"
>

</div>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
Selling Price
</label>

<div class="input-group">

<span class="input-group-text">
₱
</span>

<input
    type="number"
    name="selling_price"
    id="editSellingPrice"
    class="form-control"
    min="0"
    step="0.01"
>

</div>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
Wholesale Price
</label>

<div class="input-group">

<span class="input-group-text">
₱
</span>

<input
    type="number"
    name="wholesale_price"
    id="editWholesalePrice"
    class="form-control"
    min="0"
    step="0.01"
>

</div>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
Minimum Stock
</label>

<input
    type="number"
    name="minimum_stock"
    id="editMinimumStock"
    class="form-control"
    min="0"
    step="0.01"
>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
Maximum Stock
</label>

<input
    type="number"
    name="maximum_stock"
    id="editMaximumStock"
    class="form-control"
    min="0"
    step="0.01"
>

</div>


<div class="col-md-6">

<label class="form-label fw-semibold">
Current Stock
</label>

<input
    type="text"
    id="editCurrentStock"
    class="form-control bg-body-secondary"
    readonly
>

<div class="form-text">
Use Adjust Stock to change inventory quantity.
</div>

</div>


<div class="col-12">

<label class="form-label fw-semibold">
Description
</label>

<textarea
    name="description"
    id="editDescription"
    class="form-control"
    rows="3"
></textarea>

</div>

</div>

</div>

<div class="modal-footer">

<button
    type="button"
    class="btn btn-light"
    data-bs-dismiss="modal"
>
Cancel
</button>

<button
    type="submit"
    class="btn btn-primary fw-bold"
>

<i class="bi bi-check-lg me-1"></i>

Save Changes

</button>

</div>

</form>

</div>

</div>

</div>

<!-- Inside your modal -->
<div class="modal fade" id="stockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="">
            <!-- ADD THIS HIDDEN INPUT IF IT'S MISSING -->
            <input type="hidden" name="action" value="adjust_stock">
            
            <!-- Hidden input for Product ID -->
            <input type="hidden" name="product_id" id="stockProductId">

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Adjust Stock: <span id="stockProductName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Current Stock: <span id="stockCurrent">0.00</span></p>
                    
                    <div class="mb-3">
                        <label for="stockQuantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="stockQuantity" name="quantity" step="any" min="0" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Movement Type</label>
                        <div>
                            <input type="radio" id="stockIn" name="movement_type" value="stock_in" checked>
                            <label for="stockIn">Stock In</label>
                            
                            <input type="radio" id="stockOut" name="movement_type" value="stock_out">
                            <label for="stockOut">Stock Out</label>
                        </div>
                    </div>

                    <p>New Stock Preview: <span id="stockNew">0.00</span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>

/* =========================================================
   SEARCH + FILTER
========================================================= */

document.addEventListener('DOMContentLoaded', function () {

    const searchInput =
        document.getElementById('productSearch');

    const clearButton =
        document.getElementById('clearSearch');

    const stockFilter =
        document.getElementById('stockFilter');

    const statusFilter =
        document.getElementById('statusFilter');

    const categoryFilter =
        document.getElementById('categoryFilter');

    const clearFilters =
        document.getElementById('clearFilters');

    const rows =
        document.querySelectorAll('.product-row');

    const noResults =
        document.getElementById('noSearchResults');

    const resultText =
        document.getElementById('filterResultText');


    function applyFilters() {

        const searchTerm =
            (searchInput?.value || '')
                .toLowerCase()
                .trim();

        const selectedStock =
            stockFilter?.value || 'all';

        const selectedStatus =
            statusFilter?.value || 'all';

        const selectedCategory =
            categoryFilter?.value || 'all';

        let visibleCount = 0;

        rows.forEach(function (row) {

            const searchData =
                row.getAttribute('data-search') ||
                row.textContent.toLowerCase();

            const rowStock =
                row.getAttribute('data-stock') || '';

            const rowStatus =
                row.getAttribute('data-status') || '';

            const rowCategory =
                row.getAttribute('data-category') || '';

            const matchesSearch =
                searchTerm === '' ||
                searchData.includes(searchTerm);

            const matchesStock =
                selectedStock === 'all' ||
                rowStock === selectedStock;

            const matchesStatus =
                selectedStatus === 'all' ||
                rowStatus === selectedStatus;

            const matchesCategory =
                selectedCategory === 'all' ||
                rowCategory === selectedCategory;

            const matches =
                matchesSearch &&
                matchesStock &&
                matchesStatus &&
                matchesCategory;

            if (matches) {

                row.style.display = '';
                visibleCount++;

            } else {

                row.style.display = 'none';

            }

        });

        if (clearButton) {

            if (searchTerm !== '') {
                clearButton.classList.remove('d-none');
            } else {
                clearButton.classList.add('d-none');
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


    if (searchInput) {
        searchInput.addEventListener(
            'input',
            applyFilters
        );
    }

    if (stockFilter) {
        stockFilter.addEventListener(
            'change',
            applyFilters
        );
    }

    if (statusFilter) {
        statusFilter.addEventListener(
            'change',
            applyFilters
        );
    }

    if (categoryFilter) {
        categoryFilter.addEventListener(
            'change',
            applyFilters
        );
    }

    if (clearButton) {

        clearButton.addEventListener(
            'click',
            function () {

                if (searchInput) {
                    searchInput.value = '';
                }

                applyFilters();

                if (searchInput) {
                    searchInput.focus();
                }

            }
        );

    }

    if (clearFilters) {

        clearFilters.addEventListener(
            'click',
            function () {

                if (searchInput) {
                    searchInput.value = '';
                }

                if (stockFilter) {
                    stockFilter.value = 'all';
                }

                if (statusFilter) {
                    statusFilter.value = 'all';
                }

                if (categoryFilter) {
                    categoryFilter.value = 'all';
                }

                applyFilters();

            }
        );

    }

    applyFilters();

});


/* =========================================================
   GENERIC MODAL
========================================================= */

function showModal(id) {

    const element =
        document.getElementById(id);

    if (!element) {
        return;
    }

    bootstrap.Modal
        .getOrCreateInstance(element)
        .show();
}


/* =========================================================
   ADD PRODUCT
========================================================= */

function openProductModal() {

    showModal('productModal');

}


/* =========================================================
   VIEW PRODUCT
========================================================= */

function viewProduct(product) {

    document.getElementById('detailsName').textContent =
        product.name || '-';

    document.getElementById('detailsSku').textContent =
        product.sku || '-';

    document.getElementById('detailsBarcode').textContent =
        product.barcode || '-';

    document.getElementById('detailsCategory').textContent =
        product.category_name || '-';

    document.getElementById('detailsBrand').textContent =
        product.brand_name || '-';

    let unit = product.unit_name || '-';

    if (product.unit_abbreviation) {
        unit += ' (' + product.unit_abbreviation + ')';
    }

    document.getElementById('detailsUnit').textContent =
        unit;

    document.getElementById('detailsSupplier').textContent =
        product.supplier_name || '-';

    document.getElementById('detailsCost').textContent =
        formatCurrency(product.cost_price);

    document.getElementById('detailsSelling').textContent =
        formatCurrency(product.selling_price);

    document.getElementById('detailsWholesale').textContent =
        formatCurrency(product.wholesale_price);

    document.getElementById('detailsStock').textContent =
        formatNumber(product.current_stock);

    document.getElementById('detailsMinimum').textContent =
        formatNumber(product.minimum_stock);

    document.getElementById('detailsMaximum').textContent =
        product.maximum_stock !== null &&
        product.maximum_stock !== ''
            ? formatNumber(product.maximum_stock)
            : 'No limit';

    document.getElementById('detailsDescription').textContent =
        product.description || 'No description.';

    const statusElement =
        document.getElementById('detailsStatus');

    if (product.status === 'active') {

        statusElement.innerHTML =
            '<span class="status-badge status-active">' +
            '<i class="bi bi-check-circle me-1"></i>' +
            'Active' +
            '</span>';

    } else {

        statusElement.innerHTML =
            '<span class="status-badge status-inactive">' +
            '<i class="bi bi-x-circle me-1"></i>' +
            'Inactive' +
            '</span>';

    }

    showModal('detailsModal');

}


/* =========================================================
   EDIT PRODUCT
========================================================= */

function editProduct(product) {

    document.getElementById('editProductId').value =
        product.id || '';

    document.getElementById('editName').value =
        product.name || '';

    document.getElementById('editSku').value =
        product.sku || '';

    document.getElementById('editBarcode').value =
        product.barcode || '';

    document.getElementById('editCategory').value =
        product.category_id || '';

    document.getElementById('editBrand').value =
        product.brand_id || '';

    document.getElementById('editUnit').value =
        product.unit_id || '';

    document.getElementById('editSupplier').value =
        product.supplier_id || '';

    document.getElementById('editCostPrice').value =
        product.cost_price || 0;

    document.getElementById('editSellingPrice').value =
        product.selling_price || 0;

    document.getElementById('editWholesalePrice').value =
        product.wholesale_price || 0;

    document.getElementById('editMinimumStock').value =
        product.minimum_stock || 0;

    document.getElementById('editMaximumStock').value =
        product.maximum_stock ?? '';

    document.getElementById('editCurrentStock').value =
        formatNumber(product.current_stock);

    document.getElementById('editDescription').value =
        product.description || '';

    showModal('editProductModal');

}


/* =========================================================
   ADJUST STOCK
========================================================= */

let selectedStockProduct = null;

function openStockModal(product) {

    selectedStockProduct = product;

    document.getElementById('stockProductId').value =
        product.id || '';

    document.getElementById('stockProductName').textContent =
        product.name || '-';

    document.getElementById('stockCurrent').textContent =
        formatNumber(product.current_stock);

    document.getElementById('stockQuantity').value =
        '1';

    document.getElementById('stockNew').textContent =
        formatNumber(product.current_stock + 1);

    document.getElementById('stockIn').checked = true;

    updateStockPreview();

    showModal('stockModal');

}


/* =========================================================
   STOCK PREVIEW
========================================================= */

function updateStockPreview() {

    if (!selectedStockProduct) {
        return;
    }

    const currentStock =
        parseFloat(
            selectedStockProduct.current_stock || 0
        );

    const quantity =
        parseFloat(
            document.getElementById('stockQuantity').value || 0
        );

    const movement =
        document.querySelector(
            'input[name="movement_type"]:checked'
        )?.value;

    let newStock = currentStock;

    if (movement === 'stock_in') {

        newStock =
            currentStock + quantity;

    } else if (movement === 'stock_out') {

        newStock =
            currentStock - quantity;

    }

    const newStockElement =
        document.getElementById('stockNew');

    newStockElement.textContent =
        formatNumber(newStock);

    newStockElement.classList.remove(
        'text-primary',
        'text-danger',
        'text-success'
    );

    if (newStock < 0) {

        newStockElement.classList.add(
            'text-danger'
        );

    } else if (newStock === 0) {

        newStockElement.classList.add(
            'text-danger'
        );

    } else {

        newStockElement.classList.add(
            'text-primary'
        );

    }

}


/* =========================================================
   STOCK INPUT EVENTS
========================================================= */

document.addEventListener(
    'input',
    function (event) {

        if (
            event.target &&
            event.target.id === 'stockQuantity'
        ) {

            updateStockPreview();

        }

    }
);


document.addEventListener(
    'change',
    function (event) {

        if (
            event.target &&
            event.target.name === 'movement_type'
        ) {

            updateStockPreview();

        }

    }
);


/* =========================================================
   FORM VALIDATION
========================================================= */

document.addEventListener(
    'submit',
    function (event) {

        const form =
            event.target;

        if (
            form.querySelector(
                'input[name="action"][value="adjust_stock"]'
            )
        ) {

            if (!selectedStockProduct) {
                return;
            }

            const currentStock =
                parseFloat(
                    selectedStockProduct.current_stock || 0
                );

            const quantity =
                parseFloat(
                    document.getElementById(
                        'stockQuantity'
                    ).value || 0
                );

            const movement =
                document.querySelector(
                    'input[name="movement_type"]:checked'
                )?.value;

            if (
                movement === 'stock_out' &&
                quantity > currentStock
            ) {

                event.preventDefault();

                alert(
                    'Stock out quantity cannot be greater than the current stock.'
                );

            }

        }

    }
);


/* =========================================================
   FORMAT NUMBER
========================================================= */

function formatNumber(value) {

    const number =
        parseFloat(value || 0);

    return number.toLocaleString(
        undefined,
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }
    );

}


/* =========================================================
   FORMAT CURRENCY
========================================================= */

function formatCurrency(value) {

    const number =
        parseFloat(value || 0);

    return '₱' +
        number.toLocaleString(
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