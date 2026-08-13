<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'MULTIBUSINESSSYSTEM' ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Prevent flash of white on load by applying theme immediately -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('bs-theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
</head>
<body class="bg-body-tertiary" style="min-height: 100vh;">

    <!-- Shared Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg border-bottom shadow-sm mb-5 bg-body">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="index.php?page=select_business">MULTIBUSINESS SYSTEM</a>
            <div class="d-flex align-items-center gap-3">
                <?php if (isset($_SESSION['name'])): ?>
                    <span class="text-secondary small d-none d-sm-inline">Hello, <?= htmlspecialchars($_SESSION['name']) ?></span>
                <?php endif; ?>
                <a href="index.php?page=settings" class="btn btn-outline-primary btn-sm">Settings</a>
                <a href="index.php?page=logout" class="btn btn-danger btn-sm fw-bold">Log Out</a>
            </div>
        </div>
    </nav>