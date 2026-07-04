<?php
session_start();

if (!isset($_SESSION["user_id"]) || !isset($_GET["order_id"])) {
    header("Location: login.php"); // Redirect to login or an error page
    exit();
}

$order_id = $_GET["order_id"];
$page_title = "Order Confirmation";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="src/css/style.css">
</head>
<body>
    <header>
        <h1>Order Confirmation</h1>
        <nav>
            <ul>
                <li><a href="index.php">Products</a></li>
                <li><a href="cart.php">View Cart</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>
    <main>
        <div class="confirmation-message">
            <h2>Thank You for Your Order!</h2>
            <p>Your order #<strong><?php echo htmlspecialchars($order_id); ?></strong> has been placed successfully.</p>
            <p>You will receive an email confirmation shortly.</p>
            <p><a href="index.php">Continue Shopping</a></p>
            <!-- Potentially add a link to order history here -->
        </div>
    </main>
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Water Bottle Shop</p>
    </footer>
</body>
</html>