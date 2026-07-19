<?php

require_once "_base.php";

$_title = "Admin Order Details";

if (!isset($_GET["id"]) || !ctype_digit($_GET["id"])) {
    die("Invalid order ID.");
}

$order_id = (int) $_GET["id"];

$message = "";
$error = "";

$allowed_statuses = [
    "pending",
    "shipped",
    "completed",
    "cancelled"
];

if (is_post()) {

    $status = post("status");

    if (!in_array($status, $allowed_statuses, true)) {
        $error = "Invalid order status.";
    } else {
        try {
            $update_sql = "UPDATE orders
                           SET status = :status
                           WHERE order_id = :order_id";

            $update_stmt = $_db->prepare($update_sql);

            $update_stmt->execute([
                "status" => $status,
                "order_id" => $order_id
            ]);

            if ($update_stmt->rowCount() > 0) {
                $message = "Order status updated successfully.";
            } else {
                $message = "The order status remains unchanged.";
            }

        } catch (PDOException $e) {
            $error = "Unable to update order status.";
        }
    }
}

try {
    $sql = "SELECT
                order_id,
                user_id,
                order_date,
                total_amount,
                status
            FROM orders
            WHERE order_id = :order_id";

    $stmt = $_db->prepare($sql);

    $stmt->execute([
        "order_id" => $order_id
    ]);

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die("Order not found.");
    }

} catch (PDOException $e) {
    die("Unable to retrieve order: " . $e->getMessage());
}

$status = strtolower($order["status"]);

require "_head.php";

?>

<section class="admin-order-detail-page">

    <?php if ($message !== ""): ?>
        <div class="alert alert-success">
            <?= encode($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="alert alert-error">
            <?= encode($error) ?>
        </div>
    <?php endif; ?>

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
                <th>User ID</th>
                <td>
                    <?= encode($order["user_id"]) ?>
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
                <th>Total Amount</th>
                <td class="amount">
                    RM <?= number_format(
                        (float) $order["total_amount"],
                        2
                    ) ?>
                </td>
            </tr>

            <tr>
                <th>Current Status</th>
                <td>
                    <span class="status status-<?= encode($status) ?>">
                        <?= ucfirst(encode($status)) ?>
                    </span>
                </td>
            </tr>

        </table>

        <div class="form-section">

            <h3>Update Order Status</h3>

            <form
                method="POST"
                action="admin_order_detail.php?id=<?= urlencode(
                    $order["order_id"]
                ) ?>"
                onsubmit="return confirmStatusUpdate();"
       >

                <label for="status">
                    Select New Status
                </label>

                <select name="status" id="status" required>

                    <option
                        value="pending"
                        <?= $status === "pending" ? "selected" : "" ?>
                    >
                        Pending
                    </option>

                    <option
                        value="shipped"
                        <?= $status === "shipped" ? "selected" : "" ?>
                    >
                        Shipped
                    </option>

                    <option
                        value="completed"
                        <?= $status === "completed" ? "selected" : "" ?>
                    >
                        Completed
                    </option>

                    <option
                        value="cancelled"
                        <?= $status === "cancelled" ? "selected" : "" ?>
                    >
                        Cancelled
                    </option>

                </select>

                <div class="button-group">

                    <button type="submit">
                        Update Status
                    </button>

                    <a class="back-button" href="admin_orders.php">
                        ← Back to Manage Orders
                    </a>

                </div>

            </form>

        </div>

    </div>

</section>


<script>


function confirmStatusUpdate() {
    const statusSelect = document.getElementById("status");
    const selectedStatus =
        statusSelect.options[statusSelect.selectedIndex].text;
    return confirm(
        "Update this order status to " + selectedStatus + "?"
    );
}

</script>

<?php require "_foot.php"; ?>
