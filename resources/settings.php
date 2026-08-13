<?php
// Note: Database connection and session are already loaded via index.php -> app.php

$pdo = Database::getConnection();
$userId = $_SESSION['user_id'] ?? null;

$successMessage = '';
$errorMessage = '';

// Fetch all businesses owned by this user for the dropdown selector
$stmt = $pdo->prepare("SELECT * FROM businesses WHERE owner_id = ?");
$stmt->execute([$userId]);
$userBusinesses = $stmt->fetchAll();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Handle Password Update
    if ($action === 'update_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!empty($currentPassword) && !empty($newPassword) && !empty($confirmPassword)) {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if ($user && password_verify($currentPassword, $user['password'])) {
                if ($newPassword === $confirmPassword) {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $updateStmt->execute([$newHash, $userId]);
                    $successMessage = 'Password updated successfully!';
                } else {
                    $errorMessage = 'New passwords do not match.';
                }
            } else {
                $errorMessage = 'Incorrect current password.';
            }
        } else {
            $errorMessage = 'Please fill in all password fields.';
        }
    }

    // 2. Handle Business Name Update
    if ($action === 'update_business') {
        $targetBusinessId = $_POST['business_id'] ?? '';
        $newBusinessName = trim($_POST['business_name'] ?? '');

        if (!empty($targetBusinessId) && !empty($newBusinessName)) {
            // Verify ownership before updating
            $updateBiz = $pdo->prepare("UPDATE businesses SET business_name = ? WHERE id = ? AND owner_id = ?");
            $updateBiz->execute([$newBusinessName, $targetBusinessId, $userId]);
            
            // If this is the currently active business in session, update session too
            if (isset($_SESSION['business_id']) && $_SESSION['business_id'] == $targetBusinessId) {
                $_SESSION['business_name'] = $newBusinessName;
            }

            // Refresh business list
            $stmt->execute([$userId]);
            $userBusinesses = $stmt->fetchAll();

            $successMessage = 'Business name updated successfully!';
        } else {
            $errorMessage = 'Please select a business and provide a valid name.';
        }
    }
}

// Set dynamic page title
$pageTitle = "Settings - MULTIBUSINESSSYSTEM";

// Include Shared Header Partial (renders <html>, <head>, and the navbar)
include __DIR__ . '/partials/header.php';
?>

    <div class="container pb-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <!-- Page Header with Back Button -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold mb-0">Account & Business Settings</h2>
                    <a href="index.php?page=select_business" class="btn btn-outline-secondary btn-sm fw-bold">← Back to Businesses</a>
                </div>

                <?php if ($successMessage): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
                <?php endif; ?>
                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
                <?php endif; ?>

                <!-- Theme Toggle Card -->
                <div class="card shadow-sm border-0 rounded-4 p-4 mb-4">
                    <h4 class="fw-bold mb-3">Appearance</h4>
                    <button id="themeToggle" class="btn btn-outline-secondary w-100" onclick="toggleTheme()">
                        🌙 Toggle Dark/Light Mode
                    </button>
                </div>

                <!-- Subscription Management Card -->
                <div class="card shadow-sm border-0 rounded-4 p-4 mb-4">
                    <h4 class="fw-bold mb-3">Subscription & Plans</h4>
                    <p class="text-muted small">View your active business plans and check subscription statuses.</p>
                    <a href="index.php?page=subscription" class="btn btn-outline-primary fw-bold">Manage Subscriptions</a>
                </div>

                <!-- Rename Business Card -->
                <?php if (!empty($userBusinesses)): ?>
                <div class="card shadow-sm border-0 rounded-4 p-4 mb-4">
                    <h4 class="fw-bold mb-3">Rename Business</h4>
                    <form action="index.php?page=settings" method="POST">
                        <input type="hidden" name="action" value="update_business">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Select Business to Rename</label>
                            <select name="business_id" class="form-select" required>
                                <option value="" disabled selected>Choose a business...</option>
                                <?php foreach ($userBusinesses as $biz): ?>
                                    <option value="<?= $biz['id'] ?>"><?= htmlspecialchars($biz['business_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">New Business Name</label>
                            <input type="text" class="form-control" name="business_name" placeholder="Enter new business name" required>
                        </div>

                        <button type="submit" class="btn btn-primary fw-bold">Save New Name</button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Change Password Card -->
                <div class="card shadow-sm border-0 rounded-4 p-4">
                    <h4 class="fw-bold mb-3">Change Password</h4>
                    <form action="index.php?page=settings" method="POST">
                        <input type="hidden" name="action" value="update_password">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">New Password</label>
                            <input type="password" class="form-control" name="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn btn-dark fw-bold">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php 
// Include Shared Footer Partial
include __DIR__ . '/partials/footer.php'; 
?>