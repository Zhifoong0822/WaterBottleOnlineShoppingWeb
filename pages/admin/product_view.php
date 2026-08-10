<?php
require_once '../../_base.php';

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
                $imagePath = $product['image_url'];

                if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
                    $imageSrc = $imagePath;
                } else {
                    $imageSrc = '../../' . ltrim($imagePath, '/');
                }
                ?>

                <img
                    src="<?= htmlspecialchars($imageSrc) ?>"
                    alt="<?= htmlspecialchars($product['name']) ?>"
                    class="view-product-image"
                >
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

<?php include '../../_foot.php'; ?>