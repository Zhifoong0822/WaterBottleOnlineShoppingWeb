<?php
require_once '../../_base.php';
<<<<<<< HEAD
require_admin('../../products.php');

$_title = 'Add Product';
$categories = $_db->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetchAll(PDO::FETCH_ASSOC);
$error = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $categoryId = (int) ($_POST['category'] ?? 0);
    $price = $_POST['price'] ?? '';
    $size = trim($_POST['size'] ?? '');
    $colour = trim($_POST['colour'] ?? '');
    $stock = $_POST['stock'] ?? '';
    $description = trim($_POST['description'] ?? '');

    if ($name === '') $error[] = 'Product name is required.';
    if ($categoryId < 1) $error[] = 'Please select a category.';
    if (!is_numeric($price) || (float) $price < 0) $error[] = 'Enter a valid price.';
    if ($size === '') $error[] = 'Size is required.';
    if ($colour === '') $error[] = 'Colour is required.';
    if (filter_var($stock, FILTER_VALIDATE_INT) === false || (int) $stock < 0) $error[] = 'Enter a valid stock quantity.';
    if (empty($_FILES['images']['name'][0])) $error[] = 'Add at least one product photo.';

    $savedImages = [];
    if (!$error) {
        $uploadDir = '../../uploads/products/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
            $error[] = 'Could not create the product image folder.';
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        foreach ($_FILES['images']['name'] as $index => $originalName) {
            if ($_FILES['images']['error'][$index] !== UPLOAD_ERR_OK) {
                $error[] = 'One of the photos could not be uploaded.';
                break;
            }

            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions, true)) {
                $error[] = 'Photos must be JPG, PNG, or WebP files.';
                break;
            }

            $filename = uniqid('product_', true) . '.' . $extension;
            if (!move_uploaded_file($_FILES['images']['tmp_name'][$index], $uploadDir . $filename)) {
                $error[] = 'One of the photos could not be saved.';
                break;
            }
            $savedImages[] = 'uploads/products/' . $filename;
        }
    }

    if (!$error) {
        $_db->beginTransaction();
        try {
            $stmt = $_db->prepare('INSERT INTO products (category_id, name, description, price, image_url) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$categoryId, $name, $description, $price, $savedImages[0]]);
            $productId = (int) $_db->lastInsertId();

            $stmt = $_db->prepare('INSERT INTO product_variants (product_id, size, colour, stock) VALUES (?, ?, ?, ?)');
            $stmt->execute([$productId, $size, $colour, $stock]);

            $imageStmt = $_db->prepare('INSERT INTO product_images (product_id, image_url, sort_order) VALUES (?, ?, ?)');
            foreach ($savedImages as $position => $imageUrl) {
                $imageStmt->execute([$productId, $imageUrl, $position + 1]);
            }
            $_db->commit();
            header('Location: product_view.php?id=' . $productId);
            exit;
        } catch (Throwable $exception) {
            $_db->rollBack();
            $error[] = 'The product could not be saved. Make sure the product_images table has been imported.';
        }
    }
}

=======

$_title = 'Add Product';

/* ==========================================
   Load Categories
========================================== */
$categories = $_db->query("
    SELECT *
    FROM categories
    ORDER BY category_name
")->fetchAll(PDO::FETCH_ASSOC);
/* ==========================================
   Variables
========================================== */
$name = '';
$category = '';
$new_category = '';
$price = '';
$size = '';
$custom_size = '';
$colour = '';
$stock = '';
$description = '';
$image_url = '';

$error = [];
/* ==========================================
   Form Submitted
========================================== */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $name = trim($_POST['name']);
    $category = $_POST['category'];
    $new_category = trim($_POST['new_category']);

    $price = trim($_POST['price']);

    $size = $_POST['size'];
    $custom_size = trim($_POST['custom_size']);

    $colour = trim($_POST['colour']);

    $stock = trim($_POST['stock']);

    $description = trim($_POST['description']);

    $image_url = '';
    /* ==========================================
       Validation
    ========================================== */
    if ($name == '')
        $error['name'] = 'Product name is required.';

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $image = $_FILES['image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($image['type'], $allowed_types)) {
            $error['image'] = 'Only JPG, PNG and WEBP images are allowed.';
        }
    } else {
        $error['image'] = 'Product image is required.';
    }

    if ($category == '')
        $error['category'] = 'Please select a category.';

    if ($category == 'new' && $new_category == '')
        $error['new_category'] = 'Please enter the new category.';

    if ($price == '')
        $error['price'] = 'Price is required.';
    elseif (!is_numeric($price) || $price < 0)
        $error['price'] = 'Invalid price.';

    if ($stock == '')
        $error['stock'] = 'Stock is required.';
    elseif (!is_numeric($stock) || $stock < 0)
        $error['stock'] = 'Invalid stock.';

    if ($size == '')
        $error['size'] = 'Please select a size.';

    if ($size == 'custom' && $custom_size == '')
        $error['custom_size'] = 'Please enter custom size.';

    if ($colour == '')
        $error['colour'] = 'Colour is required.';
    /* ==========================================
       Save Product
    ========================================== */
    if (empty($error)) {
        /*
        ------------------------------------------
        New Category
        ------------------------------------------
        */
        if ($category == 'new') {
            // Check whether category already exists
            $stmt = $_db->prepare("
                SELECT category_id
                FROM categories
                WHERE category_name = ?
            ");

            $stmt->execute([$new_category]);

            $existing_category = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing_category) {

                // Use existing category
                $category = $existing_category['category_id'];

            } else {

                // Create new category
                $stmt = $_db->prepare("
                    INSERT INTO categories(category_name)
                    VALUES(?)
                ");

                $stmt->execute([$new_category]);

                $category = $_db->lastInsertId();
            }
        }
        /*
        ------------------------------------------
        Custom Size
        ------------------------------------------
        */

        if ($size == 'custom') {

            $size = $custom_size;

        }
        /*
        ------------------------------------------
        Insert Product
        ------------------------------------------
        */

        if (empty($error)) {

            $folder = '../../img/products/';

            if (!is_dir($folder)) {
                mkdir($folder, 0777, true);
            }

            $filename = time() . '_' . basename($_FILES['image']['name']);

            $target = $folder . $filename;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {

                $image_url = 'img/products/' . $filename;

            } else {

                $error['image'] = 'Failed to upload image.';
            }
        }
        $stmt = $_db->prepare("
            INSERT INTO products
            (
                category_id,
                name,
                description,
                price,
                image_url
            )
            VALUES
            (
                ?, ?, ?, ?, ?
            )
        ");

        $stmt->execute([
            $category,
            $name,
            $description,
            $price,
            $image_url
        ]);

        $product_id = $_db->lastInsertId();

        /*
        ------------------------------------------
        Insert Variant
        ------------------------------------------
        */

        $stmt = $_db->prepare("
            INSERT INTO product_variants
            (
                product_id,
                size,
                colour,
                stock
            )
            VALUES
            (
                ?, ?, ?, ?
            )
        ");

        $stmt->execute([
            $product_id,
            $size,
            $colour,
            $stock
        ]);
        /*
        ------------------------------------------
        Redirect
        ------------------------------------------
        */
        header("Location: admin_products.php");
        exit;
    }
}
>>>>>>> 055c30c62a095c55984c52f24f16ca80e9aa6f8f
include '../../_head.php';
?>
<link rel="stylesheet" href="../../css/main.css">
<link rel="stylesheet" href="../../css/admin.css">

<<<<<<< HEAD
<section class="product-card" style="max-width:760px; margin:0 auto;">
    <div class="page-header"><h2>Add Product</h2><p>Create a product with one or more gallery photos.</p></div>
    <?php if ($error): ?>
        <div id="info"><?= htmlspecialchars(implode(' ', $error)) ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="product-form">
        <label>Product name<input required name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"></label>
        <label>Category<select required name="category"><option value="">Select category</option><?php foreach ($categories as $category): ?><option value="<?= $category['category_id'] ?>" <?= (string) $category['category_id'] === ($_POST['category'] ?? '') ? 'selected' : '' ?>><?= htmlspecialchars($category['category_name']) ?></option><?php endforeach; ?></select></label>
        <div class="form-row"><label>Price (RM)<input required min="0" step="0.01" type="number" name="price" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"></label><label>Stock<input required min="0" type="number" name="stock" value="<?= htmlspecialchars($_POST['stock'] ?? '') ?>"></label></div>
        <div class="form-row"><label>Size<input required name="size" placeholder="Medium (18oz / 530ml)" value="<?= htmlspecialchars($_POST['size'] ?? '') ?>"></label><label>Colour<input required name="colour" value="<?= htmlspecialchars($_POST['colour'] ?? '') ?>"></label></div>
        <label>Description<textarea name="description" rows="4"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea></label>
        <label>Product photos <small>Choose multiple files for the product gallery.</small><input required type="file" name="images[]" accept="image/png,image/jpeg,image/webp" multiple></label>
        <div class="form-actions"><a href="admin_products.php" class="btn-view">Cancel</a><button type="submit" class="add-button">Save Product</button></div>
    </form>
</section>
<?php include '../../_foot.php'; ?>
=======
<div class="admin-container">

    <div class="edit-card">

        <div class="edit-header">
            <!-- <h2>Add a Bottle</h2> -->
        </div>

        <?php if (!empty($error)): ?>

            <div class="error-message">
                Please correct the highlighted fields.
            </div>

        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <div class="edit-content">
                <!-- Left Side -->
                <div class="edit-image">
                    <label for="imageInput" class="image-upload-label">
                        <img
                            id="imagePreview"
                            src="https://placehold.co/300x300?text=Choose+Image"
                            class="edit-product-image"
                            alt="Product Image"
                        >
                    </label>
                    <input
                        type="file"
                        id="imageInput"
                        name="image"
                        class="file-input"
                        accept="image/jpeg,image/png,image/webp"
                    >
                    <?php if(isset($error['image'])): ?>
                        <span class="error">
                            <?= $error['image'] ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Right Side -->
                <div class="edit-form">
                    <!-- Product Name -->
                    <div class="form-group">
                        <label>Product Name *</label>
                        <input
                            type="text"
                            name="name"
                            value="<?= htmlspecialchars($name) ?>"
                        >
                        <?php if(isset($error['name'])): ?>
                            <span class="error">
                                <?= $error['name'] ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Category -->
                    <div class="form-group">
                        <label>Category *</label>
                        <select
                            name="category"
                            id="category"
                        >
                            <option value="">Select Category</option>
                            <?php foreach($categories as $cat): ?>
                                <option
                                    value="<?= $cat['category_id'] ?>"
                                    <?= ($category == $cat['category_id']) ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                            <option
                                value="new"
                                <?= ($category == 'new') ? 'selected' : '' ?>
                            >
                                + New Category
                            </option>
                        </select>

                        <?php if(isset($error['category'])): ?>
                            <span class="error">
                                <?= $error['category'] ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- New Category -->
                    <div
                        class="form-group full-width"
                        id="newCategoryBox"
                        style="<?= ($category == 'new') ? '' : 'display:none;' ?>"
                    >
                        <label>New Category</label>
                        <input
                            type="text"
                            name="new_category"
                            value="<?= htmlspecialchars($new_category) ?>"
                        >
                        <?php if(isset($error['new_category'])): ?>
                            <span class="error">
                                <?= $error['new_category'] ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Price -->
                    <div class="form-group">
                        <label>Price (RM) *</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="price"
                            value="<?= htmlspecialchars($price) ?>"
                        >
                        <?php if(isset($error['price'])): ?>
                            <span class="error">
                                <?= $error['price'] ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Stock -->
                    <div class="form-group">
                        <label>Stock *</label>
                        <input
                            type="number"
                            min="0"
                            name="stock"
                            value="<?= htmlspecialchars($stock) ?>"
                        >
                        <?php if(isset($error['stock'])): ?>
                            <span class="error">
                                <?= $error['stock'] ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Size -->
                    <div class="form-group">
                        <label>Size *</label>
                        <select
                            name="size"
                            id="size"
                        >
                            <option value="">Select Size</option>
                            <option value="Micro (12oz / 350ml)"
                                <?= ($size == 'Micro (12oz / 350ml)') ? 'selected' : '' ?>>
                                Micro (12oz / 350ml)
                            </option>

                            <option value="Mini (15oz / 450ml)"
                                <?= ($size == 'Mini (15oz / 450ml)') ? 'selected' : '' ?>>
                                Mini (15oz / 450ml)
                            </option>

                            <option value="Medium (18oz / 530ml)"
                                <?= ($size == 'Medium (18oz / 530ml)') ? 'selected' : '' ?>>
                                Medium (18oz / 530ml)
                            </option>

                            <option value="Mega (32oz / 950ml)"
                                <?= ($size == 'Mega (32oz / 950ml)') ? 'selected' : '' ?>>
                                Mega (32oz / 950ml)
                            </option>
                            <option value="custom" <?= ($size=='custom')?'selected':'' ?>>Custom</option>
                        </select>

                        <?php if(isset($error['size'])): ?>
                            <span class="error">
                                <?= $error['size'] ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Colour -->
                    <div class="form-group">
                        <label>Colour *</label>
                        <input
                            type="text"
                            name="colour"
                            value="<?= htmlspecialchars($colour) ?>"
                        >
                        <?php if(isset($error['colour'])): ?>

                            <span class="error">
                                <?= $error['colour'] ?>
                            </span>

                        <?php endif; ?>
                    </div>

                    <!-- Custom Size -->
                    <div
                        class="form-group full-width"
                        id="customSizeBox"
                        style="<?= ($size == 'custom') ? '' : 'display:none;' ?>"
                    >
                        <label>Custom Size</label>
                        <input
                            type="text"
                            name="custom_size"
                            value="<?= htmlspecialchars($custom_size) ?>"
                        >
                        <?php if(isset($error['custom_size'])): ?>

                            <span class="error">
                                <?= $error['custom_size'] ?>
                            </span>

                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea
                            name="description"
                        ><?= htmlspecialchars($description) ?></textarea>
                    </div>
                </div>
            </div>
            <div class="edit-footer">
                <a
                    href="admin_products.php"
                    class="btn-view"
                >
                    Cancel
                </a>
                <button
                    type="submit"
                    class="btn-edit"
                >
                    Add Product
                </button>
            </div>
        </form>
    </div>
</div>
<script>

document.getElementById("category").addEventListener("change", function(){

    document.getElementById("newCategoryBox").style.display =
        this.value == "new"
        ? "block"
        : "none";

});

document.getElementById("size").addEventListener("change", function(){

    document.getElementById("customSizeBox").style.display =
        this.value == "custom"
        ? "block"
        : "none";

});
document.getElementById("imageInput").addEventListener("change", function(){

    const file = this.files[0];

    if (file) {

        document.getElementById("imagePreview").src =
            URL.createObjectURL(file);

    }

});
</script>
<?php include '../../_foot.php'; ?>
>>>>>>> 055c30c62a095c55984c52f24f16ca80e9aa6f8f
