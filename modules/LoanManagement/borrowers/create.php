<?php
$pdo = Database::getConnection();
$businessId = $_SESSION['business_id'] ?? null;

if (!$businessId) {
    header('Location: index.php?page=select_business');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO borrowers (business_id, name, phone, address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$businessId, $name, $phone, $address]);
        header('Location: index.php?page=borrowers');
        exit;
    } else {
        $error = "Borrower name is required.";
    }
}

$activePage = 'borrowers';
$pageTitle = "Add New Borrower - Loan Management";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script>
        (function() {
            const savedTheme = localStorage.getItem('bs-theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
</head>
<body class="bg-body-tertiary" style="min-height: 100vh;">

<div class="d-flex flex-column flex-lg-row" style="min-height: 100vh;">
    <?php include __DIR__ . '/../../../resources/partials/loansidebar.php'; ?>

    <div class="p-3 p-md-4 flex-grow-1 bg-body-tertiary">
        <div class="mb-4">
            <a href="index.php?page=borrowers" class="text-decoration-none small fw-bold text-muted mb-2 d-inline-block">
                <i class="bi bi-arrow-left me-1"></i> Back to Borrowers
            </a>
            <h2 class="fw-bold text-body mb-1 fs-3 fs-md-2">Add New Borrower</h2>
            <p class="text-muted small mb-0">Register a client under: <span class="fw-bold text-primary"><?= htmlspecialchars($_SESSION['business_name'] ?? '') ?></span></p>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4" style="max-width: 600px;">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control shadow-none" required placeholder="e.g. Juan Dela Cruz">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Contact Number</label>
                    <input type="text" name="phone" class="form-control shadow-none" placeholder="e.g. 09123456789">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold small">Complete Address</label>
                    <textarea name="address" class="form-control shadow-none" rows="3" placeholder="e.g. Purok 3, Barangay Central, City"></textarea>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <a href="index.php?page=borrowers" class="btn btn-light px-4 fw-semibold">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">Save Borrower</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>