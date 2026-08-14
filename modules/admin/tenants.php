<?php
$pdo = Database::getConnection();

// Optional: Ensure the logged-in user is an admin
// if (!isset($_SESSION['is_admin'])) { header('Location: index.php?page=login'); exit; }

$success = '';
$error = '';

// Handle Subscription Status / Plan Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_subscription'])) {
    $subscriptionId = $_POST['subscription_id'] ?? null;
    $newStatus = $_POST['status'] ?? null;
    $planId = $_POST['plan_id'] ?? null;

    if ($subscriptionId && in_array($newStatus, ['active', 'trialing', 'canceled', 'expired'])) {
        $stmt = $pdo->prepare("
            UPDATE subscriptions 
            SET status = ?, plan_id = ?, current_period_end = CASE 
                WHEN ? = 'active' THEN DATE_ADD(NOW(), INTERVAL 30 DAY) 
                ELSE current_period_end 
            END
            WHERE id = ?
        ");
        $stmt->execute([$newStatus, $planId, $newStatus, $subscriptionId]);
        
        $success = "Subscription status updated successfully!";
    } else {
        $error = "Invalid parameters provided.";
    }
}

// Fetch all businesses with their subscriptions
$stmt = $pdo->query("
    SELECT s.*, b.business_name, b.id as business_id
    FROM subscriptions s
    JOIN businesses b ON s.business_id = b.id
    ORDER BY s.created_at DESC
");
$subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-2">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-body mb-1">Tenant Subscriptions</h2>
            <p class="text-muted small mb-0">Approve, activate, or manage subscription periods for registered businesses.</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-3 py-2 px-3 small mb-4">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-3 py-2 px-3 small mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 bg-body">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3">Business Name</th>
                            <th class="py-3">Status</th>
                            <th class="py-3">Plan</th>
                            <th class="py-3">Period End</th>
                            <th class="py-3">Registered Date</th>
                            <th class="text-end pe-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($subscriptions)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">No tenant subscriptions found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($subscriptions as $sub): ?>
                                <tr>
                                    <td class="ps-4 fw-semibold"><?= htmlspecialchars($sub['business_name']) ?></td>
                                    <td>
                                        <?php 
                                            $badgeClass = match($sub['status']) {
                                                'active' => 'bg-success bg-opacity-10 text-success',
                                                'trialing' => 'bg-warning bg-opacity-10 text-warning',
                                                'canceled' => 'bg-danger bg-opacity-10 text-danger',
                                                default => 'bg-secondary bg-opacity-10 text-secondary'
                                            };
                                        ?>
                                        <span class="badge <?= $badgeClass ?> px-3 py-2 rounded-pill fw-semibold">
                                            <?= ucfirst($sub['status']) ?>
                                        </span>
                                    </td>
                                    <td>Plan #<?= htmlspecialchars($sub['plan_id']) ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars($sub['current_period_end'] ?? 'N/A') ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars($sub['created_at']) ?></td>
                                    <td class="text-end pe-4">
                                        <form method="POST" class="d-inline-flex gap-2 align-items-center">
                                            <input type="hidden" name="update_subscription" value="1">
                                            <input type="hidden" name="subscription_id" value="<?= $sub['id'] ?>">
                                            <input type="hidden" name="plan_id" value="<?= $sub['plan_id'] ?>">
                                            
                                            <select name="status" class="form-select form-select-sm shadow-none" style="width: 120px;">
                                                <option value="active" <?= $sub['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                <option value="trialing" <?= $sub['status'] === 'trialing' ? 'selected' : '' ?>>Trialing</option>
                                                <option value="expired" <?= $sub['status'] === 'expired' ? 'selected' : '' ?>>Expired</option>
                                                <option value="canceled" <?= $sub['status'] === 'canceled' ? 'selected' : '' ?>>Canceled</option>
                                            </select>

                                            <button type="submit" class="btn btn-sm btn-primary fw-semibold px-3">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>