<?php

$pdo = Database::getConnection();

$businessId = $_SESSION['business_id'] ?? null;
$userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

if (!$businessId || !$userId) {
    header('Location: index.php?page=login');
    exit;
}

$businessId = (int) $businessId;
$userId = (int) $userId;

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| ADD GUARANTOR
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_guarantor'])) {

    $loanId = (int) ($_POST['loan_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $relationship = trim($_POST['relationship'] ?? '');
    $idType = trim($_POST['id_type'] ?? '');
    $idNumber = trim($_POST['id_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($loanId <= 0) {
        $error = 'Please select a loan.';
    } elseif ($name === '') {
        $error = 'Guarantor name is required.';
    } else {

        /*
        |--------------------------------------------------------------------------
        | Verify loan belongs to current business/user
        |--------------------------------------------------------------------------
        */

        $loanCheck = $pdo->prepare("
            SELECT id
            FROM loans
            WHERE id = ?
              AND business_id = ?
              AND created_by = ?
            LIMIT 1
        ");

        $loanCheck->execute([
            $loanId,
            $businessId,
            $userId
        ]);

        $loan = $loanCheck->fetch(PDO::FETCH_ASSOC);

        if (!$loan) {

            $error = 'Invalid loan selected.';

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO loan_guarantors
                (
                    business_id,
                    loan_id,
                    name,
                    date_of_birth,
                    occupation,
                    phone,
                    email,
                    address,
                    relationship,
                    id_type,
                    id_number,
                    notes
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $businessId,
                $loanId,
                $name,
                $dateOfBirth !== '' ? $dateOfBirth : null,
                $occupation !== '' ? $occupation : null,
                $phone !== '' ? $phone : null,
                $email !== '' ? $email : null,
                $address !== '' ? $address : null,
                $relationship !== '' ? $relationship : null,
                $idType !== '' ? $idType : null,
                $idNumber !== '' ? $idNumber : null,
                $notes !== '' ? $notes : null
            ]);

            header('Location: index.php?page=guarantors&success=added');
            exit;
        }
    }
}

/*
|--------------------------------------------------------------------------
| UPDATE GUARANTOR
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_guarantor'])) {

    $guarantorId = (int) ($_POST['guarantor_id'] ?? 0);
    $loanId = (int) ($_POST['loan_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $relationship = trim($_POST['relationship'] ?? '');
    $idType = trim($_POST['id_type'] ?? '');
    $idNumber = trim($_POST['id_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($guarantorId <= 0) {

        $error = 'Invalid guarantor.';

    } elseif ($loanId <= 0) {

        $error = 'Please select a loan.';

    } elseif ($name === '') {

        $error = 'Guarantor name is required.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Verify guarantor + loan belong to current user/business
        |--------------------------------------------------------------------------
        */

        $check = $pdo->prepare("
            SELECT g.id
            FROM loan_guarantors g
            INNER JOIN loans l
                ON l.id = g.loan_id
                AND l.business_id = g.business_id
                AND l.created_by = ?
            WHERE g.id = ?
              AND g.business_id = ?
              AND g.loan_id = ?
            LIMIT 1
        ");

        $check->execute([
            $userId,
            $guarantorId,
            $businessId,
            $loanId
        ]);

        if (!$check->fetch(PDO::FETCH_ASSOC)) {

            $error = 'You are not allowed to update this guarantor.';

        } else {

            $stmt = $pdo->prepare("
                UPDATE loan_guarantors
                SET
                    loan_id = ?,
                    name = ?,
                    date_of_birth = ?,
                    occupation = ?,
                    phone = ?,
                    email = ?,
                    address = ?,
                    relationship = ?,
                    id_type = ?,
                    id_number = ?,
                    notes = ?
                WHERE id = ?
                  AND business_id = ?
            ");

            $stmt->execute([
                $loanId,
                $name,
                $dateOfBirth !== '' ? $dateOfBirth : null,
                $occupation !== '' ? $occupation : null,
                $phone !== '' ? $phone : null,
                $email !== '' ? $email : null,
                $address !== '' ? $address : null,
                $relationship !== '' ? $relationship : null,
                $idType !== '' ? $idType : null,
                $idNumber !== '' ? $idNumber : null,
                $notes !== '' ? $notes : null,
                $guarantorId,
                $businessId
            ]);

            header('Location: index.php?page=guarantors&success=updated');
            exit;
        }
    }
}

/*
|--------------------------------------------------------------------------
| DELETE GUARANTOR
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_guarantor'])) {

    $guarantorId = (int) ($_POST['guarantor_id'] ?? 0);

    if ($guarantorId > 0) {

        $stmt = $pdo->prepare("
            DELETE g
            FROM loan_guarantors g
            INNER JOIN loans l
                ON l.id = g.loan_id
                AND l.business_id = g.business_id
                AND l.created_by = ?
            WHERE g.id = ?
              AND g.business_id = ?
        ");

        $stmt->execute([
            $userId,
            $guarantorId,
            $businessId
        ]);

        header('Location: index.php?page=guarantors&success=deleted');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGE
|--------------------------------------------------------------------------
*/

if (isset($_GET['success'])) {

    $success = match ($_GET['success']) {
        'added' => 'Guarantor added successfully!',
        'updated' => 'Guarantor updated successfully!',
        'deleted' => 'Guarantor deleted successfully!',
        default => ''
    };
}

/*
|--------------------------------------------------------------------------
| LOANS
|--------------------------------------------------------------------------
| Only loans belonging to current business/user.
|--------------------------------------------------------------------------
*/

$loanStmt = $pdo->prepare("
    SELECT
        id,
        reference_number,
        borrower_id
    FROM loans
    WHERE business_id = ?
      AND created_by = ?
    ORDER BY id DESC
");

$loanStmt->execute([
    $businessId,
    $userId
]);

$loans = $loanStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| GUARANTORS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        g.*,
        l.reference_number,
        l.id AS loan_id,
        CONCAT(
            COALESCE(b.first_name, ''),
            ' ',
            COALESCE(b.last_name, '')
        ) AS borrower_name
    FROM loan_guarantors g
    INNER JOIN loans l
        ON l.id = g.loan_id
        AND l.business_id = g.business_id
        AND l.created_by = ?
    LEFT JOIN loan_borrowers b
        ON b.id = l.borrower_id
        AND b.business_id = l.business_id
    WHERE g.business_id = ?
    ORDER BY g.created_at DESC
");

$stmt->execute([
    $userId,
    $businessId
]);

$guarantors = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

$totalGuarantors = count($guarantors);

$withPhone = 0;
$withEmail = 0;
$withId = 0;

foreach ($guarantors as $g) {

    if (!empty($g['phone'])) {
        $withPhone++;
    }

    if (!empty($g['email'])) {
        $withEmail++;
    }

    if (!empty($g['id_number'])) {
        $withId++;
    }
}

$activePage = 'guarantors';
$pageTitle = 'Guarantors - Loan Management';

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= htmlspecialchars($pageTitle) ?></title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
    rel="stylesheet"
>

<script>
(function () {
    const theme = localStorage.getItem('bs-theme') || 'light';
    document.documentElement.setAttribute('data-bs-theme', theme);
})();
</script>

<style>

body {
    min-height: 100vh;
    overflow-x: hidden;
}

.guarantors-main {
    min-width: 0;
    width: 100%;
}

.page-header {
    margin-bottom: 24px;
}

.summary-card {
    border: 0;
    border-radius: 16px;
    transition: transform .2s ease, box-shadow .2s ease;
}

.summary-card:hover {
    transform: translateY(-2px);
}

.summary-icon {
    width: 46px;
    height: 46px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.15rem;
}

.search-card,
.guarantors-card {
    border: 0;
    border-radius: 16px;
}

.search-wrapper {
    border: 1px solid var(--bs-border-color);
    border-radius: 12px;
    overflow: hidden;
}

.search-wrapper .input-group-text {
    border: 0;
    background: transparent;
}

.search-wrapper .form-control {
    border: 0;
    background: transparent;
}

.search-wrapper .form-control:focus {
    box-shadow: none;
}

.guarantors-table th {
    font-size: .72rem;
    letter-spacing: .04em;
    white-space: nowrap;
}

.guarantors-table td {
    padding-top: 14px;
    padding-bottom: 14px;
}

.guarantor-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    flex-shrink: 0;
}

.mobile-guarantors {
    display: none;
}

.mobile-guarantor-card {
    border: 1px solid var(--bs-border-color);
    border-radius: 14px;
    padding: 14px;
    margin-bottom: 10px;
    background: var(--bs-body-bg);
}

.mobile-guarantor-card:last-child {
    margin-bottom: 0;
}

.mobile-guarantor-top {
    display: flex;
    align-items: center;
    gap: 11px;
}

.mobile-guarantor-name {
    font-size: .9rem;
    font-weight: 700;
}

.mobile-guarantor-info {
    font-size: .76rem;
    color: var(--bs-secondary-color);
    line-height: 1.6;
}

.mobile-guarantor-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-top: 12px;
    padding-top: 10px;
    border-top: 1px solid var(--bs-border-color);
}

.empty-state {
    padding: 55px 20px;
}

.page-alert {
    border: 0;
    border-radius: 12px;
}

.modal-content {
    border-radius: 18px;
}

@media (max-width: 767.98px) {

    .desktop-guarantors {
        display: none;
    }

    .mobile-guarantors {
        display: block;
        padding: 12px;
    }

}

@media (max-width: 575.98px) {

    .main-content {
        padding: 14px !important;
    }

    .page-header {
        margin-bottom: 16px;
    }

    .page-title {
        font-size: 1.35rem;
    }

    .page-subtitle {
        font-size: .76rem;
        line-height: 1.4;
    }

    .add-guarantor-btn {
        width: 100%;
        justify-content: center;
    }

    .summary-row {
        --bs-gutter-x: 10px;
        --bs-gutter-y: 10px;
    }

    .summary-card {
        border-radius: 14px;
    }

    .summary-card .card-body {
        padding: 15px;
    }

    .summary-icon {
        width: 40px;
        height: 40px;
        border-radius: 11px;
        font-size: 1rem;
    }

    .summary-label {
        font-size: .65rem !important;
    }

    .summary-value {
        font-size: 1.2rem !important;
    }

    .search-card,
    .guarantors-card {
        border-radius: 14px;
    }

    .modal-dialog {
        margin: 10px;
    }

    .modal-body {
        padding: 16px !important;
    }

    .modal-header,
    .modal-footer {
        padding: 14px 16px !important;
    }

}

</style>

</head>

<body class="bg-body-tertiary">

<div class="d-flex flex-column flex-lg-row min-vh-100">

<?php include __DIR__ . '/../../../resources/partials/loansidebar.php'; ?>

<main class="guarantors-main flex-grow-1 bg-body-tertiary">

<div class="main-content p-3 p-md-4">

<!-- HEADER -->

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

<div>

<div class="d-flex align-items-center gap-2 mb-1">

<div class="d-lg-none bg-primary bg-opacity-10 text-primary rounded-3 p-2">
<i class="bi bi-person-check"></i>
</div>

<h2 class="page-title fw-bold text-body mb-0">
Guarantors
</h2>

</div>

<p class="page-subtitle text-muted small mb-0">

Manage guarantors for loans under

<span class="fw-semibold text-primary">
<?= htmlspecialchars($_SESSION['business_name'] ?? 'Business') ?>
</span>

</p>

</div>

<button
    type="button"
    class="add-guarantor-btn btn btn-primary fw-bold px-3 py-2 rounded-3 shadow-sm d-flex align-items-center gap-2 text-nowrap"
    data-bs-toggle="modal"
    data-bs-target="#addGuarantorModal"
>

<i class="bi bi-person-plus-fill"></i>

<span>Add Guarantor</span>

</button>

</div>

<!-- ALERTS -->

<?php if ($success): ?>

<div class="page-alert alert alert-success alert-dismissible fade show py-2 px-3 shadow-sm small">

<i class="bi bi-check-circle-fill me-2"></i>

<?= htmlspecialchars($success) ?>

<button
    type="button"
    class="btn-close"
    data-bs-dismiss="alert"
></button>

</div>

<?php endif; ?>

<?php if ($error): ?>

<div class="page-alert alert alert-danger alert-dismissible fade show py-2 px-3 shadow-sm small">

<i class="bi bi-exclamation-triangle-fill me-2"></i>

<?= htmlspecialchars($error) ?>

<button
    type="button"
    class="btn-close"
    data-bs-dismiss="alert"
></button>

</div>

<?php endif; ?>

<!-- SUMMARY CARDS -->

<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 summary-row g-3 mb-4">

<div class="col">

<div class="card summary-card shadow-sm bg-body h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start gap-3">

<div>

<div class="summary-label text-muted fw-bold small mb-2">
TOTAL GUARANTORS
</div>

<div class="summary-value fw-bold fs-3 text-primary">
<?= number_format($totalGuarantors) ?>
</div>

<div class="small text-muted mt-1">
Registered guarantors
</div>

</div>

<div class="summary-icon bg-primary bg-opacity-10 text-primary">
<i class="bi bi-people"></i>
</div>

</div>

</div>

</div>

</div>

<div class="col">

<div class="card summary-card shadow-sm bg-body h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start gap-3">

<div>

<div class="summary-label text-muted fw-bold small mb-2">
WITH PHONE
</div>

<div class="summary-value fw-bold fs-3 text-success">
<?= number_format($withPhone) ?>
</div>

<div class="small text-muted mt-1">
Contact information
</div>

</div>

<div class="summary-icon bg-success bg-opacity-10 text-success">
<i class="bi bi-telephone"></i>
</div>

</div>

</div>

</div>

</div>

<div class="col">

<div class="card summary-card shadow-sm bg-body h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start gap-3">

<div>

<div class="summary-label text-muted fw-bold small mb-2">
WITH EMAIL
</div>

<div class="summary-value fw-bold fs-3 text-info">
<?= number_format($withEmail) ?>
</div>

<div class="small text-muted mt-1">
Email information
</div>

</div>

<div class="summary-icon bg-info bg-opacity-10 text-info">
<i class="bi bi-envelope"></i>
</div>

</div>

</div>

</div>

</div>

<div class="col">

<div class="card summary-card shadow-sm bg-body h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start gap-3">

<div>

<div class="summary-label text-muted fw-bold small mb-2">
WITH VALID ID
</div>

<div class="summary-value fw-bold fs-3 text-warning">
<?= number_format($withId) ?>
</div>

<div class="small text-muted mt-1">
ID information
</div>

</div>

<div class="summary-icon bg-warning bg-opacity-10 text-warning">
<i class="bi bi-card-text"></i>
</div>

</div>

</div>

</div>

</div>

</div>

<!-- SEARCH -->

<div class="search-card card shadow-sm mb-4">

<div class="card-body p-3">

<div class="search-wrapper input-group">

<span class="input-group-text text-muted">
<i class="bi bi-search"></i>
</span>

<input
    type="text"
    id="searchGuarantor"
    class="form-control shadow-none"
    placeholder="Search name, loan, borrower, phone, email..."
    autocomplete="off"
>

<button
    type="button"
    id="clearSearch"
    class="btn d-none"
>

<i class="bi bi-x-lg"></i>

</button>

</div>

</div>

</div>

<!-- GUARANTORS -->

<div class="guarantors-card card shadow-sm bg-body">

<!-- DESKTOP -->

<div class="desktop-guarantors table-responsive">

<table class="guarantors-table table table-hover align-middle mb-0">

<thead class="table-light text-uppercase text-muted">

<tr>

<th class="py-3 ps-4">
Guarantor
</th>

<th class="py-3">
Loan
</th>

<th class="py-3">
Borrower
</th>

<th class="py-3">
Relationship
</th>

<th class="py-3">
Contact
</th>

<th class="py-3">
ID
</th>

<th class="py-3 text-end pe-4">
Actions
</th>

</tr>

</thead>

<tbody id="guarantorTableBody">

<?php if (empty($guarantors)): ?>

<tr>

<td colspan="7">

<div class="empty-state text-center text-muted">

<div class="mb-3">

<i class="bi bi-person-check display-6 opacity-50"></i>

</div>

<div class="fw-semibold mb-1">
No guarantors found
</div>

<div class="small mb-3">
Add a guarantor to one of your loans.
</div>

<button
    type="button"
    class="btn btn-sm btn-primary fw-bold"
    data-bs-toggle="modal"
    data-bs-target="#addGuarantorModal"
>

<i class="bi bi-person-plus me-1"></i>
Add Guarantor

</button>

</div>

</td>

</tr>

<?php else: ?>

<?php foreach ($guarantors as $g): ?>

<?php

$nameParts = preg_split('/\s+/', trim($g['name'] ?? ''));

$initial = strtoupper(
    substr($nameParts[0] ?? 'G', 0, 1)
);

?>

<tr class="guarantor-row">

<td class="ps-4">

<div class="d-flex align-items-center gap-3">

<div class="guarantor-avatar bg-primary bg-opacity-10 text-primary">
<?= htmlspecialchars($initial) ?>
</div>

<div>

<div class="fw-bold text-body">
<?= htmlspecialchars($g['name']) ?>
</div>

<div class="small text-muted">

<?php if (!empty($g['occupation'])): ?>

<?= htmlspecialchars($g['occupation']) ?>

<?php else: ?>

Guarantor

<?php endif; ?>

</div>

</div>

</div>

</td>

<td>

<span class="badge bg-primary bg-opacity-10 text-primary fw-semibold">

<?= htmlspecialchars(
    $g['reference_number'] ?? '#' . $g['loan_id']
) ?>

</span>

</td>

<td>

<?php if (!empty(trim($g['borrower_name']))): ?>

<span class="fw-semibold text-body small">

<?= htmlspecialchars(trim($g['borrower_name'])) ?>

</span>

<?php else: ?>

<span class="text-muted small">
Unknown borrower
</span>

<?php endif; ?>

</td>

<td>

<?php if (!empty($g['relationship'])): ?>

<span class="badge bg-secondary bg-opacity-10 text-body">
<?= htmlspecialchars($g['relationship']) ?>
</span>

<?php else: ?>

<span class="text-muted small">
Not provided
</span>

<?php endif; ?>

</td>

<td>

<?php if (!empty($g['phone'])): ?>

<div class="small">

<i class="bi bi-telephone text-muted me-1"></i>

<?= htmlspecialchars($g['phone']) ?>

</div>

<?php endif; ?>

<?php if (!empty($g['email'])): ?>

<div class="small text-muted text-truncate" style="max-width:180px;">

<i class="bi bi-envelope me-1"></i>

<?= htmlspecialchars($g['email']) ?>

</div>

<?php endif; ?>

<?php if (empty($g['phone']) && empty($g['email'])): ?>

<span class="text-muted small">
No contact
</span>

<?php endif; ?>

</td>

<td>

<?php if (!empty($g['id_number'])): ?>

<span class="badge bg-success bg-opacity-10 text-success">

<i class="bi bi-check-circle me-1"></i>
<?= htmlspecialchars($g['id_type'] ?? 'ID') ?>

</span>

<div class="small text-muted mt-1">
<?= htmlspecialchars($g['id_number']) ?>
</div>

<?php else: ?>

<span class="text-muted small">
No ID
</span>

<?php endif; ?>

</td>

<td class="text-end pe-4">

<button
    type="button"
    class="btn btn-sm btn-outline-primary rounded-3 me-1"
    onclick='editGuarantor(<?= json_encode($g, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
    title="Edit Guarantor"
>

<i class="bi bi-pencil"></i>

</button>

<button
    type="button"
    class="btn btn-sm btn-outline-danger rounded-3"
    onclick="deleteGuarantor(<?= (int)$g['id'] ?>, <?= htmlspecialchars(json_encode($g['name'])) ?>)"
    title="Delete Guarantor"
>

<i class="bi bi-trash"></i>

</button>

</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

</tbody>

</table>

</div>

<!-- MOBILE -->

<div class="mobile-guarantors" id="mobileGuarantors">

<?php if (empty($guarantors)): ?>

<div class="empty-state text-center text-muted">

<i class="bi bi-person-check display-6 opacity-50"></i>

<div class="fw-semibold mt-3 mb-1">
No guarantors found
</div>

<div class="small">
Add your first guarantor.
</div>

</div>

<?php else: ?>

<?php foreach ($guarantors as $g): ?>

<?php

$nameParts = preg_split('/\s+/', trim($g['name'] ?? ''));

$initial = strtoupper(
    substr($nameParts[0] ?? 'G', 0, 1)
);

$searchData = strtolower(
    ($g['name'] ?? '') . ' ' .
    ($g['reference_number'] ?? '') . ' ' .
    ($g['borrower_name'] ?? '') . ' ' .
    ($g['relationship'] ?? '') . ' ' .
    ($g['phone'] ?? '') . ' ' .
    ($g['email'] ?? '') . ' ' .
    ($g['address'] ?? '') . ' ' .
    ($g['id_number'] ?? '')
);

?>

<div
    class="mobile-guarantor-card guarantor-mobile-item"
    data-search="<?= htmlspecialchars($searchData) ?>"
>

<div class="mobile-guarantor-top">

<div class="guarantor-avatar bg-primary bg-opacity-10 text-primary">
<?= htmlspecialchars($initial) ?>
</div>

<div class="flex-grow-1 min-width-0">

<div class="mobile-guarantor-name text-body">
<?= htmlspecialchars($g['name']) ?>
</div>

<div class="small text-muted">

<?= !empty($g['occupation'])
    ? htmlspecialchars($g['occupation'])
    : 'Guarantor'
?>

</div>

</div>

<span class="badge bg-primary bg-opacity-10 text-primary">
<?= htmlspecialchars($g['reference_number'] ?? '#' . $g['loan_id']) ?>
</span>

</div>

<div class="mobile-guarantor-info mt-3">

<div>

<i class="bi bi-person me-1"></i>

<strong>Borrower:</strong>

<?= !empty(trim($g['borrower_name']))
    ? htmlspecialchars(trim($g['borrower_name']))
    : 'Unknown'
?>

</div>

<div>

<i class="bi bi-diagram-3 me-1"></i>

<strong>Relationship:</strong>

<?= !empty($g['relationship'])
    ? htmlspecialchars($g['relationship'])
    : 'Not provided'
?>

</div>

<?php if (!empty($g['phone'])): ?>

<div>

<i class="bi bi-telephone me-1"></i>

<?= htmlspecialchars($g['phone']) ?>

</div>

<?php endif; ?>

<?php if (!empty($g['email'])): ?>

<div>

<i class="bi bi-envelope me-1"></i>

<?= htmlspecialchars($g['email']) ?>

</div>

<?php endif; ?>

<?php if (!empty($g['address'])): ?>

<div class="mt-1">

<i class="bi bi-geo-alt me-1"></i>

<?= htmlspecialchars($g['address']) ?>

</div>

<?php endif; ?>

<?php if (!empty($g['id_number'])): ?>

<div class="mt-1">

<i class="bi bi-card-text me-1"></i>

<?= htmlspecialchars($g['id_type'] ?? 'ID') ?>:

<?= htmlspecialchars($g['id_number']) ?>

</div>

<?php endif; ?>

</div>

<div class="mobile-guarantor-footer">

<span class="small text-muted">
Guarantor Profile
</span>

<div>

<button
    type="button"
    class="btn btn-sm btn-outline-primary rounded-3 me-1"
    onclick='editGuarantor(<?= json_encode($g, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
>

<i class="bi bi-pencil"></i>
</button>

<button
    type="button"
    class="btn btn-sm btn-outline-danger rounded-3"
    onclick="deleteGuarantor(<?= (int)$g['id'] ?>, <?= htmlspecialchars(json_encode($g['name'])) ?>)"
>

<i class="bi bi-trash"></i>
</button>

</div>

</div>

</div>

<?php endforeach; ?>

<?php endif; ?>

</div>

<div
    id="noSearchResults"
    class="text-center text-muted py-5 d-none"
>

<i class="bi bi-search display-6 opacity-50"></i>

<div class="fw-semibold mt-3">
No guarantors found
</div>

<div class="small">
Try searching using another name, loan, borrower, phone, or email.
</div>

</div>

</div>

</div>

</main>

</div>

<!-- ADD MODAL -->

<div
    class="modal fade"
    id="addGuarantorModal"
    tabindex="-1"
>

<div class="modal-dialog modal-lg modal-dialog-centered">

<div class="modal-content border-0 shadow-lg">

<div class="modal-header">

<h5 class="modal-title fw-bold">

<i class="bi bi-person-plus-fill text-primary me-2"></i>

Add New Guarantor

</h5>

<button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal"
></button>

</div>

<form method="POST">

<input
    type="hidden"
    name="add_guarantor"
    value="1"
>

<div class="modal-body">

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label fw-semibold small">
Loan <span class="text-danger">*</span>
</label>

<select
    name="loan_id"
    class="form-select"
    required
>

<option value="">
Select Loan
</option>

<?php foreach ($loans as $loan): ?>

<option value="<?= (int)$loan['id'] ?>">

<?= htmlspecialchars(
    $loan['reference_number'] ?? '#' . $loan['id']
) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-6 mb-3">

<label class="form-label fw-semibold small">
Guarantor Name <span class="text-danger">*</span>
</label>

<input
    type="text"
    name="name"
    class="form-control"
    required
    placeholder="Full name"
>

</div>

</div>

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label fw-semibold small">
Date of Birth
</label>

<input
    type="date"
    name="date_of_birth"
    class="form-control"
>

</div>

<div class="col-md-6 mb-3">

<label class="form-label fw-semibold small">
Occupation
</label>

<input
    type="text"
    name="occupation"
    class="form-control"
    placeholder="e.g. Teacher"
>

</div>

</div>

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label fw-semibold small">
Phone
</label>

<input
    type="tel"
    name="phone"
    class="form-control"
    placeholder="09123456789"
>

</div>

<div class="col-md-6 mb-3">

<label class="form-label fw-semibold small">
Email
</label>

<input
    type="email"
    name="email"
    class="form-control"
    placeholder="example@email.com"
>

</div>

</div>

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label fw-semibold small">
Relationship to Borrower
</label>

<input
    type="text"
    name="relationship"
    class="form-control"
    placeholder="e.g. Brother, Friend, Spouse"
>

</div>

<div class="col-md-3 mb-3">

<label class="form-label fw-semibold small">
ID Type
</label>

<input
    type="text"
    name="id_type"
    class="form-control"
    placeholder="e.g. Driver's License"
>

</div>

<div class="col-md-3 mb-3">

<label class="form-label fw-semibold small">
ID Number
</label>

<input
    type="text"
    name="id_number"
    class="form-control"
    placeholder="ID number"
>

</div>

</div>

<div class="mb-3">

<label class="form-label fw-semibold small">
Complete Address
</label>

<textarea
    name="address"
    class="form-control"
    rows="3"
    placeholder="Complete address"
></textarea>

</div>

<div>

<label class="form-label fw-semibold small">
Notes
</label>

<textarea
    name="notes"
    class="form-control"
    rows="2"
    placeholder="Additional information"
></textarea>

</div>

</div>

<div class="modal-footer">

<button
    type="button"
    class="btn btn-light fw-semibold"
    data-bs-dismiss="modal"
>
Cancel
</button>

<button
    type="submit"
    class="btn btn-primary fw-bold"
>

<i class="bi bi-check-lg me-1"></i>

Save Guarantor

</button>

</div>

</form>

</div>

</div>

</div>

<!-- EDIT MODAL -->

<div
    class="modal fade"
    id="editGuarantorModal"
    tabindex="-1"
>

<div class="modal-dialog modal-lg modal-dialog-centered">

<div class="modal-content border-0 shadow-lg">

<div class="modal-header">

<h5 class="modal-title fw-bold">

<i class="bi bi-pencil-square text-primary me-2"></i>

Edit Guarantor

</h5>

<button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal"
></button>

</div>

<form method="POST">

<input
    type="hidden"
    name="update_guarantor"
    value="1"
>

<input
    type="hidden"
    name="guarantor_id"
    id="edit_guarantor_id"
>

<div class="modal-body">

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label fw-semibold small">
Loan
</label>

<select
    name="loan_id"
    id="edit_loan_id"
    class="form-select"
    required
>

<?php foreach ($loans as $loan): ?>

<option value="<?= (int)$loan['id'] ?>">

<?= htmlspecialchars(
    $loan['reference_number'] ?? '#' . $loan['id']
) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-6 mb-3">

<label class="form-label fw-semibold small">
Guarantor Name
</label>

<input
    type="text"
    name="name"
    id="edit_name"
    class="form-control"
    required
>

</div>

</div>

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label fw-semibold small">
Date of Birth
</label>

<input
    type="date"
    name="date_of_birth"
    id="edit_date_of_birth"
    class="form-control"
>

</div>

<div class="col-md-6 mb-3">

<label class="form-label fw-semibold small">
Occupation
</label>

<input
    type="text"
    name="occupation"
    id="edit_occupation"
    class="form-control"
>

</div>

</div>

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label fw-semibold small">
Phone
</label>

<input
    type="tel"
    name="phone"
    id="edit_phone"
    class="form-control"
>

</div>

<div class="col-md-6 mb-3">

<label class="form-label fw-semibold small">
Email
</label>

<input
    type="email"
    name="email"
    id="edit_email"
    class="form-control"
>

</div>

</div>

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label fw-semibold small">
Relationship
</label>

<input
    type="text"
    name="relationship"
    id="edit_relationship"
    class="form-control"
>

</div>

<div class="col-md-3 mb-3">

<label class="form-label fw-semibold small">
ID Type
</label>

<input
    type="text"
    name="id_type"
    id="edit_id_type"
    class="form-control"
>

</div>

<div class="col-md-3 mb-3">

<label class="form-label fw-semibold small">
ID Number
</label>

<input
    type="text"
    name="id_number"
    id="edit_id_number"
    class="form-control"
>

</div>

</div>

<div class="mb-3">

<label class="form-label fw-semibold small">
Complete Address
</label>

<textarea
    name="address"
    id="edit_address"
    class="form-control"
    rows="3"
></textarea>

</div>

<div>

<label class="form-label fw-semibold small">
Notes
</label>

<textarea
    name="notes"
    id="edit_notes"
    class="form-control"
    rows="2"
></textarea>

</div>

</div>

<div class="modal-footer">

<button
    type="button"
    class="btn btn-light fw-semibold"
    data-bs-dismiss="modal"
>
Cancel
</button>

<button
    type="submit"
    class="btn btn-primary fw-bold"
>

<i class="bi bi-check-lg me-1"></i>

Save Changes

</button>

</div>

</form>

</div>

</div>

</div>

<!-- DELETE FORM -->

<form
    method="POST"
    id="deleteGuarantorForm"
    class="d-none"
>

<input
    type="hidden"
    name="delete_guarantor"
    value="1"
>

<input
    type="hidden"
    name="guarantor_id"
    id="delete_guarantor_id"
>

</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>

document.addEventListener('DOMContentLoaded', function () {

    const searchInput = document.getElementById('searchGuarantor');
    const clearButton = document.getElementById('clearSearch');
    const noResults = document.getElementById('noSearchResults');

    const desktopRows = document.querySelectorAll('.guarantor-row');
    const mobileItems = document.querySelectorAll('.guarantor-mobile-item');

    function performSearch() {

        const term = searchInput.value.toLowerCase().trim();

        let desktopMatches = 0;
        let mobileMatches = 0;

        desktopRows.forEach(function (row) {

            const text = row.textContent.toLowerCase();

            if (!term || text.includes(term)) {

                row.style.display = '';
                desktopMatches++;

            } else {

                row.style.display = 'none';

            }

        });

        mobileItems.forEach(function (item) {

            const text = item.getAttribute('data-search') || '';

            if (!term || text.includes(term)) {

                item.style.display = '';
                mobileMatches++;

            } else {

                item.style.display = 'none';

            }

        });

        if (term) {
            clearButton.classList.remove('d-none');
        } else {
            clearButton.classList.add('d-none');
        }

        const mobile = window.innerWidth < 768;

        const matches = mobile
            ? mobileMatches
            : desktopMatches;

        if (term && matches === 0) {
            noResults.classList.remove('d-none');
        } else {
            noResults.classList.add('d-none');
        }

    }

    searchInput.addEventListener(
        'input',
        performSearch
    );

    clearButton.addEventListener(
        'click',
        function () {

            searchInput.value = '';

            performSearch();

            searchInput.focus();

        }
    );

});

function editGuarantor(guarantor) {

    document.getElementById('edit_guarantor_id').value =
        guarantor.id || '';

    document.getElementById('edit_loan_id').value =
        guarantor.loan_id || '';

    document.getElementById('edit_name').value =
        guarantor.name || '';

    document.getElementById('edit_date_of_birth').value =
        guarantor.date_of_birth || '';

    document.getElementById('edit_occupation').value =
        guarantor.occupation || '';

    document.getElementById('edit_phone').value =
        guarantor.phone || '';

    document.getElementById('edit_email').value =
        guarantor.email || '';

    document.getElementById('edit_relationship').value =
        guarantor.relationship || '';

    document.getElementById('edit_id_type').value =
        guarantor.id_type || '';

    document.getElementById('edit_id_number').value =
        guarantor.id_number || '';

    document.getElementById('edit_address').value =
        guarantor.address || '';

    document.getElementById('edit_notes').value =
        guarantor.notes || '';

    const modal = bootstrap.Modal.getOrCreateInstance(
        document.getElementById('editGuarantorModal')
    );

    modal.show();

}

function deleteGuarantor(id, name) {

    if (!confirm(
        'Are you sure you want to delete the guarantor "' +
        name +
        '"?'
    )) {
        return;
    }

    document.getElementById('delete_guarantor_id').value = id;

    document.getElementById('deleteGuarantorForm').submit();

}

</script>

</body>

</html>