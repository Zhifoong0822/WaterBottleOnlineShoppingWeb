<?php
// Start session
session_start();

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php"); // Redirect to login page
    exit();
}

// include database and object files
include_once "src/php/DbConnection.php";
include_once "src/php/Product.php";
include_once "src/php/Cart.php";
include_once "src/php/CartItem.php";
include_once "src/php/Order.php";
include_once "src/php/OrderItem.php";

// get database connection
$database = new DbConnection();
$db = $database->getConnection();

// pass connection to objects
$product = new Product($db);
$cart = new Cart($db);
$cartItem = new CartItem($db);
$order = new Order($db);
$orderItem = new OrderItem($db);

$user_id = $_SESSION["user_id"];

// Get user's cart
$cart->user_id = $user_id;
$cart_exists = $cart->getCartByUserId();

$cart_items = [];
$total_amount = 0;

if ($cart_exists) {
    $stmt_cart_items = $cartItem->getCartItemsByCartId();
    $cart_items_num = $stmt_cart_items->rowCount();

    if ($cart_items_num > 0) {
        while ($row = $stmt_cart_items->fetch(PDO::FETCH_ASSOC)) {
            $cart_items[] = $row;
            $total_amount += $row["price"] * $row["quantity"];
        }
    }
}

$page_title = "Shopping Cart";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="src/css/style.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
</head>
<body>
    <header>
        <h1>Your Shopping Cart</h1>
        <nav>
            <ul>
                <li><a href="index.php">Products</a></li>
                <li><a href="cart.php">View Cart</a></li>
                <?php if (isset($_SESSION["user_id"])) : ?>
                    <li><a href="logout.php">Logout</a></li>
                <?php else : ?>
                    <li><a href="login.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    <main>
        <?php if (!empty($cart_items)) : ?>
            <div class="cart-items">
                <?php foreach ($cart_items as $item) : ?>
                    <div class="cart-item-card">
                        <img src="<?php echo $item["image_url"]; ?>" alt="<?php echo $item["name"]; ?>">
                        <h3><?php echo $item["name"]; ?></h3>
                        <p>Price: RM<?php echo number_format($item["price"], 2); ?></p>
                        <div class="quantity-controls">
                            <button class="update-quantity" data-cart_item_id="<?php echo $item["cart_item_id"]; ?>" data-product_id="<?php echo $item["product_id"]; ?>" data-change="-1">-</button>
                            <input type="number" value="<?php echo $item["quantity"]; ?>" min="1" class="item-quantity" data-cart_item_id="<?php echo $item["cart_item_id"]; ?>">
                            <button class="update-quantity" data-cart_item_id="<?php echo $item["cart_item_id"]; ?>" data-product_id="<?php echo $item["product_id"]; ?>" data-change="1">+</button>
                            <button class="remove-item" data-cart_item_id="<?php echo $item["cart_item_id"]; ?>">Remove</button>
                        </div>
                        <p>Subtotal: RM<?php echo number_format($item["price"] * $item["quantity"], 2); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="cart-summary">
                <h2>Total: RM<?php echo number_format($total_amount, 2); ?></h2>
                <button id="checkout-button">Checkout</button>
            </div>
        <?php else : ?>
            <p>Your cart is empty.</p>
        <?php endif; ?>
    </main>
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Water Bottle Shop</p>
    </footer>

    <script src="src/js/script.js"></script>
</body>
</html>