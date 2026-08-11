<?php

require_once "_base.php";

$_title = "My Orders";
$_page_title_class = "my-orders-title";

if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}
$user_id = (int) $_SESSION['user_id'];

$status_filter = get("status", "all");

$allowed_statuses = [
    "all",
    "pending",
    "shipped",
    "completed",
    "cancelled"
];

if (!in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = "all";
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
            SET status = 'completed',
                /* record exact date&time for completed_at when cust clicks Order Received */
                completed_at = NOW() 
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

    try {
        $_db->beginTransaction();

        $stmt_order = $_db->prepare("
            SELECT points_used, points_earned
            FROM orders
            WHERE order_id = :order_id
              AND user_id = :user_id
              AND status = 'pending'
            FOR UPDATE
        ");
        $stmt_order->execute([
            "order_id" => $cancel_order_id,
            "user_id" => $user_id
        ]);
        $cancelled_order = $stmt_order->fetch();

        if (!$cancelled_order) {
            $_db->rollBack();
            redirect("order_history.php?status=pending");
        }

        $stmt_cancel = $_db->prepare("
            UPDATE orders
            SET status = 'cancelled',
                cancelled_at = NOW()  
            WHERE order_id = :order_id
                AND status = 'pending'
        ");
        $stmt_cancel->execute(["order_id" => $cancel_order_id]);

        $stmt_points = $_db->prepare("
            UPDATE users
            SET reward_points = GREATEST(
                reward_points + :points_used - :points_earned,
                0
            )
            WHERE user_id = :user_id
        ");
        $stmt_points->execute([
            "points_used" => $cancelled_order->points_used,
            "points_earned" => $cancelled_order->points_earned,
            "user_id" => $user_id
        ]);

        $_db->commit();
        temp("info", "Order cancelled and reward points adjusted.");
        redirect("order_history.php?status=cancelled");
    } catch (PDOException $e) {
        if ($_db->inTransaction()) {
            $_db->rollBack();
        }
        temp("info", "Unable to cancel the order. Please try again.");
    }

    redirect("order_history.php?status=pending");
}

$sql = "SELECT
            o.order_id,
            o.order_date,
            o.status,
            o.total_amount,

            EXISTS (
                SELECT 1
                FROM order_feedback AS f
                WHERE f.order_id = o.order_id
                  AND f.user_id = o.user_id
            ) AS feedback_submitted,

            (
                SELECT f.rating FROM order_feedback AS f
                WHERE f.order_id = o.order_id AND f.user_id = o.user_id
                LIMIT 1
            ) AS feedback_rating,

            (
                SELECT f.feedback FROM order_feedback AS f
                WHERE f.order_id = o.order_id AND f.user_id = o.user_id
                LIMIT 1
            ) AS feedback_text,

            (
                SELECT f.updated_at FROM order_feedback AS f
                WHERE f.order_id = o.order_id AND f.user_id = o.user_id
                LIMIT 1
            ) AS feedback_updated_at,

            oi.order_item_id,
            oi.product_id,
            oi.quantity,
            oi.price AS item_price,
            p.name AS product_name,
            p.image_url,
            oi.size AS size

        FROM orders AS o

        LEFT JOIN order_items AS oi
            ON o.order_id = oi.order_id

        LEFT JOIN products AS p
            ON oi.product_id = p.product_id

        WHERE o.user_id = :user_id";

if ($status_filter !== "all") {
    $sql .= " AND o.status = :status";
}

$sql .= " ORDER BY
            o.order_date DESC,
            o.order_id DESC,
            oi.order_item_id ASC";

            $stmt = $_db->prepare($sql);

            $params = [
                "user_id" => $user_id
            ];
            
            if ($status_filter !== "all") {
                $params["status"] = $status_filter;
            }
            
            $stmt->execute($params);

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
            "feedback_submitted" => (bool) $row["feedback_submitted"],
            "feedback_rating" => $row["feedback_rating"],
            "feedback_text" => $row["feedback_text"],
            "feedback_updated_at" => $row["feedback_updated_at"],
            "items" => []
        ];
    }

    if ($row["product_id"] !== null) {
        $orders[$order_id]["items"][] = [
            "product_name" => $row["product_name"],
            "image_url" => $row["image_url"],
            "quantity" => $row["quantity"],
            "item_price" => $row["item_price"],
            "size" => $row["size"]
        ];
    }
}

require "_head.php";

?>

<nav class="order-tabs">

    <a
        href="order_history.php?status=all"
        class="<?= $status_filter === "all" ? "active" : "" ?>"
    >
        All
    </a>

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

            <div class="order-items-list">

        <?php foreach ($order["items"] as $item): ?>

            <?php
            $productName = $item["product_name"] ?: "Unknown Product";

            $imageUrl = $item["image_url"]
                ?: "https://placehold.co/100x100?text=No+Image";

            $quantity = (int) $item["quantity"];
            ?>

            <div class="order-item-preview">

                <img
                    class="order-image"
                    src="<?= encode($imageUrl) ?>"
                    alt="<?= encode($productName) ?>"
                >

                <div class="order-info">

    <h2>
        <?= encode($productName) ?>
    </h2>

    <?php if (!empty($item["size"])): ?>
        <p class="product-variation">
            Size: <?= encode($item["size"]) ?>
        </p>
    <?php endif; ?>

    <p class="quantity">
        x<?= $quantity ?>
    </p>

</div>

            </div>

        <?php endforeach; ?>

        <div class="order-meta-section">

            <p class="order-meta">
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

    <div class="order-status-line">
        <?php if ($order["status"] === "cancelled"): ?>
            <span class="refund-success-message">
                Refund has been returned successfully.
            </span>
        <?php endif; ?>

        <span class="status status-<?= encode($order["status"]) ?>">
            <?= ucfirst(encode($order["status"])) ?>
        </span>
    </div>

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

    <?php if ($order["status"] === "completed"): ?>

<?php if ($order["feedback_submitted"]): ?>

    <button
        type="button"
        class="view-rating-button"
        data-order-id="<?= encode($order["order_id"]) ?>"
        data-rating="<?= encode($order["feedback_rating"]) ?>"
        data-feedback="<?= encode($order["feedback_text"] ?? '') ?>"
        data-updated-at="<?= encode($order["feedback_updated_at"] ?? '') ?>"
    >
        View Rating
    </button>

<?php else: ?>

    <a
        class="feedback-button"
        href="order_feedback.php?id=<?= urlencode(
            $order["order_id"]
        ) ?>"
    >
        Add Feedback or Rating
    </a>

    <?php endif; ?>

<?php endif; ?>

    </div>

</div>

</article>

        <?php endforeach; ?>

    <?php else: ?>

        <p>You do not have any orders yet.</p>

    <?php endif; ?>

</section>

<section id="rating-popover" class="rating-popover" hidden role="dialog" aria-modal="false" aria-labelledby="rating-popover-title">
    <button type="button" class="rating-popover-close" aria-label="Close rating">&times;</button>
    <p class="rating-popover-eyebrow">Your Rating</p>
    <h2 id="rating-popover-title"><span id="rating-popover-stars"></span> <span id="rating-popover-score"></span></h2>
    <p id="rating-popover-feedback" class="rating-popover-feedback"></p>
    <p id="rating-popover-date" class="rating-popover-date"></p>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const popover = document.getElementById('rating-popover');
    const closeButton = popover.querySelector('.rating-popover-close');
    const closePopover = () => { popover.hidden = true; };

    document.querySelectorAll('.view-rating-button').forEach((button) => {
        button.addEventListener('click', () => {
            const rating = Number(button.dataset.rating);
            document.getElementById('rating-popover-stars').textContent = '★'.repeat(rating) + '☆'.repeat(5 - rating);
            document.getElementById('rating-popover-score').textContent = `${rating}/5`;
            document.getElementById('rating-popover-feedback').textContent = button.dataset.feedback || 'No written feedback was added.';
            document.getElementById('rating-popover-date').textContent = button.dataset.updatedAt ? `Submitted ${button.dataset.updatedAt}` : '';
            popover.hidden = false;
            const buttonRect = button.getBoundingClientRect();
            const popoverWidth = popover.offsetWidth;
            popover.style.left = `${Math.max(16, Math.min(buttonRect.right - popoverWidth, window.innerWidth - popoverWidth - 16))}px`;
            popover.style.top = `${Math.max(16, Math.min(buttonRect.top - popover.offsetHeight - 12, window.innerHeight - popover.offsetHeight - 16))}px`;
        });
    });

    closeButton.addEventListener('click', closePopover);
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closePopover(); });
    document.addEventListener('click', (event) => {
        if (!popover.hidden && !popover.contains(event.target) && !event.target.closest('.view-rating-button')) {
            closePopover();
        }
    });
});
</script>

<?php require "_foot.php"; ?>
