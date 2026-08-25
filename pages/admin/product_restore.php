<?php

/*
    Restores one product (?id=) or several
    (?ids[]=...) - sets products.status back to
    'active' so it's visible to customers again.
*/

require_once '../../_base.php';
require_admin('../../products.php');

$is_batch = isset($_GET['ids']) && is_array($_GET['ids']);

if ($is_batch) {

    $product_ids = array_map('intval', $_GET['ids']);

    $product_ids = array_filter($product_ids, function ($id) {
        return $id > 0;
    });

    $product_ids = array_values(array_unique($product_ids));

    if (empty($product_ids)) {
        header('Location: admin_products.php?view=archived');
        exit;
    }

} else {

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        header('Location: admin_products.php?view=archived');
        exit;
    }

    $product_ids = [(int) $_GET['id']];
}

$placeholders = implode(',', array_fill(0, count($product_ids), '?'));

$stmt = $_db->prepare("
    UPDATE products
    SET status = 'active'
    WHERE product_id IN ($placeholders)
");

$stmt->execute($product_ids);

header(
    'Location: admin_products.php?view=active&restored='
    . count($product_ids)
);

exit;