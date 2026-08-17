
<?php
$activePage = $activePage ?? 'inventory_dashboard';
$businessName = $_SESSION['business_name'] ?? 'Business';

$inventoryLinks = [
    [
        'page' => 'inventory_dashboard',
        'label' => 'Dashboard',
        'icon' => 'speedometer2'
    ],
    [
        'page' => 'inventory_products',
        'label' => 'Products',
        'icon' => 'box-seam'
    ],
    [
        'page' => 'inventory_categories',
        'label' => 'Categories',
        'icon' => 'tags'
    ],
    [
        'page' => 'inventory_brands',
        'label' => 'Brands',
        'icon' => 'award'
    ],
    [
        'page' => 'inventory_suppliers',
        'label' => 'Suppliers',
        'icon' => 'truck'
    ],
    [
        'page' => 'inventory_stock',
        'label' => 'Stock',
        'icon' => 'boxes'
    ],
    [
        'page' => 'inventory_stock_history',
        'label' => 'Stock History',
        'icon' => 'clock-history'
    ],
    [
        'page' => 'inventory_reports',
        'label' => 'Reports',
        'icon' => 'bar-chart'
    ]
];
?>

<style>
.inventory-sidebar{
    width:260px;
    min-width:260px;
    background:var(--bs-body-bg);
    border-right:1px solid var(--bs-border-color);
    min-height:100vh;
    position:sticky;
    top:0;
    height:100vh;
    z-index:1030;
}

.inventory-sidebar-header{
    padding:20px 18px;
    border-bottom:1px solid var(--bs-border-color);
}

.inventory-sidebar-brand{
    display:flex;
    align-items:center;
    gap:12px;
    text-decoration:none;
    color:var(--bs-body-color);
}

.inventory-sidebar-logo{
    width:42px;
    height:42px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:rgba(var(--bs-primary-rgb),.1);
    color:var(--bs-primary);
    font-size:1.2rem;
    flex-shrink:0;
}

.inventory-sidebar-title{
    font-size:.95rem;
    font-weight:700;
    line-height:1.2;
}

.inventory-sidebar-business{
    font-size:.7rem;
    color:var(--bs-secondary-color);
    margin-top:3px;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.inventory-sidebar-body{
    padding:15px 12px;
    overflow-y:auto;
    height:calc(100vh - 150px);
}

.inventory-sidebar-section{
    font-size:.65rem;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.08em;
    color:var(--bs-secondary-color);
    padding:10px 12px 7px;
}

.inventory-sidebar-link{
    display:flex;
    align-items:center;
    gap:11px;
    padding:10px 12px;
    margin-bottom:3px;
    border-radius:10px;
    color:var(--bs-secondary-color);
    text-decoration:none;
    font-size:.84rem;
    font-weight:600;
    transition:all .15s ease;
}

.inventory-sidebar-link i{
    width:20px;
    text-align:center;
    font-size:1rem;
}

.inventory-sidebar-link:hover{
    color:var(--bs-primary);
    background:rgba(var(--bs-primary-rgb),.07);
}

.inventory-sidebar-link.active{
    color:var(--bs-primary);
    background:rgba(var(--bs-primary-rgb),.1);
}

.inventory-sidebar-footer{
    position:absolute;
    bottom:0;
    left:0;
    right:0;
    padding:12px;
    background:var(--bs-body-bg);
    border-top:1px solid var(--bs-border-color);
}

.inventory-back-link{
    display:flex;
    align-items:center;
    gap:10px;
    padding:9px 12px;
    border-radius:10px;
    color:var(--bs-secondary-color);
    text-decoration:none;
    font-size:.8rem;
    font-weight:600;
}

.inventory-back-link:hover{
    color:var(--bs-primary);
    background:rgba(var(--bs-primary-rgb),.07);
}

.inventory-mobile-toggle{
    display:none;
}

@media(max-width:991.98px){
    .inventory-sidebar{
        width:100%;
        min-width:0;
        height:auto;
        min-height:auto;
        position:relative;
        border-right:0;
        border-bottom:1px solid var(--bs-border-color);
    }

    .inventory-sidebar-body,
    .inventory-sidebar-footer{
        display:none;
    }

    .inventory-sidebar-header{
        padding:12px 15px;
    }

    .inventory-mobile-toggle{
        display:block;
        margin-left:auto;
    }

    .inventory-sidebar.show .inventory-sidebar-body{
        display:block;
        height:auto;
        max-height:70vh;
    }

    .inventory-sidebar.show .inventory-sidebar-footer{
        display:block;
        position:relative;
        border-top:1px solid var(--bs-border-color);
    }
}
</style>

<aside class="inventory-sidebar" id="inventorySidebar">

    <div class="inventory-sidebar-header">
        <div class="d-flex align-items-center">

            <a href="index.php?page=inventory_dashboard"
               class="inventory-sidebar-brand flex-grow-1">

                <div class="inventory-sidebar-logo">
                    <i class="bi bi-boxes"></i>
                </div>

                <div class="min-width-0">
                    <div class="inventory-sidebar-title">
                        Inventory
                    </div>

                    <div class="inventory-sidebar-business">
                        <?= htmlspecialchars($businessName) ?>
                    </div>
                </div>

            </a>

            <button
                type="button"
                class="btn btn-sm btn-outline-secondary inventory-mobile-toggle"
                onclick="document.getElementById('inventorySidebar').classList.toggle('show')"
                aria-label="Toggle inventory menu">

                <i class="bi bi-list"></i>

            </button>

        </div>
    </div>

    <div class="inventory-sidebar-body">

        <div class="inventory-sidebar-section">
            Inventory
        </div>

        <?php foreach ($inventoryLinks as $link): ?>

            <?php $isActive = $activePage === $link['page']; ?>

            <a
                href="index.php?page=<?= urlencode($link['page']) ?>"
                class="inventory-sidebar-link <?= $isActive ? 'active' : '' ?>">

                <i class="bi bi-<?= htmlspecialchars($link['icon']) ?>"></i>

                <span>
                    <?= htmlspecialchars($link['label']) ?>
                </span>

            </a>

        <?php endforeach; ?>

    </div>

    <div class="inventory-sidebar-footer">

        <a href="index.php?page=select_business"
           class="inventory-back-link">

            <i class="bi bi-arrow-left"></i>

            <span>
                Switch Business
            </span>

        </a>

        <a href="index.php?page=dashboard"
           class="inventory-back-link">

            <i class="bi bi-grid-1x2"></i>

            <span>
                Main Dashboard
            </span>

        </a>

    </div>

</aside>
