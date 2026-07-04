<?php
// Start session 
session_start();

// Check if user is logged in, if not redirect to login page (assuming login.php exists)
// For now, let's set a dummy user_id for testing purposes.
// In a real application, this would come from the session after a successful login.
if (!isset($_SESSION["user_id"])) {
    $_SESSION["user_id"] = 1; // Dummy user ID for demonstration (member1)
    $_SESSION["role"] = "member"; // Dummy role
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

// set page header
$page_title = "Products";
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
        <h1>Water Bottle Shop</h1>
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
        <?php
        // retrieve products from database
        $stmt = $product->readAll();
        $num = $stmt->rowCount();

        echo "<div class='product-grid'>";
        if($num>0){
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){
                extract($row);
                echo "<div class='product-card'>";
                    echo "<img src='" . $image_url . "' alt='" . $name . "'>";
                    echo "<h3>{$name}</h3>";
                    echo "<p>{$description}</p>
";
                    echo "<p>Price: RM" . number_format($price, 2) . "</p>";
                    echo "<p>Stock: {$stock}</p>";
                    echo "<button class='add-to-cart' data-product_id='{$product_id}'>Add to Cart</button>";
                echo "</div>";
            }
        }else{
            echo "<div class='col-md-12'>No products found.</div>";
        }
        echo "</div>";
        ?>
    </main>
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Water Bottle Shop</p>
    </footer>

    <script src="src/js/script.js"></script>
</body>
</html>