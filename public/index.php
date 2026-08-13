<?php
// Load the core application bootstrap
require_once __DIR__ . '/../app/app.php';

// Determine which page/view to load based on the URL query parameter (e.g., index.php?page=login)
$page = isset($_GET['page']) ? $_GET['page'] : 'login';

switch ($page) {
    case 'login':
        require_once __DIR__ . '/../resources/login.php';
        break;

    case 'select_business':
        // Authentication guard
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }
        require_once __DIR__ . '/../resources/select_business.php';
        break;

    case 'settings':
        // Authentication guard
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }
        require_once __DIR__ . '/../resources/settings.php';
        break;

    case 'subscription':
        // Authentication guard
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }
        require_once __DIR__ . '/../resources/subscription.php';
        break;

    case 'dashboard':
        // Authentication guard
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }
        require_once __DIR__ . '/../modules/LoanManagement/dashboard.php';
        break;

    // Borrowers Module Routes
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

    case 'logout':
        session_destroy();
        header('Location: index.php?page=login');
        exit;

    // Account Module Routes
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

    // Loan Module Routes
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

    // Payments Module Route
    case 'payments':
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }
        require_once __DIR__ . '/../modules/LoanManagement/payments/index.php';
        break;

    // Collateral Module Routes
    case 'collaterals':
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?page=login');
            exit;
        }
        require_once __DIR__ . '/../modules/LoanManagement/collaterals/index.php';
        break;

    default:
        http_response_code(404);
        echo "<div style='font-family: Arial; text-align: center; margin-top: 50px;'>
                <h1>404 - Page Not Found</h1>
                <p>The page you are looking for does not exist.</p>
                <a href='index.php?page=login'>Go back to Login</a>
              </div>";
        break;

        // Reports Module Routes

        case 'reports':
    include __DIR__ . '/../modules/LoanManagement/reports/reports.php';
    break;
}
?>