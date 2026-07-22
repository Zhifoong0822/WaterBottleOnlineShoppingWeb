<!-- display prod, search prod, filter with category, stock, add btn, view btn, edit btn, delete btn -->

<?php
require_once '../../_base.php';

$search = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? '';

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
    WHERE 1=1
    ";

    $params = [];

    if ($search != '') {

        $sql .= "
            AND (
                p.name LIKE ?
                OR c.category_name LIKE ?
                OR pv.size LIKE ?
                OR pv.colour LIKE ?
            )
        ";

        $keyword = "%{$search}%";

        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
    }
    if ($category != '') {

        $sql .= "
            AND p.category_id = ?
        ";

        $params[] = $category;
    }
    $sql .= "
    ORDER BY p.product_id ASC
    ";

$stmt = $_db->prepare($sql);

$stmt->execute($params);

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_products = count($products);

$total_categories = $_db->query("
    SELECT COUNT(*) FROM categories
")->fetchColumn();

$low_stock_products = $_db->query("
    SELECT COUNT(*)
    FROM product_variants
    WHERE stock > 0
      AND stock <= 5
")->fetchColumn();

$out_of_stock_products = $_db->query("
    SELECT COUNT(*)
    FROM product_variants
    WHERE stock = 0
")->fetchColumn();

$categories = $_db->query("
SELECT *
FROM categories
ORDER BY category_name
")->fetchAll(PDO::FETCH_ASSOC);

$_title = 'Product Management';
include '../../_head.php';
?>

<link rel="stylesheet" href="../../css/main.css">
<link rel="stylesheet" href="../../css/admin.css">

<div class="admin-container">
    <div class="dashboard-cards">
        <div class="dashboard-card">
            <h3>Total Products</h3>
            <h2><?= $total_products ?></h2>
        </div>

        <div class="dashboard-card">
            <h3>Total Categories</h3>
            <h2><?= $total_categories ?></h2>
        </div>

        <div class="dashboard-card">
            <h3>Low Stock Products</h3>
            <h2><?= $low_stock_products ?></h2>
        </div>

        <div class="dashboard-card">
            <h3>Out of Stock Products</h3>
            <h2><?= $out_of_stock_products ?></h2>
        </div>
    </div>

    <div class="product-card">
        <div class="table-tools">
            <div class="tool-left">
                <form method="GET" class="tool-left">
                    <input
                        type="text"
                        name="search"
                        class="search-box"
                        placeholder="Search product..."
                        value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                    >

                    <select
                        name="category"
                        class="category-filter"
                    >

                        <option value="">All Categories</option>

                        <?php foreach ($categories as $cat): ?>

                            <option
                                value="<?= $cat['category_id'] ?>"
                                <?= ($category == $cat['category_id']) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <button type="submit" class="add-button">
                        Search
                    </button>
                    <a href="admin_products.php" class="add-button reset-button">
                        Reset
                    </a>
                </form>
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