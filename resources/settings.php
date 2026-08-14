<?php

// Note:
// Database connection and session are already loaded
// via index.php -> app.php

$pdo = Database::getConnection();

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    header('Location: index.php?page=login');
    exit;
}

$successMessage = '';
$errorMessage = '';


/*
|--------------------------------------------------------------------------
| Fetch User Businesses
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM businesses
    WHERE owner_id = ?
    ORDER BY business_name ASC
");

$stmt->execute([$userId]);

$userBusinesses =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Handle Form Submissions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action =
        $_POST['action'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | UPDATE PASSWORD
    |--------------------------------------------------------------------------
    */

    if ($action === 'update_password') {

        $currentPassword =
            $_POST['current_password'] ?? '';

        $newPassword =
            $_POST['new_password'] ?? '';

        $confirmPassword =
            $_POST['confirm_password'] ?? '';


        if (
            !empty($currentPassword) &&
            !empty($newPassword) &&
            !empty($confirmPassword)
        ) {

            if (strlen($newPassword) < 6) {

                $errorMessage =
                    'New password must be at least 6 characters long.';

            } elseif ($newPassword !== $confirmPassword) {

                $errorMessage =
                    'New passwords do not match.';

            } else {

                $passwordStmt =
                    $pdo->prepare("
                        SELECT password
                        FROM users
                        WHERE id = ?
                        LIMIT 1
                    ");

                $passwordStmt->execute([
                    $userId
                ]);

                $user =
                    $passwordStmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (
                    $user &&
                    password_verify(
                        $currentPassword,
                        $user['password']
                    )
                ) {

                    $newHash =
                        password_hash(
                            $newPassword,
                            PASSWORD_DEFAULT
                        );


                    $updateStmt =
                        $pdo->prepare("
                            UPDATE users
                            SET password = ?
                            WHERE id = ?
                        ");

                    $updateStmt->execute([
                        $newHash,
                        $userId
                    ]);


                    $successMessage =
                        'Password updated successfully!';

                } else {

                    $errorMessage =
                        'Incorrect current password.';

                }

            }

        } else {

            $errorMessage =
                'Please fill in all password fields.';

        }

    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE BUSINESS NAME
    |--------------------------------------------------------------------------
    */

    if ($action === 'update_business') {

        $targetBusinessId =
            intval(
                $_POST['business_id'] ?? 0
            );

        $newBusinessName =
            trim(
                $_POST['business_name'] ?? ''
            );


        if (
            $targetBusinessId > 0 &&
            $newBusinessName !== ''
        ) {

            /*
             * Verify ownership before update.
             */

            $updateBiz =
                $pdo->prepare("
                    UPDATE businesses
                    SET business_name = ?
                    WHERE id = ?
                    AND owner_id = ?
                ");

            $updateBiz->execute([
                $newBusinessName,
                $targetBusinessId,
                $userId
            ]);


            if ($updateBiz->rowCount() > 0) {

                /*
                 * Update active session business name.
                 */

                if (
                    isset($_SESSION['business_id']) &&
                    $_SESSION['business_id'] ==
                    $targetBusinessId
                ) {

                    $_SESSION['business_name'] =
                        $newBusinessName;
                }


                $successMessage =
                    'Business name updated successfully!';

            } else {

                $errorMessage =
                    'Business could not be updated. Please make sure the business belongs to your account.';

            }


            /*
             * Refresh business list.
             */

            $stmt = $pdo->prepare("
                SELECT *
                FROM businesses
                WHERE owner_id = ?
                ORDER BY business_name ASC
            ");

            $stmt->execute([
                $userId
            ]);

            $userBusinesses =
                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                );

        } else {

            $errorMessage =
                'Please select a business and provide a valid name.';

        }

    }

}


/*
|--------------------------------------------------------------------------
| Page Settings
|--------------------------------------------------------------------------
*/

$pageTitle =
    "Settings - MULTIBUSINESSSYSTEM";


/*
|--------------------------------------------------------------------------
| Shared Header
|--------------------------------------------------------------------------
*/

include __DIR__ . '/partials/header.php';

?>

<style>

/* =========================================================
   SETTINGS
   ========================================================= */

.settings-page {
    min-height: calc(100vh - 70px);

    padding-top: 25px;

    padding-bottom: 50px;
}

.settings-card {
    border: 1px solid var(--bs-border-color) !important;

    transition:
        box-shadow .2s ease,
        border-color .2s ease;
}

.settings-card:hover {
    box-shadow:
        0 8px 25px rgba(0,0,0,.06) !important;
}

.settings-icon {
    width: 42px;
    height: 42px;

    border-radius: 11px;

    display: flex;

    align-items: center;
    justify-content: center;

    background:
        rgba(var(--bs-primary-rgb), .10);

    color: var(--bs-primary);

    font-size: 18px;
}

.settings-title {
    font-size: 1rem;

    font-weight: 700;
}

.settings-description {
    font-size: .78rem;

    color: var(--bs-secondary-color);
}


/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 575.98px) {

    .settings-page {
        padding-top: 18px;
    }

    .settings-topbar {
        align-items: flex-start !important;

        gap: 12px;
    }

    .settings-topbar h2 {
        font-size: 1.2rem;
    }

    .settings-card {
        padding: 1rem !important;
    }

}

</style>


<div class="settings-page">

    <div class="container">

        <div class="row justify-content-center">

            <div class="col-12 col-lg-8">


                <!-- =================================================
                     HEADER
                     ================================================= -->

                <div
                    class="settings-topbar d-flex justify-content-between align-items-center mb-4"
                >

                    <div>

                        <h2
                            class="fw-bold text-body mb-1"
                        >

                            Account & Business Settings

                        </h2>

                        <p
                            class="text-muted small mb-0"
                        >

                            Manage your account, businesses,
                            appearance and password.

                        </p>

                    </div>


                    <a
                        href="index.php?page=select_business"
                        class="btn btn-outline-secondary btn-sm fw-bold text-nowrap"
                    >

                        <i
                            class="bi bi-arrow-left me-1"
                        ></i>

                        Businesses

                    </a>

                </div>


                <!-- =================================================
                     ALERTS
                     ================================================= -->

                <?php if ($successMessage): ?>

                    <div
                        class="alert alert-success rounded-3 small"
                    >

                        <i
                            class="bi bi-check-circle me-2"
                        ></i>

                        <?= htmlspecialchars(
                            $successMessage
                        ) ?>

                    </div>

                <?php endif; ?>


                <?php if ($errorMessage): ?>

                    <div
                        class="alert alert-danger rounded-3 small"
                    >

                        <i
                            class="bi bi-exclamation-circle me-2"
                        ></i>

                        <?= htmlspecialchars(
                            $errorMessage
                        ) ?>

                    </div>

                <?php endif; ?>


                <!-- =================================================
                     APPEARANCE
                     ================================================= -->

                <div
                    class="card settings-card shadow-sm border-0 rounded-4 p-4 mb-3"
                >

                    <div
                        class="d-flex align-items-center gap-3 mb-3"
                    >

                        <div class="settings-icon">

                            <i class="bi bi-moon-stars-fill"></i>

                        </div>

                        <div>

                            <div class="settings-title">
                                Appearance
                            </div>

                            <div class="settings-description">
                                Change between dark and light mode.
                            </div>

                        </div>

                    </div>


                    <button
                        id="themeToggle"
                        type="button"
                        class="btn btn-outline-secondary w-100 fw-semibold"
                        onclick="toggleTheme()"
                    >

                        <i
                            class="bi bi-circle-half me-1"
                        ></i>

                        Toggle Dark / Light Mode

                    </button>

                </div>


                <!-- =================================================
                     SUBSCRIPTION
                     ================================================= -->

                <div
                    class="card settings-card shadow-sm border-0 rounded-4 p-4 mb-3"
                >

                    <div
                        class="d-flex align-items-center gap-3 mb-3"
                    >

                        <div class="settings-icon">

                            <i class="bi bi-credit-card-fill"></i>

                        </div>

                        <div>

                            <div class="settings-title">
                                Subscription & Plans
                            </div>

                            <div class="settings-description">
                                View your business subscription
                                and available plans.
                            </div>

                        </div>

                    </div>


                    <a
                        href="index.php?page=subscription"
                        class="btn btn-outline-primary fw-bold w-100"
                    >

                        <i
                            class="bi bi-arrow-right-circle me-1"
                        ></i>

                        Manage Subscriptions

                    </a>

                </div>


                <!-- =================================================
                     RENAME BUSINESS
                     ================================================= -->

                <?php if (!empty($userBusinesses)): ?>

                    <div
                        class="card settings-card shadow-sm border-0 rounded-4 p-4 mb-3"
                    >

                        <div
                            class="d-flex align-items-center gap-3 mb-4"
                        >

                            <div class="settings-icon">

                                <i class="bi bi-building"></i>

                            </div>

                            <div>

                                <div class="settings-title">
                                    Rename Business
                                </div>

                                <div class="settings-description">
                                    Change the name of one of your businesses.
                                </div>

                            </div>

                        </div>


                        <form
                            action="index.php?page=settings"
                            method="POST"
                        >

                            <input
                                type="hidden"
                                name="action"
                                value="update_business"
                            >


                            <div class="mb-3">

                                <label
                                    class="form-label small fw-bold"
                                >

                                    Select Business

                                </label>


                                <select
                                    name="business_id"
                                    class="form-select"
                                    required
                                >

                                    <option
                                        value=""
                                        disabled
                                        selected
                                    >
                                        Choose a business...
                                    </option>


                                    <?php foreach (
                                        $userBusinesses
                                        as $biz
                                    ): ?>

                                        <option
                                            value="<?= (int)$biz['id'] ?>"
                                        >

                                            <?= htmlspecialchars(
                                                $biz['business_name']
                                            ) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <div class="mb-3">

                                <label
                                    class="form-label small fw-bold"
                                >

                                    New Business Name

                                </label>


                                <input
                                    type="text"
                                    class="form-control"
                                    name="business_name"
                                    placeholder="Enter new business name"
                                    maxlength="150"
                                    required
                                >

                            </div>


                            <button
                                type="submit"
                                class="btn btn-primary fw-bold w-100"
                            >

                                <i
                                    class="bi bi-check-circle me-1"
                                ></i>

                                Save New Name

                            </button>

                        </form>

                    </div>

                <?php endif; ?>


                <!-- =================================================
                     CHANGE PASSWORD
                     ================================================= -->

                <div
                    class="card settings-card shadow-sm border-0 rounded-4 p-4"
                >

                    <div
                        class="d-flex align-items-center gap-3 mb-4"
                    >

                        <div
                            class="settings-icon"
                        >

                            <i
                                class="bi bi-shield-lock-fill"
                            ></i>

                        </div>

                        <div>

                            <div class="settings-title">
                                Change Password
                            </div>

                            <div class="settings-description">
                                Update your account password.
                            </div>

                        </div>

                    </div>


                    <form
                        action="index.php?page=settings"
                        method="POST"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="update_password"
                        >


                        <div class="mb-3">

                            <label
                                class="form-label small fw-bold"
                            >
                                Current Password
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                name="current_password"
                                autocomplete="current-password"
                                required
                            >

                        </div>


                        <div class="mb-3">

                            <label
                                class="form-label small fw-bold"
                            >
                                New Password
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                name="new_password"
                                minlength="6"
                                autocomplete="new-password"
                                required
                            >

                            <div
                                class="form-text"
                                style="font-size:.7rem;"
                            >

                                Minimum 6 characters.

                            </div>

                        </div>


                        <div class="mb-4">

                            <label
                                class="form-label small fw-bold"
                            >
                                Confirm New Password
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                name="confirm_password"
                                minlength="6"
                                autocomplete="new-password"
                                required
                            >

                        </div>


                        <button
                            type="submit"
                            class="btn btn-dark fw-bold w-100"
                        >

                            <i
                                class="bi bi-key-fill me-1"
                            ></i>

                            Update Password

                        </button>

                    </form>

                </div>


            </div>

        </div>

    </div>

</div>


<?php

include __DIR__ . '/partials/footer.php';

?>