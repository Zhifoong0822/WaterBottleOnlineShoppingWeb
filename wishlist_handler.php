<?php
require_once '_base.php';

header('Content-Type: application/json');

// User must be logged in
if (!isset($_SESSION['users'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Please login first.'
    ]);
    exit;
}

// Get current logged-in user
$user_id = (int) $_SESSION['users']->user_id;

// Get product data sent from AJAX / form
$product_id = (int) ($_POST['product_id'] ?? 0);
$variant_id = (int) ($_POST['variant_id'] ?? 0);

// Validate product and variant
if ($product_id <= 0 || $variant_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid product.'
    ]);
    exit;
}

// Make sure the product variant really exists
$stmt = $_db->prepare("
    SELECT variant_id
    FROM product_variants
    WHERE variant_id = ?
      AND product_id = ?
    LIMIT 1
");

$stmt->execute([
    $variant_id,
    $product_id
]);

$variant_exists = $stmt->fetch();

if (!$variant_exists) {
    echo json_encode([
        'success' => false,
        'message' => 'Product variant not found.'
    ]);
    exit;
}

try {

    // Check whether the item is already in wishlist
    $stmt = $_db->prepare("
        SELECT wishlist_id
        FROM wishlist
        WHERE user_id = ?
          AND product_id = ?
          AND variant_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $user_id,
        $product_id,
        $variant_id
    ]);

    $wishlist_item = $stmt->fetch();

    // Already in wishlist -> remove it
    if ($wishlist_item) {

        $stmt = $_db->prepare("
            DELETE FROM wishlist
            WHERE user_id = ?
              AND product_id = ?
              AND variant_id = ?
        ");

        $stmt->execute([
            $user_id,
            $product_id,
            $variant_id
        ]);

        echo json_encode([
            'success' => true,
            'action' => 'removed',
            'wishlisted' => false,
            'message' => 'Removed from wishlist.'
        ]);

        exit;
    }

    // Not in wishlist -> add it
    $stmt = $_db->prepare("
        INSERT INTO wishlist (
            user_id,
            product_id,
            variant_id
        )
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $user_id,
        $product_id,
        $variant_id
    ]);

    echo json_encode([
        'success' => true,
        'action' => 'added',
        'wishlisted' => true,
        'message' => 'Added to wishlist.'
    ]);

} catch (PDOException $e) {

    echo json_encode([
        'success' => false,
        'message' => 'Unable to update wishlist.'
    ]);
}

?>