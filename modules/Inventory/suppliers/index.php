<?php

$pdo = Database::getConnection();

$businessId = $_SESSION['business_id'] ?? null;
$userId = $_SESSION['user_id'] ?? null;

if (!$businessId || !$userId) {
    header('Location: index.php?page=login');
    exit;
}

$error = '';
$success = '';

/* =========================================================
   CREATE SUPPLIER
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'create_supplier'
) {

    $name = trim($_POST['name'] ?? '');
    $contactPerson = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($name === '') {

        $error = 'Supplier name is required.';

    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } else {

        try {

            $pdo->beginTransaction();

            /* =====================================================
               CHECK DUPLICATE SUPPLIER
            ===================================================== */

            $stmt = $pdo->prepare("
                SELECT id
                FROM inventory_suppliers
                WHERE business_id = ?
                AND LOWER(name) = LOWER(?)
                LIMIT 1
            ");

            $stmt->execute([
                $businessId,
                $name
            ]);

            if ($stmt->fetch()) {
                throw new Exception('A supplier with this name already exists.');
            }

            /* =====================================================
               INSERT SUPPLIER
            ===================================================== */

            $stmt = $pdo->prepare("
                INSERT INTO inventory_suppliers (
                    business_id,
                    name,
                    contact_person,
                    phone,
                    email,
                    address,
                    notes,
                    status,
                    created_by
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, 'active', ?
                )
            ");

            $stmt->execute([
                $businessId,
                $name,
                $contactPerson !== '' ? $contactPerson : null,
                $phone !== '' ? $phone : null,
                $email !== '' ? $email : null,
                $address !== '' ? $address : null,
                $notes !== '' ? $notes : null,
                $userId
            ]);

            $pdo->commit();

            $success = 'Supplier created successfully.';

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e->getMessage();
        }
    }
}


/* =========================================================
   UPDATE SUPPLIER
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'update_supplier'
) {

    $supplierId = (int)($_POST['supplier_id'] ?? 0);

    $name = trim($_POST['name'] ?? '');
    $contactPerson = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($supplierId <= 0) {

        $error = 'Invalid supplier.';

    } elseif ($name === '') {

        $error = 'Supplier name is required.';

    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } else {

        try {

            $pdo->beginTransaction();

            /* =====================================================
               CHECK SUPPLIER EXISTS
            ===================================================== */

            $stmt = $pdo->prepare("
                SELECT id
                FROM inventory_suppliers
                WHERE id = ?
                AND business_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $supplierId,
                $businessId
            ]);

            if (!$stmt->fetch()) {
                throw new Exception('Supplier not found.');
            }

            /* =====================================================
               CHECK DUPLICATE NAME
            ===================================================== */

            $stmt = $pdo->prepare("
                SELECT id
                FROM inventory_suppliers
                WHERE business_id = ?
                AND LOWER(name) = LOWER(?)
                AND id != ?
                LIMIT 1
            ");

            $stmt->execute([
                $businessId,
                $name,
                $supplierId
            ]);

            if ($stmt->fetch()) {
                throw new Exception('Another supplier with this name already exists.');
            }

            /* =====================================================
               UPDATE
            ===================================================== */

            $stmt = $pdo->prepare("
                UPDATE inventory_suppliers
                SET
                    name = ?,
                    contact_person = ?,
                    phone = ?,
                    email = ?,
                    address = ?,
                    notes = ?,
                    updated_at = NOW()
                WHERE id = ?
                AND business_id = ?
            ");

            $stmt->execute([
                $name,
                $contactPerson !== '' ? $contactPerson : null,
                $phone !== '' ? $phone : null,
                $email !== '' ? $email : null,
                $address !== '' ? $address : null,
                $notes !== '' ? $notes : null,
                $supplierId,
                $businessId
            ]);

            $pdo->commit();

            $success = 'Supplier updated successfully.';

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e->getMessage();
        }
    }
}


/* =========================================================
   TOGGLE SUPPLIER STATUS
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'toggle_supplier'
) {

    $supplierId = (int)($_POST['supplier_id'] ?? 0);

    try {

        if ($supplierId <= 0) {
            throw new Exception('Invalid supplier.');
        }

        $stmt = $pdo->prepare("
            SELECT status
            FROM inventory_suppliers
            WHERE id = ?
            AND business_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $supplierId,
            $businessId
        ]);

        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$supplier) {
            throw new Exception('Supplier not found.');
        }

        $newStatus =
            $supplier['status'] === 'active'
                ? 'inactive'
                : 'active';

        $stmt = $pdo->prepare("
            UPDATE inventory_suppliers
            SET
                status = ?,
                updated_at = NOW()
            WHERE id = ?
            AND business_id = ?
        ");

        $stmt->execute([
            $newStatus,
            $supplierId,
            $businessId
        ]);

        $success =
            $newStatus === 'active'
                ? 'Supplier enabled successfully.'
                : 'Supplier disabled successfully.';

    } catch (Throwable $e) {

        $error = $e->getMessage();
    }
}


/* =========================================================
   DELETE SUPPLIER
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'delete_supplier'
) {

    $supplierId = (int)($_POST['supplier_id'] ?? 0);

    try {

        if ($supplierId <= 0) {
            throw new Exception('Invalid supplier.');
        }

        /* =====================================================
           CHECK SUPPLIER EXISTS
        ===================================================== */

        $stmt = $pdo->prepare("
            SELECT id
            FROM inventory_suppliers
            WHERE id = ?
            AND business_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $supplierId,
            $businessId
        ]);

        if (!$stmt->fetch()) {
            throw new Exception('Supplier not found.');
        }

        /* =====================================================
           CHECK IF USED BY PRODUCTS
        ===================================================== */

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM inventory_products
            WHERE supplier_id = ?
            AND business_id = ?
        ");

        $stmt->execute([
            $supplierId,
            $businessId
        ]);

        $productCount = (int)$stmt->fetchColumn();

        if ($productCount > 0) {

            throw new Exception(
                'This supplier cannot be deleted because it is assigned to ' .
                $productCount .
                ' product' .
                ($productCount === 1 ? '' : 's') .
                '. Disable it instead.'
            );
        }

        /* =====================================================
           DELETE
        ===================================================== */

        $stmt = $pdo->prepare("
            DELETE FROM inventory_suppliers
            WHERE id = ?
            AND business_id = ?
        ");

        $stmt->execute([
            $supplierId,
            $businessId
        ]);

        $success = 'Supplier deleted successfully.';

    } catch (Throwable $e) {

        $error = $e->getMessage();
    }
}


/* =========================================================
   LOAD SUPPLIERS
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.business_id,
        s.name,
        s.contact_person,
        s.phone,
        s.email,
        s.address,
        s.notes,
        s.status,
        s.created_by,
        s.created_at,
        s.updated_at,

        COUNT(p.id) AS product_count

    FROM inventory_suppliers s

    LEFT JOIN inventory_products p
        ON p.supplier_id = s.id
        AND p.business_id = s.business_id

    WHERE s.business_id = ?

    GROUP BY
        s.id,
        s.business_id,
        s.name,
        s.contact_person,
        s.phone,
        s.email,
        s.address,
        s.notes,
        s.status,
        s.created_by,
        s.created_at,
        s.updated_at

    ORDER BY s.id DESC
");

$stmt->execute([$businessId]);

$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   SUPPLIER SUMMARY
========================================================= */

$totalSuppliers = count($suppliers);
$activeSuppliers = 0;
$inactiveSuppliers = 0;

foreach ($suppliers as $supplier) {

    if ($supplier['status'] === 'active') {
        $activeSuppliers++;
    } else {
        $inactiveSuppliers++;
    }
}


$activePage = 'inventory_suppliers';
$pageTitle = 'Suppliers - Inventory';
$businessName = $_SESSION['business_name'] ?? 'Business';

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

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

    const theme =
        localStorage.getItem('bs-theme') || 'light';

    document.documentElement.setAttribute(
        'data-bs-theme',
        theme
    );

})();

</script>

<style>

body {
    min-height: 100vh;
    overflow-x: hidden;
}

.inventory-main {
    min-width: 0;
    width: 100%;
}


/* =========================================================
   HEADER
========================================================= */

.inventory-header {
    padding-bottom: 4px;
}

.inventory-header h2 {
    font-size: 1.5rem;
}


/* =========================================================
   SUMMARY
========================================================= */

.summary-card {
    border: 0;
    border-radius: 16px;
    transition:
        transform .15s ease,
        box-shadow .15s ease;
}

.summary-card:hover {
    transform: translateY(-2px);
}

.summary-icon {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 13px;
}


/* =========================================================
   SUPPLIER SECTION
========================================================= */

.supplier-section {
    border: 0;
    border-radius: 16px;
    overflow: hidden;
}

.supplier-section-header {
    padding: 20px 24px;
}


/* =========================================================
   SEARCH
========================================================= */

.search-wrapper {
    border: 1px solid var(--bs-border-color);
    border-radius: 10px;
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

.filter-select {
    border-radius: 10px;
}


/* =========================================================
   TABLE
========================================================= */

.supplier-table {
    width: 100%;
}

.supplier-table th {
    font-size: .72rem;
    letter-spacing: .04em;
    white-space: nowrap;
}

.supplier-table td {
    vertical-align: middle;
    white-space: nowrap;
    padding-top: 13px;
    padding-bottom: 13px;
}

.supplier-name {
    font-weight: 700;
}

.supplier-subtext {
    font-size: .7rem;
    color: var(--bs-secondary-color);
    margin-top: 2px;
}


/* =========================================================
   STATUS
========================================================= */

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: .35rem .65rem;
    border-radius: 50rem;
    font-size: .7rem;
    font-weight: 700;
}

.status-active {
    background: rgba(25, 135, 84, .12);
    color: var(--bs-success);
}

.status-inactive {
    background: rgba(220, 53, 69, .12);
    color: var(--bs-danger);
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state {
    padding: 55px 20px;
}


/* =========================================================
   MODAL
========================================================= */

.supplier-modal .modal-content {
    border: 0;
    border-radius: 16px;
}

.supplier-modal .modal-header {
    padding: 18px 22px;
}

.supplier-modal .modal-body {
    padding: 22px;
}

.supplier-modal .modal-footer {
    padding: 15px 22px;
}


/* =========================================================
   FORM
========================================================= */

.form-label {
    font-size: .78rem;
}

.form-control,
.form-select {
    border-radius: 9px;
}

.form-control:focus,
.form-select:focus {
    box-shadow:
        0 0 0 .2rem
        rgba(var(--bs-primary-rgb), .12);
}


/* =========================================================
   DETAIL BOX
========================================================= */

.detail-box {
    border: 1px solid var(--bs-border-color);
    border-radius: 12px;
    padding: 14px;
    height: 100%;
}

.detail-label {
    font-size: .7rem;
    color: var(--bs-secondary-color);
    margin-bottom: 4px;
}

.detail-value {
    font-weight: 600;
    word-break: break-word;
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 575.98px) {

    .inventory-main > .p-3,
    .inventory-main > .p-md-4 {
        padding: 14px !important;
    }

    .inventory-header {
        margin-bottom: 16px !important;
    }

    .inventory-header h2 {
        font-size: 1.35rem;
    }

    .inventory-header p {
        font-size: .75rem;
        line-height: 1.4;
    }

    .supplier-section {
        border-radius: 14px;
    }

    .supplier-section-header {
        padding: 15px;
    }

    .supplier-table th,
    .supplier-table td {
        font-size: .75rem;
    }

    .empty-state {
        padding: 40px 15px;
    }

    .supplier-modal .modal-dialog {
        margin: 10px;
    }

    .supplier-modal .modal-content {
        border-radius: 15px;
    }

    .supplier-modal .modal-header {
        padding: 15px 16px;
    }

    .supplier-modal .modal-body {
        padding: 16px;
    }

    .supplier-modal .modal-footer {
        padding: 12px 16px;
    }

}

</style>

</head>

<body class="bg-body-tertiary">

<div class="d-flex flex-column flex-lg-row min-vh-100">

    <?php

    $sidebarPath =
        __DIR__ .
        '/../../../resources/partials/InventorySidebar.php';

    if (file_exists($sidebarPath)) {
        include $sidebarPath;
    }

    ?>


    <main class="inventory-main flex-grow-1 bg-body-tertiary">

        <div class="p-3 p-md-4">


            <!-- =================================================
                 HEADER
            ================================================== -->

            <div class="inventory-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

                <div>

                    <div class="d-flex align-items-center gap-2 mb-1">

                        <div class="d-lg-none bg-primary bg-opacity-10 text-primary rounded-3 p-2">

                            <i class="bi bi-truck"></i>

                        </div>

                        <h2 class="fw-bold text-body mb-0">
                            Suppliers
                        </h2>

                    </div>

                    <p class="text-muted small mb-0">

                        Manage your product suppliers for

                        <span class="fw-semibold text-primary">
                            <?= htmlspecialchars($businessName) ?>
                        </span>

                    </p>

                </div>


                <button
                    type="button"
                    class="btn btn-primary fw-bold px-3 py-2 rounded-3 shadow-sm d-flex align-items-center gap-2"
                    onclick="openSupplierModal()"
                >

                    <i class="bi bi-plus-lg"></i>

                    <span>
                        Add Supplier
                    </span>

                </button>

            </div>


            <!-- =================================================
                 SUCCESS
            ================================================== -->

            <?php if (!empty($success)): ?>

                <div
                    class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 small"
                    role="alert"
                >

                    <i class="bi bi-check-circle-fill me-2"></i>

                    <?= htmlspecialchars($success) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 ERROR
            ================================================== -->

            <?php if (!empty($error)): ?>

                <div
                    class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 small"
                    role="alert"
                >

                    <i class="bi bi-exclamation-triangle-fill me-2"></i>

                    <?= htmlspecialchars($error) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 SUMMARY
            ================================================== -->

            <div class="row g-3 mb-4">


                <!-- TOTAL -->

                <div class="col-12 col-md-4">

                    <div class="card summary-card shadow-sm h-100 bg-body">

                        <div class="card-body p-4">

                            <div class="d-flex justify-content-between align-items-start">

                                <div>

                                    <div class="text-muted small fw-semibold mb-2">
                                        Total Suppliers
                                    </div>

                                    <div class="fs-3 fw-bold text-body">
                                        <?= number_format($totalSuppliers) ?>
                                    </div>

                                    <div class="small text-muted mt-1">
                                        All registered suppliers
                                    </div>

                                </div>

                                <div class="summary-icon bg-primary bg-opacity-10 text-primary">

                                    <i class="bi bi-truck fs-4"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- ACTIVE -->

                <div class="col-12 col-md-4">

                    <div class="card summary-card shadow-sm h-100 bg-body">

                        <div class="card-body p-4">

                            <div class="d-flex justify-content-between align-items-start">

                                <div>

                                    <div class="text-muted small fw-semibold mb-2">
                                        Active Suppliers
                                    </div>

                                    <div class="fs-3 fw-bold text-success">
                                        <?= number_format($activeSuppliers) ?>
                                    </div>

                                    <div class="small text-muted mt-1">
                                        Currently available
                                    </div>

                                </div>

                                <div class="summary-icon bg-success bg-opacity-10 text-success">

                                    <i class="bi bi-check-circle fs-4"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- INACTIVE -->

                <div class="col-12 col-md-4">

                    <div class="card summary-card shadow-sm h-100 bg-body">

                        <div class="card-body p-4">

                            <div class="d-flex justify-content-between align-items-start">

                                <div>

                                    <div class="text-muted small fw-semibold mb-2">
                                        Inactive Suppliers
                                    </div>

                                    <div class="fs-3 fw-bold text-danger">
                                        <?= number_format($inactiveSuppliers) ?>
                                    </div>

                                    <div class="small text-muted mt-1">
                                        Currently disabled
                                    </div>

                                </div>

                                <div class="summary-icon bg-danger bg-opacity-10 text-danger">

                                    <i class="bi bi-x-circle fs-4"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 SEARCH AND FILTER
            ================================================== -->

            <div class="card border-0 shadow-sm rounded-4 mb-4">

                <div class="card-body p-3">

                    <div class="row g-2">


                        <!-- SEARCH -->

                        <div class="col-12 col-lg-8">

                            <div class="search-wrapper input-group">

                                <span class="input-group-text text-muted">

                                    <i class="bi bi-search"></i>

                                </span>

                                <input
                                    type="text"
                                    id="supplierSearch"
                                    class="form-control"
                                    placeholder="Search supplier, contact person, phone, email..."
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


                        <!-- STATUS -->

                        <div class="col-12 col-lg-4">

                            <select
                                id="statusFilter"
                                class="form-select filter-select"
                            >

                                <option value="all">
                                    All Status
                                </option>

                                <option value="active">
                                    Active
                                </option>

                                <option value="inactive">
                                    Inactive
                                </option>

                            </select>

                        </div>

                    </div>


                    <div class="d-flex justify-content-between align-items-center mt-3">

                        <div
                            id="filterResultText"
                            class="small text-muted"
                        >

                            Showing <?= number_format($totalSuppliers) ?>
                            suppliers

                        </div>


                        <button
                            type="button"
                            id="clearFilters"
                            class="btn btn-sm btn-outline-secondary rounded-3"
                        >

                            <i class="bi bi-arrow-counterclockwise me-1"></i>

                            Clear Filters

                        </button>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 SUPPLIER TABLE
            ================================================== -->

            <div class="card supplier-section shadow-sm bg-body">


                <div class="supplier-section-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">

                    <div>

                        <h5 class="fw-bold mb-1">
                            Supplier List
                        </h5>

                        <p class="text-muted small mb-0">

                            <?= number_format($totalSuppliers) ?>

                            supplier<?= $totalSuppliers === 1 ? '' : 's' ?>

                            registered

                        </p>

                    </div>

                </div>


                <?php if (!$suppliers): ?>

                    <div class="empty-state text-center text-muted">

                        <div class="mb-3">

                            <i class="bi bi-truck display-6 opacity-50"></i>

                        </div>

                        <div class="fw-semibold mb-1">
                            No suppliers found
                        </div>

                        <div class="small mb-3">
                            Add your first supplier to start managing suppliers.
                        </div>

                        <button
                            type="button"
                            class="btn btn-primary btn-sm fw-bold"
                            onclick="openSupplierModal()"
                        >

                            <i class="bi bi-plus-lg me-1"></i>

                            Add Supplier

                        </button>

                    </div>

                <?php else: ?>


                    <div class="table-responsive">

                        <table class="supplier-table table table-hover align-middle mb-0">

                            <thead class="table-light text-uppercase text-muted">

                                <tr>

                                    <th class="ps-4 py-3">
                                        ID
                                    </th>

                                    <th class="py-3">
                                        Supplier
                                    </th>

                                    <th class="py-3">
                                        Contact Person
                                    </th>

                                    <th class="py-3">
                                        Phone
                                    </th>

                                    <th class="py-3">
                                        Email
                                    </th>

                                    <th class="py-3">
                                        Products
                                    </th>

                                    <th class="py-3">
                                        Status
                                    </th>

                                    <th class="py-3 pe-4 text-end">
                                        Action
                                    </th>

                                </tr>

                            </thead>


                            <tbody id="supplierTableBody">


                            <?php foreach ($suppliers as $supplier): ?>

                                <?php

                                $searchText = strtolower(
                                    ($supplier['name'] ?? '') . ' ' .
                                    ($supplier['contact_person'] ?? '') . ' ' .
                                    ($supplier['phone'] ?? '') . ' ' .
                                    ($supplier['email'] ?? '') . ' ' .
                                    ($supplier['address'] ?? '')
                                );

                                ?>

                                <tr
                                    class="supplier-row"
                                    data-search="<?= htmlspecialchars($searchText) ?>"
                                    data-status="<?= htmlspecialchars($supplier['status']) ?>"
                                >


                                    <!-- ID -->

                                    <td class="ps-4">

                                        <span class="text-muted small">

                                            #<?= (int)$supplier['id'] ?>

                                        </span>

                                    </td>


                                    <!-- SUPPLIER -->

                                    <td>

                                        <div class="supplier-name text-body">

                                            <?= htmlspecialchars(
                                                $supplier['name']
                                            ) ?>

                                        </div>

                                        <?php if (!empty($supplier['address'])): ?>

                                            <div class="supplier-subtext">

                                                <i class="bi bi-geo-alt me-1"></i>

                                                <?= htmlspecialchars(
                                                    $supplier['address']
                                                ) ?>

                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <!-- CONTACT PERSON -->

                                    <td>

                                        <span class="small">

                                            <?= htmlspecialchars(
                                                $supplier['contact_person'] ?? '-'
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- PHONE -->

                                    <td>

                                        <span class="small">

                                            <?= htmlspecialchars(
                                                $supplier['phone'] ?? '-'
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- EMAIL -->

                                    <td>

                                        <?php if (!empty($supplier['email'])): ?>

                                            <a
                                                href="mailto:<?= htmlspecialchars($supplier['email']) ?>"
                                                class="small text-decoration-none"
                                            >

                                                <?= htmlspecialchars(
                                                    $supplier['email']
                                                ) ?>

                                            </a>

                                        <?php else: ?>

                                            <span class="text-muted small">
                                                -
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- PRODUCTS -->

                                    <td>

                                        <span class="badge text-bg-light border">

                                            <?= number_format(
                                                (int)$supplier['product_count']
                                            ) ?>

                                            product<?= (int)$supplier['product_count'] === 1 ? '' : 's' ?>

                                        </span>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <?php if ($supplier['status'] === 'active'): ?>

                                            <span class="status-badge status-active">

                                                <i class="bi bi-check-circle me-1"></i>

                                                Active

                                            </span>

                                        <?php else: ?>

                                            <span class="status-badge status-inactive">

                                                <i class="bi bi-x-circle me-1"></i>

                                                Inactive

                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- ACTION -->

                                    <td class="text-end pe-4">

                                        <div class="dropdown">

                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-secondary rounded-3"
                                                data-bs-toggle="dropdown"
                                                aria-expanded="false"
                                            >

                                                <i class="bi bi-three-dots-vertical"></i>

                                            </button>


                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">


                                                <!-- VIEW -->

                                                <li>

                                                    <button
                                                        type="button"
                                                        class="dropdown-item"
                                                        onclick='viewSupplier(
                                                            <?= json_encode($supplier, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
                                                        )'
                                                    >

                                                        <i class="bi bi-eye me-2"></i>

                                                        View Details

                                                    </button>

                                                </li>


                                                <!-- EDIT -->

                                                <li>

                                                    <button
                                                        type="button"
                                                        class="dropdown-item"
                                                        onclick='editSupplier(
                                                            <?= json_encode($supplier, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
                                                        )'
                                                    >

                                                        <i class="bi bi-pencil-square me-2"></i>

                                                        Edit Supplier

                                                    </button>

                                                </li>


                                                <li>
                                                    <hr class="dropdown-divider">
                                                </li>


                                                <!-- ENABLE / DISABLE -->

                                                <li>

                                                    <form
                                                        method="POST"
                                                        onsubmit="return confirmToggleSupplier(
                                                            '<?= htmlspecialchars(
                                                                addslashes($supplier['name'])
                                                            ) ?>',
                                                            '<?= htmlspecialchars($supplier['status']) ?>'
                                                        )"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="toggle_supplier"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="supplier_id"
                                                            value="<?= (int)$supplier['id'] ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="dropdown-item <?= $supplier['status'] === 'active'
                                                                ? 'text-warning'
                                                                : 'text-success'
                                                            ?>"
                                                        >

                                                            <?php if ($supplier['status'] === 'active'): ?>

                                                                <i class="bi bi-pause-circle me-2"></i>

                                                                Disable Supplier

                                                            <?php else: ?>

                                                                <i class="bi bi-check-circle me-2"></i>

                                                                Enable Supplier

                                                            <?php endif; ?>

                                                        </button>

                                                    </form>

                                                </li>


                                                <!-- DELETE -->

                                                <li>

                                                    <form
                                                        method="POST"
                                                        onsubmit="return confirmDeleteSupplier(
                                                            '<?= htmlspecialchars(
                                                                addslashes($supplier['name'])
                                                            ) ?>'
                                                        )"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="delete_supplier"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="supplier_id"
                                                            value="<?= (int)$supplier['id'] ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="dropdown-item text-danger"
                                                        >

                                                            <i class="bi bi-trash me-2"></i>

                                                            Delete Supplier

                                                        </button>

                                                    </form>

                                                </li>

                                            </ul>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>


                            </tbody>

                        </table>

                    </div>


                    <!-- NO SEARCH RESULTS -->

                    <div
                        id="noSearchResults"
                        class="empty-state text-center text-muted d-none"
                    >

                        <div class="mb-3">

                            <i class="bi bi-search display-6 opacity-50"></i>

                        </div>

                        <div class="fw-semibold mb-1">
                            No suppliers found
                        </div>

                        <div class="small">
                            Try changing your search or filter options.
                        </div>

                    </div>

                <?php endif; ?>


            </div>

        </div>

    </main>

</div>


<!-- =========================================================
     ADD / EDIT SUPPLIER MODAL
========================================================= -->

<div
    class="modal fade supplier-modal"
    id="supplierModal"
    tabindex="-1"
    aria-labelledby="supplierModalLabel"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content shadow-lg">


            <div class="modal-header border-bottom">

                <h5
                    class="modal-title fw-bold"
                    id="supplierModalLabel"
                >

                    <i
                        id="supplierModalIcon"
                        class="bi bi-truck text-primary me-2"
                    ></i>

                    <span id="supplierModalTitle">
                        Add New Supplier
                    </span>

                </h5>


                <button
                    type="button"
                    class="btn-close shadow-none"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>

            </div>


            <form method="POST" id="supplierForm">

                <input
                    type="hidden"
                    name="action"
                    id="supplierFormAction"
                    value="create_supplier"
                >

                <input
                    type="hidden"
                    name="supplier_id"
                    id="supplierId"
                    value=""
                >


                <div class="modal-body">

                    <div class="row g-3">


                        <!-- SUPPLIER NAME -->

                        <div class="col-12">

                            <label class="form-label fw-semibold">

                                Supplier Name

                                <span class="text-danger">*</span>

                            </label>

                            <input
                                type="text"
                                name="name"
                                id="supplierName"
                                class="form-control"
                                required
                                placeholder="Enter supplier name"
                            >

                        </div>


                        <!-- CONTACT PERSON -->

                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Contact Person
                            </label>

                            <input
                                type="text"
                                name="contact_person"
                                id="supplierContactPerson"
                                class="form-control"
                                placeholder="Contact person"
                            >

                        </div>


                        <!-- PHONE -->

                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Phone
                            </label>

                            <input
                                type="text"
                                name="phone"
                                id="supplierPhone"
                                class="form-control"
                                placeholder="Phone number"
                            >

                        </div>


                        <!-- EMAIL -->

                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Email
                            </label>

                            <input
                                type="email"
                                name="email"
                                id="supplierEmail"
                                class="form-control"
                                placeholder="supplier@example.com"
                            >

                        </div>


                        <!-- ADDRESS -->

                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Address
                            </label>

                            <input
                                type="text"
                                name="address"
                                id="supplierAddress"
                                class="form-control"
                                placeholder="Supplier address"
                            >

                        </div>


                        <!-- NOTES -->

                        <div class="col-12">

                            <label class="form-label fw-semibold">
                                Notes
                            </label>

                            <textarea
                                name="notes"
                                id="supplierNotes"
                                class="form-control"
                                rows="4"
                                placeholder="Optional notes about this supplier"
                            ></textarea>

                        </div>

                    </div>

                </div>


                <div class="modal-footer border-top">

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
                        id="supplierSubmitButton"
                    >

                        <i class="bi bi-check-lg me-1"></i>

                        Save Supplier

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- =========================================================
     SUPPLIER DETAILS MODAL
========================================================= -->

<div
    class="modal fade supplier-modal"
    id="supplierDetailsModal"
    tabindex="-1"
    aria-labelledby="supplierDetailsModalLabel"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content shadow-lg">


            <div class="modal-header border-bottom">

                <h5
                    class="modal-title fw-bold"
                    id="supplierDetailsModalLabel"
                >

                    <i class="bi bi-truck text-primary me-2"></i>

                    Supplier Details

                </h5>


                <button
                    type="button"
                    class="btn-close shadow-none"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <div class="modal-body">

                <div class="row g-3">


                    <!-- NAME -->

                    <div class="col-12">

                        <div class="detail-box">

                            <div class="detail-label">
                                Supplier Name
                            </div>

                            <div
                                class="detail-value fs-5"
                                id="detailSupplierName"
                            >
                            </div>

                        </div>

                    </div>


                    <!-- CONTACT -->

                    <div class="col-md-6">

                        <div class="detail-box">

                            <div class="detail-label">
                                Contact Person
                            </div>

                            <div
                                class="detail-value"
                                id="detailContactPerson"
                            >
                            </div>

                        </div>

                    </div>


                    <!-- PHONE -->

                    <div class="col-md-6">

                        <div class="detail-box">

                            <div class="detail-label">
                                Phone
                            </div>

                            <div
                                class="detail-value"
                                id="detailPhone"
                            >
                            </div>

                        </div>

                    </div>


                    <!-- EMAIL -->

                    <div class="col-md-6">

                        <div class="detail-box">

                            <div class="detail-label">
                                Email
                            </div>

                            <div
                                class="detail-value"
                                id="detailEmail"
                            >
                            </div>

                        </div>

                    </div>


                    <!-- PRODUCTS -->

                    <div class="col-md-6">

                        <div class="detail-box">

                            <div class="detail-label">
                                Assigned Products
                            </div>

                            <div
                                class="detail-value"
                                id="detailProductCount"
                            >
                            </div>

                        </div>

                    </div>


                    <!-- STATUS -->

                    <div class="col-md-6">

                        <div class="detail-box">

                            <div class="detail-label">
                                Status
                            </div>

                            <div
                                class="detail-value"
                                id="detailStatus"
                            >
                            </div>

                        </div>

                    </div>


                    <!-- CREATED -->

                    <div class="col-md-6">

                        <div class="detail-box">

                            <div class="detail-label">
                                Created
                            </div>

                            <div
                                class="detail-value"
                                id="detailCreatedAt"
                            >
                            </div>

                        </div>

                    </div>


                    <!-- ADDRESS -->

                    <div class="col-12">

                        <div class="detail-box">

                            <div class="detail-label">
                                Address
                            </div>

                            <div
                                class="detail-value"
                                id="detailAddress"
                            >
                            </div>

                        </div>

                    </div>


                    <!-- NOTES -->

                    <div class="col-12">

                        <div class="detail-box">

                            <div class="detail-label">
                                Notes
                            </div>

                            <div
                                class="detail-value"
                                id="detailNotes"
                            >
                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <div class="modal-footer border-top">

                <button
                    type="button"
                    class="btn btn-light fw-semibold"
                    data-bs-dismiss="modal"
                >

                    Close

                </button>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>

/* =========================================================
   SEARCH + FILTER
========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const searchInput =
            document.getElementById('supplierSearch');

        const clearButton =
            document.getElementById('clearSearch');

        const statusFilter =
            document.getElementById('statusFilter');

        const clearFilters =
            document.getElementById('clearFilters');

        const rows =
            document.querySelectorAll('.supplier-row');

        const noResults =
            document.getElementById('noSearchResults');

        const resultText =
            document.getElementById('filterResultText');


        function applyFilters() {

            const searchTerm =
                (searchInput?.value || '')
                    .toLowerCase()
                    .trim();

            const selectedStatus =
                statusFilter?.value || 'all';

            let visibleCount = 0;


            rows.forEach(function (row) {

                const searchData =
                    row.getAttribute('data-search') ||
                    row.textContent.toLowerCase();

                const rowStatus =
                    row.getAttribute('data-status') || '';


                const matchesSearch =
                    searchTerm === '' ||
                    searchData.includes(searchTerm);

                const matchesStatus =
                    selectedStatus === 'all' ||
                    rowStatus === selectedStatus;


                const matches =
                    matchesSearch &&
                    matchesStatus;


                if (matches) {

                    row.style.display = '';

                    visibleCount++;

                } else {

                    row.style.display = 'none';

                }

            });


            if (clearButton) {

                if (searchTerm !== '') {

                    clearButton.classList.remove('d-none');

                } else {

                    clearButton.classList.add('d-none');

                }

            }


            if (resultText) {

                resultText.textContent =
                    'Showing ' +
                    visibleCount.toLocaleString() +
                    ' supplier' +
                    (visibleCount === 1 ? '' : 's');

            }


            if (noResults) {

                if (
                    visibleCount === 0 &&
                    rows.length > 0
                ) {

                    noResults.classList.remove(
                        'd-none'
                    );

                } else {

                    noResults.classList.add(
                        'd-none'
                    );

                }

            }

        }


        /* =====================================================
           SEARCH
        ===================================================== */

        if (searchInput) {

            searchInput.addEventListener(
                'input',
                applyFilters
            );

        }


        /* =====================================================
           STATUS
        ===================================================== */

        if (statusFilter) {

            statusFilter.addEventListener(
                'change',
                applyFilters
            );

        }


        /* =====================================================
           CLEAR SEARCH
        ===================================================== */

        if (clearButton) {

            clearButton.addEventListener(
                'click',
                function () {

                    if (searchInput) {
                        searchInput.value = '';
                    }

                    applyFilters();

                    if (searchInput) {
                        searchInput.focus();
                    }

                }
            );

        }


        /* =====================================================
           CLEAR FILTERS
        ===================================================== */

        if (clearFilters) {

            clearFilters.addEventListener(
                'click',
                function () {

                    if (searchInput) {
                        searchInput.value = '';
                    }

                    if (statusFilter) {
                        statusFilter.value = 'all';
                    }

                    applyFilters();

                }
            );

        }


        applyFilters();

    }
);


/* =========================================================
   ADD SUPPLIER
========================================================= */

function openSupplierModal() {

    const modalElement =
        document.getElementById('supplierModal');

    if (!modalElement) {
        return;
    }


    const form =
        document.getElementById('supplierForm');

    if (form) {
        form.reset();
    }


    document.getElementById(
        'supplierFormAction'
    ).value = 'create_supplier';

    document.getElementById(
        'supplierId'
    ).value = '';

    document.getElementById(
        'supplierModalTitle'
    ).textContent = 'Add New Supplier';

    document.getElementById(
        'supplierSubmitButton'
    ).innerHTML =
        '<i class="bi bi-check-lg me-1"></i> Save Supplier';


    const modal =
        bootstrap.Modal.getOrCreateInstance(
            modalElement
        );

    modal.show();

}


/* =========================================================
   EDIT SUPPLIER
========================================================= */

function editSupplier(supplier) {

    const modalElement =
        document.getElementById('supplierModal');

    if (!modalElement) {
        return;
    }


    document.getElementById(
        'supplierFormAction'
    ).value = 'update_supplier';

    document.getElementById(
        'supplierId'
    ).value = supplier.id || '';

    document.getElementById(
        'supplierName'
    ).value = supplier.name || '';

    document.getElementById(
        'supplierContactPerson'
    ).value = supplier.contact_person || '';

    document.getElementById(
        'supplierPhone'
    ).value = supplier.phone || '';

    document.getElementById(
        'supplierEmail'
    ).value = supplier.email || '';

    document.getElementById(
        'supplierAddress'
    ).value = supplier.address || '';

    document.getElementById(
        'supplierNotes'
    ).value = supplier.notes || '';


    document.getElementById(
        'supplierModalTitle'
    ).textContent = 'Edit Supplier';

    document.getElementById(
        'supplierSubmitButton'
    ).innerHTML =
        '<i class="bi bi-save me-1"></i> Update Supplier';


    const modal =
        bootstrap.Modal.getOrCreateInstance(
            modalElement
        );

    modal.show();

}


/* =========================================================
   VIEW SUPPLIER
========================================================= */

function viewSupplier(supplier) {

    document.getElementById(
        'detailSupplierName'
    ).textContent =
        supplier.name || '-';


    document.getElementById(
        'detailContactPerson'
    ).textContent =
        supplier.contact_person || '-';


    document.getElementById(
        'detailPhone'
    ).textContent =
        supplier.phone || '-';


    document.getElementById(
        'detailEmail'
    ).textContent =
        supplier.email || '-';


    document.getElementById(
        'detailProductCount'
    ).textContent =
        (supplier.product_count || 0) +
        ' product' +
        (
            Number(supplier.product_count || 0) === 1
                ? ''
                : 's'
        );


    const statusElement =
        document.getElementById(
            'detailStatus'
        );


    if (supplier.status === 'active') {

        statusElement.innerHTML =
            '<span class="status-badge status-active">' +
            '<i class="bi bi-check-circle me-1"></i>' +
            'Active' +
            '</span>';

    } else {

        statusElement.innerHTML =
            '<span class="status-badge status-inactive">' +
            '<i class="bi bi-x-circle me-1"></i>' +
            'Inactive' +
            '</span>';

    }


    document.getElementById(
        'detailCreatedAt'
    ).textContent =
        supplier.created_at || '-';


    document.getElementById(
        'detailAddress'
    ).textContent =
        supplier.address || '-';


    document.getElementById(
        'detailNotes'
    ).textContent =
        supplier.notes || '-';


    const modalElement =
        document.getElementById(
            'supplierDetailsModal'
        );


    const modal =
        bootstrap.Modal.getOrCreateInstance(
            modalElement
        );

    modal.show();

}


/* =========================================================
   TOGGLE CONFIRMATION
========================================================= */

function confirmToggleSupplier(
    supplierName,
    currentStatus
) {

    if (currentStatus === 'active') {

        return confirm(
            'Are you sure you want to disable "' +
            supplierName +
            '"?'
        );

    }

    return confirm(
        'Are you sure you want to enable "' +
        supplierName +
        '"?'
    );

}


/* =========================================================
   DELETE CONFIRMATION
========================================================= */

function confirmDeleteSupplier(
    supplierName
) {

    return confirm(
        'Are you sure you want to permanently delete "' +
        supplierName +
        '"?\n\n' +
        'This action cannot be undone.'
    );

}

</script>

</body>

</html>