<?php
// Note: Database connection and session are already loaded via index.php -> app.php

$pdo = Database::getConnection();
$userId = $_SESSION['user_id'] ?? null;
$businessId = $_SESSION['business_id'] ?? null;

// Fetch businesses to display their subscription status
$stmt = $pdo->prepare("SELECT * FROM businesses WHERE owner_id = ?");
$stmt->execute([$userId]);
$businesses = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription - MULTIBUSINESSSYSTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary" style="min-height: 100vh;">

    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg border-bottom shadow-sm mb-5 bg-body">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="index.php?page=select_business">MULTIBUSINESS SYSTEM</a>
            <div class="d-flex align-items-center gap-3">
                <a href="index.php?page=settings" class="btn btn-outline-secondary btn-sm">Settings</a>
                <a href="index.php?page=select_business" class="btn btn-outline-primary btn-sm">Back to Businesses</a>
                <a href="index.php?page=logout" class="btn btn-danger btn-sm fw-bold">Log Out</a>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="container pb-5">
        <div class="row justify-content-center mb-4">
            <div class="col-md-8 text-center">
                <h2 class="fw-bold text-body">Subscription Plans</h2>
                <p class="text-muted">Manage your business subscription status and active plans.</p>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <!-- Subscription Overview Card -->
                <div class="card shadow-sm border-0 rounded-4 p-4 mb-4">
                    <h4 class="fw-bold mb-3">Current Plan Status</h4>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Business Name</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($businesses)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">No businesses found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($businesses as $biz): ?>
                                        <tr>
                                            <td class="fw-bold text-body"><?= htmlspecialchars($biz['business_name']) ?></td>
                                            <td>
                                                <span class="badge bg-success text-uppercase"><?= htmlspecialchars($biz['status'] ?? 'Active') ?></span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary fw-bold" disabled>Renew / Upgrade</button>
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
    </div>

    <!-- Include Shared Footer -->
    <?php include __DIR__ . '/partials/footer.php'; ?>