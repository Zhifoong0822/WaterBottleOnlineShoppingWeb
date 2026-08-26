<?php
require '_base.php';

$_title = 'Product Details';

$product_id = (int) req('id');
$variant_id = (int) req('variant_id');
$stmt = $_db->prepare('SELECT * FROM products WHERE product_id = ? LIMIT 1');
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    require '_head.php';
    echo "<div style='text-align:center; margin: 50px;'><h3>Product not found.</h3><a href='products.php'>Back to shop</a></div>";
    require '_foot.php';
    exit;
}

// A product card represents one specific size/colour variant.
$variant_sql = 'SELECT variant_id, size, colour, stock FROM product_variants WHERE product_id = ?';
$variant_params = [$product_id];
if ($variant_id > 0) {
    $variant_sql .= ' AND variant_id = ?';
    $variant_params[] = $variant_id;
}
$variant_sql .= ' ORDER BY variant_id LIMIT 1';
$variant_stmt = $_db->prepare($variant_sql);
$variant_stmt->execute($variant_params);
$variant = $variant_stmt->fetch();

if (!$variant) {
    require '_head.php';
    echo "<div style='text-align:center; margin: 50px;'><h3>Product variant not found.</h3><a href='products.php'>Back to shop</a></div>";
    require '_foot.php';
    exit;
}
$stock = (int) ($variant->stock ?? 0);
$display_price = (float) $product->price;

$product_images = [];
try {
    $images_stmt = $_db->prepare(
        'SELECT image_url FROM product_images WHERE product_id = ? ORDER BY sort_order, image_id'
    );
    $images_stmt->execute([$product_id]);
    $product_images = $images_stmt->fetchAll();
} catch (PDOException $e) {
    // The primary image still works if the gallery table has not been imported.
}

if (!$product_images) {
    $product_images = [(object) ['image_url' => $product->image_url]];
}

$gallery_image_urls = [];
foreach ($product_images as $image) {
    $gallery_image_urls[] = $image->image_url;
}

require '_head.php';
?>

<div class="product-detail-container">
    <div class="product-gallery">
        <div class="product-gallery-main" data-images="<?= encode(json_encode($gallery_image_urls)) ?>">
            <img id="product-main-image" src="<?= encode($product_images[0]->image_url) ?>" alt="<?= encode($product->name) ?>">
            <?php if (count($product_images) > 1): ?>
                <button type="button" class="product-gallery-button previous" aria-label="Previous product image">&#8249;</button>
                <button type="button" class="product-gallery-button next" aria-label="Next product image">&#8250;</button>
                <span class="product-gallery-count">1 / <?= count($product_images) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="product-details-panel" style="flex: 1.2; display: flex; flex-direction: column; gap: 15px;">
        <h2><?= encode($product->name) ?></h2>
        <p style="color: #666; line-height: 1.6;"><?= encode($product->description) ?></p>
        <div style="font-size: 28px; font-weight: bold; color: #111;">RM <?= number_format($display_price, 2) ?></div>

        <hr style="border: 0; border-top: 1px solid #eee; margin: 10px 0;">

        <div class="product-specifications">
            <p><strong>Size:</strong> <?= encode($variant->size ?? 'Not specified') ?></p>
            <p><strong>Colour:</strong> <?= encode($variant->colour ?? 'Not specified') ?></p>
        </div>

        <form method="post" action="handle_cart.php" id="add-to-cart-form" style="display: flex; flex-direction: column; gap: 20px;">
            <input type="hidden" name="action" value="add_to_cart">
            <input type="hidden" name="product_id" value="<?= $product->product_id ?>">
            <input type="hidden" name="variant_id" value="<?= $variant->variant_id ?>">

            <div>
                <label for="quantity-input" style="display: block; font-weight: bold; margin-bottom: 5px; font-size: 14px;">Quantity:</label>
                <input type="number" id="quantity-input" name="quantity" value="1" min="1" max="<?= $stock ?>" <?= $stock < 1 ? 'disabled' : '' ?> style="width: 80px; padding: 8px; border: 1px solid #ccc; border-radius: 4px; text-align: center; font-size: 15px;">
                <small style="display:block; color: #666; margin-top: 6px; font-weight: 500;">
                    <?= $stock > 0 ? "Available inventory: $stock units" : 'Out of stock' ?>
                </small>
            </div>

            <button type="submit" <?= $stock < 1 ? 'disabled' : '' ?> style="padding: 14px; background: #111; color: white; border: none; border-radius: 4px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 10px; text-transform: uppercase;">
                <?= $stock > 0 ? 'Add to Shopping Cart' : 'Out of Stock' ?>
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const mainImage = document.getElementById('product-main-image');
    const gallery = document.querySelector('.product-gallery-main');
    const images = JSON.parse(gallery.dataset.images);

    if (images.length > 1) {
        const count = gallery.querySelector('.product-gallery-count');
        let current = 0;

        function showImage(index) {
            current = (index + images.length) % images.length;
            mainImage.src = images[current];
            count.textContent = (current + 1) + ' / ' + images.length;
        }

        gallery.querySelector('.previous').addEventListener('click', function () {
            showImage(current - 1);
        });
        gallery.querySelector('.next').addEventListener('click', function () {
            showImage(current + 1);
        });
    }

    const form = document.getElementById('add-to-cart-form');
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        fetch('handle_cart.php', { method: 'POST', body: new FormData(form) })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.message) alert(data.message);
                window.location.href = 'products.php';
            })
            .catch(function () { window.location.href = 'products.php'; });
    });
});
</script>

<?php require '_foot.php'; ?>
