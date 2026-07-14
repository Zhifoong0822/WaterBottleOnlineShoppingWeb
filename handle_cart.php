<?php
session_start();
header("Content-Type: application/json");

ini_set("display_errors", 1);
error_reporting(E_ALL);

// =========================================================================
// 1. INLINE DATABASE CONNECTION (Replaces DbConnection.php)
// =========================================================================
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
    echo json_encode(array("message" => "Database connection failed."));
    exit();
}

$user_id = 1;
$cart_id = null;

// =========================================================================
// 2. INLINE PROCEDURAL CONTROL: GET OR CREATE USER CART (Replaces Cart.php)
// =========================================================================
$query_cart = "SELECT cart_id FROM carts WHERE user_id = :user_id LIMIT 1";
$stmt_cart = $conn->prepare($query_cart);
$stmt_cart->execute([':user_id' => $user_id]);
$cart = $stmt_cart->fetch(PDO::FETCH_ASSOC);

if ($cart) {
    $cart_id = $cart['cart_id'];
} else {
    $query_insert_cart = "INSERT INTO carts SET user_id = :user_id";
    $stmt_insert_cart = $conn->prepare($query_insert_cart);
    if ($stmt_insert_cart->execute([':user_id' => $user_id])) {
        $cart_id = $conn->lastInsertId();
    } else {
        echo json_encode(array("message" => "Unable to create cart."));
        exit();
    }
}

// =========================================================================
// 3. ACTIONS ROUTER VIA RAW INLINE PDO (Replaces CartItem.php queries)
// =========================================================================
if (isset($_POST["action"])) {
    switch ($_POST["action"]) {
        
        case "add_to_cart":
            $product_id = isset($_POST["product_id"]) ? intval($_POST["product_id"]) : 0;
            $quantity = isset($_POST["quantity"]) ? intval($_POST["quantity"]) : 1;

            if ($product_id <= 0) {
                echo json_encode(array("message" => "Error: Received product_id is invalid."));
                exit();
            }

            // 1. Fetch current stock levels for this product from the database
            $query_stock = "SELECT stock FROM products WHERE product_id = :product_id LIMIT 1";
            $stmt_stock = $conn->prepare($query_stock);
            $stmt_stock->execute([':product_id' => $product_id]);
            $product = $stmt_stock->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                echo json_encode(array("message" => "Error: Product not found."));
                exit();
            }
            $current_stock = intval($product['stock']);

            // 2. Check if this item already exists in the user's cart
            $query_check_item = "SELECT cart_item_id, quantity FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id LIMIT 1";
            $stmt_check = $conn->prepare($query_check_item);
            $stmt_check->execute([':cart_id' => $cart_id, ':product_id' => $product_id]);
            $item = $stmt_check->fetch(PDO::FETCH_ASSOC);

            // Determine what is currently in the cart (0 if it's a new item addition)
            $existing_quantity = $item ? intval($item['quantity']) : 0;
            
            // Calculate what the absolute new total total would be
            $combined_total = $existing_quantity + $quantity;

            // 3. Stock Check Checkpoint: Deny entry if the new total exceeds inventory levels
            if ($combined_total > $current_stock) {
                $allowed_remaining = $current_stock - $existing_quantity;
                
                if ($allowed_remaining <= 0) {
                    echo json_encode(array("message" => "You already have the maximum available stock ($current_stock units) in your cart."));
                } else {
                    echo json_encode(array("message" => "Cannot add quantity. You have $existing_quantity in cart, and only $allowed_remaining more units can be added."));
                }
                exit();
            }

            // 4. Update or Insert records once validation has successfully passed
            if ($item) {
                // UPDATE quantity inline
                $query_update = "UPDATE cart_items SET quantity = :quantity WHERE cart_item_id = :cart_item_id";
                $stmt_update = $conn->prepare($query_update);
                
                if ($stmt_update->execute([':quantity' => $combined_total, ':cart_item_id' => $item['cart_item_id']])) {
                    echo json_encode(array("message" => "Product quantity updated in cart."));
                } else {
                    echo json_encode(array("message" => "Unable to update cart item quantity."));
                }
            } else {
                // INSERT new item inline
                $query_add = "INSERT INTO cart_items SET cart_id = :cart_id, product_id = :product_id, quantity = :quantity";
                $stmt_add = $conn->prepare($query_add);
                
                if ($stmt_add->execute([':cart_id' => $cart_id, ':product_id' => $product_id, ':quantity' => $quantity])) {
                    echo json_encode(array("message" => "Product added to cart."));
                } else {
                    echo json_encode(array("message" => "Unable to add product to cart."));
                }
            }
            break;

        case "update_quantity":
            $cart_item_id = intval($_POST["cart_item_id"]);
            $quantity = intval($_POST["quantity"]);

            $query_qty = "UPDATE cart_items SET quantity = :quantity WHERE cart_item_id = :cart_item_id";
            $stmt_qty = $conn->prepare($query_qty);
            
            if ($stmt_qty->execute([':quantity' => $quantity, ':cart_item_id' => $cart_item_id])) {
                echo json_encode(array("message" => "Cart item quantity updated."));
            } else {
                echo json_encode(array("message" => "Unable to update cart item quantity."));
            }
            break;

        case "remove_from_cart":
            $cart_item_id = intval($_POST["cart_item_id"]);

            $query_del = "DELETE FROM cart_items WHERE cart_item_id = :cart_item_id";
            $stmt_del = $conn->prepare($query_del);
            
            if ($stmt_del->execute([':cart_item_id' => $cart_item_id])) {
                echo json_encode(array("message" => "Product removed from cart."));
            } else {
                echo json_encode(array("message" => "Unable to remove product from cart."));
            }
            break;

        default:
            echo json_encode(array("message" => "Invalid action."));
            break;
    }
} else {
    echo json_encode(array("message" => "No action specified."));
}
?>