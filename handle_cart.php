<?php
// 1. Load configuration utilities, session variables, and global $_db
require '_base.php'; 
header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['message' => 'Please log in first.']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$cart_id = null;

// 2. Fetch or create the active user cart wrapper
$stmt_cart = $_db->prepare("SELECT cart_id FROM carts WHERE user_id = ? LIMIT 1");
$stmt_cart->execute([$user_id]);
$cart_data = $stmt_cart->fetch(); 

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
        $requested_size = req('size');

        if ($product_id <= 0) {
            echo json_encode(["message" => "Error: Invalid product identification."]);
            exit;
        }

        // Fetch stock for requested size, or grab the default variant if size isn't passed (e.g. accessories)
        if (!empty($requested_size)) {
            $stmt_stock = $_db->prepare("SELECT size, stock FROM product_variants WHERE product_id = ? AND size = ? LIMIT 1");
            $stmt_stock->execute([$product_id, $requested_size]);
            $variant = $stmt_stock->fetch();
        } else {
            $stmt_stock = $_db->prepare("SELECT size, stock FROM product_variants WHERE product_id = ? LIMIT 1");
            $stmt_stock->execute([$product_id]);
            $variant = $stmt_stock->fetch();
        }

        if (!$variant) {
            echo json_encode(["message" => "Error: Selected size variant not found in stock matrix."]);
            exit;
        }

        $size = $variant->size; // Use exact matched size string

        // Check existing item in user cart for this EXACT product AND size
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

        // Validate combined cart total against actual size variant stock
        if ($combined_total > $variant->stock) {
            echo json_encode(["message" => "Cannot add quantity. Total would exceed available stock for {$size} ({$variant->stock} units available)."]);
            exit;
        }

        if ($existing_item) {
            $stmt_update = $_db->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
            $stmt_update->execute([$combined_total, $existing_item->cart_item_id]);
            echo json_encode(["message" => "Product quantity updated in cart."]);
        } else {
            $stmt_insert = $_db->prepare("INSERT INTO cart_items (cart_id, product_id, size, quantity) VALUES (?, ?, ?, ?)");
            $stmt_insert->execute([$cart_id, $product_id, $size, $quantity]);
            echo json_encode(["message" => "Item added to cart successfully."]);
        }
        break;

    case "update_quantity":
        $cart_item_id = intval(req('cart_item_id'));
        $quantity = intval(req('quantity'));

        if ($quantity <= 0) {
            $stmt_del = $_db->prepare("DELETE FROM cart_items WHERE cart_item_id = ?");
            $stmt_del->execute([$cart_item_id]);
            echo json_encode(["message" => "Item removed from cart."]);
            exit;
        }

        // Validate updated quantity against variant stock in DB
        $stmt_check = $_db->prepare("
            SELECT ci.cart_item_id, pv.stock, ci.size
            FROM cart_items ci
            JOIN product_variants pv ON ci.product_id = pv.product_id AND ci.size = pv.size
            WHERE ci.cart_item_id = ?
            LIMIT 1
        ");
        $stmt_check->execute([$cart_item_id]);
        $item = $stmt_check->fetch();

        if (!$item) {
            echo json_encode(["message" => "Error: Cart item variant context not found."]);
            exit;
        }

        if ($quantity > $item->stock) {
            echo json_encode(["message" => "Cannot update quantity. Only {$item->stock} units available for {$item->size}."]);
            exit;
        }

        $stmt_qty = $_db->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
        if ($stmt_qty->execute([$quantity, $cart_item_id])) {
            echo json_encode(["message" => "Cart item quantity updated."]);
        } else {
            echo json_encode(["message" => "Unable to modify cart quantity."]);
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