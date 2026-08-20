<?php
function require_permission(string $page_slug) {
    global $_db;

    if (!isset($_SESSION['users'])) {
        redirect('login.php');
        exit;
    }

    // Admin bypasses all permission checks
    if ($_SESSION['users']->role === 'admin') {
        return;
    }

    $stmt = $_db->prepare("
        SELECT COUNT(*) 
        FROM role_permissions rp
        JOIN roles r ON r.role_id = rp.role_id
        WHERE r.role_name = ? AND rp.page_slug = ?
    ");
    $stmt->execute([$_SESSION['users']->role, $page_slug]);

    if ((int) $stmt->fetchColumn() === 0) {
        temp('info', "You don't have access to that page.");
        redirect('products.php');
        exit;
    }
}