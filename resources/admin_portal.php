<?php

$pdo = Database::getConnection();

$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'staff';

if (!$userId || $userRole !== 'super_admin') {
    header('Location: index.php?page=select_business');
    exit;
}


/*
|--------------------------------------------------------------------------
| FETCH USERS
|--------------------------------------------------------------------------
*/

$userStmt = $pdo->query("
    SELECT
        id,
        name,
        email,
        role,
        status,
        created_at
    FROM users
    ORDER BY id DESC
");

$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| FETCH BUSINESSES
|--------------------------------------------------------------------------
*/

$businessStmt = $pdo->query("
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

$allBusinesses = $businessStmt->fetchAll(PDO::FETCH_ASSOC);


$pageTitle = "Admin Portal - System Management";

include __DIR__ . '/partials/header.php';

?>

<div class="container py-4">

    <!-- PAGE HEADER -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold mb-1">
                Admin Portal
            </h2>

            <p class="text-muted small mb-0">
                Manage users, businesses, and system access.
            </p>

        </div>

        <a
            href="index.php?page=select_business"
            class="btn btn-outline-secondary btn-sm"
        >
            <i class="bi bi-arrow-left me-1"></i>
            Back to Businesses
        </a>

    </div>


    <!-- SUCCESS MESSAGE -->

    <?php if (isset($_GET['success'])): ?>

        <div class="alert alert-success rounded-4 shadow-sm mb-4">

            <i class="bi bi-check-circle me-2"></i>

            Action completed successfully.

        </div>

    <?php endif; ?>


    <!-- =========================================================
         REGISTERED USERS
         ========================================================= -->

    <div class="card shadow-sm rounded-4 mb-5 overflow-hidden">

        <div class="card-header bg-white py-3">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <h5 class="fw-bold mb-1">

                        <i class="bi bi-people me-2"></i>

                        Registered Users

                    </h5>

                    <small class="text-muted">

                        View and manage all registered users.

                    </small>

                </div>

                <span class="badge bg-primary">

                    <?= count($users) ?> Users

                </span>

            </div>

        </div>


        <?php if (empty($users)): ?>

            <div class="p-4 text-center">

                <i
                    class="bi bi-people text-muted"
                    style="font-size:3rem;"
                ></i>

                <p class="text-muted mb-0 mt-2">
                    No users found.
                </p>

            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>

                            <th>Full Name</th>

                            <th>Email Address</th>

                            <th>Role</th>

                            <th>Status</th>

                            <th>Registered Date</th>

                            <th class="text-end">
                                View
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($users as $u): ?>

                            <?php
                            $role = $u['role'] ?? 'staff';
                            $status = $u['status'] ?? 'inactive';
                            ?>

                            <tr>

                                <!-- NAME -->

                                <td class="fw-bold">

                                    <?= htmlspecialchars(
                                        $u['name']
                                    ) ?>

                                </td>


                                <!-- EMAIL -->

                                <td>

                                    <?= htmlspecialchars(
                                        $u['email']
                                    ) ?>

                                </td>


                                <!-- ROLE -->

                                <td>

                                    <?php if ($role === 'super_admin'): ?>

                                        <span class="badge bg-danger">

                                            <i class="bi bi-shield-lock me-1"></i>

                                            Super Admin

                                        </span>

                                    <?php elseif ($role === 'admin'): ?>

                                        <span class="badge bg-primary">

                                            <i class="bi bi-person-gear me-1"></i>

                                            Admin

                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-secondary">

                                            <i class="bi bi-person me-1"></i>

                                            Staff

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?php if ($status === 'active'): ?>

                                        <span class="badge bg-success">

                                            <i class="bi bi-check-circle me-1"></i>

                                            Active

                                        </span>

                                    <?php elseif ($status === 'pending'): ?>

                                        <span class="badge bg-warning text-dark">

                                            <i class="bi bi-clock me-1"></i>

                                            Pending

                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-secondary">

                                            <i class="bi bi-x-circle me-1"></i>

                                            Inactive

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <small>

                                        <?= htmlspecialchars(
                                            $u['created_at']
                                        ) ?>

                                    </small>

                                </td>


                                <!-- VIEW -->

                                <td class="text-end">

                                    <a
                                        href="index.php?page=admin_user_details&id=<?= (int)$u['id'] ?>"
                                        class="btn btn-outline-primary btn-sm"
                                        title="View User Details"
                                    >

                                        <i class="bi bi-eye me-1"></i>

                                        View

                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>


    <!-- =========================================================
         REGISTERED BUSINESSES
         ========================================================= -->

    <div class="card shadow-sm rounded-4 overflow-hidden">

        <div class="card-header bg-white py-3">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <h5 class="fw-bold mb-1">

                        <i class="bi bi-buildings me-2"></i>

                        Registered Businesses

                    </h5>

                    <small class="text-muted">

                        View and manage all registered businesses.

                    </small>

                </div>

                <span class="badge bg-primary">

                    <?= count($allBusinesses) ?> Businesses

                </span>

            </div>

        </div>


        <?php if (empty($allBusinesses)): ?>

            <div class="p-4 text-center">

                <i
                    class="bi bi-buildings text-muted"
                    style="font-size:3rem;"
                ></i>

                <p class="text-muted mb-0 mt-2">

                    No businesses found in the system.

                </p>

            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>

                            <th>Business Name</th>

                            <th>Email</th>

                            <th>Phone</th>

                            <th>Status</th>

                            <th>Created</th>

                            <th class="text-end">
                                Actions
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($allBusinesses as $biz): ?>

                            <?php
                            $businessStatus = $biz['status'] ?? 'inactive';
                            ?>

                            <tr>

                                <!-- BUSINESS NAME -->

                                <td class="fw-bold">

                                    <i class="bi bi-building me-2 text-primary"></i>

                                    <?= htmlspecialchars(
                                        $biz['name']
                                    ) ?>

                                </td>


                                <!-- EMAIL -->

                                <td>

                                    <?php if (!empty($biz['email'])): ?>

                                        <?= htmlspecialchars(
                                            $biz['email']
                                        ) ?>

                                    <?php else: ?>

                                        <span class="text-muted">
                                            N/A
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- PHONE -->

                                <td>

                                    <?php if (!empty($biz['phone'])): ?>

                                        <i class="bi bi-telephone me-1 text-primary"></i>

                                        <?= htmlspecialchars(
                                            $biz['phone']
                                        ) ?>

                                    <?php else: ?>

                                        <span class="text-muted">
                                            N/A
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?php if ($businessStatus === 'active'): ?>

                                        <span class="badge bg-success">

                                            <i class="bi bi-check-circle me-1"></i>

                                            Active

                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-secondary">

                                            <i class="bi bi-x-circle me-1"></i>

                                            Inactive

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- CREATED -->

                                <td>

                                    <small>

                                        <?= htmlspecialchars(
                                            $biz['created_at']
                                        ) ?>

                                    </small>

                                </td>


                                <!-- ACTIONS -->

                                <td class="text-end">

                                    <a
                                        href="index.php?page=admin_business_details&id=<?= (int)$biz['id'] ?>"
                                        class="btn btn-info btn-sm fw-bold px-3 text-white"
                                    >

                                        <i class="bi bi-info-circle me-1"></i>

                                        Details & Modules

                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>


<?php include __DIR__ . '/partials/footer.php'; ?>