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
   CREATE / UPDATE CATEGORY
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /* =====================================================
       CREATE CATEGORY
    ===================================================== */

    if ($action === 'create_category') {

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '') {

            $error = 'Category name is required.';

        } else {

            try {

                $pdo->beginTransaction();

                /* CHECK DUPLICATE */

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM inventory_categories
                    WHERE business_id = ?
                    AND LOWER(name) = LOWER(?)
                    LIMIT 1
                ");

                $stmt->execute([
                    $businessId,
                    $name
                ]);

                if ($stmt->fetch()) {
                    throw new Exception('A category with this name already exists.');
                }

                /* INSERT */

                $stmt = $pdo->prepare("
                    INSERT INTO inventory_categories (
                        business_id,
                        name,
                        description,
                        status,
                        created_by
                    )
                    VALUES (
                        ?, ?, ?, 'active', ?
                    )
                ");

                $stmt->execute([
                    $businessId,
                    $name,
                    $description !== '' ? $description : null,
                    $userId
                ]);

                $pdo->commit();

                $success = 'Category created successfully.';

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = $e->getMessage();
            }
        }
    }

    /* =====================================================
       UPDATE CATEGORY
    ===================================================== */

    elseif ($action === 'update_category') {

        $categoryId = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($categoryId <= 0) {

            $error = 'Invalid category.';

        } elseif ($name === '') {

            $error = 'Category name is required.';

        } else {

            try {

                $pdo->beginTransaction();

                /* CHECK CATEGORY EXISTS */

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM inventory_categories
                    WHERE id = ?
                    AND business_id = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $categoryId,
                    $businessId
                ]);

                if (!$stmt->fetch()) {
                    throw new Exception('Category not found.');
                }

                /* CHECK DUPLICATE */

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM inventory_categories
                    WHERE business_id = ?
                    AND LOWER(name) = LOWER(?)
                    AND id != ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $businessId,
                    $name,
                    $categoryId
                ]);

                if ($stmt->fetch()) {
                    throw new Exception('Another category with this name already exists.');
                }

                /* UPDATE */

                $stmt = $pdo->prepare("
                    UPDATE inventory_categories
                    SET
                        name = ?,
                        description = ?,
                        updated_at = NOW()
                    WHERE id = ?
                    AND business_id = ?
                ");

                $stmt->execute([
                    $name,
                    $description !== '' ? $description : null,
                    $categoryId,
                    $businessId
                ]);

                $pdo->commit();

                $success = 'Category updated successfully.';

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = $e->getMessage();
            }
        }
    }

    /* =====================================================
       ENABLE CATEGORY
    ===================================================== */

    elseif ($action === 'enable_category') {

        $categoryId = (int)($_POST['category_id'] ?? 0);

        if ($categoryId <= 0) {

            $error = 'Invalid category.';

        } else {

            try {

                $stmt = $pdo->prepare("
                    UPDATE inventory_categories
                    SET
                        status = 'active',
                        updated_at = NOW()
                    WHERE id = ?
                    AND business_id = ?
                ");

                $stmt->execute([
                    $categoryId,
                    $businessId
                ]);

                if ($stmt->rowCount() === 0) {
                    throw new Exception('Category not found.');
                }

                $success = 'Category enabled successfully.';

            } catch (Throwable $e) {

                $error = $e->getMessage();
            }
        }
    }

    /* =====================================================
       DISABLE CATEGORY
    ===================================================== */

    elseif ($action === 'disable_category') {

        $categoryId = (int)($_POST['category_id'] ?? 0);

        if ($categoryId <= 0) {

            $error = 'Invalid category.';

        } else {

            try {

                $stmt = $pdo->prepare("
                    UPDATE inventory_categories
                    SET
                        status = 'inactive',
                        updated_at = NOW()
                    WHERE id = ?
                    AND business_id = ?
                ");

                $stmt->execute([
                    $categoryId,
                    $businessId
                ]);

                if ($stmt->rowCount() === 0) {
                    throw new Exception('Category not found.');
                }

                $success = 'Category disabled successfully.';

            } catch (Throwable $e) {

                $error = $e->getMessage();
            }
        }
    }

    /* =====================================================
       DELETE CATEGORY
    ===================================================== */

    elseif ($action === 'delete_category') {

        $categoryId = (int)($_POST['category_id'] ?? 0);

        if ($categoryId <= 0) {

            $error = 'Invalid category.';

        } else {

            try {

                $pdo->beginTransaction();

                /* CHECK CATEGORY */

                $stmt = $pdo->prepare("
                    SELECT id, name
                    FROM inventory_categories
                    WHERE id = ?
                    AND business_id = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $categoryId,
                    $businessId
                ]);

                $category = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$category) {
                    throw new Exception('Category not found.');
                }

                /* CHECK PRODUCTS USING CATEGORY */

                $stmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM inventory_products
                    WHERE category_id = ?
                    AND business_id = ?
                ");

                $stmt->execute([
                    $categoryId,
                    $businessId
                ]);

                $productCount = (int)$stmt->fetchColumn();

                if ($productCount > 0) {

                    throw new Exception(
                        'This category cannot be deleted because it is being used by ' .
                        $productCount .
                        ' product' .
                        ($productCount === 1 ? '' : 's') .
                        '.'
                    );
                }

                /* DELETE */

                $stmt = $pdo->prepare("
                    DELETE FROM inventory_categories
                    WHERE id = ?
                    AND business_id = ?
                ");

                $stmt->execute([
                    $categoryId,
                    $businessId
                ]);

                $pdo->commit();

                $success = 'Category deleted successfully.';

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = $e->getMessage();
            }
        }
    }
}

/* =========================================================
   LOAD CATEGORIES
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        c.id,
        c.business_id,
        c.name,
        c.description,
        c.status,
        c.created_by,
        c.created_at,
        c.updated_at,
        COUNT(p.id) AS product_count
    FROM inventory_categories c
    LEFT JOIN inventory_products p
        ON p.category_id = c.id
        AND p.business_id = c.business_id
    WHERE c.business_id = ?
    GROUP BY
        c.id,
        c.business_id,
        c.name,
        c.description,
        c.status,
        c.created_by,
        c.created_at,
        c.updated_at
    ORDER BY c.id DESC
");

$stmt->execute([$businessId]);

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   SUMMARY
========================================================= */

$totalCategories = count($categories);
$activeCategories = 0;
$inactiveCategories = 0;
$categoriesWithProducts = 0;

foreach ($categories as $category) {

    if ($category['status'] === 'active') {
        $activeCategories++;
    } else {
        $inactiveCategories++;
    }

    if ((int)$category['product_count'] > 0) {
        $categoriesWithProducts++;
    }
}

$activePage = 'inventory_categories';
$pageTitle = 'Categories - Inventory';
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
   SUMMARY CARDS
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
   CATEGORY SECTION
========================================================= */

.category-section {
    border: 0;
    border-radius: 16px;
    overflow: hidden;
}

.category-section-header {
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

/* =========================================================
   TABLE
========================================================= */

.category-table {
    width: 100%;
}

.category-table th {
    font-size: .72rem;
    letter-spacing: .04em;
    white-space: nowrap;
}

.category-table td {
    vertical-align: middle;
    padding-top: 14px;
    padding-bottom: 14px;
}

.category-name {
    font-weight: 700;
}

.category-description {
    font-size: .75rem;
    color: var(--bs-secondary-color);
    margin-top: 2px;
    max-width: 400px;
}

/* =========================================================
   STATUS
========================================================= */

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

/* =========================================================
   MODALS
========================================================= */

.category-modal .modal-content {
    border: 0;
    border-radius: 16px;
}

.category-modal .modal-header {
    padding: 18px 22px;
}

.category-modal .modal-body {
    padding: 22px;
}

.category-modal .modal-footer {
    padding: 15px 22px;
}

/* =========================================================
   FORM
========================================================= */

.form-label {
    font-size: .78rem;
}

.form-control {
    border-radius: 9px;
}

.form-control:focus {
    box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .12);
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

    .inventory-header {
        margin-bottom: 16px !important;
    }

    .inventory-header h2 {
        font-size: 1.35rem;
    }

    .inventory-header p {
        font-size: .75rem;
    }

    .category-section {
        border-radius: 14px;
    }

    .category-section-header {
        padding: 15px;
    }

    .category-table th,
    .category-table td {
        font-size: .75rem;
    }

    .category-modal .modal-dialog {
        margin: 10px;
    }

    .category-modal .modal-content {
        border-radius: 15px;
    }

    .category-modal .modal-header {
        padding: 15px 16px;
    }

    .category-modal .modal-body {
        padding: 16px;
    }

    .category-modal .modal-footer {
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

            <!-- =================================================
                 HEADER
            ================================================== -->

            <div class="inventory-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

                <div>

                    <div class="d-flex align-items-center gap-2 mb-1">

                        <div class="d-lg-none bg-primary bg-opacity-10 text-primary rounded-3 p-2">

                            <i class="bi bi-tags"></i>

                        </div>

                        <h2 class="fw-bold text-body mb-0">
                            Categories
                        </h2>

                    </div>

                    <p class="text-muted small mb-0">

                        Manage product categories for

                        <span class="fw-semibold text-primary">
                            <?= htmlspecialchars($businessName) ?>
                        </span>

                    </p>

                </div>

                <button
                    type="button"
                    class="btn btn-primary fw-bold px-3 py-2 rounded-3 shadow-sm d-flex align-items-center gap-2"
                    onclick="openAddCategoryModal()"
                >

                    <i class="bi bi-plus-lg"></i>

                    <span>
                        Add Category
                    </span>

                </button>

            </div>


            <!-- =================================================
                 ALERTS
            ================================================== -->

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


            <!-- =================================================
                 SUMMARY
            ================================================== -->

            <div class="row g-3 mb-4">

                <!-- TOTAL -->

                <div class="col-12 col-md-4">

                    <div class="card summary-card shadow-sm h-100 bg-body">

                        <div class="card-body p-4">

                            <div class="d-flex justify-content-between align-items-start">

                                <div>

                                    <div class="text-muted small fw-semibold mb-2">
                                        Total Categories
                                    </div>

                                    <div class="fs-3 fw-bold">
                                        <?= number_format($totalCategories) ?>
                                    </div>

                                    <div class="small text-muted mt-1">
                                        All registered categories
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
                                        Active Categories
                                    </div>

                                    <div class="fs-3 fw-bold text-success">
                                        <?= number_format($activeCategories) ?>
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
                                        Inactive Categories
                                    </div>

                                    <div class="fs-3 fw-bold text-danger">
                                        <?= number_format($inactiveCategories) ?>
                                    </div>

                                    <div class="small text-muted mt-1">
                                        Currently disabled
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


            <!-- =================================================
                 SEARCH + FILTER
            ================================================== -->

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
                                    id="categorySearch"
                                    class="form-control"
                                    placeholder="Search category or description..."
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


                        <div class="col-12 col-lg-4">

                            <select
                                id="statusFilter"
                                class="form-select"
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

                            Showing <?= number_format($totalCategories) ?>
                            categories

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


            <!-- =================================================
                 CATEGORY TABLE
            ================================================== -->

            <div class="card category-section shadow-sm bg-body">

                <div class="category-section-header">

                    <h5 class="fw-bold mb-1">
                        Category List
                    </h5>

                    <p class="text-muted small mb-0">

                        <?= number_format($totalCategories) ?>

                        categor<?= $totalCategories === 1 ? 'y' : 'ies' ?>

                        registered

                    </p>

                </div>


                <?php if (!$categories): ?>

                    <div class="empty-state text-center text-muted">

                        <div class="mb-3">

                            <i class="bi bi-tags display-6 opacity-50"></i>

                        </div>

                        <div class="fw-semibold mb-1">
                            No categories found
                        </div>

                        <div class="small mb-3">
                            Create your first category to organize your products.
                        </div>

                        <button
                            type="button"
                            class="btn btn-primary btn-sm fw-bold"
                            onclick="openAddCategoryModal()"
                        >

                            <i class="bi bi-plus-lg me-1"></i>

                            Add Category

                        </button>

                    </div>

                <?php else: ?>

                    <div class="table-responsive">

                        <table class="category-table table table-hover align-middle mb-0">

                            <thead class="table-light text-uppercase text-muted">

                                <tr>

                                    <th class="ps-4 py-3">
                                        ID
                                    </th>

                                    <th class="py-3">
                                        Category
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


                            <tbody id="categoryTableBody">

                            <?php foreach ($categories as $category): ?>

                                <?php

                                $searchText = strtolower(
                                    ($category['name'] ?? '') . ' ' .
                                    ($category['description'] ?? '')
                                );

                                ?>

                                <tr
                                    class="category-row"
                                    data-search="<?= htmlspecialchars($searchText) ?>"
                                    data-status="<?= htmlspecialchars($category['status']) ?>"
                                >

                                    <!-- ID -->

                                    <td class="ps-4">

                                        <span class="text-muted small">

                                            #<?= (int)$category['id'] ?>

                                        </span>

                                    </td>


                                    <!-- CATEGORY -->

                                    <td>

                                        <div class="category-name">

                                            <?= htmlspecialchars(
                                                $category['name']
                                            ) ?>

                                        </div>

                                        <?php if (!empty($category['description'])): ?>

                                            <div class="category-description">

                                                <?= htmlspecialchars(
                                                    $category['description']
                                                ) ?>

                                            </div>

                                        <?php else: ?>

                                            <div class="category-description">
                                                No description
                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <!-- PRODUCTS -->

                                    <td>

                                        <?php if ((int)$category['product_count'] > 0): ?>

                                            <span class="badge text-bg-primary rounded-pill">

                                                <?= number_format(
                                                    (int)$category['product_count']
                                                ) ?>

                                                product<?= (int)$category['product_count'] === 1 ? '' : 's' ?>

                                            </span>

                                        <?php else: ?>

                                            <span class="text-muted small">
                                                No products
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <?php if ($category['status'] === 'active'): ?>

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

                                            <?= !empty($category['created_at'])
                                                ? date(
                                                    'M d, Y',
                                                    strtotime($category['created_at'])
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
                                                class="btn btn-sm btn-outline-secondary rounded-3"
                                                data-bs-toggle="dropdown"
                                                aria-expanded="false"
                                            >

                                                <i class="bi bi-three-dots-vertical"></i>

                                                Action

                                            </button>


                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">

                                                <!-- EDIT -->

                                                <li>

                                                    <button
                                                        type="button"
                                                        class="dropdown-item"
                                                        onclick='openEditCategoryModal(
                                                            <?= json_encode(
                                                                (int)$category['id']
                                                            ) ?>,
                                                            <?= json_encode(
                                                                $category['name']
                                                            ) ?>,
                                                            <?= json_encode(
                                                                $category['description'] ?? ''
                                                            ) ?>
                                                        )'
                                                    >

                                                        <i class="bi bi-pencil-square text-primary me-2"></i>

                                                        Edit Category

                                                    </button>

                                                </li>


                                                <li>
                                                    <hr class="dropdown-divider">
                                                </li>


                                                <?php if ($category['status'] === 'active'): ?>

                                                    <!-- DISABLE -->

                                                    <li>

                                                        <button
                                                            type="button"
                                                            class="dropdown-item"
                                                            onclick="openStatusModal(
                                                                <?= (int)$category['id'] ?>,
                                                                'disable',
                                                                <?= htmlspecialchars(
                                                                    json_encode($category['name']),
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                ) ?>
                                                            )"
                                                        >

                                                            <i class="bi bi-pause-circle text-warning me-2"></i>

                                                            Disable Category

                                                        </button>

                                                    </li>

                                                <?php else: ?>

                                                    <!-- ENABLE -->

                                                    <li>

                                                        <form method="POST">

                                                            <input
                                                                type="hidden"
                                                                name="action"
                                                                value="enable_category"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="category_id"
                                                                value="<?= (int)$category['id'] ?>"
                                                            >

                                                            <button
                                                                type="submit"
                                                                class="dropdown-item"
                                                            >

                                                                <i class="bi bi-check-circle text-success me-2"></i>

                                                                Enable Category

                                                            </button>

                                                        </form>

                                                    </li>

                                                <?php endif; ?>


                                                <!-- DELETE -->

                                                <li>

                                                    <button
                                                        type="button"
                                                        class="dropdown-item text-danger"
                                                        onclick="openStatusModal(
                                                            <?= (int)$category['id'] ?>,
                                                            'delete',
                                                            <?= htmlspecialchars(
                                                                json_encode($category['name']),
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>,
                                                            <?= (int)$category['product_count'] ?>
                                                        )"
                                                    >

                                                        <i class="bi bi-trash me-2"></i>

                                                        Delete Category

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


                    <!-- NO RESULTS -->

                    <div
                        id="noSearchResults"
                        class="empty-state text-center text-muted d-none"
                    >

                        <div class="mb-3">

                            <i class="bi bi-search display-6 opacity-50"></i>

                        </div>

                        <div class="fw-semibold mb-1">
                            No categories found
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
     ADD / EDIT CATEGORY MODAL
========================================================= -->

<div
    class="modal fade category-modal"
    id="categoryModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content shadow-lg">

            <div class="modal-header border-bottom">

                <h5
                    class="modal-title fw-bold"
                    id="categoryModalTitle"
                >

                    <i class="bi bi-tags text-primary me-2"></i>

                    Add Category

                </h5>

                <button
                    type="button"
                    class="btn-close shadow-none"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <form method="POST" id="categoryForm">

                <input
                    type="hidden"
                    name="action"
                    id="categoryAction"
                    value="create_category"
                >

                <input
                    type="hidden"
                    name="category_id"
                    id="categoryId"
                    value=""
                >


                <div class="modal-body">

                    <div class="mb-3">

                        <label class="form-label fw-semibold">

                            Category Name

                            <span class="text-danger">*</span>

                        </label>

                        <input
                            type="text"
                            name="name"
                            id="categoryName"
                            class="form-control"
                            required
                            maxlength="100"
                            placeholder="Enter category name"
                        >

                    </div>


                    <div class="mb-2">

                        <label class="form-label fw-semibold">
                            Description
                        </label>

                        <textarea
                            name="description"
                            id="categoryDescription"
                            class="form-control"
                            rows="4"
                            maxlength="500"
                            placeholder="Optional category description"
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
                        id="categorySaveButton"
                    >

                        <i class="bi bi-check-lg me-1"></i>

                        Save Category

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- =========================================================
     STATUS / DELETE CONFIRMATION MODAL
========================================================= -->

<div
    class="modal fade category-modal"
    id="statusModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content shadow-lg">

            <div class="modal-header border-bottom">

                <h5
                    class="modal-title fw-bold"
                    id="statusModalTitle"
                >

                    Confirm Action

                </h5>

                <button
                    type="button"
                    class="btn-close shadow-none"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <div class="modal-body">

                <div class="text-center py-2">

                    <div
                        id="statusModalIcon"
                        class="mb-3"
                    >

                        <i class="bi bi-question-circle display-5 text-primary"></i>

                    </div>


                    <h5
                        id="statusModalHeading"
                        class="fw-bold mb-2"
                    >
                        Confirm Action
                    </h5>


                    <p
                        id="statusModalMessage"
                        class="text-muted small mb-0"
                    >
                        Are you sure you want to continue?
                    </p>

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


                <form method="POST" id="statusForm">

                    <input
                        type="hidden"
                        name="action"
                        id="statusAction"
                    >

                    <input
                        type="hidden"
                        name="category_id"
                        id="statusCategoryId"
                    >

                    <button
                        type="submit"
                        id="statusConfirmButton"
                        class="btn btn-primary fw-bold"
                    >

                        Confirm

                    </button>

                </form>

            </div>

        </div>

    </div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


<script>

/* =========================================================
   ADD CATEGORY MODAL
========================================================= */

function openAddCategoryModal() {

    const modalElement =
        document.getElementById('categoryModal');

    const title =
        document.getElementById('categoryModalTitle');

    const action =
        document.getElementById('categoryAction');

    const categoryId =
        document.getElementById('categoryId');

    const name =
        document.getElementById('categoryName');

    const description =
        document.getElementById('categoryDescription');

    const saveButton =
        document.getElementById('categorySaveButton');


    title.innerHTML =
        '<i class="bi bi-tags text-primary me-2"></i> Add Category';

    action.value = 'create_category';

    categoryId.value = '';

    name.value = '';

    description.value = '';

    saveButton.innerHTML =
        '<i class="bi bi-check-lg me-1"></i> Save Category';


    const modal =
        bootstrap.Modal.getOrCreateInstance(
            modalElement
        );

    modal.show();

}


/* =========================================================
   EDIT CATEGORY MODAL
========================================================= */

function openEditCategoryModal(
    id,
    nameValue,
    descriptionValue
) {

    const modalElement =
        document.getElementById('categoryModal');

    const title =
        document.getElementById('categoryModalTitle');

    const action =
        document.getElementById('categoryAction');

    const categoryId =
        document.getElementById('categoryId');

    const name =
        document.getElementById('categoryName');

    const description =
        document.getElementById('categoryDescription');

    const saveButton =
        document.getElementById('categorySaveButton');


    title.innerHTML =
        '<i class="bi bi-pencil-square text-primary me-2"></i> Edit Category';

    action.value = 'update_category';

    categoryId.value = id;

    name.value = nameValue || '';

    description.value = descriptionValue || '';

    saveButton.innerHTML =
        '<i class="bi bi-check-lg me-1"></i> Update Category';


    const modal =
        bootstrap.Modal.getOrCreateInstance(
            modalElement
        );

    modal.show();

}


/* =========================================================
   STATUS MODAL
========================================================= */

function openStatusModal(
    categoryId,
    action,
    categoryName,
    productCount = 0
) {

    const modalElement =
        document.getElementById('statusModal');

    const title =
        document.getElementById('statusModalTitle');

    const heading =
        document.getElementById('statusModalHeading');

    const message =
        document.getElementById('statusModalMessage');

    const icon =
        document.getElementById('statusModalIcon');

    const statusAction =
        document.getElementById('statusAction');

    const statusCategoryId =
        document.getElementById('statusCategoryId');

    const confirmButton =
        document.getElementById('statusConfirmButton');


    statusAction.value =
        action === 'delete'
            ? 'delete_category'
            : 'disable_category';

    statusCategoryId.value = categoryId;


    /* =====================================================
       DISABLE
    ===================================================== */

    if (action === 'disable') {

        title.textContent =
            'Disable Category';

        heading.textContent =
            'Disable "' + categoryName + '"?';

        message.textContent =
            'This category will no longer be available when creating or editing products. Existing products will remain unchanged.';

        icon.innerHTML =
            '<i class="bi bi-pause-circle display-5 text-warning"></i>';

        confirmButton.className =
            'btn btn-warning fw-bold';

        confirmButton.innerHTML =
            '<i class="bi bi-pause-circle me-1"></i> Disable Category';

    }


    /* =====================================================
       DELETE
    ===================================================== */

    if (action === 'delete') {

        title.textContent =
            'Delete Category';

        heading.textContent =
            'Delete "' + categoryName + '"?';

        if (productCount > 0) {

            message.textContent =
                'This category is currently assigned to ' +
                productCount +
                ' product' +
                (productCount === 1 ? '' : 's') +
                '. It cannot be deleted until those products are reassigned.';

            confirmButton.disabled = true;

            confirmButton.className =
                'btn btn-danger fw-bold';

            confirmButton.innerHTML =
                '<i class="bi bi-lock me-1"></i> Cannot Delete';

        } else {

            message.textContent =
                'This action permanently deletes the category. This cannot be undone.';

            confirmButton.disabled = false;

            confirmButton.className =
                'btn btn-danger fw-bold';

            confirmButton.innerHTML =
                '<i class="bi bi-trash me-1"></i> Delete Category';

        }

        icon.innerHTML =
            '<i class="bi bi-trash3 display-5 text-danger"></i>';

    }


    const modal =
        bootstrap.Modal.getOrCreateInstance(
            modalElement
        );

    modal.show();

}


/* =========================================================
   SEARCH + FILTER
========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const searchInput =
            document.getElementById('categorySearch');

        const clearButton =
            document.getElementById('clearSearch');

        const statusFilter =
            document.getElementById('statusFilter');

        const clearFilters =
            document.getElementById('clearFilters');

        const rows =
            document.querySelectorAll('.category-row');

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


            /* CLEAR SEARCH BUTTON */

            if (clearButton) {

                if (searchTerm !== '') {

                    clearButton.classList.remove('d-none');

                } else {

                    clearButton.classList.add('d-none');

                }

            }


            /* RESULT TEXT */

            if (resultText) {

                resultText.textContent =
                    'Showing ' +
                    visibleCount.toLocaleString() +
                    ' categor' +
                    (visibleCount === 1 ? 'y' : 'ies');

            }


            /* NO RESULTS */

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


        /* SEARCH */

        if (searchInput) {

            searchInput.addEventListener(
                'input',
                applyFilters
            );

        }


        /* STATUS */

        if (statusFilter) {

            statusFilter.addEventListener(
                'change',
                applyFilters
            );

        }


        /* CLEAR SEARCH */

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


        /* CLEAR FILTERS */

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


        /* INITIAL */

        applyFilters();

    }
);

</script>

</body>

</html>