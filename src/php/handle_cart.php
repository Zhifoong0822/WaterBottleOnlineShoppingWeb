<?php
session_start();

include_once "DbConnection.php";
include_once "Product.php";
include_once "Cart.php";
include_once "CartItem.php";

$database = new DbConnection();
$db = $database->getConnection();

$product = new Product($db);
$cart = new Cart($db);
$cartItem = new CartItem($db);

if (!isset($_SESSION["user_id"])) {
    echo json_encode(array("message" => "User not logged in."));
    exit();
}

$user_id = $_SESSION["user_id"];
$cart->user_id = $user_id;

// Get or create cart for the user
if (!$cart->getCartByUserId()) {
    if (!$cart->create()) {
        echo json_encode(array("message" => "Unable to create cart."));
        exit();
    }
}

$cart_id = $cart->cart_id;

if (isset($_POST["action"])) {
    switch ($_POST["action"]) {
        case "add_to_cart":
            $product_id = $_POST["product_id"];
            $quantity = $_POST["quantity"];

            $cartItem->cart_id = $cart_id;
            $cartItem->product_id = $product_id;
            $cartItem->quantity = $quantity;

            if ($cartItem->getByCartIdAndProductId()) {
                // Item already in cart, update quantity
                $cartItem->quantity += $quantity;
                if ($cartItem->update()) {
                    echo json_encode(array("message" => "Product quantity updated in cart."));
                } else {
                    echo json_encode(array("message" => "Unable to update cart item quantity."));
                }
            } else {
                // Item not in cart, add new
                if ($cartItem->create()) {
                    echo json_encode(array("message" => "Product added to cart."));
                } else {
                    echo json_encode(array("message" => "Unable to add product to cart."));
                }
            }
            break;

        case "update_quantity":
            $cart_item_id = $_POST["cart_item_id"];
            $product_id = $_POST["product_id"];
            $quantity = $_POST["quantity"];

            $cartItem->cart_item_id = $cart_item_id;
            $cartItem->cart_id = $cart_id;
            $cartItem->product_id = $product_id;
            $cartItem->quantity = $quantity;

            if ($cartItem->update()) {
                echo json_encode(array("message" => "Cart item quantity updated."));
            } else {
                echo json_encode(array("message" => "Unable to update cart item quantity."));
            }
            break;

        case "remove_from_cart":
            $cart_item_id = $_POST["cart_item_id"];

            $cartItem->cart_item_id = $cart_item_id;

            if ($cartItem->delete()) {
                echo json_encode(array("message" => "Product removed from cart."));
            } else {
                echo json_encode(array("message" => "Unable to remove product from cart."));
            }
            break;

        default:
            echo json_encode(array("message" => "Invalid action."));
            break;
    }
}
?>