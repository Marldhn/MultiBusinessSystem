
<?php

$businessId = $_SESSION['business_id'] ?? null;

if (!$businessId) {
    header('Location: index.php?page=select_business');
    exit;
}

$businessName = $_SESSION['business_name'] ?? 'Business';

$activePage = 'inventory_dashboard';
$pageTitle = 'Inventory Dashboard';

$totalProducts = 0;
$lowStockProducts = 0;
$outOfStockProducts = 0;
$inventoryValue = 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">

<title><?= htmlspecialchars($pageTitle) ?></title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet">

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
    rel="stylesheet">

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
    include __DIR__ . '/../../resources/partials/InventorySidebar.php';
    ?>

    <main class="inventory-main flex-grow-1 bg-body-tertiary">

        <div class="p-3 p-md-4">

            <!-- HEADER -->

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
                    class="btn btn-primary fw-bold px-3 py-2 rounded-3 shadow-sm d-flex align-items-center gap-2">

                    <i class="bi bi-plus-lg"></i>

                    <span>
                        Add Product
                    </span>

                </a>

            </div>


            <!-- STATISTICS -->

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


            <!-- QUICK ACTIONS -->

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
                                class="quick-action">

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
                                class="quick-action">

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
                                class="quick-action">

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
                                class="quick-action">

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


            <!-- LOW STOCK -->

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
                        class="btn btn-sm btn-outline-primary fw-semibold">

                        View All

                        <i class="bi bi-arrow-right ms-1"></i>

                    </a>

                </div>

                <div class="empty-state text-center text-muted">

                    <div class="mb-3">

                        <i class="bi bi-box-seam display-6 opacity-50"></i>

                    </div>

                    <div class="fw-semibold mb-1">
                        No low-stock data yet
                    </div>

                    <div class="small">
                        Low-stock products will appear here once products are added.
                    </div>

                </div>

            </div>


            <!-- RECENT STOCK MOVEMENTS -->

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
                        class="small text-decoration-none fw-semibold text-nowrap">

                        View All

                        <i class="bi bi-arrow-right ms-1"></i>

                    </a>

                </div>

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

            </div>

        </div>

    </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
