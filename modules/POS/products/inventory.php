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

$activePage = 'inventory_dashboard';
$pageTitle = 'Inventory Dashboard';

/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$totalProducts = 0;
$totalStock = 0;
$lowStockProducts = 0;
$outOfStockProducts = 0;
$stockInToday = 0;
$stockOutToday = 0;

$lowStockList = [];
$recentMovements = [];

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function inventoryTableExists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
            AND table_name = ?
        ");

        $stmt->execute([$table]);

        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/*
|--------------------------------------------------------------------------
| PRODUCTS SUMMARY
|--------------------------------------------------------------------------
*/

try {

    if (inventoryTableExists($pdo, 'pos_products')) {

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT:
        | business_id and created_by are included.
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total_products,
                COALESCE(SUM(current_stock), 0) AS total_stock,
                COALESCE(
                    SUM(
                        CASE
                            WHEN current_stock <= 0 THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS out_of_stock,
                COALESCE(
                    SUM(
                        CASE
                            WHEN current_stock > 0
                            AND current_stock <= minimum_stock
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS low_stock
            FROM pos_products
            WHERE business_id = ?
            AND created_by = ?
            AND status = 'active'
        ");

        $stmt->execute([$businessId, $userId]);

        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($summary) {

            $totalProducts = (int)($summary['total_products'] ?? 0);

            $totalStock = (float)($summary['total_stock'] ?? 0);

            $lowStockProducts = (int)($summary['low_stock'] ?? 0);

            $outOfStockProducts = (int)($summary['out_of_stock'] ?? 0);
        }
    }

} catch (Throwable $e) {

    $totalProducts = 0;
    $totalStock = 0;
    $lowStockProducts = 0;
    $outOfStockProducts = 0;
}

/*
|--------------------------------------------------------------------------
| TODAY'S STOCK MOVEMENTS
|--------------------------------------------------------------------------
*/

try {

    if (inventoryTableExists($pdo, 'pos_stock_movements')) {

        $stmt = $pdo->prepare("
            SELECT
                COALESCE(
                    SUM(
                        CASE
                            WHEN movement_type IN (
                                'opening_stock',
                                'purchase',
                                'return_in',
                                'adjustment_in'
                            )
                            THEN quantity
                            ELSE 0
                        END
                    ),
                    0
                ) AS stock_in,

                COALESCE(
                    SUM(
                        CASE
                            WHEN movement_type IN (
                                'sale',
                                'return_out',
                                'adjustment_out'
                            )
                            THEN quantity
                            ELSE 0
                        END
                    ),
                    0
                ) AS stock_out

            FROM pos_stock_movements sm
            INNER JOIN pos_products p ON p.id = sm.product_id AND p.business_id = sm.business_id
            WHERE sm.business_id = ?
            AND p.created_by = ?
            AND DATE(sm.created_at) = CURDATE()
        ");

        $stmt->execute([$businessId, $userId]);

        $movementSummary =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if ($movementSummary) {

            $stockInToday =
                (float)(
                    $movementSummary['stock_in'] ?? 0
                );

            $stockOutToday =
                (float)(
                    $movementSummary['stock_out'] ?? 0
                );
        }
    }

} catch (Throwable $e) {

    $stockInToday = 0;
    $stockOutToday = 0;
}

/*
|--------------------------------------------------------------------------
| LOW STOCK PRODUCTS
|--------------------------------------------------------------------------
*/

try {

    if (inventoryTableExists($pdo, 'pos_products')) {

        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                sku,
                current_stock,
                minimum_stock,
                maximum_stock
            FROM pos_products
            WHERE business_id = ?
            AND created_by = ?
            AND status = 'active'
            AND current_stock <= minimum_stock
            ORDER BY
                current_stock ASC,
                name ASC
            LIMIT 8
        ");

        $stmt->execute([$businessId, $userId]);

        $lowStockList =
            $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Throwable $e) {

    $lowStockList = [];
}

/*
|--------------------------------------------------------------------------
| RECENT STOCK MOVEMENTS
|--------------------------------------------------------------------------
*/

try {

    if (
        inventoryTableExists(
            $pdo,
            'pos_stock_movements'
        )
        &&
        inventoryTableExists(
            $pdo,
            'pos_products'
        )
    ) {

        $stmt = $pdo->prepare("
            SELECT
                sm.id,
                sm.product_id,
                sm.movement_type,
                sm.quantity,
                sm.unit_cost,
                sm.previous_stock,
                sm.new_stock,
                sm.reference_type,
                sm.reference_id,
                sm.notes,
                sm.created_at,
                p.name AS product_name,
                p.sku
            FROM pos_stock_movements sm

            INNER JOIN pos_products p
                ON p.id = sm.product_id
                AND p.business_id = sm.business_id

            WHERE sm.business_id = ?
            AND p.created_by = ?

            ORDER BY sm.created_at DESC

            LIMIT 10
        ");

        $stmt->execute([$businessId, $userId]);

        $recentMovements =
            $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Throwable $e) {

    $recentMovements = [];
}

/*
|--------------------------------------------------------------------------
| MOVEMENT LABEL
|--------------------------------------------------------------------------
*/

function inventoryMovementLabel(string $type): string
{
    return match ($type) {

        'opening_stock' => 'Opening Stock',

        'purchase' => 'Purchase',

        'sale' => 'Sale',

        'return_in' => 'Return In',

        'return_out' => 'Return Out',

        'adjustment_in' => 'Adjustment In',

        'adjustment_out' => 'Adjustment Out',

        default => ucwords(
            str_replace(
                '_',
                ' ',
                $type
            )
        )
    };
}

/*
|--------------------------------------------------------------------------
| MOVEMENT TYPE CLASS
|--------------------------------------------------------------------------
*/

function inventoryMovementClass(string $type): string
{
    return match ($type) {

        'opening_stock',
        'purchase',
        'return_in',
        'adjustment_in'
            => 'movement-in',

        'sale',
        'return_out',
        'adjustment_out'
            => 'movement-out',

        default
            => 'movement-neutral'
    };
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

        .inventory-header h2 {
            font-size: 1.5rem;
        }

        /*
        |--------------------------------------------------------------------------
        | STAT CARDS
        |--------------------------------------------------------------------------
        */

        .stat-card {
            border: 0;
            border-radius: 16px;
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 13px;

            display: flex;
            align-items: center;
            justify-content: center;

            flex-shrink: 0;
        }

        /*
        |--------------------------------------------------------------------------
        | CONTENT CARDS
        |--------------------------------------------------------------------------
        */

        .inventory-card {
            border: 0;
            border-radius: 16px;
        }

        .inventory-card-header {
            padding: 18px 20px;
            border-bottom: 1px solid var(--bs-border-color);
        }

        /*
        |--------------------------------------------------------------------------
        | QUICK ACTIONS
        |--------------------------------------------------------------------------
        */

        .quick-action {
            display: flex;
            align-items: center;
            gap: 12px;

            width: 100%;
            padding: 14px;

            border: 1px solid var(--bs-border-color);
            border-radius: 12px;

            text-decoration: none;
            color: var(--bs-body-color);

            transition: all .15s ease;
        }

        .quick-action:hover {
            border-color: var(--bs-primary);
            background: var(--bs-primary-bg-subtle);
            color: var(--bs-primary);
            transform: translateY(-1px);
        }

        .quick-action-icon {
            width: 42px;
            height: 42px;

            border-radius: 10px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: var(--bs-primary-bg-subtle);
            color: var(--bs-primary);

            flex-shrink: 0;
        }

        /*
        |--------------------------------------------------------------------------
        | TABLE
        |--------------------------------------------------------------------------
        */

        .inventory-table th {
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--bs-secondary-color);
            white-space: nowrap;
        }

        .inventory-table td {
            vertical-align: middle;
        }

        /*
        |--------------------------------------------------------------------------
        | MOVEMENTS
        |--------------------------------------------------------------------------
        */

        .movement-badge {
            display: inline-flex;
            align-items: center;

            padding: .35rem .65rem;

            border-radius: 50rem;

            font-size: .68rem;
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

        .movement-neutral {
            background: var(--bs-secondary-bg);
            color: var(--bs-secondary-color);
        }

        /*
        |--------------------------------------------------------------------------
        | STOCK
        |--------------------------------------------------------------------------
        */

        .stock-danger {
            color: var(--bs-danger);
            font-weight: 700;
        }

        .stock-warning {
            color: var(--bs-warning-text);
            font-weight: 700;
        }

        .stock-good {
            color: var(--bs-success);
            font-weight: 700;
        }

        /*
        |--------------------------------------------------------------------------
        | EMPTY
        |--------------------------------------------------------------------------
        */

        .dashboard-empty {
            padding: 35px 20px;
        }

        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 575.98px) {

            .inventory-header h2 {
                font-size: 1.3rem;
            }

            .stat-card {
                border-radius: 14px;
            }

            .inventory-card {
                border-radius: 14px;
            }

        }

    </style>

</head>

<body class="bg-body-tertiary">

<div class="d-flex flex-column flex-lg-row min-vh-100">

    <!-- =====================================================
         INVENTORY SIDEBAR
    ====================================================== -->

    <?php

    $sidebarPath =
        __DIR__ .
        '/../../../resources/partials/POSSidebar.php';

    if (file_exists($sidebarPath)) {
        include $sidebarPath;
    }

    ?>

    <!-- =====================================================
         MAIN
    ====================================================== -->

    <main class="inventory-main flex-grow-1 bg-body-tertiary">

        <div class="p-3 p-md-4">

            <!-- =================================================
                 HEADER
            ================================================== -->

            <div
                class="inventory-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4"
            >

                <div>

                    <div class="d-flex align-items-center gap-2 mb-1">

                        <div
                            class="d-lg-none bg-primary bg-opacity-10 text-primary rounded-3 p-2"
                        >

                            <i class="bi bi-boxes"></i>

                        </div>

                        <h2 class="fw-bold text-body mb-0">

                            Inventory Dashboard

                        </h2>

                    </div>

                    <p class="text-muted small mb-0">

                        Manage and monitor inventory for

                        <span class="fw-semibold text-primary">

                            <?= htmlspecialchars($businessName) ?>

                        </span>

                    </p>

                </div>

                <div class="d-flex gap-2 flex-wrap">

                    <a
                        href="index.php?page=inventory_products"
                        class="btn btn-outline-primary rounded-3 fw-semibold"
                    >

                        <i class="bi bi-box-seam me-1"></i>

                        Products

                    </a>

                    <a
                        href="index.php?page=inventory_stock"
                        class="btn btn-primary rounded-3 fw-bold"
                    >

                        <i class="bi bi-plus-circle me-1"></i>

                        Stock In

                    </a>

                </div>

            </div>

            <!-- =================================================
                 STATISTICS
            ================================================== -->

            <div class="row g-3 mb-4">

                <!-- PRODUCTS -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="card stat-card shadow-sm h-100 bg-body">

                        <div class="card-body p-4">

                            <div
                                class="d-flex justify-content-between align-items-start"
                            >

                                <div>

                                    <div
                                        class="text-muted small fw-semibold mb-2"
                                    >
                                        Total Products
                                    </div>

                                    <div class="fs-3 fw-bold">

                                        <?= number_format(
                                            $totalProducts
                                        ) ?>

                                    </div>

                                    <div class="small text-muted mt-1">

                                        Active products

                                    </div>

                                </div>

                                <div
                                    class="stat-icon bg-primary bg-opacity-10 text-primary"
                                >

                                    <i class="bi bi-box-seam fs-4"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- TOTAL STOCK -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="card stat-card shadow-sm h-100 bg-body">

                        <div class="card-body p-4">

                            <div
                                class="d-flex justify-content-between align-items-start"
                            >

                                <div>

                                    <div
                                        class="text-muted small fw-semibold mb-2"
                                    >
                                        Current Stock
                                    </div>

                                    <div class="fs-3 fw-bold">

                                        <?= number_format(
                                            $totalStock,
                                            2
                                        ) ?>

                                    </div>

                                    <div class="small text-muted mt-1">

                                        Total available units

                                    </div>

                                </div>

                                <div
                                    class="stat-icon bg-info bg-opacity-10 text-info"
                                >

                                    <i class="bi bi-boxes fs-4"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- LOW STOCK -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="card stat-card shadow-sm h-100 bg-body">

                        <div class="card-body p-4">

                            <div
                                class="d-flex justify-content-between align-items-start"
                            >

                                <div>

                                    <div
                                        class="text-muted small fw-semibold mb-2"
                                    >
                                        Low Stock
                                    </div>

                                    <div class="fs-3 fw-bold text-warning">

                                        <?= number_format(
                                            $lowStockProducts
                                        ) ?>

                                    </div>

                                    <div class="small text-muted mt-1">

                                        Need attention

                                    </div>

                                </div>

                                <div
                                    class="stat-icon bg-warning bg-opacity-10 text-warning"
                                >

                                    <i class="bi bi-exclamation-triangle fs-4"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- OUT OF STOCK -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="card stat-card shadow-sm h-100 bg-body">

                        <div class="card-body p-4">

                            <div
                                class="d-flex justify-content-between align-items-start"
                            >

                                <div>

                                    <div
                                        class="text-muted small fw-semibold mb-2"
                                    >
                                        Out of Stock
                                    </div>

                                    <div class="fs-3 fw-bold text-danger">

                                        <?= number_format(
                                            $outOfStockProducts
                                        ) ?>

                                    </div>

                                    <div class="small text-muted mt-1">

                                        Products unavailable

                                    </div>

                                </div>

                                <div
                                    class="stat-icon bg-danger bg-opacity-10 text-danger"
                                >

                                    <i class="bi bi-x-circle fs-4"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <!-- =================================================
                 TODAY'S MOVEMENT SUMMARY
            ================================================== -->

            <div class="row g-3 mb-4">

                <div class="col-12 col-md-6">

                    <div class="card inventory-card shadow-sm bg-body">

                        <div class="card-body p-4">

                            <div class="d-flex align-items-center gap-3">

                                <div
                                    class="stat-icon bg-success bg-opacity-10 text-success"
                                >

                                    <i class="bi bi-arrow-down-circle fs-4"></i>

                                </div>

                                <div>

                                    <div class="text-muted small fw-semibold">

                                        Stock In Today

                                    </div>

                                    <div class="fs-4 fw-bold text-success">

                                        +<?= number_format(
                                            $stockInToday,
                                            2
                                        ) ?>

                                    </div>

                                    <div class="text-muted small">

                                        Purchases, returns and adjustments

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="col-12 col-md-6">

                    <div class="card inventory-card shadow-sm bg-body">

                        <div class="card-body p-4">

                            <div class="d-flex align-items-center gap-3">

                                <div
                                    class="stat-icon bg-danger bg-opacity-10 text-danger"
                                >

                                    <i class="bi bi-arrow-up-circle fs-4"></i>

                                </div>

                                <div>

                                    <div class="text-muted small fw-semibold">

                                        Stock Out Today

                                    </div>

                                    <div class="fs-4 fw-bold text-danger">

                                        -<?= number_format(
                                            $stockOutToday,
                                            2
                                        ) ?>

                                    </div>

                                    <div class="text-muted small">

                                        Sales, returns and adjustments

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <!-- =================================================
                 QUICK ACTIONS
            ================================================== -->

            <div class="card inventory-card shadow-sm bg-body mb-4">

                <div class="inventory-card-header">

                    <h5 class="fw-bold mb-1">

                        Quick Actions

                    </h5>

                    <p class="text-muted small mb-0">

                        Frequently used inventory functions

                    </p>

                </div>

                <div class="card-body p-3">

                    <div class="row g-3">

                        <div class="col-12 col-sm-6 col-lg-3">

                            <a
                                href="index.php?page=inventory_products"
                                class="quick-action"
                            >

                                <div class="quick-action-icon">

                                    <i class="bi bi-box-seam fs-5"></i>

                                </div>

                                <div>

                                    <div class="fw-bold small">

                                        Products

                                    </div>

                                    <div
                                        class="text-muted"
                                        style="font-size:.7rem;"
                                    >

                                        Manage products

                                    </div>

                                </div>

                            </a>

                        </div>

                        <div class="col-12 col-sm-6 col-lg-3">

                            <a
                                href="index.php?page=inventory_stock"
                                class="quick-action"
                            >

                                <div class="quick-action-icon">

                                    <i class="bi bi-box-arrow-in-down fs-5"></i>

                                </div>

                                <div>

                                    <div class="fw-bold small">

                                        Stock In

                                    </div>

                                    <div
                                        class="text-muted"
                                        style="font-size:.7rem;"
                                    >

                                        Add inventory

                                    </div>

                                </div>

                            </a>

                        </div>

                        <div class="col-12 col-sm-6 col-lg-3">

                            <a
                                href="index.php?page=inventory_movements"
                                class="quick-action"
                            >

                                <div class="quick-action-icon">

                                    <i class="bi bi-clock-history fs-5"></i>

                                </div>

                                <div>

                                    <div class="fw-bold small">

                                        Stock Movements

                                    </div>

                                    <div
                                        class="text-muted"
                                        style="font-size:.7rem;"
                                    >

                                        View stock history

                                    </div>

                                </div>

                            </a>

                        </div>

                        <div class="col-12 col-sm-6 col-lg-3">

                            <a
                                href="index.php?page=inventory_adjustments"
                                class="quick-action"
                            >

                                <div class="quick-action-icon">

                                    <i class="bi bi-sliders fs-5"></i>

                                </div>

                                <div>

                                    <div class="fw-bold small">

                                        Adjust Stock

                                    </div>

                                    <div
                                        class="text-muted"
                                        style="font-size:.7rem;"
                                    >

                                        Correct inventory

                                    </div>

                                </div>

                            </a>

                        </div>

                    </div>

                </div>

            </div>

            <!-- =================================================
                 MAIN CONTENT
            ================================================== -->

            <div class="row g-4">

                <!-- =================================================
                     LOW STOCK
                ================================================== -->

                <div class="col-12 col-xl-5">

                    <div
                        class="card inventory-card shadow-sm bg-body h-100"
                    >

                        <div
                            class="inventory-card-header d-flex justify-content-between align-items-center"
                        >

                            <div>

                                <h5 class="fw-bold mb-1">

                                    Stock Alerts

                                </h5>

                                <p class="text-muted small mb-0">

                                    Products that need attention

                                </p>

                            </div>

                            <a
                                href="index.php?page=inventory_products"
                                class="btn btn-sm btn-outline-primary rounded-3"
                            >

                                View All

                            </a>

                        </div>

                        <div class="card-body p-3">

                            <?php if (!$lowStockList): ?>

                                <div class="dashboard-empty text-center">

                                    <div class="mb-3">

                                        <i
                                            class="bi bi-check-circle display-6 text-success opacity-75"
                                        ></i>

                                    </div>

                                    <div class="fw-semibold">

                                        Inventory looks good

                                    </div>

                                    <div class="text-muted small">

                                        No low-stock products found.

                                    </div>

                                </div>

                            <?php else: ?>

                                <?php foreach (
                                    $lowStockList
                                    as $product
                                ): ?>

                                    <?php

                                    $stock =
                                        (float)(
                                            $product['current_stock']
                                            ?? 0
                                        );

                                    $minimum =
                                        (float)(
                                            $product['minimum_stock']
                                            ?? 0
                                        );

                                    $isOutOfStock =
                                        $stock <= 0;

                                    ?>

                                    <div
                                        class="d-flex align-items-center justify-content-between py-3 border-bottom"
                                    >

                                        <div class="min-w-0">

                                            <div
                                                class="fw-semibold small text-truncate"
                                            >

                                                <?= htmlspecialchars(
                                                    $product['name']
                                                ) ?>

                                            </div>

                                            <?php if (
                                                !empty(
                                                    $product['sku']
                                                )
                                            ): ?>

                                                <div
                                                    class="text-muted"
                                                    style="font-size:.68rem;"
                                                >

                                                    SKU:
                                                    <?= htmlspecialchars(
                                                        $product['sku']
                                                    ) ?>

                                                </div>

                                            <?php endif; ?>

                                        </div>

                                        <div
                                            class="text-end ms-3"
                                        >

                                            <div
                                                class="<?= $isOutOfStock
                                                    ? 'stock-danger'
                                                    : 'stock-warning'
                                                ?>"
                                            >

                                                <?= number_format(
                                                    $stock,
                                                    2
                                                ) ?>

                                            </div>

                                            <div
                                                class="text-muted"
                                                style="font-size:.65rem;"
                                            >

                                                Min:
                                                <?= number_format(
                                                    $minimum,
                                                    2
                                                ) ?>

                                            </div>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

                <!-- =================================================
                     RECENT MOVEMENTS
                ================================================== -->

                <div class="col-12 col-xl-7">

                    <div
                        class="card inventory-card shadow-sm bg-body h-100"
                    >

                        <div
                            class="inventory-card-header d-flex justify-content-between align-items-center"
                        >

                            <div>

                                <h5 class="fw-bold mb-1">

                                    Recent Stock Movements

                                </h5>

                                <p class="text-muted small mb-0">

                                    Latest inventory activity

                                </p>

                            </div>

                            <a
                                href="index.php?page=inventory_movements"
                                class="btn btn-sm btn-outline-primary rounded-3"
                            >

                                View All

                            </a>

                        </div>

                        <?php if (!$recentMovements): ?>

                            <div class="dashboard-empty text-center">

                                <div class="mb-3">

                                    <i
                                        class="bi bi-clock-history display-6 text-muted opacity-50"
                                    ></i>

                                </div>

                                <div class="fw-semibold">

                                    No stock movements yet

                                </div>

                                <div class="text-muted small">

                                    Inventory activity will appear here.

                                </div>

                            </div>

                        <?php else: ?>

                            <div class="table-responsive">

                                <table
                                    class="table table-hover inventory-table mb-0"
                                >

                                    <thead class="table-light">

                                        <tr>

                                            <th class="ps-4 py-3">
                                                Product
                                            </th>

                                            <th class="py-3">
                                                Movement
                                            </th>

                                            <th class="py-3 text-end">
                                                Quantity
                                            </th>

                                            <th class="py-3 text-end">
                                                Stock
                                            </th>

                                            <th class="py-3 pe-4">
                                                Date
                                            </th>

                                        </tr>

                                    </thead>

                                    <tbody>

                                    <?php foreach (
                                        $recentMovements
                                        as $movement
                                    ): ?>

                                        <?php

                                        $movementType =
                                            $movement[
                                                'movement_type'
                                            ]
                                            ?? '';

                                        $movementClass =
                                            inventoryMovementClass(
                                                $movementType
                                            );

                                        $movementLabel =
                                            inventoryMovementLabel(
                                                $movementType
                                            );

                                        $quantity =
                                            (float)(
                                                $movement['quantity']
                                                ?? 0
                                            );

                                        $newStock =
                                            (float)(
                                                $movement['new_stock']
                                                ?? 0
                                            );

                                        ?>

                                        <tr>

                                            <td class="ps-4">

                                                <div
                                                    class="fw-semibold small"
                                                >

                                                    <?= htmlspecialchars(
                                                        $movement[
                                                            'product_name'
                                                        ]
                                                        ?? 'Unknown Product'
                                                    ) ?>

                                                </div>

                                                <?php if (
                                                    !empty(
                                                        $movement['sku']
                                                    )
                                                ): ?>

                                                    <div
                                                        class="text-muted"
                                                        style="font-size:.65rem;"
                                                    >

                                                        <?= htmlspecialchars(
                                                            $movement['sku']
                                                        ) ?>

                                                    </div>

                                                <?php endif; ?>

                                            </td>

                                            <td>

                                                <span
                                                    class="movement-badge <?= $movementClass ?>"
                                                >

                                                    <?= htmlspecialchars(
                                                        $movementLabel
                                                    ) ?>

                                                </span>

                                            </td>

                                            <td class="text-end">

                                                <?php if (
                                                    in_array(
                                                        $movementType,
                                                        [
                                                            'opening_stock',
                                                            'purchase',
                                                            'return_in',
                                                            'adjustment_in'
                                                        ],
                                                        true
                                                    )
                                                ): ?>

                                                    <span
                                                        class="text-success fw-bold"
                                                    >

                                                        +<?= number_format(
                                                            $quantity,
                                                            2
                                                        ) ?>

                                                    </span>

                                                <?php else: ?>

                                                    <span
                                                        class="text-danger fw-bold"
                                                    >

                                                        -<?= number_format(
                                                            $quantity,
                                                            2
                                                        ) ?>

                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                            <td class="text-end">

                                                <span class="fw-semibold">

                                                    <?= number_format(
                                                        $newStock,
                                                        2
                                                    ) ?>

                                                </span>

                                            </td>

                                            <td class="pe-4">

                                                <div
                                                    class="text-muted"
                                                    style="font-size:.68rem;"
                                                >

                                                    <?= !empty(
                                                        $movement['created_at']
                                                    )
                                                        ? date(
                                                            'M d, Y',
                                                            strtotime(
                                                                $movement[
                                                                    'created_at'
                                                                ]
                                                            )
                                                        )
                                                        : '-'
                                                    ?>

                                                </div>

                                                <div
                                                    class="text-muted"
                                                    style="font-size:.62rem;"
                                                >

                                                    <?= !empty(
                                                        $movement['created_at']
                                                    )
                                                        ? date(
                                                            'h:i A',
                                                            strtotime(
                                                                $movement[
                                                                    'created_at'
                                                                ]
                                                            )
                                                        )
                                                        : ''
                                                    ?>

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

            </div>

            <!-- =================================================
                 INVENTORY STATUS
            ================================================== -->

            <div class="row g-4 mt-1">

                <div class="col-12">

                    <div class="card inventory-card shadow-sm bg-body">

                        <div class="inventory-card-header">

                            <h5 class="fw-bold mb-1">

                                Inventory Status

                            </h5>

                            <p class="text-muted small mb-0">

                                Current inventory overview for this business

                            </p>

                        </div>

                        <div class="card-body">

                            <div class="row g-3">

                                <div class="col-12 col-md-4">

                                    <div
                                        class="border rounded-3 p-3 h-100"
                                    >

                                        <div
                                            class="d-flex justify-content-between align-items-center mb-2"
                                        >

                                            <span class="text-muted small">

                                                Products

                                            </span>

                                            <i
                                                class="bi bi-box-seam text-primary"
                                            ></i>

                                        </div>

                                        <div class="fs-4 fw-bold">

                                            <?= number_format(
                                                $totalProducts
                                            ) ?>

                                        </div>

                                        <div
                                            class="text-muted"
                                            style="font-size:.7rem;"
                                        >

                                            Active products

                                        </div>

                                    </div>

                                </div>

                                <div class="col-12 col-md-4">

                                    <div
                                        class="border rounded-3 p-3 h-100"
                                    >

                                        <div
                                            class="d-flex justify-content-between align-items-center mb-2"
                                        >

                                            <span class="text-muted small">

                                                Low Stock

                                            </span>

                                            <i
                                                class="bi bi-exclamation-triangle text-warning"
                                            ></i>

                                        </div>

                                        <div class="fs-4 fw-bold text-warning">

                                            <?= number_format(
                                                $lowStockProducts
                                            ) ?>

                                        </div>

                                        <div
                                            class="text-muted"
                                            style="font-size:.7rem;"
                                        >

                                            Products below minimum level

                                        </div>

                                    </div>

                                </div>

                                <div class="col-12 col-md-4">

                                    <div
                                        class="border rounded-3 p-3 h-100"
                                    >

                                        <div
                                            class="d-flex justify-content-between align-items-center mb-2"
                                        >

                                            <span class="text-muted small">

                                                Out of Stock

                                            </span>

                                            <i
                                                class="bi bi-x-circle text-danger"
                                            ></i>

                                        </div>

                                        <div class="fs-4 fw-bold text-danger">

                                            <?= number_format(
                                                $outOfStockProducts
                                            ) ?>

                                        </div>

                                        <div
                                            class="text-muted"
                                            style="font-size:.7rem;"
                                        >

                                            Products with zero stock

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </main>

</div>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>