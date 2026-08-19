<?php
$pdo = Database::getConnection();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$userId = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$globalRole = $_SESSION['role'] ?? '';

if (!$userId) {
    header('Location: index.php?page=login');
    exit;
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectUsers(string $message = '', string $type = 'success'): void
{
    $url = 'index.php?page=user_management';

    if ($message !== '') {
        $url .= '&message=' . urlencode($message);
        $url .= '&type=' . urlencode($type);
    }

    header('Location: ' . $url);
    exit;
}

/*
|--------------------------------------------------------------------------
| ACCESS
|--------------------------------------------------------------------------
|
| Only super_admin can manage all users.
| Admin can manage users that belong to businesses where they are owner/admin.
|
*/

$canManageUsers = false;

if ($globalRole === 'super_admin') {
    $canManageUsers = true;
} else {
    $permissionStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM business_users
        WHERE user_id = ?
        AND role IN ('owner', 'admin')
        AND status = 'active'
    ");

    $permissionStmt->execute([$userId]);

    $canManageUsers = (int)$permissionStmt->fetchColumn() > 0;
}

if (!$canManageUsers) {
    http_response_code(403);
    exit('You do not have permission to manage users.');
}

/*
|--------------------------------------------------------------------------
| CURRENT USER BUSINESS ACCESS
|--------------------------------------------------------------------------
|
| Non-super-admin users may only manage users in businesses
| where they are owner/admin.
|
*/

$managedBusinessIds = [];

if ($globalRole !== 'super_admin') {

    $managedBusinessStmt = $pdo->prepare("
        SELECT business_id
        FROM business_users
        WHERE user_id = ?
        AND role IN ('owner', 'admin')
        AND status = 'active'
    ");

    $managedBusinessStmt->execute([$userId]);

    $managedBusinessIds = array_map(
        'intval',
        $managedBusinessStmt->fetchAll(PDO::FETCH_COLUMN)
    );
}

/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | CREATE USER
    |--------------------------------------------------------------------------
    */

    if ($action === 'create_user') {

        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? 'staff';
        $status = $_POST['status'] ?? 'active';

        if ($name === '') {
            redirectUsers('Full name is required.', 'danger');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectUsers('Please enter a valid email address.', 'danger');
        }

        if (strlen($password) < 8) {
            redirectUsers('Password must contain at least 8 characters.', 'danger');
        }

        if ($password !== $confirmPassword) {
            redirectUsers('Passwords do not match.', 'danger');
        }

        if (!in_array($role, ['super_admin', 'admin', 'staff'], true)) {
            $role = 'staff';
        }

        if (!in_array($status, ['active', 'inactive', 'pending'], true)) {
            $status = 'active';
        }

        /*
        |----------------------------------------------------------------------
        | Prevent non-super-admin from creating super_admin
        |----------------------------------------------------------------------
        */

        if ($globalRole !== 'super_admin' && $role === 'super_admin') {
            redirectUsers(
                'You are not allowed to create a Super Admin account.',
                'danger'
            );
        }

        /*
        |----------------------------------------------------------------------
        | Check email
        |----------------------------------------------------------------------
        */

        $emailStmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $emailStmt->execute([$email]);

        if ($emailStmt->fetch()) {
            redirectUsers(
                'A user with this email address already exists.',
                'danger'
            );
        }

        /*
        |----------------------------------------------------------------------
        | Create user
        |----------------------------------------------------------------------
        */

        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        try {

            $pdo->beginTransaction();

            $insertStmt = $pdo->prepare("
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
                    ?
                )
            ");

            $insertStmt->execute([
                $name,
                $email,
                $hashedPassword,
                $role,
                $status
            ]);

            $newUserId = (int)$pdo->lastInsertId();

            $pdo->commit();

            redirectUsers(
                'User created successfully.',
                'success'
            );

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            redirectUsers(
                'Unable to create user: ' . $e->getMessage(),
                'danger'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE USER
    |--------------------------------------------------------------------------
    */

    if ($action === 'update_user') {

        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $role = $_POST['role'] ?? 'staff';
        $status = $_POST['status'] ?? 'active';
        $password = $_POST['password'] ?? '';

        if (!$targetUserId) {
            redirectUsers('Invalid user.', 'danger');
        }

        if ($name === '') {
            redirectUsers('Full name is required.', 'danger');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectUsers('Please enter a valid email address.', 'danger');
        }

        if (!in_array($role, ['super_admin', 'admin', 'staff'], true)) {
            $role = 'staff';
        }

        if (!in_array($status, ['active', 'inactive', 'pending'], true)) {
            $status = 'active';
        }

        /*
        |----------------------------------------------------------------------
        | Prevent non-super-admin from modifying super admin
        |----------------------------------------------------------------------
        */

        if ($globalRole !== 'super_admin') {

            $targetRoleStmt = $pdo->prepare("
                SELECT role
                FROM users
                WHERE id = ?
                LIMIT 1
            ");

            $targetRoleStmt->execute([$targetUserId]);

            $targetRole = $targetRoleStmt->fetchColumn();

            if ($targetRole === 'super_admin') {
                redirectUsers(
                    'You cannot modify a Super Admin account.',
                    'danger'
                );
            }

            if ($role === 'super_admin') {
                redirectUsers(
                    'You cannot assign the Super Admin role.',
                    'danger'
                );
            }

            if ($targetUserId === $userId && $status === 'inactive') {
                redirectUsers(
                    'You cannot deactivate your own account.',
                    'danger'
                );
            }
        }

        /*
        |----------------------------------------------------------------------
        | Check duplicate email
        |----------------------------------------------------------------------
        */

        $emailStmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            AND id <> ?
            LIMIT 1
        ");

        $emailStmt->execute([
            $email,
            $targetUserId
        ]);

        if ($emailStmt->fetch()) {
            redirectUsers(
                'Another user is already using this email.',
                'danger'
            );
        }

        try {

            if ($password !== '') {

                if (strlen($password) < 8) {
                    redirectUsers(
                        'New password must contain at least 8 characters.',
                        'danger'
                    );
                }

                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $updateStmt = $pdo->prepare("
                    UPDATE users
                    SET
                        name = ?,
                        email = ?,
                        password = ?,
                        role = ?,
                        status = ?
                    WHERE id = ?
                ");

                $updateStmt->execute([
                    $name,
                    $email,
                    $hashedPassword,
                    $role,
                    $status,
                    $targetUserId
                ]);

            } else {

                $updateStmt = $pdo->prepare("
                    UPDATE users
                    SET
                        name = ?,
                        email = ?,
                        role = ?,
                        status = ?
                    WHERE id = ?
                ");

                $updateStmt->execute([
                    $name,
                    $email,
                    $role,
                    $status,
                    $targetUserId
                ]);
            }

            redirectUsers(
                'User updated successfully.',
                'success'
            );

        } catch (Throwable $e) {

            redirectUsers(
                'Unable to update user: ' . $e->getMessage(),
                'danger'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ASSIGN BUSINESS
    |--------------------------------------------------------------------------
    */

    if ($action === 'assign_business') {

        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $businessId = (int)($_POST['business_id'] ?? 0);
        $businessRole = $_POST['business_role'] ?? 'staff';

        if (!$targetUserId || !$businessId) {
            redirectUsers(
                'User and business are required.',
                'danger'
            );
        }

        if (!in_array($businessRole, ['owner', 'admin', 'staff'], true)) {
            $businessRole = 'staff';
        }

        /*
        |----------------------------------------------------------------------
        | Non-super-admin may only assign businesses they manage
        |----------------------------------------------------------------------
        */

        if (
            $globalRole !== 'super_admin' &&
            !in_array($businessId, $managedBusinessIds, true)
        ) {
            redirectUsers(
                'You do not have permission to assign users to this business.',
                'danger'
            );
        }

        /*
        |----------------------------------------------------------------------
        | Prevent normal admin from assigning owner
        |----------------------------------------------------------------------
        */

        if (
            $globalRole !== 'super_admin' &&
            $businessRole === 'owner'
        ) {
            redirectUsers(
                'Only Super Admin can assign the Owner role.',
                'danger'
            );
        }

        /*
        |----------------------------------------------------------------------
        | Verify user exists
        |----------------------------------------------------------------------
        */

        $userExistsStmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        $userExistsStmt->execute([$targetUserId]);

        if (!$userExistsStmt->fetch()) {
            redirectUsers(
                'Selected user does not exist.',
                'danger'
            );
        }

        /*
        |----------------------------------------------------------------------
        | Verify business exists
        |----------------------------------------------------------------------
        */

        $businessExistsStmt = $pdo->prepare("
            SELECT id
            FROM businesses
            WHERE id = ?
            LIMIT 1
        ");

        $businessExistsStmt->execute([$businessId]);

        if (!$businessExistsStmt->fetch()) {
            redirectUsers(
                'Selected business does not exist.',
                'danger'
            );
        }

        /*
        |----------------------------------------------------------------------
        | Check existing access
        |----------------------------------------------------------------------
        */

        $existingStmt = $pdo->prepare("
            SELECT id
            FROM business_users
            WHERE business_id = ?
            AND user_id = ?
            LIMIT 1
        ");

        $existingStmt->execute([
            $businessId,
            $targetUserId
        ]);

        $existingAccess = $existingStmt->fetchColumn();

        if ($existingAccess) {

            $updateAccessStmt = $pdo->prepare("
                UPDATE business_users
                SET
                    role = ?,
                    status = 'active'
                WHERE id = ?
            ");

            $updateAccessStmt->execute([
                $businessRole,
                $existingAccess
            ]);

            redirectUsers(
                'Business access updated successfully.',
                'success'
            );
        }

        /*
        |----------------------------------------------------------------------
        | Create business access
        |----------------------------------------------------------------------
        */

        $insertAccessStmt = $pdo->prepare("
            INSERT INTO business_users
            (
                business_id,
                user_id,
                role,
                status
            )
            VALUES
            (
                ?,
                ?,
                ?,
                'active'
            )
        ");

        $insertAccessStmt->execute([
            $businessId,
            $targetUserId,
            $businessRole
        ]);

        redirectUsers(
            'Business access assigned successfully.',
            'success'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE BUSINESS ACCESS
    |--------------------------------------------------------------------------
    */

    if ($action === 'update_business_access') {

        $businessUserId = (int)($_POST['business_user_id'] ?? 0);
        $businessRole = $_POST['business_role'] ?? 'staff';
        $businessStatus = $_POST['business_status'] ?? 'active';

        if (!$businessUserId) {
            redirectUsers(
                'Invalid business access.',
                'danger'
            );
        }

        if (!in_array($businessRole, ['owner', 'admin', 'staff'], true)) {
            $businessRole = 'staff';
        }

        if (!in_array($businessStatus, ['active', 'inactive'], true)) {
            $businessStatus = 'active';
        }

        $accessStmt = $pdo->prepare("
            SELECT
                bu.id,
                bu.business_id,
                bu.user_id,
                bu.role,
                u.role AS global_role
            FROM business_users bu
            INNER JOIN users u
                ON u.id = bu.user_id
            WHERE bu.id = ?
            LIMIT 1
        ");

        $accessStmt->execute([$businessUserId]);

        $access = $accessStmt->fetch(PDO::FETCH_ASSOC);

        if (!$access) {
            redirectUsers(
                'Business access record not found.',
                'danger'
            );
        }

        /*
        |----------------------------------------------------------------------
        | Permission check
        |----------------------------------------------------------------------
        */

        if (
            $globalRole !== 'super_admin' &&
            !in_array(
                (int)$access['business_id'],
                $managedBusinessIds,
                true
            )
        ) {
            redirectUsers(
                'You do not have permission to modify this business access.',
                'danger'
            );
        }

        /*
        |----------------------------------------------------------------------
        | Owner restriction
        |----------------------------------------------------------------------
        */

        if (
            $globalRole !== 'super_admin' &&
            $businessRole === 'owner'
        ) {
            redirectUsers(
                'Only Super Admin can assign the Owner role.',
                'danger'
            );
        }

        /*
        |----------------------------------------------------------------------
        | Prevent self-deactivation
        |----------------------------------------------------------------------
        */

        if (
            (int)$access['user_id'] === $userId &&
            $businessStatus === 'inactive'
        ) {
            redirectUsers(
                'You cannot remove your own access.',
                'danger'
            );
        }

        $updateAccessStmt = $pdo->prepare("
            UPDATE business_users
            SET
                role = ?,
                status = ?
            WHERE id = ?
        ");

        $updateAccessStmt->execute([
            $businessRole,
            $businessStatus,
            $businessUserId
        ]);

        redirectUsers(
            'Business access updated successfully.',
            'success'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | REMOVE BUSINESS ACCESS
    |--------------------------------------------------------------------------
    */

    if ($action === 'remove_business_access') {

        $businessUserId = (int)($_POST['business_user_id'] ?? 0);

        if (!$businessUserId) {
            redirectUsers(
                'Invalid business access.',
                'danger'
            );
        }

        $accessStmt = $pdo->prepare("
            SELECT
                id,
                business_id,
                user_id
            FROM business_users
            WHERE id = ?
            LIMIT 1
        ");

        $accessStmt->execute([$businessUserId]);

        $access = $accessStmt->fetch(PDO::FETCH_ASSOC);

        if (!$access) {
            redirectUsers(
                'Business access record not found.',
                'danger'
            );
        }

        if (
            $globalRole !== 'super_admin' &&
            !in_array(
                (int)$access['business_id'],
                $managedBusinessIds,
                true
            )
        ) {
            redirectUsers(
                'You do not have permission to remove this business access.',
                'danger'
            );
        }

        if ((int)$access['user_id'] === $userId) {
            redirectUsers(
                'You cannot remove your own business access.',
                'danger'
            );
        }

        $deleteAccessStmt = $pdo->prepare("
            DELETE FROM business_users
            WHERE id = ?
        ");

        $deleteAccessStmt->execute([
            $businessUserId
        ]);

        redirectUsers(
            'Business access removed successfully.',
            'success'
        );
    }
}

/*
|--------------------------------------------------------------------------
| FLASH MESSAGE
|--------------------------------------------------------------------------
*/

$message = $_GET['message'] ?? '';
$messageType = $_GET['type'] ?? 'success';

if (!in_array($messageType, ['success', 'danger', 'warning', 'info'], true)) {
    $messageType = 'success';
}

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');

$searchLike = '%' . $search . '%';

/*
|--------------------------------------------------------------------------
| LOAD USERS
|--------------------------------------------------------------------------
*/

if ($globalRole === 'super_admin') {

    $usersStmt = $pdo->prepare("
        SELECT
            u.id,
            u.name,
            u.email,
            u.role,
            u.status,
            u.created_at,
            u.updated_at,
            COUNT(DISTINCT bu.business_id) AS business_count
        FROM users u
        LEFT JOIN business_users bu
            ON bu.user_id = u.id
        WHERE
            u.name LIKE ?
            OR u.email LIKE ?
        GROUP BY
            u.id,
            u.name,
            u.email,
            u.role,
            u.status,
            u.created_at,
            u.updated_at
        ORDER BY u.created_at DESC
    ");

    $usersStmt->execute([
        $searchLike,
        $searchLike
    ]);

} else {

    if (empty($managedBusinessIds)) {
        $users = [];
    } else {

        $placeholders = implode(
            ',',
            array_fill(0, count($managedBusinessIds), '?')
        );

        $usersSql = "
            SELECT
                u.id,
                u.name,
                u.email,
                u.role,
                u.status,
                u.created_at,
                u.updated_at,
                COUNT(DISTINCT bu2.business_id) AS business_count
            FROM users u
            INNER JOIN business_users bu
                ON bu.user_id = u.id
                AND bu.business_id IN ($placeholders)
            LEFT JOIN business_users bu2
                ON bu2.user_id = u.id
            WHERE
                u.name LIKE ?
                OR u.email LIKE ?
            GROUP BY
                u.id,
                u.name,
                u.email,
                u.role,
                u.status,
                u.created_at,
                u.updated_at
            ORDER BY u.created_at DESC
        ";

        $usersStmt = $pdo->prepare($usersSql);

        $params = $managedBusinessIds;
        $params[] = $searchLike;
        $params[] = $searchLike;

        $usersStmt->execute($params);
    }
}

if (!isset($users)) {
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
}

/*
|--------------------------------------------------------------------------
| LOAD BUSINESSES
|--------------------------------------------------------------------------
*/

if ($globalRole === 'super_admin') {

    $businessStmt = $pdo->query("
        SELECT
            id,
            name
        FROM businesses
        ORDER BY name ASC
    ");

    $businesses = $businessStmt->fetchAll(PDO::FETCH_ASSOC);

} elseif (!empty($managedBusinessIds)) {

    $placeholders = implode(
        ',',
        array_fill(0, count($managedBusinessIds), '?')
    );

    $businessStmt = $pdo->prepare("
        SELECT
            id,
            name
        FROM businesses
        WHERE id IN ($placeholders)
        ORDER BY name ASC
    ");

    $businessStmt->execute($managedBusinessIds);

    $businesses = $businessStmt->fetchAll(PDO::FETCH_ASSOC);

} else {

    $businesses = [];
}

/*
|--------------------------------------------------------------------------
| SELECTED USER
|--------------------------------------------------------------------------
*/

$selectedUser = null;
$selectedUserId = (int)($_GET['user_id'] ?? 0);

if ($selectedUserId > 0) {

    $selectedUserStmt = $pdo->prepare("
        SELECT
            id,
            name,
            email,
            role,
            status,
            created_at,
            updated_at
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $selectedUserStmt->execute([
        $selectedUserId
    ]);

    $selectedUser = $selectedUserStmt->fetch(PDO::FETCH_ASSOC);

    if ($selectedUser) {

        if ($globalRole !== 'super_admin') {

            $verifyAccessStmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM business_users
                WHERE user_id = ?
                AND business_id IN (
                    " . (
                        !empty($managedBusinessIds)
                            ? implode(
                                ',',
                                array_fill(
                                    0,
                                    count($managedBusinessIds),
                                    '?'
                                )
                            )
                            : '0'
                    ) . "
                )
            ");

            $params = [$selectedUserId];

            foreach ($managedBusinessIds as $managedBusinessId) {
                $params[] = $managedBusinessId;
            }

            $verifyAccessStmt->execute($params);

            if ((int)$verifyAccessStmt->fetchColumn() === 0) {
                $selectedUser = null;
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| LOAD USER BUSINESS ACCESS
|--------------------------------------------------------------------------
*/

$userBusinessAccess = [];

if ($selectedUser) {

    $accessSql = "
        SELECT
            bu.id,
            bu.business_id,
            bu.user_id,
            bu.role,
            bu.status,
            bu.created_at,
            b.name AS business_name
        FROM business_users bu
        INNER JOIN businesses b
            ON b.id = bu.business_id
        WHERE bu.user_id = ?
    ";

    $accessParams = [$selectedUser['id']];

    if ($globalRole !== 'super_admin' && !empty($managedBusinessIds)) {

        $accessSql .= "
            AND bu.business_id IN (
                " . implode(
                    ',',
                    array_fill(
                        0,
                        count($managedBusinessIds),
                        '?'
                    )
                ) . "
            )
        ";

        foreach ($managedBusinessIds as $managedBusinessId) {
            $accessParams[] = $managedBusinessId;
        }
    }

    $accessSql .= " ORDER BY b.name ASC";

    $accessStmt = $pdo->prepare($accessSql);
    $accessStmt->execute($accessParams);

    $userBusinessAccess = $accessStmt->fetchAll(PDO::FETCH_ASSOC);
}

$activePage = 'user_management';
$pageTitle = 'User Management - Multi Business SaaS';
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

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
>

<style>

body {
    min-height: 100vh;
    background: var(--bs-tertiary-bg);
}

.users-page {
    width: 100%;
    max-width: 1600px;
    margin: 0 auto;
}

.page-card {
    border: 1px solid var(--bs-border-color);
    border-radius: 18px;
    overflow: hidden;
}

.user-avatar {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-weight: 700;
}

.user-name {
    font-weight: 700;
}

.user-email {
    font-size: .78rem;
    color: var(--bs-secondary-color);
    word-break: break-word;
}

.role-badge,
.status-badge {
    font-size: .68rem;
    font-weight: 700;
}

.business-row {
    border: 1px solid var(--bs-border-color);
    border-radius: 14px;
    padding: 14px;
    margin-bottom: 10px;
}

.business-row:last-child {
    margin-bottom: 0;
}

.business-icon {
    width: 40px;
    height: 40px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.empty-state {
    padding: 60px 20px;
}

.empty-icon {
    width: 60px;
    height: 60px;
    border-radius: 17px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    font-size: 1.5rem;
}

.table > :not(caption) > * > * {
    padding-top: 14px;
    padding-bottom: 14px;
}

.table tbody tr {
    transition: background .15s ease;
}

.table tbody tr:hover {
    background: var(--bs-tertiary-bg);
}

.user-row-selected {
    background: var(--bs-primary-bg-subtle) !important;
}

@media (max-width: 767.98px) {

    .users-page {
        padding: 14px !important;
    }

    .page-header h2 {
        font-size: 1.35rem;
    }

    .desktop-table {
        display: none;
    }

    .mobile-user {
        border: 1px solid var(--bs-border-color);
        border-radius: 15px;
        padding: 14px;
        margin-bottom: 10px;
        background: var(--bs-body-bg);
    }

    .mobile-user:last-child {
        margin-bottom: 0;
    }

    .mobile-user-top {
        display: flex;
        gap: 12px;
        align-items: flex-start;
    }

    .mobile-user-info {
        min-width: 0;
        flex: 1;
    }

    .mobile-user-actions {
        display: flex;
        gap: 7px;
        margin-top: 13px;
        padding-top: 12px;
        border-top: 1px solid var(--bs-border-color);
    }

    .mobile-user-actions .btn {
        flex: 1;
    }

    .page-header-actions {
        width: 100%;
    }

    .page-header-actions .btn {
        width: 100%;
    }
}

@media (min-width: 768px) {
    .mobile-users {
        display: none;
    }
}

</style>

<script>
(function () {
    const theme = localStorage.getItem('bs-theme') || 'light';
    document.documentElement.setAttribute('data-bs-theme', theme);
})();
</script>

</head>

<body>

<?php
/*
|--------------------------------------------------------------------------
| GLOBAL HEADER
|--------------------------------------------------------------------------
|
| If your global header already contains navigation,
| this will automatically use it.
|
*/

$headerPath = __DIR__ . '/../../resources/partials/header.php';

if (file_exists($headerPath)) {
    include $headerPath;
}
?>

<main class="container-fluid py-3 py-md-4 users-page">

<!-- =========================================================
     HEADER
========================================================= -->

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

    <div>

        <div class="d-flex align-items-center gap-2 mb-1">

            <div class="text-primary">
                <i class="bi bi-people fs-4"></i>
            </div>

            <h2 class="fw-bold mb-0">
                User Management
            </h2>

        </div>

        <p class="text-muted small mb-0">
            Manage users and their access across your businesses.
        </p>

    </div>

    <div class="page-header-actions">

        <button
            type="button"
            class="btn btn-primary fw-semibold"
            data-bs-toggle="modal"
            data-bs-target="#createUserModal"
        >
            <i class="bi bi-person-plus me-1"></i>
            Create User
        </button>

    </div>

</div>

<!-- =========================================================
     FLASH MESSAGE
========================================================= -->

<?php if ($message !== ''): ?>

<div class="alert alert-<?= e($messageType) ?> alert-dismissible fade show shadow-sm">

    <i class="bi
        <?=
        $messageType === 'success'
            ? 'bi-check-circle'
            : 'bi-exclamation-circle'
        ?>
        me-2"
    ></i>

    <?= e($message) ?>

    <button
        type="button"
        class="btn-close"
        data-bs-dismiss="alert"
    ></button>

</div>

<?php endif; ?>

<!-- =========================================================
     USERS LIST
========================================================= -->

<div class="card page-card shadow-sm bg-body">

    <div class="card-header bg-transparent p-3 p-md-4 border-0">

        <div class="d-flex flex-column flex-md-row justify-content-between gap-3">

            <div>

                <h5 class="fw-bold mb-1">
                    Users
                </h5>

                <p class="text-muted small mb-0">
                    Global user accounts and business access.
                </p>

            </div>

            <form
                method="GET"
                action="index.php"
                class="d-flex gap-2"
            >

                <input
                    type="hidden"
                    name="page"
                    value="user_management"
                >

                <input
                    type="search"
                    name="search"
                    value="<?= e($search) ?>"
                    class="form-control"
                    placeholder="Search name or email..."
                    style="min-width:240px;"
                >

                <button
                    type="submit"
                    class="btn btn-outline-primary"
                >
                    <i class="bi bi-search"></i>
                </button>

            </form>

        </div>

    </div>

    <?php if (empty($users)): ?>

        <div class="empty-state text-center text-muted">

            <div class="empty-icon bg-primary bg-opacity-10 text-primary">
                <i class="bi bi-people"></i>
            </div>

            <h6 class="fw-bold text-body">
                No users found
            </h6>

            <p class="small mb-3">
                Create your first user account to get started.
            </p>

            <button
                type="button"
                class="btn btn-primary btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#createUserModal"
            >
                <i class="bi bi-person-plus me-1"></i>
                Create User
            </button>

        </div>

    <?php else: ?>

    <!-- DESKTOP -->

    <div class="table-responsive desktop-table">

        <table class="table align-middle mb-0">

            <thead class="table-light text-uppercase text-muted">

                <tr>

                    <th class="ps-4">
                        User
                    </th>

                    <th>
                        Global Role
                    </th>

                    <th>
                        Business Access
                    </th>

                    <th>
                        Status
                    </th>

                    <th class="text-end pe-4">
                        Action
                    </th>

                </tr>

            </thead>

            <tbody>

            <?php foreach ($users as $user): ?>

                <tr class="<?= $selectedUserId === (int)$user['id'] ? 'user-row-selected' : '' ?>">

                    <td class="ps-4">

                        <div class="d-flex align-items-center gap-3">

                            <div class="user-avatar bg-primary bg-opacity-10 text-primary">

                                <?= e(
                                    strtoupper(
                                        substr(
                                            trim($user['name']),
                                            0,
                                            1
                                        )
                                    )
                                ) ?>

                            </div>

                            <div>

                                <div class="user-name">
                                    <?= e($user['name']) ?>
                                </div>

                                <div class="user-email">
                                    <?= e($user['email']) ?>
                                </div>

                            </div>

                        </div>

                    </td>

                    <td>

                        <?php
                        $roleClass = match ($user['role']) {
                            'super_admin' => 'danger',
                            'admin' => 'primary',
                            default => 'secondary'
                        };

                        $roleLabel = match ($user['role']) {
                            'super_admin' => 'Super Admin',
                            'admin' => 'Admin',
                            default => 'Staff'
                        };
                        ?>

                        <span class="badge bg-<?= $roleClass ?> bg-opacity-10 text-<?= $roleClass ?> role-badge">

                            <?= e($roleLabel) ?>

                        </span>

                    </td>

                    <td>

                        <span class="fw-semibold">

                            <?= number_format(
                                (int)$user['business_count']
                            ) ?>

                        </span>

                        <span class="text-muted small">
                            business(es)
                        </span>

                    </td>

                    <td>

                        <?php
                        $statusClass = match ($user['status']) {
                            'active' => 'success',
                            'pending' => 'warning',
                            default => 'secondary'
                        };
                        ?>

                        <span class="badge bg-<?= $statusClass ?> bg-opacity-10 text-<?= $statusClass ?> status-badge">

                            <i class="bi
                                <?=
                                $user['status'] === 'active'
                                    ? 'bi-check-circle'
                                    : 'bi-circle'
                                ?>
                                me-1"
                            ></i>

                            <?= e(ucfirst($user['status'])) ?>

                        </span>

                    </td>

                    <td class="text-end pe-4">

                        <a
                            href="index.php?page=user_management&user_id=<?= (int)$user['id'] ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
                            class="btn btn-sm btn-outline-primary"
                        >
                            <i class="bi bi-gear me-1"></i>
                            Manage
                        </a>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

    <!-- MOBILE -->

    <div class="mobile-users p-3">

        <?php foreach ($users as $user): ?>

            <?php
            $roleClass = match ($user['role']) {
                'super_admin' => 'danger',
                'admin' => 'primary',
                default => 'secondary'
            };

            $roleLabel = match ($user['role']) {
                'super_admin' => 'Super Admin',
                'admin' => 'Admin',
                default => 'Staff'
            };

            $statusClass = match ($user['status']) {
                'active' => 'success',
                'pending' => 'warning',
                default => 'secondary'
            };
            ?>

            <div class="mobile-user">

                <div class="mobile-user-top">

                    <div class="user-avatar bg-primary bg-opacity-10 text-primary">

                        <?= e(
                            strtoupper(
                                substr(
                                    trim($user['name']),
                                    0,
                                    1
                                )
                            )
                        ) ?>

                    </div>

                    <div class="mobile-user-info">

                        <div class="user-name">
                            <?= e($user['name']) ?>
                        </div>

                        <div class="user-email">
                            <?= e($user['email']) ?>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-2">

                            <span class="badge bg-<?= $roleClass ?> bg-opacity-10 text-<?= $roleClass ?> role-badge">
                                <?= e($roleLabel) ?>
                            </span>

                            <span class="badge bg-<?= $statusClass ?> bg-opacity-10 text-<?= $statusClass ?> status-badge">
                                <?= e(ucfirst($user['status'])) ?>
                            </span>

                        </div>

                    </div>

                </div>

                <div class="small text-muted mt-3">

                    <i class="bi bi-building me-1"></i>

                    <?= number_format(
                        (int)$user['business_count']
                    ) ?>

                    business access(es)

                </div>

                <div class="mobile-user-actions">

                    <a
                        href="index.php?page=user_management&user_id=<?= (int)$user['id'] ?>"
                        class="btn btn-sm btn-outline-primary"
                    >
                        <i class="bi bi-gear me-1"></i>
                        Manage
                    </a>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

    <?php endif; ?>

</div>

<!-- =========================================================
     SELECTED USER MANAGEMENT
========================================================= -->

<?php if ($selectedUser): ?>

<div class="row g-3 mt-1">

    <!-- USER DETAILS -->

    <div class="col-12 col-xl-4">

        <div class="card page-card shadow-sm bg-body h-100">

            <div class="card-header bg-transparent border-0 p-4">

                <h5 class="fw-bold mb-1">
                    User Details
                </h5>

                <p class="text-muted small mb-0">
                    Update account information.
                </p>

            </div>

            <div class="card-body pt-0">

                <form method="POST">

                    <input
                        type="hidden"
                        name="action"
                        value="update_user"
                    >

                    <input
                        type="hidden"
                        name="user_id"
                        value="<?= (int)$selectedUser['id'] ?>"
                    >

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Full Name
                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            value="<?= e($selectedUser['name']) ?>"
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
                            value="<?= e($selectedUser['email']) ?>"
                            required
                        >

                    </div>

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Global Role
                        </label>

                        <select
                            name="role"
                            class="form-select"
                            <?= (
                                $globalRole !== 'super_admin'
                                && $selectedUser['role'] === 'super_admin'
                            ) ? 'disabled' : '' ?>
                        >

                            <?php if ($globalRole === 'super_admin'): ?>

                                <option
                                    value="super_admin"
                                    <?= $selectedUser['role'] === 'super_admin' ? 'selected' : '' ?>
                                >
                                    Super Admin
                                </option>

                            <?php endif; ?>

                            <option
                                value="admin"
                                <?= $selectedUser['role'] === 'admin' ? 'selected' : '' ?>
                            >
                                Admin
                            </option>

                            <option
                                value="staff"
                                <?= $selectedUser['role'] === 'staff' ? 'selected' : '' ?>
                            >
                                Staff
                            </option>

                        </select>

                    </div>

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Account Status
                        </label>

                        <select
                            name="status"
                            class="form-select"
                            <?= $selectedUser['id'] == $userId ? 'disabled' : '' ?>
                        >

                            <option
                                value="active"
                                <?= $selectedUser['status'] === 'active' ? 'selected' : '' ?>
                            >
                                Active
                            </option>

                            <option
                                value="inactive"
                                <?= $selectedUser['status'] === 'inactive' ? 'selected' : '' ?>
                            >
                                Inactive
                            </option>

                            <option
                                value="pending"
                                <?= $selectedUser['status'] === 'pending' ? 'selected' : '' ?>
                            >
                                Pending
                            </option>

                        </select>

                    </div>

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            New Password
                        </label>

                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            minlength="8"
                            placeholder="Leave blank to keep current password"
                        >

                        <div class="form-text">
                            Minimum 8 characters.
                        </div>

                    </div>

                    <button
                        type="submit"
                        class="btn btn-primary w-100 fw-semibold"
                    >
                        <i class="bi bi-check-lg me-1"></i>
                        Save Changes
                    </button>

                </form>

            </div>

        </div>

    </div>

    <!-- BUSINESS ACCESS -->

    <div class="col-12 col-xl-8">

        <div class="card page-card shadow-sm bg-body">

            <div class="card-header bg-transparent border-0 p-4">

                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

                    <div>

                        <h5 class="fw-bold mb-1">
                            Business Access
                        </h5>

                        <p class="text-muted small mb-0">
                            Manage which businesses this user can access.
                        </p>

                    </div>

                    <?php if (!empty($businesses)): ?>

                    <button
                        type="button"
                        class="btn btn-primary btn-sm fw-semibold"
                        data-bs-toggle="modal"
                        data-bs-target="#assignBusinessModal"
                    >
                        <i class="bi bi-building-add me-1"></i>
                        Assign Business
                    </button>

                    <?php endif; ?>

                </div>

            </div>

            <div class="card-body pt-0">

                <?php if (empty($userBusinessAccess)): ?>

                    <div class="empty-state text-center text-muted">

                        <div class="empty-icon bg-secondary bg-opacity-10 text-secondary">
                            <i class="bi bi-building-x"></i>
                        </div>

                        <div class="fw-semibold text-body mb-1">
                            No business access
                        </div>

                        <div class="small">
                            This user has not been assigned to any business.
                        </div>

                    </div>

                <?php else: ?>

                    <?php foreach ($userBusinessAccess as $access): ?>

                    <div class="business-row">

                        <div class="d-flex align-items-center gap-3">

                            <div class="business-icon bg-primary bg-opacity-10 text-primary">

                                <i class="bi bi-building"></i>

                            </div>

                            <div class="flex-grow-1 min-width-0">

                                <div class="fw-bold text-body">
                                    <?= e($access['business_name']) ?>
                                </div>

                                <div class="small text-muted">
                                    Added <?= date('M d, Y', strtotime($access['created_at'])) ?>
                                </div>

                            </div>

                            <div class="d-none d-md-block">

                                <span class="badge
                                    <?=
                                    $access['role'] === 'owner'
                                        ? 'bg-danger'
                                        : (
                                            $access['role'] === 'admin'
                                                ? 'bg-primary'
                                                : 'bg-secondary'
                                        )
                                    ?>
                                    bg-opacity-10
                                    text-<?=
                                    $access['role'] === 'owner'
                                        ? 'danger'
                                        : (
                                            $access['role'] === 'admin'
                                                ? 'primary'
                                                : 'secondary'
                                        )
                                    ?>
                                ">

                                    <?= e(ucfirst($access['role'])) ?>

                                </span>

                            </div>

                        </div>

                        <div class="d-flex flex-column flex-md-row gap-2 mt-3">

                            <form
                                method="POST"
                                class="d-flex flex-grow-1 gap-2"
                            >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="update_business_access"
                                >

                                <input
                                    type="hidden"
                                    name="business_user_id"
                                    value="<?= (int)$access['id'] ?>"
                                >

                                <select
                                    name="business_role"
                                    class="form-select form-select-sm"
                                >

                                    <?php if ($globalRole === 'super_admin'): ?>

                                    <option
                                        value="owner"
                                        <?= $access['role'] === 'owner' ? 'selected' : '' ?>
                                    >
                                        Owner
                                    </option>

                                    <?php endif; ?>

                                    <option
                                        value="admin"
                                        <?= $access['role'] === 'admin' ? 'selected' : '' ?>
                                    >
                                        Admin
                                    </option>

                                    <option
                                        value="staff"
                                        <?= $access['role'] === 'staff' ? 'selected' : '' ?>
                                    >
                                        Staff
                                    </option>

                                </select>

                                <select
                                    name="business_status"
                                    class="form-select form-select-sm"
                                >

                                    <option
                                        value="active"
                                        <?= $access['status'] === 'active' ? 'selected' : '' ?>
                                    >
                                        Active
                                    </option>

                                    <option
                                        value="inactive"
                                        <?= $access['status'] === 'inactive' ? 'selected' : '' ?>
                                    >
                                        Inactive
                                    </option>

                                </select>

                                <button
                                    type="submit"
                                    class="btn btn-sm btn-outline-primary text-nowrap"
                                >
                                    <i class="bi bi-save me-1"></i>
                                    Save
                                </button>

                            </form>

                            <?php if ((int)$access['user_id'] !== $userId): ?>

                            <form
                                method="POST"
                                onsubmit="return confirm('Remove this user from this business?');"
                            >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="remove_business_access"
                                >

                                <input
                                    type="hidden"
                                    name="business_user_id"
                                    value="<?= (int)$access['id'] ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-sm btn-outline-danger w-100"
                                >
                                    <i class="bi bi-person-dash me-1"></i>
                                    Remove
                                </button>

                            </form>

                            <?php endif; ?>

                        </div>

                    </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>

<?php endif; ?>

</main>

<!-- =========================================================
     CREATE USER MODAL
========================================================= -->

<div
    class="modal fade"
    id="createUserModal"
    tabindex="-1"
    aria-hidden="true"
>

<div class="modal-dialog modal-dialog-centered">

<div class="modal-content border-0 shadow">

<form method="POST">

<input
    type="hidden"
    name="action"
    value="create_user"
>

<div class="modal-header">

    <div>

        <h5 class="modal-title fw-bold">
            Create User
        </h5>

        <div class="small text-muted">
            Create a global account for your SaaS.
        </div>

    </div>

    <button
        type="button"
        class="btn-close"
        data-bs-dismiss="modal"
    ></button>

</div>

<div class="modal-body">

    <div class="mb-3">

        <label class="form-label fw-semibold">
            Full Name
        </label>

        <input
            type="text"
            name="name"
            class="form-control"
            placeholder="John Smith"
            required
        >

    </div>

    <div class="mb-3">

        <label class="form-label fw-semibold">
            Email Address
        </label>

        <input
            type="email"
            name="email"
            class="form-control"
            placeholder="john@example.com"
            required
        >

    </div>

    <div class="mb-3">

        <label class="form-label fw-semibold">
            Password
        </label>

        <input
            type="password"
            name="password"
            class="form-control"
            minlength="8"
            required
        >

        <div class="form-text">
            Minimum 8 characters.
        </div>

    </div>

    <div class="mb-3">

        <label class="form-label fw-semibold">
            Confirm Password
        </label>

        <input
            type="password"
            name="confirm_password"
            class="form-control"
            minlength="8"
            required
        >

    </div>

    <div class="row g-3">

        <div class="col-12 col-sm-6">

            <label class="form-label fw-semibold">
                Global Role
            </label>

            <select
                name="role"
                class="form-select"
            >

                <?php if ($globalRole === 'super_admin'): ?>

                <option value="super_admin">
                    Super Admin
                </option>

                <?php endif; ?>

                <option value="admin">
                    Admin
                </option>

                <option value="staff" selected>
                    Staff
                </option>

            </select>

        </div>

        <div class="col-12 col-sm-6">

            <label class="form-label fw-semibold">
                Account Status
            </label>

            <select
                name="status"
                class="form-select"
            >

                <option value="active" selected>
                    Active
                </option>

                <option value="inactive">
                    Inactive
                </option>

                <option value="pending">
                    Pending
                </option>

            </select>

        </div>

    </div>

</div>

<div class="modal-footer">

    <button
        type="button"
        class="btn btn-light"
        data-bs-dismiss="modal"
    >
        Cancel
    </button>

    <button
        type="submit"
        class="btn btn-primary fw-semibold"
    >
        <i class="bi bi-person-plus me-1"></i>
        Create User
    </button>

</div>

</form>

</div>

</div>

</div>

<!-- =========================================================
     ASSIGN BUSINESS MODAL
========================================================= -->

<?php if ($selectedUser): ?>

<div
    class="modal fade"
    id="assignBusinessModal"
    tabindex="-1"
    aria-hidden="true"
>

<div class="modal-dialog modal-dialog-centered">

<div class="modal-content border-0 shadow">

<form method="POST">

<input
    type="hidden"
    name="action"
    value="assign_business"
>

<input
    type="hidden"
    name="user_id"
    value="<?= (int)$selectedUser['id'] ?>"
>

<div class="modal-header">

    <div>

        <h5 class="modal-title fw-bold">
            Assign Business
        </h5>

        <div class="small text-muted">
            Give <?= e($selectedUser['name']) ?> access to another business.
        </div>

    </div>

    <button
        type="button"
        class="btn-close"
        data-bs-dismiss="modal"
    ></button>

</div>

<div class="modal-body">

    <?php

    $assignedBusinessIds = array_map(
        'intval',
        array_column(
            $userBusinessAccess,
            'business_id'
        )
    );

    $availableBusinesses = array_filter(
        $businesses,
        function ($business) use ($assignedBusinessIds) {
            return !in_array(
                (int)$business['id'],
                $assignedBusinessIds,
                true
            );
        }
    );

    ?>

    <?php if (empty($availableBusinesses)): ?>

        <div class="alert alert-info mb-0">

            <i class="bi bi-info-circle me-2"></i>

            This user already has access to all available businesses.

        </div>

    <?php else: ?>

        <div class="mb-3">

            <label class="form-label fw-semibold">
                Business
            </label>

            <select
                name="business_id"
                class="form-select"
                required
            >

                <option value="">
                    Select business...
                </option>

                <?php foreach ($availableBusinesses as $business): ?>

                    <option
                        value="<?= (int)$business['id'] ?>"
                    >
                        <?= e($business['name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <div>

            <label class="form-label fw-semibold">
                Business Role
            </label>

            <select
                name="business_role"
                class="form-select"
                required
            >

                <?php if ($globalRole === 'super_admin'): ?>

                <option value="owner">
                    Owner
                </option>

                <?php endif; ?>

                <option value="admin">
                    Admin
                </option>

                <option value="staff" selected>
                    Staff
                </option>

            </select>

            <div class="form-text">
                This role applies only to the selected business.
            </div>

        </div>

    <?php endif; ?>

</div>

<div class="modal-footer">

    <button
        type="button"
        class="btn btn-light"
        data-bs-dismiss="modal"
    >
        Cancel
    </button>

    <?php if (!empty($availableBusinesses)): ?>

    <button
        type="submit"
        class="btn btn-primary fw-semibold"
    >
        <i class="bi bi-building-add me-1"></i>
        Assign Business
    </button>

    <?php endif; ?>

</div>

</form>

</div>

</div>

</div>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>