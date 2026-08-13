<?php
// Note: Database connection and session are already loaded via index.php -> app.php

$pdo = Database::getConnection();
$userId = $_SESSION['user_id'] ?? null;

// Fetch businesses belonging to the logged-in user using owner_id
$stmt = $pdo->prepare("SELECT * FROM businesses WHERE owner_id = ?");
$stmt->execute([$userId]);
$businesses = $stmt->fetchAll();

// Handle business selection submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['business_id'])) {
    $selectedBusinessId = $_POST['business_id'];

    foreach ($businesses as $b) {
        if ($b['id'] == $selectedBusinessId) {
            $_SESSION['business_id'] = $b['id'];
            $_SESSION['business_name'] = $b['business_name'];
            
            header('Location: index.php?page=dashboard');
            exit;
        }
    }
}

// Set dynamic page title
$pageTitle = "Select Business - MULTIBUSINESSSYSTEM";

// Include Shared Header (renders <html>, <head>, and the <nav> bar automatically)
include __DIR__ . '/partials/header.php';
?>

    <!-- Main Content Area Only -->
    <div class="container pb-5">
        <div class="row justify-content-center mb-4">
            <div class="col-md-8 text-center">
                <h2 class="fw-bold text-body">Select a Business</h2>
                <p class="text-muted">Choose a business below to access its dashboard and manage operations.</p>
            </div>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-md-8">
                <?php if (empty($businesses)): ?>
                    <div class="alert alert-warning text-center rounded-4 shadow-sm p-4">
                        <h5 class="fw-bold">No Businesses Found</h5>
                        <p class="mb-0 text-muted">You do not have any businesses registered under your account yet.</p>
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-md-2 g-4">
                        <?php foreach ($businesses as $business): ?>
                            <div class="col">
                                <div class="card shadow-sm border-0 rounded-4 h-100">
                                    <div class="card-body d-flex flex-column justify-content-between p-4">
                                        <div>
                                            <h4 class="fw-bold text-body mb-3"><?= htmlspecialchars($business['business_name'] ?? 'Unnamed Business') ?></h4>
                                            <p class="text-muted small mb-4">Status: <span class="badge bg-success"><?= htmlspecialchars($business['status'] ?? 'active') ?></span></p>
                                        </div>
                                        <form action="index.php?page=select_business" method="POST">
                                            <input type="hidden" name="business_id" value="<?= $business['id'] ?>">
                                            <button type="submit" class="btn btn-primary w-100 fw-bold rounded-3">Manage Business</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php 
// Include Shared Footer Partial
include __DIR__ . '/partials/footer.php'; 
?>