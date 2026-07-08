<?php
session_start();

// Database connection
$host = "localhost";
$db_name = "waterbottle_shop";
$username = "root";
$password = "";
$conn = null;

try {
    $conn = new PDO("mysql:host=" . $host . ";dbname=" . $db_name, $username, $password);
    $conn->exec("set names utf8");
} catch (PDOException $exception) {
    error_log("Connection error: " . $exception->getMessage());
    die("Database connection failed.");
}

// Product functions
function getAllProducts($conn) {
    $query = "SELECT * FROM products";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    return $stmt;
}

$page_title = "Products";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
</head>
<body>
    <header>
        <h1>Water Bottle Shop</h1>
        <nav>
            <ul>
                <li><a href="../../index.php">Products</a></li>
                <li><a href="./cart_view.php">View Cart</a></li>
            </ul>
        </nav>
    </header>
    <main>
        <?php
        $stmt = getAllProducts($conn);
        $num = $stmt->rowCount();

        echo "<div class='product-grid'>";
        if($num > 0) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                echo "<div class='product-card'>";
                    echo "<img src='" . $image_url . "' alt='" . $name . "'>";
                    echo "<h3>{$name}</h3>";
                    echo "<p>{$description}</p>";
                    echo "<p>Price: RM" . number_format($price, 2) . "</p>";
                    echo "<p>Stock: {$stock}</p>";
                    echo "<button class='add-to-cart' data-product_id='{$product_id}'>Add to Cart</button>";
                echo "</div>";
            }
        } else {
            echo "<div class='col-md-12'>No products found.</div>";
        }
        echo "</div>";
        ?>
    </main>
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Water Bottle Shop</p>
    </footer>

    <script src="../js/script.js"></script>
</body>
</html>