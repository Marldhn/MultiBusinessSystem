<?php
// =========================================================
// REGISTER PAGE & PROCESSOR
// =========================================================

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = Database::getConnection();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'business_owner';

    // Basic validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } else {
        // Check if email already exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            $error = "Email address is already registered.";
        } else {
            // Hash the password securely
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user into the database
            $insertStmt = $pdo->prepare("
                INSERT INTO users (name, email, password, role, plan_id, subscription_status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, 1, 'active', NOW(), NOW())
            ");
            
            if ($insertStmt->execute([$name, $email, $hashedPassword, $role])) {
                header('Location: index.php?page=login&success=' . urlencode('Registration successful! Please login.'));
                exit;
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}

$pageTitle = "Register - MULTIBUSINESSSYSTEM";
include __DIR__ . '/partials/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-6 col-lg-5">
            <div class="card shadow-sm rounded-4 border-0">
                <div class="card-body p-4 p-md-5">
                    
                    <div class="text-center mb-4">
                        <h3 class="fw-bold text-body">Create an Account</h3>
                        <p class="text-muted small">Sign up to start managing your business</p>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger rounded-3 small mb-3">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form action="index.php?page=register" method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Full Name</label>
                            <input type="text" name="name" class="form-control rounded-3" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Email Address</label>
                            <input type="email" name="email" class="form-control rounded-3" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Password</label>
                            <input type="password" name="password" class="form-control rounded-3" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold">Account Role</label>
                            <select name="role" class="form-select rounded-3" required>
                                <option value="business_owner" <?= (isset($_POST['role']) && $_POST['role'] === 'business_owner') ? 'selected' : '' ?>>Business Owner</option>
                                <option value="staff" <?= (isset($_POST['role']) && $_POST['role'] === 'staff') ? 'selected' : '' ?>>Staff</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 fw-bold rounded-3 py-2 mb-3">
                            Register
                        </button>

                        <div class="text-center">
                            <p class="small text-muted mb-0">
                                Already have an account? <a href="index.php?page=login" class="text-decoration-none fw-bold">Login here</a>
                            </p>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>