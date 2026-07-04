<?php
session_start();

include_once "DbConnection.php";
include_once "Product.php";
include_once "Cart.php";
include_once "CartItem.php";
include_once "Order.php";
include_once "OrderItem.php";

$database = new DbConnection();
$db = $database->getConnection();

$product = new Product($db);
$cart = new Cart($db);
$cartItem = new CartItem($db);
$order = new Order($db);
$orderItem = new OrderItem($db);

if (!isset($_SESSION["user_id"])) {
    echo json_encode(array("message" => "User not logged in."));
    exit();
}

$user_id = $_SESSION["user_id"];
$cart->user_id = $user_id;

// Get user's cart
if (!$cart->getCartByUserId()) {
    echo json_encode(array("message" => "Cart not found."));
    exit();
}

$cart_id = $cart->cart_id;

// Get cart items
$cartItem->cart_id = $cart_id;
$stmt_cart_items = $cartItem->getCartItemsByCartId();
$num_cart_items = $stmt_cart_items->rowCount();

if ($num_cart_items > 0) {
    $total_amount = 0;
    $checkout_items = [];

    while ($row = $stmt_cart_items->fetch(PDO::FETCH_ASSOC)) {
        $checkout_items[] = $row;
        $total_amount += $row["price"] * $row["quantity"];
    }

    // Create order
    $order->user_id = $user_id;
    $order->total_amount = $total_amount;
    $order->status = "pending"; // Default status

    if ($order->create()) {
        $order_id = $order->order_id;

        // Add cart items to order items
        foreach ($checkout_items as $item) {
            $orderItem->order_id = $order_id;
            $orderItem->product_id = $item["product_id"];
            $orderItem->quantity = $item["quantity"];
            $orderItem->price = $item["price"];
            $orderItem->create();

            // Optionally, update product stock (decrease stock)
            // $product->id = $item['product_id'];
            // $product->readOne(); // get current stock
            // $product->stock -= $item['quantity'];
            // $product->updateStock();
        }

        // Clear the cart after successful checkout
        $cartItem->cart_id = $cart_id;
        $cartItem->deleteByCartId();

        echo json_encode(array("message" => "Order placed successfully.", "order_id" => $order_id));
    } else {
        echo json_encode(array("message" => "Unable to place order."));
    }
} else {
    echo json_encode(array("message" => "Cart is empty."));
}
?>