<?php
// 1. Load configuration utilities, session variables, and global $_db
require '_base.php'; 

// 2. Fetch the product ID from the URL parameters safely using req()
$product_id = req('id'); 

// 3. Select the matching product record using an inline PDO query
$stmt = $_db->prepare('SELECT * FROM products WHERE product_id = ?');
$stmt->execute([$product_id]);
$product = $stmt->fetch(); // Fetches as an object matching class standards

// 4. If the product doesn't exist, redirect back to the listings page
if (!$product) {
    redirect('products.php');
}

// 5. Supply dynamic metadata tracking to _head.php template using the product name
$_title = $product->name;

// 6. Inject layout structure, navigation structures, and stylesheets
include '_head.php'; 
?>

<div class="product-detail-container" style="display: flex; gap: 40px; margin: 30px auto; max-width: 1000px; align-items: start; padding: 0 20px;">
    <!-- Product Image -->
    <div class="product-detail-image" style="flex: 1; max-width: 400px;">
        <img src="<?= encode($product->image_url) ?>" alt="<?= encode($product->name) ?>" style="width: 100%; border-radius: 8px; border: 1px solid #ddd; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
    </div>

    <!-- Product Details Content -->
    <div class="product-detail-info" style="flex: 1;">
        <h2><?= encode($product->name) ?></h2>
        <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">
        
        <p style="font-size: 24px; color: #28a745; font-weight: bold; margin-bottom: 15px;">
            Price: RM<?= number_format($product->price, 2) ?>
        </p>
        
        <p style="color: #555; line-height: 1.6; margin-bottom: 20px; font-size: 16px;">
            <?= encode($product->description) ?>
        </p>
        
        <p style="margin-bottom: 25px; font-size: 15px;">
            <strong>Availability:</strong> 
            <span style="color: <?= $product->stock > 0 ? '#28a745' : '#dc3545' ?>; font-weight: bold;">
                <?= $product->stock > 0 ? encode($product->stock) . ' units left in stock' : 'Out of Stock' ?>
            </span>
        </p>

        <!-- Interactive Cart Trigger Action Button -->
        <?php if ($product->stock > 0): ?>
            <button class="add-to-cart" data-product_id="<?= $product->product_id ?>" 
                    style="padding: 12px 28px; background: #007bff; color: white; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; font-weight: bold; transition: background 0.2s;">
                Add to Cart
            </button>
        <?php else: ?>
            <button disabled style="padding: 12px 28px; background: #ccc; color: #666; border: none; border-radius: 4px; font-size: 16px; cursor: not-allowed; font-weight: bold;">
                Out of Stock
            </button>
        <?php endif; ?>
        
        <div style="margin-top: 30px;">
            <a href="products.php" style="color: #007bff; text-decoration: none; font-size: 14px;">&larr; Back to Products</a>
        </div>
    </div>
</div>


<?php
// 8. Append structural trailing closures and footer elements
include '_foot.php'; 
?>