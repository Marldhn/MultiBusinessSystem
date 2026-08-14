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

// Fetch borrower's loans (Active & Past) along with total payments collected per loan
$loansStmt = $pdo->prepare("
    SELECT l.*, 
           COALESCE(p_totals.total_paid, 0) AS total_paid
    FROM loans l
    LEFT JOIN (
        SELECT loan_id, SUM(payment_amount) AS total_paid 
        FROM loan_payments 
        WHERE business_id = ? 
        GROUP BY loan_id
    ) p_totals ON l.id = p_totals.loan_id
    WHERE l.borrower_id = ? AND l.business_id = ?
    ORDER BY l.created_at DESC
");
$loansStmt->execute([$businessId, $borrowerId, $businessId]);
$loans = $loansStmt->fetchAll(PDO::FETCH_ASSOC);

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
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0 fs-6">Active & Past Loans</h5>
                        <a href="index.php?page=create_loan&borrower_id=<?= $borrower['id'] ?>" class="btn btn-sm btn-outline-primary fw-semibold">
                            <i class="bi bi-plus-lg me-1"></i> Issue New Loan
                        </a>
                    </div>

                    <?php if (empty($loans)): ?>
                        <div class="text-center py-5 text-muted">
                            <div class="mb-2"><i class="bi bi-file-earmark-text display-6 opacity-50"></i></div>
                            <p class="mb-1 fw-semibold">No loans issued to this borrower yet.</p>
                            <p class="small text-muted mb-0">Create a new loan using the button above.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                <thead class="table-light text-uppercase text-muted" style="font-size: 0.7rem;">
                                    <tr>
                                        <th class="py-2 ps-3">Ref #</th>
                                        <th class="py-2">Principal</th>
                                        <th class="py-2">Total Payable</th>
                                        <th class="py-2">Paid</th>
                                        <th class="py-2">Status</th>
                                        <th class="py-2 text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($loans as $loan): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold text-body">
                                                <?= htmlspecialchars($loan['reference_number'] ?? 'L-' . $loan['id']) ?>
                                                <div class="text-muted fw-normal" style="font-size: 0.7rem;"><?= date('M d, Y', strtotime($loan['loan_date'] ?? $loan['created_at'])) ?></div>
                                            </td>
                                            <td>₱<?= number_format($loan['principal_amount'], 2) ?></td>
                                            <td class="fw-semibold">₱<?= number_format($loan['total_payable'], 2) ?></td>
                                            <td class="text-success fw-bold">₱<?= number_format($loan['total_paid'], 2) ?></td>
                                            <td>
                                                <?php 
                                                    $status = strtolower($loan['status'] ?? 'active');
                                                    $badgeBg = 'bg-warning bg-opacity-10 text-warning';
                                                    if ($status === 'completed' || $status === 'paid') $badgeBg = 'bg-success bg-opacity-10 text-success';
                                                    if ($status === 'defaulted') $badgeBg = 'bg-danger bg-opacity-10 text-danger';
                                                ?>
                                                <span class="badge <?= $badgeBg ?> text-capitalize" style="font-size: 0.7rem;"><?= htmlspecialchars($loan['status'] ?? 'Active') ?></span>
                                            </td>
                                            <td class="text-end pe-3">
                                                <a href="index.php?page=loan_details&id=<?= $loan['id'] ?>" class="btn btn-sm btn-light border fw-semibold px-2 py-1" style="font-size: 0.75rem;">
                                                    View <i class="bi bi-chevron-right ms-1"></i>
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
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>