<?php

// Check individual page access for Staff and Supervisor
if (isset($_SESSION['users']) && isset($_db)) {

    $current_user_id = (int) ($_SESSION['users']->user_id ?? 0);
    $current_role = $_SESSION['users']->role ?? '';

    // Admin has full access
    if ($current_role === 'admin') {
        return;
    }

    // Check Staff and Supervisor only
    if (in_array($current_role, ['Staff', 'Supervisor'], true)) {

        $current_page = basename($_SERVER['SCRIPT_NAME']);

        // Pages always allowed
        $always_allowed_pages = ['login.php', 'logout.php', 'profile.php'];

        if (in_array($current_page, $always_allowed_pages, true)) {
            return;
        }

        // Check page access for this account
        $stmt = $_db->prepare("SELECT COUNT(*) FROM user_permissions WHERE user_id = ? AND page_slug = ?");
        $stmt->execute([$current_user_id, $current_page]);

        $has_permission = (int) $stmt->fetchColumn() > 0;

        if (!$has_permission) {

            // Get first available page
            $stmt = $_db->prepare("SELECT page_slug FROM user_permissions WHERE user_id = ? ORDER BY permission_id ASC LIMIT 1");
            $stmt->execute([$current_user_id]);

            $first_allowed_page = $stmt->fetchColumn();

            if ($first_allowed_page) {

                // Manage Products page path
                if ($first_allowed_page === 'admin_products.php') {
                    redirect('pages/admin/admin_products.php');
                    exit;
                }

                // Member Listing page path
                if ($first_allowed_page === 'member_listing.php') {
                    redirect('pages/admin/member_listing.php');
                    exit;
                }

                redirect($first_allowed_page);
                exit;
            }

            temp('info', 'You do not have permission to access this page.');
            redirect('profile.php');
            exit;
        }
    }
}