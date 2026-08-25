<?php
require_once '../../_base.php';

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

$error = [];


/* ==========================================
   Standard Sizes
========================================== */

$standard_sizes = [
    'Micro (12oz / 350ml)',
    'Mini (15oz / 450ml)',
    'Medium (18oz / 530ml)',
    'Mega (32oz / 950ml)'
];


/* ==========================================
   Form Submitted
========================================== */

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $name =
        trim($_POST['name'] ?? '');

    $category =
        $_POST['category'] ?? '';

    $new_category =
        trim($_POST['new_category'] ?? '');

    $price =
        trim($_POST['price'] ?? '');

    $size =
        $_POST['size'] ?? '';

    $custom_size =
        trim($_POST['custom_size'] ?? '');

    $colour =
        trim($_POST['colour'] ?? '');

    $stock =
        trim($_POST['stock'] ?? '');

    $description =
        trim($_POST['description'] ?? '');


    /* ==========================================
       Validation
    ========================================== */

    if ($name == '') {

        $error['name'] =
            'Product name is required.';
    }


    /* ==========================================
       Multiple Image Validation
    ========================================== */

    if (
        !isset($_FILES['images']) ||
        empty($_FILES['images']['name'][0])
    ) {

        $error['image'] =
            'At least one product image is required.';

    } else {

        $allowed_types = [
            'image/jpeg',
            'image/png',
            'image/webp'
        ];

        $image_count =
            count($_FILES['images']['name']);


        /* Maximum 5 images */

        if ($image_count > 5) {

            $error['image'] =
                'You can upload a maximum of 5 images.';

        } else {

            for (
                $i = 0;
                $i < $image_count;
                $i++
            ) {

                if (
                    $_FILES['images']['error'][$i]
                    !== UPLOAD_ERR_OK
                ) {

                    $error['image'] =
                        'One or more images failed to upload.';

                    break;
                }


                if (
                    !in_array(
                        $_FILES['images']['type'][$i],
                        $allowed_types
                    )
                ) {

                    $error['image'] =
                        'Only JPG, PNG and WEBP images are allowed.';

                    break;
                }
            }
        }
    }


    /* ==========================================
       Category Validation
    ========================================== */

    if ($category == '') {

        $error['category'] =
            'Please select a category.';
    }


    if (
        $category == 'new' &&
        $new_category == ''
    ) {

        $error['new_category'] =
            'Please enter the new category.';
    }


    /* ==========================================
       Price Validation
    ========================================== */

    if ($price == '') {

        $error['price'] =
            'Price is required.';

    } elseif (
        !is_numeric($price) ||
        $price < 0
    ) {

        $error['price'] =
            'Invalid price.';
    }


    /* ==========================================
       Stock Validation
    ========================================== */

    if ($stock == '') {

        $error['stock'] =
            'Stock is required.';

    } elseif (
        !is_numeric($stock) ||
        $stock < 0
    ) {

        $error['stock'] =
            'Invalid stock.';
    }


    /* ==========================================
       Size Validation
    ========================================== */

    if ($size == '') {

        $error['size'] =
            'Please select a size.';
    }


    if (
        $size == 'custom' &&
        $custom_size == ''
    ) {

        $error['custom_size'] =
            'Please enter custom size.';
    }


    /* ==========================================
       Colour Validation
    ========================================== */

    if ($colour == '') {

        $error['colour'] =
            'Colour is required.';
    }


    /* ==========================================
       Save Product
    ========================================== */

    if (empty($error)) {

        $uploaded_files = [];


        try {

            $_db->beginTransaction();


            /* ------------------------------------------
               New Category
            ------------------------------------------ */

            if ($category == 'new') {

                $stmt = $_db->prepare("
                    SELECT category_id
                    FROM categories
                    WHERE category_name = ?
                ");

                $stmt->execute([
                    $new_category
                ]);

                $existing_category =
                    $stmt->fetch(PDO::FETCH_ASSOC);


                if ($existing_category) {

                    $category =
                        $existing_category['category_id'];

                } else {

                    $stmt = $_db->prepare("
                        INSERT INTO categories
                        (category_name)
                        VALUES (?)
                    ");

                    $stmt->execute([
                        $new_category
                    ]);

                    $category =
                        $_db->lastInsertId();
                }
            }


            /* ------------------------------------------
               Custom Size
            ------------------------------------------ */

            if ($size == 'custom') {

                $size = $custom_size;
            }


            /* ------------------------------------------
               Upload Images
            ------------------------------------------ */

            $folder =
                '../../img/products/';


            if (!is_dir($folder)) {

                mkdir(
                    $folder,
                    0777,
                    true
                );
            }


            $uploaded_images = [];


            foreach (
                $_FILES['images']['name']
                as $i => $original_name
            ) {

                $filename =
                    time()
                    . '_'
                    . $i
                    . '_'
                    . basename($original_name);


                $target =
                    $folder . $filename;


                if (
                    move_uploaded_file(
                        $_FILES['images']['tmp_name'][$i],
                        $target
                    )
                ) {

                    $image_url =
                        'img/products/'
                        . $filename;


                    $uploaded_images[] =
                        $image_url;


                    $uploaded_files[] =
                        $target;

                } else {

                    throw new Exception(
                        'Failed to upload one or more images.'
                    );
                }
            }


            /* ------------------------------------------
               First Image = Main Product Image
            ------------------------------------------ */

            $main_image =
                $uploaded_images[0];


            /* ------------------------------------------
               Insert Product
            ------------------------------------------ */

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
                $main_image
            ]);


            $product_id =
                $_db->lastInsertId();


            /* ------------------------------------------
               Insert Additional Product Images
            ------------------------------------------ */

            if (
                count($uploaded_images) > 1
            ) {

                $insert_image =
                    $_db->prepare("
                        INSERT INTO product_images
                        (
                            product_id,
                            image_url
                        )
                        VALUES
                        (
                            ?, ?
                        )
                    ");


                /*
                Skip image 0 because it is
                the main product image.
                */

                for (
                    $i = 1;
                    $i < count($uploaded_images);
                    $i++
                ) {

                    $insert_image->execute([
                        $product_id,
                        $uploaded_images[$i]
                    ]);
                }
            }


            /* ------------------------------------------
               Insert Variant
            ------------------------------------------ */

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


            /* ------------------------------------------
               Commit
            ------------------------------------------ */

            $_db->commit();


            header(
                "Location: admin_products.php"
            );

            exit;


        } catch (Exception $e) {

            if (
                $_db->inTransaction()
            ) {

                $_db->rollBack();
            }


            /*
            Delete uploaded files if
            database operation failed.
            */

            foreach (
                $uploaded_files
                as $file
            ) {

                if (
                    file_exists($file)
                ) {

                    unlink($file);
                }
            }


            $error['database'] =
                'Unable to add the product. Please try again.';
        }
    }
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

    <div class="edit-card">

        <div class="edit-header">

            <h2>Add Product</h2>

        </div>


        <?php if (!empty($error['database'])): ?>

            <div class="error-message">

                <?= htmlspecialchars(
                    $error['database']
                ) ?>

            </div>

        <?php endif; ?>


        <?php if (!empty($error)): ?>

            <div class="error-message">

                Please correct the highlighted fields.

            </div>

        <?php endif; ?>


        <form
            method="post"
            enctype="multipart/form-data"
            class="add-product-form"
        >

            <div class="edit-content">


                <!-- ======================================
                     LEFT SIDE - PHOTO MANAGER
                ======================================= -->

                <div class="edit-image">

                    <div class="photo-manager">


                        <!-- Header -->

                        <div class="photo-manager-header">

                            <div>

                                <h3>
                                    Product Photos
                                </h3>

                                <p>
                                    Add product images
                                </p>

                            </div>

                            <span
                                class="photo-counter"
                                id="photoCounter"
                            >
                                0/5
                            </span>

                        </div>


                        <!-- Main Preview -->

                        <div class="photo-main-frame">

                            <span
                                class="photo-main-badge"
                                id="mainFrameBadge"
                            >
                                Choose Image
                            </span>

                            <img
                                id="editMainPreview"
                                src="https://placehold.co/400x400?text=Choose+Image"
                                alt="Product Image Preview"
                            >

                        </div>


                        <!-- Thumbnail Grid -->

                        <div
                            class="photo-grid"
                            id="photoGrid"
                        >

                            <!-- New images are inserted here -->

                            <div
                                class="photo-tile photo-tile-add"
                                id="addPhotoTile"
                            >

                                <span>+</span>

                            </div>

                        </div>


                        <!-- Selected Image Actions -->

                        <div
                            class="photo-actions"
                            id="photoActions"
                            style="display:none;"
                        >

                            <button
                                type="button"
                                class="btn-photo-main"
                                id="setMainBtn"
                            >
                                Set as Main
                            </button>

                            <button
                                type="button"
                                class="btn-photo-delete"
                                id="deletePhotoBtn"
                            >
                                Delete
                            </button>

                        </div>


                        <!-- Drag & Drop -->

                        <label
                            for="imageInput"
                            class="photo-dropzone"
                            id="photoDropzone"
                        >

                            <div class="photo-dropzone-icon">
                                &#8593;
                            </div>

                            <div class="photo-dropzone-text">
                                Drag &amp; drop or click to upload
                            </div>

                            <div class="photo-dropzone-sub">
                                JPG, PNG, WebP up to 5MB &middot;
                                Max 5 images
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


                        <?php if (isset($error['image'])): ?>

                            <span class="error">

                                <?= htmlspecialchars(
                                    $error['image']
                                ) ?>

                            </span>

                        <?php endif; ?>


                    </div>

                </div>


                <!-- ======================================
                     RIGHT SIDE - FORM
                ======================================= -->

                <div class="edit-form">


                    <!-- Product Name -->

                    <div class="form-group">

                        <label>
                            Product Name *
                        </label>

                        <input
                            type="text"
                            name="name"
                            value="<?= htmlspecialchars(
                                $name
                            ) ?>"
                        >

                        <small class="error">

                            <?= htmlspecialchars(
                                $error['name'] ?? ''
                            ) ?>

                        </small>

                    </div>


                    <!-- Category -->

                    <div class="form-group">

                        <label>
                            Category *
                        </label>

                        <select
                            name="category"
                            id="category"
                        >

                            <option value="">
                                Select Category
                            </option>

                            <?php foreach (
                                $categories
                                as $cat
                            ): ?>

                                <option
                                    value="<?= $cat['category_id'] ?>"
                                    <?= (
                                        $category
                                        ==
                                        $cat['category_id']
                                    )
                                    ? 'selected'
                                    : ''
                                    ?>
                                >

                                    <?= htmlspecialchars(
                                        $cat['category_name']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>


                            <option
                                value="new"
                                <?= (
                                    $category == 'new'
                                )
                                ? 'selected'
                                : ''
                                ?>
                            >

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
                        style="<?= (
                            $category == 'new'
                        )
                        ? ''
                        : 'display:none;'
                        ?>"
                    >

                        <label>
                            New Category
                        </label>

                        <input
                            type="text"
                            name="new_category"
                            value="<?= htmlspecialchars(
                                $new_category
                            ) ?>"
                        >

                        <small class="error">

                            <?= htmlspecialchars(
                                $error['new_category'] ?? ''
                            ) ?>

                        </small>

                    </div>


                    <!-- Price -->

                    <div class="form-group">

                        <label>
                            Price (RM) *
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="price"
                            value="<?= htmlspecialchars(
                                $price
                            ) ?>"
                        >

                        <small class="error">

                            <?= htmlspecialchars(
                                $error['price'] ?? ''
                            ) ?>

                        </small>

                    </div>


                    <!-- Stock -->

                    <div class="form-group">

                        <label>
                            Stock *
                        </label>

                        <input
                            type="number"
                            min="0"
                            name="stock"
                            value="<?= htmlspecialchars(
                                $stock
                            ) ?>"
                        >

                        <small class="error">

                            <?= htmlspecialchars(
                                $error['stock'] ?? ''
                            ) ?>

                        </small>

                    </div>


                    <!-- Size -->

                    <div class="form-group">

                        <label>
                            Size *
                        </label>

                        <select
                            name="size"
                            id="size"
                        >

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
                                    ?>
                                >

                                    <?= htmlspecialchars(
                                        $standard_size
                                    ) ?>

                                </option>

                            <?php endforeach; ?>


                            <option
                                value="custom"
                                <?= (
                                    $size == 'custom'
                                )
                                ? 'selected'
                                : ''
                                ?>
                            >

                                Custom

                            </option>

                        </select>


                        <small class="error">

                            <?= htmlspecialchars(
                                $error['size'] ?? ''
                            ) ?>

                        </small>

                    </div>


                    <!-- Custom Size -->

                    <div
                        class="form-group full-width"
                        id="customSizeBox"
                        style="<?= (
                            $size == 'custom'
                        )
                        ? ''
                        : 'display:none;'
                        ?>"
                    >

                        <label>
                            Custom Size
                        </label>

                        <input
                            type="text"
                            name="custom_size"
                            value="<?= htmlspecialchars(
                                $custom_size
                            ) ?>"
                            placeholder="Enter custom size..."
                        >

                        <small class="error">

                            <?= htmlspecialchars(
                                $error['custom_size'] ?? ''
                            ) ?>

                        </small>

                    </div>


                    <!-- Colour -->

                    <div class="form-group">

                        <label>
                            Colour *
                        </label>

                        <input
                            type="text"
                            name="colour"
                            value="<?= htmlspecialchars(
                                $colour
                            ) ?>"
                        >

                        <small class="error">

                            <?= htmlspecialchars(
                                $error['colour'] ?? ''
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
                            rows="6"
                        ><?= htmlspecialchars(
                            $description
                        ) ?></textarea>

                    </div>


                </div>

            </div>


            <!-- ======================================
                 FOOTER
            ======================================= -->

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

/* ==================================================
   CATEGORY
================================================== */

const category =
    document.getElementById(
        'category'
    );

const newCategoryBox =
    document.getElementById(
        'newCategoryBox'
    );


category.addEventListener(
    'change',
    function () {

        newCategoryBox.style.display =
            this.value === 'new'
                ? 'block'
                : 'none';

    }
);


/* ==================================================
   SIZE
================================================== */

const size =
    document.getElementById(
        'size'
    );

const customSizeBox =
    document.getElementById(
        'customSizeBox'
    );


size.addEventListener(
    'change',
    function () {

        customSizeBox.style.display =
            this.value === 'custom'
                ? 'block'
                : 'none';

    }
);


/* ==================================================
   PHOTO MANAGER
================================================== */

const imageInput =
    document.getElementById(
        'imageInput'
    );

const photoGrid =
    document.getElementById(
        'photoGrid'
    );

const addPhotoTile =
    document.getElementById(
        'addPhotoTile'
    );

const editMainPreview =
    document.getElementById(
        'editMainPreview'
    );

const mainFrameBadge =
    document.getElementById(
        'mainFrameBadge'
    );

const photoActions =
    document.getElementById(
        'photoActions'
    );

const setMainBtn =
    document.getElementById(
        'setMainBtn'
    );

const deletePhotoBtn =
    document.getElementById(
        'deletePhotoBtn'
    );

const photoCounter =
    document.getElementById(
        'photoCounter'
    );

const photoDropzone =
    document.getElementById(
        'photoDropzone'
    );


let selectedFiles = [];

let selectedTile = null;


/* ==================================================
   Counter
================================================== */

function updateCounter() {

    const total =
        selectedFiles.length;


    photoCounter.textContent =
        total + '/5';


    if (total >= 5) {

        addPhotoTile.style.display =
            'none';

    } else {

        addPhotoTile.style.display =
            'flex';
    }
}


/* ==================================================
   Update File Input
================================================== */

function updateFileInput() {

    const dataTransfer =
        new DataTransfer();


    selectedFiles.forEach(
        function (file) {

            dataTransfer.items.add(
                file
            );

        }
    );


    imageInput.files =
        dataTransfer.files;
}


/* ==================================================
   Clear Selection
================================================== */

function clearSelection() {

    if (selectedTile) {

        selectedTile.classList.remove(
            'is-selected'
        );
    }


    selectedTile =
        null;


    photoActions.style.display =
        'none';


    if (
        selectedFiles.length > 0
    ) {

        editMainPreview.src =
            URL.createObjectURL(
                selectedFiles[0]
            );

        mainFrameBadge.textContent =
            '✓ Main Image';

    } else {

        editMainPreview.src =
            'https://placehold.co/400x400?text=Choose+Image';

        mainFrameBadge.textContent =
            'Choose Image';
    }
}


/* ==================================================
   Select Tile
================================================== */

function selectTile(tile) {

    if (
        tile === selectedTile
    ) {

        clearSelection();

        return;
    }


    if (selectedTile) {

        selectedTile.classList.remove(
            'is-selected'
        );
    }


    selectedTile =
        tile;


    tile.classList.add(
        'is-selected'
    );


    const previewImg =
        tile.querySelector(
            'img'
        );


    editMainPreview.src =
        previewImg.src;


    const index =
        parseInt(
            tile.dataset.index,
            10
        );


    if (index === 0) {

        mainFrameBadge.textContent =
            '✓ Main Image';

        photoActions.style.display =
            'none';

    } else {

        mainFrameBadge.textContent =
            'Previewing';

        photoActions.style.display =
            'flex';
    }
}


/* ==================================================
   Render Gallery
================================================== */

function renderGallery() {

    photoGrid
        .querySelectorAll(
            '.photo-tile[data-type="new"]'
        )
        .forEach(
            function (tile) {

                tile.remove();

            }
        );


    selectedFiles.forEach(
        function (
            file,
            index
        ) {

            const tile =
                document.createElement(
                    'div'
                );


            tile.className =
                'photo-tile';


            if (index === 0) {

                tile.classList.add(
                    'is-main'
                );
            }


            tile.dataset.type =
                'new';


            tile.dataset.index =
                index;


            const img =
                document.createElement(
                    'img'
                );


            img.src =
                URL.createObjectURL(
                    file
                );


            img.alt =
                'Product Image';


            tile.appendChild(
                img
            );


            if (index === 0) {

                const label =
                    document.createElement(
                        'span'
                    );


                label.className =
                    'photo-tile-label';


                label.textContent =
                    'Main';


                tile.appendChild(
                    label
                );
            }


            photoGrid.insertBefore(
                tile,
                addPhotoTile
            );

        }
    );


    updateCounter();
}


/* ==================================================
   Add Files
================================================== */

function addFiles(files) {

    files.forEach(
        function (file) {

            /*
            Only allow image files
            */

            const allowedTypes = [
                'image/jpeg',
                'image/png',
                'image/webp'
            ];


            if (
                !allowedTypes.includes(
                    file.type
                )
            ) {

                alert(
                    'Only JPG, PNG and WebP images are allowed.'
                );

                return;
            }


            /*
            Maximum 5MB
            */

            if (
                file.size > 5 * 1024 * 1024
            ) {

                alert(
                    file.name
                    + ' is larger than 5MB.'
                );

                return;
            }


            /*
            Prevent duplicate file
            */

            const duplicate =
                selectedFiles.some(
                    function (
                        existingFile
                    ) {

                        return (
                            existingFile.name
                            ===
                            file.name
                            &&
                            existingFile.size
                            ===
                            file.size
                        );

                    }
                );


            if (duplicate) {

                return;
            }


            /*
            Maximum 5 images
            */

            if (
                selectedFiles.length >= 5
            ) {

                alert(
                    'A product can have a maximum of 5 images.'
                );

                return;
            }


            selectedFiles.push(
                file
            );

        }
    );


    updateFileInput();

    renderGallery();


    /*
    Automatically preview
    the first image
    */

    if (
        selectedFiles.length > 0
    ) {

        editMainPreview.src =
            URL.createObjectURL(
                selectedFiles[0]
            );

        mainFrameBadge.textContent =
            '✓ Main Image';
    }
}


/* ==================================================
   File Input
================================================== */

imageInput.addEventListener(
    'change',
    function () {

        addFiles(
            Array.from(
                this.files
            )
        );

    }
);


/* ==================================================
   Add Photo Tile
================================================== */

addPhotoTile.addEventListener(
    'click',
    function () {

        imageInput.click();

    }
);


/* ==================================================
   Select Photo
================================================== */

photoGrid.addEventListener(
    'click',
    function (event) {

        const tile =
            event.target.closest(
                '.photo-tile'
            );


        if (
            !tile ||
            tile === addPhotoTile
        ) {

            return;
        }


        selectTile(
            tile
        );

    }
);


/* ==================================================
   Set as Main
================================================== */

setMainBtn.addEventListener(
    'click',
    function () {

        if (
            !selectedTile
        ) {

            return;
        }


        const index =
            parseInt(
                selectedTile.dataset.index,
                10
            );


        if (
            index === 0
        ) {

            return;
        }


        /*
        Move selected image
        to position 0.

        The PHP backend already treats
        uploaded_images[0] as the main image.
        */

        const selectedFile =
            selectedFiles[index];


        selectedFiles.splice(
            index,
            1
        );


        selectedFiles.unshift(
            selectedFile
        );


        selectedTile =
            null;


        updateFileInput();

        renderGallery();


        editMainPreview.src =
            URL.createObjectURL(
                selectedFiles[0]
            );


        mainFrameBadge.textContent =
            '✓ Main Image';


        photoActions.style.display =
            'none';

    }
);


/* ==================================================
   Delete Selected Photo
================================================== */

deletePhotoBtn.addEventListener(
    'click',
    function () {

        if (
            !selectedTile
        ) {

            return;
        }


        const index =
            parseInt(
                selectedTile.dataset.index,
                10
            );


        /*
        Do not allow deleting
        the only image.
        */

        if (
            selectedFiles.length === 1
        ) {

            alert(
                'At least one product image is required.'
            );

            return;
        }


        selectedFiles.splice(
            index,
            1
        );


        selectedTile =
            null;


        updateFileInput();

        renderGallery();

        clearSelection();

    }
);


/* ==================================================
   Drag & Drop
================================================== */

[
    'dragenter',
    'dragover'
].forEach(
    function (eventName) {

        photoDropzone.addEventListener(
            eventName,
            function (event) {

                event.preventDefault();

                photoDropzone.classList.add(
                    'is-dragover'
                );

            }
        );

    }
);


[
    'dragleave',
    'dragend'
].forEach(
    function (eventName) {

        photoDropzone.addEventListener(
            eventName,
            function () {

                photoDropzone.classList.remove(
                    'is-dragover'
                );

            }
        );

    }
);


photoDropzone.addEventListener(
    'drop',
    function (event) {

        event.preventDefault();


        photoDropzone.classList.remove(
            'is-dragover'
        );


        if (
            event.dataTransfer &&
            event.dataTransfer.files
        ) {

            addFiles(
                Array.from(
                    event.dataTransfer.files
                )
            );

        }

    }
);


/* ==================================================
   Initial State
================================================== */

updateCounter();

</script>


<?php include '../../_foot.php'; ?>