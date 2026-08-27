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

$product_id = (int) $_GET['id'];

/*----------------------------------------------------------
    Get Categories
-----------------------------------------------------------*/
$categories = $_db->query("
    SELECT *
    FROM categories
    ORDER BY category_name
")->fetchAll(PDO::FETCH_ASSOC);

/*----------------------------------------------------------
    Load Product + Variant
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
    Load Existing Images
-----------------------------------------------------------*/
$image_stmt = $_db->prepare("
    SELECT
        image_id,
        image_url
    FROM product_images
    WHERE product_id = ?
    ORDER BY image_id ASC
");

$image_stmt->execute([$product_id]);

$additional_images = $image_stmt->fetchAll(PDO::FETCH_ASSOC);

/*----------------------------------------------------------
    Default Values
-----------------------------------------------------------*/
$name        = $product['name'];
$category_id = $product['category_id'];
$price       = $product['price'];
$size        = $product['size'];
$description = $product['description'];

$colour = $product['colour'];
$stock  = $product['stock'];

$new_category = '';

$error = [];

/*----------------------------------------------------------
    Standard Sizes
-----------------------------------------------------------*/
$standard_sizes = [
    'Micro (12oz / 350ml)',
    'Mini (15oz / 450ml)',
    'Medium (18oz / 530ml)',
    'Mega (32oz / 950ml)'
];

/*----------------------------------------------------------
    Save Changes
-----------------------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    /*------------------------------------------------------
                EXISTING PRODUCT EDIT CODE
    -------------------------------------------------------*/
    $name        = trim($_POST['name'] ?? '');
    $category_id = $_POST['category'] ?? '';
    $new_category = trim($_POST['new_category'] ?? '');
    $price       = $_POST['price'] ?? '';
    $size        = trim($_POST['size'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $colour = trim($_POST['colour'] ?? '');
    $stock  = $_POST['stock'] ?? '';
    /*------------------------------------------------------
        Images to Remove
    -------------------------------------------------------*/
    $remove_images = $_POST['remove_images'] ?? [];
    if (!is_array($remove_images)) {
        $remove_images = [];
    }
    $remove_images = array_map('intval', $remove_images);

    /*------------------------------------------------------
        Main Image Selection
        Format: "existing:<image_id>" or "new:<index>"
        (index refers to position within $_FILES['images'])
    -------------------------------------------------------*/
    $main_selection = trim($_POST['main_selection'] ?? '');

    /*------------------------------------------------------
        Handle Custom Size
    -------------------------------------------------------*/
    if ($size === 'custom') {

        $custom_size =
            trim($_POST['custom_size'] ?? '');

        if ($custom_size == '') {

            $error['size'] =
                "Please enter a custom size.";

        } else {

            foreach ($standard_sizes as $standard_size) {

                if (
                    strcasecmp(
                        $custom_size,
                        $standard_size
                    ) == 0
                ) {

                    $size = $standard_size;
                    break;
                }
            }

            if ($size === 'custom') {
                $size = $custom_size;
            }
        }
    }

    /*------------------------------------------------------
        Validation
    -------------------------------------------------------*/
    if ($name == '') {
        $error['name'] =
            "Product name is required.";
    }

    if ($price == '' || !is_numeric($price)) {
        $error['price'] =
            "Valid price is required.";
    }

    if ($stock == '' || !is_numeric($stock)) {
        $error['stock'] =
            "Valid stock is required.";
    }

    if ($colour == '') {
        $error['colour'] =
            "Colour is required.";
    }

    if ($category_id === '') {
        $error['category'] =
            "Please select a category.";
    }

    if ($category_id === 'new' && $new_category === '') {
        $error['new_category'] =
            "Please enter the new category.";
    }

    /*------------------------------------------------------
        Calculate Existing Images After Removal
    -------------------------------------------------------*/
    $remaining_additional_images = [];
    foreach ($additional_images as $image) {

        if (
            !in_array(
                (int)$image['image_id'],
                $remove_images
            )
        ) {

            $remaining_additional_images[] =
                $image;
        }
    }

    /*
    Main image is stored in products.image_url.
    If the main image is not removable, it remains.
    If all additional images are removed,
    we still have the main image.
    */
    /*------------------------------------------------------
        Handle New Images
    -------------------------------------------------------*/
    $new_files = [];

    if (
        isset($_FILES['images']) &&
        isset($_FILES['images']['name']) &&
        is_array($_FILES['images']['name'])
    ) {

        foreach (
            $_FILES['images']['name']
            as $index => $original_name
        ) {

            if (
                $_FILES['images']['error'][$index]
                === UPLOAD_ERR_OK
            ) {

                $new_files[] = [
                    'name' =>
                        $original_name,

                    'tmp_name' =>
                        $_FILES['images']['tmp_name'][$index],

                    'size' =>
                        $_FILES['images']['size'][$index]
                ];
            }
        }
    }

    /*------------------------------------------------------
        Count Total Images
    -------------------------------------------------------*/
    /*
    Existing main image
    +
    remaining additional images
    +
    new images
    */
    $total_images =
        1
        + count($remaining_additional_images)
        + count($new_files);


    if ($total_images > 5) {

        $error['image'] =
            "A product can have a maximum of 5 images.";
    }

    /*------------------------------------------------------
        Validate New Images
    -------------------------------------------------------*/
    $allowed_types = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    foreach ($new_files as $file) {
        //Maximum file size: 5 MB

        if ($file['size'] > 5 * 1024 * 1024) {

            $error['image'] =
                "Each image must be smaller than 5 MB.";

            break;
        }

        //Check actual image type
        $image_info =
            getimagesize($file['tmp_name']);

        if ($image_info === false) {

            $error['image'] =
                "One of the selected files is not a valid image.";

            break;
        }


        if (
            !in_array(
                $image_info['mime'],
                $allowed_types
            )
        ) {

            $error['image'] =
                "Only JPG, PNG and WebP images are allowed.";

            break;
        }
    }

    /*------------------------------------------------------
        Update Database
    -------------------------------------------------------*/
    if (empty($error)) {
        $uploaded_files = [];
        try {

            $_db->beginTransaction();

            /*----------------------------------------------
                Resolve "+ New Category" into a real
                category_id, reusing an existing category
                of the same name if one already exists.
            -----------------------------------------------*/
            if ($category_id === 'new') {

                $stmt = $_db->prepare("
                    SELECT category_id
                    FROM categories
                    WHERE category_name = ?
                ");

                $stmt->execute([$new_category]);

                $existing_category = $stmt->fetch(
                    PDO::FETCH_ASSOC
                );

                if ($existing_category) {

                    $category_id =
                        $existing_category['category_id'];

                } else {

                    $stmt = $_db->prepare("
                        INSERT INTO categories
                        (category_name)
                        VALUES (?)
                    ");

                    $stmt->execute([$new_category]);

                    $category_id = $_db->lastInsertId();
                }
            }

            /*----------------------------------------------
                Update Product
            -----------------------------------------------*/
            $stmt = $_db->prepare("
                UPDATE products
                SET
                    category_id = ?,
                    name = ?,
                    description = ?,
                    price = ?
                WHERE product_id = ?
            ");

            $stmt->execute([
                $category_id,
                $name,
                $description,
                $price,
                $product_id
            ]);

            /*----------------------------------------------
                Update Variant
            -----------------------------------------------*/
            $stmt = $_db->prepare("
                UPDATE product_variants
                SET
                    size = ?,
                    colour = ?,
                    stock = ?
                WHERE variant_id = ?
            ");

            $stmt->execute([
                $size,
                $colour,
                $stock,
                $product['variant_id']
            ]);

            /*----------------------------------------------
                Delete Selected Additional Images
            -----------------------------------------------*/
            if (!empty($remove_images)) {
                $delete_stmt = $_db->prepare("
                    SELECT image_url
                    FROM product_images
                    WHERE image_id = ?
                    AND product_id = ?
                ");

                $delete_image_stmt = $_db->prepare("
                    DELETE FROM product_images
                    WHERE image_id = ?
                    AND product_id = ?
                ");

                foreach ($remove_images as $image_id) {

                    $delete_stmt->execute([
                        $image_id,
                        $product_id
                    ]);

                    $image = $delete_stmt->fetch(
                        PDO::FETCH_ASSOC
                    );

                    if ($image) {

                        /*
                        Delete database record
                        */

                        $delete_image_stmt->execute([
                            $image_id,
                            $product_id
                        ]);


                        /*
                        Delete physical file
                        */

                        $image_path =
                            '../../' .
                            ltrim(
                                $image['image_url'],
                                '/'
                            );

                        if (
                            file_exists($image_path)
                        ) {

                            unlink($image_path);
                        }
                    }
                }
            }

            /*----------------------------------------------
                Upload New Images
            -----------------------------------------------*/
            $new_image_urls = [];
            if (!empty($new_files)) {
                $folder =
                    "../../img/products/";

                if (!is_dir($folder)) {

                    mkdir(
                        $folder,
                        0777,
                        true
                    );
                }

                $insert_image = $_db->prepare("
                    INSERT INTO product_images
                    (
                        product_id,
                        image_url
                    )
                    VALUES (?, ?)
                ");

                foreach ($new_files as $index => $file) {

                    /*
                    Generate unique filename
                    */

                    $extension =
                        strtolower(
                            pathinfo(
                                $file['name'],
                                PATHINFO_EXTENSION
                            )
                        );

                    $filename =
                        time()
                        . '_'
                        . bin2hex(
                            random_bytes(4)
                        )
                        . '.'
                        . $extension;


                    $target =
                        $folder . $filename;


                    if (
                        move_uploaded_file(
                            $file['tmp_name'],
                            $target
                        )
                    ) {

                        $image_url =
                            "img/products/"
                            . $filename;

                        /*
                        Remember this file's URL by its
                        original index so it can be
                        promoted to main image below if
                        the user picked it.
                        */

                        $new_image_urls[$index] =
                            $image_url;

                        /*
                        Only insert it as a regular
                        additional image if it is NOT
                        the one being promoted to main -
                        the main image lives in
                        products.image_url instead.
                        */

                        if (
                            $main_selection
                            !== 'new:' . $index
                        ) {

                            $insert_image->execute([
                                $product_id,
                                $image_url
                            ]);
                        }


                        $uploaded_files[] =
                            $target;

                    } else {

                        throw new Exception(
                            "Unable to upload image."
                        );
                    }
                }
            }

            /*----------------------------------------------
                Apply Main Image Change (if any)
            -----------------------------------------------*/
            if ($main_selection !== '') {
                $old_main_url = $product['image_url'];
                $new_main_url = null;
                
                if (
                    str_starts_with(
                        $main_selection,
                        'existing:'
                    )
                ) {

                    $picked_image_id =
                        (int) substr($main_selection, 9);

                    foreach (
                        $additional_images
                        as $img
                    ) {

                        if (
                            (int) $img['image_id']
                            === $picked_image_id
                        ) {

                            $new_main_url =
                                $img['image_url'];

                            /*
                            Remove it from product_images
                            since it's becoming the main
                            image.
                            */

                            $_db->prepare("
                                DELETE FROM product_images
                                WHERE image_id = ?
                                AND product_id = ?
                            ")->execute([
                                $picked_image_id,
                                $product_id
                            ]);

                            break;
                        }
                    }

                } elseif (
                    str_starts_with(
                        $main_selection,
                        'new:'
                    )
                ) {
                    $picked_index =
                        (int) substr($main_selection, 4);

                    $new_main_url =
                        $new_image_urls[$picked_index]
                        ?? null;
                }

                if ($new_main_url) {

                    $_db->prepare("
                        UPDATE products
                        SET image_url = ?
                        WHERE product_id = ?
                    ")->execute([
                        $new_main_url,
                        $product_id
                    ]);

                    /*
                    Keep the previous main image as a
                    regular additional image instead of
                    losing it.
                    */

                    if (
                        !empty($old_main_url)
                        && !isset($insert_image)
                    ) {

                        $insert_image = $_db->prepare("
                            INSERT INTO product_images
                            (
                                product_id,
                                image_url
                            )
                            VALUES (?, ?)
                        ");
                    }

                    if (!empty($old_main_url)) {

                        $insert_image->execute([
                            $product_id,
                            $old_main_url
                        ]);
                    }
                }
            }

            $_db->commit();
            header(
                "Location: product_view.php?id="
                . $product_id
            );
            exit;
        } catch (Exception $e) {

            if ($_db->inTransaction()) {
                $_db->rollBack();
            }

            /*
            Remove newly uploaded files
            if database update failed.
            */
            foreach ($uploaded_files as $file) {

                if (file_exists($file)) {
                    unlink($file);
                }
            }

            $error['database'] =
                "Unable to update the product. Please try again.";
        }
    }
}
/*----------------------------------------------------------
    Determine Custom Size
-----------------------------------------------------------*/
$is_custom_size =
    !in_array(
        $size,
        $standard_sizes
    );


/*----------------------------------------------------------
    Prepare Existing Images For JavaScript
-----------------------------------------------------------*/
$existing_images = [];


/*
Main image
*/

if (!empty($product['image_url'])) {

    $existing_images[] = [
        'id' => 'main',
        'url' => $product['image_url'],
        'main' => true
    ];
}

//Additional images
foreach ($additional_images as $image) {

    $existing_images[] = [
        'id' => $image['image_id'],
        'url' => $image['image_url'],
        'main' => false
    ];
}

include '../../_head.php';
?>

<link
    rel="stylesheet"
    href="../../css/main.css">

<link
    rel="stylesheet"
    href="../../css/admin.css">


<div class="admin-container">

    <form
        method="post"
        enctype="multipart/form-data"
        class="edit-card">

        <!-- ==========================================
             HEADER
        =========================================== -->
        <div class="edit-header">
            <h2>Edit Product</h2>
        </div>

        <?php if (!empty($error['database'])): ?>
            <div class="error-message">
                <?= htmlspecialchars(
                    $error['database']
                ) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error['image'])): ?>
            <div class="error-message">
                <?= htmlspecialchars(
                    $error['image']
                ) ?>
            </div>
        <?php endif; ?>

        <div class="edit-content">


            <!-- ======================================
                 LEFT SIDE - PHOTO MANAGER
            ======================================= -->
            <div class="edit-image">
                <div class="photo-manager">
                    <!-- Header -->
                    <div class="photo-manager-header">
                        <div>
                            <h3>Product Photos</h3>
                            <p>Manage your product's images</p>
                        </div>
                        <span
                            class="photo-counter"
                            id="photoCounter">
                            <?= count($existing_images) ?>/5
                        </span>
                    </div>

                    <!-- Main preview -->
                    <?php
                    $main_image =
                        $product['image_url'];

                    if (
                        str_starts_with(
                            $main_image,
                            'http://'
                        ) ||
                        str_starts_with(
                            $main_image,
                            'https://'
                        )
                    ) {

                        $main_image_src =
                            $main_image;

                    } else {

                        $main_image_src =
                            '../../'
                            . ltrim(
                                $main_image,
                                '/'
                            );
                    }
                    ?>

                    <div class="photo-main-frame">
                        <span
                            class="photo-main-badge"
                            id="mainFrameBadge">
                            &#10003; Main Image
                        </span>

                        <img
                            id="editMainPreview"
                            src="<?= htmlspecialchars(
                                $main_image_src
                            ) ?>"
                            alt="Product Image"
                        >

                    </div>

                    <!-- Thumbnail grid (existing + new images) -->
                    <div
                        class="photo-grid"
                        id="photoGrid">

                        <?php foreach (
                            $existing_images
                            as $image
                        ): ?>

                            <?php

                            $imagePath =
                                $image['url'];

                            if (
                                str_starts_with(
                                    $imagePath,
                                    'http://'
                                ) ||
                                str_starts_with(
                                    $imagePath,
                                    'https://'
                                )
                            ) {

                                $imageSrc =
                                    $imagePath;

                            } else {

                                $imageSrc =
                                    '../../'
                                    . ltrim(
                                        $imagePath,
                                        '/'
                                    );
                            }

                            ?>

                            <div
                                class="photo-tile <?= $image['main'] ? 'is-main' : '' ?>"
                                data-image-id="<?= htmlspecialchars(
                                    $image['id']
                                ) ?>"
                                data-type="existing"
                            >

                                <img
                                    src="<?= htmlspecialchars(
                                        $imageSrc
                                    ) ?>"
                                    alt="Product Image"
                                >

                                <?php if ($image['main']): ?>

                                    <span class="photo-tile-label">
                                        Main
                                    </span>

                                <?php endif; ?>

                            </div>

                        <?php endforeach; ?>


                        <!-- "+" tile to add more images -->
                        <div
                            class="photo-tile photo-tile-add"
                            id="addPhotoTile">
                            <span>+</span>
                        </div>
                    </div>


                    <!-- Actions for the selected tile -->
                    <div
                        class="photo-actions"
                        id="photoActions"
                        style="display:none;">

                        <button
                            type="button"
                            class="btn-photo-main"
                            id="setMainBtn">
                            Set as Main
                        </button>

                        <button
                            type="button"
                            class="btn-photo-delete"
                            id="deletePhotoBtn">
                            Delete
                        </button>

                    </div>

                    <!-- Drag & drop / click to upload -->
                    <label
                        for="imageInput"
                        class="photo-dropzone"
                        id="photoDropzone">

                        <div class="photo-dropzone-icon">
                            &#8593;
                        </div>

                        <div class="photo-dropzone-text">
                            Drag &amp; drop or click to upload
                        </div>

                        <div class="photo-dropzone-sub">
                            JPG, PNG, WebP up to 5MB &middot; Max 5 images
                        </div>

                    </label>

                    <input
                        type="file"
                        id="imageInput"
                        name="images[]"
                        class="file-input"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                    >

                </div>

            </div>

            <!-- ======================================
                 RIGHT SIDE - FORM
            ======================================= -->
            <div class="edit-form">
                <!-- Product Name -->
                <div class="form-group">
                    <label>
                        Product Name
                    </label>

                    <input
                        type="text"
                        name="name"
                        value="<?= htmlspecialchars(
                            $name
                        ) ?>">

                    <small class="error">

                        <?= htmlspecialchars(
                            $error['name'] ?? ''
                        ) ?>

                    </small>

                </div>
                <!-- Category -->
                <div class="form-group">

                    <label>
                        Category
                    </label>

                    <select name="category" id="category">

                        <?php foreach (
                            $categories
                            as $cat
                        ): ?>

                            <option
                                value="<?= $cat['category_id'] ?>"
                                <?= (
                                    $category_id
                                    ==
                                    $cat['category_id']
                                )
                                ? 'selected'
                                : ''
                                ?>>

                                <?= htmlspecialchars(
                                    $cat['category_name']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                        <option
                            value="new"
                            <?= ($category_id === 'new')
                                ? 'selected'
                                : ''
                            ?>>

                            + New Category
                        </option>
                    </select>

                    <small class="error">
                        <?= htmlspecialchars(
                            $error['category'] ?? ''
                        ) ?>
                    </small>
                </div>

                <!-- New Category -->
                <div
                    class="form-group full-width"
                    id="newCategoryBox"
                    style="<?= ($category_id === 'new')
                        ? ''
                        : 'display:none;'
                    ?>">

                    <label>
                        New Category
                    </label>

                    <input
                        type="text"
                        name="new_category"
                        value="<?= htmlspecialchars(
                            $new_category
                        ) ?>">

                    <small class="error">

                        <?= htmlspecialchars(
                            $error['new_category'] ?? ''
                        ) ?>

                    </small>
                </div>

                <!-- Size -->
                <div class="form-group">
                    <label>Size</label>
                    <select
                        name="size"
                        id="size">

                        <option value="">
                            Select Size
                        </option>


                        <?php foreach (
                            $standard_sizes
                            as $standard_size
                        ): ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $standard_size
                                ) ?>"
                                <?= (
                                    $size
                                    ==
                                    $standard_size
                                )
                                ? 'selected'
                                : ''
                                ?>>

                                <?= htmlspecialchars(
                                    $standard_size
                                ) ?>

                            </option>

                        <?php endforeach; ?>


                        <option
                            value="custom"
                            <?= $is_custom_size
                                ? 'selected'
                                : ''
                            ?>>

                            Custom
                        </option>
                    </select>


                    <input
                        type="text"
                        name="custom_size"
                        id="custom_size"
                        placeholder="Enter custom size..."
                        value="<?= $is_custom_size
                            ? htmlspecialchars($size)
                            : ''
                        ?>"
                        style="
                            display:
                            <?= $is_custom_size
                                ? 'block'
                                : 'none'
                            ?>;
                            margin-top:10px;
                        "
                    >


                    <small class="error">
                        <?= htmlspecialchars(
                            $error['size'] ?? ''
                        ) ?>
                    </small>
                </div>


                <!-- Price -->
                <div class="form-group">
                    <label>Price (RM)</label>

                    <input
                        type="number"
                        step="0.01"
                        name="price"
                        value="<?= htmlspecialchars(
                            $price
                        ) ?>">

                    <small class="error">

                        <?= htmlspecialchars(
                            $error['price'] ?? ''
                        ) ?>

                    </small>
                </div>

                <!-- Colour -->
                <div class="form-group">
                    <label>Colour</label>  
                    <input
                        type="text"
                        name="colour"
                        value="<?= htmlspecialchars(
                            $colour
                        ) ?>">

                    <small class="error">

                        <?= htmlspecialchars(
                            $error['colour'] ?? ''
                        ) ?>

                    </small>
                </div>


                <!-- Stock -->

                <div class="form-group">
                    <label>Stock</label>
                    <input
                        type="number"
                        name="stock"
                        min="0"
                        value="<?= htmlspecialchars(
                            $stock
                        ) ?>">

                    <small class="error">

                        <?= htmlspecialchars(
                            $error['stock'] ?? ''
                        ) ?>

                    </small>
                </div>

                <!-- Description -->
                <div class="form-group full-width">
                    <label>
                        Description
                    </label>

                    <textarea
                        name="description"
                        rows="6"><?= htmlspecialchars(
                            $description
                        ) ?></textarea>

                </div>
            </div>
        </div>


        <!-- ==========================================
             FOOTER
        =========================================== -->
        <div class="edit-footer">
            <a
                href="admin_products.php"
                class="btn-view">
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

<script>
/* ==================================================
   SIZE
================================================== */
const sizeSelect =
    document.getElementById('size');

const customSize =
    document.getElementById('custom_size');


function checkCustomSize() {

    if (
        sizeSelect.value === 'custom'
    ) {

        customSize.style.display =
            'block';

        customSize.required =
            true;

    } else {

        customSize.style.display =
            'none';

        customSize.required =
            false;
    }
}

sizeSelect.addEventListener(
    'change',
    checkCustomSize
);

checkCustomSize();


/* ==================================================
   CATEGORY
================================================== */
document.getElementById('category').addEventListener(
    'change',
    function () {

        document.getElementById('newCategoryBox').style.display =
            (this.value === 'new') ? 'block' : 'none';
    }
);

/* ==================================================
   PHOTO MANAGER
   (main preview, thumbnail grid, select / set main /
   delete, drag & drop upload)

   Removing an image now only happens through the
   "Delete" button in the action bar - select a
   thumbnail first, then click Delete.

   "Set as Main" no longer navigates away immediately.
   It just records the choice in a hidden field
   (main_selection) which is applied on the server
   when the form is submitted via "Save Changes".
   This lets a newly-added (not yet uploaded) photo
   be chosen as the main image too.
================================================== */

const imageInput      = document.getElementById('imageInput');
const photoGrid        = document.getElementById('photoGrid');
const addPhotoTile     = document.getElementById('addPhotoTile');
const editMainPreview  = document.getElementById('editMainPreview');
const mainFrameBadge   = document.getElementById('mainFrameBadge');
const photoActions     = document.getElementById('photoActions');
const setMainBtn       = document.getElementById('setMainBtn');
const deletePhotoBtn   = document.getElementById('deletePhotoBtn');
const photoCounter     = document.getElementById('photoCounter');
const photoDropzone    = document.getElementById('photoDropzone');
const editCard         = document.querySelector('.edit-card');

const trueMainSrc = editMainPreview.src;

/*
Hidden field that carries the pending main-image
choice ("existing:<id>" or "new:<index>") through to
the server on submit.
*/

const mainSelectionInput = document.createElement('input');
mainSelectionInput.type  = 'hidden';
mainSelectionInput.name  = 'main_selection';
editCard.appendChild(mainSelectionInput);

let selectedFiles = [];
let selectedTile  = null;

/*
Tracks whether the user has made any change that
hasn't been saved yet, so we can warn before an
accidental refresh / navigation.
*/

let formDirty = false;


/*--------------------------------------------------
    Counter
--------------------------------------------------*/
function getExistingCount() {

    return photoGrid.querySelectorAll(
        '.photo-tile[data-type="existing"]'
    ).length;
}


function updateCounter() {

    const total =
        getExistingCount()
        + selectedFiles.length;

    photoCounter.textContent =
        total + '/5';


    /*
    Hide the "+" tile once the limit is reached
    */

    addPhotoTile.style.display =
        (total >= 5) ? 'none' : 'flex';
}

/*--------------------------------------------------
    Selection (click a tile to preview it)
--------------------------------------------------*/
function clearSelection() {

    if (selectedTile) {
        selectedTile.classList.remove('is-selected');
    }

    selectedTile = null;

    photoActions.style.display = 'none';

    editMainPreview.src = trueMainSrc;
    mainFrameBadge.textContent = '\u2713 Main Image';
}

function selectTile(tile) {

    if (tile === selectedTile) {
        clearSelection();
        return;
    }

    if (selectedTile) {
        selectedTile.classList.remove('is-selected');
    }

    selectedTile = tile;
    tile.classList.add('is-selected');

    const previewImg = tile.querySelector('img');
    editMainPreview.src = previewImg.src;

    const isMain = tile.classList.contains('is-main');

    if (isMain) {

        mainFrameBadge.textContent = '\u2713 Main Image';
        photoActions.style.display = 'none';

    } else {

        mainFrameBadge.textContent = 'Previewing';
        photoActions.style.display = 'flex';

        /*
        Both existing and newly-chosen images can now
        be picked as main - the swap is only applied
        once the form is actually saved.
        */

        setMainBtn.style.display = 'inline-flex';
    }
}

/*--------------------------------------------------
    Click handling inside the grid
    (selection only - no per-tile remove button)
--------------------------------------------------*/

photoGrid.addEventListener('click', function (e) {

    const tile = e.target.closest('.photo-tile');

    if (!tile || tile === addPhotoTile) {
        return;
    }

    selectTile(tile);
});

addPhotoTile.addEventListener(
    'click',
    () => imageInput.click()
);


/*--------------------------------------------------
    Remove an existing (already saved) image
    -> marks it for deletion on submit
--------------------------------------------------*/

function removeExistingTile(tile) {

    formDirty = true;

    const imageId = tile.dataset.imageId;

    const input = document.createElement('input');
    input.type  = 'hidden';
    input.name  = 'remove_images[]';
    input.value = imageId;

    editCard.appendChild(input);

    if (tile === selectedTile) {
        clearSelection();
    }

    tile.remove();

    updateCounter();
}

/*--------------------------------------------------
    Remove a newly chosen (not yet uploaded) image
--------------------------------------------------*/
function removeNewTile(tile) {

    formDirty = true;

    const index = parseInt(tile.dataset.index, 10);

    if (tile === selectedTile) {
        clearSelection();
    }

    selectedFiles.splice(index, 1);

    updateFileInput();
    renderNewTiles();
}


/*--------------------------------------------------
    New file selection (input + drag & drop)
--------------------------------------------------*/
imageInput.addEventListener('change', function () {

    addFiles(Array.from(this.files));
});


function addFiles(files) {

    formDirty = true;

    files.forEach(function (file) {

        const duplicate = selectedFiles.some(
            function (existingFile) {

                return (
                    existingFile.name === file.name
                    && existingFile.size === file.size
                );
            }
        );

        if (!duplicate) {
            selectedFiles.push(file);
        }
    });

    /*
    Maximum 5 total images

    Existing images + new images
    */
    const existingCount = getExistingCount();

    if (existingCount + selectedFiles.length > 5) {

        const availableSlots = 5 - existingCount;

        alert(
            "A product can have a maximum of 5 images."
        );

        selectedFiles = selectedFiles.slice(
            0,
            Math.max(0, availableSlots)
        );
    }

    updateFileInput();
    renderNewTiles();
}

/*--------------------------------------------------
    Keep Files In Input
--------------------------------------------------*/
function updateFileInput() {

    const dataTransfer = new DataTransfer();

    selectedFiles.forEach(function (file) {
        dataTransfer.items.add(file);
    });

    imageInput.files = dataTransfer.files;
}


/*--------------------------------------------------
    Render newly chosen images into the same grid
    (no remove button here - select the tile, then
    use the "Delete" button in the action bar)
--------------------------------------------------*/
function renderNewTiles() {

    photoGrid
        .querySelectorAll('.photo-tile[data-type="new"]')
        .forEach(function (t) {
            t.remove();
        });

    selectedFiles.forEach(function (file, index) {

        const tile = document.createElement('div');
        tile.className = 'photo-tile';
        tile.dataset.type = 'new';
        tile.dataset.index = index;

        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.alt = 'New Product Image';
        tile.appendChild(img);

        photoGrid.insertBefore(tile, addPhotoTile);
    });

    updateCounter();
}

/*--------------------------------------------------
    Set as Main
    Records the pending choice instead of navigating
    away immediately. Works for existing images
    (data-type="existing", identified by image_id)
    and newly-chosen files (data-type="new",
    identified by their index in selectedFiles).
    The actual database swap happens server-side when
    the form is submitted.
--------------------------------------------------*/
setMainBtn.addEventListener('click', function () {

    if (!selectedTile) {
        return;
    }

    if (selectedTile.dataset.type === 'existing') {

        mainSelectionInput.value =
            'existing:' + selectedTile.dataset.imageId;

    } else {

        mainSelectionInput.value =
            'new:' + selectedTile.dataset.index;
    }

    formDirty = true;

    alert(
        'This image will become the main image once '
        + 'you click "Save Changes".'
    );
});

/*--------------------------------------------------
    Delete the currently selected tile
--------------------------------------------------*/

deletePhotoBtn.addEventListener('click', function () {

    if (!selectedTile) {
        return;
    }

    if (selectedTile.dataset.type === 'existing') {
        removeExistingTile(selectedTile);
    } else {
        removeNewTile(selectedTile);
    }
});

/*--------------------------------------------------
    Drag & Drop onto the dropzone
--------------------------------------------------*/

['dragover', 'dragenter'].forEach(function (evt) {

    photoDropzone.addEventListener(evt, function (e) {
        e.preventDefault();
        photoDropzone.classList.add('is-dragover');
    });
});

['dragleave', 'dragend'].forEach(function (evt) {

    photoDropzone.addEventListener(evt, function () {
        photoDropzone.classList.remove('is-dragover');
    });
});

photoDropzone.addEventListener('drop', function (e) {

    e.preventDefault();
    photoDropzone.classList.remove('is-dragover');

    if (e.dataTransfer && e.dataTransfer.files) {
        addFiles(Array.from(e.dataTransfer.files));
    }
});


/*
Initial counter state
*/

updateCounter();

/*--------------------------------------------------
    Warn on refresh / navigation away if there are
    unsaved changes (new photos, removed photos, or
    a pending main-image change). This does not
    persist any data - it only prompts the browser's
    native "leave site?" confirmation.
--------------------------------------------------*/
window.addEventListener('beforeunload', function (e) {

    if (formDirty) {
        e.preventDefault();
        e.returnValue = '';
    }
});

/*
Also mark the form dirty if any of the standard text
fields change, so refreshing after editing the name/
price/etc. (with no photo changes) still warns.
*/

document
    .querySelectorAll(
        '.edit-form input, .edit-form select, .edit-form textarea'
    )
    .forEach(function (el) {

        el.addEventListener('input', function () {
            formDirty = true;
        });
    });

/*
Don't warn when the form is actually being submitted.
*/

document
    .querySelector('.edit-card')
    .addEventListener('submit', function () {
        formDirty = false;
    });

</script>

<?php include '../../_foot.php'; ?>