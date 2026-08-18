<?php
// =========================================================
// InventorySidebar.php
// Modern Responsive Sidebar for Inventory Management
// =========================================================

$activePage = $activePage ?? '';

$inventoryItems = [
    [
        'page' => 'inventory_dashboard',
        'label' => 'Dashboard',
        'icon' => 'bi-grid-1x2-fill'
    ],
    [
        'page' => 'inventory_products',
        'label' => 'Products',
        'icon' => 'bi-box-seam-fill'
    ],
    [
        'page' => 'inventory_categories',
        'label' => 'Categories',
        'icon' => 'bi-tags-fill'
    ],
    [
        'page' => 'inventory_brands',
        'label' => 'Brands',
        'icon' => 'bi-award-fill'
    ],
    [
        'page' => 'inventory_suppliers',
        'label' => 'Suppliers',
        'icon' => 'bi-truck'
    ],
    [
        'page' => 'inventory_stock',
        'label' => 'Stock',
        'icon' => 'bi-boxes'
    ],
    [
        'page' => 'inventory_stock_history',
        'label' => 'Stock History',
        'icon' => 'bi-clock-history'
    ],
    [
        'page' => 'inventory_reports',
        'label' => 'Reports',
        'icon' => 'bi-bar-chart-line-fill'
    ],
];

$businessName = $_SESSION['business_name'] ?? 'Business';
$userName = $_SESSION['name'] ?? 'User';

$userInitial = strtoupper(
    substr(trim($userName), 0, 1)
);

if (!$userInitial) {
    $userInitial = 'U';
}
?>

<style>

/* =========================================================
   DESKTOP SIDEBAR
   ========================================================= */

.inventory-sidebar {
    width: 255px;
    min-width: 255px;
    height: 100vh;

    position: sticky;
    top: 0;

    z-index: 1030;

    display: flex;
    flex-direction: column;

    background: var(--bs-body-bg);
    border-right: 1px solid var(--bs-border-color);
}


/* =========================================================
   BRAND
   ========================================================= */

.inventory-sidebar-brand {
    padding: 20px 18px 18px;

    border-bottom: 1px solid var(--bs-border-color);
}

.inventory-brand-icon {
    width: 42px;
    height: 42px;
    min-width: 42px;

    border-radius: 12px;

    display: flex;
    align-items: center;
    justify-content: center;

    background: rgba(
        var(--bs-primary-rgb),
        .10
    );

    color: var(--bs-primary);

    font-size: 20px;
}

.inventory-brand-name {
    font-size: .9rem;
    font-weight: 700;

    color: var(--bs-body-color);

    line-height: 1.2;
}

.inventory-brand-subtitle {
    font-size: .68rem;

    color: var(--bs-secondary-color);

    margin-top: 3px;
}


/* =========================================================
   NAVIGATION
   ========================================================= */

.inventory-sidebar-nav {
    flex: 1;

    overflow-y: auto;

    padding: 18px 12px;
}

.inventory-nav-title {
    font-size: .65rem;
    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .08em;

    color: var(--bs-secondary-color);

    padding: 0 10px;

    margin-bottom: 8px;
}

.inventory-nav-item {
    margin-bottom: 3px;
}

.inventory-nav-link {
    position: relative;

    display: flex;

    align-items: center;

    gap: 12px;

    width: 100%;

    padding: 10px 11px;

    border-radius: 10px;

    color: var(--bs-secondary-color);

    text-decoration: none;

    font-size: .82rem;

    font-weight: 600;

    transition:
        background .18s ease,
        color .18s ease,
        transform .18s ease;
}

.inventory-nav-link i {
    width: 21px;
    min-width: 21px;

    text-align: center;

    font-size: 16px;

    transition: transform .18s ease;
}

.inventory-nav-link:hover {
    color: var(--bs-body-color);

    background: rgba(
        var(--bs-secondary-rgb),
        .08
    );
}

.inventory-nav-link:hover i {
    transform: translateX(1px);
}

.inventory-nav-link.active {
    color: var(--bs-primary);

    background: rgba(
        var(--bs-primary-rgb),
        .10
    );
}

.inventory-nav-link.active::before {
    content: "";

    position: absolute;

    left: -12px;

    top: 8px;

    bottom: 8px;

    width: 3px;

    border-radius: 0 4px 4px 0;

    background: var(--bs-primary);
}

.inventory-nav-link.active i {
    color: var(--bs-primary);
}


/* =========================================================
   SIDEBAR FOOTER
   ========================================================= */

.inventory-sidebar-footer {
    padding: 12px;

    border-top: 1px solid var(--bs-border-color);
}

.inventory-user-card {
    position: relative;

    display: flex;

    align-items: center;

    gap: 10px;

    padding: 10px;

    border-radius: 12px;

    background: rgba(
        var(--bs-secondary-rgb),
        .05
    );

    border: 1px solid var(--bs-border-color);

    text-decoration: none;

    color: var(--bs-body-color);

    transition: all .18s ease;
}

.inventory-user-card:hover {
    background: rgba(
        var(--bs-secondary-rgb),
        .10
    );

    color: var(--bs-body-color);
}

.inventory-user-avatar {
    width: 36px;
    height: 36px;

    min-width: 36px;

    border-radius: 10px;

    display: flex;
    align-items: center;
    justify-content: center;

    background: var(--bs-primary);

    color: #fff;

    font-size: .8rem;

    font-weight: 700;
}

.inventory-user-info {
    min-width: 0;

    flex: 1;
}

.inventory-user-name {
    display: block;

    font-size: .78rem;

    font-weight: 700;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}

.inventory-user-status {
    display: flex;

    align-items: center;

    gap: 5px;

    color: var(--bs-secondary-color);

    font-size: .65rem;

    margin-top: 2px;
}

.inventory-online-dot {
    width: 6px;
    height: 6px;

    border-radius: 50%;

    background: #198754;
}


/* =========================================================
   USER DROPDOWN
   ========================================================= */

.inventory-user-dropdown {
    min-width: 210px;

    padding: 6px;

    border: 1px solid var(--bs-border-color);

    box-shadow:
        0 10px 30px rgba(0,0,0,.10);

    border-radius: 12px;
}

.inventory-user-dropdown .dropdown-item {
    border-radius: 8px;

    padding: 9px 10px;

    font-size: .78rem;

    font-weight: 500;
}

.inventory-user-dropdown .dropdown-item:hover {
    background: rgba(
        var(--bs-secondary-rgb),
        .08
    );
}

.inventory-user-dropdown
.dropdown-item.text-danger:hover {

    background: rgba(
        var(--bs-danger-rgb),
        .08
    );
}


/* =========================================================
   SCROLLBAR
   ========================================================= */

.inventory-sidebar-nav::-webkit-scrollbar {
    width: 5px;
}

.inventory-sidebar-nav::-webkit-scrollbar-track {
    background: transparent;
}

.inventory-sidebar-nav::-webkit-scrollbar-thumb {
    background: var(--bs-border-color);

    border-radius: 10px;
}


/* =========================================================
   MOBILE HEADER
   ========================================================= */

.inventory-mobile-header {
    display: none;

    height: 62px;

    background: var(--bs-body-bg);

    border-bottom: 1px solid var(--bs-border-color);

    padding: 0 15px;

    align-items: center;

    justify-content: space-between;

    position: sticky;

    top: 0;

    z-index: 1020;
}

.inventory-mobile-brand {
    display: flex;

    align-items: center;

    gap: 9px;

    min-width: 0;
}

.inventory-mobile-brand-icon {
    width: 35px;
    height: 35px;

    border-radius: 9px;

    background: rgba(
        var(--bs-primary-rgb),
        .10
    );

    color: var(--bs-primary);

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 17px;
}

.inventory-mobile-business {
    min-width: 0;
}

.inventory-mobile-business-name {
    display: block;

    max-width: 210px;

    font-size: .82rem;

    font-weight: 700;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}

.inventory-mobile-business-subtitle {
    display: block;

    font-size: .62rem;

    color: var(--bs-secondary-color);
}

.inventory-mobile-menu {
    width: 38px;
    height: 38px;

    border: 1px solid var(--bs-border-color);

    border-radius: 9px;

    background: var(--bs-body-bg);

    color: var(--bs-body-color);

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 19px;

    cursor: pointer;
}

.inventory-mobile-menu:hover {
    background: rgba(
        var(--bs-secondary-rgb),
        .08
    );
}


/* =========================================================
   MOBILE OFFCANVAS
   ========================================================= */

.inventory-mobile-offcanvas {
    width: 285px !important;

    background: var(--bs-body-bg);

    color: var(--bs-body-color);
}

.inventory-mobile-offcanvas
.offcanvas-header {

    min-height: 62px;

    border-bottom:
        1px solid var(--bs-border-color);

    padding: 12px 16px;
}

.inventory-mobile-offcanvas
.offcanvas-body {

    padding: 14px 12px;
}

.inventory-mobile-section-title {
    font-size: .65rem;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .08em;

    color: var(--bs-secondary-color);

    padding:
        6px 10px 8px;
}

.inventory-mobile-user {
    margin-top: auto;

    padding: 12px;

    border-top:
        1px solid var(--bs-border-color);
}


/* =========================================================
   MOBILE NAVIGATION
   ========================================================= */

.inventory-mobile-offcanvas
.inventory-nav-link {

    cursor: pointer;

    -webkit-tap-highlight-color: transparent;

    touch-action: manipulation;
}

.inventory-mobile-offcanvas
.inventory-nav-link:active {

    transform: scale(.98);
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 991.98px) {

    .inventory-sidebar {
        display: none !important;
    }

    .inventory-mobile-header {
        display: flex;
    }
}

@media (min-width: 992px) {

    .inventory-mobile-offcanvas {
        display: none !important;
    }
}

</style>


<!-- =========================================================
     DESKTOP SIDEBAR
     ========================================================= -->

<aside class="inventory-sidebar d-none d-lg-flex">

    <!-- BRAND -->

    <div class="inventory-sidebar-brand">

        <a
            href="index.php?page=inventory_dashboard"
            class="text-decoration-none d-flex align-items-center gap-3"
        >

            <div class="inventory-brand-icon">

                <i class="bi bi-boxes"></i>

            </div>


            <div class="overflow-hidden">

                <div class="inventory-brand-name text-truncate">

                    <?= htmlspecialchars($businessName) ?>

                </div>

                <div class="inventory-brand-subtitle">

                    Inventory Management

                </div>

            </div>

        </a>

    </div>


    <!-- NAVIGATION -->

    <div class="inventory-sidebar-nav">

        <div class="inventory-nav-title">

            Main Menu

        </div>


        <ul class="nav flex-column">

            <?php foreach ($inventoryItems as $item): ?>

                <?php
                $isActive =
                    ($activePage === $item['page']);
                ?>

                <li class="inventory-nav-item">

                    <a
                        href="index.php?page=<?= urlencode($item['page']) ?>"
                        class="inventory-nav-link <?= $isActive ? 'active' : '' ?>"
                    >

                        <i
                            class="bi <?= htmlspecialchars($item['icon']) ?>"
                        ></i>

                        <span>

                            <?= htmlspecialchars($item['label']) ?>

                        </span>

                    </a>

                </li>

            <?php endforeach; ?>

        </ul>

    </div>


    <!-- USER FOOTER -->

    <div class="inventory-sidebar-footer">

        <div class="dropdown">

            <a
                href="#"
                class="inventory-user-card"
                data-bs-toggle="dropdown"
                aria-expanded="false"
            >

                <div class="inventory-user-avatar">

                    <?= htmlspecialchars($userInitial) ?>

                </div>


                <div class="inventory-user-info">

                    <span class="inventory-user-name">

                        <?= htmlspecialchars($userName) ?>

                    </span>


                    <span class="inventory-user-status">

                        <span class="inventory-online-dot"></span>

                        Active Session

                    </span>

                </div>


                <i
                    class="bi bi-three-dots-vertical text-muted"
                ></i>

            </a>


            <ul
                class="dropdown-menu dropdown-menu-end inventory-user-dropdown"
            >

                <li>

                    <a
                        class="dropdown-item"
                        href="index.php?page=settings"
                    >

                        <i
                            class="bi bi-gear me-2 text-muted"
                        ></i>

                        Settings

                    </a>

                </li>


                <li>

                    <a
                        class="dropdown-item"
                        href="index.php?page=select_business"
                    >

                        <i
                            class="bi bi-arrow-repeat me-2 text-muted"
                        ></i>

                        Switch Business

                    </a>

                </li>


                <li>

                    <hr
                        class="dropdown-divider my-1"
                    >

                </li>


                <li>

                    <a
                        class="dropdown-item text-danger fw-semibold"
                        href="index.php?page=logout"
                    >

                        <i
                            class="bi bi-box-arrow-right me-2"
                        ></i>

                        Log Out

                    </a>

                </li>

            </ul>

        </div>

    </div>

</aside>


<!-- =========================================================
     MOBILE TOP HEADER
     ========================================================= -->

<header class="inventory-mobile-header d-lg-none">

    <a
        href="index.php?page=inventory_dashboard"
        class="inventory-mobile-brand text-decoration-none text-body"
    >

        <div class="inventory-mobile-brand-icon">

            <i class="bi bi-boxes"></i>

        </div>


        <div class="inventory-mobile-business">

            <span class="inventory-mobile-business-name">

                <?= htmlspecialchars($businessName) ?>

            </span>


            <span class="inventory-mobile-business-subtitle">

                Inventory Management

            </span>

        </div>

    </a>


    <button
        type="button"
        class="inventory-mobile-menu"
        data-bs-toggle="offcanvas"
        data-bs-target="#inventoryMobileSidebar"
        aria-controls="inventoryMobileSidebar"
        aria-label="Open navigation menu"
    >

        <i class="bi bi-list"></i>

    </button>

</header>


<!-- =========================================================
     MOBILE OFFCANVAS
     ========================================================= -->

<div
    class="offcanvas offcanvas-start inventory-mobile-offcanvas"
    tabindex="-1"
    id="inventoryMobileSidebar"
    aria-labelledby="inventoryMobileSidebarLabel"
>


    <!-- HEADER -->

    <div class="offcanvas-header">

        <div class="d-flex align-items-center gap-2">

            <div class="inventory-brand-icon">

                <i class="bi bi-boxes"></i>

            </div>


            <div>

                <h5
                    class="offcanvas-title fw-bold fs-6 mb-0"
                    id="inventoryMobileSidebarLabel"
                >

                    <?= htmlspecialchars($businessName) ?>

                </h5>


                <div
                    class="text-muted"
                    style="font-size:.65rem;"
                >

                    Inventory Management

                </div>

            </div>

        </div>


        <button
            type="button"
            class="btn-close shadow-none"
            data-bs-dismiss="offcanvas"
            aria-label="Close"
        >
        </button>

    </div>


    <!-- BODY -->

    <div
        class="offcanvas-body d-flex flex-column"
    >

        <div class="inventory-mobile-section-title">

            Main Menu

        </div>


        <ul
            class="nav flex-column gap-1"
        >

            <?php foreach ($inventoryItems as $item): ?>

                <?php
                $isActive =
                    ($activePage === $item['page']);
                ?>

                <li>

                    <a
                        href="index.php?page=<?= urlencode($item['page']) ?>"
                        class="inventory-nav-link mobile-inventory-nav-link <?= $isActive ? 'active' : '' ?>"
                    >

                        <i
                            class="bi <?= htmlspecialchars($item['icon']) ?>"
                        ></i>


                        <span>

                            <?= htmlspecialchars($item['label']) ?>

                        </span>

                    </a>

                </li>

            <?php endforeach; ?>

        </ul>


        <!-- MOBILE USER -->

        <div class="inventory-mobile-user">

            <div class="dropdown">

                <a
                    href="#"
                    class="inventory-user-card"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >

                    <div class="inventory-user-avatar">

                        <?= htmlspecialchars($userInitial) ?>

                    </div>


                    <div class="inventory-user-info">

                        <span class="inventory-user-name">

                            <?= htmlspecialchars($userName) ?>

                        </span>


                        <span class="inventory-user-status">

                            <span class="inventory-online-dot"></span>

                            Active Session

                        </span>

                    </div>


                    <i
                        class="bi bi-three-dots-vertical text-muted"
                    ></i>

                </a>


                <ul
                    class="dropdown-menu inventory-user-dropdown"
                >

                    <li>

                        <a
                            class="dropdown-item"
                            href="index.php?page=settings"
                        >

                            <i
                                class="bi bi-gear me-2 text-muted"
                            ></i>

                            Settings

                        </a>

                    </li>


                    <li>

                        <a
                            class="dropdown-item"
                            href="index.php?page=select_business"
                        >

                            <i
                                class="bi bi-arrow-repeat me-2 text-muted"
                            ></i>

                            Switch Business

                        </a>

                    </li>


                    <li>

                        <hr
                            class="dropdown-divider my-1"
                        >

                    </li>


                    <li>

                        <a
                            class="dropdown-item text-danger fw-semibold"
                            href="index.php?page=logout"
                        >

                            <i
                                class="bi bi-box-arrow-right me-2"
                            ></i>

                            Log Out

                        </a>

                    </li>

                </ul>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     MOBILE NAVIGATION JAVASCRIPT
     ========================================================= -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const mobileLinks =
        document.querySelectorAll(
            '.mobile-inventory-nav-link'
        );

    const mobileSidebar =
        document.getElementById(
            'inventoryMobileSidebar'
        );


    mobileLinks.forEach(function (link) {

        link.addEventListener(
            'click',
            function (event) {

                event.preventDefault();

                const targetUrl =
                    this.getAttribute('href');


                /*
                 * Close Bootstrap Offcanvas
                 * before navigation.
                 */

                if (
                    mobileSidebar &&
                    typeof bootstrap !== 'undefined'
                ) {

                    const offcanvas =
                        bootstrap.Offcanvas.getInstance(
                            mobileSidebar
                        );


                    if (offcanvas) {

                        offcanvas.hide();

                    }

                }


                /*
                 * Navigate after the
                 * offcanvas begins closing.
                 */

                setTimeout(function () {

                    window.location.href =
                        targetUrl;

                }, 150);

            }
        );

    });

});

</script>