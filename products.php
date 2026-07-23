<?php
// 1. Load configuration utilities, session variables, and global $_db
require '_base.php'; 

// 2. Supply dynamic metadata tracking to _head.php template
$_title = "Products";

// 3. Inject standard layout structure, styling mappings, and navigation structures
include '_head.php'; 

// ============================================================================
// SORTING & PAGINATION SETUP
// ============================================================================
$search = trim(req('search', ''));
$sort   = req('sort', 'newest');
$page   = max(1, intval(req('page', 1)));
$limit  = 8; // Number of product cards to display per page

// Map user choices to SQL ORDER BY clauses
$sort_map = [
    'newest'     => 'p.product_id DESC',
    'price_low'  => 'p.price ASC',
    'price_high' => 'p.price DESC',
    'name_asc'   => 'p.name ASC',
    'name_desc'  => 'p.name DESC',
];
$sql_sort = isset($sort_map[$sort]) ? $sort_map[$sort] : $sort_map['newest'];

// Step A: Calculate Total Products Count for Pagination
$count_stmt = $_db->prepare("
    SELECT COUNT(DISTINCT p.product_id)
    FROM products p
    WHERE p.name LIKE ? OR p.description LIKE ?
");
$count_stmt->execute(["%$search%", "%$search%"]);
$total_items = (int) $count_stmt->fetchColumn();

// Calculate total pages needed
$total_pages = max(1, (int) ceil($total_items / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;

// Step B: Fetch Paginated & Sorted Products
$stmt = $_db->prepare("
    SELECT p.*, 
           COALESCE(SUM(pv.stock), 0) AS total_stock
    FROM products p
    LEFT JOIN product_variants pv ON p.product_id = pv.product_id
    WHERE p.name LIKE :search OR p.description LIKE :search
    GROUP BY p.product_id
    ORDER BY {$sql_sort}
    LIMIT :limit OFFSET :offset
");

$search_param = "%$search%";
$stmt->bindValue(':search', $search_param, PDO::PARAM_STR);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();
?>

<!-- 4. HTML Search & Sort Bar Form Component -->
<div class="search-container" style="display: flex; gap: 15px; justify-content: center; align-items: center; flex-wrap: wrap; margin-bottom: 25px;">
    <form method="get" action="products.php" style="display: flex; gap: 10px; align-items: center;">
        
        <!-- Search Input -->
        <input type="text" name="search" placeholder="Search for water bottles..." 
               value="<?= encode($search) ?>">

        <!-- Sort Select Dropdown -->
        <select name="sort" onchange="this.form.submit()" style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest Arrivals</option>
            <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
            <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
            <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name: A to Z</option>
            <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name: Z to A</option>
        </select>

        <button type="submit">Search</button>

        <?php if ($search !== ''): ?>
            <a href="products.php" style="margin-left: 5px; color: #dc3545; text-decoration: none; font-size: 14px;">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Display Records Summary -->
<div style="text-align: center; color: #666; margin-bottom: 20px; font-size: 14px;">
    Showing <strong><?= count($products) ?></strong> of <strong><?= $total_items ?></strong> products | 
    Page <strong><?= $page ?></strong> of <strong><?= $total_pages ?></strong>
</div>

<?php
// 5. Product Cards Grid (Maintains Original HTML Structure)
echo "<div class='product-grid'>";
if (count($products) > 0) {
    foreach ($products as $row) {
        
        $total_stock = intval($row->total_stock);

        // Apply dimming style if total aggregated stock across all variants is 0
        $card_style = ($total_stock == 0) ? "style='opacity: 0.60; filter: grayscale(40%); transition: opacity 0.3s;'" : "";

        echo "<div class='product-card' {$card_style}>";
            // Product Link
            echo "<a href='product_detail.php?id={$row->product_id}' style='text-decoration: none; color: inherit; display: block;'>";
                echo "<img src='" . encode($row->image_url) . "' alt='" . encode($row->name) . "'>";
                echo "<h3>" . encode($row->name) . "</h3>";
            echo "</a>";
            
            echo "<p>" . encode($row->description) . "</p>";
            echo "<p>Price: RM" . number_format($row->price, 2) . "</p>";
            
            // Stock Level alerts based on calculated variant total
            if ($total_stock == 0) {
                echo "<p style='color: #dc3545; font-weight: bold; margin-bottom: 15px;'>Out of Stock</p>";
            } elseif ($total_stock < 5) {
                echo "<p style='margin-bottom: 15px;'>Total Stock: " . encode($total_stock) . " <span style='color: #dc3545; font-weight: bold; font-size: 13px; margin-left: 5px;'>⚠️ Only " . encode($total_stock) . " left!</span></p>";
            } else {
                echo "<p style='margin-bottom: 15px;'>Total Stock: " . encode($total_stock) . "</p>";
            }
            
            // Details action redirect link
            echo '<div style="margin-top: auto; padding-top: 5px;">';
                echo "<a href='product_detail.php?id={$row->product_id}' class='view-details-btn'>View Details</a>";
            echo '</div>';
            
        echo "</div>"; // Close product-card
    }
} else {
    echo "<div style='grid-column: 1 / -1; text-align: center; color: #777; padding: 40px 0;'>No products found matching your search.</div>";
}
echo "</div>";
?>

<!-- 6. Pagination Navigation Buttons -->
<?php if ($total_pages > 1): ?>
    <div style="display: flex; gap: 8px; justify-content: center; align-items: center; margin: 30px 0;">
        
        <!-- Previous Page Link -->
        <?php if ($page > 1): ?>
            <a href="products.php?search=<?= urlencode($search) ?>&sort=<?= $sort ?>&page=<?= $page - 1 ?>" 
               style="padding: 8px 14px; border: 1px solid #ccc; border-radius: 4px; text-decoration: none; color: #333;">
               &laquo; Prev
            </a>
        <?php endif; ?>

        <!-- Numbered Page Links -->
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="products.php?search=<?= urlencode($search) ?>&sort=<?= $sort ?>&page=<?= $i ?>" 
               style="padding: 8px 14px; border: 1px solid #ccc; border-radius: 4px; text-decoration: none; <?= ($i === $page) ? 'background: #111; color: #fff; font-weight: bold;' : 'color: #333;' ?>">
               <?= $i ?>
            </a>
        <?php endfor; ?>

        <!-- Next Page Link -->
        <?php if ($page < $total_pages): ?>
            <a href="products.php?search=<?= urlencode($search) ?>&sort=<?= $sort ?>&page=<?= $page + 1 ?>" 
               style="padding: 8px 14px; border: 1px solid #ccc; border-radius: 4px; text-decoration: none; color: #333;">
               Next &raquo;
            </a>
        <?php endif; ?>

    </div>
<?php endif; ?>

<!-- 7. Cart Confirmation Modal Structure -->
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

<?php
// 8. Append structural trailing closures and footer elements
include '_foot.php'; 
?>