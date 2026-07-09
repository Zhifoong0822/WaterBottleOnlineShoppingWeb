<?php
// 1. Load configuration utilities, session variables, and global $_db
require '_base.php'; 

// 2. Supply dynamic metadata tracking to _head.php template
$_title = "Shopping Cart";

// 3. Inject standard layout structure, styling mappings, and navigation structures
include '_head.php'; 

// 4. Executing local structural database collection flows via $_db
$user_id = 1; 
$cart_id = null;
$cart_items = [];
$total_amount = 0;

// Query matching the class-assigned database layer
$stmt_cart = $_db->prepare("SELECT cart_id FROM carts WHERE user_id = ? LIMIT 1");
$stmt_cart->execute([$user_id]);
$cart_data = $stmt_cart->fetch(); // Returns object row or false

if ($cart_data) {
    $cart_id = $cart_data->cart_id;

    // Direct assignment validation logic leveraging explicitly written relational SQL joins
    $stmt_items = $_db->prepare("
        SELECT ci.cart_item_id, ci.product_id, ci.quantity, p.name, p.price, p.image_url 
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.product_id
        WHERE ci.cart_id = ?
    ");
    $stmt_items->execute([$cart_id]);
    $cart_items = $stmt_items->fetchAll(); // Maps all array entities cleanly as distinct objects
}
?>

<?php if (!empty($cart_items)) : ?>
    <div class="cart-items">
        <?php foreach ($cart_items as $item) : ?>
            <?php 
                // Dynamically compile math requirements safely inline 
                $total_amount += $item->price * $item->quantity; 
            ?>
            <div class="cart-item-card">
                <img src="<?= encode($item->image_url) ?>" alt="<?= encode($item->name) ?>">
                <h3><?= encode($item->name) ?></h3>
                <p>Price: RM<?= number_format($item->price, 2) ?></p>
                
                <div class="quantity-controls">
                    <button class="update-quantity" data-cart_item_id="<?= $item->cart_item_id ?>" data-product_id="<?= $item->product_id ?>" data-change="-1">-</button>
                    <input type="number" value="<?= $item->quantity ?>" min="1" class="item-quantity" data-cart_item_id="<?= $item->cart_item_id ?>" data-product_id="<?= $item->product_id ?>" readonly>
                    <button class="update-quantity" data-cart_item_id="<?= $item->cart_item_id ?>" data-product_id="<?= $item->product_id ?>" data-change="1">+</button>
                    <button class="remove-item" data-cart_item_id="<?= $item->cart_item_id ?>">Remove</button>
                </div>
                
                <p>Subtotal: RM<?= number_format($item->price * $item->quantity, 2) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="cart-summary">
        <h2>Total: RM<?= number_format($total_amount, 2) ?></h2>
    </div>
<?php else : ?>
    <p>Your cart is empty.</p>
<?php endif; ?>

<?php
// 5. Append structural trailing closures and footer elements
include '_foot.php'; 
?>