<?php
// 1. Load configuration utilities, session variables, and global $_db
require '_base.php'; 

// 2. Supply dynamic metadata tracking to _head.php template
$_title = "Products";

// 3. Inject standard layout structure, styling mappings, and navigation structures
include '_head.php'; 
?>

<!-- 4. HTML Search Bar Form Component (Basic Searching Requirement) -->
<div class="search-container" style="margin-bottom: 25px; text-align: center;">
    <form method="get" action="products.php">
        <input type="text" name="search" placeholder="Search for water bottles..." 
               value="<?= encode(req('search')) ?>" 
               style="padding: 8px 12px; width: 300px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;">
        <button type="submit" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">
            Search
        </button>
        <?php if (req('search') !== null && req('search') !== ''): ?>
            <a href="products.php" style="margin-left: 10px; color: #dc3545; text-decoration: none; font-size: 14px;">Clear</a>
        <?php endif; ?>
    </form>
</div>

<?php
// 5. Dynamic SQL Filtering using your class-standard query helper functions
$search = req('search');
$stmt = $_db->prepare("SELECT * FROM products WHERE name LIKE ? OR description LIKE ?");
$stmt->execute(["%$search%", "%$search%"]);
$products = $stmt->fetchAll(); // Grabs all records using class FETCH_OBJ pattern

echo "<div class='product-grid'>";
if (count($products) > 0) {
    foreach ($products as $row) {
        echo "<div class='product-card'>";
            // Product Detail Requirement: Wrap image and name inside an anchor tag linking to product_detail.php
            echo "<a href='product_detail.php?id={$row->product_id}' style='text-decoration: none; color: inherit;'>";
                echo "<img src='" . encode($row->image_url) . "' alt='" . encode($row->name) . "'>";
                echo "<h3>" . encode($row->name) . "</h3>";
            // Closing anchor tag correctly
            echo "</a>";
            
            echo "<p>" . encode($row->description) . "</p>";
            echo "<p>Price: RM" . number_format($row->price, 2) . "</p>";
            
            // Dynamic Inventory Stock Level Display & Alert System
            if ($row->stock == 0) {
                echo "<p style='color: #dc3545; font-weight: bold;'>Out of Stock</p>";
            } elseif ($row->stock < 5) {
                echo "<p>Stock: " . encode($row->stock) . " <span style='color: #dc3545; font-weight: bold; font-size: 13px; margin-left: 5px;'>⚠️ Only " . encode($row->stock) . " left!</span></p>";
            } else {
                echo "<p>Stock: " . encode($row->stock) . "</p>";
            }
            
            // Context-Aware Button Action Locking
            if ($row->stock == 0) {
                echo "<button class='add-to-cart' style='background: #ccc; cursor: not-allowed; color: #666;' disabled>Out of Stock</button>";
            } else {
                echo "<button class='add-to-cart' data-product_id='{$row->product_id}'>Add to Cart</button>";
            }
        echo "</div>";
    }
} else {
    echo "<div class='col-md-12' style='text-align: center; color: #777; margin: 20px 0;'>No products found matching your search.</div>";
}
echo "</div>";
?>


<?php
// 6. Append structural trailing closures and footer elements
include '_foot.php'; 
?>