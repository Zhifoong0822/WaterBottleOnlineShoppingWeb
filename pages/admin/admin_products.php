<?php
require_once '../../_base.php';
require_admin('../../products.php');

$search = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';
$page = $_GET['page'] ?? 1;

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

$query = http_build_query([
    'search'   => $search,
    'category' => $category,
    'status'   => $status,
    'sort'     => $sort, // Ensure the sort parameter is included in the query string
    'order'    => strtolower($order)
]);

$_title = 'Product Management';
include '../../_head.php';
?>
<?php if (isset($_GET['deleted']) && $_GET['deleted'] == '1'): ?>

    <div class="success-message">
        Product deleted successfully.
    </div>

<?php endif; ?>

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

        <a href="<?= ($status == 'low') ? 'admin_products.php' : 'admin_products.php?status=low' ?>" class="dashboard-card">
            <h3>Low Stock Products 
                <?= $status == 'low' ? '▼' : '▲' ?>
            </h3>
            <h2><?= $low_stock_products ?></h2>
        </a>

        <a href="<?= ($status == 'out') ? 'admin_products.php' : 'admin_products.php?status=out' ?>" class="dashboard-card">
            <h3>Out of Stock Products
                <?= $status == 'out' ? '▼' : '▲' ?>
            </h3>
            <h2><?= $out_of_stock_products ?></h2>
        </a>
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
                        <th>
                            <a href="?<?= http_build_query([
                                'search'=>$search,
                                'category'=>$category,
                                'status'=>$status,
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
                                <td>
                                    <img
                                        src="../../<?= htmlspecialchars($product['image_url']) ?>"
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
                                    <div class="action-button-group">

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

<?php include '../../_foot.php'; ?>
