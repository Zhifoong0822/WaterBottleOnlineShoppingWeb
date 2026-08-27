<?php

require_once "_base.php";

$_title = "Order Feedback";
$_hide_page_title = true;

if (!isset($_SESSION['users']->user_id)) {
    redirect('login.php');
}

if ($_SESSION['users']->role !== 'member') {
    redirect('admin_orders.php');
}

$user_id = (int) $_SESSION['users']->user_id; //set user_id in the current session

if (!isset($_GET["id"]) || !ctype_digit($_GET["id"])) {
    die("Invalid order ID.");
}

$order_id = (int) $_GET["id"];

$errors = [];

/*
 * Check that:
 * 1. The order exists
 * 2. It belongs to this customer
 * 3. Its status is completed
 */
$sql = "SELECT
            order_id,
            order_date,
            total_amount,
            status
        FROM orders
        WHERE order_id = :order_id
          AND user_id = :user_id
          AND status = 'completed'";

$stmt = $_db->prepare($sql);

$stmt->execute([
    "order_id" => $order_id,
    "user_id" => $user_id
]);

$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die(
        "This order does not exist, does not belong to you, " .
        "or has not been completed."
    );
}

/*Retrieve an existing rating, if the customer previously submitted feedback for this order.*/
$sql = "SELECT rating, feedback
        FROM order_feedback
        WHERE order_id = :order_id
          AND user_id = :user_id";

$stmt = $_db->prepare($sql);

$stmt->execute([
    "order_id" => $order_id,
    "user_id" => $user_id
]);

$existing_feedback = $stmt->fetch(PDO::FETCH_ASSOC);

$rating = $existing_feedback["rating"] ?? "";
$feedback = $existing_feedback["feedback"] ?? "";

/*Process feedback submission.*/
if (is_post()) {

    $rating = post("rating");
    $feedback = post("feedback");

    if (!ctype_digit($rating) ||
        (int) $rating < 1 ||
        (int) $rating > 5
    ) {
        $errors["rating"] = "Please select a rating from 1 to 5 stars.";
    }

    if (strlen($feedback) > 1000) {
        $errors["feedback"] = "Feedback must not exceed 1000 characters.";
    }

    if (!$errors) {
        $sql = "INSERT INTO order_feedback (
                    order_id,
                    user_id,
                    rating,
                    feedback
                )
                VALUES (
                    :order_id,
                    :user_id,
                    :rating,
                    :feedback
                )
                ON DUPLICATE KEY UPDATE
                    rating = VALUES(rating),
                    feedback = VALUES(feedback)";

        $stmt = $_db->prepare($sql);

        $stmt->execute([
            "order_id" => $order_id,
            "user_id" => $user_id,
            "rating" => (int) $rating,
            "feedback" => $feedback !== "" ? $feedback : null
        ]);

        redirect("order_history.php?status=completed");
    }
}

require "_head.php";

?>

<section class="feedback-page">

    <div class="feedback-card">

        <div class="feedback-card-header">
            <h2>
                Order Feedback
            </h2>
        </div>

        <div class="feedback-card-body">

            <p class="feedback-description">
                Rate your completed order and optionally share your experience.
            </p>

            <form method="POST" id="feedbackForm">

                <fieldset class="rating-fieldset">

                    <legend>Star Rating</legend>

                    <div class="star-rating">

                        <input
                            type="radio"
                            id="star5"
                            name="rating"
                            value="5"
                            <?= $rating == 5 ? "checked" : "" ?>
                        >
                        <label for="star5">★</label>

                        <input
                            type="radio"
                            id="star4"
                            name="rating"
                            value="4"
                            <?= $rating == 4 ? "checked" : "" ?>
                        >
                        <label for="star4">★</label>

                        <input
                            type="radio"
                            id="star3"
                            name="rating"
                            value="3"
                            <?= $rating == 3 ? "checked" : "" ?>
                        >
                        <label for="star3">★</label>

                        <input
                            type="radio"
                            id="star2"
                            name="rating"
                            value="2"
                            <?= $rating == 2 ? "checked" : "" ?>
                        >
                        <label for="star2">★</label>

                        <input
                            type="radio"
                            id="star1"
                            name="rating"
                            value="1"
                            <?= $rating == 1 ? "checked" : "" ?>
                        >
                        <label for="star1">★</label>

                    </div>

                    <?php if (!empty($errors["rating"])): ?>
                        <p class="feedback-error">
                            <?= encode($errors["rating"]) ?>
                        </p>
                    <?php endif; ?>

                </fieldset>


                <div class="feedback-form-group">

                    <label for="feedback">
                        Feedback
                        <span class="optional-text">
                            (Optional)
                        </span>
                    </label>

                    <textarea
                        id="feedback"
                        name="feedback"
                        rows="6"
                        placeholder="Share your experience with this order..."
                    ><?= encode($feedback) ?></textarea>

                    <?php if (!empty($errors["feedback"])): ?>
                        <p class="feedback-error">
                            <?= encode($errors["feedback"]) ?>
                        </p>
                    <?php endif; ?>

                </div>


                <div class="feedback-actions">

                    <button
                        type="submit"
                        class="submit-feedback-button"
                    >
                        Submit Feedback
                    </button>

                    <a
                        href="order_history.php?status=completed"
                        class="feedback-back-button"
                    >
                        Back to Completed Orders
                    </a>

                </div>

            </form>

        </div>

    </div>

</section>

<script>
document.getElementById("feedbackForm").addEventListener("submit", function (event) {
    const confirmed = window.confirm(
        "Are you sure you want to submit this feedback?"
    );

    if (!confirmed) {
        event.preventDefault();
    }
});
</script>

<?php require "_foot.php"; ?>
