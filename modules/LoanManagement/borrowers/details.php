<?php
$pdo = Database::getConnection();
$businessId = $_SESSION['business_id'] ?? null;
$borrowerId = $_GET['id'] ?? null;

if (!$businessId || !$borrowerId) {
    header('Location: index.php?page=borrowers');
    exit;
}

// Handle update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_borrower'])) {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $contactNo = trim($_POST['contact_no'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (!empty($firstName) && !empty($lastName)) {
        $updateStmt = $pdo->prepare("UPDATE loan_borrowers SET first_name = ?, last_name = ?, contact_no = ?, address = ? WHERE id = ? AND business_id = ?");
        $updateStmt->execute([$firstName, $lastName, $contactNo, $address, $borrowerId, $businessId]);
        header("Location: index.php?page=borrower_details&id=$borrowerId&success=1");
        exit;
    }
}

// Fetch borrower profile
$stmt = $pdo->prepare("SELECT * FROM loan_borrowers WHERE id = ? AND business_id = ?");
$stmt->execute([$borrowerId, $businessId]);
$borrower = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$borrower) {
    header('Location: index.php?page=borrowers');
    exit;
}

$activePage = 'borrowers';
$pageTitle = htmlspecialchars($borrower['first_name'] . ' ' . $borrower['last_name']) . " - Borrower Details";
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
            <h2 class="fw-bold text-body mb-1 fs-3 fs-md-2"><?= htmlspecialchars($borrower['first_name'] . ' ' . $borrower['last_name']) ?></h2>
            <p class="text-muted small mb-0">Client Profile & Loan History</p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success py-2 small mb-4">Borrower details updated successfully!</div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Borrower Edit/Info Card -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h5 class="fw-bold mb-3 fs-6 text-primary">Edit Borrower Info</h5>
                    <form method="POST">
                        <input type="hidden" name="update_borrower" value="1">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">First Name</label>
                            <input type="text" name="first_name" class="form-control shadow-none" value="<?= htmlspecialchars($borrower['first_name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Last Name</label>
                            <input type="text" name="last_name" class="form-control shadow-none" value="<?= htmlspecialchars($borrower['last_name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Contact Number</label>
                            <input type="text" name="contact_no" class="form-control shadow-none" value="<?= htmlspecialchars($borrower['contact_no'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Address</label>
                            <textarea name="address" class="form-control shadow-none" rows="3"><?= htmlspecialchars($borrower['address'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm py-2">Update Profile</button>
                    </form>
                </div>
            </div>

            <!-- Loan History Section -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h5 class="fw-bold mb-3 fs-6">Active & Past Loans</h5>
                    <p class="text-muted small">No loans issued to this borrower yet.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>