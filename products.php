<?php
require '_base.php';

$_title = 'Shop Water Bottles';
$_hide_page_title = true;

$search = trim(get('search', ''));
$category_id = get('category', '');
$sort = get('sort', 'newest');
$page = max(1, (int) get('page', 1));
$limit = 6;

if ($category_id !== '' && !ctype_digit($category_id)) {
    $category_id = '';
}

$category_id = $category_id === '' ? null : (int) $category_id;

$sort_map = [
    'newest' => 'p.product_id DESC',
    'popular' => 'units_sold DESC, p.product_id DESC',
    'price_low' => 'p.price ASC',
    'price_high' => 'p.price DESC',
    'name_asc' => 'p.name ASC',
];
$sql_sort = $sort_map[$sort] ?? $sort_map['newest'];

$categories = $_db->query(
    'SELECT category_id, category_name FROM categories ORDER BY category_name'
)->fetchAll();

$product_filter = '(p.name LIKE :search OR p.description LIKE :search)';
if ($category_id !== null) {
    $product_filter .= ' AND p.category_id = :category_id';
}

$count_stmt = $_db->prepare(
    "SELECT COUNT(*) FROM products p WHERE $product_filter"
);
$count_stmt->bindValue(':search', "%$search%");
if ($category_id !== null) {
    $count_stmt->bindValue(':category_id', $category_id, PDO::PARAM_INT);
}
$count_stmt->execute();
$total_items = (int) $count_stmt->fetchColumn();

$total_pages = max(1, (int) ceil($total_items / $limit));
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

$top_selling_stmt = $_db->query(
    "SELECT
        p.product_id,
        p.name,
        p.description,
        p.price,
        p.image_url,
        c.category_name,
        COALESCE(sales.units_sold, 0) AS units_sold,
        COALESCE(stock.total_stock, 0) AS total_stock
    FROM products p
    JOIN categories c ON c.category_id = p.category_id
    LEFT JOIN (
        SELECT oi.product_id, SUM(oi.quantity) AS units_sold
        FROM order_items oi
        JOIN orders o ON o.order_id = oi.order_id
        WHERE o.status <> 'cancelled'
        GROUP BY oi.product_id
    ) sales ON sales.product_id = p.product_id
    LEFT JOIN (
        SELECT product_id, SUM(stock) AS total_stock
        FROM product_variants
        GROUP BY product_id
    ) stock ON stock.product_id = p.product_id
    WHERE COALESCE(sales.units_sold, 0) > 0
    ORDER BY units_sold DESC, p.product_id ASC
    LIMIT 4"
);
$top_selling = $top_selling_stmt->fetchAll();

$products_stmt = $_db->prepare(
    "SELECT
        p.product_id,
        p.name,
        p.description,
        p.price,
        p.image_url,
        c.category_name,
        COALESCE(sales.units_sold, 0) AS units_sold,
        COALESCE(stock.total_stock, 0) AS total_stock
    FROM products p
    JOIN categories c ON c.category_id = p.category_id
    LEFT JOIN (
        SELECT oi.product_id, SUM(oi.quantity) AS units_sold
        FROM order_items oi
        JOIN orders o ON o.order_id = oi.order_id
        WHERE o.status <> 'cancelled'
        GROUP BY oi.product_id
    ) sales ON sales.product_id = p.product_id
    LEFT JOIN (
        SELECT product_id, SUM(stock) AS total_stock
        FROM product_variants
        GROUP BY product_id
    ) stock ON stock.product_id = p.product_id
    WHERE $product_filter
    ORDER BY $sql_sort
    LIMIT :limit OFFSET :offset"
);
$products_stmt->bindValue(':search', "%$search%");
if ($category_id !== null) {
    $products_stmt->bindValue(':category_id', $category_id, PDO::PARAM_INT);
}
$products_stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$products_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$products_stmt->execute();
$products = $products_stmt->fetchAll();

function product_card($product, $show_sales = false) {
    $is_sold_out = (int) $product->total_stock === 0;
    ?>
    <article class="product-card <?= $is_sold_out ? 'is-sold-out' : '' ?>">
        <a href="product_detail.php?id=<?= $product->product_id ?>" class="product-card-link">
            <img src="<?= encode($product->image_url) ?>" alt="<?= encode($product->name) ?>">
            <p class="product-category"><?= encode($product->category_name) ?></p>
            <h3><?= encode($product->name) ?></h3>
        </a>
        <p><?= encode($product->description) ?></p>
        <p class="product-price">RM<?= number_format((float) $product->price, 2) ?></p>

        <?php if ($show_sales): ?>
            <p class="product-sales"><?= number_format((int) $product->units_sold) ?> sold</p>
        <?php elseif ($is_sold_out): ?>
            <p class="stock stock-out">Out of stock</p>
        <?php elseif ((int) $product->total_stock < 8): ?>
            <p class="stock stock-low">Only <?= (int) $product->total_stock ?> left</p>
        <?php endif; ?>

        <a href="product_detail.php?id=<?= $product->product_id ?>" class="view-details-btn">View Details</a>
    </article>
    <?php
}

require '_head.php';
?>

<section class="shop-hero">
    <p class="eyebrow">Everyday hydration, made simple</p>
    <h1>Find your next favourite bottle.</h1>
    <p>Insulated bottles, tumbler cups, kids essentials and useful accessories for every day.</p>
</section>

<?php if ($top_selling): ?>
<section class="top-selling-section">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Community favourites</p>
            <h2>Top Selling</h2>
        </div>
        <a href="#all-products">Shop all products</a>
    </div>
    <div class="product-grid top-selling-grid">
        <?php foreach ($top_selling as $product): ?>
            <?php product_card($product, true); ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section id="all-products" class="catalogue-section">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Browse the collection</p>
            <h2>All Products</h2>
        </div>
    </div>

    <form method="get" action="products.php" class="product-filter-form">
        <input
            type="search"
            name="search"
            placeholder="Search bottles, tumblers or accessories"
            value="<?= encode($search) ?>"
        >

        <select name="category">
            <option value="">All categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= $category->category_id ?>" <?= $category_id === (int) $category->category_id ? 'selected' : '' ?>>
                    <?= encode($category->category_name) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="sort">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most popular</option>
            <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: low to high</option>
            <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: high to low</option>
            <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name: A to Z</option>
        </select>

        <button type="submit">Apply</button>
        <?php if ($search !== '' || $category_id !== null || $sort !== 'newest'): ?>
            <a href="products.php" class="clear-filter">Clear</a>
        <?php endif; ?>
    </form>

    <p class="record-summary">
        Showing <?= count($products) ?> of <?= $total_items ?> product<?= $total_items === 1 ? '' : 's' ?>
    </p>

    <div class="product-grid">
        <?php if ($products): ?>
            <?php foreach ($products as $product): ?>
                <?php product_card($product); ?>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="empty-catalogue">No products match your search. Try another category or keyword.</p>
        <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav class="pagination" aria-label="Product pages">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a
                href="products.php?search=<?= urlencode($search) ?>&category=<?= $category_id ?? '' ?>&sort=<?= urlencode($sort) ?>&page=<?= $i ?>"
                class="<?= $i === $page ? 'active' : '' ?>"
            ><?= $i ?></a>
        <?php endfor; ?>
    </nav>
    <?php endif; ?>
</section>

<div id="cart-confirmation-modal" class="cart-confirmation-modal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="cart-confirmation-content">
        <h2>Added to Cart</h2>
        <p id="cart-confirmation-message"></p>
        <div class="cart-confirmation-actions">
            <button type="button" id="cart-confirmation-close">Continue Shopping</button>
            <button type="button" id="cart-confirmation-view">View Cart</button>
        </div>
    </div>
</div>

<?php require '_foot.php'; ?>
