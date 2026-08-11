<?php

require_once "_base.php";

$_title = "Order Details";
$_hide_page_title = true;

if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}
$user_id = (int) $_SESSION['user_id'];

if (!isset($_GET["id"]) || !ctype_digit($_GET["id"])) {
    die("Invalid order ID.");
}

$order_id = (int) $_GET["id"];

if (is_post() && post('action') === 'send_receipt') {
    try {
        send_order_receipt($order_id, $user_id);
        temp('info', 'Your e-receipt has been sent to your registered email address.');
    } catch (Throwable $mail_error) {
        temp('info', 'Unable to send the e-receipt. Please try again later.');
    }

    redirect('order_detail.php?id=' . urlencode($order_id));
}

try {
    $sql = "SELECT
                order_id,
                user_id,
                order_date,
                shipped_at,
                completed_at,
                cancelled_at,
                total_amount,
                subtotal_amount,
                points_used,
                points_discount,
                points_earned,
                status,
                payment_method,
                payment_reference,
                payment_status
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
$payment_method = ucwords(str_replace('_', ' ', $order["payment_method"]));
$payment_status = ucfirst($order["payment_status"]);

require "_head.php";

?>

<div class="order-detail-container">
<div class="order-tracking">

<!-- Order Placed -->
<div class="tracking-step completed-step">

    <div class="tracking-circle">
        <i class="fa-solid fa-receipt"></i>
    </div>

    <div class="tracking-info">
        <strong>Order Placed</strong>

        <span>
            <?= date(
                "d M Y",
                strtotime($order["order_date"])
            ) ?>
        </span>

        <span>
            <?= date(
                "h:i A",
                strtotime($order["order_date"])
            ) ?>
        </span>
    </div>

</div>


<?php if ($status !== "cancelled"): ?>

    <!-- Line to Shipped -->
    <div class="tracking-line
        <?= !empty($order["shipped_at"]) ? "active-line" : "" ?>">
    </div>


    <!-- Shipped -->
    <div class="tracking-step
        <?= !empty($order["shipped_at"]) ? "completed-step" : "" ?>">

        <div class="tracking-circle">
            <i class="fa-solid fa-truck"></i>
        </div>

        <div class="tracking-info">

            <strong>Shipped</strong>

            <?php if (!empty($order["shipped_at"])): ?>

                <span>
                    <?= date(
                        "d M Y",
                        strtotime($order["shipped_at"])
                    ) ?>
                </span>

                <span>
                    <?= date(
                        "h:i A",
                        strtotime($order["shipped_at"])
                    ) ?>
                </span>

            <?php else: ?>

                <span class="tracking-pending">
                    Pending
                </span>

            <?php endif; ?>

        </div>

    </div>


    <!-- Line to Completed -->
    <div class="tracking-line
        <?= !empty($order["completed_at"]) ? "active-line" : "" ?>">
    </div>

<?php else: ?>

    <!-- Direct line to Cancelled -->
    <div class="tracking-line active-line"></div>

<?php endif; ?>


<!-- Final Step -->
<div class="tracking-step
    <?= (!empty($order["completed_at"]) ||
         !empty($order["cancelled_at"]))
        ? "completed-step"
        : "" ?>
    <?= ($status !== "cancelled" && !empty($order["completed_at"]))
        ? "completed-status-step"
        : "" ?>
    <?= ($status === "cancelled" && !empty($order["cancelled_at"]))
        ? "cancelled-status-step"
        : "" ?>">

        <div class="tracking-circle">

        <?php if ($status === "cancelled"): ?>

            <i class="fa-solid fa-xmark"></i>

        <?php else: ?>

            <i class="fa-solid fa-star"></i>

        <?php endif; ?>

</div>

    <div class="tracking-info">

        <?php if ($status === "cancelled"): ?>

            <strong>Cancelled</strong>

            <?php if (!empty($order["cancelled_at"])): ?>

                <span>
                    <?= date(
                        "d M Y",
                        strtotime($order["cancelled_at"])
                    ) ?>
                </span>

                <span>
                    <?= date(
                        "h:i A",
                        strtotime($order["cancelled_at"])
                    ) ?>
                </span>

            <?php endif; ?>


        <?php else: ?>

            <strong>Completed</strong>

            <?php if (!empty($order["completed_at"])): ?>

                <span>
                    <?= date(
                        "d M Y",
                        strtotime($order["completed_at"])
                    ) ?>
                </span>

                <span>
                    <?= date(
                        "h:i A",
                        strtotime($order["completed_at"])
                    ) ?>
                </span>

            <?php else: ?>

                <span class="tracking-pending">
                    Pending
                </span>

            <?php endif; ?>

        <?php endif; ?>

    </div>

</div>

</div>

    <div class="detail-card">

        <div class="card-header">
            <h2>
                Order Details
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
                <th>Subtotal</th>
                <td class="amount">
                    RM <?= number_format(
                        (float) $order["subtotal_amount"],
                        2
                    ) ?>
                </td>
            </tr>

            <?php if ((int) $order["points_used"] > 0): ?>
            <tr>
                <th>Reward Points Used</th>
                <td>
                    <?= number_format((int) $order["points_used"]) ?> points
                    (- RM <?= number_format((float) $order["points_discount"], 2) ?>)
                </td>
            </tr>
            <?php endif; ?>

            <tr>
                <th>Amount Paid</th>
                <td class="amount">
                    RM <?= number_format(
                        (float) $order["total_amount"],
                        2
                    ) ?>
                </td>
            </tr>

            <tr>
                <th>Reward Points Earned</th>
                <td>+<?= number_format((int) $order["points_earned"]) ?> points</td>
            </tr>

            <tr>
                <th>Payment Method</th>
                <td><?= encode($payment_method) ?></td>
            </tr>

            <tr>
                <th>Payment Status</th>
                <td><?= encode($payment_status) ?></td>
            </tr>

            <?php if ($order["payment_reference"]): ?>
            <tr>
                <th>Payment Reference</th>
                <td><?= encode($order["payment_reference"]) ?></td>
            </tr>
            <?php endif; ?>

        </table>

        <div class="button-area">

            <form method="post" class="receipt-form">
                <input type="hidden" name="action" value="send_receipt">
                <button type="submit" class="receipt-button">Send E-Receipt</button>
            </form>

            <a class="back-button" href="order_history.php">
                ← Back to My Orders
            </a>

        </div>

    </div>

</div>

<?php require "_foot.php"; ?>
