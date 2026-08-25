<?php

/*
    Archives one product (?id=) or several
    (?ids[]=...) - sets products.status to
    'archived' so the storefront can hide them,
    without touching any data. Reversible via
    product_restore.php.
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
        header('Location: admin_products.php?view=active');
        exit;
    }

} else {

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        header('Location: admin_products.php?view=active');
        exit;
    }

    $product_ids = [(int) $_GET['id']];
}

$placeholders = implode(',', array_fill(0, count($product_ids), '?'));

$stmt = $_db->prepare("
    UPDATE products
    SET status = 'archived'
    WHERE product_id IN ($placeholders)
");

$stmt->execute($product_ids);

header(
    'Location: admin_products.php?view=active&archived='
    . count($product_ids)
);

exit;