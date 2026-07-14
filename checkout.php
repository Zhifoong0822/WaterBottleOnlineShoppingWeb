<?php
// 1. Load configuration utilities, session variables, and global $_db
require '_base.php'; 

// 2. Supply dynamic metadata tracking to _head.php template
$_title = "Checkout";

// 3. Inject standard layout structure, styling mappings, and navigation structures
include '_head.php'; 

$user_id = 1; 
$cart_items = [];
$total_amount = 0;
$errors = [];

// Grab only items that were selected via checkbox arrays
$selected_items = req('selected_items'); // Array of cart_item_ids

if (!is_post() || empty($selected_items)) {
    // If they just typed the URL manually, bounce them back to the cart view
    redirect('cart_view.php');
}

// 4. Retrieve details for checked items only
// Convert the array into a comma-separated string format safely for SQL validation
$placeholders = implode(',', array_fill(0, count($selected_items), '?'));

$query = "
    SELECT ci.cart_item_id, ci.quantity, p.product_id, p.name, p.price 
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.product_id
    WHERE ci.cart_item_id IN ($placeholders)
";

$stmt_items = $_db->prepare($query);
$stmt_items->execute($selected_items);
$cart_items = $stmt_items->fetchAll();

foreach ($cart_items as $item) {
    $total_amount += $item->price * $item->quantity;
}

// 5. Finalize the Order Form Action processing
if (req('confirm_order')) {
    $name = req('name');
    $address = req('address');
    $phone = req('phone');

    if (empty($name)) $errors['name'] = 'Name is required.';
    if (empty($address)) $errors['address'] = 'Shipping address is required.';
    if (empty($phone)) $errors['phone'] = 'Phone number is required.';

    if (empty($errors)) {
       $_db->beginTransaction();

        try {
            // 1. FIRST CHECK STOCK: Ensure everything is still available before placing order
            $stmt_check = $_db->prepare("SELECT stock, name FROM products WHERE product_id = ? FOR UPDATE");
            foreach ($cart_items as $item) {
                $stmt_check->execute([$item->product_id]);
                $prod = $stmt_check->fetch();
                
                if ($prod->stock < $item->quantity) {
                    // Throw custom exception if stock is insufficient
                    throw new Exception("Sorry, '" . encode($prod->name) . "' only has {$prod->stock} items left in stock. Please edit your cart selection.");
                }
            }

            // A. Create Parent Order Row
            $stmt_order = $_db->prepare("
                INSERT INTO orders (user_id, total_amount, status, recipient_name, shipping_address, phone_number, order_date) 
                VALUES (?, ?, 'pending', ?, ?, ?, NOW())
            ");
            $stmt_order->execute([$user_id, $total_amount, $name, $address, $phone]);
            $order_id = $_db->lastInsertId();

            // B. Add Selected Items & C. Deduct Product Stock in DB
            $stmt_order_item = $_db->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, price) 
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt_deduct = $_db->prepare("
                UPDATE products 
                SET stock = stock - ? 
                WHERE product_id = ?
            ");
            
            foreach ($cart_items as $item) {
                // Record item in order
                $stmt_order_item->execute([$order_id, $item->product_id, $item->quantity, $item->price]);
                
                // Deduct physical stock from inventory
                $stmt_deduct->execute([$item->quantity, $item->product_id]);
            }

            // D. Delete ONLY the checked items out of the cart
            $stmt_clear = $_db->prepare("DELETE FROM cart_items WHERE cart_item_id IN ($placeholders)");
            $stmt_clear->execute($selected_items);

            $_db->commit();
            
            echo "<script>alert('Order placed successfully!'); window.location.href='products.php';</script>";
            exit;

        } catch (Exception $e) {
            $_db->rollBack();
            $errors['global'] = $e->getMessage();
        }
    }
}
?>

<div class="checkout-container" style="max-width: 800px; margin: 30px auto; padding: 0 20px; display: flex; gap: 30px;">
    
    <!-- Left: Order Summary Display -->
    <div style="flex: 1; background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #ddd; height: fit-content;">
        <h3>Selected Items</h3>
        <hr style="border:0; border-top:1px solid #ccc; margin: 10px 0;">
        <ul style="list-style: none; padding: 0; margin: 0;">
            <?php foreach ($cart_items as $item): ?>
                <li style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px;">
                    <span><?= encode($item->name) ?> (x<?= $item->quantity ?>)</span>
                    <span>RM<?= number_format($item->price * $item->quantity, 2) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <hr style="border:0; border-top:1px solid #ccc; margin: 15px 0;">
        <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 18px;">
            <span>Total:</span>
            <span style="color: #28a745;">RM<?= number_format($total_amount, 2) ?></span>
        </div>
    </div>

    <!-- Right: Shipping details data forms -->
    <div style="flex: 1.2;">
        <h3>Shipping Details</h3>
        
        <?php if (isset($errors['global'])): ?>
            <p style="color: red; font-weight: bold;"><?= encode($errors['global']) ?></p>
        <?php endif; ?>

        <form method="post" action="checkout.php" style="display: flex; flex-direction: column; gap: 15px; margin-top: 15px;">
            <!-- Re-pass selected items down into form so submission retains scope array -->
            <?php foreach ($selected_items as $id): ?>
                <input type="hidden" name="selected_items[]" value="<?= $id ?>">
            <?php endforeach; ?>
            
            <input type="hidden" name="confirm_order" value="1">

            <div>
                <label style="display: block; font-size: 14px; margin-bottom: 5px; font-weight: bold;">Full Name</label>
                <input type="text" name="name" value="<?= encode(req('name')) ?>" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                <span style="color: red; font-size: 12px;"><?= $errors['name'] ?? '' ?></span>
            </div>

            <div>
                <label style="display: block; font-size: 14px; margin-bottom: 5px; font-weight: bold;">Phone Number</label>
                <input type="text" name="phone" value="<?= encode(req('phone')) ?>" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                <span style="color: red; font-size: 12px;"><?= $errors['phone'] ?? '' ?></span>
            </div>

            <div>
                <label style="display: block; font-size: 14px; margin-bottom: 5px; font-weight: bold;">Delivery Address</label>
                <textarea name="address" rows="4" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; resize: none;"><?= encode(req('address')) ?></textarea>
                <span style="color: red; font-size: 12px;"><?= $errors['address'] ?? '' ?></span>
            </div>

            <button type="submit" style="padding: 12px; background: #28a745; color: white; border: none; border-radius: 4px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 10px;">
                Place Order (Confirm Checkout)
            </button>
            <a href="cart_view.php" style="text-align: center; color: #666; font-size: 14px; text-decoration: none; margin-top: 5px;">Cancel and Return to Cart</a>
        </form>
    </div>
</div>

<?php
include '_foot.php'; 
?>