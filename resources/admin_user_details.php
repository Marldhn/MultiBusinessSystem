<?php

$pdo = Database::getConnection();

$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? '';

if (!$userId || $userRole !== 'super_admin') {
    header('Location: index.php?page=select_business');
    exit;
}

$targetUserId = (int)($_GET['id'] ?? 0);

if ($targetUserId <= 0) {
    header('Location: index.php?page=admin_portal');
    exit;
}

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| HANDLE ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    try {

        /*
        |--------------------------------------------------------------------------
        | APPROVE USER
        |--------------------------------------------------------------------------
        */

        if ($action === 'approve_user') {

            $stmt = $pdo->prepare("
                UPDATE users
                SET status = 'active'
                WHERE id = ?
            ");

            $stmt->execute([$targetUserId]);

            $success = 'User approved successfully.';
        }

        /*
        |--------------------------------------------------------------------------
        | REJECT USER
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'reject_user') {

            $stmt = $pdo->prepare("
                UPDATE users
                SET status = 'inactive'
                WHERE id = ?
            ");

            $stmt->execute([$targetUserId]);

            $success = 'User rejected successfully.';
        }

        /*
        |--------------------------------------------------------------------------
        | SAVE BUSINESS ACCESS
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'save_business_access') {

            $selectedBusinesses = $_POST['business'] ?? [];

            /*
            | Get all businesses
            */

            $stmt = $pdo->query("
                SELECT id
                FROM businesses
            ");

            $allBusinessIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($allBusinessIds as $businessId) {

                $businessId = (int)$businessId;

                $active = isset(
                    $selectedBusinesses[$businessId]['active']
                );

                /*
                |--------------------------------------------------------------------------
                | USER HAS ACCESS
                |--------------------------------------------------------------------------
                */

                if ($active) {

                    $check = $pdo->prepare("
                        SELECT id
                        FROM business_users
                        WHERE business_id = ?
                        AND user_id = ?
                        LIMIT 1
                    ");

                    $check->execute([
                        $businessId,
                        $targetUserId
                    ]);

                    $existing = $check->fetch(PDO::FETCH_ASSOC);

                    if ($existing) {

                        $update = $pdo->prepare("
                            UPDATE business_users
                            SET role = ?
                            WHERE id = ?
                        ");

                        $update->execute([
                            'admin',
                            $existing['id']
                        ]);

                    } else {

                        $insert = $pdo->prepare("
                            INSERT INTO business_users
                            (
                                business_id,
                                user_id,
                                role
                            )
                            VALUES (?, ?, ?)
                        ");

                        $insert->execute([
                            $businessId,
                            $targetUserId,
                            'admin'
                        ]);
                    }

                }

                /*
                |--------------------------------------------------------------------------
                | USER DOES NOT HAVE ACCESS
                |--------------------------------------------------------------------------
                */

                else {

                    $delete = $pdo->prepare("
                        DELETE FROM business_users
                        WHERE business_id = ?
                        AND user_id = ?
                    ");

                    $delete->execute([
                        $businessId,
                        $targetUserId
                    ]);
                }
            }

            $success = 'Business access updated successfully.';
        }

    } catch (PDOException $e) {

        $error = $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| GET USER
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
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

$stmt->execute([$targetUserId]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: index.php?page=admin_portal');
    exit;
}


/*
|--------------------------------------------------------------------------
| GET BUSINESSES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        name,
        email,
        phone,
        address,
        status,
        created_at
    FROM businesses
    ORDER BY name ASC
");

$businesses = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| GET BUSINESSES ASSIGNED TO USER
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        business_id,
        role
    FROM business_users
    WHERE user_id = ?
");

$stmt->execute([$targetUserId]);

$userBusinesses = [];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

    $userBusinesses[(int)$row['business_id']] = $row['role'];
}


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$pageTitle = 'User Details';

include __DIR__ . '/partials/header.php';

?>

<div class="container py-4">

    <!-- HEADER -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold mb-1">
                User Details
            </h2>

            <p class="text-muted mb-0">
                Review the user and manage business access.
            </p>

        </div>

        <a
            href="index.php?page=admin_portal"
            class="btn btn-outline-secondary btn-sm"
        >
            <i class="bi bi-arrow-left me-1"></i>
            Back to Admin Portal
        </a>

    </div>


    <!-- SUCCESS -->

    <?php if ($success): ?>

        <div class="alert alert-success rounded-4 shadow-sm">

            <i class="bi bi-check-circle me-2"></i>

            <?= htmlspecialchars($success) ?>

        </div>

    <?php endif; ?>


    <!-- ERROR -->

    <?php if ($error): ?>

        <div class="alert alert-danger rounded-4 shadow-sm">

            <i class="bi bi-exclamation-triangle me-2"></i>

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- USER INFORMATION -->

    <div class="card shadow-sm border-0 rounded-4 mb-4">

        <div class="card-header bg-white py-3">

            <h5 class="fw-bold mb-0">

                <i class="bi bi-person-circle me-2"></i>

                User Information

            </h5>

        </div>

        <div class="card-body">

            <div class="row g-4">

                <div class="col-md-6">

                    <div class="text-muted small">
                        Full Name
                    </div>

                    <div class="fw-bold fs-5">

                        <?= htmlspecialchars($user['name']) ?>

                    </div>

                </div>


                <div class="col-md-6">

                    <div class="text-muted small">
                        Email Address
                    </div>

                    <div class="fw-bold fs-5">

                        <?= htmlspecialchars($user['email']) ?>

                    </div>

                </div>


                <div class="col-md-4">

                    <div class="text-muted small">
                        Role
                    </div>

                    <?php

                    $role = $user['role'] ?? 'staff';

                    $roleClass = 'secondary';

                    if ($role === 'super_admin') {
                        $roleClass = 'danger';
                    } elseif ($role === 'admin') {
                        $roleClass = 'primary';
                    } elseif ($role === 'staff') {
                        $roleClass = 'secondary';
                    }

                    ?>

                    <span class="badge bg-<?= $roleClass ?>">

                        <?= htmlspecialchars(
                            ucwords(str_replace('_', ' ', $role))
                        ) ?>

                    </span>

                </div>


                <div class="col-md-4">

                    <div class="text-muted small">
                        Account Status
                    </div>

                    <?php

                    $userStatus = $user['status'] ?? 'pending';

                    if ($userStatus === 'active'):

                    ?>

                        <span class="badge bg-success">

                            <i class="bi bi-check-circle me-1"></i>

                            Approved / Active

                        </span>

                    <?php elseif ($userStatus === 'pending'): ?>

                        <span class="badge bg-warning text-dark">

                            <i class="bi bi-clock me-1"></i>

                            Pending Approval

                        </span>

                    <?php else: ?>

                        <span class="badge bg-danger">

                            <i class="bi bi-x-circle me-1"></i>

                            Inactive / Rejected

                        </span>

                    <?php endif; ?>

                </div>


                <div class="col-md-4">

                    <div class="text-muted small">
                        Registered
                    </div>

                    <div class="fw-semibold">

                        <?= htmlspecialchars(
                            $user['created_at']
                        ) ?>

                    </div>

                </div>

            </div>

        </div>


        <!-- APPROVE / REJECT -->

        <div class="card-footer bg-light">

            <div class="d-flex justify-content-end gap-2">

                <?php if ($userStatus === 'active'): ?>

                    <form
                        method="POST"
                        onsubmit="return confirm('Reject this user?');"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="reject_user"
                        >

                        <button
                            type="submit"
                            class="btn btn-outline-danger"
                        >

                            <i class="bi bi-x-circle me-1"></i>

                            Reject User

                        </button>

                    </form>

                <?php else: ?>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="action"
                            value="approve_user"
                        >

                        <button
                            type="submit"
                            class="btn btn-success"
                        >

                            <i class="bi bi-check-circle me-1"></i>

                            Approve User

                        </button>

                    </form>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <!-- BUSINESS ACCESS -->

    <div class="card shadow-sm border-0 rounded-4">

        <div class="card-header bg-white py-3">

            <h5 class="fw-bold mb-1">

                <i class="bi bi-buildings me-2"></i>

                Business Access

            </h5>

            <small class="text-muted">

                Activate or deactivate businesses for this user.

            </small>

        </div>


        <form method="POST">

            <input
                type="hidden"
                name="action"
                value="save_business_access"
            >


            <?php if (empty($businesses)): ?>

                <div class="p-4 text-center">

                    <i
                        class="bi bi-buildings text-muted"
                        style="font-size:3rem;"
                    ></i>

                    <p class="text-muted mt-3 mb-0">

                        No businesses found.

                    </p>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">

                            <tr>

                                <th>
                                    Business
                                </th>

                                <th>
                                    Contact
                                </th>

                                <th>
                                    Address
                                </th>

                                <th>
                                    Business Status
                                </th>

                                <th>
                                    User Role
                                </th>

                                <th class="text-center">
                                    User Access
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($businesses as $business): ?>

                                <?php

                                $businessId = (int)$business['id'];

                                $hasAccess = isset(
                                    $userBusinesses[$businessId]
                                );

                                $businessStatus =
                                    $business['status'] ?? 'active';

                                ?>

                                <tr>

                                    <!-- BUSINESS -->

                                    <td>

                                        <div class="fw-bold">

                                            <?= htmlspecialchars(
                                                $business['name']
                                            ) ?>

                                        </div>

                                        <?php if (!empty($business['email'])): ?>

                                            <small class="text-muted">

                                                <?= htmlspecialchars(
                                                    $business['email']
                                                ) ?>

                                            </small>

                                        <?php endif; ?>

                                    </td>


                                    <!-- CONTACT -->

                                    <td>

                                        <?php if (!empty($business['phone'])): ?>

                                            <i class="bi bi-telephone me-1"></i>

                                            <?= htmlspecialchars(
                                                $business['phone']
                                            ) ?>

                                        <?php else: ?>

                                            <span class="text-muted">
                                                N/A
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- ADDRESS -->

                                    <td>

                                        <small>

                                            <?= !empty($business['address'])
                                                ? htmlspecialchars(
                                                    $business['address']
                                                )
                                                : 'N/A'
                                            ?>

                                        </small>

                                    </td>


                                    <!-- BUSINESS STATUS -->

                                    <td>

                                        <?php if (
                                            $businessStatus === 'active'
                                        ): ?>

                                            <span class="badge bg-success">

                                                Active

                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-secondary">

                                                Inactive

                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- USER ROLE -->

                                    <td>

                                        <?php if ($hasAccess): ?>

                                            <span class="badge bg-primary">

                                                <?= htmlspecialchars(
                                                    ucwords(
                                                        str_replace(
                                                            '_',
                                                            ' ',
                                                            $userBusinesses[$businessId]
                                                        )
                                                    )
                                                ) ?>

                                            </span>

                                        <?php else: ?>

                                            <span class="text-muted">

                                                Not Assigned

                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- ACCESS -->

                                    <td class="text-center">

                                        <div class="form-check form-switch d-inline-block">

                                            <input
                                                type="checkbox"
                                                class="form-check-input"
                                                name="business[<?= $businessId ?>][active]"
                                                value="1"
                                                <?= $hasAccess ? 'checked' : '' ?>
                                                <?= $businessStatus !== 'active' ? 'disabled' : '' ?>
                                            >

                                        </div>

                                        <div>

                                            <small class="text-muted">

                                                <?= $hasAccess
                                                    ? 'Active'
                                                    : 'Inactive'
                                                ?>

                                            </small>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


                <div class="card-footer bg-white">

                    <div class="d-flex justify-content-end">

                        <button
                            type="submit"
                            class="btn btn-primary px-4"
                        >

                            <i class="bi bi-save me-1"></i>

                            Save Business Access

                        </button>

                    </div>

                </div>

            <?php endif; ?>

        </form>

    </div>

</div>


<?php include __DIR__ . '/partials/footer.php'; ?>