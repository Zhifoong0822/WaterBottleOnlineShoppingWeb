<?php
// 1. Load configuration utilities, session variables, and global $_db
require '_base.php'; 

// 2. Supply dynamic metadata tracking to _head.php template
$_title = "Product Details";

// 3. Inject standard layout structure
include '_head.php'; 

$product_id = intval(req('id'));

// Fetch base product details
$stmt = $_db->prepare("SELECT * FROM products WHERE product_id = ? LIMIT 1");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    echo "<div style='text-align:center; margin: 50px;'><h3>Product not found.</h3><a href='products.php'>Back to shop</a></div>";
    include '_foot.php';
    exit;
}

// Fetch all size variants and their individual stocks for this product
$stmt_var = $_db->prepare("SELECT size, stock FROM product_variants WHERE product_id = ?");
$stmt_var->execute([$product_id]);
$variants = $stmt_var->fetchAll();

// Map variants into an easy key-value array: ['Medium (18oz / 530ml)' => 50]
$variant_stocks = [];
foreach ($variants as $v) {
    $variant_stocks[$v->size] = intval($v->stock);
}

// Default sizes list configuration
$sizes_list = [
    ["label" => "Micro", "sub" => "12oz / 350ml", "value" => "Micro (12oz / 350ml)", "multiplier" => "1.00"],
    ["label" => "Mini",  "sub" => "15oz / 450ml", "value" => "Mini (15oz / 450ml)",  "multiplier" => "1.10"],
    ["label" => "Medium","sub" => "18oz / 530ml", "value" => "Medium (18oz / 530ml)","multiplier" => "1.20"],
    ["label" => "Mega",  "sub" => "32oz / 950ml", "value" => "Mega (32oz / 950ml)",  "multiplier" => "1.40"],
];
?>

<div class="product-detail-container" style="max-width: 900px; margin: 40px auto; padding: 0 20px; display: flex; gap: 40px;">
    <!-- Left Side: Image -->
    <div style="flex: 1;">
        <img src="<?= encode($product->image_url) ?>" alt="<?= encode($product->name) ?>" style="width: 100%; max-height: 450px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd;">
    </div>

    <!-- Right Side: Meta Details -->
    <div style="flex: 1.2; display: flex; flex-direction: column; gap: 15px;">
        <h2><?= encode($product->name) ?></h2>
        <p style="color: #666; line-height: 1.6;"><?= encode($product->description) ?></p>
        
        <!-- Dynamic Price Display -->
        <div style="font-size: 28px; font-weight: bold; color: #111;">
            RM <span id="dynamic-price-display"><?= number_format($product->price * 1.20, 2) ?></span>
        </div>

        <hr style="border: 0; border-top: 1px solid #eee; margin: 10px 0;">

        <form method="post" action="handle_cart.php" id="add-to-cart-form" style="display: flex; flex-direction: column; gap: 20px;">
            <input type="hidden" name="action" value="add_to_cart">
            <input type="hidden" name="product_id" value="<?= $product->product_id ?>">

            <!-- Size Chips -->
            <div>
                <label style="display: block; font-weight: bold; margin-bottom: 10px; font-size: 14px; color: #333;">Select Size:</label>
                
                <div class="size-chips-container" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <?php 
                    $first_selected = false;
                    foreach ($sizes_list as $size_item) : 
                        $current_stock = isset($variant_stocks[$size_item['value']]) ? $variant_stocks[$size_item['value']] : 0;
                        $is_out_of_stock = ($current_stock <= 0);
                        
                        // Pick the first available size as default selection
                        $should_check = false;
                        if (!$is_out_of_stock && !$first_selected) {
                            $should_check = true;
                            $first_selected = true;
                        }
                    ?>
                        <label class="size-chip" 
                               style="padding: 12px 15px; border: 2px solid #ddd; border-radius: 6px; cursor: <?= $is_out_of_stock ? 'not-allowed' : 'pointer' ?>; font-weight: bold; text-align: center; flex: 1; min-width: 90px; transition: all 0.2s ease; <?= $is_out_of_stock ? 'opacity: 0.4; background: #f5f5f5;' : '' ?>">
                            
                            <input type="radio" name="size" value="<?= $size_item['value'] ?>" 
                                   data-multiplier="<?= $size_item['multiplier'] ?>" 
                                   data-stock="<?= $current_stock ?>"
                                   <?= $should_check ? 'checked' : '' ?>
                                   <?= $is_out_of_stock ? 'disabled' : '' ?>
                                   style="position: absolute; opacity: 0; width: 0; height: 0;">
                            
                            <span style="display: block; font-size: 14px;"><?= $size_item['label'] ?></span>
                            <span style="display: block; font-size: 11px; color: #666; font-weight: normal; margin-top: 2px;"><?= $size_item['sub'] ?></span>
                            <?php if ($is_out_of_stock): ?>
                                <span style="display: block; font-size: 10px; color: #dc3545; font-weight: bold; margin-top: 3px;">Sold Out</span>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Quantity Input Element -->
            <div>
                <label style="display: block; font-weight: bold; margin-bottom: 5px; font-size: 14px;">Quantity:</label>
                <input type="number" id="quantity-input" name="quantity" value="1" min="1" max="1" style="width: 80px; padding: 8px; border: 1px solid #ccc; border-radius: 4px; text-align: center; font-size: 15px;">
                <small id="stock-display" style="display:block; color: #666; margin-top: 6px; font-weight: 500;">Available Inventory: -- units</small>
            </div>

            <button type="submit" id="submit-btn" style="padding: 14px; background: #111; color: white; border: none; border-radius: 4px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 10px; text-transform: uppercase;">
                Add to Shopping Cart
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const basePrice = parseFloat(<?= json_encode($product->price) ?>);
    const priceDisplay = document.getElementById("dynamic-price-display");
    const stockDisplay = document.getElementById("stock-display");
    const qtyInput = document.getElementById("quantity-input");
    const submitBtn = document.getElementById("submit-btn");
    const chips = document.querySelectorAll(".size-chip");
    const form = document.getElementById("add-to-cart-form");

    function handleChipChange(chip, input) {
        if (input.checked && !input.disabled) {
            // Highlight selected chip
            chips.forEach(c => {
                const inp = c.querySelector('input[type="radio"]');
                if (!inp.disabled) {
                    c.style.borderColor = "#ddd";
                    c.style.backgroundColor = "transparent";
                    c.style.color = "#000";
                }
            });

            chip.style.borderColor = "#111";
            chip.style.backgroundColor = "#f8f9fa";
            chip.style.color = "#111";

            // Update Price
            const multiplier = parseFloat(input.getAttribute("data-multiplier"));
            const calculatedPrice = basePrice * multiplier;
            priceDisplay.textContent = calculatedPrice.toFixed(2);

            // Update Stock Display & Max Quantity limit
            const stock = parseInt(input.getAttribute("data-stock")) || 0;
            qtyInput.max = stock;
            qtyInput.value = Math.min(qtyInput.value || 1, stock);

            if (stock <= 5 && stock > 0) {
                stockDisplay.innerHTML = `<span style="color: #dc3545; font-weight: bold;">⚠️ Only ${stock} left in stock!</span>`;
            } else {
                stockDisplay.textContent = `Available Inventory: ${stock} units`;
            }

            submitBtn.disabled = false;
            submitBtn.style.opacity = "1";
            submitBtn.textContent = "Add to Shopping Cart";
        }
    }

    chips.forEach(chip => {
        const input = chip.querySelector('input[type="radio"]');
        
        input.addEventListener("change", function() {
            handleChipChange(chip, input);
        });

        if (input.checked && !input.disabled) {
            handleChipChange(chip, input);
        }
    });

    // Handle AJAX Form Submission with guaranteed redirect to products.php
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();

            const formData = new FormData(form);

            fetch("handle_cart.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.message) {
                    alert(data.message);
                }
                // Redirect back to products page after clicking OK on alert
                window.location.href = "products.php";
            })
            .catch(error => {
                console.error("Error:", error);
                // Redirect anyway if network error or response is okay
                window.location.href = "products.php";
            });
        });
    }
});
</script>

<?php 
include '_foot.php'; 
?>