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

$activePage = 'inventory_products';

$error = '';
$success = '';

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| ADD PRODUCT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'add'
) {

    $name = trim($_POST['name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $categoryId = (int)($_POST['category_id'] ?? 0);
    $brandId = (int)($_POST['brand_id'] ?? 0);
    $unitId = (int)($_POST['unit_id'] ?? 0);

    $costPrice = (float)($_POST['cost_price'] ?? 0);
    $sellingPrice = (float)($_POST['selling_price'] ?? 0);
    $wholesalePrice = (float)($_POST['wholesale_price'] ?? 0);

    $minimumStock = (float)($_POST['minimum_stock'] ?? 0);
    $maximumStock = trim($_POST['maximum_stock'] ?? '');
    $currentStock = (float)($_POST['current_stock'] ?? 0);

    $status = $_POST['status'] ?? 'active';

    if ($name === '') {

        $error = 'Product name is required.';

    } elseif ($costPrice < 0) {

        $error = 'Cost price cannot be negative.';

    } elseif ($sellingPrice < 0) {

        $error = 'Selling price cannot be negative.';

    } elseif ($wholesalePrice < 0) {

        $error = 'Wholesale price cannot be negative.';

    } elseif ($minimumStock < 0) {

        $error = 'Minimum stock cannot be negative.';

    } elseif ($currentStock < 0) {

        $error = 'Current stock cannot be negative.';

    } elseif (
        $maximumStock !== '' &&
        (float)$maximumStock < 0
    ) {

        $error = 'Maximum stock cannot be negative.';

    } elseif (
        !in_array(
            $status,
            ['active', 'inactive'],
            true
        )
    ) {

        $error = 'Invalid product status.';

    } else {

        try {

            /*
             * IMPORTANT:
             *
             * business_id is NEVER taken from POST.
             *
             * It comes from the authenticated session.
             *
             * This prevents a user from creating a product
             * under another business.
             */

            if ($sku !== '') {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM pos_products
                    WHERE business_id = ?
                    AND sku = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $businessId,
                    $sku
                ]);

                if ($stmt->fetch()) {

                    throw new Exception(
                        'SKU already exists in this business.'
                    );
                }
            }


            if ($barcode !== '') {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM pos_products
                    WHERE business_id = ?
                    AND barcode = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $businessId,
                    $barcode
                ]);

                if ($stmt->fetch()) {

                    throw new Exception(
                        'Barcode already exists in this business.'
                    );
                }
            }


            $stmt = $pdo->prepare("
                INSERT INTO pos_products
                (
                    business_id,
                    category_id,
                    brand_id,
                    unit_id,
                    name,
                    sku,
                    barcode,
                    description,
                    cost_price,
                    selling_price,
                    wholesale_price,
                    minimum_stock,
                    maximum_stock,
                    current_stock,
                    image,
                    status,
                    created_by
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    NULL,
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $businessId,

                $categoryId > 0
                    ? $categoryId
                    : null,

                $brandId > 0
                    ? $brandId
                    : null,

                $unitId > 0
                    ? $unitId
                    : null,

                $name,

                $sku !== ''
                    ? $sku
                    : null,

                $barcode !== ''
                    ? $barcode
                    : null,

                $description !== ''
                    ? $description
                    : null,

                $costPrice,
                $sellingPrice,
                $wholesalePrice,
                $minimumStock,

                $maximumStock !== ''
                    ? (float)$maximumStock
                    : null,

                $currentStock,

                $status,

                $userId
            ]);

            $success = 'Product added successfully.';

        } catch (Throwable $e) {

            $error = $e instanceof Exception
                ? $e->getMessage()
                : 'Unable to add product. Please try again.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| EDIT PRODUCT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'edit'
) {

    $productId = (int)($_POST['product_id'] ?? 0);

    $name = trim($_POST['name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $categoryId = (int)($_POST['category_id'] ?? 0);
    $brandId = (int)($_POST['brand_id'] ?? 0);
    $unitId = (int)($_POST['unit_id'] ?? 0);

    $costPrice = (float)($_POST['cost_price'] ?? 0);
    $sellingPrice = (float)($_POST['selling_price'] ?? 0);
    $wholesalePrice = (float)($_POST['wholesale_price'] ?? 0);

    $minimumStock = (float)($_POST['minimum_stock'] ?? 0);
    $maximumStock = trim($_POST['maximum_stock'] ?? '');
    $currentStock = (float)($_POST['current_stock'] ?? 0);

    $status = $_POST['status'] ?? 'active';

    if ($productId <= 0) {

        $error = 'Invalid product.';

    } elseif ($name === '') {

        $error = 'Product name is required.';

    } elseif ($costPrice < 0) {

        $error = 'Cost price cannot be negative.';

    } elseif ($sellingPrice < 0) {

        $error = 'Selling price cannot be negative.';

    } elseif ($wholesalePrice < 0) {

        $error = 'Wholesale price cannot be negative.';

    } elseif ($minimumStock < 0) {

        $error = 'Minimum stock cannot be negative.';

    } elseif ($currentStock < 0) {

        $error = 'Current stock cannot be negative.';

    } elseif (
        $maximumStock !== '' &&
        (float)$maximumStock < 0
    ) {

        $error = 'Maximum stock cannot be negative.';

    } elseif (
        !in_array(
            $status,
            ['active', 'inactive'],
            true
        )
    ) {

        $error = 'Invalid product status.';

    } else {

        try {

            /*
             * Check product belongs to the CURRENT business.
             */

            $stmt = $pdo->prepare("
                SELECT id
                FROM pos_products
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
                    'Product was not found in this business.'
                );
            }


            /*
             * Check duplicate SKU.
             */

            if ($sku !== '') {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM pos_products
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
                        'SKU already exists in this business.'
                    );
                }
            }


            /*
             * Check duplicate barcode.
             */

            if ($barcode !== '') {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM pos_products
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
                        'Barcode already exists in this business.'
                    );
                }
            }


            /*
             * Update ONLY the current business product.
             */

            $stmt = $pdo->prepare("
                UPDATE pos_products
                SET
                    category_id = ?,
                    brand_id = ?,
                    unit_id = ?,
                    name = ?,
                    sku = ?,
                    barcode = ?,
                    description = ?,
                    cost_price = ?,
                    selling_price = ?,
                    wholesale_price = ?,
                    minimum_stock = ?,
                    maximum_stock = ?,
                    current_stock = ?,
                    status = ?
                WHERE id = ?
                AND business_id = ?
            ");

            $stmt->execute([

                $categoryId > 0
                    ? $categoryId
                    : null,

                $brandId > 0
                    ? $brandId
                    : null,

                $unitId > 0
                    ? $unitId
                    : null,

                $name,

                $sku !== ''
                    ? $sku
                    : null,

                $barcode !== ''
                    ? $barcode
                    : null,

                $description !== ''
                    ? $description
                    : null,

                $costPrice,
                $sellingPrice,
                $wholesalePrice,
                $minimumStock,

                $maximumStock !== ''
                    ? (float)$maximumStock
                    : null,

                $currentStock,

                $status,

                $productId,
                $businessId
            ]);

            $success = 'Product updated successfully.';

        } catch (Throwable $e) {

            $error = $e instanceof Exception
                ? $e->getMessage()
                : 'Unable to update product. Please try again.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| TOGGLE PRODUCT STATUS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'toggle_status'
) {

    $productId = (int)($_POST['product_id'] ?? 0);

    if ($productId <= 0) {

        $error = 'Invalid product.';

    } else {

        try {

            $stmt = $pdo->prepare("
                SELECT status
                FROM pos_products
                WHERE id = ?
                AND business_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $productId,
                $businessId
            ]);

            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {

                throw new Exception(
                    'Product was not found in this business.'
                );
            }

            $newStatus =
                $product['status'] === 'active'
                    ? 'inactive'
                    : 'active';

            $stmt = $pdo->prepare("
                UPDATE pos_products
                SET status = ?
                WHERE id = ?
                AND business_id = ?
            ");

            $stmt->execute([
                $newStatus,
                $productId,
                $businessId
            ]);

            $success =
                $newStatus === 'active'
                    ? 'Product activated successfully.'
                    : 'Product deactivated successfully.';

        } catch (Throwable $e) {

            $error = $e instanceof Exception
                ? $e->getMessage()
                : 'Unable to change product status.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| LOAD CATEGORIES
|--------------------------------------------------------------------------
*/

$categories = [];

try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            name
        FROM pos_categories
        WHERE business_id = ?
        AND status = 'active'
        ORDER BY name ASC
    ");

    $stmt->execute([
        $businessId
    ]);

    $categories =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $categories = [];
}


/*
|--------------------------------------------------------------------------
| LOAD BRANDS
|--------------------------------------------------------------------------
*/

$brands = [];

try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            name
        FROM pos_brands
        WHERE business_id = ?
        AND status = 'active'
        ORDER BY name ASC
    ");

    $stmt->execute([
        $businessId
    ]);

    $brands =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $brands = [];
}


/*
|--------------------------------------------------------------------------
| LOAD UNITS
|--------------------------------------------------------------------------
*/

$units = [];

try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            abbreviation
        FROM pos_units
        WHERE business_id = ?
        AND status = 'active'
        ORDER BY name ASC
    ");

    $stmt->execute([
        $businessId
    ]);

    $units =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $units = [];
}


/*
|--------------------------------------------------------------------------
| COUNT PRODUCTS
|--------------------------------------------------------------------------
*/

$totalProducts = 0;

try {

    $countSql = "
        SELECT COUNT(*)
        FROM pos_products
        WHERE business_id = ?
    ";

    $countParams = [
        $businessId
    ];

    if ($search !== '') {

        $countSql .= "
            AND (
                name LIKE ?
                OR sku LIKE ?
                OR barcode LIKE ?
            )
        ";

        $searchValue =
            '%' . $search . '%';

        $countParams[] = $searchValue;
        $countParams[] = $searchValue;
        $countParams[] = $searchValue;
    }

    if (
        in_array(
            $statusFilter,
            ['active', 'inactive'],
            true
        )
    ) {

        $countSql .= "
            AND status = ?
        ";

        $countParams[] =
            $statusFilter;
    }

    $stmt =
        $pdo->prepare($countSql);

    $stmt->execute($countParams);

    $totalProducts =
        (int)$stmt->fetchColumn();

} catch (Throwable $e) {

    $totalProducts = 0;
}


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$totalPages =
    max(
        1,
        (int)ceil(
            $totalProducts / $perPage
        )
    );

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset =
    ($page - 1) * $perPage;


/*
|--------------------------------------------------------------------------
| LOAD PRODUCTS
|--------------------------------------------------------------------------
*/

$products = [];

try {

    $sql = "
        SELECT
            p.id,
            p.business_id,
            p.category_id,
            p.brand_id,
            p.unit_id,
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

            c.name AS category_name,
            b.name AS brand_name,
            u.name AS unit_name,
            u.abbreviation AS unit_abbreviation

        FROM pos_products p

        LEFT JOIN pos_categories c
            ON c.id = p.category_id
            AND c.business_id = p.business_id

        LEFT JOIN pos_brands b
            ON b.id = p.brand_id
            AND b.business_id = p.business_id

        LEFT JOIN pos_units u
            ON u.id = p.unit_id
            AND u.business_id = p.business_id

        WHERE p.business_id = ?
    ";

    $params = [
        $businessId
    ];


    if ($search !== '') {

        $sql .= "
            AND (
                p.name LIKE ?
                OR p.sku LIKE ?
                OR p.barcode LIKE ?
            )
        ";

        $searchValue =
            '%' . $search . '%';

        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
    }


    if (
        in_array(
            $statusFilter,
            ['active', 'inactive'],
            true
        )
    ) {

        $sql .= "
            AND p.status = ?
        ";

        $params[] =
            $statusFilter;
    }


    $sql .= "
        ORDER BY p.id DESC
        LIMIT {$perPage}
        OFFSET {$offset}
    ";

    $stmt =
        $pdo->prepare($sql);

    $stmt->execute($params);

    $products =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $products = [];

    if (!$error) {
        $error =
            'Unable to load products.';
    }
}


/*
|--------------------------------------------------------------------------
| PAGE TITLE
|--------------------------------------------------------------------------
*/

$pageTitle = 'POS Products';

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
    <?= e($pageTitle) ?>
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

.pos-main {
    min-width: 0;
    width: 100%;
}

.page-title {
    font-size: 1.5rem;
}

.page-subtitle {
    font-size: .82rem;
}

.pos-card {
    border: 0;
    border-radius: 16px;
}

.pos-card-header {
    padding: 18px 20px;
    border-bottom: 1px solid var(--bs-border-color);
}

.product-avatar {
    width: 42px;
    height: 42px;

    border-radius: 10px;

    display: flex;
    align-items: center;
    justify-content: center;

    background: var(--bs-primary-bg-subtle);
    color: var(--bs-primary);

    overflow: hidden;

    flex-shrink: 0;
}

.product-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-name {
    font-size: .84rem;
    font-weight: 700;
}

.product-meta {
    font-size: .68rem;
    color: var(--bs-secondary-color);
}

.product-price {
    font-size: .82rem;
    font-weight: 700;
}

.product-stock {
    font-size: .78rem;
    font-weight: 700;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;

    padding: .35rem .65rem;

    border-radius: 50rem;

    font-size: .68rem;
    font-weight: 700;
}

.status-active {
    background: rgba(25, 135, 84, .12);
    color: var(--bs-success);
}

.status-inactive {
    background: rgba(108, 117, 125, .12);
    color: var(--bs-secondary);
}

.stock-normal {
    color: var(--bs-success);
}

.stock-low {
    color: var(--bs-warning-text);
}

.stock-out {
    color: var(--bs-danger);
}

.empty-state {
    padding: 60px 20px;
}

.search-box {
    border-radius: 12px;
}

.modal-content {
    border: 0;
    border-radius: 16px;
}

.form-label {
    font-size: .76rem;
    font-weight: 600;
}

.product-mobile-card {
    border: 1px solid var(--bs-border-color);
    border-radius: 14px;
    padding: 15px;
    margin-bottom: 12px;
}

.mobile-label {
    color: var(--bs-secondary-color);
    font-size: .67rem;
}

.mobile-value {
    font-size: .78rem;
    font-weight: 600;
}

.pagination .page-link {
    border-radius: 8px;
    margin: 0 2px;
}

@media (max-width: 767.98px) {

    .main-content {
        padding: 14px !important;
    }

    .page-title {
        font-size: 1.3rem;
    }

    .desktop-products {
        display: none;
    }

    .mobile-products {
        display: block !important;
    }

    .add-product-btn {
        width: 100%;
    }
}

@media (min-width: 768px) {

    .mobile-products {
        display: none !important;
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

<div class="main-content p-3 p-md-4">


<!-- =========================================================
     HEADER
========================================================= -->

<div
    class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4"
>

    <div>

        <div class="d-flex align-items-center gap-2 mb-1">

            <div
                class="d-lg-none bg-primary bg-opacity-10 text-primary rounded-3 p-2"
            >

                <i class="bi bi-box-seam"></i>

            </div>

            <h2 class="page-title fw-bold mb-0">

                Products

            </h2>

        </div>

        <p class="page-subtitle text-muted mb-0">

            Manage products for

            <span class="fw-semibold text-primary">

                <?= e($businessName) ?>

            </span>

        </p>

    </div>


    <button
        type="button"
        class="btn btn-primary fw-bold rounded-3 add-product-btn"
        data-bs-toggle="modal"
        data-bs-target="#addProductModal"
    >

        <i class="bi bi-plus-lg me-1"></i>

        Add Product

    </button>

</div>


<!-- =========================================================
     ALERTS
========================================================= -->

<?php if ($success): ?>

<div class="alert alert-success border-0 rounded-3 small">

    <i class="bi bi-check-circle me-1"></i>

    <?= e($success) ?>

</div>

<?php endif; ?>


<?php if ($error): ?>

<div class="alert alert-danger border-0 rounded-3 small">

    <i class="bi bi-exclamation-circle me-1"></i>

    <?= e($error) ?>

</div>

<?php endif; ?>


<!-- =========================================================
     SEARCH / FILTER
========================================================= -->

<div class="card pos-card shadow-sm bg-body mb-4">

<div class="card-body p-3">

<form method="GET">

<input
    type="hidden"
    name="page"
    value="inventory_products"
>

<div class="row g-2">

    <div class="col-12 col-lg">

        <div class="input-group search-box">

            <span
                class="input-group-text bg-body border-end-0"
            >

                <i class="bi bi-search text-muted"></i>

            </span>

            <input
                type="text"
                name="search"
                class="form-control border-start-0"
                placeholder="Search product, SKU or barcode..."
                value="<?= e($search) ?>"
            >

        </div>

    </div>


    <div class="col-12 col-sm-6 col-lg-auto">

        <select
            name="status"
            class="form-select"
        >

            <option value="">
                All Status
            </option>

            <option
                value="active"
                <?= $statusFilter === 'active'
                    ? 'selected'
                    : ''
                ?>
            >
                Active
            </option>

            <option
                value="inactive"
                <?= $statusFilter === 'inactive'
                    ? 'selected'
                    : ''
                ?>
            >
                Inactive
            </option>

        </select>

    </div>


    <div class="col-12 col-sm-6 col-lg-auto">

        <button
            type="submit"
            class="btn btn-primary w-100 rounded-3"
        >

            <i class="bi bi-search me-1"></i>

            Search

        </button>

    </div>


    <?php if (
        $search !== '' ||
        $statusFilter !== ''
    ): ?>

    <div class="col-12 col-lg-auto">

        <a
            href="index.php?page=inventory_products"
            class="btn btn-outline-secondary w-100 rounded-3"
        >

            Clear

        </a>

    </div>

    <?php endif; ?>

</div>

</form>

</div>

</div>


<!-- =========================================================
     PRODUCT LIST
========================================================= -->

<div class="card pos-card shadow-sm bg-body">

<div
    class="pos-card-header d-flex justify-content-between align-items-center"
>

    <div>

        <h5 class="fw-bold mb-1">

            Product List

        </h5>

        <p class="text-muted small mb-0">

            <?= number_format($totalProducts) ?>

            product<?= $totalProducts == 1 ? '' : 's' ?>

        </p>

    </div>


    <div class="text-muted small">

        <i class="bi bi-building me-1"></i>

        <?= e($businessName) ?>

    </div>

</div>


<?php if (!$products): ?>

<div class="empty-state text-center">

    <div class="mb-3">

        <i
            class="bi bi-box-seam display-5 text-muted opacity-50"
        ></i>

    </div>

    <div class="fw-semibold mb-1">

        <?= $search !== ''
            ? 'No products found'
            : 'No products yet'
        ?>

    </div>

    <div class="text-muted small mb-3">

        <?= $search !== ''
            ? 'Try another search term.'
            : 'Add your first product to start using POS.'
        ?>

    </div>

    <?php if (
        $search === '' &&
        $statusFilter === ''
    ): ?>

    <button
        type="button"
        class="btn btn-primary btn-sm fw-bold rounded-3"
        data-bs-toggle="modal"
        data-bs-target="#addProductModal"
    >

        <i class="bi bi-plus-lg me-1"></i>

        Add Product

    </button>

    <?php endif; ?>

</div>

<?php else: ?>


<!-- =========================================================
     DESKTOP TABLE
========================================================= -->

<div class="table-responsive desktop-products">

<table class="table table-hover align-middle mb-0">

<thead class="table-light">

<tr>

    <th class="ps-4 py-3">
        Product
    </th>

    <th>
        Category
    </th>

    <th>
        SKU
    </th>

    <th class="text-end">
        Selling Price
    </th>

    <th class="text-end">
        Stock
    </th>

    <th>
        Status
    </th>

    <th class="text-end pe-4">
        Actions
    </th>

</tr>

</thead>


<tbody>

<?php foreach ($products as $product): ?>

<?php

$stock =
    (float)(
        $product['current_stock'] ?? 0
    );

$minimum =
    (float)(
        $product['minimum_stock'] ?? 0
    );

if ($stock <= 0) {

    $stockClass = 'stock-out';

} elseif ($stock <= $minimum) {

    $stockClass = 'stock-low';

} else {

    $stockClass = 'stock-normal';

}

$initial =
    strtoupper(
        substr(
            $product['name'],
            0,
            1
        )
    );

?>

<tr>


<!-- PRODUCT -->

<td class="ps-4">

<div class="d-flex align-items-center gap-3">

<div class="product-avatar">

<?php if (!empty($product['image'])): ?>

<img
    src="<?= e($product['image']) ?>"
    alt="<?= e($product['name']) ?>"
>

<?php else: ?>

<i class="bi bi-box fs-5"></i>

<?php endif; ?>

</div>


<div>

<div class="product-name">

<?= e($product['name']) ?>

</div>

<div class="product-meta">

#<?= e($product['id']) ?>

<?php if (
    !empty($product['brand_name'])
): ?>

&nbsp;•&nbsp;

<?= e($product['brand_name']) ?>

<?php endif; ?>

</div>

</div>

</div>

</td>


<!-- CATEGORY -->

<td>

<span class="product-meta">

<?= !empty($product['category_name'])
    ? e($product['category_name'])
    : '—'
?>

</span>

</td>


<!-- SKU -->

<td>

<span class="product-meta">

<?= !empty($product['sku'])
    ? e($product['sku'])
    : '—'
?>

</span>

</td>


<!-- PRICE -->

<td class="text-end">

<div class="product-price">

₱<?= number_format(
    (float)$product['selling_price'],
    2
) ?>

</div>

<?php if (
    (float)$product['wholesale_price'] > 0
): ?>

<div class="product-meta">

Wholesale:
₱<?= number_format(
    (float)$product['wholesale_price'],
    2
) ?>

</div>

<?php endif; ?>

</td>


<!-- STOCK -->

<td class="text-end">

<div class="product-stock <?= $stockClass ?>">

<?= number_format(
    $stock,
    2
) ?>

</div>

<div class="product-meta">

Min:
<?= number_format(
    $minimum,
    2
) ?>

<?php if (
    !empty($product['unit_abbreviation'])
): ?>

<?= e(
    $product['unit_abbreviation']
) ?>

<?php endif; ?>

</div>

</td>


<!-- STATUS -->

<td>

<?php if (
    $product['status'] === 'active'
): ?>

<span class="status-badge status-active">

<i class="bi bi-check-circle"></i>

Active

</span>

<?php else: ?>

<span class="status-badge status-inactive">

<i class="bi bi-dash-circle"></i>

Inactive

</span>

<?php endif; ?>

</td>


<!-- ACTIONS -->

<td class="text-end pe-4">

<div class="btn-group">


<button
    type="button"
    class="btn btn-sm btn-outline-primary"
    data-bs-toggle="modal"
    data-bs-target="#editProductModal<?= $product['id'] ?>"
    title="Edit"
>

<i class="bi bi-pencil"></i>

</button>


<form method="POST">

<input
    type="hidden"
    name="action"
    value="toggle_status"
>

<input
    type="hidden"
    name="product_id"
    value="<?= e($product['id']) ?>"
>

<button
    type="submit"
    class="btn btn-sm btn-outline-<?= $product['status'] === 'active' ? 'danger' : 'success' ?>"
    title="<?= $product['status'] === 'active'
        ? 'Deactivate'
        : 'Activate'
    ?>"
>

<i
    class="bi bi-<?= $product['status'] === 'active'
        ? 'eye-slash'
        : 'eye'
    ?>"
></i>

</button>

</form>

</div>

</td>

</tr>


<!-- =========================================================
     EDIT PRODUCT MODAL
========================================================= -->

<div
    class="modal fade"
    id="editProductModal<?= $product['id'] ?>"
    tabindex="-1"
>

<div class="modal-dialog modal-lg modal-dialog-centered">

<div class="modal-content">

<form method="POST">

<input
    type="hidden"
    name="action"
    value="edit"
>

<input
    type="hidden"
    name="product_id"
    value="<?= e($product['id']) ?>"
>


<div class="modal-header">

<div>

<h5 class="modal-title fw-bold">

Edit Product

</h5>

<div class="text-muted small">

Product #<?= e($product['id']) ?>

</div>

</div>


<button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal"
></button>

</div>


<div class="modal-body">

<div class="row g-3">


<!-- NAME -->

<div class="col-md-8">

<label class="form-label">

Product Name *

</label>

<input
    type="text"
    name="name"
    class="form-control"
    value="<?= e($product['name']) ?>"
    required
>

</div>


<!-- STATUS -->

<div class="col-md-4">

<label class="form-label">

Status

</label>

<select
    name="status"
    class="form-select"
>

<option
    value="active"
    <?= $product['status'] === 'active'
        ? 'selected'
        : ''
    ?>
>
Active
</option>

<option
    value="inactive"
    <?= $product['status'] === 'inactive'
        ? 'selected'
        : ''
    ?>
>
Inactive
</option>

</select>

</div>


<!-- CATEGORY -->

<div class="col-md-4">

<label class="form-label">

Category

</label>

<select
    name="category_id"
    class="form-select"
>

<option value="0">
No Category
</option>

<?php foreach ($categories as $category): ?>

<option
    value="<?= e($category['id']) ?>"
    <?= (int)$product['category_id'] ===
        (int)$category['id']
        ? 'selected'
        : ''
    ?>
>

<?= e($category['name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<!-- BRAND -->

<div class="col-md-4">

<label class="form-label">

Brand

</label>

<select
    name="brand_id"
    class="form-select"
>

<option value="0">
No Brand
</option>

<?php foreach ($brands as $brand): ?>

<option
    value="<?= e($brand['id']) ?>"
    <?= (int)$product['brand_id'] ===
        (int)$brand['id']
        ? 'selected'
        : ''
    ?>
>

<?= e($brand['name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<!-- UNIT -->

<div class="col-md-4">

<label class="form-label">

Unit

</label>

<select
    name="unit_id"
    class="form-select"
>

<option value="0">
No Unit
</option>

<?php foreach ($units as $unit): ?>

<option
    value="<?= e($unit['id']) ?>"
    <?= (int)$product['unit_id'] ===
        (int)$unit['id']
        ? 'selected'
        : ''
    ?>
>

<?= e($unit['name']) ?>

<?php if (
    !empty($unit['abbreviation'])
): ?>

(<?= e(
    $unit['abbreviation']
) ?>)

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<!-- SKU -->

<div class="col-md-6">

<label class="form-label">

SKU

</label>

<input
    type="text"
    name="sku"
    class="form-control"
    value="<?= e($product['sku'] ?? '') ?>"
>

</div>


<!-- BARCODE -->

<div class="col-md-6">

<label class="form-label">

Barcode

</label>

<input
    type="text"
    name="barcode"
    class="form-control"
    value="<?= e($product['barcode'] ?? '') ?>"
>

</div>


<!-- COST -->

<div class="col-md-4">

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
    value="<?= e($product['cost_price']) ?>"
>

</div>

</div>


<!-- SELLING -->

<div class="col-md-4">

<label class="form-label">

Selling Price *

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
    value="<?= e($product['selling_price']) ?>"
    required
>

</div>

</div>


<!-- WHOLESALE -->

<div class="col-md-4">

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
    value="<?= e($product['wholesale_price']) ?>"
>

</div>

</div>


<!-- MINIMUM -->

<div class="col-md-4">

<label class="form-label">

Minimum Stock

</label>

<input
    type="number"
    name="minimum_stock"
    class="form-control"
    min="0"
    step="0.01"
    value="<?= e($product['minimum_stock']) ?>"
>

</div>


<!-- MAXIMUM -->

<div class="col-md-4">

<label class="form-label">

Maximum Stock

</label>

<input
    type="number"
    name="maximum_stock"
    class="form-control"
    min="0"
    step="0.01"
    value="<?= e($product['maximum_stock'] ?? '') ?>"
>

</div>


<!-- CURRENT -->

<div class="col-md-4">

<label class="form-label">

Current Stock

</label>

<input
    type="number"
    name="current_stock"
    class="form-control"
    min="0"
    step="0.01"
    value="<?= e($product['current_stock']) ?>"
>

</div>


<!-- DESCRIPTION -->

<div class="col-12">

<label class="form-label">

Description

</label>

<textarea
    name="description"
    class="form-control"
    rows="3"
><?= e($product['description'] ?? '') ?></textarea>

</div>

</div>

</div>


<div class="modal-footer">

<button
    type="button"
    class="btn btn-outline-secondary rounded-3"
    data-bs-dismiss="modal"
>

Cancel

</button>

<button
    type="submit"
    class="btn btn-primary rounded-3 fw-bold"
>

<i class="bi bi-check-lg me-1"></i>

Save Changes

</button>

</div>

</form>

</div>

</div>

</div>

<?php endforeach; ?>

</tbody>

</table>

</div>


<!-- =========================================================
     MOBILE PRODUCTS
========================================================= -->

<div class="mobile-products p-3">

<?php foreach ($products as $product): ?>

<?php

$stock =
    (float)(
        $product['current_stock'] ?? 0
    );

$minimum =
    (float)(
        $product['minimum_stock'] ?? 0
    );

if ($stock <= 0) {

    $stockClass = 'stock-out';

} elseif ($stock <= $minimum) {

    $stockClass = 'stock-low';

} else {

    $stockClass = 'stock-normal';

}

?>

<div class="product-mobile-card">


<div class="d-flex justify-content-between gap-3">

<div class="d-flex gap-3">

<div class="product-avatar">

<?php if (!empty($product['image'])): ?>

<img
    src="<?= e($product['image']) ?>"
    alt="<?= e($product['name']) ?>"
>

<?php else: ?>

<i class="bi bi-box fs-5"></i>

<?php endif; ?>

</div>


<div>

<div class="product-name">

<?= e($product['name']) ?>

</div>

<div class="product-meta">

<?= !empty($product['sku'])
    ? 'SKU: ' . e($product['sku'])
    : 'No SKU'
?>

</div>

</div>

</div>


<?php if (
    $product['status'] === 'active'
): ?>

<span class="status-badge status-active">
Active
</span>

<?php else: ?>

<span class="status-badge status-inactive">
Inactive
</span>

<?php endif; ?>

</div>


<div class="row g-3 mt-2">

<div class="col-6">

<div class="mobile-label">
Selling Price
</div>

<div class="mobile-value">

₱<?= number_format(
    (float)$product['selling_price'],
    2
) ?>

</div>

</div>


<div class="col-6">

<div class="mobile-label">
Stock
</div>

<div class="mobile-value <?= $stockClass ?>">

<?= number_format(
    $stock,
    2
) ?>

</div>

</div>


<div class="col-6">

<div class="mobile-label">
Category
</div>

<div class="mobile-value">

<?= !empty($product['category_name'])
    ? e($product['category_name'])
    : '—'
?>

</div>

</div>


<div class="col-6">

<div class="mobile-label">
Brand
</div>

<div class="mobile-value">

<?= !empty($product['brand_name'])
    ? e($product['brand_name'])
    : '—'
?>

</div>

</div>

</div>


<div class="d-flex gap-2 mt-3">

<button
    type="button"
    class="btn btn-sm btn-outline-primary flex-grow-1 rounded-3"
    data-bs-toggle="modal"
    data-bs-target="#editProductModal<?= $product['id'] ?>"
>

<i class="bi bi-pencil me-1"></i>

Edit

</button>


<form
    method="POST"
    class="flex-grow-1"
>

<input
    type="hidden"
    name="action"
    value="toggle_status"
>

<input
    type="hidden"
    name="product_id"
    value="<?= e($product['id']) ?>"
>

<button
    type="submit"
    class="btn btn-sm btn-outline-<?= $product['status'] === 'active'
        ? 'danger'
        : 'success'
    ?> w-100 rounded-3"
>

<i
    class="bi bi-<?= $product['status'] === 'active'
        ? 'eye-slash'
        : 'eye'
    ?> me-1"
></i>

<?= $product['status'] === 'active'
    ? 'Deactivate'
    : 'Activate'
?>

</button>

</form>

</div>

</div>

<?php endforeach; ?>

</div>


<!-- =========================================================
     PAGINATION
========================================================= -->

<?php if ($totalPages > 1): ?>

<div class="p-3 border-top">

<nav>

<ul class="pagination pagination-sm justify-content-center mb-0">


<?php if ($page > 1): ?>

<li class="page-item">

<a
    class="page-link"
    href="index.php?page=inventory_products&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&p=<?= $page - 1 ?>"
>

<i class="bi bi-chevron-left"></i>

</a>

</li>

<?php endif; ?>


<?php

$startPage =
    max(
        1,
        $page - 2
    );

$endPage =
    min(
        $totalPages,
        $page + 2
    );

for (
    $i = $startPage;
    $i <= $endPage;
    $i++
):

?>

<li
    class="page-item <?= $i === $page
        ? 'active'
        : ''
    ?>"
>

<a
    class="page-link"
    href="index.php?page=inventory_products&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&p=<?= $i ?>"
>

<?= $i ?>

</a>

</li>

<?php endfor; ?>


<?php if ($page < $totalPages): ?>

<li class="page-item">

<a
    class="page-link"
    href="index.php?page=inventory_products&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&p=<?= $page + 1 ?>"
>

<i class="bi bi-chevron-right"></i>

</a>

</li>

<?php endif; ?>

</ul>

</nav>

</div>

<?php endif; ?>


<?php endif; ?>

</div>

</div>

</main>

</div>


<!-- =========================================================
     ADD PRODUCT MODAL
========================================================= -->

<div
    class="modal fade"
    id="addProductModal"
    tabindex="-1"
>

<div class="modal-dialog modal-lg modal-dialog-centered">

<div class="modal-content">

<form method="POST">

<input
    type="hidden"
    name="action"
    value="add"
>


<div class="modal-header">

<div>

<h5 class="modal-title fw-bold">

Add Product

</h5>

<div class="text-muted small">

<?= e($businessName) ?>

</div>

</div>


<button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal"
></button>

</div>


<div class="modal-body">

<div class="row g-3">


<!-- NAME -->

<div class="col-md-8">

<label class="form-label">

Product Name *

</label>

<input
    type="text"
    name="name"
    class="form-control"
    placeholder="Product name"
    required
>

</div>


<!-- STATUS -->

<div class="col-md-4">

<label class="form-label">

Status

</label>

<select
    name="status"
    class="form-select"
>

<option value="active">
Active
</option>

<option value="inactive">
Inactive
</option>

</select>

</div>


<!-- CATEGORY -->

<div class="col-md-4">

<label class="form-label">

Category

</label>

<select
    name="category_id"
    class="form-select"
>

<option value="0">
No Category
</option>

<?php foreach ($categories as $category): ?>

<option
    value="<?= e($category['id']) ?>"
>

<?= e($category['name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<!-- BRAND -->

<div class="col-md-4">

<label class="form-label">

Brand

</label>

<select
    name="brand_id"
    class="form-select"
>

<option value="0">
No Brand
</option>

<?php foreach ($brands as $brand): ?>

<option
    value="<?= e($brand['id']) ?>"
>

<?= e($brand['name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<!-- UNIT -->

<div class="col-md-4">

<label class="form-label">

Unit

</label>

<select
    name="unit_id"
    class="form-select"
>

<option value="0">
No Unit
</option>

<?php foreach ($units as $unit): ?>

<option
    value="<?= e($unit['id']) ?>"
>

<?= e($unit['name']) ?>

<?php if (
    !empty($unit['abbreviation'])
): ?>

(<?= e(
    $unit['abbreviation']
) ?>)

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<!-- SKU -->

<div class="col-md-6">

<label class="form-label">

SKU

</label>

<input
    type="text"
    name="sku"
    class="form-control"
    placeholder="SKU-001"
>

</div>


<!-- BARCODE -->

<div class="col-md-6">

<label class="form-label">

Barcode

</label>

<input
    type="text"
    name="barcode"
    class="form-control"
    placeholder="Scan or enter barcode"
>

</div>


<!-- COST -->

<div class="col-md-4">

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
    value="0.00"
>

</div>

</div>


<!-- SELLING -->

<div class="col-md-4">

<label class="form-label">

Selling Price *

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
    value="0.00"
    required
>

</div>

</div>


<!-- WHOLESALE -->

<div class="col-md-4">

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
    value="0.00"
>

</div>

</div>


<!-- MINIMUM STOCK -->

<div class="col-md-4">

<label class="form-label">

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


<!-- MAXIMUM STOCK -->

<div class="col-md-4">

<label class="form-label">

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


<!-- CURRENT STOCK -->

<div class="col-md-4">

<label class="form-label">

Current Stock

</label>

<input
    type="number"
    name="current_stock"
    class="form-control"
    min="0"
    step="0.01"
    value="0"
>

</div>


<!-- DESCRIPTION -->

<div class="col-12">

<label class="form-label">

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
    class="btn btn-outline-secondary rounded-3"
    data-bs-dismiss="modal"
>

Cancel

</button>

<button
    type="submit"
    class="btn btn-primary rounded-3 fw-bold"
>

<i class="bi bi-plus-lg me-1"></i>

Add Product

</button>

</div>

</form>

</div>

</div>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>
