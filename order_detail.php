<?php

require_once "_base.php";

$_title = "Order Details";

// Temporary customer ID for testing
$user_id = 2;

if (!isset($_GET["id"]) || !ctype_digit($_GET["id"])) {
    die("Invalid order ID.");
}

$order_id = (int) $_GET["id"];

try {
    $sql = "SELECT
                order_id,
                user_id,
                order_date,
                total_amount,
                status
            FROM orders
            WHERE order_id = :order_id
            AND user_id = :user_id";

    $stmt = $_db->prepare($sql);

    $stmt->execute([
        "order_id" => $order_id,
        "user_id" => $user_id
    ]);

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die("Order not found or you do not have permission to view it.");
    }

} catch (PDOException $e) {
    die("Unable to retrieve order: " . $e->getMessage());
}

$status = strtolower($order["status"]);

require "_head.php";

?>

<div class="order-detail-container">

    <div class="detail-card">

        <div class="card-header">
            <h2>
                Order #<?= encode($order["order_id"]) ?>
            </h2>
        </div>

        <table class="order-detail-table">

            <tr>
                <th>Order ID</th>
                <td>
                    #<?= encode($order["order_id"]) ?>
                </td>
            </tr>

            <tr>
                <th>Order Date</th>
                <td>
                    <?= date(
                        "d M Y, h:i A",
                        strtotime($order["order_date"])
                    ) ?>
                </td>
            </tr>

            <tr>
                <th>Status</th>
                <td>
                    <span class="status status-<?= encode($status) ?>">
                        <?= ucfirst(encode($status)) ?>
                    </span>
                </td>
            </tr>

            <tr>
                <th>Total Amount</th>
                <td class="amount">
                    RM <?= number_format(
                        (float) $order["total_amount"],
                        2
                    ) ?>
                </td>
            </tr>

        </table>

        <div class="button-area">

            <a class="back-button" href="order_history.php">
                ← Back to My Orders
            </a>

        </div>

    </div>

</div>

<?php require "_foot.php"; ?>