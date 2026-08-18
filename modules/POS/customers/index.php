<?php
$pdo = Database::getConnection();

$userId = $_SESSION['user_id'] ?? null;
$businessId = $_SESSION['business_id'] ?? null;
$businessName = $_SESSION['business_name'] ?? 'Business';

if (!$userId) {
    header('Location: index.php?page=login');
    exit;
}

if (!$businessId) {
    header('Location: index.php?page=select_business');
    exit;
}

$activePage = 'pos_customers';
$error = '';
$success = '';
$search = trim($_GET['search'] ?? '');

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| ADD CUSTOMER
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {

    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($firstName === '') {
        $error = 'First name is required.';
    } elseif ($lastName === '') {
        $error = 'Last name is required.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO pos_customers
                (
                    business_id,
                    first_name,
                    middle_name,
                    last_name,
                    email,
                    phone,
                    address,
                    status,
                    created_by
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'active',
                    ?
                )
            ");

            $stmt->execute([
                $businessId,
                $firstName,
                $middleName !== '' ? $middleName : null,
                $lastName,
                $email !== '' ? $email : null,
                $phone !== '' ? $phone : null,
                $address !== '' ? $address : null,
                $userId
            ]);

            $success = 'Customer added successfully.';

        } catch (Throwable $e) {
            $error = 'Unable to add customer. Please try again.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| UPDATE CUSTOMER
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {

    $customerId = (int)($_POST['customer_id'] ?? 0);

    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($customerId <= 0) {
        $error = 'Invalid customer.';
    } elseif ($firstName === '') {
        $error = 'First name is required.';
    } elseif ($lastName === '') {
        $error = 'Last name is required.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {

        try {

            $stmt = $pdo->prepare("
                UPDATE pos_customers
                SET
                    first_name = ?,
                    middle_name = ?,
                    last_name = ?,
                    email = ?,
                    phone = ?,
                    address = ?
                WHERE id = ?
                AND business_id = ?
                AND created_by = ?
            ");

            $stmt->execute([
                $firstName,
                $middleName !== '' ? $middleName : null,
                $lastName,
                $email !== '' ? $email : null,
                $phone !== '' ? $phone : null,
                $address !== '' ? $address : null,
                $customerId,
                $businessId,
                $userId
            ]);

            if ($stmt->rowCount() > 0) {
                $success = 'Customer updated successfully.';
            } else {
                $error = 'Customer was not found in this business or unauthorized.';
            }

        } catch (Throwable $e) {
            $error = 'Unable to update customer. Please try again.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| TOGGLE CUSTOMER STATUS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {

    $customerId = (int)($_POST['customer_id'] ?? 0);

    if ($customerId > 0) {

        try {

            $stmt = $pdo->prepare("
                SELECT status
                FROM pos_customers
                WHERE id = ?
                AND business_id = ?
                AND created_by = ?
                LIMIT 1
            ");

            $stmt->execute([
                $customerId,
                $businessId,
                $userId
            ]);

            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($customer) {

                $newStatus =
                    $customer['status'] === 'active'
                        ? 'inactive'
                        : 'active';

                $stmt = $pdo->prepare("
                    UPDATE pos_customers
                    SET status = ?
                    WHERE id = ?
                    AND business_id = ?
                    AND created_by = ?
                ");

                $stmt->execute([
                    $newStatus,
                    $customerId,
                    $businessId,
                    $userId
                ]);

                $success =
                    $newStatus === 'active'
                        ? 'Customer activated successfully.'
                        : 'Customer deactivated successfully.';

            } else {
                $error = 'Customer was not found in this business.';
            }

        } catch (Throwable $e) {
            $error = 'Unable to change customer status.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| LOAD CUSTOMERS
|--------------------------------------------------------------------------
*/

$customers = [];

try {

    $sql = "
        SELECT
            id,
            first_name,
            middle_name,
            last_name,
            email,
            phone,
            address,
            status,
            created_at,
            created_by
        FROM pos_customers
        WHERE business_id = ?
        AND created_by = ?
    ";

    $params = [$businessId, $userId];

    if ($search !== '') {

        $sql .= "
            AND (
                first_name LIKE ?
                OR middle_name LIKE ?
                OR last_name LIKE ?
                OR email LIKE ?
                OR phone LIKE ?
            )
        ";

        $searchValue = '%' . $search . '%';

        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
    }

    $sql .= "
        ORDER BY id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $customers = [];
    $error = 'Unable to load customers.';
}

$pageTitle = 'POS Customers';
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= e($pageTitle) ?></title>

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

.pos-main {
    min-width: 0;
    width: 100%;
}

.page-header {
    margin-bottom: 24px;
}

.page-title {
    font-size: 1.5rem;
}

.page-subtitle {
    font-size: .82rem;
}

.pos-card {
    border: 0;
    border-radius: 16px;
}

.pos-card-header {
    padding: 18px 20px;
    border-bottom: 1px solid var(--bs-border-color);
}

.search-box {
    border-radius: 12px;
}

.customer-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;

    background: var(--bs-primary-bg-subtle);
    color: var(--bs-primary);

    font-weight: 700;
    flex-shrink: 0;
}

.customer-name {
    font-weight: 700;
    font-size: .85rem;
}

.customer-info {
    font-size: .72rem;
    color: var(--bs-secondary-color);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;

    padding: .35rem .65rem;

    border-radius: 50rem;

    font-size: .68rem;
    font-weight: 700;
}

.status-active {
    background: rgba(25, 135, 84, .12);
    color: var(--bs-success);
}

.status-inactive {
    background: rgba(108, 117, 125, .12);
    color: var(--bs-secondary);
}

.empty-state {
    padding: 60px 20px;
}

.mobile-customer-card {
    border: 1px solid var(--bs-border-color);
    border-radius: 14px;
    padding: 15px;
    margin-bottom: 12px;
}

.customer-detail {
    font-size: .76rem;
}

.customer-detail i {
    width: 18px;
}

.modal-content {
    border: 0;
    border-radius: 16px;
}

.form-label {
    font-size: .76rem;
    font-weight: 600;
}

@media (max-width: 767.98px) {

    .main-content {
        padding: 14px !important;
    }

    .page-title {
        font-size: 1.3rem;
    }

    .desktop-customers {
        display: none;
    }

    .mobile-customers {
        display: block !important;
    }

    .add-customer-btn {
        width: 100%;
    }
}

@media (min-width: 768px) {

    .mobile-customers {
        display: none !important;
    }
}

</style>

</head>

<body class="bg-body-tertiary">

<div class="d-flex flex-column flex-lg-row min-vh-100">



<?php

$sidebarPath =
    __DIR__ . '/../../../resources/partials/POSSidebar.php';

if (file_exists($sidebarPath)) {
    include $sidebarPath;
}

?>

<main class="pos-main flex-grow-1 bg-body-tertiary">

<div class="main-content p-3 p-md-4">

<!-- HEADER -->

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

    <div>

        <div class="d-flex align-items-center gap-2 mb-1">

            <div class="d-lg-none bg-primary bg-opacity-10 text-primary rounded-3 p-2">
                <i class="bi bi-people"></i>
            </div>

            <h2 class="page-title fw-bold mb-0">
                Customers
            </h2>

        </div>

        <p class="page-subtitle text-muted mb-0">

            Manage POS customers for

            <span class="fw-semibold text-primary">
                <?= e($businessName) ?>
            </span>

        </p>

    </div>

    <button
        type="button"
        class="btn btn-primary fw-bold rounded-3 add-customer-btn"
        data-bs-toggle="modal"
        data-bs-target="#addCustomerModal"
    >

        <i class="bi bi-person-plus me-1"></i>

        Add Customer

    </button>

</div>


<!-- ALERTS -->

<?php if ($success): ?>

<div class="alert alert-success border-0 rounded-3 small">

    <i class="bi bi-check-circle me-1"></i>

    <?= e($success) ?>

</div>

<?php endif; ?>


<?php if ($error): ?>

<div class="alert alert-danger border-0 rounded-3 small">

    <i class="bi bi-exclamation-circle me-1"></i>

    <?= e($error) ?>

</div>

<?php endif; ?>


<!-- SEARCH -->

<div class="card pos-card shadow-sm bg-body mb-4">

    <div class="card-body p-3">

        <form method="GET">

            <input
                type="hidden"
                name="page"
                value="pos_customers"
            >

            <div class="row g-2">

                <div class="col-12 col-md">

                    <div class="input-group search-box">

                        <span class="input-group-text bg-body border-end-0">

                            <i class="bi bi-search text-muted"></i>

                        </span>

                        <input
                            type="text"
                            name="search"
                            class="form-control border-start-0"
                            placeholder="Search customer name, email or phone..."
                            value="<?= e($search) ?>"
                        >

                    </div>

                </div>

                <div class="col-12 col-md-auto">

                    <button
                        type="submit"
                        class="btn btn-primary rounded-3 w-100"
                    >

                        <i class="bi bi-search me-1"></i>

                        Search

                    </button>

                </div>

                <?php if ($search !== ''): ?>

                <div class="col-12 col-md-auto">

                    <a
                        href="index.php?page=pos_customers"
                        class="btn btn-outline-secondary rounded-3 w-100"
                    >

                        Clear

                    </a>

                </div>

                <?php endif; ?>

            </div>

        </form>

    </div>

</div>


<!-- CUSTOMER LIST -->

<div class="card pos-card shadow-sm bg-body">

<div class="pos-card-header d-flex justify-content-between align-items-center">

    <div>

        <h5 class="fw-bold mb-1">
            Customer List
        </h5>

        <p class="text-muted small mb-0">

            <?= number_format(count($customers)) ?>

            customer<?= count($customers) === 1 ? '' : 's' ?>

        </p>

    </div>

    <div class="text-muted small">

        <i class="bi bi-building me-1"></i>

        <?= e($businessName) ?>

    </div>

</div>


<?php if (!$customers): ?>

<div class="empty-state text-center">

    <div class="mb-3">

        <i class="bi bi-people display-5 text-muted opacity-50"></i>

    </div>

    <div class="fw-semibold mb-1">

        <?= $search !== ''
            ? 'No customers found'
            : 'No customers yet'
        ?>

    </div>

    <div class="text-muted small mb-3">

        <?= $search !== ''
            ? 'Try another search term.'
            : 'Add your first POS customer to get started.'
        ?>

    </div>

    <?php if ($search === ''): ?>

    <button
        type="button"
        class="btn btn-primary btn-sm fw-bold rounded-3"
        data-bs-toggle="modal"
        data-bs-target="#addCustomerModal"
    >

        <i class="bi bi-person-plus me-1"></i>

        Add Customer

    </button>

    <?php endif; ?>

</div>

<?php else: ?>


<!-- DESKTOP TABLE -->

<div class="table-responsive desktop-customers">

<table class="table table-hover align-middle mb-0">

<thead class="table-light">

<tr>

    <th class="ps-4 py-3">
        Customer
    </th>

    <th>
        Contact
    </th>

    <th>
        Address
    </th>

    <th>
        Status
    </th>

    <th>
        Created
    </th>

    <th class="text-end pe-4">
        Actions
    </th>

</tr>

</thead>

<tbody>

<?php foreach ($customers as $customer): ?>

<?php

$fullName = trim(
    $customer['first_name'] . ' ' .
    ($customer['middle_name'] ?? '') . ' ' .
    $customer['last_name']
);

$initial =
    strtoupper(
        substr(
            $customer['first_name'],
            0,
            1
        )
    );

?>

<tr>

<td class="ps-4">

    <div class="d-flex align-items-center gap-3">

        <div class="customer-avatar">

            <?= e($initial) ?>

        </div>

        <div>

            <div class="customer-name">
                <?= e($fullName) ?>
            </div>

            <div class="customer-info">

                Customer #<?= e($customer['id']) ?>

            </div>

        </div>

    </div>

</td>


<td>

    <?php if (!empty($customer['phone'])): ?>

    <div class="customer-info mb-1">

        <i class="bi bi-telephone me-1"></i>

        <?= e($customer['phone']) ?>

    </div>

    <?php endif; ?>


    <?php if (!empty($customer['email'])): ?>

    <div class="customer-info">

        <i class="bi bi-envelope me-1"></i>

        <?= e($customer['email']) ?>

    </div>

    <?php endif; ?>

    <?php if (
        empty($customer['phone']) &&
        empty($customer['email'])
    ): ?>

    <span class="text-muted small">
        No contact information
    </span>

    <?php endif; ?>

</td>


<td>

    <span class="customer-info">

        <?= !empty($customer['address'])
            ? e($customer['address'])
            : '—'
        ?>

    </span>

</td>


<td>

    <?php if ($customer['status'] === 'active'): ?>

    <span class="status-badge status-active">

        <i class="bi bi-check-circle"></i>

        Active

    </span>

    <?php else: ?>

    <span class="status-badge status-inactive">

        <i class="bi bi-dash-circle"></i>

        Inactive

    </span>

    <?php endif; ?>

</td>


<td>

    <span class="customer-info">

        <?= !empty($customer['created_at'])
            ? date(
                'M d, Y',
                strtotime($customer['created_at'])
            )
            : '—'
        ?>

    </span>

</td>


<td class="text-end pe-4">

    <div class="btn-group">

        <!-- CUSTOMER DETAILS / PURCHASE HISTORY -->
        <a
            href="index.php?page=pos_customer_details&id=<?= e($customer['id']) ?>"
            class="btn btn-sm btn-outline-info"
            title="View Customer Details"
        >
            <i class="bi bi-person-vcard"></i>
        </a>

        <!-- EDIT -->
        <button
            type="button"
            class="btn btn-sm btn-outline-primary"
            data-bs-toggle="modal"
            data-bs-target="#editCustomerModal<?= $customer['id'] ?>"
            title="Edit Customer"
        >
            <i class="bi bi-pencil"></i>
        </button>

        <!-- ACTIVATE / DEACTIVATE -->
        <form method="POST">

            <input
                type="hidden"
                name="action"
                value="toggle_status"
            >

            <input
                type="hidden"
                name="customer_id"
                value="<?= e($customer['id']) ?>"
            >

            <button
                type="submit"
                class="btn btn-sm btn-outline-<?= $customer['status'] === 'active' ? 'danger' : 'success' ?>"
                title="<?= $customer['status'] === 'active' ? 'Deactivate' : 'Activate' ?>"
            >
                <i class="bi bi-<?= $customer['status'] === 'active' ? 'person-dash' : 'person-check' ?>"></i>
            </button>

        </form>

    </div>

</td>

</tr>


<!-- EDIT MODAL -->

<div
    class="modal fade"
    id="editCustomerModal<?= $customer['id'] ?>"
    tabindex="-1"
>

<div class="modal-dialog modal-dialog-centered">

<div class="modal-content">

<form method="POST">

<input
    type="hidden"
    name="action"
    value="edit"
>

<input
    type="hidden"
    name="customer_id"
    value="<?= e($customer['id']) ?>"
>

<div class="modal-header">

    <div>

        <h5 class="modal-title fw-bold">
            Edit Customer
        </h5>

        <div class="text-muted small">
            Customer #<?= e($customer['id']) ?>
        </div>

    </div>

    <button
        type="button"
        class="btn-close"
        data-bs-dismiss="modal"
    ></button>

</div>

<div class="modal-body">

<div class="row g-3">

    <div class="col-md-4">

        <label class="form-label">
            First Name *
        </label>

        <input
            type="text"
            name="first_name"
            class="form-control"
            value="<?= e($customer['first_name']) ?>"
            required
        >

    </div>

    <div class="col-md-4">

        <label class="form-label">
            Middle Name
        </label>

        <input
            type="text"
            name="middle_name"
            class="form-control"
            value="<?= e($customer['middle_name'] ?? '') ?>"
        >

    </div>

    <div class="col-md-4">

        <label class="form-label">
            Last Name *
        </label>

        <input
            type="text"
            name="last_name"
            class="form-control"
            value="<?= e($customer['last_name']) ?>"
            required
        >

    </div>

    <div class="col-md-6">

        <label class="form-label">
            Phone
        </label>

        <input
            type="text"
            name="phone"
            class="form-control"
            value="<?= e($customer['phone'] ?? '') ?>"
        >

    </div>

    <div class="col-md-6">

        <label class="form-label">
            Email
        </label>

        <input
            type="email"
            name="email"
            class="form-control"
            value="<?= e($customer['email'] ?? '') ?>"
        >

    </div>

    <div class="col-12">

        <label class="form-label">
            Address
        </label>

        <textarea
            name="address"
            class="form-control"
            rows="3"
        ><?= e($customer['address'] ?? '') ?></textarea>

    </div>

</div>

</div>

<div class="modal-footer">

    <button
        type="button"
        class="btn btn-outline-secondary rounded-3"
        data-bs-dismiss="modal"
    >
        Cancel
    </button>

    <button
        type="submit"
        class="btn btn-primary rounded-3 fw-bold"
    >

        <i class="bi bi-check-lg me-1"></i>

        Save Changes

    </button>

</div>

</form>

</div>

</div>

</div>

<?php endforeach; ?>

</tbody>

</table>

</div>


<!-- MOBILE CUSTOMER CARDS -->

<div class="mobile-customers p-3">

<?php foreach ($customers as $customer): ?>

<?php

$fullName = trim(
    $customer['first_name'] . ' ' .
    ($customer['middle_name'] ?? '') . ' ' .
    $customer['last_name']
);

$initial =
    strtoupper(
        substr(
            $customer['first_name'],
            0,
            1
        )
    );

?>

<div class="mobile-customer-card">

    <div class="d-flex justify-content-between align-items-start gap-3">

        <div class="d-flex align-items-center gap-3">

            <div class="customer-avatar">

                <?= e($initial) ?>

            </div>

            <div>

                <div class="customer-name">

                    <?= e($fullName) ?>

                </div>

                <div class="customer-info">

                    Customer #<?= e($customer['id']) ?>

                </div>

            </div>

        </div>


        <?php if ($customer['status'] === 'active'): ?>

        <span class="status-badge status-active">
            Active
        </span>

        <?php else: ?>

        <span class="status-badge status-inactive">
            Inactive
        </span>

        <?php endif; ?>

    </div>


    <div class="mt-3">

        <?php if (!empty($customer['phone'])): ?>

        <div class="customer-detail mb-2">

            <i class="bi bi-telephone text-muted"></i>

            <?= e($customer['phone']) ?>

        </div>

        <?php endif; ?>


        <?php if (!empty($customer['email'])): ?>

        <div class="customer-detail mb-2">

            <i class="bi bi-envelope text-muted"></i>

            <?= e($customer['email']) ?>

        </div>

        <?php endif; ?>


        <?php if (!empty($customer['address'])): ?>

        <div class="customer-detail">

            <i class="bi bi-geo-alt text-muted"></i>

            <?= e($customer['address']) ?>

        </div>

        <?php endif; ?>

    </div>


    <div class="d-flex gap-2 mt-3">

        <button
            type="button"
            class="btn btn-sm btn-outline-primary flex-grow-1 rounded-3"
            data-bs-toggle="modal"
            data-bs-target="#editCustomerModal<?= $customer['id'] ?>"
        >

            <i class="bi bi-pencil me-1"></i>

            Edit

        </button>


        <form method="POST" class="flex-grow-1">

            <input
                type="hidden"
                name="action"
                value="toggle_status"
            >

            <input
                type="hidden"
                name="customer_id"
                value="<?= e($customer['id']) ?>"
            >

            <button
                type="submit"
                class="btn btn-sm btn-outline-<?= $customer['status'] === 'active' ? 'danger' : 'success' ?> w-100 rounded-3"
            >

                <i class="bi bi-person-<?= $customer['status'] === 'active' ? 'dash' : 'check' ?> me-1"></i>

                <?= $customer['status'] === 'active'
                    ? 'Deactivate'
                    : 'Activate'
                ?>

            </button>

        </form>

    </div>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>

</div>

</div>

</main>

</div>


<!-- ADD CUSTOMER MODAL -->

<div
    class="modal fade"
    id="addCustomerModal"
    tabindex="-1"
>

<div class="modal-dialog modal-dialog-centered">

<div class="modal-content">

<form method="POST">

<input
    type="hidden"
    name="action"
    value="add"
>

<div class="modal-header">

    <div>

        <h5 class="modal-title fw-bold">
            Add Customer
        </h5>

        <div class="text-muted small">

            <?= e($businessName) ?>

        </div>

    </div>

    <button
        type="button"
        class="btn-close"
        data-bs-dismiss="modal"
    ></button>

</div>


<div class="modal-body">

<div class="row g-3">

    <div class="col-md-4">

        <label class="form-label">
            First Name *
        </label>

        <input
            type="text"
            name="first_name"
            class="form-control"
            placeholder="Juan"
            required
        >

    </div>

    <div class="col-md-4">

        <label class="form-label">
            Middle Name
        </label>

        <input
            type="text"
            name="middle_name"
            class="form-control"
            placeholder="Santos"
        >

    </div>

    <div class="col-md-4">

        <label class="form-label">
            Last Name *
        </label>

        <input
            type="text"
            name="last_name"
            class="form-control"
            placeholder="Dela Cruz"
            required
        >

    </div>


    <div class="col-md-6">

        <label class="form-label">
            Phone
        </label>

        <input
            type="text"
            name="phone"
            class="form-control"
            placeholder="09XXXXXXXXX"
        >

    </div>


    <div class="col-md-6">

        <label class="form-label">
            Email
        </label>

        <input
            type="email"
            name="email"
            class="form-control"
            placeholder="customer@example.com"
        >

    </div>


    <div class="col-12">

        <label class="form-label">
            Address
        </label>

        <textarea
            name="address"
            class="form-control"
            rows="3"
            placeholder="Customer address"
        ></textarea>

    </div>

</div>

</div>


<div class="modal-footer">

    <button
        type="button"
        class="btn btn-outline-secondary rounded-3"
        data-bs-dismiss="modal"
    >

        Cancel

    </button>

    <button
        type="submit"
        class="btn btn-primary rounded-3 fw-bold"
    >

        <i class="bi bi-person-plus me-1"></i>

        Add Customer

    </button>

</div>

</form>

</div>

</div>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>
