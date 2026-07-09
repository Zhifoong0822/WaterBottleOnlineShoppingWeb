<?php
// 1. Load configuration utilities, session variables, and global $_db
require '_base.php'; 

// 2. Supply dynamic metadata tracking to _head.php template
$_title = "Products";

// 3. Inject standard layout structure, styling mappings, and navigation structures
include '_head.php'; 
?>

<?php
// 4. Local inline logic flow retrieving products from active data layers
$stmt = $_db->prepare("SELECT * FROM products");
$stmt->execute();
$products = $stmt->fetchAll(); // Grabs all records using class FETCH_OBJ pattern

echo "<div class='product-grid'>";
if (count($products) > 0) {
    foreach ($products as $row) {
        echo "<div class='product-card'>";
            // Using OOP standard property mapping matching your practical set templates
            echo "<img src='" . encode($row->image_url) . "' alt='" . encode($row->name) . "'>";
            echo "<h3>" . encode($row->name) . "</h3>";
            echo "<p>" . encode($row->description) . "</p>";
            echo "<p>Price: RM" . number_format($row->price, 2) . "</p>";
            echo "<p>Stock: " . encode($row->stock) . "</p>";
            echo "<button class='add-to-cart' data-product_id='{$row->product_id}'>Add to Cart</button>";
        echo "</div>";
    }
} else {
    echo "<div class='col-md-12'>No products found.</div>";
}
echo "</div>";
?>

<script src="../js/script.js"></script>

<?php
// 6. Append structural trailing closures and footer elements
include '_foot.php'; 
?>