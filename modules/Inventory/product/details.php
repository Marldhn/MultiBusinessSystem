<?php

$pdo = Database::getConnection();

$businessId = $_SESSION['business_id'] ?? null;
$userId = $_SESSION['user_id'] ?? null;

if (!$businessId || !$userId) {
    header('Location: index.php?page=login');
    exit;
}

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productId <= 0) {
    header('Location: index.php?page=inventory_products');
    exit;
}

$businessName = $_SESSION['business_name'] ?? 'Business';
$activePage = 'inventory_products';
$pageTitle = 'Product Details';

$error = '';
$success = '';
$product = null;


/*
|--------------------------------------------------------------------------
| UPDATE PRODUCT
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_product') {

    $name = trim($_POST['name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $categoryId = !empty($_POST['category_id'])
        ? (int)$_POST['category_id']
        : null;

    $brandId = !empty($_POST['brand_id'])
        ? (int)$_POST['brand_id']
        : null;

    $unitId = !empty($_POST['unit_id'])
        ? (int)$_POST['unit_id']
        : null;

    $supplierId = !empty($_POST['supplier_id'])
        ? (int)$_POST['supplier_id']
        : null;

    $costPrice = max(
        0,
        (float)($_POST['cost_price'] ?? 0)
    );

    $sellingPrice = max(
        0,
        (float)($_POST['selling_price'] ?? 0)
    );

    $wholesalePrice = max(
        0,
        (float)($_POST['wholesale_price'] ?? 0)
    );

    $minimumStock = max(
        0,
        (float)($_POST['minimum_stock'] ?? 0)
    );

    $maximumStock = ($_POST['maximum_stock'] ?? '') !== ''
        ? max(0, (float)$_POST['maximum_stock'])
        : null;

    $status = ($_POST['status'] ?? 'active') === 'inactive'
        ? 'inactive'
        : 'active';


    if ($name === '') {

        $error = 'Product name is required.';

    } else {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | CHECK PRODUCT
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT id
                FROM inventory_products
                WHERE id = ?
                AND business_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $productId,
                $businessId
            ]);

            if (!$stmt->fetch()) {
                throw new Exception(
                    'Product not found or you do not have permission to modify it.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CHECK SKU
            |--------------------------------------------------------------------------
            */

            if ($sku !== '') {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM inventory_products
                    WHERE business_id = ?
                    AND sku = ?
                    AND id != ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $businessId,
                    $sku,
                    $productId
                ]);

                if ($stmt->fetch()) {
                    throw new Exception(
                        'SKU already exists for this business.'
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | CHECK BARCODE
            |--------------------------------------------------------------------------
            */

            if ($barcode !== '') {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM inventory_products
                    WHERE business_id = ?
                    AND barcode = ?
                    AND id != ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $businessId,
                    $barcode,
                    $productId
                ]);

                if ($stmt->fetch()) {
                    throw new Exception(
                        'Barcode already exists for this business.'
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE PRODUCT
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE inventory_products
                SET
                    category_id = ?,
                    brand_id = ?,
                    unit_id = ?,
                    supplier_id = ?,
                    name = ?,
                    sku = ?,
                    barcode = ?,
                    description = ?,
                    cost_price = ?,
                    selling_price = ?,
                    wholesale_price = ?,
                    minimum_stock = ?,
                    maximum_stock = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?
                AND business_id = ?
            ");

            $stmt->execute([
                $categoryId,
                $brandId,
                $unitId,
                $supplierId,
                $name,
                $sku !== '' ? $sku : null,
                $barcode !== '' ? $barcode : null,
                $description !== '' ? $description : null,
                $costPrice,
                $sellingPrice,
                $wholesalePrice,
                $minimumStock,
                $maximumStock,
                $status,
                $productId,
                $businessId
            ]);


            $pdo->commit();

            $success = 'Product updated successfully.';

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| LOAD PRODUCT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.business_id,
        p.category_id,
        p.brand_id,
        p.unit_id,
        p.supplier_id,
        p.name,
        p.sku,
        p.barcode,
        p.description,
        p.cost_price,
        p.selling_price,
        p.wholesale_price,
        p.minimum_stock,
        p.maximum_stock,
        p.current_stock,
        p.image,
        p.status,
        p.created_by,
        p.created_at,
        p.updated_at,

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

    WHERE p.id = ?
    AND p.business_id = ?

    LIMIT 1
");

$stmt->execute([
    $productId,
    $businessId
]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {

    if (!$error) {
        $error = 'Product not found or you do not have permission to view this product.';
    }

}


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
| LOAD BRANDS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, name
    FROM inventory_brands
    WHERE business_id = ?
    AND status = 'active'
    ORDER BY name ASC
");

$stmt->execute([$businessId]);

$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| LOAD UNITS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, name, abbreviation
    FROM inventory_units
    WHERE business_id = ?
    AND status = 'active'
    ORDER BY name ASC
");

$stmt->execute([$businessId]);

$units = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| LOAD SUPPLIERS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, name
    FROM inventory_suppliers
    WHERE business_id = ?
    AND status = 'active'
    ORDER BY name ASC
");

$stmt->execute([$businessId]);

$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| PRODUCT VALUES
|--------------------------------------------------------------------------
*/

if ($product) {

    $productName = $product['name'] ?? 'Unnamed Product';

    $sku = $product['sku'] ?? '';
    $barcode = $product['barcode'] ?? '';

    $description = $product['description'] ?? '';

    $costPrice = (float)($product['cost_price'] ?? 0);

    $sellingPrice = (float)($product['selling_price'] ?? 0);

    $wholesalePrice = (float)($product['wholesale_price'] ?? 0);

    $minimumStock = (float)($product['minimum_stock'] ?? 0);

    $maximumStock = $product['maximum_stock'] !== null
        ? (float)$product['maximum_stock']
        : null;

    $currentStock = (float)($product['current_stock'] ?? 0);

    $status = $product['status'] ?? 'inactive';


    /*
    |--------------------------------------------------------------------------
    | STOCK STATUS
    |--------------------------------------------------------------------------
    */

    if ($currentStock <= 0) {

        $stockStatus = 'Out of Stock';
        $stockClass = 'danger';
        $stockIcon = 'bi-box2';

    } elseif ($currentStock <= $minimumStock) {

        $stockStatus = 'Low Stock';
        $stockClass = 'warning';
        $stockIcon = 'bi-exclamation-triangle';

    } else {

        $stockStatus = 'In Stock';
        $stockClass = 'success';
        $stockIcon = 'bi-box-seam';

    }


    /*
    |--------------------------------------------------------------------------
    | INITIAL
    |--------------------------------------------------------------------------
    */

    $initial = strtoupper(
        substr(trim($productName), 0, 1)
    );


    /*
    |--------------------------------------------------------------------------
    | IMAGE
    |--------------------------------------------------------------------------
    */

    $imagePath = '';

    if (!empty($product['image'])) {

        $image = ltrim($product['image'], '/\\');

        $possiblePaths = [

            $_SERVER['DOCUMENT_ROOT'] . '/' . $image,

            __DIR__ . '/../../../public/' . $image,

            __DIR__ . '/../../../' . $image

        ];

        foreach ($possiblePaths as $possiblePath) {

            if (is_file($possiblePath)) {

                $imagePath = $image;

                break;
            }
        }
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

<script>

(function () {

    const theme =
        localStorage.getItem('bs-theme') || 'light';

    document.documentElement.setAttribute(
        'data-bs-theme',
        theme
    );

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

.page-header {
    margin-bottom: 24px;
}

.product-card {
    border: 0;
    border-radius: 16px;
    overflow: hidden;
}

.product-header {
    padding: 24px;
    border-bottom: 1px solid var(--bs-border-color);
}

.product-avatar {
    width: 72px;
    height: 72px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.5rem;
    font-weight: 700;
}

.product-image {
    width: 72px;
    height: 72px;
    object-fit: cover;
    border-radius: 16px;
}

.product-name {
    font-size: 1.35rem;
    font-weight: 700;
    line-height: 1.3;
}

.product-meta {
    font-size: .8rem;
    color: var(--bs-secondary-color);
}

.status-badge {
    font-size: .72rem;
    font-weight: 700;
    padding: 6px 10px;
    border-radius: 20px;
}

.info-section {
    padding: 24px;
}

.section-title {
    font-size: .9rem;
    font-weight: 700;
    margin-bottom: 18px;
}

.info-item {
    padding: 13px 0;
    border-bottom: 1px solid var(--bs-border-color);
}

.info-item:last-child {
    border-bottom: 0;
}

.info-label {
    font-size: .72rem;
    color: var(--bs-secondary-color);
    margin-bottom: 3px;
}

.info-value {
    font-size: .88rem;
    font-weight: 600;
    color: var(--bs-body-color);
    word-break: break-word;
}

.price-card {
    border: 1px solid var(--bs-border-color);
    border-radius: 13px;
    padding: 17px;
    height: 100%;
}

.price-label {
    font-size: .72rem;
    color: var(--bs-secondary-color);
    margin-bottom: 5px;
}

.price-value {
    font-size: 1.15rem;
    font-weight: 700;
}

.stock-box {
    border: 1px solid var(--bs-border-color);
    border-radius: 13px;
    padding: 18px;
    height: 100%;
}

.stock-number {
    font-size: 1.6rem;
    font-weight: 700;
}

.description-box {
    background: var(--bs-tertiary-bg);
    border-radius: 12px;
    padding: 16px;
    font-size: .85rem;
    line-height: 1.6;
    white-space: pre-wrap;
}

.detail-footer {
    padding: 18px 24px;
    border-top: 1px solid var(--bs-border-color);
}

.page-alert {
    border: 0;
    border-radius: 12px;
}

.edit-modal .modal-content {
    border: 0;
    border-radius: 16px;
    overflow: hidden;
}

.edit-modal .modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--bs-border-color);
}

.edit-modal .modal-body {
    padding: 24px;
}

.edit-modal .modal-footer {
    padding: 18px 24px;
    border-top: 1px solid var(--bs-border-color);
}

.form-label {
    font-size: .8rem;
    font-weight: 600;
    margin-bottom: 6px;
}

.form-control,
.form-select {
    border-radius: 9px;
    padding: 10px 12px;
}

.form-control:focus,
.form-select:focus {
    box-shadow: 0 0 0 .2rem rgba(13,110,253,.1);
}

@media (max-width: 575.98px) {

    .main-content {
        padding: 14px !important;
    }

    .page-header {
        margin-bottom: 16px;
    }

    .page-title {
        font-size: 1.3rem;
    }

    .product-card {
        border-radius: 14px;
    }

    .product-header {
        padding: 18px;
    }

    .product-avatar,
    .product-image {
        width: 58px;
        height: 58px;
        border-radius: 13px;
    }

    .product-name {
        font-size: 1.05rem;
    }

    .info-section {
        padding: 18px;
    }

    .detail-footer {
        padding: 14px 18px;
    }

    .detail-footer .btn {
        width: 100%;
    }

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

<main class="inventory-main flex-grow-1 bg-body-tertiary">

<div class="main-content p-3 p-md-4">

<!-- =====================================================
     PAGE HEADER
====================================================== -->

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

<div>

<div class="d-flex align-items-center gap-2 mb-1">

<div class="d-lg-none bg-primary bg-opacity-10 text-primary rounded-3 p-2">

<i class="bi bi-box-seam"></i>

</div>

<h2 class="page-title fw-bold text-body mb-0">
Product Details
</h2>

</div>

<p class="text-muted small mb-0">

View product information and inventory details for

<span class="fw-semibold text-primary">
<?= htmlspecialchars($businessName) ?>
</span>

</p>

</div>

<div class="d-flex gap-2">

<a
href="index.php?page=inventory_products"
class="btn btn-outline-secondary fw-semibold rounded-3"

>

<i class="bi bi-arrow-left me-1"></i>
Back to Products </a>

<?php if ($product): ?>

<button
type="button"
class="btn btn-primary fw-bold rounded-3 shadow-sm"
data-bs-toggle="modal"
data-bs-target="#editProductModal"

>

<i class="bi bi-pencil-square me-1"></i>
Edit Product </button>

<?php endif; ?>

</div>

</div>

<?php if ($success): ?>

<div class="alert alert-success page-alert shadow-sm mb-4">

<i class="bi bi-check-circle-fill me-2"></i>

<?= htmlspecialchars($success) ?>

</div>

<?php endif; ?>

<?php if ($error): ?>

<div class="page-alert alert alert-danger shadow-sm py-3 px-3">

<div class="d-flex align-items-center gap-2">

<i class="bi bi-exclamation-triangle-fill"></i>

<span>
<?= htmlspecialchars($error) ?>
</span>

</div>

</div>

<?php elseif ($product): ?>

<!-- =====================================================
     PRODUCT CARD
====================================================== -->

<div class="product-card card shadow-sm bg-body mb-4">

<!-- PRODUCT HEADER -->

<div class="product-header">

<div class="d-flex align-items-center gap-3">

<?php if (!empty($imagePath)): ?>

<img
src="<?= htmlspecialchars($imagePath) ?>"
alt="<?= htmlspecialchars($productName) ?>"
class="product-image"

>

<?php else: ?>

<div class="product-avatar bg-primary bg-opacity-10 text-primary">

<?= htmlspecialchars($initial) ?>

</div>

<?php endif; ?>

<div class="min-width-0 flex-grow-1">

<div class="d-flex flex-wrap align-items-center gap-2">

<div class="product-name text-body">

<?= htmlspecialchars($productName) ?>

</div>

<?php if ($status === 'active'): ?>

<span class="status-badge bg-success bg-opacity-10 text-success">

Active

</span>

<?php else: ?>

<span class="status-badge bg-danger bg-opacity-10 text-danger">

Inactive

</span>

<?php endif; ?>

</div>

<div class="product-meta mt-1">

<?php if ($sku): ?>

SKU: <strong>

<?= htmlspecialchars($sku) ?>

</strong>

<?php else: ?>

No SKU

<?php endif; ?>

<?php if ($barcode): ?>

<span class="mx-1">•</span>

Barcode: <strong>

<?= htmlspecialchars($barcode) ?>

</strong>

<?php endif; ?>

</div>

</div>

</div>

</div>

<!-- =====================================================
     BASIC INFORMATION
====================================================== -->

<div class="info-section">

<div class="section-title">

<i class="bi bi-info-circle me-1 text-primary"></i>

Product Information

</div>

<div class="row g-4">

<div class="col-6 col-lg-3">

<div class="info-item">

<div class="info-label">
Category
</div>

<div class="info-value">

<?= htmlspecialchars(
    $product['category_name'] ?? '-'
) ?>

</div>

</div>

</div>

<div class="col-6 col-lg-3">

<div class="info-item">

<div class="info-label">
Brand
</div>

<div class="info-value">

<?= htmlspecialchars(
    $product['brand_name'] ?? '-'
) ?>

</div>

</div>

</div>

<div class="col-6 col-lg-3">

<div class="info-item">

<div class="info-label">
Unit
</div>

<div class="info-value">

<?php if (!empty($product['unit_name'])): ?>

<?= htmlspecialchars(
    $product['unit_name']
) ?>

<?php if (!empty($product['unit_abbreviation'])): ?>

<span class="text-muted">

(<?= htmlspecialchars(
 $product['unit_abbreviation']
) ?>)

</span>

<?php endif; ?>

<?php else: ?>

*

<?php endif; ?>

</div>

</div>

</div>

<div class="col-6 col-lg-3">

<div class="info-item">

<div class="info-label">
Supplier
</div>

<div class="info-value">

<?= htmlspecialchars(
    $product['supplier_name'] ?? '-'
) ?>

</div>

</div>

</div>

</div>

</div>

<!-- =====================================================
     PRICES
====================================================== -->

<div class="info-section border-top">

<div class="section-title">

<i class="bi bi-cash-stack me-1 text-success"></i>

Pricing

</div>

<div class="row g-3">

<div class="col-12 col-md-4">

<div class="price-card">

<div class="price-label">
Cost Price
</div>

<div class="price-value text-body">

₱<?= number_format(
 $costPrice,
 2
) ?>

</div>

</div>

</div>

<div class="col-12 col-md-4">

<div class="price-card">

<div class="price-label">
Selling Price
</div>

<div class="price-value text-primary">

₱<?= number_format(
 $sellingPrice,
 2
) ?>

</div>

</div>

</div>

<div class="col-12 col-md-4">

<div class="price-card">

<div class="price-label">
Wholesale Price
</div>

<div class="price-value text-success">

₱<?= number_format(
 $wholesalePrice,
 2
) ?>

</div>

</div>

</div>

</div>

</div>

<!-- =====================================================
     STOCK
====================================================== -->

<div class="info-section border-top">

<div class="section-title">

<i class="bi bi-box-seam me-1 text-primary"></i>

Inventory Stock

</div>

<div class="row g-3">

<div class="col-12 col-md-4">

<div class="stock-box">

<div class="d-flex justify-content-between align-items-start">

<div>

<div class="info-label">
Current Stock
</div>

<div class="stock-number text-<?= $stockClass ?>">

<?= number_format(
    $currentStock,
    2
) ?>

</div>

</div>

<i class="bi <?= $stockIcon ?> fs-4 text-<?= $stockClass ?>"></i>

</div>

<div class="mt-2">

<span class="badge text-bg-<?= $stockClass ?>">

<?= htmlspecialchars($stockStatus) ?>

</span>

</div>

</div>

</div>

<div class="col-12 col-md-4">

<div class="stock-box">

<div class="info-label">
Minimum Stock
</div>

<div class="stock-number text-body">

<?= number_format(
    $minimumStock,
    2
) ?>

</div>

<div class="small text-muted mt-1">

Reorder threshold

</div>

</div>

</div>

<div class="col-12 col-md-4">

<div class="stock-box">

<div class="info-label">
Maximum Stock
</div>

<div class="stock-number text-body">

<?= $maximumStock !== null
    ? number_format($maximumStock, 2)
    : 'No limit'
?>

</div>

<div class="small text-muted mt-1">

Maximum inventory level

</div>

</div>

</div>

</div>

</div>

<!-- =====================================================
     DESCRIPTION
====================================================== -->

<div class="info-section border-top">

<div class="section-title">

<i class="bi bi-card-text me-1 text-info"></i>

Description

</div>

<?php if ($description !== ''): ?>

<div class="description-box">

<?= htmlspecialchars($description) ?>

</div>

<?php else: ?>

<div class="description-box text-muted">

No description provided for this product.

</div>

<?php endif; ?>

</div>

<!-- =====================================================
     SYSTEM INFORMATION
====================================================== -->

<div class="info-section border-top">

<div class="section-title">

<i class="bi bi-clock-history me-1 text-secondary"></i>

System Information

</div>

<div class="row g-4">

<div class="col-12 col-md-4">

<div class="info-label">
Product ID
</div>

<div class="info-value">

#<?= (int)$product['id'] ?>

</div>

</div>

<div class="col-12 col-md-4">

<div class="info-label">
Created At
</div>

<div class="info-value">

<?= !empty($product['created_at'])
    ? htmlspecialchars(
        date(
            'M d, Y h:i A',
            strtotime($product['created_at'])
        )
    )
    : '-'
?>

</div>

</div>

<div class="col-12 col-md-4">

<div class="info-label">
Last Updated
</div>

<div class="info-value">

<?= !empty($product['updated_at'])
    ? htmlspecialchars(
        date(
            'M d, Y h:i A',
            strtotime($product['updated_at'])
        )
    )
    : '-'
?>

</div>

</div>

</div>

</div>

<!-- =====================================================
     FOOTER
====================================================== -->

<div class="detail-footer">

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">

<div class="small text-muted">

<i class="bi bi-building me-1"></i>

<?= htmlspecialchars($businessName) ?>

</div>

<div class="d-flex gap-2">

<a
href="index.php?page=inventory_products"
class="btn btn-outline-secondary btn-sm fw-semibold rounded-3"

>

<i class="bi bi-arrow-left me-1"></i>

Back

</a>

<button
type="button"
class="btn btn-primary btn-sm fw-bold rounded-3"
data-bs-toggle="modal"
data-bs-target="#editProductModal"

>

<i class="bi bi-pencil-square me-1"></i>

Edit Product

</button>

</div>

</div>

</div>

</div>

<?php endif; ?>

</div>

</main>

</div>

<!-- =========================================================
     EDIT PRODUCT MODAL
========================================================= -->

<?php if ($product): ?>

<div
    class="modal fade edit-modal"
    id="editProductModal"
    tabindex="-1"
    aria-labelledby="editProductModalLabel"
    aria-hidden="true"
>

<div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">

<div class="modal-content bg-body">

<!-- MODAL HEADER -->

<div class="modal-header">

<div>

<h5
    class="modal-title fw-bold"
    id="editProductModalLabel"
>

<i class="bi bi-pencil-square text-primary me-2"></i>

Edit Product

</h5>

<div class="small text-muted mt-1">

Modify product information

</div>

</div>

<button
type="button"
class="btn-close"
data-bs-dismiss="modal"
aria-label="Close"

> </button>

</div>

<!-- MODAL BODY -->

<form method="POST">

<input
type="hidden"
name="action"
value="update_product"

>

<div class="modal-body">

<div class="row g-3">

<!-- PRODUCT NAME -->

<div class="col-12">

<label class="form-label">
Product Name *
</label>

<input
type="text"
name="name"
class="form-control"
value="<?= htmlspecialchars($product['name'] ?? '') ?>"
required

>

</div>

<!-- SKU -->

<div class="col-12 col-md-6">

<label class="form-label">
SKU
</label>

<input
type="text"
name="sku"
class="form-control"
value="<?= htmlspecialchars($product['sku'] ?? '') ?>"

>

</div>

<!-- BARCODE -->

<div class="col-12 col-md-6">

<label class="form-label">
Barcode
</label>

<input
type="text"
name="barcode"
class="form-control"
value="<?= htmlspecialchars($product['barcode'] ?? '') ?>"

>

</div>

<!-- CATEGORY -->

<div class="col-12 col-md-6">

<label class="form-label">
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

<option
    value="<?= (int)$category['id'] ?>"
    <?= (int)($product['category_id'] ?? 0) === (int)$category['id']
        ? 'selected'
        : ''
    ?>
>

<?= htmlspecialchars($category['name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<!-- BRAND -->

<div class="col-12 col-md-6">

<label class="form-label">
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

<option
    value="<?= (int)$brand['id'] ?>"
    <?= (int)($product['brand_id'] ?? 0) === (int)$brand['id']
        ? 'selected'
        : ''
    ?>
>

<?= htmlspecialchars($brand['name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<!-- UNIT -->

<div class="col-12 col-md-6">

<label class="form-label">
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

<option
    value="<?= (int)$unit['id'] ?>"
    <?= (int)($product['unit_id'] ?? 0) === (int)$unit['id']
        ? 'selected'
        : ''
    ?>
>

<?= htmlspecialchars($unit['name']) ?>

<?php if (!empty($unit['abbreviation'])): ?>

(<?= htmlspecialchars($unit['abbreviation']) ?>)

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>

<!-- SUPPLIER -->

<div class="col-12 col-md-6">

<label class="form-label">
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

<option
    value="<?= (int)$supplier['id'] ?>"
    <?= (int)($product['supplier_id'] ?? 0) === (int)$supplier['id']
        ? 'selected'
        : ''
    ?>
>

<?= htmlspecialchars($supplier['name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<!-- COST PRICE -->

<div class="col-12 col-md-4">

<label class="form-label">
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
value="<?= htmlspecialchars($product['cost_price'] ?? 0) ?>"

>

</div>

</div>

<!-- SELLING PRICE -->

<div class="col-12 col-md-4">

<label class="form-label">
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
value="<?= htmlspecialchars($product['selling_price'] ?? 0) ?>"

>

</div>

</div>

<!-- WHOLESALE PRICE -->

<div class="col-12 col-md-4">

<label class="form-label">
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
value="<?= htmlspecialchars($product['wholesale_price'] ?? 0) ?>"

>

</div>

</div>

<!-- MINIMUM STOCK -->

<div class="col-12 col-md-4">

<label class="form-label">
Minimum Stock
</label>

<input
type="number"
name="minimum_stock"
class="form-control"
min="0"
step="0.01"
value="<?= htmlspecialchars($product['minimum_stock'] ?? 0) ?>"

>

</div>

<!-- MAXIMUM STOCK -->

<div class="col-12 col-md-4">

<label class="form-label">
Maximum Stock
</label>

<input
type="number"
name="maximum_stock"
class="form-control"
min="0"
step="0.01"
value="<?= $product['maximum_stock'] !== null
     ? htmlspecialchars($product['maximum_stock'])
     : ''
 ?>"

>

</div>

<!-- STATUS -->

<div class="col-12 col-md-4">

<label class="form-label">
Status
</label>

<select
name="status"
class="form-select"

>

<option
    value="active"
    <?= ($product['status'] ?? '') === 'active'
        ? 'selected'
        : ''
    ?>
>

Active

</option>

<option
    value="inactive"
    <?= ($product['status'] ?? '') === 'inactive'
        ? 'selected'
        : ''
    ?>
>

Inactive

</option>

</select>

</div>

<!-- DESCRIPTION -->

<div class="col-12">

<label class="form-label">
Description
</label>

<textarea
    name="description"
    class="form-control"
    rows="4"
    placeholder="Product description"
><?= htmlspecialchars($product['description'] ?? '') ?></textarea>

</div>

</div>

</div>

<!-- MODAL FOOTER -->

<div class="modal-footer">

<button
type="button"
class="btn btn-outline-secondary fw-semibold rounded-3"
data-bs-dismiss="modal"

>

Cancel

</button>

<button
type="submit"
class="btn btn-primary fw-bold rounded-3"

>

<i class="bi bi-check-lg me-1"></i>

Save Changes

</button>

</div>

</form>

</div>

</div>

</div>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php if ($success): ?>

<script>

document.addEventListener('DOMContentLoaded', function () {

    const modalElement =
        document.getElementById('editProductModal');

    if (modalElement) {

        const modal =
            bootstrap.Modal.getInstance(modalElement);

        if (modal) {
            modal.hide();
        }

    }

});

</script>

<?php endif; ?>

</body>

</html>
