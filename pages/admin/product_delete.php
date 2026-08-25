<!-- Admin Product List > Click "Delete" (or select rows and "Delete Selected")
    > Confirmation: "Are you sure you want to delete this product / these products?"
    > Cancel (Back to Product List) / Confirm > Check product(s) exist > Delete product(s) / related variants
    > Success message > Admin Product List -->

<?php
require_once '../../_base.php';
require_admin('../../products.php');

$_title = 'Delete Product';

/*----------------------------------------------------------
    Determine Mode: Single (?id=) vs Batch (?ids[]=)
-----------------------------------------------------------*/

$is_batch = isset($_GET['ids']) && is_array($_GET['ids']);

if ($is_batch) {

    $product_ids = array_map('intval', $_GET['ids']);
    $product_ids = array_filter($product_ids, function ($id) {
        return $id > 0;
    });
    $product_ids = array_values(array_unique($product_ids));

    if (empty($product_ids)) {
        header('Location: admin_products.php');
        exit;
    }

} else {

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        header('Location: admin_products.php');
        exit;
    }

    $product_ids = [(int) $_GET['id']];
}

/*----------------------------------------------------------
    Get Product(s)
-----------------------------------------------------------*/

$placeholders = implode(',', array_fill(0, count($product_ids), '?'));

$stmt = $_db->prepare("
    SELECT
        p.product_id,
        p.name,
        p.image_url,
        p.price,
        c.category_name
    FROM products p
    LEFT JOIN categories c
        ON p.category_id = c.category_id
    WHERE p.product_id IN ($placeholders)
");

$stmt->execute($product_ids);

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*----------------------------------------------------------
    Product(s) Not Found
-----------------------------------------------------------*/

if (empty($products)) {
    header('Location: admin_products.php');
    exit;
}

/*
Single-mode template below still refers to $product -
keep it available for backward compatibility.
*/

$product = $products[0];

/*----------------------------------------------------------
    Delete Product(s)
-----------------------------------------------------------*/

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['confirm']) && $_POST['confirm'] == 'yes') {

        try {

            $_db->beginTransaction();

            $placeholders = implode(',', array_fill(0, count($product_ids), '?'));

            /* Delete additional product images first */
            $stmt = $_db->prepare("
                DELETE FROM product_images
                WHERE product_id IN ($placeholders)
            ");

            $stmt->execute($product_ids);

            /* Delete product variants */
            $stmt = $_db->prepare("
                DELETE FROM product_variants
                WHERE product_id IN ($placeholders)
            ");

            $stmt->execute($product_ids);

            /* Delete product(s) */
            $stmt = $_db->prepare("
                DELETE FROM products
                WHERE product_id IN ($placeholders)
            ");

            $stmt->execute($product_ids);

            /* Complete transaction */
            $_db->commit();

            /* Return to product list */
            header(
                'Location: admin_products.php?deleted='
                . count($product_ids)
            );
            exit;

        } catch (Exception $e) {

            /* Roll back if deletion fails */
            if ($_db->inTransaction()) {
                $_db->rollBack();
            }

            $error = 'Unable to delete the product(s). Please try again.';
        }
    }
}

include '../../_head.php';
?>

<link rel="stylesheet" href="../../css/main.css">
<link rel="stylesheet" href="../../css/admin.css">

<div class="admin-container">

    <div class="edit-card">

        <!-- Error Message -->
        <?php if (isset($error)): ?>
            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Confirmation -->
        <div class="delete-content">

            <?php if ($is_batch): ?>

                <p>
                    Are you sure you want to delete these
                    <strong><?= count($products) ?></strong> products?
                </p>

                <?php foreach ($products as $p): ?>

                    <div class="delete-product-info">
                        <div class="delete-product-image">
                            <img
                                src="../../<?= htmlspecialchars($p['image_url']) ?>"
                                alt="<?= htmlspecialchars($p['name']) ?>"
                                class="delete-image"
                            >
                        </div>
                        <div class="delete-product-details">
                            <h3>
                                <?= htmlspecialchars($p['name']) ?>
                            </h3>
                            <p>
                                <strong>Category:</strong>
                                <?= htmlspecialchars($p['category_name']) ?>
                            </p>
                            <p>
                                <strong>Price:</strong>
                                RM <?= number_format($p['price'], 2) ?>
                            </p>
                        </div>
                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <p>
                    Are you sure you want to delete this product?
                </p>

                <!-- Product Information -->
                <div class="delete-product-info">
                    <div class="delete-product-image">
                        <img
                            src="../../<?= htmlspecialchars($product['image_url']) ?>"
                            alt="<?= htmlspecialchars($product['name']) ?>"
                            class="delete-image"
                        >
                    </div>
                    <div class="delete-product-details">
                        <h3>
                            <?= htmlspecialchars($product['name']) ?>
                        </h3>
                        <p>
                            <strong>Category:</strong>
                            <?= htmlspecialchars($product['category_name']) ?>
                        </p>
                        <p>
                            <strong>Price:</strong>
                            RM <?= number_format($product['price'], 2) ?>
                        </p>
                    </div>
                </div>

            <?php endif; ?>

            <!-- Warning -->
            <p class="delete-warning">
                All product variants and stock information will also be deleted.
            </p>
            <p class="delete-warning">
                This action cannot be undone.
            </p>
        </div>

        <!-- Buttons -->
        <div class="edit-footer">
            <a
                href="admin_products.php"
                class="btn-view">
                Cancel
            </a>
            <form method="post">
                <input
                    type="hidden"
                    name="confirm"
                    value="yes"
                >
                <button
                    type="submit"
                    class="btn-delete">
                    <?= $is_batch
                        ? 'Delete ' . count($products) . ' Products'
                        : 'Delete Product'
                    ?>
                </button>
            </form>
        </div>
    </div>
</div>
<?php include '../../_foot.php'; ?>