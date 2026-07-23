<?php
require_once '../../_base.php';

$_title = 'Edit Product';

/*----------------------------------------------------------
    Validate Product ID
-----------------------------------------------------------*/
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_products.php");
    exit;
}

$product_id = (int)$_GET['id'];

/*----------------------------------------------------------
    Get Categories
-----------------------------------------------------------*/
$categories = $_db->query("
SELECT *
FROM categories
ORDER BY category_name
")->fetchAll(PDO::FETCH_ASSOC);

/*----------------------------------------------------------
    Load Product + Colour
-----------------------------------------------------------*/
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
    header("Location: admin_products.php");
    exit;
}

/*----------------------------------------------------------
    Default Values
-----------------------------------------------------------*/
$name        = $product['name'];
$category_id = $product['category_id'];
$price       = $product['price'];
$size        = $product['size'];
$description = $product['description'];
$image_url   = $product['image_url'];

$colour = $product['colour'];
$stock       = $product['stock'];

$error = [];

/*----------------------------------------------------------
    Save Changes
-----------------------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $name        = trim($_POST['name']);
    $category_id = $_POST['category'];
    $price       = $_POST['price'];
    $size        = trim($_POST['size']);
    $description = trim($_POST['description']);

    $colour = trim($_POST['colour']);
    $stock       = $_POST['stock'];

    /*-------------------------
        Validation
    --------------------------*/
    if ($name == '')
        $error['name'] = "Product name is required.";

    if ($price == '' || !is_numeric($price))
        $error['price'] = "Valid price is required.";

    if ($stock == '' || !is_numeric($stock))
        $error['stock'] = "Valid stock is required.";

    if ($colour == '')
        $error['colour'] = "Colour is required.";

    /*-------------------------
        Upload Image
    --------------------------*/
    if (!empty($_FILES['image']['name'])) {

        $folder = "../../uploads/";

        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        $filename = time() . "_" . basename($_FILES['image']['name']);

        $target = $folder . $filename;

        move_uploaded_file(
            $_FILES['image']['tmp_name'],
            $target
        );
        $image_url = "../../uploads/" . $filename;
    }

    /*-------------------------
        Update Database
    --------------------------*/
    if (empty($error)) {
        $_db->beginTransaction();
        try {
            $stmt = $_db->prepare("
            UPDATE products
            SET

            category_id=?,
            name=?,
            description=?,
            price=?,
            image_url=?

            WHERE product_id=?
            ");

            $stmt->execute([

                $category_id,
                $name,
                $description,
                $price,
                $image_url,
                $product_id

            ]);

            $stmt = $_db->prepare("
            UPDATE product_variants
            SET

                size=?,
                colour=?,
                stock=?

            WHERE variant_id=?
            ");

            $stmt->execute([

                $size,
                $colour,
                $stock,
                $product['variant_id']

            ]);

            $_db->commit();

            header("Location: product_view.php?id=".$product_id);
            exit;
        }
        catch(Exception $e){
            $_db->rollBack();
            $error['database'] = $e->getMessage();
        }
    }
}

include '../../_head.php';
?>

<link rel="stylesheet" href="../../css/main.css">
<link rel="stylesheet" href="../../css/admin.css">

<div class="admin-container">
    <form method="post" enctype="multipart/form-data" class="edit-card">
        <div class="edit-header">
            <h2>Edit Product</h2>
        </div>
        <?php if (!empty($error['database'])) : ?>
            <div class="error-message">
                <?= $error['database'] ?>
            </div>
        <?php endif; ?>

        <div class="edit-content">
            <!-- Left Side -->
            <div class="edit-image">
                <img
                    src="<?= htmlspecialchars($image_url) ?>"
                    class="edit-product-image"
                    alt="Product Image">
                <input
                    type="file"
                    name="image"
                    class="file-input">
            </div>

            <!-- Right Side -->
            <div class="edit-form">
                <!-- Product Name -->
                <div class="form-group">
                    <label>Product Name</label>
                    <input
                        type="text"
                        name="name"
                        value="<?= htmlspecialchars($name) ?>">
                    <small class="error">
                        <?= $error['name'] ?? '' ?>
                    </small>
                </div>

                <!-- Category -->
                <div class="form-group">
                    <label>Category</label>
                    <select name="category">
                        <?php foreach ($categories as $cat): ?>
                            <option
                                value="<?= $cat['category_id'] ?>"
                                <?= ($category_id == $cat['category_id']) ? 'selected' : '' ?>>

                                <?= htmlspecialchars($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Size -->
                <div class="form-group">
                    <label>Size</label>
                    <!-- <input
                        type="text"
                        name="size"
                        value="<?= htmlspecialchars($size) ?>"> -->
                        <select name="size">
                            <?php
                            $sizes = [
                                '400ml',
                                '410ml',
                                '500ml',
                                '530ml',
                                '600ml',
                                '950ml',
                                'Standard',
                                'Universal'
                            ];
                            foreach($sizes as $s):
                            ?>
                                <option value="<?= $s ?>"
                                    <?= ($size == $s) ? 'selected' : '' ?>>
                                    <?= $s ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                </div>

                <!-- Price -->
                <div class="form-group">
                    <label>Price (RM)</label>
                    <input
                        type="number"
                        step="0.01"
                        name="price"
                        value="<?= htmlspecialchars($price) ?>">

                    <small class="error">
                        <?= $error['price'] ?? '' ?>
                    </small>
                </div>

                <!-- Colour Name -->
                <div class="form-group">
                    <label>Colour</label>
                    <input
                        type="text"
                        name="colour"
                        value="<?= htmlspecialchars($colour) ?>">

                    <small class="error">
                        <?= $error['colour'] ?? '' ?>
                    </small>
                </div>

                <!-- Colour Picker -->
                <!-- <div class="form-group">

                    <label>Colour</label>

                    <input
                        type="color"
                        name="colour_code"
                        value="<?= htmlspecialchars($colour) ?>">

                </div> -->
                <!-- Stock -->
                <div class="form-group">
                    <label>Stock</label>
                    <input
                        type="number"
                        name="stock"
                        min="0"
                        value="<?= htmlspecialchars($stock) ?>">

                    <small class="error">
                        <?= $error['stock'] ?? '' ?>
                    </small>
                </div>
                <!-- Description -->
                <div class="form-group full-width">

                    <label>Description</label>

                    <textarea
                        name="description"
                        rows="6"><?= htmlspecialchars($description) ?></textarea>
                </div>
            </div>
        </div>

        <div class="edit-footer">
            <a href="admin_products.php" class="btn-view">
                Cancel
            </a>
            <button
                type="submit"
                class="btn-edit">

                Save Changes

            </button>
        </div>
    </form>
</div>

<?php include '../../_foot.php'; ?>