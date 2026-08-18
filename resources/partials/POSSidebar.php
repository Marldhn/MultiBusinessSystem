<?php
// =========================================================
// pos_sidebar.php
// Modern Responsive Sidebar for POS Management
// =========================================================

$posActivePage = $activePage ?? 'pos_dashboard';

$businessName = $_SESSION['business_name'] ?? 'POS';
$userName = $_SESSION['name'] ?? 'System Admin';

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

.pos-sidebar {
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

.pos-sidebar-brand {
    padding: 20px 18px 18px;
    border-bottom: 1px solid var(--bs-border-color);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 12px;
}

.pos-sidebar-logo {
    width: 42px;
    height: 42px;
    min-width: 42px;

    border-radius: 12px;

    display: flex;
    align-items: center;
    justify-content: center;

    background: rgba(var(--bs-primary-rgb), .10);
    color: var(--bs-primary);

    font-size: 20px;
}

.pos-sidebar-title {
    font-size: .9rem;
    font-weight: 700;
    color: var(--bs-body-color);
    line-height: 1.2;
}

.pos-sidebar-subtitle {
    font-size: .68rem;
    color: var(--bs-secondary-color);
    margin-top: 3px;
}


/* =========================================================
   NAVIGATION
   ========================================================= */

.pos-sidebar-nav {
    flex: 1;
    overflow-y: auto;
    padding: 18px 12px;
}

.pos-sidebar-section {
    margin-bottom: 18px;
}

.pos-sidebar-section-title {
    font-size: .65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--bs-secondary-color);
    padding: 0 10px;
    margin-bottom: 8px;
}

.pos-sidebar-link {
    position: relative;
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 10px 11px;
    margin-bottom: 3px;
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

.pos-sidebar-link i {
    width: 21px;
    min-width: 21px;
    text-align: center;
    font-size: 16px;
    transition: transform .18s ease;
}

.pos-sidebar-link:hover {
    color: var(--bs-body-color);
    background: rgba(var(--bs-secondary-rgb), .08);
}

.pos-sidebar-link:hover i {
    transform: translateX(1px);
}

.pos-sidebar-link.active {
    color: var(--bs-primary);
    background: rgba(var(--bs-primary-rgb), .10);
}

.pos-sidebar-link.active::before {
    content: "";
    position: absolute;
    left: -12px;
    top: 8px;
    bottom: 8px;
    width: 3px;
    border-radius: 0 4px 4px 0;
    background: var(--bs-primary);
}

.pos-sidebar-link.active i {
    color: var(--bs-primary);
}

.pos-sidebar-divider {
    border-top: 1px solid var(--bs-border-color);
    margin: 12px 6px;
}


/* =========================================================
   SIDEBAR FOOTER (USER CARD)
   ========================================================= */

.pos-sidebar-footer {
    padding: 12px;
    border-top: 1px solid var(--bs-border-color);
}

.pos-user-card {
    position: relative;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border-radius: 12px;
    background: rgba(var(--bs-secondary-rgb), .05);
    border: 1px solid var(--bs-border-color);
    text-decoration: none;
    color: var(--bs-body-color);
    transition: all .18s ease;
}

.pos-user-card:hover {
    background: rgba(var(--bs-secondary-rgb), .10);
    color: var(--bs-body-color);
}

.pos-user-avatar {
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

.pos-user-info {
    min-width: 0;
    flex: 1;
}

.pos-user-name {
    display: block;
    font-size: .78rem;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pos-user-status {
    display: flex;
    align-items: center;
    gap: 5px;
    color: var(--bs-secondary-color);
    font-size: .65rem;
    margin-top: 2px;
}

.pos-online-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #198754;
}


/* =========================================================
   DROPDOWN
   ========================================================= */

.pos-user-dropdown {
    min-width: 210px;
    padding: 6px;
    border: 1px solid var(--bs-border-color);
    box-shadow: 0 10px 30px rgba(0,0,0,.10);
    border-radius: 12px;
}

.pos-user-dropdown .dropdown-item {
    border-radius: 8px;
    padding: 9px 10px;
    font-size: .78rem;
    font-weight: 500;
}

.pos-user-dropdown .dropdown-item:hover {
    background: rgba(var(--bs-secondary-rgb), .08);
}

.pos-user-dropdown .dropdown-item.text-danger:hover {
    background: rgba(var(--bs-danger-rgb), .08);
}


/* =========================================================
   SCROLLBAR
   ========================================================= */

.pos-sidebar-nav::-webkit-scrollbar {
    width: 5px;
}

.pos-sidebar-nav::-webkit-scrollbar-track {
    background: transparent;
}

.pos-sidebar-nav::-webkit-scrollbar-thumb {
    background: var(--bs-border-color);
    border-radius: 10px;
}


/* =========================================================
   MOBILE HEADER
   ========================================================= */

.pos-mobile-header {
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

.pos-mobile-brand {
    display: flex;
    align-items: center;
    gap: 9px;
    min-width: 0;
}

.pos-mobile-brand-icon {
    width: 35px;
    height: 35px;
    border-radius: 9px;
    background: rgba(var(--bs-primary-rgb), .10);
    color: var(--bs-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
}

.pos-mobile-business {
    min-width: 0;
}

.pos-mobile-business-name {
    display: block;
    max-width: 210px;
    font-size: .82rem;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pos-mobile-business-subtitle {
    display: block;
    font-size: .62rem;
    color: var(--bs-secondary-color);
}

.pos-mobile-menu {
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

.pos-mobile-menu:hover {
    background: rgba(var(--bs-secondary-rgb), .08);
}


/* =========================================================
   MOBILE OFFCANVAS
   ========================================================= */

.pos-mobile-offcanvas {
    width: 285px !important;
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
}

.pos-mobile-offcanvas .offcanvas-header {
    min-height: 62px;
    border-bottom: 1px solid var(--bs-border-color);
    padding: 12px 16px;
}

.pos-mobile-offcanvas .offcanvas-body {
    padding: 14px 12px;
}

.pos-mobile-user {
    margin-top: auto;
    padding: 12px;
    border-top: 1px solid var(--bs-border-color);
}

.pos-mobile-offcanvas .pos-sidebar-link {
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
}

.pos-mobile-offcanvas .pos-sidebar-link:active {
    transform: scale(.98);
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 991.98px) {
    .pos-sidebar {
        display: none !important;
    }

    .pos-mobile-header {
        display: flex;
    }
}

@media (min-width: 992px) {
    .pos-mobile-offcanvas {
        display: none !important;
    }
}

</style>


<!-- =========================================================
     DESKTOP SIDEBAR
     ========================================================= -->

<aside class="pos-sidebar d-none d-lg-flex">

    <!-- BRAND -->
    <a href="index.php?page=pos_dashboard" class="pos-sidebar-brand">
        <div class="pos-sidebar-logo">
            <i class="bi bi-cart3"></i>
        </div>
        <div class="overflow-hidden">
            <div class="pos-sidebar-title text-truncate">
                <?= htmlspecialchars($businessName) ?>
            </div>
            <div class="pos-sidebar-subtitle">
                Point of Sale
            </div>
        </div>
    </a>

    <!-- NAVIGATION BODY -->
    <div class="pos-sidebar-nav">

        <!-- MAIN -->
        <div class="pos-sidebar-section">
            <div class="pos-sidebar-section-title">Main</div>

            <a href="index.php?page=pos_dashboard" class="pos-sidebar-link <?= $posActivePage === 'pos_dashboard' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2"></i>
                <span>Dashboard</span>
            </a>

            <a href="index.php?page=pos_sales" class="pos-sidebar-link <?= $posActivePage === 'pos_sales' ? 'active' : '' ?>">
                <i class="bi bi-cart-plus"></i>
                <span>New Sale</span>
            </a>

            <a href="index.php?page=pos_transactions" class="pos-sidebar-link <?= $posActivePage === 'pos_transactions' ? 'active' : '' ?>">
                <i class="bi bi-receipt"></i>
                <span>Sales Transactions</span>
            </a>
        </div>

        <!-- MANAGEMENT -->
        <div class="pos-sidebar-section">
            <div class="pos-sidebar-section-title">Management</div>

            <a href="index.php?page=pos_customers" class="pos-sidebar-link <?= $posActivePage === 'pos_customers' ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>Customers</span>
            </a>

            <a href="index.php?page=pos_products" class="pos-sidebar-link <?= $posActivePage === 'pos_products' ? 'active' : '' ?>">
                <i class="bi bi-box-seam"></i>
                <span>Products</span>
            </a>

            <a href="index.php?page=pos_stock" class="pos-sidebar-link <?= $posActivePage === 'pos_stock' ? 'active' : '' ?>">
                <i class="bi bi-boxes"></i>
                <span>Inventory</span>
            </a>
        </div>

        <!-- REPORTS -->
        <div class="pos-sidebar-section">
            <div class="pos-sidebar-section-title">Reports</div>

            <a href="index.php?page=pos_reports" class="pos-sidebar-link <?= $posActivePage === 'pos_reports' ? 'active' : '' ?>">
                <i class="bi bi-bar-chart"></i>
                <span>Sales Reports</span>
            </a>

            <a href="index.php?page=pos_sales_history" class="pos-sidebar-link <?= $posActivePage === 'pos_sales_history' ? 'active' : '' ?>">
                <i class="bi bi-clock-history"></i>
                <span>Sales History</span>
            </a>
        </div>

        <div class="pos-sidebar-divider"></div>



    </div>

    <!-- USER FOOTER -->
    <div class="pos-sidebar-footer">
        <div class="dropdown">
            <a href="#" class="pos-user-card" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="pos-user-avatar">
                    <?= htmlspecialchars($userInitial) ?>
                </div>
                <div class="pos-user-info">
                    <span class="pos-user-name">
                        <?= htmlspecialchars($userName) ?>
                    </span>
                    <span class="pos-user-status">
                        <span class="pos-online-dot"></span>
                        Active Session
                    </span>
                </div>
                <i class="bi bi-three-dots-vertical text-muted"></i>
            </a>

            <ul class="dropdown-menu dropdown-menu-end pos-user-dropdown">
                <li>
                    <a class="dropdown-item" href="index.php?page=settings">
                        <i class="bi bi-gear me-2 text-muted"></i> Settings
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="index.php?page=select_business">
                        <i class="bi bi-arrow-repeat me-2 text-muted"></i> Switch Business
                    </a>
                </li>
                <li>
                    <hr class="dropdown-divider my-1">
                </li>
                <li>
                    <a class="dropdown-item text-danger fw-semibold" href="index.php?page=logout">
                        <i class="bi bi-box-arrow-right me-2"></i> Log Out
                    </a>
                </li>
            </ul>
        </div>
    </div>

</aside>


<!-- =========================================================
     MOBILE TOP HEADER
     ========================================================= -->

<header class="pos-mobile-header d-lg-none">
    <a href="index.php?page=pos_dashboard" class="pos-mobile-brand text-decoration-none text-body">
        <div class="pos-mobile-brand-icon">
            <i class="bi bi-cart3"></i>
        </div>
        <div class="pos-mobile-business">
            <span class="pos-mobile-business-name">
                <?= htmlspecialchars($businessName) ?>
            </span>
            <span class="pos-mobile-business-subtitle">
                Point of Sale
            </span>
        </div>
    </a>

    <button type="button" class="pos-mobile-menu" data-bs-toggle="offcanvas" data-bs-target="#mobilePosSidebar" aria-controls="mobilePosSidebar" aria-label="Open navigation menu">
        <i class="bi bi-list"></i>
    </button>
</header>


<!-- =========================================================
     MOBILE OFFCANVAS
     ========================================================= -->

<div class="offcanvas offcanvas-start pos-mobile-offcanvas" tabindex="-1" id="mobilePosSidebar" aria-labelledby="mobilePosSidebarLabel">

    <!-- HEADER -->
    <div class="offcanvas-header">
        <div class="d-flex align-items-center gap-2">
            <div class="pos-sidebar-logo">
                <i class="bi bi-cart3"></i>
            </div>
            <div>
                <h5 class="offcanvas-title fw-bold fs-6 mb-0" id="mobilePosSidebarLabel">
                    <?= htmlspecialchars($businessName) ?>
                </h5>
                <div class="text-muted" style="font-size:.65rem;">
                    Point of Sale
                </div>
            </div>
        </div>

        <button type="button" class="btn-close shadow-none" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <!-- BODY -->
    <div class="offcanvas-body d-flex flex-column">

        <div class="pos-sidebar-section">
            <div class="pos-sidebar-section-title">Main</div>
            <a href="index.php?page=pos_dashboard" class="pos-sidebar-link mobile-nav-link <?= $posActivePage === 'pos_dashboard' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2"></i>
                <span>Dashboard</span>
            </a>
            <a href="index.php?page=pos_sales" class="pos-sidebar-link mobile-nav-link <?= $posActivePage === 'pos_sales' ? 'active' : '' ?>">
                <i class="bi bi-cart-plus"></i>
                <span>New Sale</span>
            </a>
            <a href="index.php?page=pos_transactions" class="pos-sidebar-link mobile-nav-link <?= $posActivePage === 'pos_transactions' ? 'active' : '' ?>">
                <i class="bi bi-receipt"></i>
                <span>Sales Transactions</span>
            </a>
        </div>

        <div class="pos-sidebar-section">
            <div class="pos-sidebar-section-title">Management</div>
            <a href="index.php?page=pos_customers" class="pos-sidebar-link mobile-nav-link <?= $posActivePage === 'pos_customers' ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>Customers</span>
            </a>
            <a href="index.php?page=pos_products" class="pos-sidebar-link mobile-nav-link <?= $posActivePage === 'pos_products' ? 'active' : '' ?>">
                <i class="bi bi-box-seam"></i>
                <span>Products</span>
            </a>
            <a href="index.php?page=pos_stock" class="pos-sidebar-link mobile-nav-link <?= $posActivePage === 'pos_stock' ? 'active' : '' ?>">
                <i class="bi bi-boxes"></i>
                <span>Inventory</span>
            </a>
        </div>

        <div class="pos-sidebar-section">
            <div class="pos-sidebar-section-title">Reports</div>
            <a href="index.php?page=pos_reports" class="pos-sidebar-link mobile-nav-link <?= $posActivePage === 'pos_reports' ? 'active' : '' ?>">
                <i class="bi bi-bar-chart"></i>
                <span>Sales Reports</span>
            </a>
            <a href="index.php?page=pos_sales_history" class="pos-sidebar-link mobile-nav-link <?= $posActivePage === 'pos_sales_history' ? 'active' : '' ?>">
                <i class="bi bi-clock-history"></i>
                <span>Sales History</span>
            </a>
        </div>

        <div class="pos-sidebar-divider"></div>

        <div class="pos-sidebar-section">
            <div class="pos-sidebar-section-title">System</div>
            <a href="index.php?page=inventory_dashboard" class="pos-sidebar-link mobile-nav-link <?= $posActivePage === 'inventory_dashboard' ? 'active' : '' ?>">
                <i class="bi bi-box-seam"></i>
                <span>Inventory Module</span>
            </a>
            <a href="index.php?page=select_business" class="pos-sidebar-link mobile-nav-link <?= $posActivePage === 'select_business' ? 'active' : '' ?>">
                <i class="bi bi-building"></i>
                <span>Switch Business</span>
            </a>
            <a href="index.php?page=settings" class="pos-sidebar-link mobile-nav-link <?= $posActivePage === 'settings' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i>
                <span>Settings</span>
            </a>
        </div>

        <!-- MOBILE USER -->
        <div class="pos-mobile-user">
            <div class="dropdown">
                <a href="#" class="pos-user-card" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="pos-user-avatar">
                        <?= htmlspecialchars($userInitial) ?>
                    </div>
                    <div class="pos-user-info">
                        <span class="pos-user-name">
                            <?= htmlspecialchars($userName) ?>
                        </span>
                        <span class="pos-user-status">
                            <span class="pos-online-dot"></span>
                            Active Session
                        </span>
                    </div>
                    <i class="bi bi-three-dots-vertical text-muted"></i>
                </a>

                <ul class="dropdown-menu pos-user-dropdown">
                    <li>
                        <a class="dropdown-item" href="index.php?page=settings">
                            <i class="bi bi-gear me-2 text-muted"></i> Settings
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="index.php?page=select_business">
                            <i class="bi bi-arrow-repeat me-2 text-muted"></i> Switch Business
                        </a>
                    </li>
                    <li>
                        <hr class="dropdown-divider my-1">
                    </li>
                    <li>
                        <a class="dropdown-item text-danger fw-semibold" href="index.php?page=logout">
                            <i class="bi bi-box-arrow-right me-2"></i> Log Out
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
    const mobileLinks = document.querySelectorAll('.mobile-nav-link');
    const mobileSidebar = document.getElementById('mobilePosSidebar');

    mobileLinks.forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            const targetUrl = this.getAttribute('href');

            if (mobileSidebar && typeof bootstrap !== 'undefined') {
                const offcanvas = bootstrap.Offcanvas.getInstance(mobileSidebar);
                if (offcanvas) {
                    offcanvas.hide();
                }
            }

            setTimeout(function () {
                window.location.href = targetUrl;
            }, 150);
        });
    });
});
</script>