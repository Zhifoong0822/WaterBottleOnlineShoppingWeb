<?php

require_once "_base.php";

$_title = "Admin Order Details";

if (!isset($_GET["id"]) || !ctype_digit($_GET["id"])) {
    die("Invalid order ID.");
}

$order_id = (int) $_GET["id"];

$message = "";
$error = "";

//retrieve the current order first
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

$current_status = strtolower($order["status"]);

//valid status transitions
$status_transitions = [
    "pending" => [
        "pending",
        "shipped",
        "cancelled"
    ],

    "shipped" => [
        "shipped",
        "completed"
    ],

    "completed" => [
        "completed"
    ],

    "cancelled" => [
        "cancelled"
    ]
];

//process status update
if (is_post()) {

    $new_status = post("status"); //capture admin input(new status)

    $allowed_next_statuses =
        $status_transitions[$current_status] ?? [];

    if (!in_array($new_status, $allowed_next_statuses, true)) {
        
        $error =
            "The order status cannot be changed from " .
            ucfirst($current_status) .
            " to " .
            ucfirst($new_status) .
            ".";

    } elseif ($new_status === $current_status) {

        $message = "The order status remains unchanged.";

    } else {

        try {
            $update_sql = "UPDATE orders
                           SET status = :new_status
                           WHERE order_id = :order_id
                             AND status = :current_status";

            $update_stmt = $_db->prepare($update_sql);

            $update_stmt->execute([
                "new_status" => $new_status,
                "order_id" => $order_id,
                "current_status" => $current_status
            ]);

            if ($update_stmt->rowCount() > 0) {
                redirect(
                    "admin_order_detail.php?id=" .
                    urlencode($order_id)
                );
            }

            $error = "The order status could not be updated.";

        } catch (PDOException $e) {
            $error = "Unable to update order status.";
        }
    }
}

//retrieve the latest order information
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
            <?= in_array(
                "pending",
                $status_transitions[$status] ?? [],
                true
            ) ? "" : "disabled" ?>
        >
            Pending
        </option>

        <option
            value="shipped"
            <?= $status === "shipped" ? "selected" : "" ?>
            <?= in_array(
                "shipped",
                $status_transitions[$status] ?? [],
                true
            ) ? "" : "disabled" ?>
        >
            Shipped
        </option>

        <option
            value="completed"
            <?= $status === "completed" ? "selected" : "" ?>
            <?= in_array(
                "completed",
                $status_transitions[$status] ?? [],
                true
            ) ? "" : "disabled" ?>
        >
            Completed
        </option>

        <option
            value="cancelled"
            <?= $status === "cancelled" ? "selected" : "" ?>
            <?= in_array(
                "cancelled",
                $status_transitions[$status] ?? [],
                true
            ) ? "" : "disabled" ?>
        >
                Cancelled
            </option>

        </select>

        <div class="button-group">

            <?php
            $is_final_status = in_array(
                $status,
                ["completed", "cancelled"],
                true
            );
            ?>

            <button
                type="submit"
                <?= $is_final_status ? "disabled" : "" ?>
            >
                <?= $is_final_status
                    ? "Status Finalised"
                    : "Update Status" ?>
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
