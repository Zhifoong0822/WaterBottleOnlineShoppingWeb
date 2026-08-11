<!-- Admin Product List > Click "Delete" > Confirmation: "Are you sure you want to delete this product?"
    > Cancel (Back to Product List) / Confirm > Check product exists > Delete product / related variant
    > Success message > Admin Product List -->

<?php
require_once '../../_base.php';

$_title = 'Delete Product';

/*----------------------------------------------------------
    Validate Product ID
-----------------------------------------------------------*/

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin_products.php');
    exit;
}

$product_id = (int) $_GET['id'];

/*----------------------------------------------------------
    Get Product
-----------------------------------------------------------*/

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
    WHERE p.product_id = ?
");

$stmt->execute([$product_id]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

/*----------------------------------------------------------
    Product Not Found
-----------------------------------------------------------*/

if (!$product) {
    header('Location: admin_products.php');
    exit;
}

/*----------------------------------------------------------
    Delete Product
-----------------------------------------------------------*/

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['confirm']) && $_POST['confirm'] == 'yes') {

        try {

            $_db->beginTransaction();

            /* Delete product variants first */
            $stmt = $_db->prepare("
                DELETE FROM product_variants
                WHERE product_id = ?
            ");

            $stmt->execute([$product_id]);

            /* Delete product */
            $stmt = $_db->prepare("
                DELETE FROM products
                WHERE product_id = ?
            ");

            $stmt->execute([$product_id]);

            /* Complete transaction */
            $_db->commit();

            /* Return to product list */
            header('Location: admin_products.php?deleted=1');
            exit;

        } catch (Exception $e) {

            /* Roll back if deletion fails */
            if ($_db->inTransaction()) {
                $_db->rollBack();
            }

            $error = 'Unable to delete the product. Please try again.';
        }
    }
}

include '../../_head.php';
?>

<link rel="stylesheet" href="../../css/main.css">
<link rel="stylesheet" href="../../css/admin.css">

<div class="admin-container">

    <div class="edit-card">

        <!-- Header -->
        <!-- <div class="edit-header">
            <h2>Delete Product</h2>
        </div> -->

        <!-- Error Message -->
        <?php if (isset($error)): ?>
            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Confirmation -->
        <div class="delete-content">
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
                    Delete Product
                </button>
            </form>
        </div>
    </div>
</div>
<?php include '../../_foot.php'; ?>