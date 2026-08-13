<?php
// 1. Load configuration utilities, session variables, and global $_db
require '_base.php'; 

// 2. Supply dynamic metadata tracking to _head.php template
$_title = "Checkout";

if (!isset($_SESSION['users']->user_id)) {
    redirect('login.php');
}
$user_id = (int) $_SESSION['users']->user_id;
$cart_items = [];
$total_amount = 0;
$errors = [];
$payment_methods = [
    'online_banking' => 'Online Banking (Demo)',
    'ewallet' => 'E-Wallet (Demo)',
    'credit_debit_card' => 'Credit / Debit Card (Demo)',
    'cash_on_delivery' => 'Cash on Delivery',
];



// Fetch all saved address items belonging to current session user
$stmt_addr = $_db->prepare("SELECT * FROM user_addresses WHERE user_id = ?");
$stmt_addr->execute([$user_id]);
$saved_addresses = $stmt_addr->fetchAll();

// Grab only items that were selected via checkbox arrays
$selected_items = post('selected_items', []);

if (!is_array($selected_items) || empty($selected_items)) {
    redirect('cart_view.php');
}

$selected_items = array_values(array_unique($selected_items));

foreach ($selected_items as $id) {
    if (!valid_positive_int($id)) {
        redirect('cart_view.php');
    }
}

$selected_items = array_map('intval', $selected_items);

// 4. Retrieve details for checked items only (including the size configuration string column)
$placeholders = implode(',', array_fill(0, count($selected_items), '?'));

$query = "
    SELECT ci.cart_item_id, ci.quantity, ci.size, p.product_id, p.name, p.price 
    FROM cart_items ci
    JOIN carts c ON c.cart_id = ci.cart_id
    JOIN products p ON ci.product_id = p.product_id
    WHERE c.user_id = ?
      AND ci.cart_item_id IN ($placeholders)
";

$stmt_items = $_db->prepare($query);
$stmt_items->execute(array_merge([$user_id], $selected_items));
$cart_items = $stmt_items->fetchAll();

if (count($cart_items) !== count($selected_items)) {
    temp('info', 'One or more selected cart items are no longer available.');
    redirect('cart_view.php');
}

// Calculate totals using dynamic sizing calculation method
foreach ($cart_items as $item) {
    $item->computed_price = variant_price($item->price, $item->size);
    $total_amount += $item->computed_price * $item->quantity;
}

$stmt_points = $_db->prepare("SELECT reward_points FROM users WHERE user_id = ?");
$stmt_points->execute([$user_id]);
$points_balance = (int) $stmt_points->fetchColumn();
$maximum_points_for_order = min(
    $points_balance,
    maximum_redeemable_points($total_amount)
);

$points_input = post('points_to_use', '0');
$display_points_to_use = ctype_digit($points_input) ? (int) $points_input : 0;
$display_points_to_use = min($display_points_to_use, $maximum_points_for_order);
$display_points_discount = points_to_ringgit($display_points_to_use);
$display_amount_due = $total_amount - $display_points_discount;

// 5. Finalize the Order Form Action processing
if (req('confirm_order')) {
    $address_type = req('address_type'); // 'saved' or 'new'
    $name = '';
    $phone = '';
    $address = '';
    $payment_method = post('payment_method', '');
    $payment_reference = trim(post('payment_reference', ''));
    $points_to_use = 0;

    if (!array_key_exists($payment_method, $payment_methods)) {
        $errors['payment_method'] = 'Please select a payment method.';
    } elseif ($payment_method !== 'cash_on_delivery' && $payment_reference === '') {
        $errors['payment_reference'] = 'Enter a demo payment reference.';
    } elseif (strlen($payment_reference) > 100) {
        $errors['payment_reference'] = 'Payment reference must not exceed 100 characters.';
    }

    $payment_status = $payment_method === 'cash_on_delivery' ? 'pending' : 'paid';

    if (!ctype_digit($points_input)) {
        $errors['points_to_use'] = 'Points used must be a whole number.';
    } else {
        $points_to_use = (int) $points_input;

        if ($points_to_use > $maximum_points_for_order) {
            $errors['points_to_use'] = 'You may use up to ' . $maximum_points_for_order . ' points for this order.';
        }
    }

    if ($address_type === 'saved') {
        $address_id = req('address_id');
        if (empty($address_id)) {
            $errors['address_id'] = 'Please select a saved address option profile.';
        } else {
            // Retrieve data fields out of lookup profile records directly
            $stmt_lookup = $_db->prepare("SELECT * FROM user_addresses WHERE address_id = ? AND user_id = ?");
            $stmt_lookup->execute([$address_id, $user_id]);
            $addr_profile = $stmt_lookup->fetch();
            
            if ($addr_profile) {
                $name = $addr_profile->recipient_name;
                $phone = $addr_profile->phone_number;
                $address = $addr_profile->address_text;
            } else {
                $errors['address_id'] = 'Selected address configuration context was invalid.';
            }
        }
    } elseif ($address_type === 'new') {
        // Fallback validation routes handling customized manual form additions
        $name = trim(req('name'));
        $phone = trim(req('phone'));
        $address = trim(req('address'));
        $label = trim(req('address_label')) ?: 'Home';

        if ($name === '') {
            $errors['name'] = 'Name is required.';
        }

        if ($phone === '') {
            $errors['phone'] = 'Phone number is required.';
        } elseif (!valid_phone($phone)) {
            $errors['phone'] = 'Enter a valid phone number.';
        }

        if ($address === '') {
            $errors['address'] = 'Shipping address is required.';
        }
        
        // If inputs validate cleanly and user checked "save profile", update relational DB profiles
        if (empty($errors) && req('save_new_address')) {
            $stmt_save_addr = $_db->prepare("
                INSERT INTO user_addresses (user_id, address_label, recipient_name, phone_number, address_text) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt_save_addr->execute([$user_id, $label, $name, $phone, $address]);
        }
    } else {
        $errors['address_type'] = 'Please select a delivery option.';
    }

    if (empty($errors)) {
        $_db->beginTransaction();

        try {
            // Lock the member's point balance so concurrent checkouts cannot overspend points.
            $stmt_member = $_db->prepare("
                SELECT reward_points
                FROM users
                WHERE user_id = ?
                FOR UPDATE
            ");
            $stmt_member->execute([$user_id]);
            $current_points = (int) $stmt_member->fetchColumn();
            $maximum_points_for_order = min(
                $current_points,
                maximum_redeemable_points($total_amount)
            );

            if ($points_to_use > $maximum_points_for_order) {
                throw new Exception('Your reward-points balance changed. Please review your checkout again.');
            }

            $points_discount = points_to_ringgit($points_to_use);
            $amount_due = round($total_amount - $points_discount, 2);
            $points_earned = earned_reward_points($amount_due);

            // 1. FIRST CHECK STOCK: Check specific size variant stock in product_variants
            $stmt_check = $_db->prepare("
                SELECT pv.stock, p.name 
                FROM product_variants pv
                JOIN products p ON pv.product_id = p.product_id
                WHERE pv.product_id = ? AND pv.size = ?
                FOR UPDATE
            ");

            foreach ($cart_items as $item) {
                $stmt_check->execute([$item->product_id, $item->size]);
                $variant = $stmt_check->fetch();
                
                if (!$variant) {
                    throw new Exception("Sorry, the selected size option ('" . encode($item->size) . "') is no longer available.");
                }

                if ($variant->stock < $item->quantity) {
                    throw new Exception("Sorry, '" . encode($variant->name) . "' (" . encode($item->size) . ") only has {$variant->stock} items left in stock. Please edit your cart selection.");
                }
            }

            // A. Create Parent Order Row
            $stmt_order = $_db->prepare("
                INSERT INTO orders (
                    user_id, total_amount, subtotal_amount, points_used, points_discount, points_earned,
                    status, recipient_name, shipping_address, phone_number,
                    payment_method, payment_reference, payment_status, order_date
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt_order->execute([
                $user_id, $amount_due, $total_amount, $points_to_use, $points_discount, $points_earned,
                $name, $address, $phone,
                $payment_method, $payment_reference ?: null, $payment_status
            ]);
            $order_id = $_db->lastInsertId();

            $stmt_update_points = $_db->prepare("
                UPDATE users
                SET reward_points = reward_points - ? + ?
                WHERE user_id = ?
            ");
            $stmt_update_points->execute([$points_to_use, $points_earned, $user_id]);

            // B. Add Selected Items & C. Deduct Variant Product Stock in DB
            $stmt_order_item = $_db->prepare("
                INSERT INTO order_items (order_id, product_id, size, quantity, price) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            // FIXED: Deducts stock from product_variants table by matching product_id AND size
            $stmt_deduct = $_db->prepare("
                UPDATE product_variants 
                SET stock = stock - ? 
                WHERE product_id = ? AND size = ?
            ");
            
            foreach ($cart_items as $item) {
                // Record item variant configuration details inside static historical ledger orders 
                $stmt_order_item->execute([$order_id, $item->product_id, $item->size, $item->quantity, $item->computed_price]);
                
                // Deduct physical stock from target size variant inventory
                $stmt_deduct->execute([$item->quantity, $item->product_id, $item->size]);
            }

            // D. Delete ONLY the checked items out of the cart
            $stmt_clear = $_db->prepare("
                DELETE ci
                FROM cart_items ci
                JOIN carts c ON c.cart_id = ci.cart_id
                WHERE c.user_id = ?
                  AND ci.cart_item_id IN ($placeholders)
            ");
            $stmt_clear->execute(array_merge([$user_id], $selected_items));

            $_db->commit();

            // The order has already been committed, so email failure never
            // reverses a successful checkout.
            try {
                send_order_receipt($order_id, $user_id);
                temp('info', 'Order placed successfully. Your e-receipt has been sent to your email.');
            } catch (Throwable $mail_error) {
                temp('info', 'Order placed successfully. Your e-receipt could not be sent; you can resend it from Order Details after email is configured.');
            }
            redirect('order_detail.php?id=' . $order_id);

        } catch (Exception $e) {
            $_db->rollBack();
            $errors['global'] = $e->getMessage();
        }
    }
}

// Render the shared page layout only after all possible redirects are complete.
include '_head.php';
?>

<div class="checkout-container" style="max-width: 900px; margin: 30px auto; padding: 0 20px; display: flex; gap: 30px;">
    
    <!-- Left: Order Summary Display -->
    <div style="flex: 1; background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #ddd; height: fit-content;">
        <h3>Order Summary</h3>
        <hr style="border:0; border-top:1px solid #ccc; margin: 10px 0;">
        <ul style="list-style: none; padding: 0; margin: 0;">
            <?php foreach ($cart_items as $item): ?>
                <li style="margin-bottom: 12px; font-size: 14px;">
                    <div style="display: flex; justify-content: space-between; font-weight: bold;">
                        <span><?= encode($item->name) ?> (x<?= $item->quantity ?>)</span>
                        <span>RM<?= number_format($item->computed_price * $item->quantity, 2) ?></span>
                    </div>
                    <div style="font-size: 12px; color: #666; font-style: italic;">
                        Size: <?= encode($item->size) ?> (RM<?= number_format($item->computed_price, 2) ?> each)
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <hr style="border:0; border-top:1px solid #ccc; margin: 15px 0;">
        <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 16px;">
            <span>Subtotal:</span>
            <span>RM<?= number_format($total_amount, 2) ?></span>
        </div>
        <div style="display: flex; justify-content: space-between; margin-top: 8px;">
            <span>Reward points discount:</span>
            <span id="points-discount-display">- RM<?= number_format($display_points_discount, 2) ?></span>
        </div>
        <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 18px; margin-top: 12px;">
            <span>Amount Due:</span>
            <span id="amount-due-display" style="color: #28a745;">RM<?= number_format($display_amount_due, 2) ?></span>
        </div>
    </div>

    <!-- Right: Shipping details data forms -->
    <div style="flex: 1.3;">
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

            <!-- Address Choice Selector Matrix -->
            <div style="background: #f1f3f5; padding: 15px; border-radius: 6px; border: 1px solid #e2e6ea;">
                <p style="font-weight: bold; margin-bottom: 10px; font-size: 14px;">Select Delivery Option:</p>
                
                <label style="display: block; margin-bottom: 8px; cursor: pointer;">
                    <input type="radio" name="address_type" value="saved" checked>
                    Use a Saved Address Profile
                </label>
                
                <label style="display: block; cursor: pointer;">
                    <input type="radio" name="address_type" value="new">
                    Ship to a New Address Instead
                </label>
            </div>

            <div style="background: #f1f3f5; padding: 15px; border-radius: 6px; border: 1px solid #e2e6ea;">
                <label for="payment_method" style="display: block; font-size: 14px; margin-bottom: 5px; font-weight: bold;">Payment Method</label>
                <select id="payment_method" name="payment_method" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; background: white;">
                    <option value="">- Select a payment method -</option>
                    <?php foreach ($payment_methods as $value => $label): ?>
                        <option value="<?= $value ?>" <?= post('payment_method') === $value ? 'selected' : '' ?>>
                            <?= encode($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span style="color: red; font-size: 12px;"><?= $errors['payment_method'] ?? '' ?></span>

                <div id="payment-reference-block" style="margin-top: 12px;">
                    <label for="payment_reference" style="display: block; font-size: 14px; margin-bottom: 5px; font-weight: bold;">Demo Payment Reference</label>
                    <input id="payment_reference" type="text" name="payment_reference" maxlength="100" value="<?= encode(post('payment_reference')) ?>" placeholder="Example: DEMO-123456 or card last 4 digits" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
                    <small style="display: block; margin-top: 5px; color: #666;">Demo only. Do not enter real card or bank-account details.</small>
                    <span style="color: red; font-size: 12px;"><?= $errors['payment_reference'] ?? '' ?></span>
                </div>
            </div>

            <div style="background: #fff8e8; padding: 15px; border-radius: 6px; border: 1px solid #f0cf86;">
                <label for="points_to_use" style="display: block; font-size: 14px; margin-bottom: 5px; font-weight: bold;">Reward Points</label>
                <p style="margin: 0 0 8px; font-size: 13px; color: #666;">
                    Balance: <strong><?= number_format($points_balance) ?> points</strong>.
                    100 points = RM1. You may use up to <?= number_format($maximum_points_for_order) ?> points (10% order limit).
                </p>
                <input
                    id="points_to_use"
                    type="number"
                    name="points_to_use"
                    min="0"
                    max="<?= $maximum_points_for_order ?>"
                    step="1"
                    value="<?= $display_points_to_use ?>"
                    data-subtotal="<?= $total_amount ?>"
                    data-max-points="<?= $maximum_points_for_order ?>"
                    style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;"
                >
                <small style="display: block; margin-top: 5px; color: #666;">Earn 10 points for every RM10 paid after redemption.</small>
                <span style="color: red; font-size: 12px;"><?= $errors['points_to_use'] ?? '' ?></span>
            </div>

            <!-- Block A: Dropdown showing existing options -->
            <div id="saved-address-block">
                <label style="display: block; font-size: 14px; margin-bottom: 5px; font-weight: bold;">Choose Address Profile</label>
                <?php if (count($saved_addresses) > 0): ?>
                    <select name="address_id" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; background: white;">
                        <?php foreach ($saved_addresses as $addr): ?>
                            <option value="<?= $addr->address_id ?>">
                                [<?= encode($addr->address_label) ?>] <?= encode($addr->recipient_name) ?> - <?= encode($addr->address_text) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span style="color: red; font-size: 12px;"><?= $errors['address_id'] ?? '' ?></span>
                <?php else: ?>
                    <p style="color:#666; font-size:13px; font-style:italic;">No addresses saved yet. Select "Ship to a New Address Instead" below.</p>
                <?php endif; ?>
            </div>

            <!-- Block B: Input fields processing new entries manually -->
            <div id="new-address-block" style="display: none; flex-direction: column; gap: 15px; border-left: 3px solid #007bff; padding-left: 15px;">
                <div>
                    <label style="display: block; font-size: 14px; margin-bottom: 5px; font-weight: bold;">Address Label (e.g. Home, Secondary)</label>
                    <input type="text" name="address_label" value="<?= encode(req('address_label')) ?>" placeholder="Home" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                </div>

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
                    <textarea name="address" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; resize: none;"><?= encode(req('address')) ?></textarea>
                    <span style="color: red; font-size: 12px;"><?= $errors['address'] ?? '' ?></span>
                </div>

                <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer;">
                    <input type="checkbox" name="save_new_address" value="1" checked>
                    Save this address to my profile configuration for next time
                </label>
            </div>

            <button type="submit" style="padding: 12px; background: #28a745; color: white; border: none; border-radius: 4px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 10px;">
                Place Order (Confirm Checkout)
            </button>
            <a href="cart_view.php" style="text-align: center; color: #666; font-size: 14px; text-decoration: none; margin-top: 5px;">Cancel and Return to Cart</a>
        </form>
    </div>
</div>

<script>
// Retain visibility settings correctly if form updates due to validation errors
<?php if (req('address_type') === 'new' || isset($errors['name']) || isset($errors['phone']) || isset($errors['address'])): ?>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelector('input[name="address_type"][value="new"]').checked = true;
    });
<?php endif; ?>
</script>

<?php
include '_foot.php';
?>
