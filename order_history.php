<?php

require_once "_base.php";

$_title = "My Orders";

if (!isset($_SESSION['users']->user_id)) {
    redirect('login.php');
}

$user_id = (int) $_SESSION['users']->user_id;  

//Update variables by getting from URL
$status_filter = get("status", "all");
$page = get("page", "1");

if (!ctype_digit($page) || (int) $page < 1) {
    $page = 1;
} else {
    $page = (int) $page;
}

$orders_per_page = 8;
$allowed_statuses = [
    "all",
    "pending",
    "shipped",
    "completed",
    "cancelled"
];

//Display all status tab if $status_filter isn't in $allowed_statuses
if (!in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = "all";
}

//Customer clicks Order Received button
if (is_post() && post("action") === "confirm_order_received") {
    $received_order_id = post("order_id");

    if (!ctype_digit($received_order_id)) {
        redirect("order_history.php?status=shipped");
    }

    $received_order_id = (int) $received_order_id;

    $sql = "UPDATE orders
            SET status = 'completed', completed_at = NOW()
            WHERE order_id = :order_id
              AND user_id = :user_id
              AND status = 'shipped'";

    $stmt = $_db->prepare($sql);

    $stmt->execute([
        "order_id" => $received_order_id,
        "user_id" => $user_id
    ]);

    //Update status successfully
    if ($stmt->rowCount() > 0) {
        redirect("order_history.php?status=completed");
    }

    //Update status failed
    redirect("order_history.php?status=shipped");
}

//Customer clicks Cancel Order button
if (is_post() && post("action") === "cancel_order") {
    $cancel_order_id = post("order_id");

    if (!ctype_digit($cancel_order_id)) {
        redirect("order_history.php?status=pending");
    }

    $cancel_order_id = (int) $cancel_order_id;

    try {
        $_db->beginTransaction();

        //Lock the row until transaction ends
        $stmt_order = $_db->prepare("
            SELECT points_used, points_earned
            FROM orders
            WHERE order_id = :order_id
              AND user_id = :user_id
              AND status = 'pending'
            FOR UPDATE");

        $stmt_order->execute([
            "order_id" => $cancel_order_id,
            "user_id" => $user_id
        ]);
        $cancelled_order = $stmt_order->fetch();

        //If order not exists
        if (!$cancelled_order) {
            $_db->rollBack();  //Undo transaction
            redirect("order_history.php?status=pending");
        }

        // Mark the order cancelled first. The status condition makes this safe
        // if another request has already processed the cancellation.
        $stmt_cancel = $_db->prepare("
            UPDATE orders
            SET status = 'cancelled', cancelled_at = NOW()  
            WHERE order_id = :order_id
                AND user_id = :user_id
                AND status = 'pending'
        ");
        $stmt_cancel->execute([
            "order_id" => $cancel_order_id,
            "user_id" => $user_id
        ]);

        if ($stmt_cancel->rowCount() !== 1) {
            throw new PDOException('The order was already processed.');
        }

        // get the items in the order
        $stmt_items = $_db->prepare("
            SELECT product_id, size, quantity
            FROM order_items
            WHERE order_id = ?
            FOR UPDATE
        ");
        $stmt_items->execute([$cancel_order_id]);
        $cancelled_items = $stmt_items->fetchAll();

        $stmt_restore_stock = $_db->prepare("
            UPDATE product_variants
            SET stock = stock + ?
            WHERE product_id = ? AND size = ?
        ");
 // Restore stock for each item in the order
        foreach ($cancelled_items as $item) {
            $stmt_restore_stock->execute([$item->quantity, $item->product_id, $item->size]);

            if ($stmt_restore_stock->rowCount() !== 1) {
                throw new PDOException('Unable to restore stock for an order item.');
            }
        }

        //Restore reward points
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

            /*Set feedback_submitted = true if feedback exists*/
            EXISTS (
                SELECT 1
                FROM order_feedback AS f
                WHERE f.order_id = o.order_id
                  AND f.user_id = o.user_id
            ) AS feedback_submitted,

            oi.order_item_id,
            oi.product_id,
            oi.quantity,
            oi.size AS size,
            oi.price AS item_price,
            p.name AS product_name,
            p.image_url

        FROM orders AS o

        LEFT JOIN order_items AS oi
            ON o.order_id = oi.order_id

        LEFT JOIN products AS p
            ON oi.product_id = p.product_id

        /*Ensure customer only sees their own orders*/
        WHERE o.user_id = :user_id";

//Add status condition to $sql to filter & display orders that match the status condition
if ($status_filter !== "all") {
    $sql .= " AND o.status = :status";
}

//Sort orders
$sql .= " ORDER BY
            o.order_date DESC,
            o.order_id DESC,
            oi.order_item_id ASC";

            $stmt = $_db->prepare($sql);

            $params = [
                "user_id" => $user_id
            ];
            
            //Only if a specific status tab is selected
            if ($status_filter !== "all") {
                $params["status"] = $status_filter;
            }
            
            $stmt->execute($params);

//Retrieve all matching rows & store into $rows array
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$orders = [];

foreach ($rows as $row) {
    $order_id = $row["order_id"];  //Store current order ID

    //Store order info if the current order_id not exists yet
    if (!isset($orders[$order_id])) {
        $orders[$order_id] = [
            "order_id" => $row["order_id"],
            "order_date" => $row["order_date"],
            "status" => $row["status"],
            "total_amount" => $row["total_amount"],
            "feedback_submitted" => (bool) $row["feedback_submitted"],
            "items" => []
        ];
    }

    //Group and Store each product of the order
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

$total_orders = count($orders);
$total_pages = max(1, (int) ceil($total_orders / $orders_per_page));

if ($page > $total_pages) {
    $page = $total_pages;
}

//Pagination
$orders = array_slice(
    $orders,
    ($page - 1) * $orders_per_page, //starting position
    $orders_per_page, //howmany orders to take
    true
);

require "_head.php";
?>

<!-- Change URL based on the clicked status tab -->
<nav class="order-tabs">  

    <!-- active highlights the currently selected tab --> 
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

    <!--If $orders array is not empty*-->
    <?php if ($orders): ?>
        <?php foreach ($orders as $order): ?>

            <article class="order-card">

            <div class="order-left">

        <!--If the order's items[] is not empty-->
        <?php if ($order["items"]): ?>

            <div class="order-items-list">

        <?php foreach ($order["items"] as $item_index => $item): ?>

            <?php
            $productName = $item["product_name"] ?: "Unknown Product";

            $imageUrl = $item["image_url"] ?: "https://placehold.co/100x100?text=No+Image";

            $quantity = (int) $item["quantity"];
            ?>

            <!--First 2 items (index 0 & 1) are visible, the rest is hidden-->
            <div
                class="order-item-preview<?= $item_index >= 2 ? ' additional-order-item' : '' ?>"
                <?= $item_index >= 2 ? 'hidden' : '' ?>
            >

                <!--Item Image-->
                <img
                    class="order-image"
                    src="<?= encode($imageUrl) ?>"
                    alt="<?= encode($productName) ?>"
                >

                <div class="order-info">

                <!--Display Product Title-->
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

        <!--Display View More button if items more than 2-->
        <?php if (count($order["items"]) > 2): ?>
            <button
                type="button"
                class="view-more-products"
                aria-expanded="false" 
            >
                View More
            </button>
        <?php endif; ?>

        <div class="order-meta-section">
            <p class="order-meta">
                <?= date("d M Y, h:i A", strtotime($order["order_date"])) ?>
            </p>

            <!--View Details button-->
            <a
                class="view-details"
                href="order_detail.php?id=<?= urlencode($order["order_id"]) ?>"
            >
                View Details
            </a>

        </div>

    </div>
    <!--If order has no product-->
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
            <!--Display the status text inside the colourbox--> 
            <?= ucfirst(encode($order["status"])) ?>
        </span>
    </div>

    <div class="amount-section">

        <span class="amount-label">
            Total Amount Paid
        </span>

        <strong class="amount">
            RM <?= number_format((float) $order["total_amount"], 2) ?>
        </strong>

        <!--Order Received button-->
        <?php if ($order["status"] === "shipped"): ?>
            <form
                method="POST"
                class="received-form"
                onsubmit="return confirm('Confirm that you have received this order?');"
            >
                <input
                    type="hidden"
                    name="action"
                    value="confirm_order_received"
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

        <!--Cancel Order button-->
        <?php if ($order["status"] === "pending"): ?>
        <form
            method="POST"
            class="cancel-form"
            onsubmit="return confirm('Are you sure you want to cancel this order?');"
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
            <!--Display View Feedback if feedback_submitted isn't null-->
            <?php if ($order["feedback_submitted"]): ?>
                <a 
                    class="feedback-button" 
                    href="view_feedback.php?id=<?= urlencode($order["order_id"]) ?>"
                >
                    View Feedback
                </a>

            <?php else: ?>
                 <a 
                    class="feedback-button" 
                    href="order_feedback.php?id=<?= urlencode($order["order_id"]) ?>"
                >
                    Add Feedback
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

<?php if ($total_pages > 1): ?>
    <nav class="order-pagination" aria-label="Order history pages">
        <!--Display Previous button-->
        <?php if ($page > 1): ?>
            <a
                class="pagination-link pagination-direction"
                href="order_history.php?status=<?= urlencode($status_filter) ?>&amp;page=<?= $page - 1 ?>"
                rel="prev"
            >
                Previous
            </a>
        <?php endif; ?>

        <!--Display PageNumber button-->
        <?php for ($page_number = 1; $page_number <= $total_pages; $page_number++): ?>
            <a
                class="pagination-link <?= $page_number === $page ? "active" : "" ?>"
                href="order_history.php?status=<?= urlencode($status_filter) ?>&amp;page=<?= $page_number ?>"
                <?= $page_number === $page ? 'aria-current="page"' : '' ?>
            >
                <?= $page_number ?>
            </a>
        <?php endfor; ?>

        <!--Display Next button-->
        <?php if ($page < $total_pages): ?>
            <a
                class="pagination-link pagination-direction"
                href="order_history.php?status=<?= urlencode($status_filter) ?>&amp;page=<?= $page + 1 ?>"
                rel="next"
            >
                Next
            </a>
        <?php endif; ?>
    </nav>

<?php endif; ?>

<script>
    //View More/View Less
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.view-more-products').forEach((button) => {
            //Runs when customer clicks View More
            button.addEventListener('click', () => {
                //Find orderItemsList of current order
                const orderItemsList = button.closest('.order-items-list');
                //Find hidden products
                const additionalItems = orderItemsList.querySelectorAll('.additional-order-item');
                //aria-expanded initially is false
                const isExpanded = button.getAttribute('aria-expanded') === 'true';

                additionalItems.forEach((item) => {
                    item.hidden = isExpanded;
                });

                //Reverse the current state of isExpanded
                button.setAttribute('aria-expanded', String(!isExpanded));
                //isExpanded is the state before the click
                button.textContent = isExpanded ? 'View More' : 'View Less';
            });
        });
    });
</script>

<?php require "_foot.php"; ?>
