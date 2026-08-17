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
| CREATE BRAND
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_brand') {

    $name = trim($_POST['name'] ?? '');

    if ($name === '') {

        $error = 'Brand name is required.';

    } else {

        try {

            $stmt = $pdo->prepare("
                SELECT id
                FROM inventory_brands
                WHERE business_id = ?
                AND LOWER(name) = LOWER(?)
                LIMIT 1
            ");

            $stmt->execute([
                $businessId,
                $name
            ]);

            if ($stmt->fetch()) {

                throw new Exception('A brand with this name already exists.');

            }

            $stmt = $pdo->prepare("
                INSERT INTO inventory_brands (
                    business_id,
                    name,
                    status,
                    created_by
                )
                VALUES (
                    ?,
                    ?,
                    'active',
                    ?
                )
            ");

            $stmt->execute([
                $businessId,
                $name,
                $userId
            ]);

            $success = 'Brand created successfully.';

        } catch (Throwable $e) {

            $error = $e->getMessage();

        }

    }

}

/*
|--------------------------------------------------------------------------
| UPDATE BRAND
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_brand') {

    $brandId = (int)($_POST['brand_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');

    if ($brandId <= 0) {

        $error = 'Invalid brand selected.';

    } elseif ($name === '') {

        $error = 'Brand name is required.';

    } else {

        try {

            $stmt = $pdo->prepare("
                SELECT id
                FROM inventory_brands
                WHERE id = ?
                AND business_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $brandId,
                $businessId
            ]);

            if (!$stmt->fetch()) {

                throw new Exception('Brand not found.');

            }

            $stmt = $pdo->prepare("
                SELECT id
                FROM inventory_brands
                WHERE business_id = ?
                AND LOWER(name) = LOWER(?)
                AND id != ?
                LIMIT 1
            ");

            $stmt->execute([
                $businessId,
                $name,
                $brandId
            ]);

            if ($stmt->fetch()) {

                throw new Exception('Another brand with this name already exists.');

            }

            $stmt = $pdo->prepare("
                UPDATE inventory_brands
                SET name = ?
                WHERE id = ?
                AND business_id = ?
            ");

            $stmt->execute([
                $name,
                $brandId,
                $businessId
            ]);

            $success = 'Brand updated successfully.';

        } catch (Throwable $e) {

            $error = $e->getMessage();

        }

    }

}

/*
|--------------------------------------------------------------------------
| TOGGLE BRAND STATUS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_brand') {

    $brandId = (int)($_POST['brand_id'] ?? 0);

    if ($brandId <= 0) {

        $error = 'Invalid brand selected.';

    } else {

        try {

            $stmt = $pdo->prepare("
                SELECT id, name, status
                FROM inventory_brands
                WHERE id = ?
                AND business_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $brandId,
                $businessId
            ]);

            $brand = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$brand) {

                throw new Exception('Brand not found.');

            }

            $newStatus =
                $brand['status'] === 'active'
                    ? 'inactive'
                    : 'active';

            $stmt = $pdo->prepare("
                UPDATE inventory_brands
                SET status = ?
                WHERE id = ?
                AND business_id = ?
            ");

            $stmt->execute([
                $newStatus,
                $brandId,
                $businessId
            ]);

            if ($newStatus === 'active') {

                $success =
                    'Brand "' .
                    $brand['name'] .
                    '" has been enabled.';

            } else {

                $success =
                    'Brand "' .
                    $brand['name'] .
                    '" has been disabled.';

            }

        } catch (Throwable $e) {

            $error = $e->getMessage();

        }

    }

}

/*
|--------------------------------------------------------------------------
| DELETE BRAND
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_brand') {

    $brandId = (int)($_POST['brand_id'] ?? 0);

    if ($brandId <= 0) {

        $error = 'Invalid brand selected.';

    } else {

        try {

            $stmt = $pdo->prepare("
                SELECT id, name
                FROM inventory_brands
                WHERE id = ?
                AND business_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $brandId,
                $businessId
            ]);

            $brand = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$brand) {

                throw new Exception('Brand not found.');

            }

            /*
            |--------------------------------------------------------------------------
            | CHECK IF BRAND IS BEING USED
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM inventory_products
                WHERE business_id = ?
                AND brand_id = ?
            ");

            $stmt->execute([
                $businessId,
                $brandId
            ]);

            $productCount = (int)$stmt->fetchColumn();

            if ($productCount > 0) {

                throw new Exception(
                    'This brand cannot be deleted because it is currently assigned to ' .
                    $productCount .
                    ' product' .
                    ($productCount === 1 ? '' : 's') .
                    '. Disable the brand instead.'
                );

            }

            $stmt = $pdo->prepare("
                DELETE FROM inventory_brands
                WHERE id = ?
                AND business_id = ?
            ");

            $stmt->execute([
                $brandId,
                $businessId
            ]);

            $success =
                'Brand "' .
                $brand['name'] .
                '" deleted successfully.';

        } catch (Throwable $e) {

            $error = $e->getMessage();

        }

    }

}

/*
|--------------------------------------------------------------------------
| LOAD BRANDS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        b.id,
        b.business_id,
        b.name,
        b.status,
        b.created_by,
        b.created_at,
        b.updated_at,

        (
            SELECT COUNT(*)
            FROM inventory_products p
            WHERE p.business_id = b.business_id
            AND p.brand_id = b.id
        ) AS product_count

    FROM inventory_brands b

    WHERE b.business_id = ?

    ORDER BY b.name ASC
");

$stmt->execute([
    $businessId
]);

$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

$totalBrands = count($brands);
$activeBrands = 0;
$inactiveBrands = 0;
$brandsWithProducts = 0;

foreach ($brands as $brand) {

    if ($brand['status'] === 'active') {

        $activeBrands++;

    } else {

        $inactiveBrands++;

    }

    if ((int)$brand['product_count'] > 0) {

        $brandsWithProducts++;

    }

}

$activePage = 'inventory_brands';
$pageTitle = 'Brands - Inventory';
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

    const theme =
        localStorage.getItem('bs-theme') || 'light';

    document.documentElement.setAttribute(
        'data-bs-theme',
        theme
    );

})();

</script>

<style>

/*
|--------------------------------------------------------------------------
| GENERAL
|--------------------------------------------------------------------------
*/

body {
    min-height: 100vh;
    overflow-x: hidden;
}

.inventory-main {
    min-width: 0;
    width: 100%;
}

/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.inventory-header {
    padding-bottom: 4px;
}

.inventory-header h2 {
    font-size: 1.5rem;
}

/*
|--------------------------------------------------------------------------
| SUMMARY CARDS
|--------------------------------------------------------------------------
*/

.summary-card {
    border: 0;
    border-radius: 16px;
    transition:
        transform .15s ease,
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

/*
|--------------------------------------------------------------------------
| BRAND SECTION
|--------------------------------------------------------------------------
*/

.brand-section {
    border: 0;
    border-radius: 16px;
    overflow: hidden;
}

.brand-section-header {
    padding: 20px 24px;
}

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.brand-table {
    width: 100%;
}

.brand-table th {
    font-size: .72rem;
    letter-spacing: .04em;
    white-space: nowrap;
}

.brand-table td {
    vertical-align: middle;
    white-space: nowrap;
    padding-top: 14px;
    padding-bottom: 14px;
}

.brand-name {
    font-weight: 700;
}

/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| EMPTY STATE
|--------------------------------------------------------------------------
*/

.empty-state {
    padding: 55px 20px;
}

/*
|--------------------------------------------------------------------------
| MODALS
|--------------------------------------------------------------------------
*/

.brand-modal .modal-content {
    border: 0;
    border-radius: 16px;
}

.brand-modal .modal-header {
    padding: 18px 22px;
}

.brand-modal .modal-body {
    padding: 22px;
}

.brand-modal .modal-footer {
    padding: 15px 22px;
}

/*
|--------------------------------------------------------------------------
| FORM
|--------------------------------------------------------------------------
*/

.form-label {
    font-size: .78rem;
}

.form-control,
.form-select {
    border-radius: 9px;
}

.form-control:focus,
.form-select:focus {
    box-shadow:
        0 0 0 .2rem
        rgba(var(--bs-primary-rgb), .12);
}

/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

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

    .brand-section {
        border-radius: 14px;
    }

    .brand-section-header {
        padding: 15px;
    }

    .brand-table th,
    .brand-table td {
        font-size: .75rem;
    }

    .empty-state {
        padding: 40px 15px;
    }

    .brand-modal .modal-dialog {
        margin: 10px;
    }

    .brand-modal .modal-content {
        border-radius: 15px;
    }

    .brand-modal .modal-header {
        padding: 15px 16px;
    }

    .brand-modal .modal-body {
        padding: 16px;
    }

    .brand-modal .modal-footer {
        padding: 12px 16px;
    }

}

</style>

</head>

<body class="bg-body-tertiary">

<div class="d-flex flex-column flex-lg-row min-vh-100">

<?php

$sidebarPath =
    __DIR__ .
    '/../../../resources/partials/InventorySidebar.php';

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

                <i class="bi bi-tags"></i>

            </div>

            <h2 class="fw-bold text-body mb-0">
                Brands
            </h2>

        </div>

        <p class="text-muted small mb-0">

            Manage product brands for

            <span class="fw-semibold text-primary">

                <?= htmlspecialchars($businessName) ?>

            </span>

        </p>

    </div>

    <button
        type="button"
        class="btn btn-primary fw-bold px-3 py-2 rounded-3 shadow-sm d-flex align-items-center gap-2"
        onclick="openBrandModal()"
    >

        <i class="bi bi-plus-lg"></i>

        <span>
            Add Brand
        </span>

    </button>

</div>


<!-- =====================================================
     SUCCESS
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


<!-- =====================================================
     ERROR
====================================================== -->

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

    <!-- TOTAL -->

    <div class="col-12 col-md-4">

        <div class="card summary-card shadow-sm h-100 bg-body">

            <div class="card-body p-4">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <div class="text-muted small fw-semibold mb-2">
                            Total Brands
                        </div>

                        <div class="fs-3 fw-bold text-body">

                            <?= number_format($totalBrands) ?>

                        </div>

                        <div class="small text-muted mt-1">
                            All registered brands
                        </div>

                    </div>

                    <div class="summary-icon bg-primary bg-opacity-10 text-primary">

                        <i class="bi bi-tags fs-4"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- ACTIVE -->

    <div class="col-12 col-md-4">

        <div class="card summary-card shadow-sm h-100 bg-body">

            <div class="card-body p-4">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <div class="text-muted small fw-semibold mb-2">
                            Active Brands
                        </div>

                        <div class="fs-3 fw-bold text-success">

                            <?= number_format($activeBrands) ?>

                        </div>

                        <div class="small text-muted mt-1">
                            Available for products
                        </div>

                    </div>

                    <div class="summary-icon bg-success bg-opacity-10 text-success">

                        <i class="bi bi-check-circle fs-4"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- INACTIVE -->

    <div class="col-12 col-md-4">

        <div class="card summary-card shadow-sm h-100 bg-body">

            <div class="card-body p-4">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <div class="text-muted small fw-semibold mb-2">
                            Inactive Brands
                        </div>

                        <div class="fs-3 fw-bold text-danger">

                            <?= number_format($inactiveBrands) ?>

                        </div>

                        <div class="small text-muted mt-1">
                            Disabled brands
                        </div>

                    </div>

                    <div class="summary-icon bg-danger bg-opacity-10 text-danger">

                        <i class="bi bi-x-circle fs-4"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- =====================================================
     SEARCH + FILTER
====================================================== -->

<div class="card border-0 shadow-sm rounded-4 mb-4">

    <div class="card-body p-3">

        <div class="row g-2">

            <div class="col-12 col-lg-8">

                <div class="search-wrapper input-group">

                    <span class="input-group-text text-muted">

                        <i class="bi bi-search"></i>

                    </span>

                    <input
                        type="text"
                        id="brandSearch"
                        class="form-control"
                        placeholder="Search brand..."
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


            <div class="col-12 col-sm-6 col-lg-4">

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

        </div>


        <div class="d-flex justify-content-between align-items-center mt-3">

            <div
                id="filterResultText"
                class="small text-muted"
            >

                Showing <?= number_format($totalBrands) ?> brands

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
     BRAND LIST
====================================================== -->

<div class="card brand-section shadow-sm bg-body">

    <div class="brand-section-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">

        <div>

            <h5 class="fw-bold mb-1">
                Brand List
            </h5>

            <p class="text-muted small mb-0">

                <?= count($brands) ?>

                brand<?= count($brands) === 1 ? '' : 's' ?>

                registered

            </p>

        </div>

    </div>


    <?php if (!$brands): ?>

        <div class="empty-state text-center text-muted">

            <div class="mb-3">

                <i class="bi bi-tags display-6 opacity-50"></i>

            </div>

            <div class="fw-semibold mb-1">
                No brands found
            </div>

            <div class="small mb-3">
                Add your first brand to start organizing products.
            </div>

            <button
                type="button"
                class="btn btn-primary btn-sm fw-bold"
                onclick="openBrandModal()"
            >

                <i class="bi bi-plus-lg me-1"></i>

                Add Brand

            </button>

        </div>

    <?php else: ?>

        <div class="table-responsive">

            <table class="brand-table table table-hover align-middle mb-0">

                <thead class="table-light text-uppercase text-muted">

                    <tr>

                        <th class="ps-4 py-3">
                            ID
                        </th>

                        <th class="py-3">
                            Brand
                        </th>

                        <th class="py-3">
                            Products
                        </th>

                        <th class="py-3">
                            Status
                        </th>

                        <th class="py-3">
                            Created
                        </th>

                        <th class="py-3 pe-4 text-end">
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody id="brandTableBody">

                <?php foreach ($brands as $brand): ?>

                    <tr
                        class="brand-row"
                        data-search="<?= htmlspecialchars(
                            strtolower($brand['name'])
                        ) ?>"
                        data-status="<?= htmlspecialchars(
                            $brand['status']
                        ) ?>"
                    >

                        <!-- ID -->

                        <td class="ps-4">

                            <span class="text-muted small">

                                #<?= (int)$brand['id'] ?>

                            </span>

                        </td>


                        <!-- BRAND -->

                        <td>

                            <div class="d-flex align-items-center gap-2">

                                <div
                                    class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center"
                                    style="width:38px;height:38px;"
                                >

                                    <i class="bi bi-tag"></i>

                                </div>

                                <div>

                                    <div class="brand-name text-body">

                                        <?= htmlspecialchars(
                                            $brand['name']
                                        ) ?>

                                    </div>

                                </div>

                            </div>

                        </td>


                        <!-- PRODUCTS -->

                        <td>

                            <?php if ((int)$brand['product_count'] > 0): ?>

                                <span class="badge text-bg-primary rounded-pill">

                                    <?= number_format(
                                        (int)$brand['product_count']
                                    ) ?>

                                </span>

                            <?php else: ?>

                                <span class="text-muted small">
                                    0
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- STATUS -->

                        <td>

                            <?php if ($brand['status'] === 'active'): ?>

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


                        <!-- CREATED -->

                        <td>

                            <span class="small text-muted">

                                <?= !empty($brand['created_at'])
                                    ? date(
                                        'M d, Y',
                                        strtotime($brand['created_at'])
                                    )
                                    : '-'
                                ?>

                            </span>

                        </td>


                        <!-- ACTION -->

                        <td class="text-end pe-4">

                            <div class="dropdown">

                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary rounded-3 dropdown-toggle"
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false"
                                >

                                    <i class="bi bi-three-dots"></i>

                                    Action

                                </button>


                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">

                                    <!-- EDIT -->

                                    <li>

                                        <button
                                            type="button"
                                            class="dropdown-item"
                                            onclick='openEditBrandModal(
                                                <?= json_encode(
                                                    [
                                                        'id' => (int)$brand['id'],
                                                        'name' => $brand['name']
                                                    ],
                                                    JSON_HEX_TAG |
                                                    JSON_HEX_APOS |
                                                    JSON_HEX_QUOT |
                                                    JSON_HEX_AMP
                                                ) ?>
                                            )'
                                        >

                                            <i class="bi bi-pencil-square text-primary me-2"></i>

                                            Edit Brand

                                        </button>

                                    </li>


                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>


                                    <!-- ENABLE / DISABLE -->

                                    <li>

                                        <button
                                            type="button"
                                            class="dropdown-item"
                                            onclick='openToggleBrandModal(
                                                <?= json_encode(
                                                    [
                                                        'id' => (int)$brand['id'],
                                                        'name' => $brand['name'],
                                                        'status' => $brand['status']
                                                    ],
                                                    JSON_HEX_TAG |
                                                    JSON_HEX_APOS |
                                                    JSON_HEX_QUOT |
                                                    JSON_HEX_AMP
                                                ) ?>
                                            )'
                                        >

                                            <?php if ($brand['status'] === 'active'): ?>

                                                <i class="bi bi-slash-circle text-warning me-2"></i>

                                                Disable Brand

                                            <?php else: ?>

                                                <i class="bi bi-check-circle text-success me-2"></i>

                                                Enable Brand

                                            <?php endif; ?>

                                        </button>

                                    </li>


                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>


                                    <!-- DELETE -->

                                    <li>

                                        <button
                                            type="button"
                                            class="dropdown-item text-danger"
                                            onclick='openDeleteBrandModal(
                                                <?= json_encode(
                                                    [
                                                        'id' => (int)$brand['id'],
                                                        'name' => $brand['name'],
                                                        'product_count' => (int)$brand['product_count']
                                                    ],
                                                    JSON_HEX_TAG |
                                                    JSON_HEX_APOS |
                                                    JSON_HEX_QUOT |
                                                    JSON_HEX_AMP
                                                ) ?>
                                            )'
                                        >

                                            <i class="bi bi-trash me-2"></i>

                                            Delete Brand

                                        </button>

                                    </li>

                                </ul>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>


        <!-- NO SEARCH RESULTS -->

        <div
            id="noSearchResults"
            class="empty-state text-center text-muted d-none"
        >

            <div class="mb-3">

                <i class="bi bi-search display-6 opacity-50"></i>

            </div>

            <div class="fw-semibold mb-1">
                No brands found
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
     ADD BRAND MODAL
========================================================= -->

<div
    class="modal fade brand-modal"
    id="brandModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content shadow-lg">

            <div class="modal-header border-bottom">

                <h5 class="modal-title fw-bold">

                    <i class="bi bi-tag text-primary me-2"></i>

                    Add New Brand

                </h5>

                <button
                    type="button"
                    class="btn-close shadow-none"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <form method="POST">

                <input
                    type="hidden"
                    name="action"
                    value="create_brand"
                >


                <div class="modal-body">

                    <label class="form-label fw-semibold">

                        Brand Name

                        <span class="text-danger">*</span>

                    </label>

                    <input
                        type="text"
                        name="name"
                        class="form-control"
                        required
                        maxlength="150"
                        placeholder="Enter brand name"
                    >

                    <div class="form-text">

                        Example: Nike, Adidas, Samsung, Apple

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

                        Save Brand

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- =========================================================
     EDIT BRAND MODAL
========================================================= -->

<div
    class="modal fade brand-modal"
    id="editBrandModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content shadow-lg">

            <div class="modal-header border-bottom">

                <h5 class="modal-title fw-bold">

                    <i class="bi bi-pencil-square text-primary me-2"></i>

                    Edit Brand

                </h5>

                <button
                    type="button"
                    class="btn-close shadow-none"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <form method="POST">

                <input
                    type="hidden"
                    name="action"
                    value="update_brand"
                >

                <input
                    type="hidden"
                    name="brand_id"
                    id="editBrandId"
                >


                <div class="modal-body">

                    <label class="form-label fw-semibold">

                        Brand Name

                        <span class="text-danger">*</span>

                    </label>

                    <input
                        type="text"
                        name="name"
                        id="editBrandName"
                        class="form-control"
                        required
                        maxlength="150"
                        placeholder="Enter brand name"
                    >

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

                        <i class="bi bi-save me-1"></i>

                        Update Brand

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- =========================================================
     TOGGLE STATUS MODAL
========================================================= -->

<div
    class="modal fade brand-modal"
    id="toggleBrandModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content shadow-lg">

            <div class="modal-header border-bottom">

                <h5 class="modal-title fw-bold">

                    <i
                        id="toggleBrandIcon"
                        class="bi bi-slash-circle text-warning me-2"
                    ></i>

                    <span id="toggleBrandTitle">
                        Disable Brand
                    </span>

                </h5>

                <button
                    type="button"
                    class="btn-close shadow-none"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <form method="POST">

                <input
                    type="hidden"
                    name="action"
                    value="toggle_brand"
                >

                <input
                    type="hidden"
                    name="brand_id"
                    id="toggleBrandId"
                >


                <div class="modal-body">

                    <p
                        class="mb-0"
                        id="toggleBrandMessage"
                    ></p>

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
                        id="toggleBrandButton"
                        class="btn btn-warning fw-bold"
                    >

                        <i class="bi bi-slash-circle me-1"></i>

                        Disable Brand

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- =========================================================
     DELETE BRAND MODAL
========================================================= -->

<div
    class="modal fade brand-modal"
    id="deleteBrandModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content shadow-lg">

            <div class="modal-header border-bottom">

                <h5 class="modal-title fw-bold text-danger">

                    <i class="bi bi-trash me-2"></i>

                    Delete Brand

                </h5>

                <button
                    type="button"
                    class="btn-close shadow-none"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <form method="POST">

                <input
                    type="hidden"
                    name="action"
                    value="delete_brand"
                >

                <input
                    type="hidden"
                    name="brand_id"
                    id="deleteBrandId"
                >


                <div class="modal-body">

                    <div class="text-center py-2">

                        <div
                            class="bg-danger bg-opacity-10 text-danger rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                            style="width:65px;height:65px;"
                        >

                            <i class="bi bi-trash fs-3"></i>

                        </div>


                        <h6 class="fw-bold">
                            Are you sure?
                        </h6>


                        <p class="text-muted small mb-0">

                            You are about to delete

                            <strong
                                id="deleteBrandName"
                                class="text-body"
                            ></strong>.

                            This action cannot be undone.

                        </p>


                        <div
                            id="deleteBrandWarning"
                            class="alert alert-warning small mt-3 mb-0 d-none"
                        >

                            <i class="bi bi-exclamation-triangle me-1"></i>

                            This brand is currently assigned to products
                            and cannot be deleted.

                        </div>

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
                        id="deleteBrandButton"
                        class="btn btn-danger fw-bold"
                    >

                        <i class="bi bi-trash me-1"></i>

                        Delete Brand

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


<script>

/*
|--------------------------------------------------------------------------
| SEARCH + FILTER
|--------------------------------------------------------------------------
*/

document.addEventListener('DOMContentLoaded', function () {

    const searchInput =
        document.getElementById('brandSearch');

    const clearButton =
        document.getElementById('clearSearch');

    const statusFilter =
        document.getElementById('statusFilter');

    const clearFilters =
        document.getElementById('clearFilters');

    const rows =
        document.querySelectorAll('.brand-row');

    const noResults =
        document.getElementById('noSearchResults');

    const resultText =
        document.getElementById('filterResultText');


    function applyFilters() {

        const searchTerm =
            (searchInput?.value || '')
                .toLowerCase()
                .trim();

        const selectedStatus =
            statusFilter?.value || 'all';


        let visibleCount = 0;


        rows.forEach(function (row) {

            const searchData =
                row.getAttribute('data-search') ||
                row.textContent.toLowerCase();

            const rowStatus =
                row.getAttribute('data-status') || '';


            const matchesSearch =
                searchTerm === '' ||
                searchData.includes(searchTerm);

            const matchesStatus =
                selectedStatus === 'all' ||
                rowStatus === selectedStatus;


            const matches =
                matchesSearch &&
                matchesStatus;


            if (matches) {

                row.style.display = '';

                visibleCount++;

            } else {

                row.style.display = 'none';

            }

        });


        /*
        |--------------------------------------------------------------------------
        | CLEAR SEARCH BUTTON
        |--------------------------------------------------------------------------
        */

        if (clearButton) {

            if (searchTerm !== '') {

                clearButton.classList.remove('d-none');

            } else {

                clearButton.classList.add('d-none');

            }

        }


        /*
        |--------------------------------------------------------------------------
        | RESULT TEXT
        |--------------------------------------------------------------------------
        */

        if (resultText) {

            resultText.textContent =
                'Showing ' +
                visibleCount.toLocaleString() +
                ' brand' +
                (visibleCount === 1 ? '' : 's');

        }


        /*
        |--------------------------------------------------------------------------
        | NO RESULTS
        |--------------------------------------------------------------------------
        */

        if (noResults) {

            if (
                visibleCount === 0 &&
                rows.length > 0
            ) {

                noResults.classList.remove('d-none');

            } else {

                noResults.classList.add('d-none');

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | SEARCH
    |--------------------------------------------------------------------------
    */

    if (searchInput) {

        searchInput.addEventListener(
            'input',
            applyFilters
        );

    }


    /*
    |--------------------------------------------------------------------------
    | STATUS
    |--------------------------------------------------------------------------
    */

    if (statusFilter) {

        statusFilter.addEventListener(
            'change',
            applyFilters
        );

    }


    /*
    |--------------------------------------------------------------------------
    | CLEAR SEARCH
    |--------------------------------------------------------------------------
    */

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


    /*
    |--------------------------------------------------------------------------
    | CLEAR FILTERS
    |--------------------------------------------------------------------------
    */

    if (clearFilters) {

        clearFilters.addEventListener(
            'click',
            function () {

                if (searchInput) {
                    searchInput.value = '';
                }

                if (statusFilter) {
                    statusFilter.value = 'all';
                }

                applyFilters();

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | INITIAL FILTER
    |--------------------------------------------------------------------------
    */

    applyFilters();

});


/*
|--------------------------------------------------------------------------
| ADD BRAND MODAL
|--------------------------------------------------------------------------
*/

function openBrandModal() {

    const modalElement =
        document.getElementById('brandModal');

    if (!modalElement) {
        return;
    }

    const form =
        modalElement.querySelector('form');

    if (form) {
        form.reset();
    }

    const modal =
        bootstrap.Modal.getOrCreateInstance(
            modalElement
        );

    modal.show();

}


/*
|--------------------------------------------------------------------------
| EDIT BRAND MODAL
|--------------------------------------------------------------------------
*/

function openEditBrandModal(brand) {

    const modalElement =
        document.getElementById('editBrandModal');

    if (!modalElement) {
        return;
    }


    document.getElementById(
        'editBrandId'
    ).value = brand.id;


    document.getElementById(
        'editBrandName'
    ).value = brand.name;


    const modal =
        bootstrap.Modal.getOrCreateInstance(
            modalElement
        );

    modal.show();

}


/*
|--------------------------------------------------------------------------
| TOGGLE BRAND MODAL
|--------------------------------------------------------------------------
*/

function openToggleBrandModal(brand) {

    const modalElement =
        document.getElementById('toggleBrandModal');

    if (!modalElement) {
        return;
    }


    const id =
        document.getElementById('toggleBrandId');

    const title =
        document.getElementById('toggleBrandTitle');

    const message =
        document.getElementById('toggleBrandMessage');

    const button =
        document.getElementById('toggleBrandButton');

    const icon =
        document.getElementById('toggleBrandIcon');


    id.value = brand.id;


    if (brand.status === 'active') {

        title.textContent =
            'Disable Brand';

        message.innerHTML =
            'Are you sure you want to disable ' +
            '<strong>' +
            escapeHtml(brand.name) +
            '</strong>? ' +
            'The brand will no longer be available ' +
            'for new product assignments.';

        button.className =
            'btn btn-warning fw-bold';

        button.innerHTML =
            '<i class="bi bi-slash-circle me-1"></i>' +
            'Disable Brand';

        icon.className =
            'bi bi-slash-circle text-warning me-2';

    } else {

        title.textContent =
            'Enable Brand';

        message.innerHTML =
            'Are you sure you want to enable ' +
            '<strong>' +
            escapeHtml(brand.name) +
            '</strong>? ' +
            'The brand will become available again.';

        button.className =
            'btn btn-success fw-bold';

        button.innerHTML =
            '<i class="bi bi-check-circle me-1"></i>' +
            'Enable Brand';

        icon.className =
            'bi bi-check-circle text-success me-2';

    }


    const modal =
        bootstrap.Modal.getOrCreateInstance(
            modalElement
        );

    modal.show();

}


/*
|--------------------------------------------------------------------------
| DELETE BRAND MODAL
|--------------------------------------------------------------------------
*/

function openDeleteBrandModal(brand) {

    const modalElement =
        document.getElementById('deleteBrandModal');

    if (!modalElement) {
        return;
    }


    document.getElementById(
        'deleteBrandId'
    ).value = brand.id;


    document.getElementById(
        'deleteBrandName'
    ).textContent = brand.name;


    const warning =
        document.getElementById(
            'deleteBrandWarning'
        );

    const deleteButton =
        document.getElementById(
            'deleteBrandButton'
        );


    if (brand.product_count > 0) {

        warning.classList.remove('d-none');

        deleteButton.disabled = true;

    } else {

        warning.classList.add('d-none');

        deleteButton.disabled = false;

    }


    const modal =
        bootstrap.Modal.getOrCreateInstance(
            modalElement
        );

    modal.show();

}


/*
|--------------------------------------------------------------------------
| HTML ESCAPE
|--------------------------------------------------------------------------
*/

function escapeHtml(value) {

    const div =
        document.createElement('div');

    div.textContent =
        value ?? '';

    return div.innerHTML;

}

</script>

</body>

</html>