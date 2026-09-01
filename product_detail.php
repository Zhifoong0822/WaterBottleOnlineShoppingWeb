<?php
require_once '_base.php';

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

// Check whether current product is already in wishlist
$is_wishlisted = false;

if (isset($_SESSION['users'])) {
    $wishlist_stmt = $_db->prepare("SELECT wishlist_id FROM wishlist WHERE user_id = ? AND product_id = ? AND variant_id = ? LIMIT 1");
    $wishlist_stmt->execute([$_SESSION['users']->user_id, $product_id, $variant->variant_id]);
    $is_wishlisted = (bool) $wishlist_stmt->fetch();
}

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

function getYoutubeEmbedUrl($url)
{
    if (!$url) {
        return null;
    }

    $parts = parse_url($url);

    if (!$parts || empty($parts['host'])) {
        return null;
    }

    $host = strtolower($parts['host']);
    $path = $parts['path'] ?? '';

    // Normal YouTube URL
    if ($host === 'www.youtube.com' || $host === 'youtube.com') {

        if (!empty($parts['query'])) {

            parse_str($parts['query'], $query);

            if (!empty($query['v'])) {
                return 'https://www.youtube.com/embed/' . rawurlencode($query['v']);
            }
        }

        if (str_starts_with($path, '/shorts/')) {

            $video_id = trim(
                substr($path, strlen('/shorts/'))
            );

            if ($video_id !== '') {
                return 'https://www.youtube.com/embed/' . rawurlencode($video_id);
            }
        }

        if (str_starts_with($path, '/embed/')) {

            return 'https://www.youtube.com' . $path;
        }
    }

    // Short YouTube URL
    if ($host === 'youtu.be') {

        $video_id = trim($path, '/');

        if ($video_id !== '') {
            return 'https://www.youtube.com/embed/' . rawurlencode($video_id);
        }
    }

    return null;
}
 
$youtube_embed_url = getYoutubeEmbedUrl($product->video_url ?? null); 
 
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

        <button type="button" id="product-wishlist-btn" data-product-id="<?= (int) $product->product_id ?>" data-variant-id="<?= (int) $variant->variant_id ?>" aria-label="Toggle wishlist" style="width:48px; height:48px; border:1px solid #ddd; border-radius:50%; background:white; font-size:28px; cursor:pointer; display:flex; align-items:center; justify-content:center; color:<?= $is_wishlisted ? 'red' : '#111' ?>;"><?= $is_wishlisted ? '♥' : '♡' ?></button>

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
 
</div> 
 
<?php if ($youtube_embed_url): ?> 
 
<div 
    class="product-video-section" 
    style=" 
        max-width:1200px; 
        margin:40px auto; 
        padding:0 20px; 
    " 
> 
 
    <h3 
        style=" 
            margin-bottom:20px; 
            font-size:22px; 
        " 
    > 
        Product Video 
    </h3> 
 
 
    <div 
        class="product-video-container" 
        style=" 
            position:relative; 
            width:100%; 
            aspect-ratio:9 / 16; 
            max-width:360px; 
            margin:0 auto; 
            overflow:hidden; 
            border-radius:8px; 
            background:#000; 
        " 
    > 
 
        <iframe 
            src="<?= encode($youtube_embed_url) ?>" 
            title="<?= encode($product->name) ?> Product Video" 
            style=" 
                position:absolute; 
                top:0; 
                left:0; 
                width:100%; 
                height:100%; 
                border:0; 
            " 
            allow=" 
                accelerometer; 
                autoplay; 
                clipboard-write; 
                encrypted-media; 
                gyroscope; 
                picture-in-picture; 
                web-share 
            " 
            allowfullscreen 
        > 
        </iframe> 
 
    </div> 
 
</div> 
 
<?php endif; ?> 
 
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

    // Add to cart
    const form = document.getElementById('add-to-cart-form');
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        fetch('handle_cart.php', { method: 'POST', body: new FormData(form) })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.redirect) {
                    if (data.message) alert(data.message);
                    window.location.href = data.redirect;
                    return;
                }
                if (data.message) alert(data.message);
                window.location.href = 'products.php';
            })
            .catch(function () { window.location.href = 'login.php'; });
    });

    // Wishlist button
    const wishlistButton = document.getElementById('product-wishlist-btn');
    if (wishlistButton) {
        wishlistButton.addEventListener('click', function () {
            const formData = new FormData();
            formData.append('product_id', wishlistButton.dataset.productId);
            formData.append('variant_id', wishlistButton.dataset.variantId);

            fetch('wishlist_handler.php', { method: 'POST', body: formData })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data.success) {
                        alert(data.message);
                        return;
                    }

                    if (data.wishlisted) {
                        wishlistButton.textContent = '♥';
                        wishlistButton.style.color = 'red';
                    } else {
                        wishlistButton.textContent = '♡';
                        wishlistButton.style.color = '#111';
                    }
                })
                .catch(function () {
                    alert('Unable to update wishlist.');
                });
        });
    }
});
</script>
 
<?php require '_foot.php'; ?>