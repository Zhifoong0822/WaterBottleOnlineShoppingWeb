<?php
session_start();

// ==========================================
// 1. DATABASE CONNECTION (Procedural PDO)
// ==========================================
$host = "localhost";
$db_name = "waterbottle_shop";
$username = "root";
$password = "";
$conn = null;

try {
    $conn = new PDO("mysql:host=" . $host . ";dbname=" . $db_name, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("set names utf8");
} catch (PDOException $exception) {
    die("Database connection failed: " . $exception->getMessage());
}

// ==========================================
// 2. LOCAL INLINE FUNCTIONS (Passing $conn)
// ==========================================

function getCartByUserId($conn, $user_id) {
    $query = "SELECT cart_id FROM carts WHERE user_id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC); // Return the row array directly
}

function getCartItemsByCartId($conn, $cart_id) {
    // Structural SQL JOIN to pull product metrics down smoothly
    $query = "SELECT ci.cart_item_id, ci.product_id, ci.quantity, p.name, p.price, p.image_url 
              FROM cart_items ci
              JOIN products p ON ci.product_id = p.product_id
              WHERE ci.cart_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$cart_id]);
    return $stmt;
}

// ==========================================
// 3. CORE PROCESSING LOGICFLOW
// ==========================================
$user_id = 1; 
$cart_id = null;
$cart_items = [];
$total_amount = 0;

// Execute inline queries
$cart_data = getCartByUserId($conn, $user_id);

if ($cart_data) {
    $cart_id = $cart_data["cart_id"];

    $stmt_items = getCartItemsByCartId($conn, $cart_id);
    $cart_items_num = $stmt_items->rowCount();

    if ($cart_items_num > 0) {
        while ($row = $stmt_items->fetch(PDO::FETCH_ASSOC)) {
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
    
    <link rel="stylesheet" href="/src/css/style.css">
    
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
</head>
<body>
    <header>
        <h1>Your Shopping Cart</h1>
        <nav>
            <ul>
                <li><a href="products.php">Products</a></li>
                <li><a href="cart_view.php">View Cart</a></li>
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
                            <input type="number" value="<?php echo $item["quantity"]; ?>" min="1" class="item-quantity" data-cart_item_id="<?php echo $item["cart_item_id"]; ?>" readonly>
                            <button class="update-quantity" data-cart_item_id="<?php echo $item["cart_item_id"]; ?>" data-product_id="<?php echo $item["product_id"]; ?>" data-change="1">+</button>
                            <button class="remove-item" data-cart_item_id="<?php echo $item["cart_item_id"]; ?>">Remove</button>
                        </div>
                        <p>Subtotal: RM<?php echo number_format($item["price"] * $item["quantity"], 2); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="cart-summary">
                <h2>Total: RM<?php echo number_format($total_amount, 2); ?></h2>
            </div>
        <?php else : ?>
            <p>Your cart is empty.</p>
        <?php endif; ?>
    </main>
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Water Bottle Shop</p>
    </footer>

    <script src="../js/script.js"></script>
</body>
</html>