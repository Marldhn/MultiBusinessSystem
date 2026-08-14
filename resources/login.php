<?php
// =========================================================
// LOGIN PAGE (MATCHED TO USERS TABLE SCHEMA - WITH APPROVAL)
// =========================================================

$pdo = Database::getConnection();

$error = '';
$success = '';
$register_error = '';
$register_success = '';

// Check for success messages passed from standard URL redirects if any
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

/*
|--------------------------------------------------------------------------
| Handle Registration (POST from Modal)
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $reg_name = trim($_POST['reg_name'] ?? '');
    $reg_email = trim($_POST['reg_email'] ?? '');
    $reg_password = $_POST['reg_password'] ?? '';
    $reg_confirm_password = $_POST['reg_confirm_password'] ?? '';

    if (empty($reg_name) || empty($reg_email) || empty($reg_password) || empty($reg_confirm_password)) {
        $register_error = 'Please fill in all registration fields.';
    } elseif ($reg_password !== $reg_confirm_password) {
        $register_error = 'Passwords do not match.';
    } elseif (strlen($reg_password) < 6) {
        $register_error = 'Password must be at least 6 characters long.';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$reg_email]);
        if ($stmt->fetch()) {
            $register_error = 'Email address is already registered.';
        } else {
            $hashed_password = password_hash($reg_password, PASSWORD_DEFAULT);
            
            try {
                // Insert new user with is_approved = 0 (Pending admin approval)
                // Note: Ensure your database table has the 'is_approved' column (TINYINT default 0)
                $insert = $pdo->prepare("INSERT INTO users (name, email, password, role, is_approved, subscription_status, created_at, updated_at) VALUES (?, ?, ?, 'business_owner', 0, 'active', NOW(), NOW())");
                if ($insert->execute([$reg_name, $reg_email, $hashed_password])) {
                    $success = 'Registration successful! Your account is pending admin approval before you can sign in.';
                } else {
                    $register_error = 'An error occurred during registration. Please try again.';
                }
            } catch (PDOException $e) {
                $register_error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Handle Login
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] === 'login')) {

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($email) && !empty($password)) {

        // Query the users table
        $stmt = $pdo->prepare("
            SELECT *
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);
        $user = $stmt->fetch();

        /*
        |--------------------------------------------------------------------------
        | Verify Login
        |--------------------------------------------------------------------------
        */

        if ($user && password_verify($password, $user['password'])) {

            // Check if account requires admin approval (is_approved == 0)
            if (isset($user['is_approved']) && (int)$user['is_approved'] === 0) {
                $error = 'Your account is pending admin approval. Please wait for activation.';
            } 
            // Optional: Check if subscription is canceled or expired if needed
            elseif (isset($user['subscription_status']) && $user['subscription_status'] === 'canceled') {
                $error = 'Your subscription has been canceled. Please contact support.';
            } else {

                /*
                |--------------------------------------------------------------------------
                | Regenerate Session ID
                |--------------------------------------------------------------------------
                */

                session_regenerate_id(true);

                /*
                |--------------------------------------------------------------------------
                | Set Session Variables
                |--------------------------------------------------------------------------
                */

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                /*
                |--------------------------------------------------------------------------
                | Clear previous business selection
                |--------------------------------------------------------------------------
                */

                unset($_SESSION['business_id']);
                unset($_SESSION['business_name']);

                /*
                |--------------------------------------------------------------------------
                | Redirect to Business Selection
                |--------------------------------------------------------------------------
                */

                header('Location: index.php?page=select_business');
                exit;
            }

        } else {

            $error = 'Invalid email address or password.';

        }

    } else {

        $error = 'Please fill in all fields.';

    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d6efd">
    <title>Login - MULTIBUSINESS SYSTEM</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --login-primary: #0d6efd;
            --login-primary-dark: #0b5ed7;
            --login-border: #dee2e6;
            --login-bg: #f5f7fb;
            --login-card-bg: #ffffff;
            --login-text: #212529;
            --login-muted: #6c757d;
        }

        * { box-sizing: border-box; }

        html, body {
            width: 100%;
            min-height: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            background: radial-gradient(circle at top left, rgba(13, 110, 253, .08), transparent 35%),
                        radial-gradient(circle at bottom right, rgba(13, 110, 253, .06), transparent 35%),
                        var(--login-bg);
            color: var(--login-text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
        }

        .login-container {
            width: 100%;
            max-width: 440px;
        }

        .login-card {
            background: var(--login-card-bg);
            border: 1px solid rgba(0, 0, 0, .06);
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .08), 0 5px 20px rgba(0, 0, 0, .04);
        }

        .login-card-body {
            padding: 42px 40px;
        }

        .login-brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: 32px;
        }

        .login-brand-icon {
            width: 64px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 18px;
            background: rgba(var(--bs-primary-rgb), .10);
            color: var(--login-primary);
            font-size: 29px;
            margin-bottom: 18px;
        }

        .login-brand-title {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -.02em;
            color: var(--login-text);
            margin-bottom: 5px;
        }

        .login-brand-subtitle {
            font-size: .78rem;
            color: var(--login-muted);
            margin: 0;
        }

        .login-alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            border-radius: 12px;
            padding: 12px 14px;
            font-size: .78rem;
            margin-bottom: 22px;
        }

        .login-alert i {
            font-size: 16px;
            margin-top: 1px;
        }

        .login-form-group {
            margin-bottom: 18px;
        }

        .login-label {
            display: block;
            font-size: .75rem;
            font-weight: 700;
            color: #495057;
            margin-bottom: 7px;
        }

        .login-input-wrapper {
            position: relative;
        }

        .login-input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #8a94a6;
            font-size: 16px;
            pointer-events: none;
            z-index: 2;
        }

        .login-input {
            width: 100%;
            min-height: 48px;
            border: 1px solid var(--login-border);
            border-radius: 11px;
            padding: 10px 44px 10px 43px;
            font-size: .82rem;
            background: #fff;
            color: #212529;
            transition: border-color .18s ease, box-shadow .18s ease;
        }

        .login-input:focus {
            border-color: var(--login-primary);
            box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb), .10);
            outline: none;
        }

        .login-input::placeholder { color: #adb5bd; }

        .password-toggle {
            position: absolute;
            right: 7px;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: #8a94a6;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background .15s ease, color .15s ease;
        }

        .password-toggle:hover {
            background: #f1f3f5;
            color: #495057;
        }

        .login-button {
            width: 100%;
            min-height: 48px;
            border: 0;
            border-radius: 11px;
            background: var(--login-primary);
            color: #fff;
            font-size: .82rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background .18s ease, transform .18s ease, box-shadow .18s ease;
        }

        .login-button:hover {
            background: var(--login-primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 7px 18px rgba(var(--bs-primary-rgb), .20);
        }

        .login-footer {
            text-align: center;
            margin-top: 25px;
            font-size: .68rem;
            color: var(--login-muted);
        }

        .login-footer i { margin-right: 4px; }
    </style>
</head>

<body>

<div class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-card-body">

                <div class="login-brand">
                    <div class="login-brand-icon">
                        <i class="bi bi-buildings-fill"></i>
                    </div>
                    <div class="login-brand-title">MULTIBUSINESS SYSTEM</div>
                    <p class="login-brand-subtitle">Sign in to manage your businesses</p>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success login-alert" role="alert">
                        <i class="bi bi-check-circle-fill"></i>
                        <div><?= htmlspecialchars($success) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger login-alert" role="alert">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <form action="index.php?page=login" method="POST" id="loginForm">
                    <input type="hidden" name="action" value="login">

                    <div class="login-form-group">
                        <label for="email" class="login-label">Email Address</label>
                        <div class="login-input-wrapper">
                            <i class="bi bi-envelope login-input-icon"></i>
                            <input type="email" class="login-input" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email" placeholder="name@example.com">
                        </div>
                    </div>

                    <div class="login-form-group">
                        <label for="password" class="login-label">Password</label>
                        <div class="login-input-wrapper">
                            <i class="bi bi-lock login-input-icon"></i>
                            <input type="password" class="login-input" id="password" name="password" required autocomplete="current-password" placeholder="Enter your password">
                            <button type="button" class="password-toggle" id="passwordToggle" aria-label="Show password">
                                <i class="bi bi-eye" id="passwordIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="login-button" id="loginButton">
                            <i class="bi bi-box-arrow-in-right"></i>
                            <span id="loginButtonText">Sign In</span>
                        </button>
                    </div>

                    <div class="text-center mt-3">
                        <p class="small text-muted mb-0" style="font-size: 0.75rem;">
                            Don't have an account? 
                            <a href="#" data-bs-toggle="modal" data-bs-target="#registerModal" class="text-decoration-none fw-bold">Register here</a>
                        </p>
                    </div>
                </form>

                <div class="login-footer">
                    <i class="bi bi-shield-lock"></i> Secure business management system
                </div>

            </div>
        </div>
    </div>
</div>

<!-- =========================================================
     REGISTRATION MODAL
     ========================================================= -->
<div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.15);">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="registerModalLabel" style="font-size: 1.15rem;">Create an Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 pb-4 pt-2">
                
                <?php if (!empty($register_error)): ?>
                    <div class="alert alert-danger login-alert mb-3" role="alert">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <div><?= htmlspecialchars($register_error) ?></div>
                    </div>
                <?php endif; ?>

                <form action="index.php?page=login" method="POST" id="registerForm">
                    <input type="hidden" name="action" value="register">

                    <div class="login-form-group mb-3">
                        <label for="reg_name" class="login-label">Full Name</label>
                        <div class="login-input-wrapper">
                            <i class="bi bi-person login-input-icon"></i>
                            <input type="text" class="login-input" id="reg_name" name="reg_name" value="<?= htmlspecialchars($_POST['reg_name'] ?? '') ?>" required placeholder="John Doe">
                        </div>
                    </div>

                    <div class="login-form-group mb-3">
                        <label for="reg_email" class="login-label">Email Address</label>
                        <div class="login-input-wrapper">
                            <i class="bi bi-envelope login-input-icon"></i>
                            <input type="email" class="login-input" id="reg_email" name="reg_email" value="<?= htmlspecialchars($_POST['reg_email'] ?? '') ?>" required placeholder="name@example.com">
                        </div>
                    </div>

                    <div class="login-form-group mb-3">
                        <label for="reg_password" class="login-label">Password</label>
                        <div class="login-input-wrapper">
                            <i class="bi bi-lock login-input-icon"></i>
                            <input type="password" class="login-input" id="reg_password" name="reg_password" required placeholder="At least 6 characters">
                        </div>
                    </div>

                    <div class="login-form-group mb-4">
                        <label for="reg_confirm_password" class="login-label">Confirm Password</label>
                        <div class="login-input-wrapper">
                            <i class="bi bi-lock-fill login-input-icon"></i>
                            <input type="password" class="login-input" id="reg_confirm_password" name="reg_confirm_password" required placeholder="Re-enter password">
                        </div>
                    </div>

                    <button type="submit" class="login-button w-100">
                        <i class="bi bi-person-plus"></i>
                        <span>Register Account</span>
                    </button>
                </form>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    <?php if (!empty($register_error)): ?>
        var registerModal = new bootstrap.Modal(document.getElementById('registerModal'));
        registerModal.show();
    <?php endif; ?>

    const passwordInput = document.getElementById('password');
    const passwordToggle = document.getElementById('passwordToggle');
    const passwordIcon = document.getElementById('passwordIcon');

    if (passwordInput && passwordToggle && passwordIcon) {
        passwordToggle.addEventListener('click', function () {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.classList.remove('bi-eye');
                passwordIcon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordIcon.classList.remove('bi-eye-slash');
                passwordIcon.classList.add('bi-eye');
            }
        });
    }

    const loginForm = document.getElementById('loginForm');
    const loginButton = document.getElementById('loginButton');
    const loginButtonText = document.getElementById('loginButtonText');

    if (loginForm && loginButton && loginButtonText) {
        loginForm.addEventListener('submit', function () {
            loginButton.disabled = true;
            loginButtonText.textContent = 'Signing In...';
        });
    }
});
</script>

</body>
</html>