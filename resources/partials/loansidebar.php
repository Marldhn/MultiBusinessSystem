<?php
// =========================================================
// loansidebar.php
// Modern Responsive Sidebar for Loan Management
// Grouped Navigation
// =========================================================

$activePage = $activePage ?? '';

$navGroups = [

    [
        'title' => 'Overview',
        'items' => [
            [
                'page' => 'dashboard',
                'label' => 'Dashboard',
                'icon' => 'bi-grid-1x2-fill'
            ],
        ]
    ],

    [
    'title' => 'Customers',
    'items' => [
        [
            'page' => 'borrowers',
            'label' => 'Borrowers',
            'icon' => 'bi-people-fill'
        ],
        [
            'page' => 'guarantors',
            'label' => 'Guarantors',
            'icon' => 'bi-person-check-fill'
        ],
    ]
],

    [
        'title' => 'Loan Management',
        'items' => [
            [
                'page' => 'loans',
                'label' => 'Loans',
                'icon' => 'bi-file-earmark-text-fill'
            ],
            [
                'page' => 'collaterals',
                'label' => 'Collaterals',
                'icon' => 'bi-shield-check'
            ],
        ]
    ],

    [
        'title' => 'Collections',
        'items' => [
            [
                'page' => 'payments',
                'label' => 'Payments',
                'icon' => 'bi-cash-stack'
            ],
            [
                'page' => 'loan_accounts',
                'label' => 'Accounts / Wallets',
                'icon' => 'bi-wallet2'
            ],
        ]
    ],

    [
        'title' => 'Analytics',
        'items' => [
            [
                'page' => 'reports',
                'label' => 'Reports',
                'icon' => 'bi-bar-chart-line-fill'
            ],
        ]
    ],

];

$businessName = $_SESSION['business_name'] ?? 'LoanSaaS';
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

.loan-sidebar {
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

.loan-sidebar-brand {
    padding: 20px 18px 18px;
    border-bottom: 1px solid var(--bs-border-color);
}

.loan-brand-icon {
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

.loan-brand-name {
    font-size: .9rem;
    font-weight: 700;
    color: var(--bs-body-color);
    line-height: 1.2;
}

.loan-brand-subtitle {
    font-size: .68rem;
    color: var(--bs-secondary-color);
    margin-top: 3px;
}


/* =========================================================
   SIDEBAR NAVIGATION
   ========================================================= */

.loan-sidebar-nav {
    flex: 1;
    overflow-y: auto;
    padding: 15px 12px;
}


/* =========================================================
   NAVIGATION GROUP
   ========================================================= */

.loan-nav-group {
    margin-bottom: 18px;
}

.loan-nav-group:last-child {
    margin-bottom: 0;
}

.loan-nav-title {
    font-size: .62rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--bs-secondary-color);
    padding: 0 10px;
    margin-bottom: 7px;
}


/* =========================================================
   NAVIGATION ITEM
   ========================================================= */

.loan-nav-item {
    margin-bottom: 3px;
}

.loan-nav-link {
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

.loan-nav-link i {
    width: 21px;
    min-width: 21px;
    text-align: center;
    font-size: 16px;
    transition: transform .18s ease;
}

.loan-nav-link:hover {
    color: var(--bs-body-color);
    background: rgba(var(--bs-secondary-rgb), .08);
}

.loan-nav-link:hover i {
    transform: translateX(1px);
}

.loan-nav-link.active {
    color: var(--bs-primary);
    background: rgba(var(--bs-primary-rgb), .10);
}

.loan-nav-link.active::before {
    content: "";
    position: absolute;
    left: -12px;
    top: 8px;
    bottom: 8px;
    width: 3px;
    border-radius: 0 4px 4px 0;
    background: var(--bs-primary);
}

.loan-nav-link.active i {
    color: var(--bs-primary);
}


/* =========================================================
   SIDEBAR FOOTER
   ========================================================= */

.loan-sidebar-footer {
    padding: 12px;
    border-top: 1px solid var(--bs-border-color);
}

.loan-user-card {
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

.loan-user-card:hover {
    background: rgba(var(--bs-secondary-rgb), .10);
    color: var(--bs-body-color);
}

.loan-user-avatar {
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

.loan-user-info {
    min-width: 0;
    flex: 1;
}

.loan-user-name {
    display: block;
    font-size: .78rem;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.loan-user-status {
    display: flex;
    align-items: center;
    gap: 5px;
    color: var(--bs-secondary-color);
    font-size: .65rem;
    margin-top: 2px;
}

.loan-online-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #198754;
}


/* =========================================================
   DROPDOWN
   ========================================================= */

.loan-user-dropdown {
    min-width: 210px;
    padding: 6px;
    border: 1px solid var(--bs-border-color);
    box-shadow: 0 10px 30px rgba(0,0,0,.10);
    border-radius: 12px;
}

.loan-user-dropdown .dropdown-item {
    border-radius: 8px;
    padding: 9px 10px;
    font-size: .78rem;
    font-weight: 500;
}

.loan-user-dropdown .dropdown-item:hover {
    background: rgba(var(--bs-secondary-rgb), .08);
}

.loan-user-dropdown .dropdown-item.text-danger:hover {
    background: rgba(var(--bs-danger-rgb), .08);
}


/* =========================================================
   SCROLLBAR
   ========================================================= */

.loan-sidebar-nav::-webkit-scrollbar {
    width: 5px;
}

.loan-sidebar-nav::-webkit-scrollbar-track {
    background: transparent;
}

.loan-sidebar-nav::-webkit-scrollbar-thumb {
    background: var(--bs-border-color);
    border-radius: 10px;
}


/* =========================================================
   MOBILE HEADER
   ========================================================= */

.loan-mobile-header {
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

.loan-mobile-brand {
    display: flex;
    align-items: center;
    gap: 9px;
    min-width: 0;
}

.loan-mobile-brand-icon {
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

.loan-mobile-business {
    min-width: 0;
}

.loan-mobile-business-name {
    display: block;
    max-width: 210px;
    font-size: .82rem;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.loan-mobile-business-subtitle {
    display: block;
    font-size: .62rem;
    color: var(--bs-secondary-color);
}

.loan-mobile-menu {
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

.loan-mobile-menu:hover {
    background: rgba(var(--bs-secondary-rgb), .08);
}


/* =========================================================
   MOBILE OFFCANVAS
   ========================================================= */

.loan-mobile-offcanvas {
    width: 285px !important;
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
}

.loan-mobile-offcanvas .offcanvas-header {
    min-height: 62px;
    border-bottom: 1px solid var(--bs-border-color);
    padding: 12px 16px;
}

.loan-mobile-offcanvas .offcanvas-body {
    padding: 14px 12px;
}


/* =========================================================
   MOBILE GROUP TITLES
   ========================================================= */

.loan-mobile-section-title {
    font-size: .62rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--bs-secondary-color);
    padding: 6px 10px 8px;
}

.loan-mobile-nav-group {
    margin-bottom: 17px;
}

.loan-mobile-nav-group:last-child {
    margin-bottom: 0;
}


/* =========================================================
   MOBILE USER
   ========================================================= */

.loan-mobile-user {
    margin-top: auto;
    padding: 12px 0 0;
    border-top: 1px solid var(--bs-border-color);
}


/* =========================================================
   MOBILE NAVIGATION
   ========================================================= */

.loan-mobile-offcanvas .loan-nav-link {
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
}

.loan-mobile-offcanvas .loan-nav-link:active {
    transform: scale(.98);
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 991.98px) {

    .loan-sidebar {
        display: none !important;
    }

    .loan-mobile-header {
        display: flex;
    }

}

@media (min-width: 992px) {

    .loan-mobile-offcanvas {
        display: none !important;
    }

}

</style>


<!-- =========================================================
     DESKTOP SIDEBAR
     ========================================================= -->

<aside class="loan-sidebar d-none d-lg-flex">

    <!-- BRAND -->

    <div class="loan-sidebar-brand">

        <a
            href="index.php?page=dashboard"
            class="text-decoration-none d-flex align-items-center gap-3"
        >

            <div class="loan-brand-icon">
                <i class="bi bi-cash-coin"></i>
            </div>

            <div class="overflow-hidden">

                <div class="loan-brand-name text-truncate">
                    <?= htmlspecialchars($businessName) ?>
                </div>

                <div class="loan-brand-subtitle">
                    Loan Management
                </div>

            </div>

        </a>

    </div>


    <!-- NAVIGATION -->

    <div class="loan-sidebar-nav">

        <?php foreach ($navGroups as $group): ?>

            <div class="loan-nav-group">

                <div class="loan-nav-title">
                    <?= htmlspecialchars($group['title']) ?>
                </div>

                <ul class="nav flex-column">

                    <?php foreach ($group['items'] as $item): ?>

                        <?php
                        $isActive = ($activePage === $item['page']);
                        ?>

                        <li class="loan-nav-item">

                            <a
                                href="index.php?page=<?= urlencode($item['page']) ?>"
                                class="loan-nav-link <?= $isActive ? 'active' : '' ?>"
                            >

                                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>

                                <span>
                                    <?= htmlspecialchars($item['label']) ?>
                                </span>

                            </a>

                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endforeach; ?>

    </div>


    <!-- USER FOOTER -->

    <div class="loan-sidebar-footer">

        <div class="dropdown">

            <a
                href="#"
                class="loan-user-card"
                data-bs-toggle="dropdown"
                aria-expanded="false"
            >

                <div class="loan-user-avatar">
                    <?= htmlspecialchars($userInitial) ?>
                </div>

                <div class="loan-user-info">

                    <span class="loan-user-name">
                        <?= htmlspecialchars($userName) ?>
                    </span>

                    <span class="loan-user-status">

                        <span class="loan-online-dot"></span>

                        Active Session

                    </span>

                </div>

                <i class="bi bi-three-dots-vertical text-muted"></i>

            </a>

            <ul class="dropdown-menu dropdown-menu-end loan-user-dropdown">

                <li>

                <a
                        class="dropdown-item"
                        href="index.php?page=settings"
                    >

                        <i class="bi bi-arrow-repeat me-2 text-muted"></i>

                        Settings

                    </a>

                    <a
                        class="dropdown-item"
                        href="index.php?page=select_business"
                    >

                        <i class="bi bi-arrow-repeat me-2 text-muted"></i>

                        Switch Business

                    </a>

                </li>

                <li>

                    <hr class="dropdown-divider my-1">

                </li>

                <li>

                    <a
                        class="dropdown-item text-danger fw-semibold"
                        href="index.php?page=logout"
                    >

                        <i class="bi bi-box-arrow-right me-2"></i>

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

<header class="loan-mobile-header d-lg-none">

    <a
        href="index.php?page=dashboard"
        class="loan-mobile-brand text-decoration-none text-body"
    >

        <div class="loan-mobile-brand-icon">
            <i class="bi bi-cash-coin"></i>
        </div>

        <div class="loan-mobile-business">

            <span class="loan-mobile-business-name">
                <?= htmlspecialchars($businessName) ?>
            </span>

            <span class="loan-mobile-business-subtitle">
                Loan Management
            </span>

        </div>

    </a>

    <button
        type="button"
        class="loan-mobile-menu"
        data-bs-toggle="offcanvas"
        data-bs-target="#mobileSidebar"
        aria-controls="mobileSidebar"
        aria-label="Open navigation menu"
    >

        <i class="bi bi-list"></i>

    </button>

</header>


<!-- =========================================================
     MOBILE OFFCANVAS
     ========================================================= -->

<div
    class="offcanvas offcanvas-start loan-mobile-offcanvas"
    tabindex="-1"
    id="mobileSidebar"
    aria-labelledby="mobileSidebarLabel"
>

    <!-- HEADER -->

    <div class="offcanvas-header">

        <div class="d-flex align-items-center gap-2">

            <div class="loan-brand-icon">

                <i class="bi bi-cash-coin"></i>

            </div>

            <div>

                <h5
                    class="offcanvas-title fw-bold fs-6 mb-0"
                    id="mobileSidebarLabel"
                >

                    <?= htmlspecialchars($businessName) ?>

                </h5>

                <div
                    class="text-muted"
                    style="font-size:.65rem;"
                >

                    Loan Management

                </div>

            </div>

        </div>

        <button
            type="button"
            class="btn-close shadow-none"
            data-bs-dismiss="offcanvas"
            aria-label="Close"
        ></button>

    </div>


    <!-- BODY -->

    <div class="offcanvas-body d-flex flex-column">

        <?php foreach ($navGroups as $group): ?>

            <div class="loan-mobile-nav-group">

                <div class="loan-mobile-section-title">

                    <?= htmlspecialchars($group['title']) ?>

                </div>

                <ul class="nav flex-column gap-1">

                    <?php foreach ($group['items'] as $item): ?>

                        <?php
                        $isActive = ($activePage === $item['page']);
                        ?>

                        <li>

                            <a
                                href="index.php?page=<?= urlencode($item['page']) ?>"
                                class="loan-nav-link mobile-nav-link <?= $isActive ? 'active' : '' ?>"
                                data-page="<?= htmlspecialchars($item['page']) ?>"
                            >

                                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>

                                <span>
                                    <?= htmlspecialchars($item['label']) ?>
                                </span>

                            </a>

                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endforeach; ?>


        <!-- MOBILE USER -->

        <div class="loan-mobile-user">

            <div class="dropdown">

                <a
                    href="#"
                    class="loan-user-card"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >

                    <div class="loan-user-avatar">
                        <?= htmlspecialchars($userInitial) ?>
                    </div>

                    <div class="loan-user-info">

                        <span class="loan-user-name">
                            <?= htmlspecialchars($userName) ?>
                        </span>

                        <span class="loan-user-status">

                            <span class="loan-online-dot"></span>

                            Active Session

                        </span>

                    </div>

                    <i class="bi bi-three-dots-vertical text-muted"></i>

                </a>

                <ul class="dropdown-menu loan-user-dropdown">

                    <li>

                        <a
                            class="dropdown-item"
                            href="index.php?page=select_business"
                        >

                            <i class="bi bi-arrow-repeat me-2 text-muted"></i>

                            Switch Business

                        </a>

                    </li>

                    <li>

                        <hr class="dropdown-divider my-1">

                    </li>

                    <li>

                        <a
                            class="dropdown-item text-danger fw-semibold"
                            href="index.php?page=logout"
                        >

                            <i class="bi bi-box-arrow-right me-2"></i>

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

    const mobileLinks = document.querySelectorAll('.mobile-nav-link');

    const mobileSidebar = document.getElementById('mobileSidebar');

    mobileLinks.forEach(function (link) {

        link.addEventListener('click', function (event) {

            event.preventDefault();

            const targetUrl = this.getAttribute('href');

            if (
                mobileSidebar &&
                typeof bootstrap !== 'undefined'
            ) {

                const offcanvas =
                    bootstrap.Offcanvas.getInstance(mobileSidebar);

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