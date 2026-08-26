<?php

require_once "_base.php";

$_title = "Order Details";
$_hide_page_title = true;

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
                payment_status,
                recipient_name,
                phone_number,
                shipping_address,
                address_updated
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
$can_update_address = $status === 'pending' && !(bool) $order['address_updated'];
$show_address_editor = $can_update_address && get('edit_address') === '1';

if (is_post() && post('action') === 'update_address') {
    $address_source = post('address_source', 'saved');
    $return_url = 'order_detail.php?id=' . urlencode($order_id);
    $shipping_address = '';
    $recipient_name = $order['recipient_name'];
    $phone_number = $order['phone_number'];

    if (!$can_update_address) {
        temp('info', 'The delivery address can only be changed once before the order is processed.');
    } else {
        if ($address_source === 'saved') {
            $address_id = post('address_id', '');
            $saved_address_stmt = $_db->prepare(
                'SELECT recipient_name, phone_number, address_text FROM user_addresses WHERE address_id = ? AND user_id = ?'
            );
            $saved_address_stmt->execute([(int) $address_id, $user_id]);
            $saved_address = $saved_address_stmt->fetch();

            if (!$saved_address) {
                temp('info', 'Please choose one of your saved addresses.');
                $return_url .= '&edit_address=1';
            } else {
                $shipping_address = $saved_address->address_text;
                $recipient_name = $saved_address->recipient_name;
                $phone_number = $saved_address->phone_number;
            }
        } elseif ($address_source === 'manual') {
            $shipping_address = trim(post('shipping_address', ''));

            if ($shipping_address === '') {
                temp('info', 'Please enter a delivery address.');
                $return_url .= '&edit_address=1';
            } elseif (strlen($shipping_address) > 1000) {
                temp('info', 'The delivery address must not exceed 1,000 characters.');
                $return_url .= '&edit_address=1';
            }
        } else {
            temp('info', 'Choose a saved address or enter one manually.');
            $return_url .= '&edit_address=1';
        }

        if ($shipping_address !== '') {
            $update_stmt = $_db->prepare(
                "UPDATE orders
                 SET recipient_name = ?, phone_number = ?, shipping_address = ?, address_updated = 1
                 WHERE order_id = ? AND user_id = ? AND status = 'pending' AND address_updated = 0"
            );
            $update_stmt->execute([$recipient_name, $phone_number, $shipping_address, $order_id, $user_id]);

            temp(
                'info',
                $update_stmt->rowCount() === 1
                    ? 'Your delivery address has been updated.'
                    : 'The delivery address could not be updated because the order has already been processed.'
            );
        }
    }

    redirect($return_url);
}

$saved_addresses = [];
if ($show_address_editor) {
    $saved_addresses_stmt = $_db->prepare(
        'SELECT address_id, address_label, recipient_name, phone_number, address_text FROM user_addresses WHERE user_id = ? ORDER BY address_label, address_id'
    );
    $saved_addresses_stmt->execute([$user_id]);
    $saved_addresses = $saved_addresses_stmt->fetchAll();
}

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

            <tr>
                <th>Delivery To</th>
                <td>
                    <?= encode($order['recipient_name']) ?><br>
                    <?= encode($order['phone_number']) ?>
                </td>
            </tr>

            <tr>
                <th>Delivery Address</th>
                <td><?= nl2br(encode($order['shipping_address'])) ?></td>
            </tr>

        </table>

        <?php if ($can_update_address && !$show_address_editor): ?>
            <div class="address-change-action">
                <a class="receipt-button" href="order_detail.php?id=<?= urlencode($order_id) ?>&edit_address=1">Change Delivery Address</a>
                <p>You can make one change before this order is processed.</p>
            </div>
        <?php elseif ($show_address_editor): ?>
            <form method="post" class="address-update-form">
                <input type="hidden" name="action" value="update_address">
                <h3>Change Delivery Address</h3>
                <p>Choose a saved address, or enter a new one. This can only be done once.</p>

                <?php if ($saved_addresses): ?>
                    <label class="address-source-option">
                        <input type="radio" name="address_source" value="saved" checked>
                        Use a saved address
                    </label>
                    <select id="saved-address-select" name="address_id" required>
                        <?php foreach ($saved_addresses as $address): ?>
                            <option value="<?= (int) $address->address_id ?>">
                                [<?= encode($address->address_label) ?>] <?= encode($address->recipient_name) ?> — <?= encode($address->address_text) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <label class="address-source-option">
                    <input type="radio" name="address_source" value="manual" <?= $saved_addresses ? '' : 'checked' ?>>
                    Enter a new address
                </label>
                <textarea id="shipping_address" name="shipping_address" rows="4" maxlength="1000" <?= $saved_addresses ? 'disabled' : 'required' ?>><?= encode($order['shipping_address']) ?></textarea>

                <div class="address-update-actions">
                    <button type="submit" class="receipt-button">Save Delivery Address</button>
                    <a class="back-button" href="order_detail.php?id=<?= urlencode($order_id) ?>">Cancel</a>
                </div>
            </form>
        <?php elseif ((bool) $order['address_updated']): ?>
            <p class="address-update-note">The one-time delivery address change has already been used.</p>
        <?php endif; ?>

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

<?php if ($show_address_editor): ?>
<script>
document.querySelectorAll('input[name="address_source"]').forEach(function (option) {
    option.addEventListener('change', function () {
        const useSaved = this.value === 'saved' && this.checked;
        const savedSelect = document.getElementById('saved-address-select');
        const manualAddress = document.getElementById('shipping_address');

        if (savedSelect) {
            savedSelect.disabled = !useSaved;
            savedSelect.required = useSaved;
        }
        manualAddress.disabled = useSaved;
        manualAddress.required = !useSaved;
    });
});
</script>
<?php endif; ?>

<?php require "_foot.php"; ?>
