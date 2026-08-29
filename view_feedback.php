<?php

require_once "_base.php";

$_title = "View Feedback";
$_hide_page_title = true;

if (!isset($_SESSION['users']->user_id)) {
    redirect('login.php');
}

if ($_SESSION['users']->role !== 'member') {
    redirect('admin_orders.php');
}

$user_id = (int) $_SESSION['users']->user_id;

// Check order ID
if (!isset($_GET["id"]) || !ctype_digit($_GET["id"])) {
    die("Invalid order ID.");
}

$order_id = (int) $_GET["id"];

// Retrieve customer's feedback
$sql = "SELECT
            f.order_id,
            f.rating,
            f.feedback,
            f.created_at
        FROM order_feedback AS f
        INNER JOIN orders AS o
            ON f.order_id = o.order_id
        WHERE f.order_id = :order_id
          AND f.user_id = :user_id
          AND o.status = 'completed'";

$stmt = $_db->prepare($sql);

$stmt->execute([
    "order_id" => $order_id,
    "user_id" => $user_id
]);

$feedback = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if feedback exists
if (!$feedback) {
    temp("info", "Feedback not found.");
    redirect("order_history.php?status=completed");
}

$rating = (int) $feedback["rating"];

require "_head.php";
?>

<div class="order-detail-container">

    <div class="detail-card">

        <div class="card-header">
            <h2>My Order Feedback</h2>
        </div>

        <table class="order-detail-table">

            <tr>
                <th>Order ID</th>
                <td>
                    #<?= encode($feedback["order_id"]) ?>
                </td>
            </tr>

            <tr>
                <th>Star Rating</th>
                <td>
                    <div class="view-star-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= $rating): ?>
                                <span class="view-star filled">★</span>
                            <?php else: ?>
                                <span class="view-star empty">★</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>

                    <span class="rating-value">
                        <?= $rating ?>/5
                    </span>
                </td>
            </tr>

            <tr>
                <th>Feedback</th>
                <td>
                    <?php if (!empty($feedback["feedback"])): ?>
                        <?= nl2br(encode($feedback["feedback"])) ?>
                    <?php else: ?>
                        <span class="no-feedback">
                            No written feedback was provided.
                        </span>
                    <?php endif; ?>
                </td>
            </tr>

            <tr>
                <th>Date Submitted</th>
                <td>
                    <?php if (!empty($feedback["created_at"])): ?>
                        <?= date("d M Y, h:i A", strtotime($feedback["created_at"])) ?>
                    <?php else: ?>
                        Not available
                    <?php endif; ?>
                </td>
            </tr>

        </table>

        <div class="button-area">
            <a class="back-button"
               href="order_history.php?status=completed">
                Back to Completed Orders
            </a>
        </div>

    </div>

</div>

<?php require "_foot.php"; ?>