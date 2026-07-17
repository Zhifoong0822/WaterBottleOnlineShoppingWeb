<?php
// 1. Load configuration utilities, session variables, and global $_db
require '_base.php'; 
header("Content-Type: application/json");

$user_id = 1; // Handled dynamically or mocked for your session persistence
$cart_id = null;

// 2. Fetch or create the active user cart wrapper
$stmt_cart = $_db->prepare("SELECT cart_id FROM carts WHERE user_id = ? LIMIT 1");
$stmt_cart->execute([$user_id]);
$cart_data = $stmt_cart->fetch(); // Fetches as an object via your framework configuration

if ($cart_data) {
    $cart_id = $cart_data->cart_id;
} else {
    $stmt_ins_cart = $_db->prepare("INSERT INTO carts (user_id) VALUES (?)");
    $stmt_ins_cart->execute([$user_id]);
    $cart_id = $_db->lastInsertId();
}

// 3. Process requests via the system framework req() utility
$action = req('action');

switch ($action) {
    
    case "add_to_cart":
        $product_id = intval(req('product_id'));
        $quantity = intval(req('quantity')) ?: 1;
        // Capture the explicit text value from the selected size chip group
        $size = req('size') ?: "Medium (18oz / 530ml)";

        if ($product_id <= 0) {
            echo json_encode(["message" => "Error: Invalid product tracking identification."]);
            exit;
        }

        // Fetch current physical stock levels from the database catalog matrix
        $stmt_stock = $_db->prepare("SELECT stock FROM products WHERE product_id = ? LIMIT 1");
        $stmt_stock->execute([$product_id]);
        $product = $stmt_stock->fetch();

        if (!$product) {
            echo json_encode(["message" => "Error: Product variant context not found."]);
            exit;
        }

        // =========================================================================
        // CRITICAL FIX: Match BOTH product_id AND size configuration columns
        // =========================================================================
        $stmt_check = $_db->prepare("
            SELECT cart_item_id, quantity 
            FROM cart_items 
            WHERE cart_id = ? AND product_id = ? AND size = ? 
            LIMIT 1
        ");
        $stmt_check->execute([$cart_id, $product_id, $size]);
        $existing_item = $stmt_check->fetch();

        $existing_quantity = $existing_item ? intval($existing_item->quantity) : 0;
        $combined_total = $existing_quantity + $quantity;

        // Inventory safety guard check
        if ($combined_total > $product->stock) {
            echo json_encode(["message" => "Cannot add quantity. Total would exceed available inventory ({$product->stock} units)."]);
            exit;
        }

        if ($existing_item) {
            // Increments ONLY if they added the exact same product with the exact same size
            $stmt_update = $_db->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
            $stmt_update->execute([$combined_total, $existing_item->cart_item_id]);
            echo json_encode(["message" => "Quantity updated for this size variant."]);
        } else {
            // Inserts a BRAND NEW row if the size string is different!
            $stmt_insert = $_db->prepare("INSERT INTO cart_items (cart_id, product_id, size, quantity) VALUES (?, ?, ?, ?)");
            $stmt_insert->execute([$cart_id, $product_id, $size, $quantity]);
            echo json_encode(["message" => "New size variant added to cart."]);
        }
        break;

    case "update_quantity":
        $cart_item_id = intval(req('cart_item_id'));
        $quantity = intval(req('quantity'));

        $stmt_qty = $_db->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
        if ($stmt_qty->execute([$quantity, $cart_item_id])) {
            echo json_encode(["message" => "Cart item quantity updated."]);
        } else {
            echo json_encode(["message" => "Unable to modify quantity layers."]);
        }
        break;

    case "remove_from_cart":
        $cart_item_id = intval(req('cart_item_id'));

        $stmt_del = $_db->prepare("DELETE FROM cart_items WHERE cart_item_id = ?");
        if ($stmt_del->execute([$cart_item_id])) {
            echo json_encode(["message" => "Item removed successfully."]);
        } else {
            echo json_encode(["message" => "Unable to clear target record rows."]);
        }
        break;

    default:
        echo json_encode(["message" => "Invalid framework action requested."]);
        break;
}