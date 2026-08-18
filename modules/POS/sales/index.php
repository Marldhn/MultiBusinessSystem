<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/db.php';

if (!isset($pdo)) {
    $pdo = Database::getConnection();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$businessId = (int)($_SESSION['business_id'] ?? 0);
$businessName = $_SESSION['business_name'] ?? 'Business';

if (!$businessId) {
    header('Location: index.php?page=select_business');
    exit;
}

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| FIND POS SALES TABLE
|--------------------------------------------------------------------------
*/

$possibleSalesTables = [
    'pos_sales',
    'pos_transactions',
    'sales',
    'pos_orders',
    'orders'
];

$salesTable = null;

try {
    $placeholders = implode(',', array_fill(0, count($possibleSalesTables), '?'));

    $stmt = $pdo->prepare("
        SELECT TABLE_NAME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN ($placeholders)
        LIMIT 1
    ");

    $stmt->execute($possibleSalesTables);

    $foundTable = $stmt->fetchColumn();

    if ($foundTable) {
        $salesTable = $foundTable;
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$totalSales = 0;
$totalTransactions = 0;
$averageSale = 0;
$todaySales = 0;
$monthSales = 0;

$dailySales = [];
$paymentSummary = [];
$recentSales = [];

$columns = [];

/*
|--------------------------------------------------------------------------
| GET TABLE COLUMNS
|--------------------------------------------------------------------------
*/

if ($salesTable) {

    try {

        $stmt = $pdo->query("
            SHOW COLUMNS FROM `$salesTable`
        ");

        $columnRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columnRows as $column) {
            $columns[] = $column['Field'];
        }

    } catch (Throwable $e) {

        $error = $e->getMessage();
        $salesTable = null;
    }
}

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function findColumn(array $columns, array $possible)
{
    foreach ($possible as $column) {

        if (in_array($column, $columns, true)) {
            return $column;
        }
    }

    return null;
}

/*
|--------------------------------------------------------------------------
| DETECT COLUMNS
|--------------------------------------------------------------------------
*/

$idColumn = findColumn(
    $columns,
    [
        'id',
        'sale_id',
        'transaction_id',
        'order_id'
    ]
);

$businessColumn = findColumn(
    $columns,
    [
        'business_id'
    ]
);

$totalColumn = findColumn(
    $columns,
    [
        'total_amount',
        'grand_total',
        'total',
        'amount',
        'net_total',
        'sale_total'
    ]
);

$dateColumn = findColumn(
    $columns,
    [
        'created_at',
        'sale_date',
        'transaction_date',
        'order_date',
        'date',
        'created_date'
    ]
);

$paymentColumn = findColumn(
    $columns,
    [
        'payment_method',
        'payment_type',
        'method'
    ]
);

$invoiceColumn = findColumn(
    $columns,
    [
        'invoice_number',
        'invoice_no',
        'transaction_number',
        'reference_number',
        'order_number'
    ]
);

$customerColumn = findColumn(
    $columns,
    [
        'customer_name',
        'customer',
        'buyer_name'
    ]
);

/*
|--------------------------------------------------------------------------
| REPORT DATA
|--------------------------------------------------------------------------
*/

if ($salesTable && $totalColumn) {

    try {

        /*
        |--------------------------------------------------------------------------
        | BUSINESS CONDITION
        |--------------------------------------------------------------------------
        */

        $businessWhere = '';
        $businessParams = [];

        if ($businessColumn) {

            $businessWhere = "
                WHERE `$businessColumn` = ?
            ";

            $businessParams[] = $businessId;
        }

        /*
        |--------------------------------------------------------------------------
        | TOTAL SALES
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                COALESCE(SUM(`$totalColumn`), 0)
            FROM `$salesTable`
            $businessWhere
        ");

        $stmt->execute($businessParams);

        $totalSales = (float)$stmt->fetchColumn();

        /*
        |--------------------------------------------------------------------------
        | TOTAL TRANSACTIONS
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                COUNT(*)
            FROM `$salesTable`
            $businessWhere
        ");

        $stmt->execute($businessParams);

        $totalTransactions = (int)$stmt->fetchColumn();

        /*
        |--------------------------------------------------------------------------
        | AVERAGE SALE
        |--------------------------------------------------------------------------
        */

        if ($totalTransactions > 0) {

            $averageSale =
                $totalSales / $totalTransactions;
        }

        /*
        |--------------------------------------------------------------------------
        | TODAY SALES
        |--------------------------------------------------------------------------
        */

        if ($dateColumn) {

            $todayWhere = [];

            if ($businessColumn) {
                $todayWhere[] = "`$businessColumn` = ?";
            }

            $todayWhere[] = "DATE(`$dateColumn`) = CURDATE()";

            $todayParams = [];

            if ($businessColumn) {
                $todayParams[] = $businessId;
            }

            $stmt = $pdo->prepare("
                SELECT
                    COALESCE(SUM(`$totalColumn`), 0)
                FROM `$salesTable`
                WHERE " . implode(' AND ', $todayWhere)
            );

            $stmt->execute($todayParams);

            $todaySales = (float)$stmt->fetchColumn();
        }

        /*
        |--------------------------------------------------------------------------
        | CURRENT MONTH SALES
        |--------------------------------------------------------------------------
        */

        if ($dateColumn) {

            $monthWhere = [];

            if ($businessColumn) {
                $monthWhere[] = "`$businessColumn` = ?";
            }

            $monthWhere[] = "
                YEAR(`$dateColumn`) = YEAR(CURDATE())
                AND MONTH(`$dateColumn`) = MONTH(CURDATE())
            ";

            $monthParams = [];

            if ($businessColumn) {
                $monthParams[] = $businessId;
            }

            $stmt = $pdo->prepare("
                SELECT
                    COALESCE(SUM(`$totalColumn`), 0)
                FROM `$salesTable`
                WHERE " . implode(' AND ', $monthWhere)
            );

            $stmt->execute($monthParams);

            $monthSales = (float)$stmt->fetchColumn();
        }

        /*
        |--------------------------------------------------------------------------
        | LAST 7 DAYS
        |--------------------------------------------------------------------------
        */

        if ($dateColumn) {

            $dailyWhere = [];

            if ($businessColumn) {
                $dailyWhere[] = "`$businessColumn` = ?";
            }

            $dailyWhere[] = "
                `$dateColumn` >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            ";

            $dailyParams = [];

            if ($businessColumn) {
                $dailyParams[] = $businessId;
            }

            $stmt = $pdo->prepare("
                SELECT
                    DATE(`$dateColumn`) AS sale_day,
                    COALESCE(SUM(`$totalColumn`), 0) AS total
                FROM `$salesTable`
                WHERE " . implode(' AND ', $dailyWhere) . "
                GROUP BY DATE(`$dateColumn`)
                ORDER BY sale_day ASC
            ");

            $stmt->execute($dailyParams);

            $dailySalesRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($dailySalesRows as $row) {

                $dailySales[$row['sale_day']] =
                    (float)$row['total'];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | PAYMENT METHOD SUMMARY
        |--------------------------------------------------------------------------
        */

        if ($paymentColumn) {

            $paymentWhere = [];

            if ($businessColumn) {
                $paymentWhere[] = "`$businessColumn` = ?";
            }

            $paymentParams = [];

            if ($businessColumn) {
                $paymentParams[] = $businessId;
            }

            $stmt = $pdo->prepare("
                SELECT
                    COALESCE(`$paymentColumn`, 'Unknown') AS payment_method,
                    COUNT(*) AS transaction_count,
                    COALESCE(SUM(`$totalColumn`), 0) AS total
                FROM `$salesTable`
                " . (
                    $paymentWhere
                        ? 'WHERE ' . implode(' AND ', $paymentWhere)
                        : ''
                ) . "
                GROUP BY `$paymentColumn`
                ORDER BY total DESC
            ");

            $stmt->execute($paymentParams);

            $paymentSummary =
                $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        /*
        |--------------------------------------------------------------------------
        | RECENT SALES
        |--------------------------------------------------------------------------
        */

        $selectFields = [];

        if ($idColumn) {
            $selectFields[] =
                "`$idColumn` AS sale_id";
        } else {
            $selectFields[] =
                "NULL AS sale_id";
        }

        if ($invoiceColumn) {
            $selectFields[] =
                "`$invoiceColumn` AS invoice_number";
        } else {
            $selectFields[] =
                "NULL AS invoice_number";
        }

        if ($customerColumn) {
            $selectFields[] =
                "`$customerColumn` AS customer_name";
        } else {
            $selectFields[] =
                "NULL AS customer_name";
        }

        $selectFields[] =
            "`$totalColumn` AS total_amount";

        if ($paymentColumn) {
            $selectFields[] =
                "`$paymentColumn` AS payment_method";
        } else {
            $selectFields[] =
                "NULL AS payment_method";
        }

        if ($dateColumn) {
            $selectFields[] =
                "`$dateColumn` AS sale_date";
        } else {
            $selectFields[] =
                "NULL AS sale_date";
        }

        $recentWhere = '';

        if ($businessColumn) {

            $recentWhere =
                "WHERE `$businessColumn` = :business_id";
        }

        $orderBy = $dateColumn
            ? "`$dateColumn` DESC"
            : ($idColumn ? "`$idColumn` DESC" : "1");

        $stmt = $pdo->prepare("
            SELECT
                " . implode(", ", $selectFields) . "
            FROM `$salesTable`
            $recentWhere
            ORDER BY $orderBy
            LIMIT 20
        ");

        if ($businessColumn) {
            $stmt->bindValue(
                ':business_id',
                $businessId,
                PDO::PARAM_INT
            );
        }

        $stmt->execute();

        $recentSales =
            $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    catch (Throwable $e) {

        $error = $e->getMessage();

        error_log(
            'POS Reports Error: ' .
            $e->getMessage()
        );
    }
}

/*
|--------------------------------------------------------------------------
| PREPARE LAST 7 DAYS
|--------------------------------------------------------------------------
*/

$chartLabels = [];
$chartValues = [];

for ($i = 6; $i >= 0; $i--) {

    $date = date(
        'Y-m-d',
        strtotime("-$i days")
    );

    $chartLabels[] =
        date('M d', strtotime($date));

    $chartValues[] =
        $dailySales[$date] ?? 0;
}

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
POS Reports - <?= htmlspecialchars($businessName) ?>
</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
    rel="stylesheet"
>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>

body {
    min-height: 100vh;
    overflow-x: hidden;
}

.pos-main {
    min-width: 0;
    width: 100%;
}

.summary-card {
    border: 0;
    border-radius: 16px;
    transition: .15s ease;
}

.summary-card:hover {
    transform: translateY(-2px);
}

.summary-icon {
    width: 50px;
    height: 50px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.report-card {
    border: 0;
    border-radius: 16px;
}

.report-card-header {
    padding: 20px 24px;
}

.report-table th {
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    white-space: nowrap;
}

.report-table td {
    vertical-align: middle;
    white-space: nowrap;
}

.amount {
    font-weight: 700;
}

.empty-state {
    padding: 55px 20px;
}

.payment-badge {
    display: inline-flex;
    align-items: center;
    padding: .35rem .65rem;
    border-radius: 50rem;
    font-size: .72rem;
    font-weight: 700;
    background: rgba(13,110,253,.1);
    color: var(--bs-primary);
}

@media(max-width:575.98px) {

    .pos-main > .p-3,
    .pos-main > .p-md-4 {
        padding: 14px !important;
    }

    .report-card-header {
        padding: 15px;
    }

}

</style>

</head>

<body class="bg-body-tertiary">

<div class="d-flex flex-column flex-lg-row min-vh-100">

<?php

$sidebarPath =
    __DIR__ .
    '/../../../resources/partials/POSSidebar.php';

if (file_exists($sidebarPath)) {
    include $sidebarPath;
}

?>

<main class="pos-main flex-grow-1">

<div class="p-3 p-md-4">

<!-- =====================================================
     HEADER
===================================================== -->

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

<div>

<div class="d-flex align-items-center gap-2 mb-1">

<div class="d-lg-none bg-primary bg-opacity-10 text-primary rounded-3 p-2">

<i class="bi bi-bar-chart"></i>

</div>

<h2 class="fw-bold mb-0">
POS Reports
</h2>

</div>

<p class="text-muted small mb-0">

Sales reports for

<span class="fw-semibold text-primary">

<?= htmlspecialchars($businessName) ?>

</span>

</p>

</div>

</div>


<!-- =====================================================
     ERROR
===================================================== -->

<?php if ($error): ?>

<div class="alert alert-danger border-0 shadow-sm rounded-3">

<i class="bi bi-exclamation-triangle-fill me-2"></i>

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>


<!-- =====================================================
     NO SALES TABLE
===================================================== -->

<?php if (!$salesTable): ?>

<div class="card report-card shadow-sm">

<div class="card-body empty-state text-center">

<i class="bi bi-database-x display-5 text-danger opacity-50"></i>

<h5 class="fw-bold mt-3">
POS Sales Table Not Found
</h5>

<p class="text-muted small mb-0">

The report could not find a POS sales table.

</p>

</div>

</div>

<?php else: ?>


<!-- =====================================================
     SUMMARY CARDS
===================================================== -->

<div class="row g-3 mb-4">

<div class="col-12 col-md-6 col-xl-3">

<div class="card summary-card shadow-sm h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start">

<div>

<div class="text-muted small fw-semibold mb-2">
Total Sales
</div>

<div class="fs-3 fw-bold">
₱<?= number_format($totalSales, 2) ?>
</div>

</div>

<div class="summary-icon bg-primary bg-opacity-10 text-primary">

<i class="bi bi-cash-stack fs-4"></i>

</div>

</div>

</div>

</div>

</div>


<div class="col-12 col-md-6 col-xl-3">

<div class="card summary-card shadow-sm h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start">

<div>

<div class="text-muted small fw-semibold mb-2">
Transactions
</div>

<div class="fs-3 fw-bold">
<?= number_format($totalTransactions) ?>
</div>

</div>

<div class="summary-icon bg-success bg-opacity-10 text-success">

<i class="bi bi-receipt fs-4"></i>

</div>

</div>

</div>

</div>

</div>


<div class="col-12 col-md-6 col-xl-3">

<div class="card summary-card shadow-sm h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start">

<div>

<div class="text-muted small fw-semibold mb-2">
Average Sale
</div>

<div class="fs-3 fw-bold">
₱<?= number_format($averageSale, 2) ?>
</div>

</div>

<div class="summary-icon bg-info bg-opacity-10 text-info">

<i class="bi bi-calculator fs-4"></i>

</div>

</div>

</div>

</div>

</div>


<div class="col-12 col-md-6 col-xl-3">

<div class="card summary-card shadow-sm h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start">

<div>

<div class="text-muted small fw-semibold mb-2">
Today's Sales
</div>

<div class="fs-3 fw-bold text-success">
₱<?= number_format($todaySales, 2) ?>
</div>

</div>

<div class="summary-icon bg-warning bg-opacity-10 text-warning">

<i class="bi bi-calendar-day fs-4"></i>

</div>

</div>

</div>

</div>

</div>

</div>


<!-- =====================================================
     MONTH SALES
===================================================== -->

<div class="card report-card shadow-sm mb-4">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-center">

<div>

<div class="text-muted small fw-semibold">
Current Month Sales
</div>

<div class="fs-3 fw-bold text-primary">

₱<?= number_format($monthSales, 2) ?>

</div>

</div>

<i class="bi bi-calendar3 fs-2 text-primary opacity-50"></i>

</div>

</div>

</div>


<!-- =====================================================
     SALES CHART
===================================================== -->

<div class="card report-card shadow-sm mb-4">

<div class="report-card-header">

<h5 class="fw-bold mb-1">
Sales - Last 7 Days
</h5>

<p class="text-muted small mb-0">
Daily POS sales
</p>

</div>

<div class="card-body pt-0">

<div style="height:320px;">

<canvas id="salesChart"></canvas>

</div>

</div>

</div>


<div class="row g-4">


<!-- =====================================================
     PAYMENT SUMMARY
===================================================== -->

<div class="col-12 col-xl-4">

<div class="card report-card shadow-sm h-100">

<div class="report-card-header">

<h5 class="fw-bold mb-1">
Payment Methods
</h5>

<p class="text-muted small mb-0">
Sales by payment method
</p>

</div>

<div class="table-responsive">

<table class="table report-table mb-0">

<thead class="table-light">

<tr>

<th class="ps-4">
Method
</th>

<th>
Transactions
</th>

<th class="text-end pe-4">
Total
</th>

</tr>

</thead>

<tbody>

<?php if (!$paymentSummary): ?>

<tr>

<td
    colspan="3"
    class="text-center text-muted py-4"
>

No payment data available.

</td>

</tr>

<?php else: ?>

<?php foreach ($paymentSummary as $payment): ?>

<tr>

<td class="ps-4">

<span class="payment-badge">

<?= htmlspecialchars(
    $payment['payment_method'] ?: 'Unknown'
) ?>

</span>

</td>

<td>

<?= number_format(
    (int)$payment['transaction_count']
) ?>

</td>

<td class="text-end pe-4 amount">

₱<?= number_format(
    (float)$payment['total'],
    2
) ?>

</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

</tbody>

</table>

</div>

</div>

</div>


<!-- =====================================================
     RECENT SALES
===================================================== -->

<div class="col-12 col-xl-8">

<div class="card report-card shadow-sm h-100">

<div class="report-card-header">

<h5 class="fw-bold mb-1">
Recent Sales
</h5>

<p class="text-muted small mb-0">
Latest POS transactions
</p>

</div>

<div class="table-responsive">

<table class="table report-table table-hover mb-0">

<thead class="table-light">

<tr>

<th class="ps-4">
Reference
</th>

<th>
Customer
</th>

<th>
Payment
</th>

<th>
Date
</th>

<th class="text-end pe-4">
Amount
</th>

</tr>

</thead>

<tbody>

<?php if (!$recentSales): ?>

<tr>

<td
    colspan="5"
    class="text-center text-muted py-5"
>

<i class="bi bi-receipt display-6 opacity-50"></i>

<div class="mt-2">
No sales found.
</div>

</td>

</tr>

<?php else: ?>

<?php foreach ($recentSales as $sale): ?>

<tr>

<td class="ps-4">

<span class="fw-semibold">

<?= htmlspecialchars(
    $sale['invoice_number']
        ?: (
            $sale['sale_id']
                ? '#' . $sale['sale_id']
                : '-'
        )
) ?>

</span>

</td>

<td>

<?= htmlspecialchars(
    $sale['customer_name'] ?: 'Walk-in Customer'
) ?>

</td>

<td>

<?php if (!empty($sale['payment_method'])): ?>

<span class="payment-badge">

<?= htmlspecialchars(
    $sale['payment_method']
) ?>

</span>

<?php else: ?>

<span class="text-muted">
-
</span>

<?php endif; ?>

</td>

<td>

<?php

if (!empty($sale['sale_date'])) {

    echo htmlspecialchars(
        date(
            'M d, Y h:i A',
            strtotime($sale['sale_date'])
        )
    );

} else {

    echo '-';
}

?>

</td>

<td class="text-end pe-4 amount">

₱<?= number_format(
    (float)$sale['total_amount'],
    2
) ?>

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

<?php endif; ?>

</div>

</main>

</div>


<script>

const chartLabels =
    <?= json_encode($chartLabels) ?>;

const chartValues =
    <?= json_encode($chartValues) ?>;

const chartElement =
    document.getElementById('salesChart');

if (chartElement) {

    new Chart(
        chartElement,
        {
            type: 'line',

            data: {

                labels: chartLabels,

                datasets: [
                    {
                        label: 'Sales',

                        data: chartValues,

                        tension: 0.35,

                        fill: true,

                        borderWidth: 2,

                        pointRadius: 4
                    }
                ]
            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                plugins: {

                    legend: {
                        display: false
                    },

                    tooltip: {

                        callbacks: {

                            label: function(context) {

                                return '₱' +
                                    Number(
                                        context.raw || 0
                                    ).toLocaleString(
                                        undefined,
                                        {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        }
                                    );
                            }
                        }
                    }
                },

                scales: {

                    y: {

                        beginAtZero: true,

                        ticks: {

                            callback: function(value) {

                                return '₱' +
                                    Number(value)
                                        .toLocaleString();
                            }
                        }
                    }

                }

            }
        }
    );
}

</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>