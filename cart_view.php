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

// Capture the search keyword from the GET request
$search = req('search'); 

// Query matching the class-assigned database layer
$stmt_cart = $_db->prepare("SELECT cart_id FROM carts WHERE user_id = ? LIMIT 1");
$stmt_cart->execute([$user_id]);
$cart_data = $stmt_cart->fetch(); // Returns object row or false

if ($cart_data) {
    $cart_id = $cart_data->cart_id;

    // Relational SQL join query updated to fetch p.stock from the products catalog matrix
    $stmt_items = $_db->prepare("
        SELECT ci.cart_item_id, ci.product_id, ci.quantity, p.name, p.price, p.image_url, p.stock 
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.product_id
        WHERE ci.cart_id = ? AND (p.name LIKE ? OR p.description LIKE ?)
    ");
    $stmt_items->execute([$cart_id, "%$search%", "%$search%"]);
    $cart_items = $stmt_items->fetchAll(); // Maps all entities cleanly as objects
}
?>

<!-- HTML Cart Content Search Form Component -->
<div class="search-container" style="margin-bottom: 25px; text-align: center;">
    <form method="get" action="cart_view.php">
        <input type="text" name="search" placeholder="Search items in your cart..." 
               value="<?= encode($search) ?>" 
               style="padding: 8px 12px; width: 300px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;">
        <button type="submit" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">
            Search Cart
        </button>
        <?php if ($search !== null && $search !== ''): ?>
            <a href="cart_view.php" style="margin-left: 10px; color: #dc3545; text-decoration: none; font-size: 14px;">Clear Filter</a>
        <?php endif; ?>
    </form>
</div>

<?php if (!empty($cart_items)) : ?>
    <!-- Wrap the cart items inside a Form to post checked values directly to checkout -->
    <form id="cart-selection-form" method="post" action="checkout.php">
        <div class="cart-items">
            <?php foreach ($cart_items as $item) : ?>
                <?php $subtotal = $item->price * $item->quantity; ?>
                <div class="cart-item-card" style="display: flex; align-items: center; gap: 20px; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 8px;">
                    
                    <!-- Checkbox for item selection -->
                    <input type="checkbox" name="selected_items[]" value="<?= $item->cart_item_id ?>" class="item-checkbox" checked 
                           data-subtotal="<?= $subtotal ?>" style="width: 20px; height: 20px; cursor: pointer;">
                    
                    <img src="<?= encode($item->image_url) ?>" alt="<?= encode($item->name) ?>" style="width: 80px; height: 80px; object-fit: cover;">
                    
                    <div style="flex: 1;">
                        <h3><?= encode($item->name) ?></h3>
                        <p>Price: RM<?= number_format($item->price, 2) ?></p>
                        
                       <!-- Change this section inside cart_view.php -->
                    <div class="quantity-controls">
                        <button type="button" class="update-quantity" data-cart_item_id="<?= $item->cart_item_id ?>" data-product_id="<?= $item->product_id ?>" data-change="-1">-</button>
                        
                        <input type="number" value="<?= $item->quantity ?>" min="1" max="<?= $item->stock ?>" class="item-quantity" data-cart_item_id="<?= $item->cart_item_id ?>" data-product_id="<?= $item->product_id ?>" style="width: 60px; text-align: center;">
                        
                        <button type="button" class="update-quantity" data-cart_item_id="<?= $item->cart_item_id ?>" data-product_id="<?= $item->product_id ?>" data-change="1">+</button>
                        <button type="button" class="remove-item" data-cart_item_id="<?= $item->cart_item_id ?>">Remove</button>
                    </div>
                    </div>
                    
                    <p style="font-weight: bold;">Subtotal: RM<?= number_format($subtotal, 2) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="cart-summary" style="text-align: right; margin-top: 20px; padding: 20px; background: #f9f9f9; border-radius: 8px;">
            <h2>Total Selected: RM<span id="grand-total">0.00</span></h2>
            <button type="submit" style="margin-top: 15px; padding: 12px 25px; background: #28a745; color: white; border: none; font-weight: bold; border-radius: 4px; font-size: 16px; cursor: pointer;">
                Proceed to Checkout &rarr;
            </button>
        </div>
    </form>
<?php else : ?>
    <div style="text-align: center; margin: 30px 0;">
        <?php if ($search !== null && $search !== ''): ?>
            <p>No items in your cart match "<b><?= encode($search) ?></b>".</p>
        <?php else: ?>
            <p>Your cart is empty.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const checkboxes = document.querySelectorAll(".item-checkbox");
    const grandTotalSpan = document.getElementById("grand-total");
    const form = document.getElementById("cart-selection-form");

    // Recalculates total based only on checked values
    function calculateTotal() {
        let total = 0;
        checkboxes.forEach(cb => {
            if (cb.checked) {
                total += parseFloat(cb.getAttribute("data-subtotal"));
            }
        });
        grandTotalSpan.textContent = total.toFixed(2);
    }

    // Bind event listeners to all selection boxes
    checkboxes.forEach(cb => {
        cb.addEventListener("change", calculateTotal);
    });

    // Run calculation once on page load initialization
    if(grandTotalSpan) {
        calculateTotal();
    }

    // Stop form submission if nothing is picked
    if (form) {
        form.addEventListener("submit", function(e) {
            const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
            if (!anyChecked) {
                e.preventDefault();
                alert("Please select at least one item to checkout.");
            }
        });
    }
});
</script>

<?php
// 5. Append structural trailing closures and footer elements
include '_foot.php'; 
?>