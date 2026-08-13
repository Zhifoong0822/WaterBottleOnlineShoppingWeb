<?php

require_once "_base.php";

$_title = "Order Details";

if (!isset($_SESSION['users']->user_id)) {
    redirect('login.php');
}
$user_id = (int) $_SESSION['users']->user_id;

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
