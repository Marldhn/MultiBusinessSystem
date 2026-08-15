<?php
$pdo = Database::getConnection();

$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'staff';

if (!$userId) {
    header('Location: index.php?page=login');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['business_id'])) {
    $selectedBusinessId = (int)$_POST['business_id'];

    if ($selectedBusinessId <= 0) {
        $error = 'Invalid business selected.';
    } else {
        $hasAccess = false;
        $businessRole = 'owner';

        if ($userRole === 'super_admin') {
            $hasAccess = true;
            $businessRole = 'super_admin';
        } else {
            $checkStmt = $pdo->prepare("
                SELECT role
                FROM business_users
                WHERE business_id = ?
                AND user_id = ?
                AND status = 'active'
                LIMIT 1
            ");
            $checkStmt->execute([$selectedBusinessId, $userId]);

            $assignment = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($assignment) {
                $hasAccess = true;
                $businessRole = $assignment['role'];
            }
        }

        if ($hasAccess) {
            $bStmt = $pdo->prepare("
                SELECT *
                FROM businesses
                WHERE id = ?
                AND status = 'active'
                LIMIT 1
            ");
            $bStmt->execute([$selectedBusinessId]);

            $businessData = $bStmt->fetch(PDO::FETCH_ASSOC);

            if ($businessData) {
                $_SESSION['business_id'] = $businessData['id'];
                $_SESSION['business_name'] = $businessData['name'];
                $_SESSION['business_role'] = $businessRole;

                header('Location: index.php?page=dashboard');
                exit;
            }

            $error = 'Selected business is inactive or does not exist.';
        } else {
            $error = 'You do not have permission to access this business.';
        }
    }
}

if ($userRole === 'super_admin') {
    $stmt = $pdo->prepare("
        SELECT
            b.id,
            b.name,
            b.email,
            b.phone,
            b.address,
            b.status,
            b.created_at,
            'super_admin' AS user_business_role
        FROM businesses b
        WHERE b.status = 'active'
        ORDER BY b.name ASC
    ");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("
        SELECT
            b.id,
            b.name,
            b.email,
            b.phone,
            b.address,
            b.status,
            b.created_at,
            bu.role AS user_business_role
        FROM businesses b
        INNER JOIN business_users bu
            ON bu.business_id = b.id
        WHERE bu.user_id = ?
        AND bu.status = 'active'
        AND b.status = 'active'
        ORDER BY b.name ASC
    ");
    $stmt->execute([$userId]);
}

$businesses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Select Business - Multibusiness System';

include __DIR__ . '/partials/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-9 col-lg-8">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1">Select a Business</h2>
                    <p class="text-muted small mb-0">
                        Choose the business workspace you want to access.
                    </p>
                </div>

                <?php if ($userRole === 'super_admin'): ?>
                    <a href="index.php?page=admin_portal"
                       class="btn btn-outline-primary btn-sm fw-bold">
                        <i class="bi bi-shield-lock me-1"></i>
                        Admin Portal
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger rounded-4 shadow-sm mb-4">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (empty($businesses)): ?>

                <div class="card p-5 text-center shadow-sm rounded-4 border-0 bg-white">
                    <div class="text-muted mb-3" style="font-size:3rem;">
                        <i class="bi bi-buildings"></i>
                    </div>

                    <h5 class="fw-bold text-dark">
                        No Businesses Available
                    </h5>

                    <p class="text-muted small mb-0">
                        You are not currently assigned to any active
                        businesses. Please contact an administrator.
                    </p>
                </div>

            <?php else: ?>

                <div class="row g-3">

                    <?php foreach ($businesses as $biz): ?>

                        <?php $businessId = (int)$biz['id']; ?>

                        <div class="col-md-6">
                            <div class="card h-100 shadow-sm rounded-4 border-0 transition-card">
                                <div class="card-body p-4 d-flex flex-column">

                                    <div>
                                        <div class="d-flex align-items-center justify-content-between mb-3">

                                            <div class="rounded-3 bg-primary bg-opacity-10 text-primary p-3">
                                                <i class="bi bi-shop"
                                                   style="font-size:1.5rem;"></i>
                                            </div>

                                            <?php if (!empty($biz['user_business_role'])): ?>
                                                <span class="badge bg-secondary text-uppercase px-2 py-1"
                                                      style="font-size:.65rem;">
                                                    <?= htmlspecialchars($biz['user_business_role']) ?>
                                                </span>
                                            <?php endif; ?>

                                        </div>

                                        <h5 class="fw-bold text-dark mb-1">
                                            <?= htmlspecialchars($biz['name']) ?>
                                        </h5>

                                        <?php if (!empty($biz['email'])): ?>
                                            <p class="text-muted small mb-1">
                                                <i class="bi bi-envelope me-1"></i>
                                                <?= htmlspecialchars($biz['email']) ?>
                                            </p>
                                        <?php endif; ?>

                                        <?php if (!empty($biz['phone'])): ?>
                                            <p class="text-muted small mb-1">
                                                <i class="bi bi-telephone me-1"></i>
                                                <?= htmlspecialchars($biz['phone']) ?>
                                            </p>
                                        <?php endif; ?>

                                        <?php if (!empty($biz['address'])): ?>
                                            <p class="text-muted small mb-3">
                                                <i class="bi bi-geo-alt me-1"></i>
                                                <?= htmlspecialchars($biz['address']) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>

                                    <form method="POST" class="mt-auto">
                                        <input type="hidden"
                                               name="business_id"
                                               value="<?= $businessId ?>">

                                        <button type="submit"
                                                class="btn btn-primary w-100 fw-bold rounded-3 py-2">
                                            <i class="bi bi-box-arrow-in-right me-1"></i>
                                            Open Workspace
                                        </button>
                                    </form>

                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

            <div class="text-center mt-4">
                <a href="index.php?page=logout"
                   class="text-danger small text-decoration-none fw-bold">
                    <i class="bi bi-box-arrow-right me-1"></i>
                    Sign Out
                </a>
            </div>

        </div>
    </div>
</div>

<style>
.transition-card {
    transition: transform .2s ease, box-shadow .2s ease;
}

.transition-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,.08) !important;
}
</style>

<?php include __DIR__ . '/partials/footer.php'; ?>