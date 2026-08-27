<?php
require_once '../../_base.php';

$_title = 'Add Product';

/*----------------------------------------------------------
    Bulk CSV Import Handler

    Expects a CSV with header row containing at least:
    name, category_name, price, size, colour, stock, image_urls
    (description is the only optional column)

    image_urls holds one or more image links separated by a
    pipe "|" - e.g. "url1|url2". The first is the main product
    image, the rest become gallery images. At least one is
    required per row.

    Each valid row creates a product + one variant. Invalid
    rows are skipped and reported - the whole file is not
    rejected just because one row is bad.
-----------------------------------------------------------*/

function handle_bulk_import(PDO $_db, $file)
{
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Please choose a valid CSV file to upload.'];
    }

    if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        return ['error' => 'Only .csv files are accepted.'];
    }

    $handle = fopen($file['tmp_name'], 'r');

    if ($handle === false) {
        return ['error' => 'Unable to read the uploaded file.'];
    }

    $header = array_map('trim', fgetcsv($handle) ?: []);
    $required = ['name', 'category_name', 'price', 'size', 'colour', 'stock', 'image_urls'];
    $missing = array_diff($required, $header);

    if (!empty($missing)) {
        fclose($handle);
        return ['error' => 'Missing required column(s): ' . implode(', ', $missing)];
    }

    $imported = 0;
    $skipped = [];
    $row_number = 1;
    $category_cache = [];

    while (($row = fgetcsv($handle)) !== false) {

        $row_number++;

        if (count(array_filter($row, fn($v) => trim((string) $v) !== '')) === 0) {
            continue;
        }

        $data = array_combine($header, array_pad($row, count($header), ''));

        $name          = trim($data['name'] ?? '');
        $category_name = trim($data['category_name'] ?? '');
        $description   = trim($data['description'] ?? '');
        $price         = trim($data['price'] ?? '');
        $size          = trim($data['size'] ?? '');
        $colour        = trim($data['colour'] ?? '') ?: 'Standard';
        $stock         = trim($data['stock'] ?? '');

        /*
        image_urls supports multiple images per product,
        separated by a pipe "|" - e.g. "url1|url2|url3".
        The first URL becomes the main product image,
        any remaining URLs become gallery images, same as
        the extra photos uploaded on the single-product
        form. At least one URL is required - no blanks.
        */

        $image_urls_raw = trim($data['image_urls'] ?? '');

        $image_urls = array_values(array_filter(
            array_map('trim', explode('|', $image_urls_raw)),
            fn($url) => $url !== ''
        ));

        $row_errors = [];

        if ($name === '') $row_errors[] = 'name is required';
        if ($category_name === '') $row_errors[] = 'category_name is required';
        if ($price === '' || !is_numeric($price) || $price < 0) $row_errors[] = 'invalid price';
        if ($size === '') $row_errors[] = 'size is required';
        if ($stock === '' || !is_numeric($stock) || $stock < 0) $row_errors[] = 'invalid stock';
        if (empty($image_urls)) $row_errors[] = 'at least one image_urls value is required';
        if (count($image_urls) > 5) $row_errors[] = 'maximum 5 images allowed';

        if (!empty($row_errors)) {
            $skipped[] = "Row $row_number ($name): " . implode(', ', $row_errors);
            continue;
        }

        try {

            $_db->beginTransaction();

            if (!isset($category_cache[$category_name])) {

                $stmt = $_db->prepare("SELECT category_id FROM categories WHERE category_name = ?");
                $stmt->execute([$category_name]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $category_cache[$category_name] = $existing['category_id'];
                } else {
                    $stmt = $_db->prepare("INSERT INTO categories (category_name) VALUES (?)");
                    $stmt->execute([$category_name]);
                    $category_cache[$category_name] = $_db->lastInsertId();
                }
            }

            $main_image = $image_urls[0];

            $stmt = $_db->prepare("
                INSERT INTO products (category_id, name, description, price, image_url)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $category_cache[$category_name],
                $name,
                $description,
                $price,
                $main_image
            ]);

            $product_id = $_db->lastInsertId();

            if (count($image_urls) > 1) {

                $insert_image = $_db->prepare("
                    INSERT INTO product_images (product_id, image_url)
                    VALUES (?, ?)
                ");

                for ($i = 1; $i < count($image_urls); $i++) {
                    $insert_image->execute([$product_id, $image_urls[$i]]);
                }
            }

            $stmt = $_db->prepare("
                INSERT INTO product_variants (product_id, size, colour, stock)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$product_id, $size, $colour, $stock]);

            $_db->commit();

            $imported++;

        } catch (Exception $e) {

            if ($_db->inTransaction()) {
                $_db->rollBack();
            }

            $skipped[] = "Row $row_number ($name): database error";
        }
    }

    fclose($handle);

    return ['imported' => $imported, 'skipped' => $skipped];
}

/*----------------------------------------------------------
    Load Categories
-----------------------------------------------------------*/

$categories = $_db->query("
    SELECT * FROM categories ORDER BY category_name
")->fetchAll(PDO::FETCH_ASSOC);

$standard_sizes = [
    'Micro (12oz / 350ml)',
    'Mini (15oz / 450ml)',
    'Medium (18oz / 530ml)',
    'Mega (32oz / 950ml)'
];

/*----------------------------------------------------------
    Variables
-----------------------------------------------------------*/

$name = '';
$category = '';
$new_category = '';
$price = '';
$size = '';
$custom_size = '';
$colour = '';
$stock = '';
$description = '';
$video_url = '';

$error = [];
$bulk_results = null;
$active_tab = 'single';

/*----------------------------------------------------------
    Form Submitted
-----------------------------------------------------------*/

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $submit_mode = $_POST['submit_mode'] ?? 'single';

    /*--------------------------------------------------
        Bulk CSV Import
    --------------------------------------------------*/
    if ($submit_mode == 'bulk') {

    $bulk_results = handle_bulk_import($_db, $_FILES['csv_file'] ?? null);
    if (!isset($bulk_results['error'])) {
        /*
        Success (even if some rows were skipped) - hand the
        summary to admin_products.php via a one-time session
        flash, same as how delete/archive/restore show their
        result there instead of staying on this page.
        */
        $_SESSION['bulk_import_result'] = $bulk_results;

        header('Location: admin_products.php?view=active');
        exit;
    }
    /*
    Only a hard failure (e.g. missing columns, bad file)
    stays on this page so the admin can fix and retry.
    */
    $active_tab = 'bulk';
    /*--------------------------------------------------
        Single Product
    --------------------------------------------------*/
    } else {

        $name = trim($_POST['name'] ?? '');
        $category = $_POST['category'] ?? '';
        $new_category = trim($_POST['new_category'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $size = $_POST['size'] ?? '';
        $custom_size = trim($_POST['custom_size'] ?? '');
        $colour = trim($_POST['colour'] ?? '');
        $stock = trim($_POST['stock'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $video_url = trim($_POST['video_url'] ?? '');

        if ($name == '') $error['name'] = 'Product name is required.';

        if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {

            $error['image'] = 'At least one product image is required.';

        } else {

            $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
            $image_count = count($_FILES['images']['name']);

            if ($image_count > 5) {

                $error['image'] = 'You can upload a maximum of 5 images.';

            } else {

                for ($i = 0; $i < $image_count; $i++) {

                    if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
                        $error['image'] = 'One or more images failed to upload.';
                        break;
                    }

                    if (!in_array($_FILES['images']['type'][$i], $allowed_types)) {
                        $error['image'] = 'Only JPG, PNG and WEBP images are allowed.';
                        break;
                    }
                }
            }
        }

        if ($category == '') $error['category'] = 'Please select a category.';
        if ($category == 'new' && $new_category == '') $error['new_category'] = 'Please enter the new category.';

        if ($price == '') {
            $error['price'] = 'Price is required.';
        } elseif (!is_numeric($price) || $price < 0) {
            $error['price'] = 'Invalid price.';
        }

        if ($stock == '') {
            $error['stock'] = 'Stock is required.';
        } elseif (!is_numeric($stock) || $stock < 0) {
            $error['stock'] = 'Invalid stock.';
        }

        if ($size == '') $error['size'] = 'Please select a size.';
        if ($size == 'custom' && $custom_size == '') $error['custom_size'] = 'Please enter custom size.';
        if ($colour == '') $error['colour'] = 'Colour is required.';

        if (empty($error)) {

            $uploaded_files = [];

            try {

                $_db->beginTransaction();

                if ($category == 'new') {

                    $stmt = $_db->prepare("SELECT category_id FROM categories WHERE category_name = ?");
                    $stmt->execute([$new_category]);
                    $existing_category = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($existing_category) {
                        $category = $existing_category['category_id'];
                    } else {
                        $stmt = $_db->prepare("INSERT INTO categories (category_name) VALUES (?)");
                        $stmt->execute([$new_category]);
                        $category = $_db->lastInsertId();
                    }
                }

                if ($size == 'custom') $size = $custom_size;

                $folder = '../../img/products/';
                if (!is_dir($folder)) mkdir($folder, 0777, true);

                $uploaded_images = [];

                foreach ($_FILES['images']['name'] as $i => $original_name) {

                    $filename = time() . '_' . $i . '_' . basename($original_name);
                    $target = $folder . $filename;

                    if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $target)) {

                        $image_url = 'img/products/' . $filename;
                        $uploaded_images[] = $image_url;
                        $uploaded_files[] = $target;

                    } else {

                        throw new Exception('Failed to upload one or more images.');
                    }
                }

                $main_image = $uploaded_images[0];

                $stmt = $_db->prepare("
                    INSERT INTO products (category_id, name, description, price, image_url, video_url)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$category, $name, $description, $price, $main_image, $video_url !== '' ? $video_url : null]);

                $product_id = $_db->lastInsertId();

                if (count($uploaded_images) > 1) {

                    $insert_image = $_db->prepare("
                        INSERT INTO product_images (product_id, image_url)
                        VALUES (?, ?)
                    ");

                    for ($i = 1; $i < count($uploaded_images); $i++) {
                        $insert_image->execute([$product_id, $uploaded_images[$i]]);
                    }
                }

                $stmt = $_db->prepare("
                    INSERT INTO product_variants (product_id, size, colour, stock)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$product_id, $size, $colour, $stock]);

                $_db->commit();

                header("Location: admin_products.php");
                exit;

            } catch (Exception $e) {

                if ($_db->inTransaction()) $_db->rollBack();

                foreach ($uploaded_files as $file) {
                    if (file_exists($file)) unlink($file);
                }

                $error['database'] = 'Unable to add the product. Please try again.';
            }
        }
    }
}

include '../../_head.php';
?>

<link rel="stylesheet" href="../../css/main.css">
<link rel="stylesheet" href="../../css/admin.css">

<div class="admin-container">
    <div class="edit-card">
        <!-- <div class="edit-header">
            <h2>Add Product</h2>
        </div> -->
        <div class="product-tabs">
            <button type="button" class="tab-button <?= $active_tab == 'single' ? 'active' : '' ?>" data-tab="single">
                Single Product
            </button>
            <button type="button" class="tab-button <?= $active_tab == 'bulk' ? 'active' : '' ?>" data-tab="bulk">
                Bulk Upload (CSV)
            </button>
        </div>

        <?php if (!empty($error['database'])): ?>
            <div class="error-message"><?= htmlspecialchars($error['database']) ?></div>
        <?php endif; ?>

        <?php if (!empty($error) && empty($error['database'])): ?>
            <div class="error-message">Please correct the highlighted fields.</div>
        <?php endif; ?>

        <?php if ($bulk_results && isset($bulk_results['error'])): ?>
            <div class="error-message"><?= htmlspecialchars($bulk_results['error']) ?></div>
        <?php endif; ?>

        <?php if ($bulk_results && isset($bulk_results['imported'])): ?>
            <div class="success-message">
                <?= $bulk_results['imported'] ?> product<?= $bulk_results['imported'] == 1 ? '' : 's' ?> imported successfully.
                <?php if (!empty($bulk_results['skipped'])): ?>
                    <?= count($bulk_results['skipped']) ?> row<?= count($bulk_results['skipped']) == 1 ? '' : 's' ?> skipped.
                <?php endif; ?>
            </div>

            <?php if (!empty($bulk_results['skipped'])): ?>
                <div class="error-message">
                    <strong>Skipped rows:</strong>
                    <ul class="bulk-error-list">
                        <?php foreach ($bulk_results['skipped'] as $skip): ?>
                            <li><?= htmlspecialchars($skip) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- ======================================
             SINGLE PRODUCT PANEL
        ======================================= -->
        <div id="singleProductPanel" class="tab-panel" style="<?= $active_tab == 'single' ? '' : 'display:none;' ?>">
            <form method="post" enctype="multipart/form-data" class="add-product-form">
                <input type="hidden" name="submit_mode" value="single">

                <div class="edit-content">
                    <!-- LEFT SIDE - PHOTO MANAGER -->
                    <div class="edit-image">
                        <div class="photo-manager">
                            <div class="photo-manager-header">
                                <div>
                                    <h3>Product Photos</h3>
                                    <p>Add product images</p>
                                </div>
                                <span class="photo-counter" id="photoCounter">0/5</span>
                            </div>

                            <div class="photo-main-frame">
                                <span class="photo-main-badge" id="mainFrameBadge">Choose Image</span>
                                <img id="editMainPreview" src="https://placehold.co/400x400?text=Choose+Image" alt="Product Image Preview">
                            </div>

                            <div class="photo-grid" id="photoGrid">
                                <div class="photo-tile photo-tile-add" id="addPhotoTile">
                                    <span>+</span>
                                </div>
                            </div>

                            <div class="photo-actions" id="photoActions" style="display:none;">
                                <button type="button" class="btn-photo-main" id="setMainBtn">Set as Main</button>
                                <button type="button" class="btn-photo-delete" id="deletePhotoBtn">Delete</button>
                            </div>

                            <label for="imageInput" class="photo-dropzone" id="photoDropzone">
                                <div class="photo-dropzone-icon">&#8593;</div>
                                <div class="photo-dropzone-text">Drag &amp; drop or click to upload</div>
                                <div class="photo-dropzone-sub">JPG, PNG, WebP up to 5MB &middot; Max 5 images</div>
                            </label>

                            <input type="file" id="imageInput" name="images[]" class="file-input" accept="image/jpeg,image/png,image/webp" multiple>

                            <?php if (isset($error['image'])): ?>
                                <span class="error"><?= htmlspecialchars($error['image']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- RIGHT SIDE - FORM -->
                    <div class="edit-form">
                        <div class="form-group">
                            <label>Product Name *</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($name) ?>">
                            <small class="error"><?= htmlspecialchars($error['name'] ?? '') ?></small>
                        </div>

                        <div class="form-group">
                            <label>Category *</label>
                            <select name="category" id="category">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>" <?= ($category == $cat['category_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="new" <?= ($category == 'new') ? 'selected' : '' ?>>+ New Category</option>
                            </select>
                            <small class="error"><?= htmlspecialchars($error['category'] ?? '') ?></small>
                        </div>

                        <div class="form-group full-width" id="newCategoryBox" style="<?= ($category == 'new') ? '' : 'display:none;' ?>">
                            <label>New Category</label>
                            <input type="text" name="new_category" value="<?= htmlspecialchars($new_category) ?>">
                            <small class="error"><?= htmlspecialchars($error['new_category'] ?? '') ?></small>
                        </div>

                        <div class="form-group">
                            <label>Price (RM) *</label>
                            <input type="number" step="0.01" min="0" name="price" value="<?= htmlspecialchars($price) ?>">
                            <small class="error"><?= htmlspecialchars($error['price'] ?? '') ?></small>
                        </div>

                        <div class="form-group">
                            <label>Stock *</label>
                            <input type="number" min="0" name="stock" value="<?= htmlspecialchars($stock) ?>">
                            <small class="error"><?= htmlspecialchars($error['stock'] ?? '') ?></small>
                        </div>

                        <div class="form-group">
                            <label>Size *</label>
                            <select name="size" id="size">
                                <option value="">Select Size</option>
                                <?php foreach ($standard_sizes as $standard_size): ?>
                                    <option value="<?= htmlspecialchars($standard_size) ?>" <?= ($size == $standard_size) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($standard_size) ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="custom" <?= ($size == 'custom') ? 'selected' : '' ?>>Custom</option>
                            </select>
                            <small class="error"><?= htmlspecialchars($error['size'] ?? '') ?></small>
                        </div>

                        <div class="form-group full-width" id="customSizeBox" style="<?= ($size == 'custom') ? '' : 'display:none;' ?>">
                            <label>Custom Size</label>
                            <input type="text" name="custom_size" value="<?= htmlspecialchars($custom_size) ?>" placeholder="Enter custom size...">
                            <small class="error"><?= htmlspecialchars($error['custom_size'] ?? '') ?></small>
                        </div>

                        <div class="form-group">
                            <label>Colour *</label>
                            <input type="text" name="colour" value="<?= htmlspecialchars($colour) ?>">
                            <small class="error"><?= htmlspecialchars($error['colour'] ?? '') ?></small>
                        </div>

                        <div class="form-group full-width">
                            <label>Description</label>
                            <textarea name="description" rows="6"><?= htmlspecialchars($description) ?></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label>Product Video URL <small>(Optional)</small></label>
                            <input
                                type="url"
                                name="video_url"
                                value="<?= htmlspecialchars($video_url) ?>"
                                placeholder="https://www.youtube.com/watch?v=..."
                            >
                        </div>
                    </div>
                </div>

                <div class="edit-footer">
                    <a href="admin_products.php" class="btn-view">Cancel</a>
                    <button type="submit" class="btn-edit">Add Product</button>
                </div>
            </form>
        </div>

        <!-- ======================================
             BULK UPLOAD PANEL
        ======================================= -->
        <div id="bulkUploadPanel" class="tab-panel" style="<?= $active_tab == 'bulk' ? '' : 'display:none;' ?>">

            <div class="bulk-info-banner">
                <span>&#9432; Download our CSV template to ensure your data is formatted correctly.</span>
                <a href="product_import_template.csv" download class="btn-view">Download Template</a>
            </div>

            <form method="post" enctype="multipart/form-data" id="bulkUploadForm">
                <input type="hidden" name="submit_mode" value="bulk">

                <label for="csvInput" class="csv-dropzone" id="csvDropzone">
                    <div class="photo-dropzone-icon">&#8593;</div>
                    <div class="photo-dropzone-text" id="csvDropzoneText">Drag &amp; drop your CSV file here</div>
                    <div class="photo-dropzone-sub">or</div>
                    <span class="btn-view" id="csvBrowseBtn">Browse Files</span>
                    <div class="photo-dropzone-sub">Accepted format: .csv &middot; Max file size: 10MB</div>
                </label>

                <input type="file" id="csvInput" name="csv_file" accept=".csv" class="file-input">

                <h3 class="bulk-preview-title">Preview</h3>

                <div class="bulk-preview-box" id="bulkPreviewBox">
                    <div class="bulk-preview-empty" id="bulkPreviewEmpty">
                        Upload a CSV file to preview your products before importing.
                    </div>
                    <div class="bulk-preview-table-wrapper" id="bulkPreviewTableWrapper" style="display:none;">
                        <table class="bulk-preview-table" id="bulkPreviewTable">
                            <thead><tr id="bulkPreviewHead"></tr></thead>
                            <tbody id="bulkPreviewBody"></tbody>
                        </table>
                    </div>
                </div>

                <div class="edit-footer">
                    <a href="admin_products.php" class="btn-view">Cancel</a>
                    <button type="submit" class="btn-edit" id="bulkImportBtn" disabled>Import Products</button>
                </div>
            </form>
        </div>

    </div>
</div>

<script>

/* ==================================================
   TABS
================================================== */

document.querySelectorAll('.tab-button').forEach(function (btn) {

    btn.addEventListener('click', function () {

        document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        document.getElementById('singleProductPanel').style.display = this.dataset.tab === 'single' ? 'block' : 'none';
        document.getElementById('bulkUploadPanel').style.display = this.dataset.tab === 'bulk' ? 'block' : 'none';
    });
});

/* ==================================================
   CATEGORY / SIZE TOGGLES
================================================== */

document.getElementById('category').addEventListener('change', function () {
    document.getElementById('newCategoryBox').style.display = this.value === 'new' ? 'block' : 'none';
});

document.getElementById('size').addEventListener('change', function () {
    document.getElementById('customSizeBox').style.display = this.value === 'custom' ? 'block' : 'none';
});

/* ==================================================
   PHOTO MANAGER (Single Product)
================================================== */

const imageInput = document.getElementById('imageInput');
const photoGrid = document.getElementById('photoGrid');
const addPhotoTile = document.getElementById('addPhotoTile');
const editMainPreview = document.getElementById('editMainPreview');
const mainFrameBadge = document.getElementById('mainFrameBadge');
const photoActions = document.getElementById('photoActions');
const setMainBtn = document.getElementById('setMainBtn');
const deletePhotoBtn = document.getElementById('deletePhotoBtn');
const photoCounter = document.getElementById('photoCounter');
const photoDropzone = document.getElementById('photoDropzone');

let selectedFiles = [];
let selectedTile = null;

function updateCounter() {
    photoCounter.textContent = selectedFiles.length + '/5';
    addPhotoTile.style.display = selectedFiles.length >= 5 ? 'none' : 'flex';
}

function updateFileInput() {
    const dt = new DataTransfer();
    selectedFiles.forEach(f => dt.items.add(f));
    imageInput.files = dt.files;
}

function clearSelection() {
    if (selectedTile) selectedTile.classList.remove('is-selected');
    selectedTile = null;
    photoActions.style.display = 'none';

    if (selectedFiles.length > 0) {
        editMainPreview.src = URL.createObjectURL(selectedFiles[0]);
        mainFrameBadge.textContent = '✓ Main Image';
    } else {
        editMainPreview.src = 'https://placehold.co/400x400?text=Choose+Image';
        mainFrameBadge.textContent = 'Choose Image';
    }
}

function selectTile(tile) {
    if (tile === selectedTile) { clearSelection(); return; }
    if (selectedTile) selectedTile.classList.remove('is-selected');

    selectedTile = tile;
    tile.classList.add('is-selected');

    const index = parseInt(tile.dataset.index, 10);
    editMainPreview.src = tile.querySelector('img').src;

    if (index === 0) {
        mainFrameBadge.textContent = '✓ Main Image';
        photoActions.style.display = 'none';
    } else {
        mainFrameBadge.textContent = 'Previewing';
        photoActions.style.display = 'flex';
    }
}

function renderGallery() {
    photoGrid.querySelectorAll('.photo-tile[data-type="new"]').forEach(t => t.remove());

    selectedFiles.forEach(function (file, index) {

        const tile = document.createElement('div');
        tile.className = 'photo-tile' + (index === 0 ? ' is-main' : '');
        tile.dataset.type = 'new';
        tile.dataset.index = index;

        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.alt = 'Product Image';
        tile.appendChild(img);

        if (index === 0) {
            const label = document.createElement('span');
            label.className = 'photo-tile-label';
            label.textContent = 'Main';
            tile.appendChild(label);
        }

        photoGrid.insertBefore(tile, addPhotoTile);
    });

    updateCounter();
}

function addFiles(files) {

    files.forEach(function (file) {

        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!allowedTypes.includes(file.type)) {
            alert('Only JPG, PNG and WebP images are allowed.');
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            alert(file.name + ' is larger than 5MB.');
            return;
        }

        const duplicate = selectedFiles.some(f => f.name === file.name && f.size === file.size);
        if (duplicate) return;

        if (selectedFiles.length >= 5) {
            alert('A product can have a maximum of 5 images.');
            return;
        }

        selectedFiles.push(file);
    });

    updateFileInput();
    renderGallery();

    if (selectedFiles.length > 0) {
        editMainPreview.src = URL.createObjectURL(selectedFiles[0]);
        mainFrameBadge.textContent = '✓ Main Image';
    }
}

imageInput.addEventListener('change', function () { addFiles(Array.from(this.files)); });
addPhotoTile.addEventListener('click', function () { imageInput.click(); });

photoGrid.addEventListener('click', function (e) {
    const tile = e.target.closest('.photo-tile');
    if (!tile || tile === addPhotoTile) return;
    selectTile(tile);
});

setMainBtn.addEventListener('click', function () {
    if (!selectedTile) return;

    const index = parseInt(selectedTile.dataset.index, 10);
    if (index === 0) return;

    const file = selectedFiles.splice(index, 1)[0];
    selectedFiles.unshift(file);
    selectedTile = null;

    updateFileInput();
    renderGallery();

    editMainPreview.src = URL.createObjectURL(selectedFiles[0]);
    mainFrameBadge.textContent = '✓ Main Image';
    photoActions.style.display = 'none';
});

deletePhotoBtn.addEventListener('click', function () {
    if (!selectedTile) return;

    if (selectedFiles.length === 1) {
        alert('At least one product image is required.');
        return;
    }

    const index = parseInt(selectedTile.dataset.index, 10);
    selectedFiles.splice(index, 1);
    selectedTile = null;

    updateFileInput();
    renderGallery();
    clearSelection();
});

['dragenter', 'dragover'].forEach(evt => {
    photoDropzone.addEventListener(evt, function (e) {
        e.preventDefault();
        photoDropzone.classList.add('is-dragover');
    });
});

['dragleave', 'dragend'].forEach(evt => {
    photoDropzone.addEventListener(evt, function () {
        photoDropzone.classList.remove('is-dragover');
    });
});

photoDropzone.addEventListener('drop', function (e) {
    e.preventDefault();
    photoDropzone.classList.remove('is-dragover');
    if (e.dataTransfer && e.dataTransfer.files) addFiles(Array.from(e.dataTransfer.files));
});

updateCounter();

/* ==================================================
   BULK CSV UPLOAD - client-side parse for preview only.
   The actual import happens server-side on submit.
================================================== */

const csvInput = document.getElementById('csvInput');
const csvDropzone = document.getElementById('csvDropzone');
const csvBrowseBtn = document.getElementById('csvBrowseBtn');
const csvDropzoneText = document.getElementById('csvDropzoneText');
const bulkPreviewEmpty = document.getElementById('bulkPreviewEmpty');
const bulkPreviewTableWrapper = document.getElementById('bulkPreviewTableWrapper');
const bulkPreviewHead = document.getElementById('bulkPreviewHead');
const bulkPreviewBody = document.getElementById('bulkPreviewBody');
const bulkImportBtn = document.getElementById('bulkImportBtn');

function parseCsv(text) {

    const rows = [];
    let row = [], field = '', inQuotes = false;

    for (let i = 0; i < text.length; i++) {

        const char = text[i], next = text[i + 1];

        if (inQuotes) {
            if (char === '"' && next === '"') { field += '"'; i++; }
            else if (char === '"') { inQuotes = false; }
            else { field += char; }
        } else {
            if (char === '"') { inQuotes = true; }
            else if (char === ',') { row.push(field); field = ''; }
            else if (char === '\n' || char === '\r') {
                if (char === '\r' && next === '\n') i++;
                row.push(field);
                rows.push(row);
                row = [];
                field = '';
            } else {
                field += char;
            }
        }
    }

    if (field.length > 0 || row.length > 0) { row.push(field); rows.push(row); }

    return rows.filter(r => r.length > 1 || r[0] !== '');
}

function handleCsvFile(file) {

    csvDropzoneText.textContent = file.name;

    const reader = new FileReader();

    reader.onload = function (e) {

        const rows = parseCsv(e.target.result);

        if (rows.length < 2) {
            alert('The CSV file appears to be empty.');
            bulkImportBtn.disabled = true;
            return;
        }

        const header = rows[0];
        const dataRows = rows.slice(1).filter(r => r.some(cell => cell.trim() !== ''));

        bulkPreviewHead.innerHTML = '';
        header.forEach(function (col) {
            const th = document.createElement('th');
            th.textContent = col;
            bulkPreviewHead.appendChild(th);
        });

        bulkPreviewBody.innerHTML = '';
        dataRows.slice(0, 20).forEach(function (row) {
            const tr = document.createElement('tr');
            header.forEach(function (col, i) {
                const td = document.createElement('td');
                td.textContent = row[i] ?? '';
                tr.appendChild(td);
            });
            bulkPreviewBody.appendChild(tr);
        });

        bulkPreviewEmpty.style.display = 'none';
        bulkPreviewTableWrapper.style.display = 'block';
        bulkImportBtn.disabled = dataRows.length === 0;
    };

    reader.readAsText(file);
}

csvBrowseBtn.addEventListener('click', function (e) { e.preventDefault(); csvInput.click(); });

csvInput.addEventListener('change', function () {
    if (this.files[0]) handleCsvFile(this.files[0]);
});

['dragenter', 'dragover'].forEach(evt => {
    csvDropzone.addEventListener(evt, function (e) {
        e.preventDefault();
        csvDropzone.classList.add('is-dragover');
    });
});

['dragleave', 'dragend'].forEach(evt => {
    csvDropzone.addEventListener(evt, function () {
        csvDropzone.classList.remove('is-dragover');
    });
});

csvDropzone.addEventListener('drop', function (e) {
    e.preventDefault();
    csvDropzone.classList.remove('is-dragover');

    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
        const dt = new DataTransfer();
        dt.items.add(e.dataTransfer.files[0]);
        csvInput.files = dt.files;
        handleCsvFile(e.dataTransfer.files[0]);
    }
});

</script>

<?php include '../../_foot.php'; ?>