<?php
$pdo = Database::getConnection();
$businessId = $_SESSION['business_id'] ?? null;
$userId = $_SESSION['user_id'] ?? null;

if (!$businessId) {
    header('Location: index.php?page=select_business');
    exit;
}

$activePage = 'collaterals';
$pageTitle = "Collateral Management - Loan Management";

$stmt = $pdo->prepare("
    SELECT
        c.*,
        l.reference_number,
        l.principal_amount,
        l.total_payable,
        l.status AS loan_status,
        l.due_date,
        l.created_by,
        CONCAT(b.first_name, ' ', b.last_name) AS borrower_name,
        b.contact_no AS contact_number,
        a.account_name
    FROM loan_collaterals c
    INNER JOIN loans l ON c.loan_id = l.id
    INNER JOIN loan_borrowers b ON l.borrower_id = b.id
    INNER JOIN loan_accounts a ON l.account_id = a.id
    WHERE c.business_id = ?
      AND l.business_id = ?
      AND l.created_by = ?
    ORDER BY c.created_at DESC
");

$stmt->execute([
    $businessId,
    $businessId,
    $userId
]);

$collaterals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=htmlspecialchars($pageTitle)?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<script>
(function(){
    const savedTheme=localStorage.getItem('bs-theme')||'light';
    document.documentElement.setAttribute('data-bs-theme',savedTheme);
})();
</script>

<style>
body{font-size:.9rem}
.object-fit-cover{object-fit:cover}
.card{transition:transform .15s ease}
.card:hover{transform:translateY(-2px)}
</style>
</head>

<body class="bg-body-tertiary" style="min-height:100vh">

<div class="d-flex flex-column flex-lg-row" style="min-height:100vh">

<?php include __DIR__.'/../../../resources/partials/loansidebar.php'; ?>

<div class="p-3 p-md-4 flex-grow-1 bg-body-tertiary">

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

<div>
<h2 class="fw-bold text-body mb-1 fs-3 fs-md-2">Collateral Index</h2>

<p class="text-muted small mb-0">
Track pledged items, specs, and estimated values for
<span class="fw-bold text-primary">
<?=htmlspecialchars($_SESSION['business_name']??'')?>
</span>
</p>
</div>

<a href="index.php?page=loans"
class="btn btn-primary fw-bold px-3 py-2 rounded-3 text-nowrap shadow-sm">
<i class="bi bi-plus-circle-fill me-1"></i>
Issue New Loan w/ Collateral
</a>

</div>

<?php if(empty($collaterals)): ?>

<div class="card border-0 shadow-sm rounded-4 p-5 text-center text-muted">

<div class="mb-2">
<i class="bi bi-shield-exclamation display-6 opacity-50"></i>
</div>

<p class="mb-1 fw-semibold">
No collaterals recorded yet
</p>

<p class="small text-muted mb-3">
Collateral items added during loan issuance will appear here.
</p>

<div>
<a href="index.php?page=loans"
class="btn btn-sm btn-primary fw-bold">
+ Go to Loans
</a>
</div>

</div>

<?php else: ?>

<div class="row g-4">

<?php foreach($collaterals as $col):

$imagePath='';

if(!empty($col['image_path'])){

    if(strpos($col['image_path'],'uploads/')===0){

        $imagePath=$col['image_path'];

    }elseif(
        file_exists(
            __DIR__.'/../../public/uploads/collaterals/'.basename($col['image_path'])
        )
    ){

        $imagePath='uploads/collaterals/'.basename($col['image_path']);

    }else{

        $imagePath='uploads/'.basename($col['image_path']);

    }
}

?>

<div class="col-12 col-md-6 col-xl-4">

<div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden d-flex flex-column">

<div class="position-relative bg-secondary bg-opacity-10 d-flex align-items-center justify-content-center"
style="height:180px">

<?php if(!empty($imagePath)): ?>

<a href="#"
data-bs-toggle="modal"
data-bs-target="#imageModal<?= (int)$col['id'] ?>"
class="w-100 h-100">

<img
src="<?=htmlspecialchars($imagePath)?>"
alt="Collateral"
class="w-100 h-100 object-fit-cover">

</a>

<?php else: ?>

<div class="text-muted text-center">

<i class="bi bi-image display-5"></i>

<div class="small mt-1">
No Image Available
</div>

</div>

<?php endif; ?>

<span class="position-absolute top-0 end-0 m-3 badge bg-dark bg-opacity-75 px-2 py-1 shadow-sm">
<?=htmlspecialchars(ucfirst($col['loan_status']))?>
</span>

</div>

<div class="card-body p-4 d-flex flex-column justify-content-between">

<div>

<div class="d-flex justify-content-between align-items-start mb-2">

<h5
class="fw-bold text-body mb-0 text-truncate"
style="max-width:200px"
title="<?=htmlspecialchars($col['item_name'])?>">

<?=htmlspecialchars($col['item_name'])?>

</h5>

<span class="text-success fw-bold fs-5">
₱<?=number_format((float)$col['estimated_value'],2)?>
</span>

</div>

<p class="text-muted small text-truncate mb-3">

<?=htmlspecialchars(
$col['description']??'No additional description provided.'
)?>

</p>

<div class="p-3 bg-body-tertiary rounded-3 mb-3 small">

<div class="mb-1 text-body">

<strong>Borrower:</strong>
<?=htmlspecialchars($col['borrower_name'])?>

</div>

<div class="mb-1 text-muted">

<i class="bi bi-telephone me-1"></i>

<?=htmlspecialchars($col['contact_number']??'N/A')?>

</div>

<div class="text-primary fw-semibold mt-2 pt-2 border-top">

<?=htmlspecialchars(
$col['reference_number']??('Ref: #'.$col['loan_id'])
)?>

—
₱<?=number_format((float)$col['total_payable'],2)?>

</div>

</div>

</div>

<div>

<button
type="button"
class="btn btn-sm btn-outline-primary w-100 fw-semibold py-2"
data-bs-toggle="modal"
data-bs-target="#detailsModal<?= (int)$col['id'] ?>">

<i class="bi bi-eye me-1"></i>
View Full Details

</button>

</div>

</div>

</div>

</div>

<?php if(!empty($imagePath)): ?>

<div
class="modal fade"
id="imageModal<?= (int)$col['id'] ?>"
tabindex="-1"
aria-hidden="true">

<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">

<div class="modal-content border-0 shadow-lg rounded-4 bg-dark text-white text-center p-3">

<div class="modal-header border-0 pb-0">

<h6 class="modal-title text-light fw-bold">
<?=htmlspecialchars($col['item_name'])?>
</h6>

<button
type="button"
class="btn-close btn-close-white shadow-none"
data-bs-dismiss="modal">
</button>

</div>

<div class="modal-body p-2">

<img
src="<?=htmlspecialchars($imagePath)?>"
alt="Full Collateral Image"
class="img-fluid rounded shadow"
style="max-height:400px;object-fit:contain">

</div>

</div>

</div>

</div>

<?php endif; ?>

<div
class="modal fade"
id="detailsModal<?= (int)$col['id'] ?>"
tabindex="-1"
aria-hidden="true">

<div class="modal-dialog modal-dialog-centered">

<div class="modal-content border-0 shadow-lg rounded-4">

<div class="modal-header border-bottom px-4 py-3">

<h5 class="modal-title fw-bold fs-6 text-body">

<i class="bi bi-shield-fill-check text-primary me-2"></i>

Collateral & Loan Overview

</h5>

<button
type="button"
class="btn-close shadow-none"
data-bs-dismiss="modal">
</button>

</div>

<div class="modal-body p-4 text-start">

<?php if(!empty($imagePath)): ?>

<div class="text-center mb-3">

<img
src="<?=htmlspecialchars($imagePath)?>"
alt="Collateral"
class="img-fluid rounded border shadow-sm"
style="max-height:200px;object-fit:contain">

</div>

<?php endif; ?>

<div class="mb-3">

<span class="text-muted small d-block">
Item Name
</span>

<h5 class="fw-bold text-body mb-0">
<?=htmlspecialchars($col['item_name'])?>
</h5>

</div>

<div class="row mb-3">

<div class="col-6">

<span class="text-muted small d-block">
Estimated Value
</span>

<span class="fw-bold text-success fs-5">
₱<?=number_format((float)$col['estimated_value'],2)?>
</span>

</div>

<div class="col-6">

<span class="text-muted small d-block">
Loan Principal
</span>

<span class="fw-semibold text-body fs-5">
₱<?=number_format((float)$col['principal_amount'],2)?>
</span>

</div>

</div>

<div class="mb-3">

<span class="text-muted small d-block">
Description / Specs
</span>

<div class="p-3 bg-body-tertiary rounded-3 border small text-body">

<?=nl2br(
htmlspecialchars(
$col['description']??'No description provided.'
)
)?>

</div>

</div>

<div class="p-3 bg-primary bg-opacity-10 rounded-3 border border-primary border-opacity-25">

<div class="small fw-bold text-primary mb-1">
Associated Borrower & Loan Info
</div>

<div class="small text-body mb-1">

<strong>Borrower:</strong>
<?=htmlspecialchars($col['borrower_name'])?>

</div>

<div class="small text-body mb-1">

<strong>Funding Source:</strong>
<?=htmlspecialchars($col['account_name'])?>

</div>

<div class="small text-body">

<strong>Due Date:</strong>

<?=!empty($col['due_date'])
?date('M d, Y',strtotime($col['due_date']))
:'N/A'?>

</div>

</div>

</div>

<div class="modal-footer border-top px-4 py-3">

<button
type="button"
class="btn btn-secondary px-4 fw-semibold"
data-bs-dismiss="modal">

Close

</button>

</div>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>