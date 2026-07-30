<?php

require_once "_base.php";

$_title = "Order Feedback";

// Temporary customer ID for testing.
// Replace with the logged-in user ID later.
$user_id = 1;

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

/*
 * Retrieve an existing rating, if the customer
 * previously submitted feedback for this order.
 */
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

/*
 * Process feedback submission.
 */
if (is_post()) {

    $rating = post("rating");
    $feedback = post("feedback");

    if (
        !ctype_digit($rating) ||
        (int) $rating < 1 ||
        (int) $rating > 5
    ) {
        $errors["rating"] = "Please select a rating from 1 to 5 stars.";
    }

    if (strlen($feedback) > 1000) {
        $errors["feedback"] =
            "Feedback must not exceed 1000 characters.";
    }

    if (!$errors) {

        /*
         * Because order_id and user_id are unique together,
         * this inserts new feedback or updates existing feedback.
         */
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
                Feedback for Order #<?= encode($order["order_id"]) ?>
            </h2>
        </div>

        <div class="feedback-card-body">

            <p class="feedback-description">
                Rate your completed order and optionally share
                your experience.
            </p>

            <form method="POST">

                <fieldset class="rating-fieldset">

                    <legend>Star Rating</legend>

                    <div class="star-rating">

                        <?php for ($star = 5; $star >= 1; $star--): ?>

                            <input
                                type="radio"
                                id="star<?= $star ?>"
                                name="rating"
                                value="<?= $star ?>"
                                <?= (string) $rating === (string) $star
                                    ? "checked"
                                    : "" ?>
                            >

                            <label
                                for="star<?= $star ?>"
                                title="<?= $star ?> stars"
                            >
                                ★
                            </label>

                        <?php endfor; ?>

                    </div>

                    <?php if (isset($errors["rating"])): ?>
                        <p class="feedback-error">
                            <?= encode($errors["rating"]) ?>
                        </p>
                    <?php endif; ?>

                </fieldset>

                <div class="feedback-form-group">

                    <label for="feedback">
                        Feedback
                        <span class="optional-text">(Optional)</span>
                    </label>

                    <textarea
                        id="feedback"
                        name="feedback"
                        rows="6"
                        maxlength="1000"
                        placeholder="Share your experience with this order..."
                    ><?= encode($feedback) ?></textarea>

                    <?php if (isset($errors["feedback"])): ?>
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
                        <?= $existing_feedback
                            ? "Update Feedback"
                            : "Submit Feedback" ?>
                    </button>

                    <a
                        class="feedback-back-button"
                        href="order_history.php?status=completed"
                    >
                        ← Back to Completed Orders
                    </a>

                </div>

            </form>

        </div>

    </div>

</section>

<?php require "_foot.php"; ?>