<?php
// 1. Load configuration utilities, session variables, and global $_db
require '_base.php'; 

// 2. Supply dynamic metadata tracking to _head.php template
$_title = "Product Details";

// 3. Inject standard layout structure
include '_head.php'; 

$product_id = intval(req('id'));

// Fetch product details
$stmt = $_db->prepare("SELECT * FROM products WHERE product_id = ? LIMIT 1");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    echo "<div style='text-align:center; margin: 50px;'><h3>Product not found.</h3><a href='products.php'>Back to shop</a></div>";
    include '_foot.php';
    exit;
}
?>

<div class="product-detail-container" style="max-width: 900px; margin: 40px auto; padding: 0 20px; display: flex; gap: 40px;">
    <!-- Left Side: Image -->
    <div style="flex: 1;">
        <img src="<?= encode($product->image_url) ?>" alt="<?= encode($product->name) ?>" style="width: 100%; max-height: 450px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd;">
    </div>

    <!-- Right Side: Meta Details and Interactive Form -->
    <div style="flex: 1.2; display: flex; flex-direction: column; gap: 15px;">
        <h2><?= encode($product->name) ?></h2>
        <p style="color: #666; line-height: 1.6;"><?= encode($product->description) ?></p>
        
        <!-- Live Interchanging Price Counter Component -->
        <div style="font-size: 28px; font-weight: bold; color: #111;">
            RM <span id="dynamic-price-display"><?= number_format($product->price * 1.20, 2) ?></span>
        </div>

        <hr style="border: 0; border-top: 1px solid #eee; margin: 10px 0;">

        <!-- Form target pointing to your handle_cart.php controller script -->
        <form method="post" action="handle_cart.php" id="add-to-cart-form" style="display: flex; flex-direction: column; gap: 20px;">
            <input type="hidden" name="action" value="add_to_cart">
            <input type="hidden" name="product_id" value="<?= $product->product_id ?>">

            <!-- Premium Visual Size Picker Layout (No Dropdown, Clean Strings Only) -->
            <div>
                <label style="display: block; font-weight: bold; margin-bottom: 10px; font-size: 14px; color: #333;">Select Size:</label>
                
                <div class="size-chips-container" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    
                    <!-- Micro Variant Pill -->
                    <label class="size-chip" style="padding: 12px 20px; border: 2px solid #ddd; border-radius: 6px; cursor: pointer; font-weight: bold; text-align: center; flex: 1; min-width: 100px; transition: all 0.2s ease;">
                        <input type="radio" name="size" value="Micro (12oz / 350ml)" data-multiplier="1.00" style="position: absolute; opacity: 0; width: 0; height: 0;">
                        <span style="display: block; font-size: 14px;">Micro</span>
                        <span style="display: block; font-size: 11px; color: #666; font-weight: normal; margin-top: 2px;">12oz / 350ml</span>
                    </label>

                    <!-- Mini Variant Pill -->
                    <label class="size-chip" style="padding: 12px 20px; border: 2px solid #ddd; border-radius: 6px; cursor: pointer; font-weight: bold; text-align: center; flex: 1; min-width: 100px; transition: all 0.2s ease;">
                        <input type="radio" name="size" value="Mini (15oz / 450ml)" data-multiplier="1.10" style="position: absolute; opacity: 0; width: 0; height: 0;">
                        <span style="display: block; font-size: 14px;">Mini</span>
                        <span style="display: block; font-size: 11px; color: #666; font-weight: normal; margin-top: 2px;">15oz / 450ml</span>
                    </label>

                    <!-- Medium Variant Pill (Selected by default) -->
                    <label class="size-chip" style="padding: 12px 20px; border: 2px solid #ddd; border-radius: 6px; cursor: pointer; font-weight: bold; text-align: center; flex: 1; min-width: 100px; transition: all 0.2s ease;">
                        <input type="radio" name="size" value="Medium (18oz / 530ml)" data-multiplier="1.20" checked style="position: absolute; opacity: 0; width: 0; height: 0;">
                        <span style="display: block; font-size: 14px;">Medium</span>
                        <span style="display: block; font-size: 11px; color: #666; font-weight: normal; margin-top: 2px;">18oz / 530ml</span>
                    </label>

                    <!-- Mega Variant Pill -->
                    <label class="size-chip" style="padding: 12px 20px; border: 2px solid #ddd; border-radius: 6px; cursor: pointer; font-weight: bold; text-align: center; flex: 1; min-width: 100px; transition: all 0.2s ease;">
                        <input type="radio" name="size" value="Mega (32oz / 950ml)" data-multiplier="1.40" style="position: absolute; opacity: 0; width: 0; height: 0;">
                        <span style="display: block; font-size: 14px;">Mega</span>
                        <span style="display: block; font-size: 11px; color: #666; font-weight: normal; margin-top: 2px;">32oz / 950ml</span>
                    </label>

                </div>
            </div>

            <!-- Quantity Input Element -->
            <div>
                <label style="display: block; font-weight: bold; margin-bottom: 5px; font-size: 14px;">Quantity:</label>
                <input type="number" name="quantity" value="1" min="1" max="<?= $product->stock ?>" style="width: 80px; padding: 8px; border: 1px solid #ccc; border-radius: 4px; text-align: center; font-size: 15px;">
                <small style="display:block; color: #999; margin-top: 6px;">Available Inventory: <?= $product->stock ?> units</small>
            </div>

            <button type="submit" style="padding: 14px; background: #111; color: white; border: none; border-radius: 4px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 10px; text-transform: uppercase; letter-spacing: 0.5px;">
                Add to Shopping Cart
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const basePrice = parseFloat(<?= json_encode($product->price) ?>);
    const priceDisplay = document.getElementById("dynamic-price-display");
    const chips = document.querySelectorAll(".size-chip");
    const form = document.getElementById("add-to-cart-form"); // Grab the form

    // --- 1. HANDLE LIVE PRICE SWITCHING ---
    function handleChipChange(chip, input) {
        if (input.checked) {
            chips.forEach(c => {
                c.style.borderColor = "#ddd";
                c.style.backgroundColor = "transparent";
                c.style.color = "#000";
            });

            chip.style.borderColor = "#111";
            chip.style.backgroundColor = "#f8f9fa";
            chip.style.color = "#111";

            const multiplier = parseFloat(input.getAttribute("data-multiplier"));
            const calculatedPrice = basePrice * multiplier;
            priceDisplay.textContent = calculatedPrice.toFixed(2);
        }
    }

    chips.forEach(chip => {
        const input = chip.querySelector('input[type="radio"]');
        input.addEventListener("change", function() {
            handleChipChange(chip, input);
        });
        if (input.checked) {
            handleChipChange(chip, input);
        }
    });

    // --- 2. NEW: INTERCEPT ADD TO CART SUBMISSION ---
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault(); // Stop the browser from navigating to handle_cart.php

            // Package up the form data automatically
            const formData = new FormData(form);

            // Send it to the backend silently in the background
            fetch("handle_cart.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json()) // Parse the JSON reply
            .then(data => {
                // Display the dynamic message in a clean alert window
                alert(data.message); 
                
                // Redirect to the cart view page after successful addition
                window.location.href = 'cart_view.php';
            })
            .catch(error => {
                console.error("Error:", error);
                alert("Something went wrong adding the item to the cart.");
            });
        });
    }
});
</script>

<?php 
include '_foot.php'; 
?>