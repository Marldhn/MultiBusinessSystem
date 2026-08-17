<?php
$posActivePage = $activePage ?? 'pos_dashboard';
?>

<style>
.pos-sidebar {
    width: 250px;
    min-height: 100vh;
    flex-shrink: 0;
    background: var(--bs-body-bg);
    border-right: 1px solid var(--bs-border-color);
    transition: all .2s ease;
}

.pos-sidebar-header {
    padding: 20px;
    border-bottom: 1px solid var(--bs-border-color);
}

.pos-sidebar-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    color: var(--bs-body-color);
}

.pos-sidebar-logo {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bs-primary);
    color: #fff;
    border-radius: 10px;
    font-size: 1.2rem;
}

.pos-sidebar-title {
    font-weight: 700;
    font-size: 1rem;
}

.pos-sidebar-subtitle {
    font-size: .7rem;
    color: var(--bs-secondary-color);
}

.pos-sidebar-body {
    padding: 15px 12px;
}

.pos-sidebar-section {
    margin-bottom: 20px;
}

.pos-sidebar-section-title {
    font-size: .65rem;
    font-weight: 700;
    color: var(--bs-secondary-color);
    text-transform: uppercase;
    letter-spacing: .08em;
    padding: 0 10px;
    margin-bottom: 7px;
}

.pos-sidebar-link {
    display: flex;
    align-items: center;
    gap: 11px;
    width: 100%;
    padding: 10px 12px;
    margin-bottom: 3px;
    border-radius: 9px;
    color: var(--bs-secondary-color);
    text-decoration: none;
    font-size: .82rem;
    font-weight: 600;
    transition: all .15s ease;
}

.pos-sidebar-link i {
    width: 20px;
    text-align: center;
    font-size: 1rem;
}

.pos-sidebar-link:hover {
    background: var(--bs-primary-bg-subtle);
    color: var(--bs-primary);
}

.pos-sidebar-link.active {
    background: var(--bs-primary-bg-subtle);
    color: var(--bs-primary);
}

.pos-sidebar-divider {
    border-top: 1px solid var(--bs-border-color);
    margin: 15px 5px;
}

.pos-business-card {
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 11px;
    padding: 12px;
    margin-bottom: 15px;
}

.pos-business-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bs-primary-bg-subtle);
    color: var(--bs-primary);
}

.pos-business-name {
    font-size: .78rem;
    font-weight: 700;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pos-business-label {
    font-size: .65rem;
    color: var(--bs-secondary-color);
}

@media (max-width: 991.98px) {
    .pos-sidebar {
        width: 100%;
        min-height: auto;
        border-right: 0;
        border-bottom: 1px solid var(--bs-border-color);
    }

    .pos-sidebar-body {
        padding: 10px;
    }

    .pos-sidebar-section-title {
        display: none;
    }

    .pos-sidebar-section {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        margin-bottom: 5px;
    }

    .pos-sidebar-link {
        width: auto;
        margin: 0;
    }

    .pos-sidebar-link span {
        display: none;
    }

    .pos-sidebar-divider,
    .pos-business-card {
        display: none;
    }
}
</style>

<aside class="pos-sidebar">

    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="pos-sidebar-header">

        <a
            href="index.php?page=pos_dashboard"
            class="pos-sidebar-brand"
        >

            <div class="pos-sidebar-logo">
                <i class="bi bi-cart3"></i>
            </div>

            <div>

                <div class="pos-sidebar-title">
                    POS
                </div>

                <div class="pos-sidebar-subtitle">
                    Point of Sale
                </div>

            </div>

        </a>

    </div>


    <!-- =====================================================
         SIDEBAR BODY
    ====================================================== -->

    <div class="pos-sidebar-body">

        <!-- BUSINESS -->

        <div class="pos-business-card">

            <div class="d-flex align-items-center gap-2">

                <div class="pos-business-icon">

                    <i class="bi bi-building"></i>

                </div>

                <div class="flex-grow-1 min-w-0">

                    <div class="pos-business-label">
                        Current Business
                    </div>

                    <div class="pos-business-name">

                        <?= htmlspecialchars(
                            $_SESSION['business_name'] ?? 'Business'
                        ) ?>

                    </div>

                </div>

            </div>

        </div>


        <!-- MAIN -->

        <div class="pos-sidebar-section">

            <div class="pos-sidebar-section-title">
                Main
            </div>


            <!-- Dashboard -->

            <a
                href="index.php?page=pos_dashboard"
                class="pos-sidebar-link <?= $posActivePage === 'pos_dashboard' ? 'active' : '' ?>"
            >

                <i class="bi bi-grid-1x2"></i>

                <span>
                    Dashboard
                </span>

            </a>


            <!-- New Sale -->

            <a
                href="index.php?page=pos_sales"
                class="pos-sidebar-link <?= $posActivePage === 'pos_sales' ? 'active' : '' ?>"
            >

                <i class="bi bi-cart-plus"></i>

                <span>
                    New Sale
                </span>

            </a>


            <!-- Sales -->

            <a
                href="index.php?page=pos_transactions"
                class="pos-sidebar-link <?= $posActivePage === 'pos_transactions' ? 'active' : '' ?>"
            >

                <i class="bi bi-receipt"></i>

                <span>
                    Sales Transactions
                </span>

            </a>

        </div>


        <!-- MANAGEMENT -->

        <div class="pos-sidebar-section">

            <div class="pos-sidebar-section-title">
                Management
            </div>


            <!-- Customers -->

            <a
                href="index.php?page=pos_customers"
                class="pos-sidebar-link <?= $posActivePage === 'pos_customers' ? 'active' : '' ?>"
            >

                <i class="bi bi-people"></i>

                <span>
                    Customers
                </span>

            </a>


            <!-- Products -->

            <a
                href="index.php?page=pos_products"
                class="pos-sidebar-link"
            >

                <i class="bi bi-box-seam"></i>

                <span>
                    Products
                </span>

            </a>


            <!-- Inventory -->

            <a
                href="index.php?page=pos_stock"
                class="pos-sidebar-link"
            >

                <i class="bi bi-boxes"></i>

                <span>
                    Inventory
                </span>

            </a>

        </div>


        <!-- REPORTS -->

        <div class="pos-sidebar-section">

            <div class="pos-sidebar-section-title">
                Reports
            </div>


            <!-- Sales Reports -->

            <a
                href="index.php?page=pos_reports"
                class="pos-sidebar-link <?= $posActivePage === 'pos_reports' ? 'active' : '' ?>"
            >

                <i class="bi bi-bar-chart"></i>

                <span>
                    Sales Reports
                </span>

            </a>


            <!-- Sales History -->

            <a
                href="index.php?page=pos_sales_history"
                class="pos-sidebar-link <?= $posActivePage === 'pos_sales_history' ? 'active' : '' ?>"
            >

                <i class="bi bi-clock-history"></i>

                <span>
                    Sales History
                </span>

            </a>

        </div>


        <div class="pos-sidebar-divider"></div>


        <!-- SYSTEM -->

        <div class="pos-sidebar-section">

            <div class="pos-sidebar-section-title">
                System
            </div>


            <!-- Inventory -->

            <a
                href="index.php?page=inventory_dashboard"
                class="pos-sidebar-link"
            >

                <i class="bi bi-box-seam"></i>

                <span>
                    Inventory Module
                </span>

            </a>


            <!-- Business Selection -->

            <a
                href="index.php?page=select_business"
                class="pos-sidebar-link"
            >

                <i class="bi bi-building"></i>

                <span>
                    Switch Business
                </span>

            </a>


            <!-- Settings -->

            <a
                href="index.php?page=settings"
                class="pos-sidebar-link"
            >

                <i class="bi bi-gear"></i>

                <span>
                    Settings
                </span>

            </a>


            <!-- Logout -->

            <a
                href="index.php?page=logout"
                class="pos-sidebar-link text-danger"
            >

                <i class="bi bi-box-arrow-right"></i>

                <span>
                    Logout
                </span>

            </a>

        </div>

    </div>

</aside>
