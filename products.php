<?php
// 1. Load configuration utilities, session variables, and global $_db
require '_base.php'; 

// 2. Supply dynamic metadata tracking to _head.php template
$_title = "Products";

// 3. Inject standard layout structure, styling mappings, and navigation structures
include '_head.php'; 
?>

<!-- 4. HTML Search Bar Form Component -->
<div class="search-container">
    <form method="get" action="products.php">
        <input type="text" name="search" placeholder="Search for water bottles..." 
               value="<?= encode(req('search')) ?>">
        <button type="submit">Search</button>
        <?php if (req('search') !== null && req('search') !== ''): ?>
            <a href="products.php" style="margin-left: 10px; color: #dc3545; text-decoration: none; font-size: 14px;">Clear</a>
        <?php endif; ?>
    </form>
</div>

<?php
// 5. Dynamic SQL Filtering
$search = req('search');
$stmt = $_db->prepare("SELECT * FROM products WHERE name LIKE ? OR description LIKE ?");
$stmt->execute(["%$search%", "%$search%"]);
$products = $stmt->fetchAll();

echo "<div class='product-grid'>";
if (count($products) > 0) {
    foreach ($products as $row) {
        echo "<div class='product-card'>";
            // Product Link
            echo "<a href='product_detail.php?id={$row->product_id}' style='text-decoration: none; color: inherit; display: block;'>";
                echo "<img src='" . encode($row->image_url) . "' alt='" . encode($row->name) . "'>";
                echo "<h3>" . encode($row->name) . "</h3>";
            echo "</a>";
            
            echo "<p>" . encode($row->description) . "</p>";
            echo "<p>Price: RM" . number_format($row->price, 2) . "</p>";
            
            // Stock Level alerts
            if ($row->stock == 0) {
                echo "<p style='color: #dc3545; font-weight: bold; margin-bottom: 15px;'>Out of Stock</p>";
            } elseif ($row->stock < 5) {
                echo "<p style='margin-bottom: 15px;'>Stock: " . encode($row->stock) . " <span style='color: #dc3545; font-weight: bold; font-size: 13px; margin-left: 5px;'>⚠️ Only " . encode($row->stock) . " left!</span></p>";
            } else {
                echo "<p style='margin-bottom: 15px;'>Stock: " . encode($row->stock) . "</p>";
            }
            
            // Replaced the internal card "Add to Cart" block with a dedicated details action redirect link
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

<!-- 6. Cart Confirmation Modal Structure -->
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
// 7. Append structural trailing closures and footer elements
include '_foot.php'; 
?>