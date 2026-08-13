<?php
$pdo = Database::getConnection();
$businessId = $_SESSION['business_id'] ?? null;

if (!$businessId) {
    header('Location: index.php?page=select_business');
    exit;
}

$error = '';
$success = '';

// Handle Add Borrower Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_borrower'])) {

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $contactNo = trim($_POST['contact_no'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (!empty($firstName) && !empty($lastName)) {

        $stmt = $pdo->prepare("
            INSERT INTO loan_borrowers
            (business_id, first_name, last_name, contact_no, address)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $businessId,
            $firstName,
            $lastName,
            $contactNo,
            $address
        ]);

        header('Location: index.php?page=borrowers&success=1');
        exit;

    } else {
        $error = "Both First Name and Last Name are required.";
    }
}

if (isset($_GET['success'])) {
    $success = "Borrower added successfully!";
}

$activePage = 'borrowers';
$pageTitle = "Borrowers - Loan Management";

// Fetch borrowers
$stmt = $pdo->prepare("
    SELECT *
    FROM loan_borrowers
    WHERE business_id = ?
    ORDER BY created_at DESC
");

$stmt->execute([$businessId]);
$borrowers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <!-- Prevent theme flash -->
    <script>
        (function () {
            const savedTheme = localStorage.getItem('bs-theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>

    <style>

        body {
            min-height: 100vh;
        }

        .borrowers-main {
            min-width: 0;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 24px;
        }

        /* Search Card */
        .search-card {
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

        /* Main Card */
        .borrowers-card {
            border: 0;
            border-radius: 16px;
            overflow: hidden;
        }

        /* Avatar */
        .borrower-avatar {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.8rem;
        }

        /* Table */
        .borrowers-table th {
            font-size: 0.72rem;
            letter-spacing: 0.04em;
            white-space: nowrap;
        }

        .borrowers-table td {
            padding-top: 14px;
            padding-bottom: 14px;
        }

        /* Mobile Cards */
        .mobile-borrowers {
            display: none;
        }

        .mobile-borrower-card {
            border: 1px solid var(--bs-border-color);
            border-radius: 13px;
            padding: 14px;
            margin-bottom: 10px;
            background: var(--bs-body-bg);
        }

        .mobile-borrower-card:last-child {
            margin-bottom: 0;
        }

        .mobile-borrower-top {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .mobile-borrower-name {
            font-size: 0.9rem;
            font-weight: 700;
        }

        .mobile-borrower-contact {
            font-size: 0.75rem;
            color: var(--bs-secondary-color);
        }

        .mobile-borrower-address {
            font-size: 0.76rem;
            color: var(--bs-secondary-color);
            line-height: 1.4;
        }

        .mobile-borrower-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid var(--bs-border-color);
        }

        /* Empty State */
        .empty-state {
            padding: 55px 20px;
        }

        /* Alerts */
        .page-alert {
            border: 0;
            border-radius: 12px;
        }

        @media (max-width: 575.98px) {

            .borrowers-main {
                width: 100%;
            }

            .main-content {
                padding: 14px !important;
            }

            /* Header */
            .page-header {
                margin-bottom: 16px;
            }

            .page-title {
                font-size: 1.35rem;
            }

            .page-subtitle {
                font-size: 0.76rem;
                line-height: 1.4;
            }

            .add-borrower-btn {
                width: 100%;
                justify-content: center;
            }

            /* Alerts */
            .page-alert {
                font-size: 0.78rem;
                margin-bottom: 14px !important;
            }

            /* Search */
            .search-card {
                border-radius: 14px;
                margin-bottom: 14px !important;
            }

            .search-card .card-body {
                padding: 12px;
            }

            .search-wrapper {
                border-radius: 10px;
            }

            .search-wrapper .form-control {
                font-size: 0.82rem;
            }

            /* Hide desktop table */
            .desktop-borrowers {
                display: none;
            }

            /* Show mobile cards */
            .mobile-borrowers {
                display: block;
                padding: 12px;
            }

            .borrowers-card {
                border-radius: 14px;
            }

            .mobile-borrower-card {
                padding: 13px;
            }

            .empty-state {
                padding: 35px 15px;
            }

            /* Modal */
            .modal-dialog {
                margin: 10px;
            }

            .modal-content {
                border-radius: 16px !important;
            }

            .modal-header {
                padding: 15px 16px !important;
            }

            .modal-body {
                padding: 16px !important;
            }

            .modal-footer {
                padding: 12px 16px !important;
            }

            .modal-footer .btn {
                flex: 1;
            }

            .form-control {
                font-size: 0.85rem;
            }

            .form-label {
                font-size: 0.75rem !important;
            }
        }

    </style>

</head>

<body class="bg-body-tertiary">

<div class="d-flex flex-column flex-lg-row min-vh-100">

    <!-- Sidebar -->
    <?php include __DIR__ . '/../../../resources/partials/loansidebar.php'; ?>


    <!-- =========================
         MAIN CONTENT
    ========================== -->

    <main class="borrowers-main flex-grow-1 bg-body-tertiary">

        <div class="main-content p-3 p-md-4">


            <!-- =========================
                 PAGE HEADER
            ========================== -->

            <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

                <div class="min-width-0">

                    <div class="d-flex align-items-center gap-2 mb-1">

                        <div class="d-lg-none bg-primary bg-opacity-10 text-primary rounded-3 p-2">
                            <i class="bi bi-people"></i>
                        </div>

                        <h2 class="page-title fw-bold text-body mb-0">
                            Borrowers
                        </h2>

                    </div>

                    <p class="page-subtitle text-muted small mb-0">

                        Manage clients registered under

                        <span class="fw-semibold text-primary">
                            <?= htmlspecialchars($_SESSION['business_name'] ?? '') ?>
                        </span>

                    </p>

                </div>


                <!-- Add Borrower -->
                <button
                    type="button"
                    class="add-borrower-btn btn btn-primary fw-bold px-3 py-2 rounded-3 shadow-sm d-flex align-items-center gap-2 text-nowrap"
                    data-bs-toggle="modal"
                    data-bs-target="#addBorrowerModal"
                >
                    <i class="bi bi-person-plus-fill"></i>
                    <span>Add New Borrower</span>
                </button>

            </div>


            <!-- =========================
                 SUCCESS / ERROR
            ========================== -->

            <?php if (!empty($success)): ?>

                <div
                    class="page-alert alert alert-success alert-dismissible fade show py-2 px-3 shadow-sm small"
                    role="alert"
                >
                    <i class="bi bi-check-circle-fill me-2"></i>

                    <?= htmlspecialchars($success) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Close"
                    ></button>
                </div>

            <?php endif; ?>


            <?php if (!empty($error)): ?>

                <div
                    class="page-alert alert alert-danger alert-dismissible fade show py-2 px-3 shadow-sm small"
                    role="alert"
                >
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>

                    <?= htmlspecialchars($error) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Close"
                    ></button>
                </div>

            <?php endif; ?>


            <!-- =========================
                 SEARCH
            ========================== -->

            <div class="search-card card shadow-sm mb-4">

                <div class="card-body p-3">

                    <div class="search-wrapper input-group">

                        <span class="input-group-text text-muted">
                            <i class="bi bi-search"></i>
                        </span>

                        <input
                            type="text"
                            id="searchBorrower"
                            class="form-control shadow-none"
                            placeholder="Search borrower..."
                            autocomplete="off"
                        >

                        <button
                            type="button"
                            id="clearSearch"
                            class="btn d-none"
                            title="Clear search"
                        >
                            <i class="bi bi-x-lg"></i>
                        </button>

                    </div>

                </div>

            </div>


            <!-- =========================
                 BORROWERS CARD
            ========================== -->

            <div class="borrowers-card card shadow-sm bg-body">


                <!-- =========================
                     DESKTOP TABLE
                ========================== -->

                <div class="desktop-borrowers table-responsive">

                    <table class="borrowers-table table table-hover align-middle mb-0">

                        <thead class="table-light text-uppercase text-muted">

                            <tr>

                                <th class="py-3 ps-4">
                                    Borrower Name
                                </th>

                                <th class="py-3">
                                    Contact Number
                                </th>

                                <th class="py-3">
                                    Address
                                </th>

                                <th class="py-3 text-end pe-4">
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody id="borrowersTableBody">

                        <?php if (empty($borrowers)): ?>

                            <tr>

                                <td colspan="4">

                                    <div class="empty-state text-center text-muted">

                                        <div class="mb-3">
                                            <i class="bi bi-people display-6 opacity-50"></i>
                                        </div>

                                        <div class="fw-semibold mb-1">
                                            No borrowers found
                                        </div>

                                        <div class="small mb-3">
                                            Get started by adding your first client profile.
                                        </div>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-primary fw-bold"
                                            data-bs-toggle="modal"
                                            data-bs-target="#addBorrowerModal"
                                        >
                                            <i class="bi bi-person-plus me-1"></i>
                                            Add Borrower
                                        </button>

                                    </div>

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($borrowers as $b): ?>

                                <?php
                                $fullName = trim(
                                    ($b['first_name'] ?? '') . ' ' .
                                    ($b['last_name'] ?? '')
                                );

                                $initial = strtoupper(
                                    substr($b['first_name'] ?? 'B', 0, 1)
                                );
                                ?>

                                <tr class="borrower-row">

                                    <!-- Name -->
                                    <td class="ps-4">

                                        <div class="d-flex align-items-center gap-3">

                                            <div class="borrower-avatar bg-primary bg-opacity-10 text-primary">
                                                <?= htmlspecialchars($initial) ?>
                                            </div>

                                            <div>

                                                <div class="fw-bold text-body">
                                                    <?= htmlspecialchars($fullName) ?>
                                                </div>

                                                <div class="small text-muted">
                                                    Borrower
                                                </div>

                                            </div>

                                        </div>

                                    </td>


                                    <!-- Contact -->
                                    <td>

                                        <?php if (!empty($b['contact_no'])): ?>

                                            <span class="text-body small">
                                                <i class="bi bi-telephone me-1 text-muted"></i>
                                                <?= htmlspecialchars($b['contact_no']) ?>
                                            </span>

                                        <?php else: ?>

                                            <span class="text-muted small">
                                                No contact number
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- Address -->
                                    <td>

                                        <div
                                            class="text-muted small text-truncate"
                                            style="max-width: 280px;"
                                            title="<?= htmlspecialchars($b['address'] ?? '') ?>"
                                        >
                                            <?=
                                            !empty($b['address'])
                                                ? htmlspecialchars($b['address'])
                                                : 'No address provided'
                                            ?>
                                        </div>

                                    </td>


                                    <!-- Actions -->
                                    <td class="text-end pe-4">

                                        <a
                                            href="index.php?page=borrower_details&id=<?= $b['id'] ?>"
                                            class="btn btn-sm btn-outline-secondary rounded-3 px-3"
                                            title="View Borrower Details"
                                        >
                                            <i class="bi bi-eye me-1"></i>
                                            View
                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>


                <!-- =========================
                     MOBILE BORROWER CARDS
                ========================== -->

                <div class="mobile-borrowers" id="mobileBorrowers">

                    <?php if (empty($borrowers)): ?>

                        <div class="empty-state text-center text-muted">

                            <div class="mb-3">
                                <i class="bi bi-people display-6 opacity-50"></i>
                            </div>

                            <div class="fw-semibold mb-1">
                                No borrowers found
                            </div>

                            <div class="small mb-3">
                                Add your first client profile to get started.
                            </div>

                            <button
                                type="button"
                                class="btn btn-sm btn-primary fw-bold"
                                data-bs-toggle="modal"
                                data-bs-target="#addBorrowerModal"
                            >
                                <i class="bi bi-person-plus me-1"></i>
                                Add Borrower
                            </button>

                        </div>

                    <?php else: ?>

                        <?php foreach ($borrowers as $b): ?>

                            <?php
                            $fullName = trim(
                                ($b['first_name'] ?? '') . ' ' .
                                ($b['last_name'] ?? '')
                            );

                            $initial = strtoupper(
                                substr($b['first_name'] ?? 'B', 0, 1)
                            );
                            ?>

                            <div
                                class="mobile-borrower-card borrower-mobile-item"
                                data-search="<?= htmlspecialchars(
                                    strtolower(
                                        $fullName . ' ' .
                                        ($b['contact_no'] ?? '') . ' ' .
                                        ($b['address'] ?? '')
                                    )
                                ) ?>"
                            >

                                <div class="mobile-borrower-top">

                                    <div class="borrower-avatar bg-primary bg-opacity-10 text-primary">
                                        <?= htmlspecialchars($initial) ?>
                                    </div>

                                    <div class="flex-grow-1 min-width-0">

                                        <div class="mobile-borrower-name text-body text-truncate">
                                            <?= htmlspecialchars($fullName) ?>
                                        </div>

                                        <div class="mobile-borrower-contact">

                                            <?php if (!empty($b['contact_no'])): ?>

                                                <i class="bi bi-telephone me-1"></i>
                                                <?= htmlspecialchars($b['contact_no']) ?>

                                            <?php else: ?>

                                                <i class="bi bi-telephone-x me-1"></i>
                                                No contact number

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                </div>


                                <div class="mobile-borrower-address mt-3">

                                    <i class="bi bi-geo-alt me-1"></i>

                                    <?=
                                    !empty($b['address'])
                                        ? htmlspecialchars($b['address'])
                                        : 'No address provided'
                                    ?>

                                </div>


                                <div class="mobile-borrower-footer">

                                    <span class="small text-muted">
                                        Borrower Profile
                                    </span>

                                    <a
                                        href="index.php?page=borrower_details&id=<?= $b['id'] ?>"
                                        class="btn btn-sm btn-outline-primary rounded-3 px-3"
                                    >
                                        <i class="bi bi-eye me-1"></i>
                                        View
                                    </a>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>


                <!-- No Search Results -->
                <div
                    id="noSearchResults"
                    class="text-center text-muted py-5 d-none"
                >
                    <i class="bi bi-search display-6 opacity-50"></i>

                    <div class="fw-semibold mt-3">
                        No borrowers found
                    </div>

                    <div class="small">
                        Try searching using a different name, contact number, or address.
                    </div>
                </div>

            </div>

        </div>

    </main>

</div>


<!-- =========================
     ADD BORROWER MODAL
========================== -->

<div
    class="modal fade"
    id="addBorrowerModal"
    tabindex="-1"
    aria-labelledby="addBorrowerModalLabel"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content border-0 shadow-lg rounded-4">

            <!-- Modal Header -->
            <div class="modal-header border-bottom px-4 py-3">

                <h5
                    class="modal-title fw-bold fs-6 text-body"
                    id="addBorrowerModalLabel"
                >
                    <i class="bi bi-person-plus-fill text-primary me-2"></i>
                    Add New Borrower
                </h5>

                <button
                    type="button"
                    class="btn-close shadow-none"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>

            </div>


            <!-- Form -->
            <form method="POST">

                <input
                    type="hidden"
                    name="add_borrower"
                    value="1"
                >


                <!-- Modal Body -->
                <div class="modal-body p-4">

                    <div class="row">

                        <!-- First Name -->
                        <div class="col-md-6 mb-3">

                            <label class="form-label fw-semibold small">
                                First Name
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                name="first_name"
                                class="form-control shadow-none"
                                required
                                autocomplete="given-name"
                                placeholder="e.g. Juan"
                            >

                        </div>


                        <!-- Last Name -->
                        <div class="col-md-6 mb-3">

                            <label class="form-label fw-semibold small">
                                Last Name
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                name="last_name"
                                class="form-control shadow-none"
                                required
                                autocomplete="family-name"
                                placeholder="e.g. Dela Cruz"
                            >

                        </div>

                    </div>


                    <!-- Contact -->
                    <div class="mb-3">

                        <label class="form-label fw-semibold small">
                            Contact Number
                        </label>

                        <input
                            type="tel"
                            name="contact_no"
                            class="form-control shadow-none"
                            autocomplete="tel"
                            placeholder="e.g. 09123456789"
                        >

                    </div>


                    <!-- Address -->
                    <div class="mb-2">

                        <label class="form-label fw-semibold small">
                            Complete Address
                        </label>

                        <textarea
                            name="address"
                            class="form-control shadow-none"
                            rows="3"
                            autocomplete="street-address"
                            placeholder="e.g. Purok 3, Barangay Central, City"
                        ></textarea>

                    </div>

                </div>


                <!-- Modal Footer -->
                <div class="modal-footer border-top px-4 py-3">

                    <button
                        type="button"
                        class="btn btn-light px-4 fw-semibold"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary px-4 fw-bold shadow-sm"
                    >
                        <i class="bi bi-check-lg me-1"></i>
                        Save Borrower
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- Bootstrap JS -->
<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


<!-- Search -->
<script>

document.addEventListener('DOMContentLoaded', function () {

    const searchInput = document.getElementById('searchBorrower');
    const clearButton = document.getElementById('clearSearch');
    const noResults = document.getElementById('noSearchResults');

    const desktopRows = document.querySelectorAll('.borrower-row');
    const mobileItems = document.querySelectorAll('.borrower-mobile-item');


    function performSearch() {

        const searchTerm = searchInput.value
            .toLowerCase()
            .trim();

        let desktopMatches = 0;
        let mobileMatches = 0;


        /* Desktop */

        desktopRows.forEach(function (row) {

            const text = row.textContent.toLowerCase();

            if (text.includes(searchTerm)) {

                row.style.display = '';
                desktopMatches++;

            } else {

                row.style.display = 'none';

            }

        });


        /* Mobile */

        mobileItems.forEach(function (item) {

            const searchData =
                item.getAttribute('data-search') || '';

            if (searchData.includes(searchTerm)) {

                item.style.display = '';
                mobileMatches++;

            } else {

                item.style.display = 'none';

            }

        });


        /* Clear button */

        if (searchTerm.length > 0) {

            clearButton.classList.remove('d-none');

        } else {

            clearButton.classList.add('d-none');

        }


        /* No results */

        const totalMatches =
            window.innerWidth < 576
                ? mobileMatches
                : desktopMatches;

        if (searchTerm.length > 0 && totalMatches === 0) {

            noResults.classList.remove('d-none');

        } else {

            noResults.classList.add('d-none');

        }

    }


    searchInput.addEventListener('input', performSearch);


    clearButton.addEventListener('click', function () {

        searchInput.value = '';

        performSearch();

        searchInput.focus();

    });

});

</script>

</body>
</html>