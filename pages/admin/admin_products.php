<!-- display prod, search prod, filter with category, stock, add btn, view btn, edit btn, delete btn -->

<?php
require_once '../../_base.php';

$sql = "
    SELECT
        p.*,
        c.category_name
    FROM products p
    LEFT JOIN categories c
        ON p.category_id = c.category_id
    ORDER BY p.product_id ASC
";

$stmt = $_db->query($sql);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_products = count($products);

$_title = 'Product Management';
include '../../_head.php';
?>

<link rel="stylesheet" href="../../css/main.css">
<link rel="stylesheet" href="../../css/admin.css">

<div class="admin-container">
    <div class="page-header">
        <div class="product-count">
            Total Products: <?php echo $total_products; ?>
        </div>
    </div>

    <div class="product-card">
        <div class="table-tools">
            <div class="tool-left">
                <input
                    type="text"
                    class="search-box"
                    placeholder="Search product..."
                >
                <select class="category-filter">
                    <option>All Categories</option>
                </select>
            </div>
            <a href="product_add.php" class="add-button">
                + Add Product
            </a>
        </div>

        <div class="table-wrapper">
            <table class="product-table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Size</th>
                        <th>Colour</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (!empty($products)): ?>

                        <?php foreach ($products as $product): ?>

                            <?php
                            if ($product['stock'] > 5) {
                                $stockClass = 'stock-in';
                                $stockText = 'In Stock';
                            } elseif ($product['stock'] > 0) {
                                $stockClass = 'stock-low';
                                $stockText = 'Low Stock';
                            } else {
                                $stockClass = 'stock-out';
                                $stockText = 'Out of Stock';
                            }
                            ?>

                            <tr>

                                <td>
                                    <img
                                        src="<?= htmlspecialchars($product['image_url']) ?>"
                                        class="product-image"
                                        alt="<?= htmlspecialchars($product['name']) ?>"
                                    >
                                </td>

                                <td><?= htmlspecialchars($product['name']) ?></td>

                                <td><?= htmlspecialchars($product['category_name']) ?></td>

                                <td><?= htmlspecialchars($product['size']) ?></td>

                                <td><?= htmlspecialchars($product['colour']) ?></td>

                                <td class="product-price">
                                    RM <?= number_format($product['price'], 2) ?>
                                </td>

                                <td><?= $product['stock'] ?></td>

                                <td>
                                    <span class="stock-badge <?= $stockClass ?>">
                                        <?= $stockText ?>
                                    </span>
                                </td>

                                <td class="action-buttons">

                                    <a href="product_view.php?id=<?= $product['product_id'] ?>"
                                    class="btn-view">
                                        View
                                    </a>

                                    <a href="product_edit.php?id=<?= $product['product_id'] ?>"
                                    class="btn-edit">
                                        Edit
                                    </a>

                                    <a href="product_delete.php?id=<?= $product['product_id'] ?>"
                                    class="btn-delete">
                                        Delete
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                    <tr>
                        <td colspan="9" class="no-products">
                            No products found.
                        </td>
                    </tr>

                    <?php endif; ?>

                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../_foot.php'; ?>