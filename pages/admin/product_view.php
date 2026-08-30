<?php
require_once '../../_base.php';
require_admin('../../products.php');

$_title = 'View Product';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin_products.php');
    exit;
}

$product_id = $_GET['id'];

$sql = "
SELECT
    p.*,
    c.category_name,
    pv.variant_id,
    pv.size,
    pv.colour,
    pv.stock

FROM products p

LEFT JOIN categories c
    ON p.category_id = c.category_id

LEFT JOIN product_variants pv
    ON p.product_id = pv.product_id

WHERE p.product_id = ?
";

$stmt = $_db->prepare($sql);
$stmt->execute([$product_id]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: admin_products.php');
    exit;
}
/* ==========================================
   Load Product Images
========================================== */

$image_stmt = $_db->prepare("
    SELECT image_id, image_url
    FROM product_images
    WHERE product_id = ?
    ORDER BY image_id ASC
");

$image_stmt->execute([$product_id]);

$additional_images = $image_stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../_head.php';
?>

<link rel="stylesheet" href="../../css/main.css">
<link rel="stylesheet" href="../../css/admin.css">

<div class="admin-container">
    <div class="view-card">
        <div class="view-header">
            <h2>Product Details</h2>
        </div>
        <div class="view-content">
            <div class="view-image">
                <?php
                /* ==========================================
                view Images
                Main image + additional images
                ========================================== */
                $product_images = [];

                // Main product image
                if (!empty($product['image_url'])) {
                    $product_images[] = $product['image_url'];
                }
                // Additional product images
                foreach ($additional_images as $img) {
                    $product_images[] = $img['image_url'];
                }
                ?>
                <!-- Main Image -->
                <div class="product-gallery">

                    <button
                        type="button"
                        class="gallery-arrow gallery-prev"
                        id="prevImage">
                        &#10094;
                    </button>

                    <div class="gallery-main">
                        <img
                            id="mainProductImage"
                            src="<?= htmlspecialchars(
                                str_starts_with($product_images[0], 'http://') ||
                                str_starts_with($product_images[0], 'https://')
                                    ? $product_images[0]
                                    : '../../' . ltrim($product_images[0], '/')
                            ) ?>"
                            alt="<?= htmlspecialchars($product['name']) ?>"
                        >
                    </div>
                    <button
                        type="button"
                        class="gallery-arrow gallery-next"
                        id="nextImage">
                        &#10095;
                    </button>
                </div>
                <!-- Image Counter -->
                <div
                    class="gallery-counter"
                    id="imageCounter">
                    1 / <?= count($product_images) ?>
                </div>
                <!-- Thumbnails -->
                <div class="gallery-thumbnails">
                    <?php foreach ($product_images as $index => $imagePath): ?>
                        <?php
                        if (
                            str_starts_with($imagePath, 'http://') ||
                            str_starts_with($imagePath, 'https://')
                        ) {
                            $imageSrc = $imagePath;
                        } else {
                            $imageSrc = '../../' . ltrim($imagePath, '/');
                        }
                        ?>
                        <button
                            type="button"
                            class="gallery-thumbnail <?= $index === 0 ? 'active' : '' ?>"
                            data-index="<?= $index ?>"
                            data-image="<?= htmlspecialchars($imageSrc) ?>"
                        >

                            <img
                                src="<?= htmlspecialchars($imageSrc) ?>"
                                alt="<?= htmlspecialchars($product['name']) ?> image <?= $index + 1 ?>"
                            >

                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="view-details">
                <div class="detail-row">
                    <label>Product Name</label>
                    <span><?= htmlspecialchars($product['name']) ?></span>
                </div>

                <div class="detail-row">
                    <label>Category</label>
                    <span><?= htmlspecialchars($product['category_name']) ?></span>
                </div>

                <div class="detail-row">
                    <label>Size</label>
                    <span><?= htmlspecialchars($product['size']) ?></span>
                </div>

                <div class="detail-row">
                    <label>Price</label>
                    <span>
                        RM <?= number_format($product['price'],2) ?>
                    </span>
                </div>

                <div class="detail-row">
                    <label>Stock</label>
                    <span><?= $product['stock'] ?></span>
                </div>
                <div class="detail-row">
                    <label>Colour</label>
                    <div class="colour-display">
                        <span class="colour-name">
                            <?= htmlspecialchars($product['colour']) ?>
                        </span>
                    </div>
                </div>
                <div class="detail-row">
                    <label>Status</label>
                    <?php
                    if($product['stock'] > 5){
                        echo '<span class="stock-badge stock-in">In Stock</span>';
                    }
                    elseif($product['stock'] > 0){
                        echo '<span class="stock-badge stock-low">Low Stock</span>';
                    }
                    else{
                        echo '<span class="stock-badge stock-out">Out of Stock</span>';
                    }
                    ?>
                </div>
                <div class="detail-row description">
                    <label>Description</label>
                    <p>
                        <?= nl2br(htmlspecialchars($product['description'])) ?>
                    </p>

                </div>

                <div class="detail-row">
                    <label>Video URL</label>
                    <span>
                        <?= $product["video_url"]
                        ? encode($product["video_url"])
                        : "No video available" ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="view-footer">
            <a href="admin_products.php" class="btn-view">
                Back
            </a>
            <a
                href="product_edit.php?id=<?= $product['product_id'] ?>"
                class="btn-edit">
                Edit Product
            </a>
        </div>
    </div>
</div>
<script>
const productImages = <?= json_encode(
    array_map(function($imagePath) {

        if (
            str_starts_with($imagePath, 'http://') ||
            str_starts_with($imagePath, 'https://')
        ) {
            return $imagePath;
        }

        return '../../' . ltrim($imagePath, '/');

    }, $product_images)
) ?>;

let currentImage = 0;

const mainImage =
    document.getElementById('mainProductImage');

const counter =
    document.getElementById('imageCounter');

const thumbnails =
    document.querySelectorAll('.gallery-thumbnail');

const prevButton =
    document.getElementById('prevImage');

const nextButton =
    document.getElementById('nextImage');


function showImage(index) {

    if (productImages.length === 0) {
        return;
    }

    /*
    Keep index inside valid range
    */

    if (index < 0) {
        index = productImages.length - 1;
    }

    if (index >= productImages.length) {
        index = 0;
    }

    currentImage = index;


    /*
    Update main image
    */

    mainImage.src =
        productImages[currentImage];


    /*
    Update counter
    */

    counter.textContent =
        (currentImage + 1)
        + ' / '
        + productImages.length;


    /*
    Update active thumbnail
    */

    thumbnails.forEach(function(thumbnail, i) {

        thumbnail.classList.toggle(
            'active',
            i === currentImage
        );

    });

}


/*
Previous image
*/

prevButton.addEventListener(
    'click',
    function() {

        showImage(currentImage - 1);

    }
);


/*
Next image
*/

nextButton.addEventListener(
    'click',
    function() {

        showImage(currentImage + 1);

    }
);


/*
Click thumbnail
*/

thumbnails.forEach(function(thumbnail) {

    thumbnail.addEventListener(
        'click',
        function() {

            const index =
                parseInt(
                    this.dataset.index
                );

            showImage(index);

        }
    );

});


/*
Keyboard navigation
*/

document.addEventListener(
    'keydown',
    function(event) {

        if (event.key === 'ArrowLeft') {
            showImage(currentImage - 1);
        }

        if (event.key === 'ArrowRight') {
            showImage(currentImage + 1);
        }

    }
);
</script>
<?php include '../../_foot.php'; ?>
