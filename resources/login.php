<?php
// =========================================================
// LOGIN PAGE
// =========================================================
// Note: Database connection and session are already loaded
// via your index.php -> app.php/bootstrap.
// =========================================================

$pdo = Database::getConnection();

$error = '';

/*
|--------------------------------------------------------------------------
| Handle Login
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($email) && !empty($password)) {

        // Query the users table matching your database schema
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

            /*
            |--------------------------------------------------------------------------
            | Regenerate Session ID
            |--------------------------------------------------------------------------
            | Prevents session fixation after successful login.
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
            |
            | This prevents the previous business from remaining active
            | when another login happens.
            |
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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="theme-color"
        content="#0d6efd"
    >

    <title>Login - MULTIBUSINESS SYSTEM</title>


    <!-- =====================================================
         BOOTSTRAP
         ===================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- =====================================================
         BOOTSTRAP ICONS
         ===================================================== -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <!-- =====================================================
         CUSTOM LOGIN CSS
         ===================================================== -->

    <style>

        /* =====================================================
           GLOBAL
           ===================================================== */

        :root {
            --login-primary: #0d6efd;
            --login-primary-dark: #0b5ed7;
            --login-border: #dee2e6;
            --login-bg: #f5f7fb;
            --login-card-bg: #ffffff;
            --login-text: #212529;
            --login-muted: #6c757d;
        }


        * {
            box-sizing: border-box;
        }


        html,
        body {
            width: 100%;
            min-height: 100%;
            margin: 0;
            padding: 0;
        }


        body {
            min-height: 100vh;
            background:
                radial-gradient(
                    circle at top left,
                    rgba(13, 110, 253, .08),
                    transparent 35%
                ),
                radial-gradient(
                    circle at bottom right,
                    rgba(13, 110, 253, .06),
                    transparent 35%
                ),
                var(--login-bg);

            color: var(--login-text);

            font-family:
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Roboto,
                Helvetica,
                Arial,
                sans-serif;
        }


        /* =====================================================
           LOGIN WRAPPER
           ===================================================== */

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


        /* =====================================================
           LOGIN CARD
           ===================================================== */

        .login-card {
            background: var(--login-card-bg);

            border: 1px solid rgba(0, 0, 0, .06);

            border-radius: 22px;

            overflow: hidden;

            box-shadow:
                0 20px 60px rgba(0, 0, 0, .08),
                0 5px 20px rgba(0, 0, 0, .04);
        }


        .login-card-body {
            padding: 42px 40px;
        }


        /* =====================================================
           BRAND
           ===================================================== */

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

            background:
                rgba(
                    var(--bs-primary-rgb),
                    .10
                );

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


        /* =====================================================
           ERROR MESSAGE
           ===================================================== */

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


        /* =====================================================
           FORM
           ===================================================== */

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

            padding:
                10px
                44px
                10px
                43px;

            font-size: .82rem;

            background: #fff;

            color: #212529;

            transition:
                border-color .18s ease,
                box-shadow .18s ease;
        }


        .login-input:focus {
            border-color: var(--login-primary);

            box-shadow:
                0 0 0 3px
                rgba(
                    var(--bs-primary-rgb),
                    .10
                );

            outline: none;
        }


        .login-input::placeholder {
            color: #adb5bd;
        }


        /* =====================================================
           PASSWORD TOGGLE
           ===================================================== */

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

            transition:
                background .15s ease,
                color .15s ease;
        }


        .password-toggle:hover {
            background: #f1f3f5;

            color: #495057;
        }


        /* =====================================================
           LOGIN BUTTON
           ===================================================== */

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

            transition:
                background .18s ease,
                transform .18s ease,
                box-shadow .18s ease;
        }


        .login-button:hover {
            background: var(--login-primary-dark);

            transform: translateY(-1px);

            box-shadow:
                0 7px 18px
                rgba(
                    var(--bs-primary-rgb),
                    .20
                );
        }


        .login-button:active {
            transform: translateY(0);
        }


        .login-button:disabled {
            opacity: .7;

            cursor: not-allowed;

            transform: none;
        }


        /* =====================================================
           FOOTER
           ===================================================== */

        .login-footer {
            text-align: center;

            margin-top: 25px;

            font-size: .68rem;

            color: var(--login-muted);
        }


        .login-footer i {
            margin-right: 4px;
        }


        /* =====================================================
           RESPONSIVE
           ===================================================== */

        @media (max-width: 575.98px) {

            .login-page {
                padding: 20px 14px;
            }


            .login-container {
                max-width: 100%;
            }


            .login-card {
                border-radius: 18px;
            }


            .login-card-body {
                padding: 32px 22px;
            }


            .login-brand {
                margin-bottom: 27px;
            }


            .login-brand-icon {
                width: 58px;
                height: 58px;

                border-radius: 16px;

                font-size: 25px;

                margin-bottom: 15px;
            }


            .login-brand-title {
                font-size: 1.18rem;
            }


            .login-brand-subtitle {
                font-size: .72rem;
            }


            .login-input {
                min-height: 46px;
            }


            .login-button {
                min-height: 46px;
            }

        }


        /* =====================================================
           VERY SMALL PHONES
           ===================================================== */

        @media (max-width: 360px) {

            .login-page {
                padding: 15px 10px;
            }


            .login-card-body {
                padding: 27px 18px;
            }


            .login-brand-title {
                font-size: 1.08rem;
            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     LOGIN PAGE
     ========================================================= -->

<div class="login-page">

    <div class="login-container">

        <div class="login-card">

            <div class="login-card-body">


                <!-- =================================================
                     BRAND
                     ================================================= -->

                <div class="login-brand">

                    <div class="login-brand-icon">

                        <i class="bi bi-buildings-fill"></i>

                    </div>


                    <div class="login-brand-title">

                        MULTIBUSINESS SYSTEM

                    </div>


                    <p class="login-brand-subtitle">

                        Sign in to manage your businesses

                    </p>

                </div>


                <!-- =================================================
                     ERROR
                     ================================================= -->

                <?php if (!empty($error)): ?>

                    <div
                        class="alert alert-danger login-alert"
                        role="alert"
                    >

                        <i class="bi bi-exclamation-circle-fill"></i>

                        <div>

                            <?= htmlspecialchars($error) ?>

                        </div>

                    </div>

                <?php endif; ?>


                <!-- =================================================
                     LOGIN FORM
                     ================================================= -->

                <form
                    action="index.php?page=login"
                    method="POST"
                    id="loginForm"
                >


                    <!-- =================================================
                         EMAIL
                         ================================================= -->

                    <div class="login-form-group">

                        <label
                            for="email"
                            class="login-label"
                        >
                            Email Address
                        </label>


                        <div class="login-input-wrapper">

                            <i class="bi bi-envelope login-input-icon"></i>


                            <input
                                type="email"
                                class="login-input"
                                id="email"
                                name="email"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                required
                                autocomplete="email"
                                placeholder="name@example.com"
                            >

                        </div>

                    </div>


                    <!-- =================================================
                         PASSWORD
                         ================================================= -->

                    <div class="login-form-group">

                        <label
                            for="password"
                            class="login-label"
                        >
                            Password
                        </label>


                        <div class="login-input-wrapper">

                            <i class="bi bi-lock login-input-icon"></i>


                            <input
                                type="password"
                                class="login-input"
                                id="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                placeholder="Enter your password"
                            >


                            <button
                                type="button"
                                class="password-toggle"
                                id="passwordToggle"
                                aria-label="Show password"
                            >

                                <i
                                    class="bi bi-eye"
                                    id="passwordIcon"
                                ></i>

                            </button>

                        </div>

                    </div>


                    <!-- =================================================
                         SUBMIT BUTTON
                         ================================================= -->

                    <div class="mt-4">

                        <button
                            type="submit"
                            class="login-button"
                            id="loginButton"
                        >

                            <i class="bi bi-box-arrow-in-right"></i>

                            <span id="loginButtonText">
                                Sign In
                            </span>

                        </button>

                    </div>

                </form>


                <!-- =================================================
                     FOOTER
                     ================================================= -->

                <div class="login-footer">

                    <i class="bi bi-shield-lock"></i>

                    Secure business management system

                </div>


            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     BOOTSTRAP JAVASCRIPT
     ========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>


<!-- =========================================================
     LOGIN JAVASCRIPT
     ========================================================= -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    /*
    |--------------------------------------------------------------------------
    | Password Show / Hide
    |--------------------------------------------------------------------------
    */

    const passwordInput =
        document.getElementById('password');

    const passwordToggle =
        document.getElementById('passwordToggle');

    const passwordIcon =
        document.getElementById('passwordIcon');


    if (
        passwordInput &&
        passwordToggle &&
        passwordIcon
    ) {

        passwordToggle.addEventListener(
            'click',
            function () {

                if (passwordInput.type === 'password') {

                    passwordInput.type = 'text';

                    passwordIcon.classList.remove(
                        'bi-eye'
                    );

                    passwordIcon.classList.add(
                        'bi-eye-slash'
                    );

                    passwordToggle.setAttribute(
                        'aria-label',
                        'Hide password'
                    );

                } else {

                    passwordInput.type = 'password';

                    passwordIcon.classList.remove(
                        'bi-eye-slash'
                    );

                    passwordIcon.classList.add(
                        'bi-eye'
                    );

                    passwordToggle.setAttribute(
                        'aria-label',
                        'Show password'
                    );

                }

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Prevent Double Submit
    |--------------------------------------------------------------------------
    */

    const loginForm =
        document.getElementById('loginForm');

    const loginButton =
        document.getElementById('loginButton');

    const loginButtonText =
        document.getElementById('loginButtonText');


    if (
        loginForm &&
        loginButton &&
        loginButtonText
    ) {

        loginForm.addEventListener(
            'submit',
            function () {

                loginButton.disabled = true;

                loginButtonText.textContent =
                    'Signing In...';

            }
        );

    }

});

</script>


</body>
</html>