
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
   FILTERS
========================================================= */

$search = trim($_GET['search'] ?? '');
$productFilter = (int)($_GET['product_id'] ?? 0);
$movementFilter = trim($_GET['movement_type'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

/* =========================================================
   VALID MOVEMENT TYPES
========================================================= */

$movementTypes = [
    'opening_stock' => 'Opening Stock',
    'stock_in' => 'Stock In',
    'stock_out' => 'Stock Out',
    'adjustment' => 'Adjustment'
];

/* =========================================================
   LOAD PRODUCTS
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        sku,
        barcode,
        current_stock
    FROM inventory_products
    WHERE business_id = ?
    ORDER BY name ASC
");

$stmt->execute([$businessId]);

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   BUILD HISTORY QUERY
========================================================= */

$where = [
    'm.business_id = ?'
];

$params = [
    $businessId
];

/* =========================================================
   SEARCH
========================================================= */

if ($search !== '') {

    $where[] = "
        (
            p.name LIKE ?
            OR p.sku LIKE ?
            OR p.barcode LIKE ?
            OR m.notes LIKE ?
            OR m.reference_type LIKE ?
            OR CAST(m.reference_id AS CHAR) LIKE ?
            OR u.name LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

/* =========================================================
   PRODUCT FILTER
========================================================= */

if ($productFilter > 0) {

    $where[] = 'm.product_id = ?';

    $params[] = $productFilter;
}

/* =========================================================
   MOVEMENT FILTER
========================================================= */

if ($movementFilter !== '' && isset($movementTypes[$movementFilter])) {

    $where[] = 'm.movement_type = ?';

    $params[] = $movementFilter;
}

/* =========================================================
   DATE FROM
========================================================= */

if ($dateFrom !== '') {

    $where[] = 'DATE(m.created_at) >= ?';

    $params[] = $dateFrom;
}

/* =========================================================
   DATE TO
========================================================= */

if ($dateTo !== '') {

    $where[] = 'DATE(m.created_at) <= ?';

    $params[] = $dateTo;
}

/* =========================================================
   LOAD HISTORY
========================================================= */

$sql = "
    SELECT
        m.id,
        m.business_id,
        m.product_id,
        m.movement_type,
        m.quantity,
        m.unit_cost,
        m.previous_stock,
        m.new_stock,
        m.reference_type,
        m.reference_id,
        m.notes,
        m.created_by,
        m.created_at,

        p.name AS product_name,
        p.sku AS product_sku,
        p.barcode AS product_barcode,

        u.name AS created_by_name

    FROM inventory_stock_movements m

    INNER JOIN inventory_products p
        ON p.id = m.product_id
        AND p.business_id = m.business_id

    LEFT JOIN users u
        ON u.id = m.created_by

    WHERE " . implode(' AND ', $where) . "

    ORDER BY m.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   SUMMARY
========================================================= */

$totalMovements = count($movements);

$totalStockIn = 0;
$totalStockOut = 0;
$totalAdjustments = 0;
$totalOpening = 0;

foreach ($movements as $movement) {

    $quantity = (float)($movement['quantity'] ?? 0);

    switch ($movement['movement_type']) {

        case 'stock_in':
            $totalStockIn += $quantity;
            break;

        case 'stock_out':
            $totalStockOut += $quantity;
            break;

        case 'adjustment':
            $totalAdjustments += $quantity;
            break;

        case 'opening_stock':
            $totalOpening += $quantity;
            break;
    }
}

/* =========================================================
   PAGE
========================================================= */

$activePage = 'inventory_stock_history';
$pageTitle = 'Stock History - Inventory';
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

/* =========================================================
   GENERAL
========================================================= */

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
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 13px;
}

/* =========================================================
   FILTER CARD
========================================================= */

.filter-card {
    border: 0;
    border-radius: 16px;
}

.form-control,
.form-select {
    border-radius: 9px;
}

.form-control:focus,
.form-select:focus {
    box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .12);
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
   HISTORY CARD
========================================================= */

.history-section {
    border: 0;
    border-radius: 16px;
    overflow: hidden;
}

.history-header {
    padding: 20px 24px;
}

/* =========================================================
   TABLE
========================================================= */

.history-table {
    width: 100%;
}

.history-table th {
    font-size: .72rem;
    letter-spacing: .04em;
    white-space: nowrap;
}

.history-table td {
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
   MOVEMENT BADGES
========================================================= */

.movement-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: .35rem .65rem;
    border-radius: 50rem;
    font-size: .7rem;
    font-weight: 700;
}

.movement-opening {
    background: rgba(13, 110, 253, .12);
    color: var(--bs-primary);
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
    background: rgba(255, 193, 7, .15);
    color: #997404;
}

/* =========================================================
   STOCK VALUES
========================================================= */

.stock-before {
    color: var(--bs-secondary-color);
}

.stock-in {
    color: var(--bs-success);
    font-weight: 700;
}

.stock-out {
    color: var(--bs-danger);
    font-weight: 700;
}

.stock-adjustment {
    color: #997404;
    font-weight: 700;
}

.stock-after {
    font-weight: 700;
}

/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state {
    padding: 60px 20px;
}

/* =========================================================
   MODAL
========================================================= */

.history-modal .modal-content {
    border: 0;
    border-radius: 16px;
}

.history-modal .modal-header {
    padding: 18px 22px;
}

.history-modal .modal-body {
    padding: 22px;
}

.history-detail {
    padding: 12px 14px;
    border-radius: 10px;
    background: var(--bs-tertiary-bg);
}

.history-detail-label {
    font-size: .68rem;
    color: var(--bs-secondary-color);
    margin-bottom: 3px;
}

.history-detail-value {
    font-weight: 600;
    word-break: break-word;
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

    .summary-card .card-body {
        padding: 18px !important;
    }

    .history-header {
        padding: 15px;
    }

    .history-table th,
    .history-table td {
        font-size: .75rem;
    }

    .empty-state {
        padding: 45px 15px;
    }

    .history-modal .modal-dialog {
        margin: 10px;
    }

    .history-modal .modal-content {
        border-radius: 15px;
    }

    .history-modal .modal-header,
    .history-modal .modal-body {
        padding: 16px;
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

                            <i class="bi bi-clock-history"></i>

                        </div>

                        <h2 class="fw-bold text-body mb-0">
                            Stock History
                        </h2>

                    </div>

                    <p class="text-muted small mb-0">

                        View all inventory stock movements for

                        <span class="fw-semibold text-primary">
                            <?= htmlspecialchars($businessName) ?>
                        </span>

                    </p>

                </div>

            </div>


            <!-- =====================================================
                 SUMMARY
            ====================================================== -->

            <div class="row g-3 mb-4">

                <!-- TOTAL MOVEMENTS -->

                <div class="col-12 col-md-6 col-xl-3">

                    <div class="card summary-card shadow-sm h-100 bg-body">

                        <div class="card-body p-4">

                            <div class="d-flex justify-content-between align-items-start">

                                <div>

                                    <div class="text-muted small fw-semibold mb-2">
                                        Total Movements
                                    </div>

                                    <div class="fs-3 fw-bold">
                                        <?= number_format($totalMovements) ?>
                                    </div>

                                    <div class="small text-muted mt-1">
                                        Stock transactions
                                    </div>

                                </div>

                                <div class="summary-icon bg-primary bg-opacity-10 text-primary">

                                    <i class="bi bi-clock-history fs-4"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- STOCK IN -->

                <div class="col-12 col-md-6 col-xl-3">

                    <div class="card summary-card shadow-sm h-100 bg-body">

                        <div class="card-body p-4">

                            <div class="d-flex justify-content-between align-items-start">

                                <div>

                                    <div class="text-muted small fw-semibold mb-2">
                                        Stock In
                                    </div>

                                    <div class="fs-3 fw-bold text-success">
                                        <?= number_format($totalStockIn, 2) ?>
                                    </div>

                                    <div class="small text-muted mt-1">
                                        Units added
                                    </div>

                                </div>

                                <div class="summary-icon bg-success bg-opacity-10 text-success">

                                    <i class="bi bi-box-arrow-in-down fs-4"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- STOCK OUT -->

                <div class="col-12 col-md-6 col-xl-3">

                    <div class="card summary-card shadow-sm h-100 bg-body">

                        <div class="card-body p-4">

                            <div class="d-flex justify-content-between align-items-start">

                                <div>

                                    <div class="text-muted small fw-semibold mb-2">
                                        Stock Out
                                    </div>

                                    <div class="fs-3 fw-bold text-danger">
                                        <?= number_format($totalStockOut, 2) ?>
                                    </div>

                                    <div class="small text-muted mt-1">
                                        Units removed
                                    </div>

                                </div>

                                <div class="summary-icon bg-danger bg-opacity-10 text-danger">

                                    <i class="bi bi-box-arrow-up fs-4"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- ADJUSTMENTS -->

                <div class="col-12 col-md-6 col-xl-3">

                    <div class="card summary-card shadow-sm h-100 bg-body">

                        <div class="card-body p-4">

                            <div class="d-flex justify-content-between align-items-start">

                                <div>

                                    <div class="text-muted small fw-semibold mb-2">
                                        Adjustments
                                    </div>

                                    <div class="fs-3 fw-bold text-warning">
                                        <?= number_format($totalAdjustments, 2) ?>
                                    </div>

                                    <div class="small text-muted mt-1">
                                        Adjusted quantity
                                    </div>

                                </div>

                                <div class="summary-icon bg-warning bg-opacity-10 text-warning">

                                    <i class="bi bi-sliders fs-4"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- =====================================================
                 FILTERS
            ====================================================== -->

            <div class="card filter-card shadow-sm mb-4">

                <div class="card-body p-3 p-md-4">

                    <form
                        method="GET"
                        action="index.php"
                        id="historyFilterForm"
                    >

                        <input
                            type="hidden"
                            name="page"
                            value="inventory_stock_history"
                        >

                        <div class="row g-2">

                            <!-- SEARCH -->

                            <div class="col-12 col-lg-4">

                                <label class="form-label fw-semibold">
                                    Search
                                </label>

                                <div class="search-wrapper input-group">

                                    <span class="input-group-text text-muted">

                                        <i class="bi bi-search"></i>

                                    </span>

                                    <input
                                        type="text"
                                        name="search"
                                        class="form-control"
                                        placeholder="Product, SKU, barcode, notes..."
                                        value="<?= htmlspecialchars($search) ?>"
                                        autocomplete="off"
                                    >

                                </div>

                            </div>


                            <!-- PRODUCT -->

                            <div class="col-12 col-md-6 col-lg-2">

                                <label class="form-label fw-semibold">
                                    Product
                                </label>

                                <select
                                    name="product_id"
                                    class="form-select"
                                >

                                    <option value="0">
                                        All Products
                                    </option>

                                    <?php foreach ($products as $product): ?>

                                        <option
                                            value="<?= (int)$product['id'] ?>"
                                            <?= $productFilter === (int)$product['id'] ? 'selected' : '' ?>
                                        >

                                            <?= htmlspecialchars($product['name']) ?>

                                            <?php if (!empty($product['sku'])): ?>

                                                -
                                                <?= htmlspecialchars($product['sku']) ?>

                                            <?php endif; ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- MOVEMENT -->

                            <div class="col-12 col-md-6 col-lg-2">

                                <label class="form-label fw-semibold">
                                    Movement
                                </label>

                                <select
                                    name="movement_type"
                                    class="form-select"
                                >

                                    <option value="">
                                        All Movements
                                    </option>

                                    <?php foreach ($movementTypes as $value => $label): ?>

                                        <option
                                            value="<?= htmlspecialchars($value) ?>"
                                            <?= $movementFilter === $value ? 'selected' : '' ?>
                                        >

                                            <?= htmlspecialchars($label) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- DATE FROM -->

                            <div class="col-12 col-md-6 col-lg-2">

                                <label class="form-label fw-semibold">
                                    From
                                </label>

                                <input
                                    type="date"
                                    name="date_from"
                                    class="form-control"
                                    value="<?= htmlspecialchars($dateFrom) ?>"
                                >

                            </div>


                            <!-- DATE TO -->

                            <div class="col-12 col-md-6 col-lg-2">

                                <label class="form-label fw-semibold">
                                    To
                                </label>

                                <input
                                    type="date"
                                    name="date_to"
                                    class="form-control"
                                    value="<?= htmlspecialchars($dateTo) ?>"
                                >

                            </div>

                        </div>


                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-3">

                            <div class="small text-muted">

                                Showing

                                <span class="fw-semibold text-body">
                                    <?= number_format($totalMovements) ?>
                                </span>

                                movement<?= $totalMovements === 1 ? '' : 's' ?>

                            </div>

                            <div class="d-flex gap-2">

                                <a
                                    href="index.php?page=inventory_stock_history"
                                    class="btn btn-sm btn-outline-secondary rounded-3"
                                >

                                    <i class="bi bi-arrow-counterclockwise me-1"></i>

                                    Clear

                                </a>

                                <button
                                    type="submit"
                                    class="btn btn-sm btn-primary rounded-3 fw-semibold"
                                >

                                    <i class="bi bi-funnel me-1"></i>

                                    Apply Filters

                                </button>

                            </div>

                        </div>

                    </form>

                </div>

            </div>


            <!-- =====================================================
                 HISTORY TABLE
            ====================================================== -->

            <div class="card history-section shadow-sm bg-body">

                <div class="history-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">

                    <div>

                        <h5 class="fw-bold mb-1">
                            Stock Movement History
                        </h5>

                        <p class="text-muted small mb-0">
                            Complete record of inventory stock changes
                        </p>

                    </div>

                    <div class="small text-muted">

                        <i class="bi bi-database me-1"></i>

                        <?= number_format($totalMovements) ?>
                        record<?= $totalMovements === 1 ? '' : 's' ?>

                    </div>

                </div>


                <?php if (!$movements): ?>

                    <div class="empty-state text-center text-muted">

                        <div class="mb-3">

                            <i class="bi bi-clock-history display-5 opacity-50"></i>

                        </div>

                        <div class="fw-semibold mb-1">
                            No stock history found
                        </div>

                        <div class="small">
                            No inventory movement matches the selected filters.
                        </div>

                    </div>

                <?php else: ?>

                    <div class="table-responsive">

                        <table class="history-table table table-hover align-middle mb-0">

                            <thead class="table-light text-uppercase text-muted">

                                <tr>

                                    <th class="ps-4 py-3">
                                        ID
                                    </th>

                                    <th class="py-3">
                                        Date
                                    </th>

                                    <th class="py-3">
                                        Product
                                    </th>

                                    <th class="py-3">
                                        Movement
                                    </th>

                                    <th class="py-3">
                                        Previous
                                    </th>

                                    <th class="py-3">
                                        Quantity
                                    </th>

                                    <th class="py-3">
                                        New Stock
                                    </th>

                                    <th class="py-3">
                                        Reference
                                    </th>

                                    <th class="py-3">
                                        Created By
                                    </th>

                                    <th class="py-3 pe-4 text-end">
                                        Action
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php foreach ($movements as $movement): ?>

                                <?php

                                $movementType =
                                    $movement['movement_type'] ?? '';

                                $quantity =
                                    (float)($movement['quantity'] ?? 0);

                                $previousStock =
                                    (float)($movement['previous_stock'] ?? 0);

                                $newStock =
                                    (float)($movement['new_stock'] ?? 0);

                                switch ($movementType) {

                                    case 'stock_in':

                                        $movementLabel = 'Stock In';
                                        $movementClass = 'movement-in';
                                        $movementIcon = 'bi-box-arrow-in-down';
                                        $quantityClass = 'stock-in';
                                        $quantityPrefix = '+';

                                        break;

                                    case 'stock_out':

                                        $movementLabel = 'Stock Out';
                                        $movementClass = 'movement-out';
                                        $movementIcon = 'bi-box-arrow-up';
                                        $quantityClass = 'stock-out';
                                        $quantityPrefix = '-';

                                        break;

                                    case 'adjustment':

                                        $movementLabel = 'Adjustment';
                                        $movementClass = 'movement-adjustment';
                                        $movementIcon = 'bi-sliders';
                                        $quantityClass = 'stock-adjustment';
                                        $quantityPrefix = '';

                                        break;

                                    case 'opening_stock':

                                        $movementLabel = 'Opening Stock';
                                        $movementClass = 'movement-opening';
                                        $movementIcon = 'bi-box-seam';
                                        $quantityClass = 'stock-in';
                                        $quantityPrefix = '+';

                                        break;

                                    default:

                                        $movementLabel = ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $movementType
                                            )
                                        );

                                        $movementClass = 'movement-opening';
                                        $movementIcon = 'bi-arrow-repeat';
                                        $quantityClass = '';
                                        $quantityPrefix = '';

                                        break;
                                }

                                $reference = '';

                                if (!empty($movement['reference_type'])) {

                                    $reference =
                                        ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $movement['reference_type']
                                            )
                                        );

                                    if (!empty($movement['reference_id'])) {

                                        $reference .=
                                            ' #' .
                                            (int)$movement['reference_id'];
                                    }

                                } else {

                                    $reference = '-';
                                }

                                $dateDisplay = '-';

                                if (!empty($movement['created_at'])) {

                                    $timestamp =
                                        strtotime($movement['created_at']);

                                    if ($timestamp !== false) {

                                        $dateDisplay =
                                            date(
                                                'M d, Y h:i A',
                                                $timestamp
                                            );
                                    }
                                }

                                ?>

                                <tr>

                                    <!-- ID -->

                                    <td class="ps-4">

                                        <span class="text-muted small">
                                            #<?= (int)$movement['id'] ?>
                                        </span>

                                    </td>


                                    <!-- DATE -->

                                    <td>

                                        <span class="small">
                                            <?= htmlspecialchars($dateDisplay) ?>
                                        </span>

                                    </td>


                                    <!-- PRODUCT -->

                                    <td>

                                        <div class="product-name">

                                            <?= htmlspecialchars(
                                                $movement['product_name']
                                            ) ?>

                                        </div>

                                        <?php if (!empty($movement['product_sku'])): ?>

                                            <div class="product-subtext">

                                                SKU:
                                                <?= htmlspecialchars(
                                                    $movement['product_sku']
                                                ) ?>

                                            </div>

                                        <?php elseif (!empty($movement['product_barcode'])): ?>

                                            <div class="product-subtext">

                                                <i class="bi bi-upc-scan me-1"></i>

                                                <?= htmlspecialchars(
                                                    $movement['product_barcode']
                                                ) ?>

                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <!-- MOVEMENT -->

                                    <td>

                                        <span class="movement-badge <?= $movementClass ?>">

                                            <i class="bi <?= $movementIcon ?>"></i>

                                            <?= htmlspecialchars($movementLabel) ?>

                                        </span>

                                    </td>


                                    <!-- PREVIOUS -->

                                    <td>

                                        <span class="stock-before">

                                            <?= number_format(
                                                $previousStock,
                                                2
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- QUANTITY -->

                                    <td>

                                        <span class="<?= $quantityClass ?>">

                                            <?= htmlspecialchars($quantityPrefix) ?>

                                            <?= number_format(
                                                abs($quantity),
                                                2
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- NEW STOCK -->

                                    <td>

                                        <span class="stock-after">

                                            <?= number_format(
                                                $newStock,
                                                2
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- REFERENCE -->

                                    <td>

                                        <span class="small">

                                            <?= htmlspecialchars($reference) ?>

                                        </span>

                                    </td>


                                    <!-- CREATED BY -->

                                    <td>

                                        <span class="small">

                                            <?= htmlspecialchars(
                                                $movement['created_by_name']
                                                ?? 'System'
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- ACTION -->

                                    <td class="text-end pe-4">

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary rounded-3"
                                            onclick="showMovementDetails(
                                                <?= htmlspecialchars(
                                                    json_encode(
                                                        $movement,
                                                        JSON_HEX_TAG |
                                                        JSON_HEX_APOS |
                                                        JSON_HEX_QUOT |
                                                        JSON_HEX_AMP
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            )"
                                            title="View Movement Details"
                                        >

                                            <i class="bi bi-eye me-1"></i>

                                            Details

                                        </button>

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
     MOVEMENT DETAILS MODAL
========================================================= -->

<div
    class="modal fade history-modal"
    id="movementDetailsModal"
    tabindex="-1"
    aria-labelledby="movementDetailsModalLabel"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content shadow-lg">

            <div class="modal-header border-bottom">

                <div>

                    <h5
                        class="modal-title fw-bold"
                        id="movementDetailsModalLabel"
                    >

                        <i class="bi bi-clock-history text-primary me-2"></i>

                        Stock Movement Details

                    </h5>

                    <div
                        class="small text-muted mt-1"
                        id="movementModalSubtitle"
                    ></div>

                </div>

                <button
                    type="button"
                    class="btn-close shadow-none"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>

            </div>


            <div class="modal-body">

                <!-- MOVEMENT BADGE -->

                <div
                    class="mb-4"
                    id="movementModalType"
                ></div>


                <!-- DETAILS -->

                <div class="row g-3">

                    <div class="col-12">

                        <div class="history-detail">

                            <div class="history-detail-label">
                                Product
                            </div>

                            <div
                                class="history-detail-value"
                                id="detailProduct"
                            >
                                -
                            </div>

                        </div>

                    </div>


                    <div class="col-12 col-md-4">

                        <div class="history-detail">

                            <div class="history-detail-label">
                                Previous Stock
                            </div>

                            <div
                                class="history-detail-value"
                                id="detailPreviousStock"
                            >
                                -
                            </div>

                        </div>

                    </div>


                    <div class="col-12 col-md-4">

                        <div class="history-detail">

                            <div class="history-detail-label">
                                Quantity
                            </div>

                            <div
                                class="history-detail-value"
                                id="detailQuantity"
                            >
                                -
                            </div>

                        </div>

                    </div>


                    <div class="col-12 col-md-4">

                        <div class="history-detail">

                            <div class="history-detail-label">
                                New Stock
                            </div>

                            <div
                                class="history-detail-value"
                                id="detailNewStock"
                            >
                                -
                            </div>

                        </div>

                    </div>


                    <div class="col-12 col-md-6">

                        <div class="history-detail">

                            <div class="history-detail-label">
                                SKU
                            </div>

                            <div
                                class="history-detail-value"
                                id="detailSku"
                            >
                                -
                            </div>

                        </div>

                    </div>


                    <div class="col-12 col-md-6">

                        <div class="history-detail">

                            <div class="history-detail-label">
                                Barcode
                            </div>

                            <div
                                class="history-detail-value"
                                id="detailBarcode"
                            >
                                -
                            </div>

                        </div>

                    </div>


                    <div class="col-12 col-md-6">

                        <div class="history-detail">

                            <div class="history-detail-label">
                                Reference
                            </div>

                            <div
                                class="history-detail-value"
                                id="detailReference"
                            >
                                -
                            </div>

                        </div>

                    </div>


                    <div class="col-12 col-md-6">

                        <div class="history-detail">

                            <div class="history-detail-label">
                                Unit Cost
                            </div>

                            <div
                                class="history-detail-value"
                                id="detailUnitCost"
                            >
                                -
                            </div>

                        </div>

                    </div>


                    <div class="col-12 col-md-6">

                        <div class="history-detail">

                            <div class="history-detail-label">
                                Created By
                            </div>

                            <div
                                class="history-detail-value"
                                id="detailCreatedBy"
                            >
                                -
                            </div>

                        </div>

                    </div>


                    <div class="col-12 col-md-6">

                        <div class="history-detail">

                            <div class="history-detail-label">
                                Date & Time
                            </div>

                            <div
                                class="history-detail-value"
                                id="detailCreatedAt"
                            >
                                -
                            </div>

                        </div>

                    </div>


                    <div class="col-12">

                        <div class="history-detail">

                            <div class="history-detail-label">
                                Notes / Reason
                            </div>

                            <div
                                class="history-detail-value"
                                id="detailNotes"
                            >
                                -
                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <div class="modal-footer border-top">

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

/* =========================================================
   ESCAPE HTML
========================================================= */

function escapeHtml(value) {

    if (value === null || value === undefined) {
        return '';
    }

    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}


/* =========================================================
   FORMAT NUMBER
========================================================= */

function formatNumber(value) {

    const number = parseFloat(value || 0);

    return number.toLocaleString(
        undefined,
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }
    );
}


/* =========================================================
   SHOW MOVEMENT DETAILS
========================================================= */

function showMovementDetails(movement) {

    if (!movement) {
        return;
    }

    const type =
        movement.movement_type || '';

    let label = 'Movement';
    let badgeClass = 'movement-opening';
    let icon = 'bi-arrow-repeat';
    let prefix = '';

    switch (type) {

        case 'stock_in':

            label = 'Stock In';
            badgeClass = 'movement-in';
            icon = 'bi-box-arrow-in-down';
            prefix = '+';

            break;

        case 'stock_out':

            label = 'Stock Out';
            badgeClass = 'movement-out';
            icon = 'bi-box-arrow-up';
            prefix = '-';

            break;

        case 'adjustment':

            label = 'Adjustment';
            badgeClass = 'movement-adjustment';
            icon = 'bi-sliders';

            break;

        case 'opening_stock':

            label = 'Opening Stock';
            badgeClass = 'movement-opening';
            icon = 'bi-box-seam';
            prefix = '+';

            break;
    }


    /* =====================================================
       MOVEMENT TYPE
    ===================================================== */

    document.getElementById('movementModalType').innerHTML =
        '<span class="movement-badge ' +
        badgeClass +
        '">' +
        '<i class="bi ' +
        icon +
        '"></i> ' +
        escapeHtml(label) +
        '</span>';


    /* =====================================================
       SUBTITLE
    ===================================================== */

    document.getElementById('movementModalSubtitle').textContent =
        'Movement #' +
        (movement.id || '');


    /* =====================================================
       PRODUCT
    ===================================================== */

    let productText =
        movement.product_name || '-';

    if (movement.product_sku) {

        productText +=
            ' • SKU: ' +
            movement.product_sku;
    }

    document.getElementById('detailProduct').textContent =
        productText;


    /* =====================================================
       STOCK VALUES
    ===================================================== */

    document.getElementById('detailPreviousStock').textContent =
        formatNumber(movement.previous_stock);


    let quantity =
        Math.abs(parseFloat(movement.quantity || 0));

    document.getElementById('detailQuantity').textContent =
        prefix +
        formatNumber(quantity);


    document.getElementById('detailNewStock').textContent =
        formatNumber(movement.new_stock);


    /* =====================================================
       SKU
    ===================================================== */

    document.getElementById('detailSku').textContent =
        movement.product_sku || '-';


    /* =====================================================
       BARCODE
    ===================================================== */

    document.getElementById('detailBarcode').textContent =
        movement.product_barcode || '-';


    /* =====================================================
       REFERENCE
    ===================================================== */

    let reference = '-';

    if (movement.reference_type) {

        reference =
            String(movement.reference_type)
                .replace(/_/g, ' ')
                .replace(/\b\w/g, function (char) {
                    return char.toUpperCase();
                });

        if (movement.reference_id) {

            reference +=
                ' #' +
                movement.reference_id;
        }
    }

    document.getElementById('detailReference').textContent =
        reference;


    /* =====================================================
       UNIT COST
    ===================================================== */

    if (
        movement.unit_cost !== null &&
        movement.unit_cost !== undefined &&
        movement.unit_cost !== ''
    ) {

        document.getElementById('detailUnitCost').textContent =
            '₱' +
            formatNumber(movement.unit_cost);

    } else {

        document.getElementById('detailUnitCost').textContent =
            '-';
    }


    /* =====================================================
       CREATED BY
    ===================================================== */

    document.getElementById('detailCreatedBy').textContent =
        movement.created_by_name ||
        'System';


    /* =====================================================
       DATE
    ===================================================== */

    let createdAt =
        movement.created_at || '-';

    if (createdAt !== '-') {

        const date =
            new Date(
                createdAt.replace(' ', 'T')
            );

        if (!isNaN(date.getTime())) {

            createdAt =
                date.toLocaleString(
                    undefined,
                    {
                        year: 'numeric',
                        month: 'short',
                        day: '2-digit',
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true
                    }
                );
        }
    }

    document.getElementById('detailCreatedAt').textContent =
        createdAt;


    /* =====================================================
       NOTES
    ===================================================== */

    document.getElementById('detailNotes').textContent =
        movement.notes || 'No notes provided.';


    /* =====================================================
       SHOW MODAL
    ===================================================== */

    const modalElement =
        document.getElementById(
            'movementDetailsModal'
        );

    const modal =
        bootstrap.Modal.getOrCreateInstance(
            modalElement
        );

    modal.show();

}

</script>

</body>

</html>

