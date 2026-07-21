<?php

require_once "_base.php";

$_title = "My Orders";

$user_id = 2;

$status_filter = get("status", "pending");

$allowed_statuses = [
    "pending",
    "shipped",
    "completed",
    "cancelled"
];

if (!in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = "pending";
}

// Customer clicks Order Received button
if (is_post() && post("action") === "confirm_received") {

    $received_order_id = post("order_id");

    if (!ctype_digit($received_order_id)) {
        temp("info", "Invalid order.");
        redirect("order_history.php?status=shipped");
    }

    $received_order_id = (int) $received_order_id;

    $sql = "UPDATE orders
            SET status = 'completed'
            WHERE order_id = :order_id
              AND user_id = :user_id
              AND status = 'shipped'";

    $stmt = $_db->prepare($sql);

    $stmt->execute([
        "order_id" => $received_order_id,
        "user_id" => $user_id
    ]);

    if ($stmt->rowCount() > 0) {
        redirect("order_history.php?status=completed");
    }

    redirect("order_history.php?status=shipped");
}

// Customer clicks Cancel Order button
if (is_post() && post("action") === "cancel_order") {

    $cancel_order_id = post("order_id");

    if (!ctype_digit($cancel_order_id)) {
        redirect("order_history.php?status=pending");
    }

    $cancel_order_id = (int) $cancel_order_id;

    $sql = "UPDATE orders
            SET status = 'cancelled'
            WHERE order_id = :order_id
              AND user_id = :user_id
              AND status = 'pending'";

    $stmt = $_db->prepare($sql);

    $stmt->execute([
        "order_id" => $cancel_order_id,
        "user_id" => $user_id
    ]);

    if ($stmt->rowCount() > 0) {
        redirect("order_history.php?status=cancelled");
    }

    redirect("order_history.php?status=pending");
}

$sql = "SELECT
            o.order_id,
            o.order_date,
            o.status,
            o.total_amount,
            oi.order_item_id,
            oi.product_id,
            oi.quantity,
            oi.price AS item_price,
            p.name AS product_name,
            p.image_url
        FROM orders AS o
        LEFT JOIN order_items AS oi
            ON o.order_id = oi.order_id
        LEFT JOIN products AS p
            ON oi.product_id = p.product_id
        WHERE o.user_id = :user_id
        AND o.status = :status
        ORDER BY
            o.order_date DESC,
            o.order_id DESC,
            oi.order_item_id ASC";

$stmt = $_db->prepare($sql);
$stmt->execute([
    "user_id" => $user_id,
    "status" => $status_filter
]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$orders = [];

foreach ($rows as $row) {
    $order_id = $row["order_id"];

    if (!isset($orders[$order_id])) {
        $orders[$order_id] = [
            "order_id" => $row["order_id"],
            "order_date" => $row["order_date"],
            "status" => $row["status"],
            "total_amount" => $row["total_amount"],
            "items" => []
        ];
    }

    if ($row["product_id"] !== null) {
        $orders[$order_id]["items"][] = [
            "product_name" => $row["product_name"],
            "image_url" => $row["image_url"],
            "quantity" => $row["quantity"],
            "item_price" => $row["item_price"]
        ];
    }
}

require "_head.php";

?>

<nav class="order-tabs">

    <a
        href="order_history.php?status=pending"
        class="<?= $status_filter === "pending" ? "active" : "" ?>"
    >
        To Ship
    </a>

    <a
        href="order_history.php?status=shipped"
        class="<?= $status_filter === "shipped" ? "active" : "" ?>"
    >
        To Receive
    </a>

    <a
        href="order_history.php?status=completed"
        class="<?= $status_filter === "completed" ? "active" : "" ?>"
    >
        Completed
    </a>

    <a
        href="order_history.php?status=cancelled"
        class="<?= $status_filter === "cancelled" ? "active" : "" ?>"
    >
        Cancelled
    </a>

</nav>

<section class="order-history">

    <?php if ($orders): ?>

        <?php foreach ($orders as $order): ?>

            <article class="order-card">

    <div class="order-left">

        <?php if ($order["items"]): ?>

            <?php
            $firstItem = $order["items"][0];

            $productName = $firstItem["product_name"] ?: "Unknown Product";
            $imageUrl = $firstItem["image_url"]
                ?: "https://placehold.co/100x100?text=No+Image";

            $quantity = (int) $firstItem["quantity"];
            ?>

            <img
                class="order-image"
                src="<?= encode($imageUrl) ?>"
                alt="<?= encode($productName) ?>"
            >

            <div class="order-info">

                <h2>
                    <?= encode($productName) ?>
                </h2>

                <p class="quantity">
                    x<?= $quantity ?>
                </p>

                <p class="order-meta">
                    Order #<?= encode($order["order_id"]) ?>
                    ·
                    <?= date(
                        "d M Y, h:i A",
                        strtotime($order["order_date"])
                    ) ?>
                </p>

                <a
                    class="view-details"
                    href="order_detail.php?id=<?= urlencode(
                        $order["order_id"]
                    ) ?>"
                >
                    View Details
                </a>

            </div>

        <?php else: ?>

            <div class="order-info">
                <h2>No product information</h2>

                <p class="order-meta">
                    Order #<?= encode($order["order_id"]) ?>
                </p>
            </div>

        <?php endif; ?>

    </div>

    <div class="order-right">

    <span class="status status-<?= encode($order["status"]) ?>">
        <?= ucfirst(encode($order["status"])) ?>
    </span>

    <div class="amount-section">

        <span class="amount-label">
            Total Amount Paid
        </span>

        <strong class="amount">
            RM <?= number_format(
                (float) $order["total_amount"],
                2
            ) ?>
        </strong>

        <?php if ($order["status"] === "shipped"): ?>

            <form
                method="POST"
                class="received-form"
                onsubmit="return confirm(
                    'Confirm that you have received this order?'
                );"
            >

                <input
                    type="hidden"
                    name="action"
                    value="confirm_received"
                >

                <input
                    type="hidden"
                    name="order_id"
                    value="<?= encode($order["order_id"]) ?>"
                >

                <button
                    type="submit"
                    class="received-button"
                >
                    Order Received
                </button>

            </form>

        <?php endif; ?>

        <?php if ($order["status"] === "pending"): ?>

        <form
            method="POST"
            class="cancel-form"
            onsubmit="return confirm(
            'Are you sure you want to cancel this order?'
            );"
        >

        <input
            type="hidden"
            name="action"
            value="cancel_order"
        >

        <input
            type="hidden"
            name="order_id"
            value="<?= encode($order["order_id"]) ?>"
        >

        <button
            type="submit"
            class="cancel-button"
        >
            Cancel Order
        </button>

        </form>

    <?php endif; ?>

    </div>

</div>

</article>

        <?php endforeach; ?>

    <?php else: ?>

        <p>You do not have any orders yet.</p>

    <?php endif; ?>

</section>

<?php require "_foot.php"; ?>