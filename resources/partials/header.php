<?php

/*
|--------------------------------------------------------------------------
| START SESSION
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| SESSION TIMEOUT
|--------------------------------------------------------------------------
|
| 5-minute inactivity timeout.
|
*/

$inactiveTimeout = 300;

if (isset($_SESSION['last_activity'])) {

    $inactiveTime = time() - (int)$_SESSION['last_activity'];

    if ($inactiveTime >= $inactiveTimeout) {

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        header('Location: index.php?page=login&timeout=1');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| UPDATE LAST ACTIVITY
|--------------------------------------------------------------------------
*/

$_SESSION['last_activity'] = time();


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$currentUserId = $_SESSION['user_id']
    ?? $_SESSION['id']
    ?? null;

$currentUserName = $_SESSION['name']
    ?? 'User';

$currentUserRole = $_SESSION['role']
    ?? 'staff';

$currentBusinessId = $_SESSION['business_id']
    ?? null;

$currentBusinessName = $_SESSION['business_name']
    ?? 'Business';


/*
|--------------------------------------------------------------------------
| PAGE TITLE
|--------------------------------------------------------------------------
*/

$pageTitle = $pageTitle ?? 'MULTIBUSINESS SYSTEM';


/*
|--------------------------------------------------------------------------
| ESCAPE HELPER
|--------------------------------------------------------------------------
*/

if (!function_exists('e')) {

    function e($value): string
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
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

    <title><?= e($pageTitle) ?></title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Bootstrap Icons -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >


    <!-- Apply saved theme before page renders -->

    <script>

        (function () {

            const savedTheme =
                localStorage.getItem('bs-theme') || 'light';

            document.documentElement.setAttribute(
                'data-bs-theme',
                savedTheme
            );

        })();

    </script>


    <style>

        body {
            min-height: 100vh;
        }

        .main-navbar {
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        .navbar-brand {
            font-weight: 700;
            letter-spacing: -.02em;
        }

        .user-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
        }

        .business-badge {
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .navbar .dropdown-menu {
            border-radius: 12px;
            min-width: 230px;
        }

        .navbar .dropdown-item {
            border-radius: 8px;
            margin: 2px 6px;
            width: auto;
        }

        .navbar .dropdown-item:hover {
            background: var(--bs-tertiary-bg);
        }

        .navbar-user-name {
            max-width: 140px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        @media (max-width: 575.98px) {

            .navbar-brand {
                font-size: .95rem;
            }

            .business-badge {
                max-width: 120px;
            }

            .navbar-user-name {
                display: none;
            }

        }

    </style>

</head>


<body class="bg-body-tertiary">


<!--
|--------------------------------------------------------------------------
| MAIN NAVIGATION
|--------------------------------------------------------------------------
-->

<nav
    class="navbar navbar-expand-lg border-bottom shadow-sm bg-body main-navbar"
>

    <div class="container-fluid px-3 px-md-4">


        <!-- BRAND -->

        <a
            class="navbar-brand text-primary"
            href="index.php?page=select_business"
        >

            <i class="bi bi-grid-1x2-fill me-1"></i>

            MULTIBUSINESS SYSTEM

        </a>


        <!-- MOBILE TOGGLE -->

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNavbar"
            aria-controls="mainNavbar"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >

            <span class="navbar-toggler-icon"></span>

        </button>


        <!-- NAV CONTENT -->

        <div
            class="collapse navbar-collapse"
            id="mainNavbar"
        >

            <div class="ms-auto d-flex align-items-center gap-2 mt-3 mt-lg-0">


                <!-- CURRENT BUSINESS -->

                <?php if ($currentBusinessId): ?>

                    <span
                        class="badge bg-primary bg-opacity-10 text-primary business-badge px-3 py-2"
                        title="<?= e($currentBusinessName) ?>"
                    >

                        <i class="bi bi-building me-1"></i>

                        <?= e($currentBusinessName) ?>

                    </span>

                <?php endif; ?>


                <!-- USER DROPDOWN -->

                <?php if ($currentUserId): ?>

                    <div class="dropdown">

                        <button
                            class="btn btn-outline-secondary d-flex align-items-center gap-2"
                            type="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                        >

                            <span
                                class="user-avatar bg-primary bg-opacity-10 text-primary"
                            >

                                <?= e(
                                    strtoupper(
                                        substr($currentUserName, 0, 1)
                                    )
                                ) ?>

                            </span>


                            <span class="navbar-user-name">

                                <?= e($currentUserName) ?>

                            </span>

                            <i class="bi bi-chevron-down small"></i>

                        </button>


                        <!-- DROPDOWN -->

                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">

                            <li>

                                <div class="px-3 py-2">

                                    <div class="fw-bold text-body">

                                        <?= e($currentUserName) ?>

                                    </div>

                                    <div class="small text-muted">

                                        <?= e(
                                            ucfirst(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $currentUserRole
                                                )
                                            )
                                        ) ?>

                                    </div>

                                    <?php if ($currentBusinessId): ?>

                                        <div class="small text-muted mt-1">

                                            <i class="bi bi-building me-1"></i>

                                            <?= e($currentBusinessName) ?>

                                        </div>

                                    <?php endif; ?>

                                </div>

                            </li>


                            <li>
                                <hr class="dropdown-divider">
                            </li>


                            <!-- SWITCH BUSINESS -->

                            <li>

                                <a
                                    class="dropdown-item"
                                    href="index.php?page=select_business"
                                >

                                    <i class="bi bi-arrow-left-right me-2"></i>

                                    Switch Business

                                </a>

                            </li>


                            <!-- SETTINGS -->

                            <li>

                                <a
                                    class="dropdown-item"
                                    href="index.php?page=settings"
                                >

                                    <i class="bi bi-gear me-2"></i>

                                    Settings

                                </a>

                            </li>


                            <li>
                                <hr class="dropdown-divider">
                            </li>


                            <!-- LOGOUT -->

                            <li>

                                <a
                                    class="dropdown-item text-danger"
                                    href="index.php?page=logout"
                                >

                                    <i class="bi bi-box-arrow-right me-2"></i>

                                    Log Out

                                </a>

                            </li>

                        </ul>

                    </div>

                <?php else: ?>

                    <!-- LOGIN -->

                    <a
                        href="index.php?page=login"
                        class="btn btn-primary btn-sm"
                    >

                        <i class="bi bi-box-arrow-in-right me-1"></i>

                        Log In

                    </a>

                <?php endif; ?>


            </div>

        </div>

    </div>

</nav>


<!--
|--------------------------------------------------------------------------
| PAGE CONTENT
|--------------------------------------------------------------------------
|
| Your individual page content starts after this header.
|
-->