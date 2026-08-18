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

$businessId = (int)($_SESSION['business_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$businessName = $_SESSION['business_name'] ?? 'Business';

if (!$businessId) {
    header('Location: index.php?page=select_business');
    exit;
}

$pageTitle = 'POS Reports';

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| DATE FILTER
|--------------------------------------------------------------------------
*/

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = date('Y-m-01');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = date('Y-m-d');
}

if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

$startDateTime = $startDate . ' 00:00:00';
$endDateTime = $endDate . ' 23:59:59';


/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$totalSales = 0;
$totalTransactions = 0;
$totalItems = 0;
$averageSale = 0;
$cashSales = 0;
$cardSales = 0;
$gcashSales = 0;
$otherSales = 0;

$dailySales = [];
$topProducts = [];
$recentSales = [];


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function tableExists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
            AND table_name = ?
        ");

        $stmt->execute([$table]);

        return (int)$stmt->fetchColumn() > 0;

    } catch (Throwable $e) {
        return false;
    }
}


function columnExists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
            AND table_name = ?
            AND column_name = ?
        ");

        $stmt->execute([
            $table,
            $column
        ]);

        return (int)$stmt->fetchColumn() > 0;

    } catch (Throwable $e) {
        return false;
    }
}


try {

    /*
    |--------------------------------------------------------------------------
    | DETERMINE POS TABLES
    |--------------------------------------------------------------------------
    */

    $salesTable = null;
    $itemsTable = null;

    $possibleSalesTables = [
        'pos_sales',
        'sales',
        'pos_transactions',
        'transactions'
    ];

    $possibleItemsTables = [
        'pos_sale_items',
        'sale_items',
        'pos_items',
        'transaction_items'
    ];

    foreach ($possibleSalesTables as $table) {

        if (tableExists($pdo, $table)) {
            $salesTable = $table;
            break;
        }
    }

    foreach ($possibleItemsTables as $table) {

        if (tableExists($pdo, $table)) {
            $itemsTable = $table;
            break;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | STOP IF POS TABLE DOES NOT EXIST
    |--------------------------------------------------------------------------
    */

    if (!$salesTable) {

        throw new Exception(
            'POS sales table was not found. Expected a table such as pos_sales.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DETERMINE COLUMNS
    |--------------------------------------------------------------------------
    */

    $saleIdColumn = 'id';

    if (!columnExists($pdo, $salesTable, 'id')) {
        throw new Exception(
            'The POS sales table does not contain an id column.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | BUSINESS COLUMN
    |--------------------------------------------------------------------------
    */

    $businessColumn = null;

    foreach ([
        'business_id',
        'businessId'
    ] as $column) {

        if (columnExists($pdo, $salesTable, $column)) {
            $businessColumn = $column;
            break;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DATE COLUMN
    |--------------------------------------------------------------------------
    */

    $dateColumn = null;

    foreach ([
        'created_at',
        'sale_date',
        'transaction_date',
        'date',
        'created'
    ] as $column) {

        if (columnExists($pdo, $salesTable, $column)) {
            $dateColumn = $column;
            break;
        }
    }

    if (!$dateColumn) {

        throw new Exception(
            'No sale date column was found in the POS sales table.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | TOTAL COLUMN
    |--------------------------------------------------------------------------
    */

    $totalColumn = null;

    foreach ([
        'total',
        'grand_total',
        'total_amount',
        'amount',
        'net_total'
    ] as $column) {

        if (columnExists($pdo, $salesTable, $column)) {
            $totalColumn = $column;
            break;
        }
    }

    if (!$totalColumn) {

        throw new Exception(
            'No total amount column was found in the POS sales table.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PAYMENT METHOD COLUMN
    |--------------------------------------------------------------------------
    */

    $paymentColumn = null;

    foreach ([
        'payment_method',
        'payment_type',
        'method'
    ] as $column) {

        if (columnExists($pdo, $salesTable, $column)) {
            $paymentColumn = $column;
            break;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SALE NUMBER COLUMN
    |--------------------------------------------------------------------------
    */

    $referenceColumn = null;

    foreach ([
        'invoice_number',
        'receipt_number',
        'sale_number',
        'transaction_number',
        'reference_number'
    ] as $column) {

        if (columnExists($pdo, $salesTable, $column)) {
            $referenceColumn = $column;
            break;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | BASE WHERE
    |--------------------------------------------------------------------------
    */

    $where = [];
    $params = [];

    if ($businessColumn) {

        $where[] = "s.`$businessColumn` = ?";
        $params[] = $businessId;
    }

    $where[] = "s.`$dateColumn` BETWEEN ? AND ?";

    $params[] = $startDateTime;
    $params[] = $endDateTime;

    /*
    |--------------------------------------------------------------------------
    | IMPORTANT
    |--------------------------------------------------------------------------
    | We intentionally DO NOT use:
    |
    | s.status = 'completed'
    |
    | because your previous POS database returned:
    |
    | Unknown column 'status'
    |
    */

    $whereSql = implode(' AND ', $where);


    /*
    |--------------------------------------------------------------------------
    | TOTAL SALES
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(s.`$totalColumn`), 0)
        FROM `$salesTable` s
        WHERE $whereSql
    ");

    $stmt->execute($params);

    $totalSales = (float)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TOTAL TRANSACTIONS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*)
        FROM `$salesTable` s
        WHERE $whereSql
    ");

    $stmt->execute($params);

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
    | PAYMENT METHOD SUMMARY
    |--------------------------------------------------------------------------
    */

    if ($paymentColumn) {

        $stmt = $pdo->prepare("
            SELECT
                LOWER(TRIM(COALESCE(s.`$paymentColumn`, 'other'))) AS payment_method,
                COALESCE(SUM(s.`$totalColumn`), 0) AS total
            FROM `$salesTable` s
            WHERE $whereSql
            GROUP BY LOWER(TRIM(COALESCE(s.`$paymentColumn`, 'other')))
            ORDER BY total DESC
        ");

        $stmt->execute($params);

        $paymentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($paymentRows as $payment) {

            $method = strtolower(
                trim($payment['payment_method'] ?? 'other')
            );

            $amount = (float)$payment['total'];

            if (in_array($method, [
                'cash',
                'cash payment'
            ], true)) {

                $cashSales += $amount;

            } elseif (in_array($method, [
                'card',
                'credit card',
                'debit card'
            ], true)) {

                $cardSales += $amount;

            } elseif (in_array($method, [
                'gcash',
                'g cash'
            ], true)) {

                $gcashSales += $amount;

            } else {

                $otherSales += $amount;
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | ITEMS SOLD
    |--------------------------------------------------------------------------
    */

    if ($itemsTable && tableExists($pdo, $itemsTable)) {

        $itemSaleIdColumn = null;

        foreach ([
            'sale_id',
            'transaction_id',
            'pos_sale_id'
        ] as $column) {

            if (columnExists(
                $pdo,
                $itemsTable,
                $column
            )) {

                $itemSaleIdColumn = $column;
                break;
            }
        }


        $quantityColumn = null;

        foreach ([
            'quantity',
            'qty',
            'quantity_sold'
        ] as $column) {

            if (columnExists(
                $pdo,
                $itemsTable,
                $column
            )) {

                $quantityColumn = $column;
                break;
            }
        }


        if (
            $itemSaleIdColumn &&
            $quantityColumn
        ) {

            $itemBusinessColumn = null;

            if (
                $businessColumn &&
                columnExists(
                    $pdo,
                    $itemsTable,
                    $businessColumn
                )
            ) {

                $itemBusinessColumn =
                    $businessColumn;
            }


            $itemWhere = [];

            $itemParams = [];

            if ($itemBusinessColumn) {

                $itemWhere[] =
                    "i.`$itemBusinessColumn` = ?";

                $itemParams[] =
                    $businessId;
            }


            $itemWhere[] =
                "s.`$dateColumn` BETWEEN ? AND ?";

            $itemParams[] =
                $startDateTime;

            $itemParams[] =
                $endDateTime;


            $itemWhereSql =
                implode(' AND ', $itemWhere);


            $stmt = $pdo->prepare("
                SELECT
                    COALESCE(SUM(i.`$quantityColumn`), 0)
                FROM `$itemsTable` i
                INNER JOIN `$salesTable` s
                    ON s.id = i.`$itemSaleIdColumn`
                WHERE $itemWhereSql
            ");

            $stmt->execute($itemParams);

            $totalItems =
                (float)$stmt->fetchColumn();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DAILY SALES
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            DATE(s.`$dateColumn`) AS sale_day,
            COUNT(*) AS transactions,
            COALESCE(SUM(s.`$totalColumn`), 0) AS total
        FROM `$salesTable` s
        WHERE $whereSql
        GROUP BY DATE(s.`$dateColumn`)
        ORDER BY sale_day ASC
    ");

    $stmt->execute($params);

    $dailySales =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | TOP PRODUCTS
    |--------------------------------------------------------------------------
    */

    if ($itemsTable) {

        $itemSaleIdColumn = null;

        foreach ([
            'sale_id',
            'transaction_id',
            'pos_sale_id'
        ] as $column) {

            if (columnExists(
                $pdo,
                $itemsTable,
                $column
            )) {

                $itemSaleIdColumn = $column;
                break;
            }
        }


        $quantityColumn = null;

        foreach ([
            'quantity',
            'qty',
            'quantity_sold'
        ] as $column) {

            if (columnExists(
                $pdo,
                $itemsTable,
                $column
            )) {

                $quantityColumn = $column;
                break;
            }
        }


        $productNameColumn = null;

        foreach ([
            'product_name',
            'name',
            'item_name'
        ] as $column) {

            if (columnExists(
                $pdo,
                $itemsTable,
                $column
            )) {

                $productNameColumn = $column;
                break;
            }
        }


        $itemTotalColumn = null;

        foreach ([
            'total',
            'subtotal',
            'line_total',
            'amount'
        ] as $column) {

            if (columnExists(
                $pdo,
                $itemsTable,
                $column
            )) {

                $itemTotalColumn = $column;
                break;
            }
        }


        if (
            $itemSaleIdColumn &&
            $quantityColumn &&
            $productNameColumn
        ) {

            $stmt = $pdo->prepare("
                SELECT
                    i.`$productNameColumn` AS product_name,
                    SUM(i.`$quantityColumn`) AS quantity_sold,
                    " .
                    ($itemTotalColumn
                        ? "COALESCE(SUM(i.`$itemTotalColumn`), 0)"
                        : "0"
                    ) . " AS sales_total
                FROM `$itemsTable` i
                INNER JOIN `$salesTable` s
                    ON s.id = i.`$itemSaleIdColumn`
                WHERE $whereSql
                GROUP BY i.`$productNameColumn`
                ORDER BY quantity_sold DESC
                LIMIT 10
            ");

            $stmt->execute($params);

            $topProducts =
                $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RECENT SALES
    |--------------------------------------------------------------------------
    */

    $referenceSelect = $referenceColumn
        ? "s.`$referenceColumn` AS reference_number,"
        : "NULL AS reference_number,";

    $paymentSelect = $paymentColumn
        ? "s.`$paymentColumn` AS payment_method,"
        : "NULL AS payment_method,";

    $stmt = $pdo->prepare("
        SELECT
            s.id,
            $referenceSelect
            s.`$dateColumn` AS sale_date,
            s.`$totalColumn` AS total,
            $paymentSelect
            s.*
        FROM `$salesTable` s
        WHERE $whereSql
        ORDER BY s.`$dateColumn` DESC
        LIMIT 50
    ");

    $stmt->execute($params);

    $recentSales =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (Throwable $e) {

    $error = $e->getMessage();

    error_log(
        'POS Reports Error: ' .
        $e->getMessage()
    );
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
    transition: transform .15s ease,
                box-shadow .15s ease;
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
    overflow: hidden;
}

.report-card-header {
    padding: 20px 24px;
}

.report-table {
    width: 100%;
}

.report-table th {
    font-size: .72rem;
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

.payment-badge {
    display: inline-flex;
    align-items: center;
    padding: .35rem .65rem;
    border-radius: 50rem;
    font-size: .7rem;
    font-weight: 700;
    text-transform: capitalize;
}

.payment-cash {
    background: rgba(25,135,84,.12);
    color: var(--bs-success);
}

.payment-card {
    background: rgba(13,110,253,.12);
    color: var(--bs-primary);
}

.payment-gcash {
    background: rgba(111,66,193,.12);
    color: #6f42c1;
}

.payment-other {
    background: rgba(108,117,125,.12);
    color: var(--bs-secondary-color);
}

.chart-container {
    position: relative;
    height: 320px;
}

.empty-state {
    padding: 50px 20px;
}

@media (max-width: 575.98px) {

    .report-card-header {
        padding: 16px;
    }

    .chart-container {
        height: 250px;
    }

}

</style>

</head>

<body class="bg-body-tertiary">

<div class="d-flex flex-column flex-lg-row min-vh-100">

<?php

/*
|--------------------------------------------------------------------------
| POS SIDEBAR
|--------------------------------------------------------------------------
*/

$sidebarPaths = [
    __DIR__ . '/../../../resources/partials/POSSidebar.php',
    __DIR__ . '/../../../resources/partials/PosSidebar.php',
    __DIR__ . '/../../../resources/partials/posSidebar.php'
];

foreach ($sidebarPaths as $sidebarPath) {

    if (file_exists($sidebarPath)) {

        include $sidebarPath;

        break;
    }
}

?>

<main class="pos-main flex-grow-1">

<div class="p-3 p-md-4">

<!-- HEADER -->

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

<div>

<h2 class="fw-bold mb-1">

<i class="bi bi-bar-chart-line text-primary me-2"></i>

POS Reports

</h2>

<p class="text-muted mb-0">

Sales reports for

<span class="fw-semibold text-primary">

<?= htmlspecialchars($businessName) ?>

</span>

</p>

</div>

</div>


<!-- ERROR -->

<?php if ($error !== ''): ?>

<div class="alert alert-danger border-0 shadow-sm rounded-3">

<i class="bi bi-exclamation-triangle-fill me-2"></i>

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>


<!-- DATE FILTER -->

<div class="card report-card shadow-sm mb-4">

<div class="card-body p-3">

<form
    method="GET"
    class="row g-2 align-items-end"
>

<input
    type="hidden"
    name="page"
    value="pos_reports"
>

<div class="col-12 col-md-4">

<label class="form-label small fw-semibold">
Start Date
</label>

<input
    type="date"
    name="start_date"
    class="form-control"
    value="<?= htmlspecialchars($startDate) ?>"
>

</div>


<div class="col-12 col-md-4">

<label class="form-label small fw-semibold">
End Date
</label>

<input
    type="date"
    name="end_date"
    class="form-control"
    value="<?= htmlspecialchars($endDate) ?>"
>

</div>


<div class="col-12 col-md-4">

<button
    type="submit"
    class="btn btn-primary w-100 fw-bold"
>

<i class="bi bi-funnel me-1"></i>

Generate Report

</button>

</div>

</form>

</div>

</div>


<!-- SUMMARY CARDS -->

<div class="row g-3 mb-4">

<div class="col-12 col-md-6 col-xl-3">

<div class="card summary-card shadow-sm h-100">

<div class="card-body p-4">

<div class="d-flex justify-content-between align-items-start">

<div>

<div class="text-muted small fw-semibold">
Total Sales
</div>

<div class="fs-3 fw-bold mt-2">

₱<?= number_format($totalSales, 2) ?>

</div>

<div class="small text-muted mt-1">

<?= htmlspecialchars($startDate) ?>

to

<?= htmlspecialchars($endDate) ?>

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

<div class="text-muted small fw-semibold">
Transactions
</div>

<div class="fs-3 fw-bold mt-2">

<?= number_format($totalTransactions) ?>

</div>

<div class="small text-muted mt-1">
Completed sales recorded
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

<div class="text-muted small fw-semibold">
Items Sold
</div>

<div class="fs-3 fw-bold mt-2">

<?= number_format($totalItems, 2) ?>

</div>

<div class="small text-muted mt-1">
Total quantity sold
</div>

</div>

<div class="summary-icon bg-warning bg-opacity-10 text-warning">

<i class="bi bi-box-seam fs-4"></i>

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

<div class="text-muted small fw-semibold">
Average Sale
</div>

<div class="fs-3 fw-bold mt-2">

₱<?= number_format($averageSale, 2) ?>

</div>

<div class="small text-muted mt-1">
Average per transaction
</div>

</div>

<div class="summary-icon bg-info bg-opacity-10 text-info">

<i class="bi bi-calculator fs-4"></i>

</div>

</div>

</div>

</div>

</div>

</div>


<!-- PAYMENT SUMMARY -->

<div class="row g-3 mb-4">

<div class="col-12 col-md-6 col-lg-3">

<div class="card summary-card shadow-sm h-100">

<div class="card-body p-4">

<div class="text-muted small fw-semibold">
Cash Sales
</div>

<div class="fs-4 fw-bold mt-2">

₱<?= number_format($cashSales, 2) ?>

</div>

</div>

</div>

</div>


<div class="col-12 col-md-6 col-lg-3">

<div class="card summary-card shadow-sm h-100">

<div class="card-body p-4">

<div class="text-muted small fw-semibold">
Card Sales
</div>

<div class="fs-4 fw-bold mt-2">

₱<?= number_format($cardSales, 2) ?>

</div>

</div>

</div>

</div>


<div class="col-12 col-md-6 col-lg-3">

<div class="card summary-card shadow-sm h-100">

<div class="card-body p-4">

<div class="text-muted small fw-semibold">
GCash Sales
</div>

<div class="fs-4 fw-bold mt-2">

₱<?= number_format($gcashSales, 2) ?>

</div>

</div>

</div>

</div>


<div class="col-12 col-md-6 col-lg-3">

<div class="card summary-card shadow-sm h-100">

<div class="card-body p-4">

<div class="text-muted small fw-semibold">
Other Payments
</div>

<div class="fs-4 fw-bold mt-2">

₱<?= number_format($otherSales, 2) ?>

</div>

</div>

</div>

</div>

</div>


<!-- DAILY SALES -->

<div class="card report-card shadow-sm mb-4">

<div class="report-card-header">

<h5 class="fw-bold mb-1">

Daily Sales

</h5>

<p class="text-muted small mb-0">

Sales performance during the selected period.

</p>

</div>

<div class="card-body">

<div class="chart-container">

<canvas id="salesChart"></canvas>

</div>

</div>

</div>


<!-- TOP PRODUCTS -->

<div class="card report-card shadow-sm mb-4">

<div class="report-card-header">

<h5 class="fw-bold mb-1">

Top-Selling Products

</h5>

<p class="text-muted small mb-0">

Products with the highest quantity sold.

</p>

</div>

<?php if (!$topProducts): ?>

<div class="empty-state text-center text-muted">

<i class="bi bi-box-seam display-6 opacity-50"></i>

<div class="fw-semibold mt-3">

No product sales found.

</div>

</div>

<?php else: ?>

<div class="table-responsive">

<table class="report-table table table-hover mb-0">

<thead class="table-light">

<tr>

<th class="ps-4">
#
</th>

<th>
Product
</th>

<th>
Quantity Sold
</th>

<th class="pe-4 text-end">
Sales
</th>

</tr>

</thead>

<tbody>

<?php foreach ($topProducts as $index => $product): ?>

<tr>

<td class="ps-4">

<?= $index + 1 ?>

</td>

<td>

<span class="fw-semibold">

<?= htmlspecialchars(
    $product['product_name'] ?? '-'
) ?>

</span>

</td>

<td>

<?= number_format(
    (float)($product['quantity_sold'] ?? 0),
    2
) ?>

</td>

<td class="pe-4 text-end amount">

₱<?= number_format(
    (float)($product['sales_total'] ?? 0),
    2
) ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</div>


<!-- SALES HISTORY -->

<div class="card report-card shadow-sm">

<div class="report-card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">

<div>

<h5 class="fw-bold mb-1">

Sales History

</h5>

<p class="text-muted small mb-0">

Transactions from the selected date range.

</p>

</div>

<div class="small text-muted">

<?= number_format(count($recentSales)) ?>

records shown

</div>

</div>


<?php if (!$recentSales): ?>

<div class="empty-state text-center text-muted">

<i class="bi bi-receipt display-6 opacity-50"></i>

<div class="fw-semibold mt-3">

No sales found.

</div>

<div class="small">

Try selecting a different date range.

</div>

</div>

<?php else: ?>

<div class="table-responsive">

<table class="report-table table table-hover mb-0">

<thead class="table-light text-uppercase text-muted">

<tr>

<th class="ps-4">
ID
</th>

<th>
Reference
</th>

<th>
Date
</th>

<th>
Payment
</th>

<th class="pe-4 text-end">
Total
</th>

</tr>

</thead>

<tbody>

<?php foreach ($recentSales as $sale): ?>

<?php

$paymentMethod =
    strtolower(
        trim(
            $sale['payment_method'] ?? 'other'
        )
    );

if (
    $paymentMethod === 'cash' ||
    $paymentMethod === 'cash payment'
) {

    $paymentClass = 'payment-cash';

} elseif (
    $paymentMethod === 'card' ||
    $paymentMethod === 'credit card' ||
    $paymentMethod === 'debit card'
) {

    $paymentClass = 'payment-card';

} elseif (
    $paymentMethod === 'gcash' ||
    $paymentMethod === 'g cash'
) {

    $paymentClass = 'payment-gcash';

} else {

    $paymentClass = 'payment-other';
}

?>

<tr>

<td class="ps-4">

<span class="text-muted">

#<?= (int)$sale['id'] ?>

</span>

</td>


<td>

<?= htmlspecialchars(
    $sale['reference_number'] ?? '-'
) ?>

</td>


<td>

<?php

$displayDate =
    !empty($sale['sale_date'])
        ? date(
            'M d, Y h:i A',
            strtotime($sale['sale_date'])
        )
        : '-';

?>

<?= htmlspecialchars($displayDate) ?>

</td>


<td>

<span class="payment-badge <?= $paymentClass ?>">

<?= htmlspecialchars(
    $sale['payment_method'] ?? 'Other'
) ?>

</span>

</td>


<td class="pe-4 text-end amount">

₱<?= number_format(
    (float)($sale['total'] ?? 0),
    2
) ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</div>

</div>

</main>

</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

const dailySales =
    <?= json_encode($dailySales) ?>;

const labels =
    dailySales.map(function (row) {

        const date =
            new Date(
                row.sale_day + 'T00:00:00'
            );

        return date.toLocaleDateString(
            undefined,
            {
                month: 'short',
                day: 'numeric'
            }
        );

    });

const values =
    dailySales.map(function (row) {

        return parseFloat(
            row.total || 0
        );

    });


const ctx =
    document.getElementById(
        'salesChart'
    );


if (ctx) {

    new Chart(ctx, {

        type: 'line',

        data: {

            labels: labels,

            datasets: [{

                label: 'Sales',

                data: values,

                tension: 0.35,

                fill: true,

                borderWidth: 2,

                pointRadius: 4,

                pointHoverRadius: 6

            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            interaction: {

                intersect: false,

                mode: 'index'

            },

            plugins: {

                legend: {

                    display: false

                },

                tooltip: {

                    callbacks: {

                        label: function (context) {

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

                        callback: function (value) {

                            return '₱' +
                                Number(value)
                                    .toLocaleString();

                        }

                    }

                }

            }

        }

    });

}

</script>

</body>

</html>