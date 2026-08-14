<?php
// =========================================================
// SELECT BUSINESS
// =========================================================
// Note:
// Database connection and session are already loaded
// through index.php -> app.php
// =========================================================

$pdo = Database::getConnection();

$userId = $_SESSION['user_id'] ?? null;


// =========================================================
// SECURITY CHECK
// =========================================================

if (!$userId) {
    header('Location: index.php?page=login');
    exit;
}


// =========================================================
// FETCH BUSINESSES BELONGING TO LOGGED-IN USER
// =========================================================

$stmt = $pdo->prepare("
    SELECT *
    FROM businesses
    WHERE owner_id = ?
    ORDER BY business_name ASC
");

$stmt->execute([$userId]);

$businesses = $stmt->fetchAll(PDO::FETCH_ASSOC);


// =========================================================
// VARIABLES
// =========================================================

$error = '';


// =========================================================
// HANDLE BUSINESS SELECTION
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['business_id'])
) {

    $selectedBusinessId =
        intval($_POST['business_id']);


    if ($selectedBusinessId <= 0) {

        $error =
            "Please select a valid business.";

    } else {

        /*
         * Important:
         * We do NOT trust the business_id sent
         * by the browser.
         *
         * We check again that the business
         * belongs to the logged-in user.
         */

        $businessStmt = $pdo->prepare("
            SELECT
                id,
                business_name,
                status
            FROM businesses
            WHERE id = ?
            AND owner_id = ?
            LIMIT 1
        ");

        $businessStmt->execute([
            $selectedBusinessId,
            $userId
        ]);

        $selectedBusiness =
            $businessStmt->fetch(PDO::FETCH_ASSOC);


        if (!$selectedBusiness) {

            $error =
                "The selected business does not exist or you do not have access to it.";

        } else {

            /*
             * Check business status.
             *
             * If your database uses another status
             * system, you can modify this section.
             */

            $businessStatus =
                strtolower(
                    trim(
                        $selectedBusiness['status'] ?? 'active'
                    )
                );


            if (
                $businessStatus !== 'active' &&
                $businessStatus !== ''
            ) {

                $error =
                    "This business is currently not active.";

            } else {

                /*
                 * Store selected business
                 * in the session.
                 */

                $_SESSION['business_id'] =
                    $selectedBusiness['id'];

                $_SESSION['business_name'] =
                    $selectedBusiness['business_name'];


                /*
                 * Optional useful session value.
                 */

                $_SESSION['business_status'] =
                    $selectedBusiness['status'] ?? 'active';


                /*
                 * Regenerate session ID after
                 * changing the active business.
                 *
                 * This is a good security practice.
                 */

                session_regenerate_id(true);


                /*
                 * Go to dashboard.
                 */

                header(
                    'Location: index.php?page=dashboard'
                );

                exit;
            }
        }
    }
}


// =========================================================
// PAGE TITLE
// =========================================================

$pageTitle =
    "Select Business - MULTIBUSINESSSYSTEM";


// =========================================================
// SHARED HEADER
// =========================================================

include __DIR__ . '/partials/header.php';

?>

<style>

/* =========================================================
   SELECT BUSINESS PAGE
   ========================================================= */

.select-business-page {
    min-height: calc(100vh - 100px);

    padding-top: 30px;
    padding-bottom: 50px;
}


/* =========================================================
   HEADER
   ========================================================= */

.business-page-header {
    text-align: center;

    margin-bottom: 35px;
}

.business-page-header h2 {
    font-size: 1.8rem;
    font-weight: 800;

    margin-bottom: 8px;
}

.business-page-header p {
    max-width: 600px;

    margin-left: auto;
    margin-right: auto;

    font-size: .9rem;
}


/* =========================================================
   BUSINESS CARD
   ========================================================= */

.business-card {
    height: 100%;

    border: 1px solid var(--bs-border-color) !important;

    border-radius: 18px !important;

    background: var(--bs-body-bg);

    transition:
        transform .18s ease,
        box-shadow .18s ease,
        border-color .18s ease;
}

.business-card:hover {
    transform: translateY(-3px);

    box-shadow:
        0 10px 30px rgba(0,0,0,.08) !important;

    border-color:
        rgba(var(--bs-primary-rgb), .35) !important;
}


/* =========================================================
   BUSINESS ICON
   ========================================================= */

.business-icon {
    width: 52px;
    height: 52px;

    border-radius: 14px;

    display: flex;

    align-items: center;
    justify-content: center;

    background:
        rgba(var(--bs-primary-rgb), .10);

    color:
        var(--bs-primary);

    font-size: 23px;

    margin-bottom: 18px;
}


/* =========================================================
   BUSINESS NAME
   ========================================================= */

.business-name {
    font-size: 1.15rem;

    font-weight: 750;

    color: var(--bs-body-color);

    word-break: break-word;

    line-height: 1.3;
}


/* =========================================================
   BUSINESS STATUS
   ========================================================= */

.business-status {
    font-size: .78rem;

    color: var(--bs-secondary-color);
}


/* =========================================================
   BUTTON
   ========================================================= */

.manage-business-btn {
    min-height: 44px;

    border-radius: 10px !important;

    font-size: .85rem;

    transition:
        transform .15s ease,
        box-shadow .15s ease;
}

.manage-business-btn:hover {
    transform: translateY(-1px);

    box-shadow:
        0 5px 15px
        rgba(var(--bs-primary-rgb), .20);
}


/* =========================================================
   EMPTY STATE
   ========================================================= */

.business-empty {
    max-width: 600px;

    margin: 0 auto;

    border-radius: 18px;

    padding: 45px 25px;

    border:
        1px solid var(--bs-border-color);

    background:
        var(--bs-body-bg);
}

.business-empty-icon {
    width: 65px;
    height: 65px;

    margin: 0 auto 18px;

    border-radius: 18px;

    display: flex;

    align-items: center;
    justify-content: center;

    background:
        rgba(var(--bs-warning-rgb), .10);

    color:
        var(--bs-warning);

    font-size: 28px;
}


/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 575.98px) {

    .select-business-page {
        padding-top: 20px;

        padding-left: 12px;
        padding-right: 12px;
    }

    .business-page-header {
        margin-bottom: 25px;
    }

    .business-page-header h2 {
        font-size: 1.45rem;
    }

    .business-page-header p {
        font-size: .8rem;
    }

    .business-card {
        border-radius: 15px !important;
    }

    .business-name {
        font-size: 1rem;
    }

}

</style>


<!-- =========================================================
     MAIN CONTENT
     ========================================================= -->

<div class="container-fluid select-business-page">

    <div class="container">


        <!-- =====================================================
             PAGE HEADER
             ===================================================== -->

        <div class="business-page-header">

            <h2 class="text-body">

                Select a Business

            </h2>


            <p class="text-muted mb-0">

                Choose a business below to access its
                dashboard and manage operations.

            </p>

        </div>


        <!-- =====================================================
             ERROR
             ===================================================== -->

        <?php if (!empty($error)): ?>

            <div
                class="alert alert-danger
                       alert-dismissible fade show
                       rounded-4 shadow-sm
                       small mb-4"
                role="alert"
            >

                <i
                    class="bi bi-exclamation-triangle-fill me-2"
                ></i>

                <?= htmlspecialchars($error) ?>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"
                ></button>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             BUSINESSES
             ===================================================== -->

        <?php if (empty($businesses)): ?>


            <!-- EMPTY STATE -->

            <div
                class="business-empty
                       text-center
                       shadow-sm"
            >

                <div class="business-empty-icon">

                    <i
                        class="bi bi-building-x"
                    ></i>

                </div>


                <h5
                    class="fw-bold text-body mb-2"
                >

                    No Businesses Found

                </h5>


                <p
                    class="text-muted small mb-4"
                >

                    You do not have any businesses
                    registered under your account yet.

                </p>


                <a
                    href="index.php?page=dashboard"
                    class="btn btn-primary
                           fw-semibold
                           rounded-3
                           px-4"
                >

                    <i
                        class="bi bi-arrow-left me-1"
                    ></i>

                    Back to Dashboard

                </a>

            </div>


        <?php else: ?>


            <!-- =================================================
                 BUSINESS GRID
                 ================================================= -->

            <div
                class="row
                       row-cols-1
                       row-cols-sm-2
                       row-cols-lg-3
                       g-3 g-md-4
                       justify-content-center"
            >


                <?php foreach ($businesses as $business): ?>

                    <?php

                    $businessId =
                        (int)($business['id'] ?? 0);

                    $businessNameValue =
                        $business['business_name']
                        ?? 'Unnamed Business';

                    $businessStatus =
                        $business['status']
                        ?? 'active';

                    $businessStatusLower =
                        strtolower(
                            trim($businessStatus)
                        );


                    $isActive =
                        (
                            $businessStatusLower ===
                            'active'
                        );

                    ?>


                    <div class="col">


                        <!-- BUSINESS CARD -->

                        <div
                            class="card
                                   business-card
                                   shadow-sm"
                        >

                            <div
                                class="card-body
                                       d-flex
                                       flex-column
                                       p-3 p-md-4"
                            >


                                <!-- ICON -->

                                <div class="business-icon">

                                    <i
                                        class="bi bi-building"
                                    ></i>

                                </div>


                                <!-- BUSINESS INFORMATION -->

                                <div
                                    class="flex-grow-1"
                                >

                                    <h4
                                        class="business-name
                                               mb-2"
                                    >

                                        <?= htmlspecialchars(
                                            $businessNameValue
                                        ) ?>

                                    </h4>


                                    <div
                                        class="business-status
                                               mb-4"
                                    >

                                        <span
                                            class="me-1"
                                        >
                                            Status:
                                        </span>


                                        <?php if ($isActive): ?>

                                            <span
                                                class="badge
                                                       bg-success
                                                       bg-opacity-10
                                                       text-success
                                                       px-2 py-1"
                                            >

                                                <i
                                                    class="bi
                                                           bi-check-circle-fill
                                                           me-1"
                                                ></i>

                                                <?= htmlspecialchars(
                                                    ucfirst(
                                                        $businessStatus
                                                    )
                                                ) ?>

                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="badge
                                                       bg-secondary
                                                       bg-opacity-10
                                                       text-secondary
                                                       px-2 py-1"
                                            >

                                                <?= htmlspecialchars(
                                                    ucfirst(
                                                        $businessStatus
                                                    )
                                                ) ?>

                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </div>


                                <!-- SELECT FORM -->

                                <form
                                    action="index.php?page=select_business"
                                    method="POST"
                                    class="business-select-form"
                                >

                                    <input
                                        type="hidden"
                                        name="business_id"
                                        value="<?= $businessId ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="btn
                                               btn-primary
                                               w-100
                                               fw-bold
                                               manage-business-btn"
                                        <?= !$isActive
                                            ? 'disabled'
                                            : ''
                                        ?>
                                    >

                                        <?php if ($isActive): ?>

                                            <i
                                                class="bi
                                                       bi-box-arrow-in-right
                                                       me-1"
                                            ></i>

                                            Manage Business

                                        <?php else: ?>

                                            <i
                                                class="bi
                                                       bi-lock-fill
                                                       me-1"
                                            ></i>

                                            Business Unavailable

                                        <?php endif; ?>

                                    </button>

                                </form>


                            </div>

                        </div>


                    </div>


                <?php endforeach; ?>


            </div>


        <?php endif; ?>


    </div>

</div>


<?php

// =========================================================
// SHARED FOOTER
// =========================================================

include __DIR__ . '/partials/footer.php';

?>