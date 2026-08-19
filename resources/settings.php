<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = Database::getConnection();

$userId = $_SESSION['user_id'] ?? null;
$businessId = $_SESSION['business_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'staff';

if (!$userId) {
    header('Location: index.php?page=login');
    exit;
}

if (!$businessId) {
    header('Location: index.php?page=select_business');
    exit;
}

$successMessage = '';
$errorMessage = '';

/*
|--------------------------------------------------------------------------
| GET CURRENT BUSINESS
|--------------------------------------------------------------------------
*/

$businessStmt = $pdo->prepare("
    SELECT id, name
    FROM businesses
    WHERE id = ?
    LIMIT 1
");

$businessStmt->execute([$businessId]);

$currentBusiness = $businessStmt->fetch(PDO::FETCH_ASSOC);

if (!$currentBusiness) {
    unset($_SESSION['business_id']);
    unset($_SESSION['business_name']);

    header('Location: index.php?page=select_business');
    exit;
}

$_SESSION['business_name'] = $currentBusiness['name'];


/*
|--------------------------------------------------------------------------
| GET CURRENT USER'S BUSINESS ROLE
|--------------------------------------------------------------------------
*/

$roleStmt = $pdo->prepare("
    SELECT role, status
    FROM business_users
    WHERE business_id = ?
    AND user_id = ?
    LIMIT 1
");

$roleStmt->execute([
    $businessId,
    $userId
]);

$currentBusinessUser = $roleStmt->fetch(PDO::FETCH_ASSOC);

if (!$currentBusinessUser) {
    header('Location: index.php?page=select_business');
    exit;
}

$businessRole = $currentBusinessUser['role'];
$businessStatus = $currentBusinessUser['status'];


/*
|--------------------------------------------------------------------------
| HANDLE POST REQUESTS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | ADD STAFF / ADMIN
    |--------------------------------------------------------------------------
    */

    if ($action === 'add_user') {

        /*
         * Only owner and admin can create users.
         */

        if (!in_array($businessRole, ['owner', 'admin'], true)) {

            $errorMessage =
                'You do not have permission to create users.';

        } else {

            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $newRole = $_POST['role'] ?? 'staff';


            /*
             * Admin can only create staff.
             */

            if (
                $businessRole === 'admin' &&
                $newRole !== 'staff'
            ) {

                $errorMessage =
                    'Administrators can only create staff accounts.';

            } elseif (!in_array($newRole, ['admin', 'staff'], true)) {

                $errorMessage =
                    'Invalid user role selected.';

            } elseif ($name === '') {

                $errorMessage =
                    'Please enter the user name.';

            } elseif ($email === '') {

                $errorMessage =
                    'Please enter the email address.';

            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

                $errorMessage =
                    'Please enter a valid email address.';

            } elseif (strlen($password) < 6) {

                $errorMessage =
                    'Password must be at least 6 characters long.';

            } elseif ($password !== $confirmPassword) {

                $errorMessage =
                    'Passwords do not match.';

            } else {

                /*
                 * Check whether email already exists.
                 */

                $existingStmt = $pdo->prepare("
                    SELECT id, name, email
                    FROM users
                    WHERE email = ?
                    LIMIT 1
                ");

                $existingStmt->execute([$email]);

                $existingUser =
                    $existingStmt->fetch(PDO::FETCH_ASSOC);


                try {

                    $pdo->beginTransaction();


                    /*
                     * If user already exists:
                     *
                     * We do NOT create another users row.
                     *
                     * We simply attach the existing user
                     * to this business.
                     */

                    if ($existingUser) {

                        $newUserId = (int)$existingUser['id'];


                        /*
                         * Check if already belongs to this business.
                         */

                        $membershipStmt = $pdo->prepare("
                            SELECT id
                            FROM business_users
                            WHERE business_id = ?
                            AND user_id = ?
                            LIMIT 1
                        ");

                        $membershipStmt->execute([
                            $businessId,
                            $newUserId
                        ]);

                        $existingMembership =
                            $membershipStmt->fetch(PDO::FETCH_ASSOC);


                        if ($existingMembership) {

                            throw new Exception(
                                'This user already belongs to this business.'
                            );
                        }


                        /*
                         * Add existing user to business.
                         */

                        $membershipInsert = $pdo->prepare("
                            INSERT INTO business_users
                            (
                                business_id,
                                user_id,
                                created_by,
                                role,
                                status
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                ?,
                                'active'
                            )
                        ");

                        $membershipInsert->execute([
                            $businessId,
                            $newUserId,
                            $userId,
                            $newRole
                        ]);


                    } else {

                        /*
                         * Create completely new global user.
                         */

                        $hashedPassword =
                            password_hash(
                                $password,
                                PASSWORD_DEFAULT
                            );


                        $userInsert = $pdo->prepare("
                            INSERT INTO users
                            (
                                name,
                                email,
                                password,
                                role,
                                status
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                ?,
                                'active'
                            )
                        ");

                        $userInsert->execute([
                            $name,
                            $email,
                            $passwordHash ?? $hashedPassword,
                            $newRole
                        ]);

                        $newUserId =
                            (int)$pdo->lastInsertId();


                        /*
                         * Attach new user to current business.
                         */

                        $membershipInsert = $pdo->prepare("
                            INSERT INTO business_users
                            (
                                business_id,
                                user_id,
                                created_by,
                                role,
                                status
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                ?,
                                'active'
                            )
                        ");

                        $membershipInsert->execute([
                            $businessId,
                            $newUserId,
                            $userId,
                            $newRole
                        ]);
                    }


                    $pdo->commit();


                    $successMessage =
                        ucfirst($newRole) .
                        ' account created successfully!';

                } catch (Throwable $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $errorMessage =
                        $e->getMessage();
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CHANGE USER STATUS
    |--------------------------------------------------------------------------
    */

    if ($action === 'change_user_status') {

        if (!in_array($businessRole, ['owner', 'admin'], true)) {

            $errorMessage =
                'You do not have permission to manage users.';

        } else {

            $targetUserId =
                (int)($_POST['user_id'] ?? 0);

            $newStatus =
                $_POST['status'] ?? '';

            if ($targetUserId <= 0) {

                $errorMessage =
                    'Invalid user.';

            } elseif (
                !in_array(
                    $newStatus,
                    ['active', 'inactive'],
                    true
                )
            ) {

                $errorMessage =
                    'Invalid status.';

            } elseif ($targetUserId === (int)$userId) {

                $errorMessage =
                    'You cannot deactivate your own account.';

            } else {

                /*
                 * Admin can only manage staff.
                 */

                if ($businessRole === 'admin') {

                    $targetRoleStmt = $pdo->prepare("
                        SELECT role
                        FROM business_users
                        WHERE business_id = ?
                        AND user_id = ?
                        LIMIT 1
                    ");

                    $targetRoleStmt->execute([
                        $businessId,
                        $targetUserId
                    ]);

                    $targetRole =
                        $targetRoleStmt->fetchColumn();

                    if ($targetRole !== 'staff') {

                        $errorMessage =
                            'Administrators can only manage staff accounts.';
                    }
                }


                if ($errorMessage === '') {

                    $statusStmt = $pdo->prepare("
                        UPDATE business_users
                        SET status = ?
                        WHERE business_id = ?
                        AND user_id = ?
                    ");

                    $statusStmt->execute([
                        $newStatus,
                        $businessId,
                        $targetUserId
                    ]);

                    $successMessage =
                        'User status updated successfully.';
                }
            }
        }
    }


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
            empty($currentPassword) ||
            empty($newPassword) ||
            empty($confirmPassword)
        ) {

            $errorMessage =
                'Please fill in all password fields.';

        } elseif (strlen($newPassword) < 6) {

            $errorMessage =
                'New password must be at least 6 characters long.';

        } elseif ($newPassword !== $confirmPassword) {

            $errorMessage =
                'New passwords do not match.';

        } else {

            $passwordStmt = $pdo->prepare("
                SELECT password
                FROM users
                WHERE id = ?
                LIMIT 1
            ");

            $passwordStmt->execute([
                $userId
            ]);

            $user =
                $passwordStmt->fetch(PDO::FETCH_ASSOC);


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


                $updateStmt = $pdo->prepare("
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
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE BUSINESS NAME
    |--------------------------------------------------------------------------
    */

    if ($action === 'update_business') {

        /*
         * Only owner can rename business.
         */

        if ($businessRole !== 'owner') {

            $errorMessage =
                'Only the business owner can rename the business.';

        } else {

            $newBusinessName =
                trim(
                    $_POST['business_name'] ?? ''
                );


            if ($newBusinessName === '') {

                $errorMessage =
                    'Please provide a valid business name.';

            } else {

                $updateBiz = $pdo->prepare("
                    UPDATE businesses
                    SET name = ?
                    WHERE id = ?
                ");

                $updateBiz->execute([
                    $newBusinessName,
                    $businessId
                ]);


                $_SESSION['business_name'] =
                    $newBusinessName;

                $successMessage =
                    'Business name updated successfully.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| GET BUSINESS USERS
|--------------------------------------------------------------------------
|
| IMPORTANT:
| We ONLY retrieve users belonging to the currently selected
| business. This prevents users from other businesses appearing.
|--------------------------------------------------------------------------
*/

$usersStmt = $pdo->prepare("
    SELECT
        u.id,
        u.name,
        u.email,
        u.created_at AS user_created_at,
        bu.role,
        bu.status,
        bu.created_by,
        bu.created_at AS membership_created_at
    FROM business_users bu
    INNER JOIN users u
        ON u.id = bu.user_id
    WHERE bu.business_id = ?
    ORDER BY
        CASE
            WHEN bu.user_id = ? THEN 0
            WHEN bu.role = 'owner' THEN 1
            WHEN bu.role = 'admin' THEN 2
            ELSE 3
        END,
        u.name ASC
");

$usersStmt->execute([
    $businessId,
    $userId
]);

$businessUsers =
    $usersStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| PAGE TITLE
|--------------------------------------------------------------------------
*/

$pageTitle =
    'Settings - MULTIBUSINESSSYSTEM';


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

include __DIR__ . '/partials/header.php';

?>

<style>

.settings-page {
    min-height: calc(100vh - 70px);
    padding-top: 25px;
    padding-bottom: 50px;
}

.settings-card {
    border: 1px solid var(--bs-border-color) !important;
    transition: box-shadow .2s ease;
}

.settings-card:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,.06) !important;
}

.settings-icon {
    width: 42px;
    height: 42px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(var(--bs-primary-rgb), .10);
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

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(var(--bs-primary-rgb), .10);
    color: var(--bs-primary);
    font-weight: 700;
}

.role-badge {
    font-size: .7rem;
}

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

}

</style>


<div class="settings-page">

<div class="container">

<div class="row justify-content-center">

<div class="col-12 col-xl-9">


<!-- =========================================================
     HEADER
========================================================= -->

<div class="settings-topbar d-flex justify-content-between align-items-center mb-4">

<div>

<h2 class="fw-bold text-body mb-1">
Account & Business Settings
</h2>

<p class="text-muted small mb-0">
Manage your account, business, users and security.
</p>

</div>

<a
href="index.php?page=select_business"
class="btn btn-outline-secondary btn-sm fw-bold"
>
<i class="bi bi-arrow-left me-1"></i>
Businesses
</a>

</div>


<!-- =========================================================
     ALERTS
========================================================= -->

<?php if ($successMessage): ?>

<div class="alert alert-success rounded-3 small">

<i class="bi bi-check-circle me-2"></i>

<?= htmlspecialchars($successMessage) ?>

</div>

<?php endif; ?>


<?php if ($errorMessage): ?>

<div class="alert alert-danger rounded-3 small">

<i class="bi bi-exclamation-circle me-2"></i>

<?= htmlspecialchars($errorMessage) ?>

</div>

<?php endif; ?>


<!-- =========================================================
     BUSINESS USERS
========================================================= -->

<div class="card settings-card shadow-sm border-0 rounded-4 p-4 mb-3">

<div class="d-flex justify-content-between align-items-center mb-4">

<div class="d-flex align-items-center gap-3">

<div class="settings-icon">

<i class="bi bi-people-fill"></i>

</div>

<div>

<div class="settings-title">
Business Users
</div>

<div class="settings-description">
Users who have access to <?= htmlspecialchars($currentBusiness['name']) ?>.
</div>

</div>

</div>


<?php if (in_array($businessRole, ['owner', 'admin'], true)): ?>

<button
type="button"
class="btn btn-primary btn-sm fw-bold"
data-bs-toggle="modal"
data-bs-target="#addUserModal"
>

<i class="bi bi-person-plus-fill me-1"></i>

Add User

</button>

<?php endif; ?>

</div>


<div class="table-responsive">

<table class="table table-hover align-middle mb-0">

<thead>

<tr>

<th>User</th>

<th>Email</th>

<th>Role</th>

<th>Status</th>

<th class="text-end">
Action
</th>

</tr>

</thead>

<tbody>

<?php if (empty($businessUsers)): ?>

<tr>

<td colspan="5" class="text-center text-muted py-4">

No users found.

</td>

</tr>

<?php endif; ?>


<?php foreach ($businessUsers as $businessUser): ?>

<tr>

<td>

<div class="d-flex align-items-center gap-2">

<div class="user-avatar">

<?= strtoupper(
    substr(
        $businessUser['name'],
        0,
        1
    )
) ?>

</div>

<div>

<div class="fw-semibold">

<?= htmlspecialchars(
    $businessUser['name']
) ?>

<?php if (
    (int)$businessUser['id'] ===
    (int)$userId
): ?>

<span class="badge bg-primary-subtle text-primary role-badge ms-1">
You
</span>

<?php endif; ?>

</div>

</div>

</div>

</td>


<td>

<span class="small">

<?= htmlspecialchars(
    $businessUser['email']
) ?>

</span>

</td>


<td>

<?php

$roleClass = match (
    $businessUser['role']
) {

    'owner' =>
        'bg-danger-subtle text-danger',

    'admin' =>
        'bg-primary-subtle text-primary',

    default =>
        'bg-secondary-subtle text-secondary'
};

?>

<span class="badge <?= $roleClass ?> role-badge">

<?= ucfirst(
    htmlspecialchars(
        $businessUser['role']
    )
) ?>

</span>

</td>


<td>

<?php

$statusClass =
    $businessUser['status'] === 'active'
        ? 'bg-success-subtle text-success'
        : 'bg-secondary-subtle text-secondary';

?>

<span class="badge <?= $statusClass ?> role-badge">

<?= ucfirst(
    htmlspecialchars(
        $businessUser['status']
    )
) ?>

</span>

</td>


<td class="text-end">

<?php if (
    (int)$businessUser['id'] !==
    (int)$userId &&
    in_array($businessRole, ['owner', 'admin'], true)
): ?>

<?php

$canManage =
    $businessRole === 'owner' ||
    $businessUser['role'] === 'staff';

?>

<?php if ($canManage): ?>

<form
method="POST"
class="d-inline"
>

<input
type="hidden"
name="action"
value="change_user_status"
>

<input
type="hidden"
name="user_id"
value="<?= (int)$businessUser['id'] ?>"
>

<?php if (
    $businessUser['status'] === 'active'
): ?>

<input
type="hidden"
name="status"
value="inactive"
>

<button
type="submit"
class="btn btn-outline-danger btn-sm"
onclick="return confirm('Deactivate this user?')"
>

<i class="bi bi-person-dash"></i>

Deactivate

</button>

<?php else: ?>

<input
type="hidden"
name="status"
value="active"
>

<button
type="submit"
class="btn btn-outline-success btn-sm"
>

<i class="bi bi-person-check"></i>

Activate

</button>

<?php endif; ?>

</form>

<?php endif; ?>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>


<!-- =========================================================
     BUSINESS NAME
========================================================= -->

<?php if ($businessRole === 'owner'): ?>

<div class="card settings-card shadow-sm border-0 rounded-4 p-4 mb-3">

<div class="d-flex align-items-center gap-3 mb-4">

<div class="settings-icon">

<i class="bi bi-building"></i>

</div>

<div>

<div class="settings-title">
Business Settings
</div>

<div class="settings-description">
Manage your current business information.
</div>

</div>

</div>


<form method="POST">

<input
type="hidden"
name="action"
value="update_business"
>

<label class="form-label small fw-bold">
Business Name
</label>

<div class="input-group">

<input
type="text"
name="business_name"
class="form-control"
value="<?= htmlspecialchars($currentBusiness['name']) ?>"
maxlength="150"
required
>

<button
type="submit"
class="btn btn-primary fw-bold"
>

Save

</button>

</div>

</form>

</div>

<?php endif; ?>


<!-- =========================================================
     SUBSCRIPTION
========================================================= -->

<div class="card settings-card shadow-sm border-0 rounded-4 p-4 mb-3">

<div class="d-flex align-items-center gap-3 mb-3">

<div class="settings-icon">

<i class="bi bi-credit-card-fill"></i>

</div>

<div>

<div class="settings-title">
Subscription & Plans
</div>

<div class="settings-description">
View your business subscription and available plans.
</div>

</div>

</div>


<a
href="index.php?page=subscription"
class="btn btn-outline-primary fw-bold w-100"
>

<i class="bi bi-arrow-right-circle me-1"></i>

Manage Subscriptions

</a>

</div>


<!-- =========================================================
     APPEARANCE
========================================================= -->

<div class="card settings-card shadow-sm border-0 rounded-4 p-4 mb-3">

<div class="d-flex align-items-center gap-3 mb-3">

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

<i class="bi bi-circle-half me-1"></i>

Toggle Dark / Light Mode

</button>

</div>


<!-- =========================================================
     PASSWORD
========================================================= -->

<div class="card settings-card shadow-sm border-0 rounded-4 p-4">

<div class="d-flex align-items-center gap-3 mb-4">

<div class="settings-icon">

<i class="bi bi-shield-lock-fill"></i>

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


<form method="POST">

<input
type="hidden"
name="action"
value="update_password"
>


<div class="mb-3">

<label class="form-label small fw-bold">
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

<label class="form-label small fw-bold">
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

</div>


<div class="mb-4">

<label class="form-label small fw-bold">
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

<i class="bi bi-key-fill me-1"></i>

Update Password

</button>

</form>

</div>


</div>

</div>

</div>

</div>


<!-- =========================================================
     ADD USER MODAL
========================================================= -->

<?php if (in_array($businessRole, ['owner', 'admin'], true)): ?>

<div
class="modal fade"
id="addUserModal"
tabindex="-1"
aria-hidden="true"
>

<div class="modal-dialog modal-dialog-centered">

<div class="modal-content rounded-4 border-0 shadow">

<div class="modal-header">

<h5 class="modal-title fw-bold">

<i class="bi bi-person-plus-fill text-primary me-2"></i>

Add User

</h5>

<button
type="button"
class="btn-close"
data-bs-dismiss="modal"
></button>

</div>


<form method="POST">

<div class="modal-body">

<input
type="hidden"
name="action"
value="add_user"
>


<div class="mb-3">

<label class="form-label fw-semibold">
Name
</label>

<input
type="text"
name="name"
class="form-control"
maxlength="150"
required
>

</div>


<div class="mb-3">

<label class="form-label fw-semibold">
Email
</label>

<input
type="email"
name="email"
class="form-control"
maxlength="150"
required
>

<div class="form-text">
If this email already exists, the existing account will be added to this business.
</div>

</div>


<div class="mb-3">

<label class="form-label fw-semibold">
Role
</label>

<select
name="role"
class="form-select"
required
>

<?php if ($businessRole === 'owner'): ?>

<option value="staff">
Staff
</option>

<option value="admin">
Admin
</option>

<?php else: ?>

<option value="staff">
Staff
</option>

<?php endif; ?>

</select>

</div>


<div class="mb-3">

<label class="form-label fw-semibold">
Password
</label>

<input
type="password"
name="password"
class="form-control"
minlength="6"
required
>

</div>


<div class="mb-3">

<label class="form-label fw-semibold">
Confirm Password
</label>

<input
type="password"
name="confirm_password"
class="form-control"
minlength="6"
required
>

</div>

</div>


<div class="modal-footer">

<button
type="button"
class="btn btn-outline-secondary"
data-bs-dismiss="modal"
>

Cancel

</button>

<button
type="submit"
class="btn btn-primary fw-bold"
>

<i class="bi bi-person-plus-fill me-1"></i>

Create User

</button>

</div>

</form>

</div>

</div>

</div>

<?php endif; ?>


<script>

function toggleTheme() {

    const html =
        document.documentElement;

    const current =
        html.getAttribute('data-bs-theme') || 'light';

    const next =
        current === 'dark'
            ? 'light'
            : 'dark';

    html.setAttribute(
        'data-bs-theme',
        next
    );

    localStorage.setItem(
        'bs-theme',
        next
    );
}

</script>


<?php

include __DIR__ . '/partials/footer.php';

?>