
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

$pageTitle = 'New Sale';
$activePage = 'pos_sales';

$error = '';
$success = '';

$customers = [];
$products = [];

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function generateInvoiceNumber(PDO $pdo, int $businessId): string
{
    $prefix = 'INV-' . date('Ymd') . '-';

    $stmt = $pdo->prepare("
        SELECT invoice_number
        FROM pos_sales
        WHERE business_id = ?
        AND invoice_number LIKE ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([
        $businessId,
        $prefix . '%'
    ]);

    $lastInvoice = $stmt->fetchColumn();

    if ($lastInvoice) {
        $lastNumber = (int)substr(
            $lastInvoice,
            strlen($prefix)
        );

        $nextNumber = $lastNumber + 1;
    } else {
        $nextNumber = 1;
    }

    return $prefix . str_pad(
        $nextNumber,
        4,
        '0',
        STR_PAD_LEFT
    );
}

/*
|--------------------------------------------------------------------------
| LOAD CUSTOMERS
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Only customers belonging to the current business are loaded.
|
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            first_name,
            middle_name,
            last_name,
            email,
            phone
        FROM pos_customers
        WHERE business_id = ?
        AND status = 'active'
        ORDER BY first_name ASC, last_name ASC
    ");

    $stmt->execute([
        $businessId
    ]);

    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $customers = [];
}

/*
|--------------------------------------------------------------------------
| LOAD PRODUCTS
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Only products belonging to the current business are loaded.
|
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.name,
            p.sku,
            p.barcode,
            p.cost_price,
            p.selling_price,
            p.wholesale_price,
            p.minimum_stock,
            p.current_stock,
            p.status,
            c.name AS category_name,
            b.name AS brand_name,
            u.name AS unit_name,
            u.abbreviation AS unit_abbreviation
        FROM pos_products p

        LEFT JOIN pos_categories c
            ON c.id = p.category_id
            AND c.business_id = p.business_id

        LEFT JOIN pos_brands b
            ON b.id = p.brand_id
            AND b.business_id = p.business_id

        LEFT JOIN pos_units u
            ON u.id = p.unit_id
            AND u.business_id = p.business_id

        WHERE p.business_id = ?
        AND p.status = 'active'

        ORDER BY p.name ASC
    ");

    $stmt->execute([
        $businessId
    ]);

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $products = [];
}

/*
|--------------------------------------------------------------------------
| COMPLETE SALE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'complete_sale') {

        $customerId = !empty($_POST['customer_id'])
            ? (int)$_POST['customer_id']
            : null;

        $discount = max(
            0,
            (float)($_POST['discount'] ?? 0)
        );

        $tax = max(
            0,
            (float)($_POST['tax'] ?? 0)
        );

        $amountPaid = max(
            0,
            (float)($_POST['amount_paid'] ?? 0)
        );

        $paymentMethod =
            $_POST['payment_method'] ?? 'cash';

        $notes =
            trim($_POST['notes'] ?? '');

        $cartJson =
            $_POST['cart'] ?? '[]';

        $cart = json_decode(
            $cartJson,
            true
        );

        if (!is_array($cart)) {
            $cart = [];
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDATE PAYMENT METHOD
        |--------------------------------------------------------------------------
        */

        $allowedPaymentMethods = [
            'cash',
            'card',
            'gcash',
            'bank_transfer',
            'other'
        ];

        if (!in_array(
            $paymentMethod,
            $allowedPaymentMethods,
            true
        )) {

            $error = 'Invalid payment method.';
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDATE CART
        |--------------------------------------------------------------------------
        */

        if (!$error && empty($cart)) {
            $error = 'Please add at least one product to the sale.';
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDATE CUSTOMER
        |--------------------------------------------------------------------------
        */

        if (!$error && $customerId) {

            try {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM pos_customers
                    WHERE id = ?
                    AND business_id = ?
                    AND status = 'active'
                    LIMIT 1
                ");

                $stmt->execute([
                    $customerId,
                    $businessId
                ]);

                if (!$stmt->fetchColumn()) {

                    $error =
                        'The selected customer does not belong to this business.';
                }

            } catch (Throwable $e) {

                $error =
                    'Unable to validate the selected customer.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | PROCESS SALE
        |--------------------------------------------------------------------------
        */

        if (!$error) {

            try {

                $pdo->beginTransaction();

                $validatedItems = [];
                $subtotal = 0;

                /*
                |--------------------------------------------------------------------------
                | VALIDATE EVERY PRODUCT
                |--------------------------------------------------------------------------
                |
                | IMPORTANT:
                | The business_id condition prevents another business'
                | products from being submitted manually.
                |
                */

                foreach ($cart as $item) {

                    $productId =
                        (int)($item['product_id'] ?? 0);

                    $quantity =
                        (float)($item['quantity'] ?? 0);

                    if (
                        $productId <= 0 ||
                        $quantity <= 0
                    ) {

                        throw new Exception(
                            'Invalid product or quantity.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | LOCK PRODUCT ROW
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $pdo->prepare("
                        SELECT
                            id,
                            name,
                            sku,
                            selling_price,
                            current_stock
                        FROM pos_products
                        WHERE id = ?
                        AND business_id = ?
                        AND status = 'active'
                        FOR UPDATE
                    ");

                    $stmt->execute([
                        $productId,
                        $businessId
                    ]);

                    $product =
                        $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$product) {

                        throw new Exception(
                            'One of the selected products is not available.'
                        );
                    }

                    $currentStock =
                        (float)$product['current_stock'];

                    if ($quantity > $currentStock) {

                        throw new Exception(
                            'Not enough stock for: ' .
                            $product['name'] .
                            '. Available stock: ' .
                            number_format(
                                $currentStock,
                                2
                            )
                        );
                    }

                    $unitPrice =
                        (float)$product['selling_price'];

                    $itemTotal =
                        $unitPrice * $quantity;

                    $subtotal += $itemTotal;

                    $validatedItems[] = [
                        'product_id' => $productId,
                        'product_name' => $product['name'],
                        'sku' => $product['sku'],
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total' => $itemTotal,
                        'previous_stock' => $currentStock,
                        'new_stock' =>
                            $currentStock - $quantity
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | CALCULATE TOTAL
                |--------------------------------------------------------------------------
                */

                $totalAmount =
                    $subtotal
                    - $discount
                    + $tax;

                $totalAmount =
                    max(0, $totalAmount);

                if ($amountPaid > $totalAmount) {

                    $changeAmount =
                        $amountPaid - $totalAmount;

                } else {

                    $changeAmount = 0;
                }

                $paymentStatus = 'unpaid';

                if ($amountPaid >= $totalAmount) {

                    $paymentStatus = 'paid';

                } elseif ($amountPaid > 0) {

                    $paymentStatus = 'partial';
                }

                /*
                |--------------------------------------------------------------------------
                | GENERATE INVOICE
                |--------------------------------------------------------------------------
                */

                $invoiceNumber =
                    generateInvoiceNumber(
                        $pdo,
                        $businessId
                    );

                /*
                |--------------------------------------------------------------------------
                | INSERT SALE
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    INSERT INTO pos_sales (
                        business_id,
                        customer_id,
                        invoice_number,
                        sale_date,
                        subtotal,
                        discount,
                        tax,
                        total_amount,
                        amount_paid,
                        change_amount,
                        payment_status,
                        sale_status,
                        notes,
                        created_by,
                        created_at
                    )
                    VALUES (
                        ?,
                        ?,
                        ?,
                        NOW(),
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        'completed',
                        ?,
                        ?,
                        NOW()
                    )
                ");

                $stmt->execute([
                    $businessId,
                    $customerId,
                    $invoiceNumber,
                    $subtotal,
                    $discount,
                    $tax,
                    $totalAmount,
                    $amountPaid,
                    $changeAmount,
                    $paymentStatus,
                    $notes ?: null,
                    $userId
                ]);

                $saleId =
                    (int)$pdo->lastInsertId();

                /*
                |--------------------------------------------------------------------------
                | INSERT SALE ITEMS
                |--------------------------------------------------------------------------
                */

                $itemStmt = $pdo->prepare("
                    INSERT INTO pos_sale_items (
                        sale_id,
                        product_id,
                        product_name,
                        sku,
                        quantity,
                        unit_price,
                        discount,
                        total,
                        created_at
                    )
                    VALUES (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        0,
                        ?,
                        NOW()
                    )
                ");

                /*
                |--------------------------------------------------------------------------
                | UPDATE STOCK
                |--------------------------------------------------------------------------
                */

                $stockStmt = $pdo->prepare("
                    UPDATE pos_products
                    SET current_stock = ?
                    WHERE id = ?
                    AND business_id = ?
                ");

                /*
                |--------------------------------------------------------------------------
                | STOCK MOVEMENT
                |--------------------------------------------------------------------------
                */

                $movementStmt = $pdo->prepare("
                    INSERT INTO pos_stock_movements (
                        business_id,
                        product_id,
                        movement_type,
                        quantity,
                        unit_cost,
                        previous_stock,
                        new_stock,
                        reference_type,
                        reference_id,
                        notes,
                        created_by,
                        created_at
                    )
                    VALUES (
                        ?,
                        ?,
                        'sale',
                        ?,
                        0,
                        ?,
                        ?,
                        'pos_sale',
                        ?,
                        ?,
                        ?,
                        NOW()
                    )
                ");

                foreach ($validatedItems as $item) {

                    /*
                    |--------------------------------------------------------------------------
                    | SALE ITEM
                    |--------------------------------------------------------------------------
                    */

                    $itemStmt->execute([
                        $saleId,
                        $item['product_id'],
                        $item['product_name'],
                        $item['sku'],
                        $item['quantity'],
                        $item['unit_price'],
                        $item['total']
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | UPDATE PRODUCT STOCK
                    |--------------------------------------------------------------------------
                    */

                    $stockStmt->execute([
                        $item['new_stock'],
                        $item['product_id'],
                        $businessId
                    ]);

                    if ($stockStmt->rowCount() < 1) {

                        throw new Exception(
                            'Unable to update product stock.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | STOCK MOVEMENT
                    |--------------------------------------------------------------------------
                    */

                    $movementStmt->execute([
                        $businessId,
                        $item['product_id'],
                        $item['quantity'],
                        $item['previous_stock'],
                        $item['new_stock'],
                        $saleId,
                        'POS Sale ' . $invoiceNumber,
                        $userId
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | PAYMENT
                |--------------------------------------------------------------------------
                */

                if ($amountPaid > 0) {

                    $paymentStmt = $pdo->prepare("
                        INSERT INTO pos_payments (
                            business_id,
                            sale_id,
                            payment_method,
                            amount,
                            reference_number,
                            payment_date,
                            notes,
                            created_by,
                            created_at
                        )
                        VALUES (
                            ?,
                            ?,
                            ?,
                            ?,
                            NULL,
                            NOW(),
                            ?,
                            ?,
                            NOW()
                        )
                    ");

                    $paymentStmt->execute([
                        $businessId,
                        $saleId,
                        $paymentMethod,
                        $amountPaid,
                        $notes ?: null,
                        $userId
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | COMMIT
                |--------------------------------------------------------------------------
                */

                $pdo->commit();

                $success =
                    'Sale completed successfully. Invoice: ' .
                    $invoiceNumber;

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error =
                    $e->getMessage();
            }
        }
    }
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

.pos-main {
    min-width: 0;
    width: 100%;
}

.pos-card {
    border: 0;
    border-radius: 16px;
}

.pos-card-header {
    padding: 18px 20px;
    border-bottom: 1px solid var(--bs-border-color);
}

.product-search {
    height: 48px;
}

.product-card {
    border: 1px solid var(--bs-border-color);
    border-radius: 12px;
    cursor: pointer;
    transition: .15s ease;
    height: 100%;
}

.product-card:hover {
    border-color: var(--bs-primary);
    background: var(--bs-primary-bg-subtle);
    transform: translateY(-1px);
}

.product-card.disabled {
    opacity: .5;
    cursor: not-allowed;
}

.product-name {
    font-weight: 700;
    font-size: .9rem;
}

.product-meta {
    font-size: .7rem;
    color: var(--bs-secondary-color);
}

.product-price {
    font-weight: 800;
    color: var(--bs-primary);
}

.stock-label {
    font-size: .68rem;
}

.cart-table th {
    font-size: .7rem;
    text-transform: uppercase;
    color: var(--bs-secondary-color);
    white-space: nowrap;
}

.cart-table td {
    vertical-align: middle;
}

.qty-control {
    width: 105px;
}

.qty-control input {
    text-align: center;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 10px;
}

.summary-total {
    font-size: 1.5rem;
    font-weight: 800;
}

.payment-method {
    cursor: pointer;
}

.payment-method input {
    display: none;
}

.payment-method-content {
    border: 1px solid var(--bs-border-color);
    border-radius: 10px;
    padding: 12px;
    text-align: center;
    transition: .15s ease;
}

.payment-method input:checked +
.payment-method-content {
    border-color: var(--bs-primary);
    background: var(--bs-primary-bg-subtle);
    color: var(--bs-primary);
}

.empty-cart {
    padding: 60px 20px;
}

@media (max-width: 767.98px) {

    .pos-card {
        border-radius: 12px;
    }

    .product-card {
        padding: 12px !important;
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

<main class="pos-main flex-grow-1 bg-body-tertiary">

<div class="p-3 p-md-4">

<!-- =========================================================
     HEADER
========================================================= -->

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

    <div>

        <h2 class="fw-bold mb-1">

            <i class="bi bi-cart-plus me-2 text-primary"></i>

            New Sale

        </h2>

        <div class="text-muted small">

            Create a new transaction for

            <span class="fw-semibold text-primary">

                <?= htmlspecialchars($businessName) ?>

            </span>

        </div>

    </div>

    <a
        href="index.php?page=pos_dashboard"
        class="btn btn-outline-secondary rounded-3"
    >

        <i class="bi bi-arrow-left me-1"></i>

        Dashboard

    </a>

</div>


<!-- =========================================================
     ALERTS
========================================================= -->

<?php if ($error): ?>

<div class="alert alert-danger alert-dismissible fade show">

    <i class="bi bi-exclamation-triangle me-2"></i>

    <?= htmlspecialchars($error) ?>

    <button
        type="button"
        class="btn-close"
        data-bs-dismiss="alert"
    ></button>

</div>

<?php endif; ?>


<?php if ($success): ?>

<div class="alert alert-success alert-dismissible fade show">

    <i class="bi bi-check-circle me-2"></i>

    <?= htmlspecialchars($success) ?>

    <a
        href="index.php?page=pos_sales"
        class="alert-link ms-2"
    >
        Start another sale
    </a>

    <button
        type="button"
        class="btn-close"
        data-bs-dismiss="alert"
    ></button>

</div>

<?php endif; ?>


<form
    method="POST"
    id="saleForm"
>

<input
    type="hidden"
    name="action"
    value="complete_sale"
>

<input
    type="hidden"
    name="cart"
    id="cartInput"
>


<div class="row g-4">

<!-- =========================================================
     PRODUCTS
========================================================= -->

<div class="col-12 col-xl-7">

<div class="card pos-card shadow-sm bg-body">

<div class="pos-card-header">

    <div class="d-flex justify-content-between align-items-center gap-3">

        <div>

            <h5 class="fw-bold mb-1">

                Products

            </h5>

            <div class="text-muted small">

                Select a product to add it to the cart.

            </div>

        </div>

        <span class="badge bg-primary-subtle text-primary">

            <?= count($products) ?> Products

        </span>

    </div>

</div>

<div class="card-body">

<div class="input-group mb-4">

    <span class="input-group-text">

        <i class="bi bi-search"></i>

    </span>

    <input
        type="text"
        id="productSearch"
        class="form-control product-search"
        placeholder="Search product, SKU or barcode..."
        autocomplete="off"
    >

</div>


<div
    id="productGrid"
    class="row g-3"
>

<?php if (!$products): ?>

<div class="col-12">

    <div class="text-center py-5">

        <i class="bi bi-box-seam display-5 text-muted"></i>

        <div class="fw-semibold mt-3">

            No products available

        </div>

        <div class="text-muted small">

            Create products first before making a sale.

        </div>

    </div>

</div>

<?php else: ?>

<?php foreach ($products as $product): ?>

<?php

$productStock =
    (float)$product['current_stock'];

$isOutOfStock =
    $productStock <= 0;

?>

<div
    class="col-12 col-sm-6 col-lg-4 product-wrapper"
    data-search="<?= htmlspecialchars(
        strtolower(
            ($product['name'] ?? '') . ' ' .
            ($product['sku'] ?? '') . ' ' .
            ($product['barcode'] ?? '')
        )
    ) ?>"
>

<div
    class="product-card p-3 <?= $isOutOfStock ? 'disabled' : '' ?>"
    data-product-id="<?= (int)$product['id'] ?>"
    data-product-name="<?= htmlspecialchars(
        $product['name'],
        ENT_QUOTES
    ) ?>"
    data-product-sku="<?= htmlspecialchars(
        $product['sku'] ?? '',
        ENT_QUOTES
    ) ?>"
    data-product-price="<?= (float)$product['selling_price'] ?>"
    data-product-stock="<?= $productStock ?>"
>

<div class="d-flex justify-content-between gap-2">

    <div class="product-name">

        <?= htmlspecialchars(
            $product['name']
        ) ?>

    </div>

    <i class="bi bi-plus-circle text-primary"></i>

</div>

<?php if (!empty($product['sku'])): ?>

<div class="product-meta mt-1">

    SKU:
    <?= htmlspecialchars($product['sku']) ?>

</div>

<?php endif; ?>

<?php if (!empty($product['barcode'])): ?>

<div class="product-meta">

    Barcode:
    <?= htmlspecialchars($product['barcode']) ?>

</div>

<?php endif; ?>

<div class="d-flex justify-content-between align-items-end mt-3">

    <div>

        <div class="product-price">

            ₱<?= number_format(
                (float)$product['selling_price'],
                2
            ) ?>

        </div>

        <div class="stock-label text-muted">

            <?php if ($isOutOfStock): ?>

                <span class="text-danger fw-bold">
                    Out of stock
                </span>

            <?php else: ?>

                Stock:
                <?= number_format(
                    $productStock,
                    2
                ) ?>

            <?php endif; ?>

        </div>

    </div>

    <?php if (!empty($product['category_name'])): ?>

    <span class="badge bg-secondary-subtle text-secondary">

        <?= htmlspecialchars(
            $product['category_name']
        ) ?>

    </span>

    <?php endif; ?>

</div>

</div>

</div>

<?php endforeach; ?>

<?php endif; ?>

</div>


<div
    id="noSearchResults"
    class="text-center py-5 d-none"
>

    <i class="bi bi-search display-6 text-muted"></i>

    <div class="fw-semibold mt-3">

        No products found

    </div>

    <div class="text-muted small">

        Try another product name, SKU, or barcode.

    </div>

</div>

</div>

</div>

</div>


<!-- =========================================================
     CART
========================================================= -->

<div class="col-12 col-xl-5">

<div class="card pos-card shadow-sm bg-body">

<div class="pos-card-header">

<div class="d-flex justify-content-between align-items-center">

<div>

    <h5 class="fw-bold mb-1">

        Current Sale

    </h5>

    <div class="text-muted small">

        Review items before completing the transaction.

    </div>

</div>

<button
    type="button"
    id="clearCartBtn"
    class="btn btn-sm btn-outline-danger rounded-3"
>

    <i class="bi bi-trash me-1"></i>

    Clear

</button>

</div>

</div>


<div class="card-body p-0">

<div
    id="emptyCart"
    class="empty-cart text-center"
>

    <i class="bi bi-cart3 display-5 text-muted opacity-50"></i>

    <div class="fw-semibold mt-3">

        Cart is empty

    </div>

    <div class="text-muted small">

        Select products to start the sale.

    </div>

</div>


<div
    id="cartContainer"
    class="table-responsive d-none"
>

<table class="table cart-table mb-0">

<thead class="table-light">

<tr>

    <th class="ps-3">
        Product
    </th>

    <th>
        Qty
    </th>

    <th class="text-end pe-3">
        Total
    </th>

</tr>

</thead>

<tbody id="cartBody"></tbody>

</table>

</div>

</div>


<!-- =========================================================
     CUSTOMER
========================================================= -->

<div class="card-body border-top">

<label class="form-label fw-semibold">

    Customer

</label>

<select
    name="customer_id"
    class="form-select"
>

<option value="">

    Walk-in Customer

</option>

<?php foreach ($customers as $customer): ?>

<option value="<?= (int)$customer['id'] ?>">

<?= htmlspecialchars(
    trim(
        $customer['first_name'] . ' ' .
        ($customer['middle_name'] ?? '') . ' ' .
        $customer['last_name']
    )
) ?>

<?php if (!empty($customer['phone'])): ?>

 — <?= htmlspecialchars(
    $customer['phone']
) ?>

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<!-- =========================================================
     TOTALS
========================================================= -->

<div class="card-body border-top">

<div class="summary-row">

    <span class="text-muted">

        Subtotal

    </span>

    <span
        id="subtotalDisplay"
        class="fw-semibold"
    >

        ₱0.00

    </span>

</div>


<div class="mb-3">

<label class="form-label small fw-semibold">

    Discount

</label>

<div class="input-group">

<span class="input-group-text">

    ₱

</span>

<input
    type="number"
    name="discount"
    id="discount"
    class="form-control"
    value="0"
    min="0"
    step="0.01"
>

</div>

</div>


<div class="mb-3">

<label class="form-label small fw-semibold">

    Tax

</label>

<div class="input-group">

<span class="input-group-text">

    ₱

</span>

<input
    type="number"
    name="tax"
    id="tax"
    class="form-control"
    value="0"
    min="0"
    step="0.01"
>

</div>

</div>


<div class="summary-row border-top pt-3">

    <span class="fw-bold">

        Total

    </span>

    <span
        id="totalDisplay"
        class="summary-total text-primary"
    >

        ₱0.00

    </span>

</div>

</div>


<!-- =========================================================
     PAYMENT
========================================================= -->

<div class="card-body border-top">

<label class="form-label fw-semibold mb-3">

    Payment Method

</label>

<div class="row g-2">

<?php

$paymentMethods = [
    'cash' => [
        'label' => 'Cash',
        'icon' => 'bi-cash'
    ],
    'card' => [
        'label' => 'Card',
        'icon' => 'bi-credit-card'
    ],
    'gcash' => [
        'label' => 'GCash',
        'icon' => 'bi-phone'
    ],
    'bank_transfer' => [
        'label' => 'Bank',
        'icon' => 'bi-bank'
    ],
    'other' => [
        'label' => 'Other',
        'icon' => 'bi-three-dots'
    ]
];

foreach ($paymentMethods as $value => $method):

?>

<div class="col-6">

<label class="payment-method w-100">

<input
    type="radio"
    name="payment_method"
    value="<?= $value ?>"
    <?= $value === 'cash'
        ? 'checked'
        : ''
    ?>
>

<div class="payment-method-content">

    <i class="bi <?= $method['icon'] ?>"></i>

    <div class="small fw-semibold mt-1">

        <?= $method['label'] ?>

    </div>

</div>

</label>

</div>

<?php endforeach; ?>

</div>

</div>


<!-- =========================================================
     AMOUNT PAID
========================================================= -->

<div class="card-body border-top">

<label class="form-label fw-semibold">

    Amount Paid

</label>

<div class="input-group input-group-lg">

<span class="input-group-text">

    ₱

</span>

<input
    type="number"
    name="amount_paid"
    id="amountPaid"
    class="form-control fw-bold"
    value="0"
    min="0"
    step="0.01"
>

</div>


<div class="d-flex justify-content-between mt-3">

<span class="text-muted">

    Change

</span>

<span
    id="changeDisplay"
    class="fw-bold text-success"
>

    ₱0.00

</span>

</div>

</div>


<!-- =========================================================
     NOTES
========================================================= -->

<div class="card-body border-top">

<label class="form-label fw-semibold">

    Notes

</label>

<textarea
    name="notes"
    class="form-control"
    rows="2"
    placeholder="Optional notes..."
></textarea>

</div>


<!-- =========================================================
     COMPLETE SALE
========================================================= -->

<div class="card-body border-top">

<button
    type="submit"
    id="completeSaleBtn"
    class="btn btn-primary btn-lg w-100 rounded-3 fw-bold"
    disabled
>

    <i class="bi bi-check-circle me-1"></i>

    Complete Sale

</button>

</div>

</div>

</div>

</div>

</form>

</div>

</main>

</div>


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


<script>

let cart = [];


/*
|--------------------------------------------------------------------------
| FORMAT MONEY
|--------------------------------------------------------------------------
*/

function formatMoney(value)
{
    return '₱' + Number(value || 0).toLocaleString(
        'en-PH',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }
    );
}


/*
|--------------------------------------------------------------------------
| FIND CART ITEM
|--------------------------------------------------------------------------
*/

function findCartItem(productId)
{
    return cart.find(
        item => Number(item.product_id) === Number(productId)
    );
}


/*
|--------------------------------------------------------------------------
| ADD PRODUCT
|--------------------------------------------------------------------------
*/

function addProduct(card)
{
    const productId =
        Number(card.dataset.productId);

    const productName =
        card.dataset.productName;

    const sku =
        card.dataset.productSku || '';

    const price =
        Number(card.dataset.productPrice);

    const stock =
        Number(card.dataset.productStock);

    if (stock <= 0) {
        return;
    }

    const existing =
        findCartItem(productId);

    if (existing) {

        if (existing.quantity + 1 > stock) {

            alert(
                'You cannot sell more than the available stock.'
            );

            return;
        }

        existing.quantity++;

    } else {

        cart.push({
            product_id: productId,
            product_name: productName,
            sku: sku,
            unit_price: price,
            quantity: 1,
            stock: stock
        });
    }

    renderCart();
}


/*
|--------------------------------------------------------------------------
| REMOVE PRODUCT
|--------------------------------------------------------------------------
*/

function removeProduct(productId)
{
    cart =
        cart.filter(
            item =>
                Number(item.product_id)
                !==
                Number(productId)
        );

    renderCart();
}


/*
|--------------------------------------------------------------------------
| CHANGE QUANTITY
|--------------------------------------------------------------------------
*/

function changeQuantity(productId, quantity)
{
    const item =
        findCartItem(productId);

    if (!item) {
        return;
    }

    quantity =
        Number(quantity);

    if (quantity <= 0) {

        removeProduct(productId);

        return;
    }

    if (quantity > item.stock) {

        alert(
            'Available stock is only ' +
            item.stock
        );

        quantity =
            item.stock;
    }

    item.quantity =
        quantity;

    renderCart();
}


/*
|--------------------------------------------------------------------------
| CALCULATE SUBTOTAL
|--------------------------------------------------------------------------
*/

function calculateSubtotal()
{
    return cart.reduce(
        (sum, item) => {

            return sum +
                (
                    Number(item.unit_price)
                    *
                    Number(item.quantity)
                );

        },
        0
    );
}


/*
|--------------------------------------------------------------------------
| RENDER CART
|--------------------------------------------------------------------------
*/

function renderCart()
{
    const emptyCart =
        document.getElementById(
            'emptyCart'
        );

    const cartContainer =
        document.getElementById(
            'cartContainer'
        );

    const cartBody =
        document.getElementById(
            'cartBody'
        );

    const completeButton =
        document.getElementById(
            'completeSaleBtn'
        );

    if (!cart.length) {

        emptyCart.classList.remove(
            'd-none'
        );

        cartContainer.classList.add(
            'd-none'
        );

        completeButton.disabled =
            true;

        cartBody.innerHTML = '';

    } else {

        emptyCart.classList.add(
            'd-none'
        );

        cartContainer.classList.remove(
            'd-none'
        );

        completeButton.disabled =
            false;

        cartBody.innerHTML = '';

        cart.forEach(item => {

            const row =
                document.createElement(
                    'tr'
                );

            const itemTotal =
                Number(item.unit_price)
                *
                Number(item.quantity);

            row.innerHTML = `

                <td class="ps-3">

                    <div class="fw-semibold small">

                        ${escapeHtml(
                            item.product_name
                        )}

                    </div>

                    ${
                        item.sku
                        ? `
                            <div
                                class="text-muted"
                                style="font-size:.65rem;"
                            >
                                SKU:
                                ${escapeHtml(item.sku)}
                            </div>
                          `
                        : ''
                    }

                    <div
                        class="text-primary fw-semibold"
                        style="font-size:.7rem;"
                    >

                        ${formatMoney(
                            item.unit_price
                        )}

                    </div>

                </td>

                <td>

                    <div
                        class="input-group input-group-sm qty-control"
                    >

                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            onclick="changeQuantity(
                                ${item.product_id},
                                ${item.quantity - 1}
                            )"
                        >
                            -
                        </button>

                        <input
                            type="number"
                            class="form-control"
                            value="${item.quantity}"
                            min="1"
                            max="${item.stock}"
                            onchange="
                                changeQuantity(
                                    ${item.product_id},
                                    this.value
                                )
                            "
                        >

                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            onclick="changeQuantity(
                                ${item.product_id},
                                ${item.quantity + 1}
                            )"
                        >
                            +
                        </button>

                    </div>

                </td>

                <td class="text-end pe-3">

                    <div class="fw-bold">

                        ${formatMoney(itemTotal)}

                    </div>

                    <button
                        type="button"
                        class="btn btn-sm btn-link text-danger p-0"
                        onclick="
                            removeProduct(
                                ${item.product_id}
                            )
                        "
                    >

                        Remove

                    </button>

                </td>
            `;

            cartBody.appendChild(row);

        });
    }

    updateTotals();
}


/*
|--------------------------------------------------------------------------
| UPDATE TOTALS
|--------------------------------------------------------------------------
*/

function updateTotals()
{
    const subtotal =
        calculateSubtotal();

    const discount =
        Math.max(
            0,
            Number(
                document.getElementById(
                    'discount'
                ).value
            ) || 0
        );

    const tax =
        Math.max(
            0,
            Number(
                document.getElementById(
                    'tax'
                ).value
            ) || 0
        );

    const total =
        Math.max(
            0,
            subtotal -
            discount +
            tax
        );

    const amountPaid =
        Math.max(
            0,
            Number(
                document.getElementById(
                    'amountPaid'
                ).value
            ) || 0
        );

    const change =
        Math.max(
            0,
            amountPaid - total
        );

    document.getElementById(
        'subtotalDisplay'
    ).textContent =
        formatMoney(subtotal);

    document.getElementById(
        'totalDisplay'
    ).textContent =
        formatMoney(total);

    document.getElementById(
        'changeDisplay'
    ).textContent =
        formatMoney(change);

    document.getElementById(
        'cartInput'
    ).value =
        JSON.stringify(
            cart.map(item => ({
                product_id:
                    item.product_id,
                quantity:
                    item.quantity
            }))
        );
}


/*
|--------------------------------------------------------------------------
| ESCAPE HTML
|--------------------------------------------------------------------------
*/

function escapeHtml(value)
{
    return String(value)
        .replace(
            /&/g,
            '&amp;'
        )
        .replace(
            /</g,
            '&lt;'
        )
        .replace(
            />/g,
            '&gt;'
        )
        .replace(
            /"/g,
            '&quot;'
        )
        .replace(
            /'/g,
            '&#039;'
        );
}


/*
|--------------------------------------------------------------------------
| PRODUCT CLICK
|--------------------------------------------------------------------------
*/

document.querySelectorAll(
    '.product-card:not(.disabled)'
).forEach(card => {

    card.addEventListener(
        'click',
        function () {

            addProduct(this);

        }
    );

});


/*
|--------------------------------------------------------------------------
| PRODUCT SEARCH
|--------------------------------------------------------------------------
*/

document.getElementById(
    'productSearch'
).addEventListener(
    'input',
    function () {

        const search =
            this.value
                .toLowerCase()
                .trim();

        let visibleCount = 0;

        document.querySelectorAll(
            '.product-wrapper'
        ).forEach(wrapper => {

            const data =
                wrapper.dataset.search || '';

            const matches =
                data.includes(search);

            wrapper.style.display =
                matches
                    ? ''
                    : 'none';

            if (matches) {
                visibleCount++;
            }

        });

        document.getElementById(
            'noSearchResults'
        ).classList.toggle(
            'd-none',
            visibleCount !== 0
        );

    }
);


/*
|--------------------------------------------------------------------------
| CLEAR CART
|--------------------------------------------------------------------------
*/

document.getElementById(
    'clearCartBtn'
).addEventListener(
    'click',
    function () {

        if (!cart.length) {
            return;
        }

        if (
            !confirm(
                'Clear all items from the cart?'
            )
        ) {
            return;
        }

        cart = [];

        renderCart();

    }
);


/*
|--------------------------------------------------------------------------
| TOTAL EVENTS
|--------------------------------------------------------------------------
*/

document.getElementById(
    'discount'
).addEventListener(
    'input',
    updateTotals
);

document.getElementById(
    'tax'
).addEventListener(
    'input',
    updateTotals
);

document.getElementById(
    'amountPaid'
).addEventListener(
    'input',
    updateTotals
);


/*
|--------------------------------------------------------------------------
| FORM VALIDATION
|--------------------------------------------------------------------------
*/

document.getElementById(
    'saleForm'
).addEventListener(
    'submit',
    function (event) {

        if (!cart.length) {

            event.preventDefault();

            alert(
                'Please add at least one product.'
            );

            return;
        }

        updateTotals();

    }
);


/*
|--------------------------------------------------------------------------
| INITIALIZE
|--------------------------------------------------------------------------
*/

renderCart();

</script>

</body>

</html>
