<?php

// =========================================================
// LOAD APPLICATION BOOTSTRAP
// =========================================================

require_once __DIR__ . '/../app/app.php';


// =========================================================
// GET PAGE
// =========================================================

$page = $_GET['page'] ?? 'login';


// =========================================================
// ROUTING
// =========================================================

switch ($page) {

    // =====================================================
    // LOGIN
    // =====================================================

    case 'login':

        require_once __DIR__ . '/../resources/login.php';

        break;


    // =====================================================
    // REGISTER
    // =====================================================

    case 'register':

        require_once __DIR__ . '/../resources/register.php';

        break;


    case 'register_process':

        require_once __DIR__ . '/../app/register_process.php';

        break;


    // =====================================================
    // SELECT BUSINESS
    // =====================================================

    case 'select_business':

        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }

        require_once __DIR__ . '/../resources/select_business.php';

        break;


    // =====================================================
    // SETTINGS
    // =====================================================

    case 'settings':

        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }

        require_once __DIR__ . '/../resources/settings.php';

        break;


    // =====================================================
    // SUBSCRIPTION
    // =====================================================

    case 'subscription':

        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }

        require_once __DIR__ . '/../resources/subscription.php';

        break;


    // =====================================================
    // DASHBOARD
    // =====================================================

    case 'dashboard':

        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }

        require_once __DIR__ . '/../modules/LoanManagement/dashboard.php';

        break;


    // =====================================================
    // BORROWERS
    // =====================================================

    case 'borrowers':

        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }

        require_once __DIR__ . '/../modules/LoanManagement/borrowers/index.php';

        break;


    case 'borrower_create':

        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }

        require_once __DIR__ . '/../modules/LoanManagement/borrowers/create.php';

        break;


    case 'borrower_details':

        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }

        require_once __DIR__ . '/../modules/LoanManagement/borrowers/details.php';

        break;


    // =====================================================
    // LOAN ACCOUNTS
    // =====================================================

    case 'loan_accounts':

        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }

        require_once __DIR__ . '/../modules/LoanManagement/accounts/index.php';

        break;


    case 'loan_account_details':

        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }

        require_once __DIR__ . '/../modules/LoanManagement/accounts/details.php';

        break;


    // =====================================================
    // LOANS
    // =====================================================

    case 'loans':

        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }

        require_once __DIR__ . '/../modules/LoanManagement/loans/index.php';

        break;


    case 'loan_details':

        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }

        require_once __DIR__ . '/../modules/LoanManagement/loans/details.php';

        break;


    // =====================================================
    // PAYMENTS
    // =====================================================

    case 'payments':

        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }

        require_once __DIR__ . '/../modules/LoanManagement/payments/index.php';

        break;


    // =====================================================
    // COLLATERALS
    // =====================================================

    case 'collaterals':

        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }

        require_once __DIR__ . '/../modules/LoanManagement/collaterals/index.php';

        break;


    // =====================================================
    // REPORTS
    // =====================================================

    case 'reports':

        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }

        require_once __DIR__ . '/../modules/LoanManagement/reports/reports.php';

        break;


    // =====================================================
    // ADMIN PORTAL
    // =====================================================

    case 'admin_portal':

        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }

        if (
            !isset($_SESSION['role']) ||
            $_SESSION['role'] !== 'super_admin'
        ) {
            header('Location: index.php?page=select_business');
            exit;
        }

        require_once __DIR__ . '/../resources/admin_portal.php';

        break;


    // =====================================================
    // ADMIN BUSINESS DETAILS
    // =====================================================

    case 'admin_business_details':

        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }

        if (
            !isset($_SESSION['role']) ||
            $_SESSION['role'] !== 'super_admin'
        ) {
            header('Location: index.php?page=select_business');
            exit;
        }

        require_once __DIR__ . '/../resources/admin_business_details.php';

        break;


    // =====================================================
    // ADMIN USER DETAILS
    // =====================================================

    case 'admin_user_details':

        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }

        if (
            !isset($_SESSION['role']) ||
            $_SESSION['role'] !== 'super_admin'
        ) {
            header('Location: index.php?page=select_business');
            exit;
        }

        require_once __DIR__ . '/../resources/admin_user_details.php';

        break;


    // =====================================================
    // LOGOUT
    // =====================================================

    case 'logout':

        session_destroy();

        header('Location: index.php?page=login');

        exit;


    // =====================================================
    // 404
    // =====================================================

    default:

        http_response_code(404);

        echo "
        <div style='
            font-family: Arial;
            text-align: center;
            margin-top: 50px;
        '>

            <h1>404 - Page Not Found</h1>

            <p>
                The page you are looking for does not exist.
            </p>

            <a href='index.php?page=login'>
                Go back to Login
            </a>

        </div>";

        break;
}
?>