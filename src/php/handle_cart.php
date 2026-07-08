<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for testing
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ==========================================
// 1. DATABASE CONFIGURATION & CONNECTION
// ==========================================
$host = "localhost";
$db_name = "waterbottle_shop";
$username = "root";
$password = "";
$conn = null;

try {
    $conn = new PDO("mysql:host=" . $host . ";dbname=" . $db_name, $username, $password);
    // Set error mode to exception so we can catch SQL structural errors
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("set names utf8");
} catch (PDOException $exception) {
    echo json_encode(array("message" => "Database connection failed: " . $exception->getMessage()));
    exit();
}

// ==========================================
// 2. DEFINE DUMMY USER ID (Fulfilling Foreign Key)
// ==========================================
$user_id = 1; 

// ==========================================
// 3. FLOW CONTROL: GET OR CREATE THE USER'S CART
// ==========================================
$cart_id = null;

try {
    $query_cart = "SELECT cart_id FROM carts WHERE user_id = ? LIMIT 1";
    $stmt_cart = $conn->prepare($query_cart);
    $stmt_cart->execute([$user_id]);
    $cart_row = $stmt_cart->fetch(PDO::FETCH_ASSOC);

    if ($cart_row) {
        $cart_id = $cart_row['cart_id'];
    } else {
        $insert_cart = "INSERT INTO carts (user_id) VALUES (?)";
        $stmt_insert = $conn->prepare($insert_cart);
        if ($stmt_insert->execute([$user_id])) {
            $cart_id = $conn->lastInsertId();
        } else {
            echo json_encode(array("message" => "SQL Error: Unable to create row in carts table."));
            exit();
        }
    }
} catch (PDOException $e) {
    echo json_encode(array("message" => "Cart Table Exception: " . $e->getMessage()));
    exit();
}

// ==========================================
// 4. ACTION ROUTER & PROCESSOR
// ==========================================
if (isset($_POST["action"])) {
    switch ($_POST["action"]) {
        
        case "add_to_cart":
            $product_id = isset($_POST["product_id"]) ? intval($_POST["product_id"]) : 0;
            $quantity = isset($_POST["quantity"]) ? intval($_POST["quantity"]) : 1;

            // Strict Validation Check
            if ($product_id <= 0) {
                echo json_encode(array("message" => "Error: Received product_id is 0 or invalid. Check your HTML data-product_id attribute."));
                exit();
            }

            try {
                // Check if the item already exists in this cart
                $query_check = "SELECT cart_item_id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1";
                $stmt_check = $conn->prepare($query_check);
                $stmt_check->execute([$cart_id, $product_id]);
                $item = $stmt_check->fetch(PDO::FETCH_ASSOC);

                if ($item) {
                    $new_quantity = $item['quantity'] + $quantity;
                    $query_update = "UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?";
                    $stmt_update = $conn->prepare($query_update);
                    
                    if ($stmt_update->execute([$new_quantity, $item['cart_item_id']])) {
                        echo json_encode(array("message" => "Product quantity updated in cart."));
                    } else {
                        echo json_encode(array("message" => "Failed to execute UPDATE statement on database."));
                    }
                } else {
                    $query_add = "INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)";
                    $stmt_add = $conn->prepare($query_add);
                    
                    if ($stmt_add->execute([$cart_id, $product_id, $quantity])) {
                        echo json_encode(array("message" => "Product added to cart."));
                    } else {
                        echo json_encode(array("message" => "Failed to execute INSERT statement on database."));
                    }
                }
            } catch (PDOException $e) {
                // This catches foreign key failures, structural mismatch, or unknown constraints
                echo json_encode(array("message" => "Database Transaction Exception: " . $e->getMessage()));
            }
            break;

        case "update_quantity":
            // (Keeping validation identical to your structured workflow)
            $cart_item_id = intval($_POST["cart_item_id"]);
            $quantity = intval($_POST["quantity"]);

            $query_qty = "UPDATE cart_items SET quantity = ? WHERE cart_item_id = ? AND cart_id = ?";
            $stmt_qty = $conn->prepare($query_qty);
            
            if ($stmt_qty->execute([$quantity, $cart_item_id, $cart_id])) {
                echo json_encode(array("message" => "Cart item quantity updated."));
            } else {
                echo json_encode(array("message" => "Unable to update cart item quantity."));
            }
            break;

        case "remove_from_cart":
            $cart_item_id = intval($_POST["cart_item_id"]);

            $query_del = "DELETE FROM cart_items WHERE cart_item_id = ? AND cart_id = ?";
            $stmt_del = $conn->prepare($query_del);
            
            if ($stmt_del->execute([$cart_item_id, $cart_id])) {
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