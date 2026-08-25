<?php
require_once '../../_base.php';
require_admin('../../products.php');

function admin_image_src($image_url)
{
    return (str_starts_with($image_url, 'http://') || str_starts_with($image_url, 'https://'))
        ? $image_url
        : '../../' . ltrim($image_url, '/');
}
/*
One-time flash message from a bulk CSV import on
product_add.php - read once, then cleared.
*/
$bulk_import_result = null;

if (isset($_SESSION['bulk_import_result'])) {
    $bulk_import_result = $_SESSION['bulk_import_result'];
    unset($_SESSION['bulk_import_result']);
}
$search = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';
$page = $_GET['page'] ?? 1;

/*
Active vs Archived tab. Anything other than
"archived" falls back to "active".
*/
$view = (($_GET['view'] ?? 'active') === 'archived') ? 'archived' : 'active';

$sort = $_GET['sort'] ?? 'product_id';
$order = $_GET['order'] ?? 'desc';
$page = max(1, (int)$page);
$limit = 10;
$offset = ($page - 1) * $limit;

function sortIcon($column, $sort, $order)
{
    if ($sort != $column) {
        return "⇅";
    }

    return strtolower($order) == "asc"
        ? "▲"
        : "▼";
}
function nextOrder($column, $sort, $order)
{
    if ($sort != $column) {
        return "asc";
    }

    if (strtolower($order) == "asc") {
        return "desc";
    }

    return "";
}
// Base SQL
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
    $countSql = "
        SELECT COUNT(*)

        FROM products p

        LEFT JOIN categories c
            ON p.category_id = c.category_id

        LEFT JOIN product_variants pv
            ON p.product_id = pv.product_id

        WHERE 1=1
    ";
    $countParams = [];

    /*
    Tab filter - only show products matching the
    currently selected Active / Archived tab.
    */

    $sql .= "
        AND p.status = ?
    ";

    $countSql .= "
        AND p.status = ?
    ";

    $params[] = $view;
    $countParams[] = $view;

    if ($search != '') {
        $sql .= "
            AND (
                p.name LIKE ?
                OR c.category_name LIKE ?
                OR pv.size LIKE ?
                OR pv.colour LIKE ?
            )
        ";

        $countSql .= "
            AND (
                p.name LIKE ?
                OR c.category_name LIKE ?
                OR pv.size LIKE ?
                OR pv.colour LIKE ?
            )
        ";

        $keyword = "%{$search}%";

        for ($i = 0; $i < 4; $i++) {
            $params[] = $keyword;
            $countParams[] = $keyword;
        }
    }
    if ($category != '') {
        $sql .= "
            AND p.category_id = ?
        ";

        $countSql .= "
            AND p.category_id = ?
        ";

        $params[] = $category;
        $countParams[] = $category;
    }
    
    if ($status == 'low') {
        $sql .= "
            AND pv.stock > 0
            AND pv.stock <= 5
        ";
        $countSql .= "
            AND pv.stock > 0
            AND pv.stock <= 5
        ";

    }
    elseif ($status == 'out') {

        $sql .= "
            AND pv.stock = 0
        ";
        $countSql .= "
            AND pv.stock = 0
        ";

    }

    $allowedSort = [
        'product_id'    => 'p.product_id',
        'name'          => 'p.name',
        'category_name' => 'c.category_name',
        'price'         => 'p.price',
        'stock'         => 'pv.stock'
    ];
    if (!array_key_exists($sort, $allowedSort)) {
        $sort = 'product_id';
    }

    if ($order == 'asc') {
        $orderSql = 'ASC';
    }
    elseif ($order == 'desc') {
        $orderSql = 'DESC';
    }
    else {
        $sort = 'product_id';
        $orderSql = 'ASC';
    }

    $sql .= "
    ORDER BY {$allowedSort[$sort]} $orderSql
    LIMIT $limit OFFSET $offset
    ";

// Count all matching records
$countStmt = $_db->prepare($countSql);
$countStmt->execute($countParams);

$total_products = $countStmt->fetchColumn();
$totalPages = ceil($total_products / $limit);

$stmt = $_db->prepare($sql);
$stmt->execute($params);

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_categories = $_db->query("
    SELECT COUNT(*) FROM categories
")->fetchColumn();

/*
These dashboard cards always reflect the live,
customer-visible catalog - regardless of which
tab / search / filters are currently applied to
the table below. Archived products never count
towards them.
*/

$active_product_count = $_db->query("
    SELECT COUNT(*)
    FROM products
    WHERE status = 'active'
")->fetchColumn();

$archived_product_count = $_db->query("
    SELECT COUNT(*)
    FROM products
    WHERE status = 'archived'
")->fetchColumn();

$low_stock_products = $_db->query("
    SELECT COUNT(*)
    FROM product_variants pv
    JOIN products p
        ON p.product_id = pv.product_id
    WHERE p.status = 'active'
      AND pv.stock > 0
      AND pv.stock <= 5
")->fetchColumn();
$out_of_stock_products = $_db->query("
    SELECT COUNT(*)
    FROM product_variants pv
    JOIN products p
        ON p.product_id = pv.product_id
    WHERE p.status = 'active'
      AND pv.stock = 0
")->fetchColumn();

$categories = $_db->query("
SELECT *
FROM categories
ORDER BY category_name
")->fetchAll(PDO::FETCH_ASSOC);

$query = http_build_query([
    'search'   => $search,
    'category' => $category,
    'status'   => $status,
    'view'     => $view,
    'sort'     => $sort, // Ensure the sort parameter is included in the query string
    'order'    => strtolower($order)
]);

$_title = 'Product Management';
include '../../_head.php';
?>
<?php if (isset($_GET['deleted']) && (int) $_GET['deleted'] > 0): ?>

    <?php $deleted_count = (int) $_GET['deleted']; ?>

    <div class="success-message">
        <?= $deleted_count ?> product<?= $deleted_count > 1 ? 's' : '' ?> deleted successfully.
    </div>

<?php endif; ?>

<?php if (isset($_GET['archived']) && (int) $_GET['archived'] > 0): ?>

    <?php $archived_count = (int) $_GET['archived']; ?>

    <div class="success-message">
        <?= $archived_count ?> product<?= $archived_count > 1 ? 's' : '' ?> archived.
        Hidden from customers, nothing was deleted.
    </div>

<?php endif; ?>

<?php if (isset($_GET['restored']) && (int) $_GET['restored'] > 0): ?>

    <?php $restored_count = (int) $_GET['restored']; ?>

    <div class="success-message">
        <?= $restored_count ?> product<?= $restored_count > 1 ? 's' : '' ?> restored and visible to customers again.
    </div>

<?php endif; ?>

<?php if ($bulk_import_result): ?>

    <div class="success-message">
        <?= $bulk_import_result['imported'] ?> product<?= $bulk_import_result['imported'] == 1 ? '' : 's' ?> imported successfully.
        <?php if (!empty($bulk_import_result['skipped'])): ?>
            <?= count($bulk_import_result['skipped']) ?> row<?= count($bulk_import_result['skipped']) == 1 ? '' : 's' ?> skipped.
        <?php endif; ?>
    </div>

    <?php if (!empty($bulk_import_result['skipped'])): ?>
        <div class="error-message">
            <strong>Skipped rows:</strong>
            <ul class="bulk-error-list">
                <?php foreach ($bulk_import_result['skipped'] as $skip): ?>
                    <li><?= htmlspecialchars($skip) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

<?php endif; ?>

<link rel="stylesheet" href="../../css/main.css">
<link rel="stylesheet" href="../../css/admin.css">

<div class="admin-container">
    <div class="dashboard-cards">
        <div class="dashboard-card">
            <h3>Total Products</h3>
            <h2><?= $active_product_count ?></h2>
        </div>

        <div class="dashboard-card">
            <h3>Total Categories</h3>
            <h2><?= $total_categories ?></h2>
        </div>

        <?php $lowActive = ($status == 'low' && $view === 'active'); ?>
        <a href="<?= $lowActive ? 'admin_products.php?view=active' : 'admin_products.php?status=low&view=active' ?>" class="dashboard-card">
            <h3>Low Stock Products 
                <?= $lowActive ? '▼' : '▲' ?>
            </h3>
            <h2><?= $low_stock_products ?></h2>
        </a>

        <?php $outActive = ($status == 'out' && $view === 'active'); ?>
        <a href="<?= $outActive ? 'admin_products.php?view=active' : 'admin_products.php?status=out&view=active' ?>" class="dashboard-card">
            <h3>Out of Stock Products
                <?= $outActive ? '▼' : '▲' ?>
            </h3>
            <h2><?= $out_of_stock_products ?></h2>
        </a>
    </div>

    <div class="product-card">

        <div class="view-tabs">

            <a
                href="?<?= http_build_query(array_merge($_GET, ['view' => 'active', 'page' => 1])) ?>"
                class="view-tab <?= $view === 'active' ? 'is-active' : '' ?>">
                Active
                <span class="view-tab-count"><?= $active_product_count ?></span>
            </a>

            <a
                href="?<?= http_build_query(array_merge($_GET, ['view' => 'archived', 'page' => 1])) ?>"
                class="view-tab <?= $view === 'archived' ? 'is-active' : '' ?>">
                Archived
                <span class="view-tab-count"><?= $archived_product_count ?></span>
            </a>

        </div>

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

        <!--
            Batch selection form.

            Active tab: submits (GET) to product_archive.php with
            ids[] in the query string - archives every selected
            product immediately (reversible, so just a JS confirm,
            no separate confirmation page).

            Archived tab: two buttons share the same checkboxes -
            "Restore Selected" (product_restore.php, instant) and
            "Delete Selected" (product_delete.php, permanent -
            re-uses the SAME confirmation page as the single-row
            Delete link).
        -->
        <form
            id="batchActionForm"
            method="GET"
            action="<?= $view === 'active' ? 'product_archive.php' : 'product_restore.php' ?>">

            <div
                class="selection-bar"
                id="selectionBar">

                <span id="selectionCount">
                    0 items selected
                </span>

                <div class="selection-actions">

                    <button
                        type="button"
                        id="deselectAllBtn"
                        class="btn-view">
                        Deselect All
                    </button>

                    <?php if ($view === 'active'): ?>

                        <button
                            type="submit"
                            formaction="product_archive.php"
                            data-batch-action="archive"
                            class="btn-delete">
                            Archive Selected
                        </button>

                    <?php else: ?>

                        <button
                            type="submit"
                            formaction="product_restore.php"
                            data-batch-action="restore"
                            class="btn-restore">
                            Restore Selected
                        </button>

                        <button
                            type="submit"
                            formaction="product_delete.php"
                            data-batch-action="delete"
                            class="btn-delete">
                            Delete Selected
                        </button>

                    <?php endif; ?>

                </div>

            </div>

            <div class="table-wrapper">
                <table class="product-table">
                    <thead>
                        <tr>
                            <th class="checkbox-col">
                                <input
                                    type="checkbox"
                                    id="selectAllCheckbox"
                                    title="Select all">
                            </th>
                            <th>Image</th>
                            <th>
                                <a href="?<?= http_build_query([
                                    'search'=>$search,
                                    'category'=>$category,
                                    'status'=>$status,
                                    'view'=>$view,
                                    'sort'=>'name',
                                    'order'=>nextOrder('name',$sort,$order),
                                    'page'=>1
                                ]) ?>" class="sort-link">

                                Product <?= sortIcon('name',$sort,$order) ?>
                                </a>
                            </th>
                            <th>
                                <a href="?<?= http_build_query([
                                    'search'=>$search,
                                    'category'=>$category,
                                    'status'=>$status,
                                    'view'=>$view,
                                    'sort'=>'category_name',
                                    'order'=>nextOrder('category_name',$sort,$order),
                                    'page'=>1
                                ]) ?>" class="sort-link">

                                Category <?= sortIcon('category_name',$sort,$order) ?>

                                </a>
                            </th>
                            <th>Size</th>
                            <th>
                                <a href="?<?= http_build_query([
                                    'search'=>$search,
                                    'category'=>$category,
                                    'status'=>$status,
                                    'view'=>$view,
                                    'sort'=>'price',
                                    'order'=>nextOrder('price',$sort,$order),
                                    'page'=>1
                                ]) ?>" class="sort-link">

                                Price <?= sortIcon('price',$sort,$order) ?>
                                </a>
                            </th>
                            <th>
                                <a href="?<?= http_build_query([
                                    'search'=>$search,
                                    'category'=>$category,
                                    'status'=>$status,
                                    'view'=>$view,
                                    'sort'=>'stock',
                                    'order'=>nextOrder('stock',$sort,$order),
                                    'page'=>1
                                ]) ?>" class="sort-link">

                                Stock <?= sortIcon('stock',$sort,$order) ?>

                                </a>
                            </th>
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
                                    <td class="checkbox-col">
                                        <input
                                            type="checkbox"
                                            name="ids[]"
                                            value="<?= $product['product_id'] ?>"
                                            class="row-checkbox">
                                    </td>

                                    <td>
                                        <img
                                            src="<?= htmlspecialchars(admin_image_src($product['image_url'])) ?>"
                                            class="product-image <?= $view === 'archived' ? 'is-archived' : '' ?>"
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
                                        <div class="action-button-group">

                                            <a href="product_view.php?id=<?= $product['product_id'] ?>"
                                            class="btn-view">
                                                View
                                            </a>

                                            <a href="product_edit.php?id=<?= $product['product_id'] ?>"
                                            class="btn-edit">
                                                Edit
                                            </a>

                                            <?php if ($view === 'active'): ?>

                                                <a href="product_archive.php?id=<?= $product['product_id'] ?>"
                                                class="btn-delete"
                                                onclick="return confirm('Archive this product?\n\nCustomers won\'t be able to see it anymore, but nothing is deleted - you can restore it anytime from the Archived tab.');">
                                                    Archive
                                                </a>

                                            <?php else: ?>

                                                <a href="product_restore.php?id=<?= $product['product_id'] ?>"
                                                class="btn-restore">
                                                    Restore
                                                </a>

                                                <a href="product_delete.php?id=<?= $product['product_id'] ?>"
                                                class="btn-delete">
                                                    Delete
                                                </a>

                                            <?php endif; ?>

                                        </div>
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

        </form>

        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?= $query ?>&page=<?= $page - 1 ?>">
                    Previous
                </a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a
                    class="<?= $page == $i ? 'active' : '' ?>"
                    href="?<?= $query ?>&page=<?= $i ?>">
                    <?= $i ?>
                </a>

            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?<?= $query ?>&page=<?= $page + 1 ?>">
                    Next
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>

/* ==================================================
   BATCH SELECTION
   Checking a row shows the selection bar. "Delete
   Selected" submits the surrounding form (GET) to
   product_delete.php, which reads ids[] from the
   query string and shows the same confirmation page
   used for single-row deletes.
================================================== */

const selectAllCheckbox = document.getElementById('selectAllCheckbox');
const rowCheckboxes     = document.querySelectorAll('.row-checkbox');
const selectionBar      = document.getElementById('selectionBar');
const selectionCount    = document.getElementById('selectionCount');
const deselectAllBtn    = document.getElementById('deselectAllBtn');
const batchActionForm   = document.getElementById('batchActionForm');


function updateSelectionState() {

    const checked = document.querySelectorAll('.row-checkbox:checked');
    const total   = rowCheckboxes.length;

    if (checked.length > 0) {

        selectionBar.classList.add('is-active');

        selectionCount.textContent =
            checked.length + ' item' + (checked.length > 1 ? 's' : '') + ' selected';

    } else {

        selectionBar.classList.remove('is-active');
    }

    if (selectAllCheckbox) {

        selectAllCheckbox.checked =
            total > 0 && checked.length === total;

        selectAllCheckbox.indeterminate =
            checked.length > 0 && checked.length < total;
    }

    rowCheckboxes.forEach(function (cb) {

        cb.closest('tr').classList.toggle(
            'row-selected',
            cb.checked
        );
    });
}


if (selectAllCheckbox) {

    selectAllCheckbox.addEventListener('change', function () {

        rowCheckboxes.forEach(function (cb) {
            cb.checked = selectAllCheckbox.checked;
        });

        updateSelectionState();
    });
}


rowCheckboxes.forEach(function (cb) {
    cb.addEventListener('change', updateSelectionState);
});


if (deselectAllBtn) {

    deselectAllBtn.addEventListener('click', function () {

        rowCheckboxes.forEach(function (cb) {
            cb.checked = false;
        });

        updateSelectionState();
    });
}


/*
Safety net: don't let the form submit with nothing
selected (e.g. if the bar was left visible somehow).

Archive Selected is reversible but still hides
products from customers, so it gets a confirm
dialog. Restore Selected is non-destructive, no
confirm needed. Delete Selected already navigates
to a full confirmation page (product_delete.php),
so no extra confirm here.
*/

if (batchActionForm) {

    batchActionForm.addEventListener('submit', function (e) {

        const checked = document.querySelectorAll('.row-checkbox:checked');

        if (checked.length === 0) {
            e.preventDefault();
            return;
        }

        const submitter = e.submitter;
        const action = submitter ? submitter.dataset.batchAction : null;

        if (action === 'archive') {

            const confirmed = confirm(
                'Archive ' + checked.length + ' product(s)?\n\n'
                + 'They will be hidden from customers, but you can '
                + 'restore them anytime from the Archived tab.'
            );

            if (!confirmed) {
                e.preventDefault();
            }
        }
    });
}


updateSelectionState();

</script>

<?php include '../../_foot.php'; ?>