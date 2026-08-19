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

/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$defaults = [
    'currency' => 'PHP',
    'currency_symbol' => '₱',

    'default_interest_type' => 'flat',
    'default_interest_rate' => '0.00',
    'default_payment_frequency' => 'monthly',
    'default_payment_type' => 'installment',
    'default_loan_term' => 12,
    'grace_period' => 0,

    'late_fee_type' => 'fixed',
    'late_fee_amount' => '0.00',

    'business_address' => '',
    'business_phone' => '',
    'business_email' => '',

    'date_format' => 'M d, Y',
    'timezone' => 'Asia/Manila'
];

/*
|--------------------------------------------------------------------------
| CREATE SETTINGS RECORD IF IT DOES NOT EXIST
|--------------------------------------------------------------------------
*/

try {

    $checkStmt = $pdo->prepare("
        SELECT id
        FROM loan_settings
        WHERE business_id = ?
        LIMIT 1
    ");

    $checkStmt->execute([$businessId]);

    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {

        $insertStmt = $pdo->prepare("
            INSERT INTO loan_settings (
                business_id
            )
            VALUES (?)
        ");

        $insertStmt->execute([$businessId]);
    }

} catch (PDOException $e) {

    $error = 'Unable to initialize loan settings. Please check your database configuration.';
}

/*
|--------------------------------------------------------------------------
| SAVE SETTINGS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {

    $currency = trim($_POST['currency'] ?? 'PHP');
    $currencySymbol = trim($_POST['currency_symbol'] ?? '₱');

    $defaultInterestType =
        $_POST['default_interest_type'] ?? 'flat';

    $defaultInterestRate =
        (float)($_POST['default_interest_rate'] ?? 0);

    $defaultPaymentFrequency =
        $_POST['default_payment_frequency'] ?? 'monthly';

    $defaultPaymentType =
        $_POST['default_payment_type'] ?? 'installment';

    $defaultLoanTerm =
        max(
            1,
            (int)($_POST['default_loan_term'] ?? 12)
        );

    $gracePeriod =
        max(
            0,
            (int)($_POST['grace_period'] ?? 0)
        );

    $lateFeeType =
        $_POST['late_fee_type'] ?? 'fixed';

    $lateFeeAmount =
        max(
            0,
            (float)($_POST['late_fee_amount'] ?? 0)
        );

    $businessAddress =
        trim($_POST['business_address'] ?? '');

    $businessPhone =
        trim($_POST['business_phone'] ?? '');

    $businessEmail =
        trim($_POST['business_email'] ?? '');

    $dateFormat =
        $_POST['date_format'] ?? 'M d, Y';

    $timezone =
        $_POST['timezone'] ?? 'Asia/Manila';

    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    $allowedCurrencies = [
        'PHP',
        'USD',
        'EUR',
        'GBP'
    ];

    $allowedInterestTypes = [
        'flat',
        'reducing_balance'
    ];

    $allowedFrequencies = [
        'daily',
        'weekly',
        'biweekly',
        'monthly'
    ];

    $allowedPaymentTypes = [
        'installment',
        'lump_sum'
    ];

    $allowedLateFeeTypes = [
        'fixed',
        'percentage'
    ];

    $allowedDateFormats = [
        'M d, Y',
        'F d, Y',
        'Y-m-d',
        'd/m/Y'
    ];

    $allowedTimezones = [
        'Asia/Manila',
        'Asia/Singapore',
        'Asia/Tokyo',
        'UTC'
    ];

    if (!in_array($currency, $allowedCurrencies, true)) {
        $currency = 'PHP';
    }

    if (!in_array($defaultInterestType, $allowedInterestTypes, true)) {
        $defaultInterestType = 'flat';
    }

    if (!in_array($defaultPaymentFrequency, $allowedFrequencies, true)) {
        $defaultPaymentFrequency = 'monthly';
    }

    if (!in_array($defaultPaymentType, $allowedPaymentTypes, true)) {
        $defaultPaymentType = 'installment';
    }

    if (!in_array($lateFeeType, $allowedLateFeeTypes, true)) {
        $lateFeeType = 'fixed';
    }

    if (!in_array($dateFormat, $allowedDateFormats, true)) {
        $dateFormat = 'M d, Y';
    }

    if (!in_array($timezone, $allowedTimezones, true)) {
        $timezone = 'Asia/Manila';
    }

    if ($currencySymbol === '') {
        $currencySymbol = '₱';
    }

    if ($defaultInterestRate < 0) {
        $defaultInterestRate = 0;
    }

    if ($defaultInterestRate > 100) {
        $defaultInterestRate = 100;
    }

    if ($lateFeeType === 'percentage' && $lateFeeAmount > 100) {
        $lateFeeAmount = 100;
    }

    if ($businessEmail !== '' &&
        !filter_var($businessEmail, FILTER_VALIDATE_EMAIL)
    ) {

        $error = 'Please enter a valid business email address.';

    } else {

        try {

            $updateStmt = $pdo->prepare("
                UPDATE loan_settings
                SET
                    currency = ?,
                    currency_symbol = ?,
                    default_interest_type = ?,
                    default_interest_rate = ?,
                    default_payment_frequency = ?,
                    default_payment_type = ?,
                    default_loan_term = ?,
                    grace_period = ?,
                    late_fee_type = ?,
                    late_fee_amount = ?,
                    business_address = ?,
                    business_phone = ?,
                    business_email = ?,
                    date_format = ?,
                    timezone = ?
                WHERE business_id = ?
            ");

            $updateStmt->execute([
                $currency,
                $currencySymbol,
                $defaultInterestType,
                $defaultInterestRate,
                $defaultPaymentFrequency,
                $defaultPaymentType,
                $defaultLoanTerm,
                $gracePeriod,
                $lateFeeType,
                $lateFeeAmount,
                $businessAddress !== ''
                    ? $businessAddress
                    : null,
                $businessPhone !== ''
                    ? $businessPhone
                    : null,
                $businessEmail !== ''
                    ? $businessEmail
                    : null,
                $dateFormat,
                $timezone,
                $businessId
            ]);

            $success = 'Settings saved successfully.';

        } catch (PDOException $e) {

            $error = 'Unable to save settings. Please try again.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| LOAD SETTINGS
|--------------------------------------------------------------------------
*/

try {

    $settingsStmt = $pdo->prepare("
        SELECT *
        FROM loan_settings
        WHERE business_id = ?
        LIMIT 1
    ");

    $settingsStmt->execute([$businessId]);

    $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $settings = false;

    if ($error === '') {
        $error = 'Unable to load your settings.';
    }
}

/*
|--------------------------------------------------------------------------
| MERGE WITH DEFAULTS
|--------------------------------------------------------------------------
*/

if (!$settings) {
    $settings = $defaults;
} else {
    $settings = array_merge(
        $defaults,
        $settings
    );
}

$businessName =
    $_SESSION['business_name'] ?? 'Loan Management System';

$activePage = 'settings';

$pageTitle = 'Settings - Loan Management';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars($pageTitle) ?>
    </title>

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

            const savedTheme =
                localStorage.getItem('bs-theme') || 'light';

            document.documentElement.setAttribute(
                'data-bs-theme',
                savedTheme
            );

        })();

    </script>

    <style>

        body {
            min-height: 100vh;
        }

        .settings-main {
            min-width: 0;
        }

        .settings-container {
            max-width: 1250px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-title {
            font-size: 1.65rem;
            font-weight: 700;
        }

        .page-subtitle {
            font-size: .82rem;
            color: var(--bs-secondary-color);
        }

        /*
        |--------------------------------------------------------------------------
        | TOP CATEGORY CARDS
        |--------------------------------------------------------------------------
        */

        .settings-category {
            display: flex;
            align-items: center;
            gap: 13px;

            height: 100%;

            padding: 16px;

            background: var(--bs-body-bg);

            border: 1px solid var(--bs-border-color);

            border-radius: 14px;

            text-decoration: none;

            color: var(--bs-body-color);

            transition:
                border-color .18s ease,
                background .18s ease,
                transform .18s ease;
        }

        .settings-category:hover {
            color: var(--bs-body-color);

            border-color:
                rgba(var(--bs-primary-rgb), .35);

            background:
                rgba(var(--bs-primary-rgb), .035);

            transform: translateY(-1px);
        }

        .settings-category-icon {
            width: 42px;
            height: 42px;
            min-width: 42px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 11px;

            background:
                rgba(var(--bs-primary-rgb), .10);

            color: var(--bs-primary);

            font-size: 18px;
        }

        .settings-category-title {
            font-size: .82rem;
            font-weight: 700;
        }

        .settings-category-description {
            margin-top: 2px;

            font-size: .68rem;

            color: var(--bs-secondary-color);

            line-height: 1.35;
        }

        /*
        |--------------------------------------------------------------------------
        | MAIN CARD
        |--------------------------------------------------------------------------
        */

        .settings-card {
            background: var(--bs-body-bg);

            border: 1px solid var(--bs-border-color);

            border-radius: 16px;

            overflow: hidden;
        }

        /*
        |--------------------------------------------------------------------------
        | SECTION
        |--------------------------------------------------------------------------
        */

        .settings-section {
            padding: 24px;

            border-bottom:
                1px solid var(--bs-border-color);
        }

        .settings-section:last-child {
            border-bottom: 0;
        }

        .settings-section-heading {
            display: flex;
            align-items: flex-start;
            gap: 13px;

            margin-bottom: 22px;
        }

        .settings-icon {
            width: 40px;
            height: 40px;
            min-width: 40px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 11px;

            background:
                rgba(var(--bs-primary-rgb), .10);

            color: var(--bs-primary);

            font-size: 17px;
        }

        .settings-section-title {
            font-size: .95rem;
            font-weight: 700;

            margin-bottom: 3px;
        }

        .settings-section-description {
            font-size: .72rem;

            color: var(--bs-secondary-color);

            line-height: 1.45;
        }

        /*
        |--------------------------------------------------------------------------
        | FORM
        |--------------------------------------------------------------------------
        */

        .form-label {
            font-size: .76rem;
            font-weight: 650;

            margin-bottom: 6px;
        }

        .form-control,
        .form-select {
            min-height: 40px;

            font-size: .82rem;

            border-radius: 9px;
        }

        textarea.form-control {
            min-height: auto;
        }

        .form-text {
            font-size: .68rem;

            color: var(--bs-secondary-color);

            margin-top: 5px;
        }

        /*
        |--------------------------------------------------------------------------
        | PAYMENT RULE CARDS
        |--------------------------------------------------------------------------
        */

        .payment-rule {
            height: 100%;

            padding: 16px;

            border:
                1px solid var(--bs-border-color);

            border-radius: 12px;

            background:
                rgba(var(--bs-secondary-rgb), .025);
        }

        .payment-rule-icon {
            width: 34px;
            height: 34px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 9px;

            background:
                rgba(var(--bs-primary-rgb), .10);

            color: var(--bs-primary);

            margin-bottom: 10px;
        }

        .payment-rule-title {
            font-size: .77rem;
            font-weight: 700;

            margin-bottom: 4px;
        }

        .payment-rule-text {
            font-size: .68rem;

            line-height: 1.45;

            color: var(--bs-secondary-color);
        }

        /*
        |--------------------------------------------------------------------------
        | LATE FEE PREVIEW
        |--------------------------------------------------------------------------
        */

        .late-fee-preview {
            padding: 15px;

            border-radius: 12px;

            background:
                rgba(var(--bs-warning-rgb), .08);

            border:
                1px solid rgba(var(--bs-warning-rgb), .20);
        }

        .late-fee-preview-title {
            font-size: .75rem;
            font-weight: 700;

            margin-bottom: 3px;
        }

        .late-fee-preview-text {
            font-size: .68rem;

            color: var(--bs-secondary-color);
        }

        /*
        |--------------------------------------------------------------------------
        | CURRENCY PREVIEW
        |--------------------------------------------------------------------------
        */

        .currency-preview {
            font-size: 1.45rem;
            font-weight: 700;
        }

        /*
        |--------------------------------------------------------------------------
        | SAVE BAR
        |--------------------------------------------------------------------------
        */

        .save-bar {
            position: sticky;

            bottom: 15px;

            z-index: 20;

            padding: 15px 24px;

            background:
                color-mix(
                    in srgb,
                    var(--bs-body-bg) 94%,
                    transparent
                );

            backdrop-filter: blur(10px);

            border-top:
                1px solid var(--bs-border-color);
        }

        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 991.98px) {

            .main-content {
                padding: 18px !important;
            }

            .settings-section {
                padding: 20px;
            }

            .save-bar {
                bottom: 0;
            }

        }

        @media (max-width: 575.98px) {

            .main-content {
                padding: 14px !important;
            }

            .page-title {
                font-size: 1.35rem;
            }

            .settings-section {
                padding: 16px;
            }

            .settings-section-heading {
                margin-bottom: 18px;
            }

            .save-bar {
                padding: 12px 16px;
            }

        }

    </style>

</head>

<body class="bg-body-tertiary">

<div class="d-flex flex-column flex-lg-row min-vh-100">

    <?php
    /*
    |--------------------------------------------------------------------------
    | LOAN SIDEBAR
    |--------------------------------------------------------------------------
    |
    | settings.php is located at:
    |
    | modules/LoanManagement/settings.php
    |
    | resources/partials is located at:
    |
    | resources/partials/
    |
    | Therefore:
    |
    | ../../resources/partials/loansidebar.php
    |
    */

    include __DIR__ . '/../../resources/partials/loansidebar.php';
    ?>

    <main class="settings-main flex-grow-1 bg-body-tertiary">

        <div class="main-content p-3 p-md-4">

            <div class="settings-container">

                <!-- =====================================================
                     PAGE HEADER
                ====================================================== -->

                <div class="page-header">

                    <div class="d-flex align-items-center gap-2 mb-1">

                        <div class="d-lg-none
                                    bg-primary
                                    bg-opacity-10
                                    text-primary
                                    rounded-3
                                    p-2">

                            <i class="bi bi-gear-fill"></i>

                        </div>

                        <h1 class="page-title mb-0">

                            Settings

                        </h1>

                    </div>

                    <div class="page-subtitle">

                        Configure how your loan management system
                        operates for

                        <span class="fw-semibold text-primary">

                            <?= htmlspecialchars($businessName) ?>

                        </span>

                    </div>

                </div>


                <!-- =====================================================
                     ALERTS
                ====================================================== -->

                <?php if ($success): ?>

                    <div
                        class="alert alert-success
                               border-0
                               shadow-sm
                               small
                               d-flex
                               align-items-center
                               gap-2"
                    >

                        <i class="bi bi-check-circle-fill"></i>

                        <span>

                            <?= htmlspecialchars($success) ?>

                        </span>

                    </div>

                <?php endif; ?>


                <?php if ($error): ?>

                    <div
                        class="alert alert-danger
                               border-0
                               shadow-sm
                               small
                               d-flex
                               align-items-center
                               gap-2"
                    >

                        <i class="bi bi-exclamation-triangle-fill"></i>

                        <span>

                            <?= htmlspecialchars($error) ?>

                        </span>

                    </div>

                <?php endif; ?>


                <!-- =====================================================
                     CATEGORY CARDS
                     NOT A SECOND SIDEBAR
                ====================================================== -->

                <div class="row g-3 mb-4">

                    <div class="col-sm-6 col-xl-3">

                        <a
                            href="#business-settings"
                            class="settings-category"
                        >

                            <div class="settings-category-icon">

                                <i class="bi bi-building"></i>

                            </div>

                            <div>

                                <div class="settings-category-title">

                                    Business

                                </div>

                                <div class="settings-category-description">

                                    Currency and contact information.

                                </div>

                            </div>

                        </a>

                    </div>


                    <div class="col-sm-6 col-xl-3">

                        <a
                            href="#loan-settings"
                            class="settings-category"
                        >

                            <div class="settings-category-icon">

                                <i class="bi bi-file-earmark-text"></i>

                            </div>

                            <div>

                                <div class="settings-category-title">

                                    Loans

                                </div>

                                <div class="settings-category-description">

                                    Default loan creation rules.

                                </div>

                            </div>

                        </a>

                    </div>


                    <div class="col-sm-6 col-xl-3">

                        <a
                            href="#payment-settings"
                            class="settings-category"
                        >

                            <div class="settings-category-icon">

                                <i class="bi bi-cash-stack"></i>

                            </div>

                            <div>

                                <div class="settings-category-title">

                                    Payments

                                </div>

                                <div class="settings-category-description">

                                    Late fees and payment behavior.

                                </div>

                            </div>

                        </a>

                    </div>


                    <div class="col-sm-6 col-xl-3">

                        <a
                            href="#system-settings"
                            class="settings-category"
                        >

                            <div class="settings-category-icon">

                                <i class="bi bi-sliders"></i>

                            </div>

                            <div>

                                <div class="settings-category-title">

                                    System

                                </div>

                                <div class="settings-category-description">

                                    Date and timezone preferences.

                                </div>

                            </div>

                        </a>

                    </div>

                </div>


                <!-- =====================================================
                     FORM
                ====================================================== -->

                <form method="POST">

                    <input
                        type="hidden"
                        name="save_settings"
                        value="1"
                    >


                    <div class="settings-card shadow-sm">


                        <!-- =================================================
                             BUSINESS SETTINGS
                        ================================================== -->

                        <section
                            id="business-settings"
                            class="settings-section"
                        >

                            <div class="settings-section-heading">

                                <div class="settings-icon">

                                    <i class="bi bi-building"></i>

                                </div>

                                <div>

                                    <div class="settings-section-title">

                                        Business Settings

                                    </div>

                                    <div class="settings-section-description">

                                        Configure the business information
                                        and currency used throughout the
                                        loan management system.

                                    </div>

                                </div>

                            </div>


                            <div class="row g-3">


                                <div class="col-md-6">

                                    <label class="form-label">

                                        Business Name

                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        value="<?= htmlspecialchars($businessName) ?>"
                                        disabled
                                    >

                                    <div class="form-text">

                                        Business name is managed from
                                        your business account.

                                    </div>

                                </div>


                                <div class="col-md-3">

                                    <label class="form-label">

                                        Currency

                                    </label>

                                    <select
                                        name="currency"
                                        class="form-select"
                                        id="currency"
                                    >

                                        <option
                                            value="PHP"
                                            <?= $settings['currency'] === 'PHP'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            PHP - Philippine Peso
                                        </option>

                                        <option
                                            value="USD"
                                            <?= $settings['currency'] === 'USD'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            USD - US Dollar
                                        </option>

                                        <option
                                            value="EUR"
                                            <?= $settings['currency'] === 'EUR'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            EUR - Euro
                                        </option>

                                        <option
                                            value="GBP"
                                            <?= $settings['currency'] === 'GBP'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            GBP - Pound Sterling
                                        </option>

                                    </select>

                                </div>


                                <div class="col-md-3">

                                    <label class="form-label">

                                        Currency Symbol

                                    </label>

                                    <input
                                        type="text"
                                        name="currency_symbol"
                                        id="currency_symbol"
                                        class="form-control"
                                        maxlength="5"
                                        value="<?= htmlspecialchars(
                                            $settings['currency_symbol']
                                        ) ?>"
                                    >

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">

                                        Business Phone

                                    </label>

                                    <input
                                        type="text"
                                        name="business_phone"
                                        class="form-control"
                                        value="<?= htmlspecialchars(
                                            $settings['business_phone'] ?? ''
                                        ) ?>"
                                        placeholder="09123456789"
                                    >

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">

                                        Business Email

                                    </label>

                                    <input
                                        type="email"
                                        name="business_email"
                                        class="form-control"
                                        value="<?= htmlspecialchars(
                                            $settings['business_email'] ?? ''
                                        ) ?>"
                                        placeholder="business@example.com"
                                    >

                                </div>


                                <div class="col-12">

                                    <label class="form-label">

                                        Business Address

                                    </label>

                                    <textarea
                                        name="business_address"
                                        class="form-control"
                                        rows="3"
                                        placeholder="Complete business address"
                                    ><?= htmlspecialchars(
                                        $settings['business_address'] ?? ''
                                    ) ?></textarea>

                                </div>


                                <div class="col-12">

                                    <div class="payment-rule">

                                        <div class="small text-muted mb-1">

                                            Currency Preview

                                        </div>

                                        <div
                                            class="currency-preview"
                                            id="currencyPreview"
                                        >

                                            <?= htmlspecialchars(
                                                $settings['currency_symbol']
                                            ) ?>

                                            10,000.00

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </section>


                        <!-- =================================================
                             LOAN SETTINGS
                        ================================================== -->

                        <section
                            id="loan-settings"
                            class="settings-section"
                        >

                            <div class="settings-section-heading">

                                <div class="settings-icon">

                                    <i class="bi bi-file-earmark-text"></i>

                                </div>

                                <div>

                                    <div class="settings-section-title">

                                        Loan Settings

                                    </div>

                                    <div class="settings-section-description">

                                        These values are used as defaults
                                        when creating a new loan. They can
                                        still be changed for individual loans.

                                    </div>

                                </div>

                            </div>


                            <div class="row g-3">


                                <div class="col-md-6">

                                    <label class="form-label">

                                        Default Interest Type

                                    </label>

                                    <select
                                        name="default_interest_type"
                                        class="form-select"
                                    >

                                        <option
                                            value="flat"
                                            <?= $settings['default_interest_type'] === 'flat'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Flat Interest
                                        </option>

                                        <option
                                            value="reducing_balance"
                                            <?= $settings['default_interest_type'] === 'reducing_balance'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Reducing Balance
                                        </option>

                                    </select>

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">

                                        Default Interest Rate (%)

                                    </label>

                                    <div class="input-group">

                                        <input
                                            type="number"
                                            name="default_interest_rate"
                                            class="form-control"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            value="<?= htmlspecialchars(
                                                $settings['default_interest_rate']
                                            ) ?>"
                                        >

                                        <span class="input-group-text">

                                            %

                                        </span>

                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">

                                        Default Payment Frequency

                                    </label>

                                    <select
                                        name="default_payment_frequency"
                                        class="form-select"
                                    >

                                        <option
                                            value="daily"
                                            <?= $settings['default_payment_frequency'] === 'daily'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Daily
                                        </option>

                                        <option
                                            value="weekly"
                                            <?= $settings['default_payment_frequency'] === 'weekly'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Weekly
                                        </option>

                                        <option
                                            value="biweekly"
                                            <?= $settings['default_payment_frequency'] === 'biweekly'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Biweekly
                                        </option>

                                        <option
                                            value="monthly"
                                            <?= $settings['default_payment_frequency'] === 'monthly'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Monthly
                                        </option>

                                    </select>

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">

                                        Default Payment Type

                                    </label>

                                    <select
                                        name="default_payment_type"
                                        class="form-select"
                                    >

                                        <option
                                            value="installment"
                                            <?= $settings['default_payment_type'] === 'installment'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Installment
                                        </option>

                                        <option
                                            value="lump_sum"
                                            <?= $settings['default_payment_type'] === 'lump_sum'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Lump Sum
                                        </option>

                                    </select>

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">

                                        Default Loan Term

                                    </label>

                                    <input
                                        type="number"
                                        name="default_loan_term"
                                        class="form-control"
                                        min="1"
                                        value="<?= (int)$settings['default_loan_term'] ?>"
                                    >

                                    <div class="form-text">

                                        Default number of payment periods.

                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">

                                        Grace Period

                                    </label>

                                    <div class="input-group">

                                        <input
                                            type="number"
                                            name="grace_period"
                                            class="form-control"
                                            min="0"
                                            value="<?= (int)$settings['grace_period'] ?>"
                                        >

                                        <span class="input-group-text">

                                            days

                                        </span>

                                    </div>

                                    <div class="form-text">

                                        Number of days after the due date
                                        before the payment becomes overdue.

                                    </div>

                                </div>


                                <div class="col-12">

                                    <div class="payment-rule">

                                        <div class="payment-rule-icon">

                                            <i class="bi bi-info-circle"></i>

                                        </div>

                                        <div class="payment-rule-title">

                                            How these defaults work

                                        </div>

                                        <div class="payment-rule-text">

                                            These settings are used when
                                            creating a new loan. Individual
                                            loans can have their own interest,
                                            frequency, payment type and term.

                                            Changing these settings will not
                                            modify existing loans.

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </section>


                        <!-- =================================================
                             PAYMENT SETTINGS
                        ================================================== -->

                        <section
                            id="payment-settings"
                            class="settings-section"
                        >

                            <div class="settings-section-heading">

                                <div class="settings-icon">

                                    <i class="bi bi-cash-stack"></i>

                                </div>

                                <div>

                                    <div class="settings-section-title">

                                        Payment Settings

                                    </div>

                                    <div class="settings-section-description">

                                        Configure how late payments are
                                        identified and how penalties should
                                        be calculated.

                                    </div>

                                </div>

                            </div>


                            <div class="row g-3">


                                <!-- LATE FEE TYPE -->

                                <div class="col-md-6">

                                    <label class="form-label">

                                        Late Fee Type

                                    </label>

                                    <select
                                        name="late_fee_type"
                                        id="lateFeeType"
                                        class="form-select"
                                    >

                                        <option
                                            value="fixed"
                                            <?= $settings['late_fee_type'] === 'fixed'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Fixed Amount
                                        </option>

                                        <option
                                            value="percentage"
                                            <?= $settings['late_fee_type'] === 'percentage'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Percentage
                                        </option>

                                    </select>

                                    <div class="form-text">

                                        Choose whether the late fee is a
                                        fixed amount or a percentage of the
                                        overdue payment.

                                    </div>

                                </div>


                                <!-- LATE FEE AMOUNT -->

                                <div class="col-md-6">

                                    <label class="form-label">

                                        Late Fee Amount

                                    </label>

                                    <div class="input-group">

                                        <input
                                            type="number"
                                            name="late_fee_amount"
                                            id="lateFeeAmount"
                                            class="form-control"
                                            min="0"
                                            step="0.01"
                                            value="<?= htmlspecialchars(
                                                $settings['late_fee_amount']
                                            ) ?>"
                                        >

                                        <span
                                            class="input-group-text"
                                            id="lateFeeSuffix"
                                        >

                                            <?= $settings['late_fee_type'] === 'percentage'
                                                ? '%'
                                                : htmlspecialchars(
                                                    $settings['currency_symbol']
                                                ) ?>

                                        </span>

                                    </div>

                                    <div
                                        class="form-text"
                                        id="lateFeeHelp"
                                    >

                                        <?php if (
                                            $settings['late_fee_type'] === 'percentage'
                                        ): ?>

                                            Example: 5 means 5% of the
                                            overdue amount.

                                        <?php else: ?>

                                            Example:
                                            <?= htmlspecialchars(
                                                $settings['currency_symbol']
                                            ) ?>
                                            50.00 will add a fixed
                                            <?= htmlspecialchars(
                                                $settings['currency_symbol']
                                            ) ?>
                                            50 late fee.

                                        <?php endif; ?>

                                    </div>

                                </div>


                                <!-- GRACE PERIOD SUMMARY -->

                                <div class="col-md-6">

                                    <div class="payment-rule">

                                        <div class="payment-rule-icon">

                                            <i class="bi bi-hourglass-split"></i>

                                        </div>

                                        <div class="payment-rule-title">

                                            Grace Period

                                        </div>

                                        <div class="payment-rule-text">

                                            Current setting:

                                            <strong>

                                                <?= (int)$settings['grace_period'] ?>

                                                day(s)

                                            </strong>

                                            after the scheduled due date.

                                            A payment should only be treated
                                            as overdue after this period.

                                        </div>

                                    </div>

                                </div>


                                <!-- REMAINING BALANCE -->

                                <div class="col-md-6">

                                    <div class="payment-rule">

                                        <div class="payment-rule-icon">

                                            <i class="bi bi-wallet2"></i>

                                        </div>

                                        <div class="payment-rule-title">

                                            Remaining Balance

                                        </div>

                                        <div class="payment-rule-text">

                                            Your payment processing should
                                            calculate the outstanding amount
                                            after every payment.

                                            <strong>

                                                Loan Balance − Payments
                                                + Applicable Penalties

                                            </strong>

                                            This allows partial payments to
                                            reduce the outstanding balance.

                                        </div>

                                    </div>

                                </div>


                                <!-- LATE FEE PREVIEW -->

                                <div class="col-12">

                                    <div class="late-fee-preview">

                                        <div class="late-fee-preview-title">

                                            <i class="bi bi-calculator me-1"></i>

                                            Late Fee Example

                                        </div>

                                        <div
                                            class="late-fee-preview-text"
                                            id="lateFeePreview"
                                        ></div>

                                    </div>

                                </div>


                                <!-- PAYMENT FLOW -->

                                <div class="col-12 mt-2">

                                    <div class="payment-rule">

                                        <div class="d-flex align-items-center gap-2 mb-3">

                                            <div class="payment-rule-icon mb-0">

                                                <i class="bi bi-diagram-3"></i>

                                            </div>

                                            <div class="payment-rule-title mb-0">

                                                Recommended Payment Flow

                                            </div>

                                        </div>


                                        <div class="row g-3">

                                            <div class="col-md-3">

                                                <div class="small fw-semibold">

                                                    1. Check Due Date

                                                </div>

                                                <div class="payment-rule-text">

                                                    Compare the scheduled
                                                    payment date with today's
                                                    date.

                                                </div>

                                            </div>


                                            <div class="col-md-3">

                                                <div class="small fw-semibold">

                                                    2. Check Grace Period

                                                </div>

                                                <div class="payment-rule-text">

                                                    Determine whether the
                                                    payment is officially
                                                    overdue.

                                                </div>

                                            </div>


                                            <div class="col-md-3">

                                                <div class="small fw-semibold">

                                                    3. Calculate Penalty

                                                </div>

                                                <div class="payment-rule-text">

                                                    Apply the configured fixed
                                                    or percentage late fee.

                                                </div>

                                            </div>


                                            <div class="col-md-3">

                                                <div class="small fw-semibold">

                                                    4. Update Balance

                                                </div>

                                                <div class="payment-rule-text">

                                                    Record the payment and
                                                    update the remaining
                                                    balance.

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </section>


                        <!-- =================================================
                             SYSTEM SETTINGS
                        ================================================== -->

                        <section
                            id="system-settings"
                            class="settings-section"
                        >

                            <div class="settings-section-heading">

                                <div class="settings-icon">

                                    <i class="bi bi-sliders"></i>

                                </div>

                                <div>

                                    <div class="settings-section-title">

                                        System Settings

                                    </div>

                                    <div class="settings-section-description">

                                        Configure how dates and times are
                                        displayed throughout the system.

                                    </div>

                                </div>

                            </div>


                            <div class="row g-3">


                                <div class="col-md-6">

                                    <label class="form-label">

                                        Date Format

                                    </label>

                                    <select
                                        name="date_format"
                                        class="form-select"
                                    >

                                        <option
                                            value="M d, Y"
                                            <?= $settings['date_format'] === 'M d, Y'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Aug 19, 2026
                                        </option>

                                        <option
                                            value="F d, Y"
                                            <?= $settings['date_format'] === 'F d, Y'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            August 19, 2026
                                        </option>

                                        <option
                                            value="Y-m-d"
                                            <?= $settings['date_format'] === 'Y-m-d'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            2026-08-19
                                        </option>

                                        <option
                                            value="d/m/Y"
                                            <?= $settings['date_format'] === 'd/m/Y'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            19/08/2026
                                        </option>

                                    </select>

                                    <div class="form-text">

                                        Used when displaying loan,
                                        payment and schedule dates.

                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <label class="form-label">

                                        Timezone

                                    </label>

                                    <select
                                        name="timezone"
                                        class="form-select"
                                    >

                                        <option
                                            value="Asia/Manila"
                                            <?= $settings['timezone'] === 'Asia/Manila'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Asia/Manila
                                        </option>

                                        <option
                                            value="Asia/Singapore"
                                            <?= $settings['timezone'] === 'Asia/Singapore'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Asia/Singapore
                                        </option>

                                        <option
                                            value="Asia/Tokyo"
                                            <?= $settings['timezone'] === 'Asia/Tokyo'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Asia/Tokyo
                                        </option>

                                        <option
                                            value="UTC"
                                            <?= $settings['timezone'] === 'UTC'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            UTC
                                        </option>

                                    </select>

                                </div>


                                <div class="col-12">

                                    <div class="payment-rule">

                                        <div class="payment-rule-icon">

                                            <i class="bi bi-clock"></i>

                                        </div>

                                        <div class="payment-rule-title">

                                            Current Timezone

                                        </div>

                                        <div class="payment-rule-text">

                                            Your system is configured to use:

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $settings['timezone']
                                                ) ?>

                                            </strong>

                                            <br>

                                            This should match the location
                                            where your loan business operates
                                            so payment due dates are
                                            calculated correctly.

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </section>


                        <!-- =================================================
                             SAVE BAR
                        ================================================== -->

                        <div class="save-bar">

                            <div
                                class="d-flex
                                       flex-column
                                       flex-sm-row
                                       justify-content-between
                                       align-items-sm-center
                                       gap-3"
                            >

                                <div>

                                    <div class="fw-semibold small">

                                        Save your changes

                                    </div>

                                    <div
                                        class="text-muted"
                                        style="font-size:.68rem;"
                                    >

                                        These settings apply to this
                                        business only.

                                    </div>

                                </div>


                                <button
                                    type="submit"
                                    class="btn btn-primary
                                           fw-semibold
                                           px-4"
                                >

                                    <i
                                        class="bi bi-check-lg me-1"
                                    ></i>

                                    Save Settings

                                </button>

                            </div>

                        </div>

                    </div>

                </form>

            </div>

        </div>

    </main>

</div>


<!-- =========================================================
     BOOTSTRAP
========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


<script>

document.addEventListener('DOMContentLoaded', function () {

    /*
    |--------------------------------------------------------------------------
    | CURRENCY SYMBOL
    |--------------------------------------------------------------------------
    */

    const currency =
        document.getElementById('currency');

    const currencySymbol =
        document.getElementById('currency_symbol');

    const currencyPreview =
        document.getElementById('currencyPreview');


    const currencySymbols = {
        PHP: '₱',
        USD: '$',
        EUR: '€',
        GBP: '£'
    };


    if (currency) {

        currency.addEventListener('change', function () {

            const selected =
                this.value;

            if (
                currencySymbol &&
                currencySymbol.value.trim() === ''
            ) {

                currencySymbol.value =
                    currencySymbols[selected] || '';

            }

            updateCurrencyPreview();

        });

    }


    if (currencySymbol) {

        currencySymbol.addEventListener(
            'input',
            updateCurrencyPreview
        );

    }


    function updateCurrencyPreview() {

        if (!currencyPreview || !currencySymbol) {
            return;
        }

        const symbol =
            currencySymbol.value.trim() || '₱';

        currencyPreview.textContent =
            symbol + ' 10,000.00';

        updateLateFeePreview();

    }


    /*
    |--------------------------------------------------------------------------
    | LATE FEE
    |--------------------------------------------------------------------------
    */

    const lateFeeType =
        document.getElementById('lateFeeType');

    const lateFeeAmount =
        document.getElementById('lateFeeAmount');

    const lateFeeSuffix =
        document.getElementById('lateFeeSuffix');

    const lateFeeHelp =
        document.getElementById('lateFeeHelp');

    const lateFeePreview =
        document.getElementById('lateFeePreview');


    function updateLateFeeDisplay() {

        if (
            !lateFeeType ||
            !lateFeeAmount ||
            !lateFeeSuffix
        ) {
            return;
        }

        const type =
            lateFeeType.value;

        const symbol =
            currencySymbol
                ? currencySymbol.value.trim() || '₱'
                : '₱';

        if (type === 'percentage') {

            lateFeeSuffix.textContent =
                '%';

            lateFeeHelp.textContent =
                'Example: 5 means 5% of the overdue amount.';

        } else {

            lateFeeSuffix.textContent =
                symbol;

            lateFeeHelp.textContent =
                'Example: ' +
                symbol +
                '50.00 will add a fixed ' +
                symbol +
                '50 late fee.';

        }

        updateLateFeePreview();

    }


    function updateLateFeePreview() {

        if (
            !lateFeePreview ||
            !lateFeeType ||
            !lateFeeAmount
        ) {
            return;
        }

        const type =
            lateFeeType.value;

        const amount =
            parseFloat(lateFeeAmount.value) || 0;

        const symbol =
            currencySymbol
                ? currencySymbol.value.trim() || '₱'
                : '₱';

        const samplePayment = 1000;

        let fee = 0;

        if (type === 'percentage') {

            fee =
                samplePayment *
                (amount / 100);

            lateFeePreview.textContent =
                'For a ' +
                symbol +
                '1,000.00 overdue payment, a ' +
                amount.toFixed(2) +
                '% late fee would be ' +
                symbol +
                fee.toFixed(2) +
                '.';

        } else {

            fee = amount;

            lateFeePreview.textContent =
                'For a ' +
                symbol +
                '1,000.00 overdue payment, a fixed late fee of ' +
                symbol +
                amount.toFixed(2) +
                ' would be added.';

        }

    }


    if (lateFeeType) {

        lateFeeType.addEventListener(
            'change',
            updateLateFeeDisplay
        );

    }


    if (lateFeeAmount) {

        lateFeeAmount.addEventListener(
            'input',
            updateLateFeePreview
        );

    }


    if (currencySymbol) {

        currencySymbol.addEventListener(
            'input',
            updateLateFeeDisplay
        );

    }


    updateCurrencyPreview();
    updateLateFeeDisplay();


    /*
    |--------------------------------------------------------------------------
    | SMOOTH SCROLL
    |--------------------------------------------------------------------------
    */

    document.querySelectorAll(
        '.settings-category'
    ).forEach(function (link) {

        link.addEventListener(
            'click',
            function (event) {

                const targetId =
                    this.getAttribute('href');

                const target =
                    document.querySelector(targetId);

                if (!target) {
                    return;
                }

                event.preventDefault();

                const offset =
                    15;

                const targetPosition =
                    target.getBoundingClientRect().top +
                    window.scrollY -
                    offset;

                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });

            }
        );

    });

});

</script>

</body>

</html>