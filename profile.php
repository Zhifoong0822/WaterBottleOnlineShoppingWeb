<?php

require_once '_base.php';

if (!isset($_SESSION['users'])) {
    redirect('login.php');
    exit;
}

$_title = "My Profile";
$user_id = $_SESSION['users']->user_id;
$current_role = $_SESSION['users']->role ?? '';

$is_admin = ($current_role === 'admin');
$is_staff_account = in_array($current_role, ['Staff', 'Supervisor'], true);
$is_member = ($current_role === 'member');

// Handle profile / password / staff account updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $form_type = $_POST['form_type'] ?? '';

    // Profile info update
    if ($form_type === 'profile') {

        $updated_name = trim($_POST['user-name'] ?? '');
        $updated_email = trim($_POST['user-email'] ?? '');
        $updated_profilepic = null;

        if ($updated_name === '') {
            temp('email_error', 'Name cannot be empty.');
            redirect('profile.php?tab=settings');
            exit;
        }

        // Validate email
        if (!filter_var($updated_email, FILTER_VALIDATE_EMAIL)) {
            temp('email_error', 'Please enter a valid email address.');
            redirect('profile.php?tab=settings');
            exit;
        }

        // Prevent duplicate email
        $stmt_check = $_db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
        $stmt_check->execute([$updated_email, $user_id]);

        if ($stmt_check->fetchColumn() > 0) {
            temp('email_error', 'This email is already in use by another account.');
            redirect('profile.php?tab=settings');
            exit;
        }

        // Upload profile photo
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {

            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $allowed_ext, true)) {
                temp('email_error', 'Invalid photo file type.');
                redirect('profile.php?tab=settings');
                exit;
            }

            $filename = $user_id . '_' . time() . '.' . $ext;
            $target_path = PROJECT_ROOT . PROFILE_IMAGE_DIR . $filename;

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_path)) {
                $updated_profilepic = $filename;
            }
        }

        // Update profile
        if ($updated_profilepic) {
            $stmt = $_db->prepare("UPDATE users SET username = ?, email = ?, profilepic = ? WHERE user_id = ?");
            $stmt->execute([$updated_name, $updated_email, $updated_profilepic, $user_id]);
        } else {
            $stmt = $_db->prepare("UPDATE users SET username = ?, email = ? WHERE user_id = ?");
            $stmt->execute([$updated_name, $updated_email, $user_id]);
        }

        // Update session
        $_SESSION['users']->username = $updated_name;
        $_SESSION['users']->email = $updated_email;

        if ($updated_profilepic) {
            $_SESSION['users']->profilepic = $updated_profilepic;
        }

        header("Location: profile.php?tab=settings");
        exit;
    }

    // Password update
    if ($form_type === 'password') {

        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Get current password
        $stmt = $_db->prepare("SELECT password FROM users WHERE user_id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            temp('password_error', 'User not found.');
            redirect('profile.php?tab=settings');
            exit;
        }

        // Verify current password
        if (!password_verify($current_password, $row['password'])) {
            temp('password_error', 'Current password is incorrect.');
            redirect('profile.php?tab=settings');
            exit;
        }

        // Validate new password
        if (strlen($new_password) < 8) {
            temp('password_error', 'New password must be at least 8 characters.');
            redirect('profile.php?tab=settings');
            exit;
        }

        if ($new_password !== $confirm_password) {
            temp('password_error', 'New password and confirmation do not match.');
            redirect('profile.php?tab=settings');
            exit;
        }

        if (password_verify($new_password, $row['password'])) {
            temp('password_error', 'New password must be different from the current password.');
            redirect('profile.php?tab=settings');
            exit;
        }

        // Update password
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $_db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->execute([$new_hash, $user_id]);

        temp('password_success', 'Password updated successfully.');
        header("Location: profile.php?tab=settings");
        exit;
    }

    // Create or update Staff / Supervisor account
    if ($form_type === 'save_staff_account') {

        if (!$is_admin) {
            redirect('profile.php');
            exit;
        }

        $allowed_role_names = ['Staff', 'Supervisor'];

        // Admin pages available for Staff / Supervisor
        $available_pages = [
            'admin_products.php' => 'Manage Products',
            'admin_orders.php' => 'Manage Orders',
            'member_listing.php' => 'Member Listing'
        ];

        $edit_user_id = (int) ($_POST['edit_user_id'] ?? 0);
        $role_name = $_POST['role_name'] ?? '';
        $selected_pages = $_POST['pages'] ?? [];
        $account_username = trim($_POST['username'] ?? '');
        $account_email = trim($_POST['user_email'] ?? '');
        $account_password = $_POST['user_password'] ?? '';
        $account_password_confirm = $_POST['user_password_confirm'] ?? '';

        $selected_pages = array_values(array_intersect($selected_pages, array_keys($available_pages)));
        $is_editing = $edit_user_id > 0;

        // Validate role
        if (!in_array($role_name, $allowed_role_names, true)) {
            temp('role_error', 'Please choose a valid role.');
            header("Location: profile.php?tab=roles");
            exit;
        }

        // Validate username
        if ($account_username === '') {
            temp('role_error', 'Username is required.');
            header("Location: profile.php?tab=roles");
            exit;
        }

        // Validate email
        if (!filter_var($account_email, FILTER_VALIDATE_EMAIL)) {
            temp('role_error', 'Enter a valid email address.');
            header("Location: profile.php?tab=roles");
            exit;
        }

        // Check duplicate username
        if ($is_editing) {
            $stmt = $_db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND user_id != ?");
            $stmt->execute([$account_username, $edit_user_id]);
        } else {
            $stmt = $_db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$account_username]);
        }

        if ((int) $stmt->fetchColumn() > 0) {
            temp('role_error', 'That username is already taken.');
            header("Location: profile.php?tab=roles");
            exit;
        }

        // Check duplicate email
        if ($is_editing) {
            $stmt = $_db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$account_email, $edit_user_id]);
        } else {
            $stmt = $_db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$account_email]);
        }

        if ((int) $stmt->fetchColumn() > 0) {
            temp('role_error', 'An account with that email already exists.');
            header("Location: profile.php?tab=roles");
            exit;
        }

        // New account password validation
        if (!$is_editing) {

            if (strlen($account_password) < 8) {
                temp('role_error', 'Password must be at least 8 characters.');
                header("Location: profile.php?tab=roles");
                exit;
            }

            if ($account_password !== $account_password_confirm) {
                temp('role_error', 'Passwords do not match.');
                header("Location: profile.php?tab=roles");
                exit;
            }
        }

        // Edit account password validation
        if ($is_editing && $account_password !== '') {

            if (strlen($account_password) < 8) {
                temp('role_error', 'Password must be at least 8 characters.');
                header("Location: profile.php?tab=roles&edit_user=" . $edit_user_id);
                exit;
            }

            if ($account_password !== $account_password_confirm) {
                temp('role_error', 'Passwords do not match.');
                header("Location: profile.php?tab=roles&edit_user=" . $edit_user_id);
                exit;
            }
        }

        try {

            $_db->beginTransaction();

            // Update existing account
            if ($is_editing) {

                $stmt = $_db->prepare("SELECT user_id FROM users WHERE user_id = ? AND role IN ('Staff', 'Supervisor') LIMIT 1");
                $stmt->execute([$edit_user_id]);
                $existing_user = $stmt->fetch(PDO::FETCH_OBJ);

                if (!$existing_user) {
                    throw new Exception('Staff account not found.');
                }

                // Update with new password
                if ($account_password !== '') {

                    $hashed = password_hash($account_password, PASSWORD_DEFAULT);

                    $stmt = $_db->prepare("UPDATE users SET username = ?, email = ?, password = ?, role = ? WHERE user_id = ?");
                    $stmt->execute([$account_username, $account_email, $hashed, $role_name, $edit_user_id]);

                } else {

                    // Update without changing password
                    $stmt = $_db->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE user_id = ?");
                    $stmt->execute([$account_username, $account_email, $role_name, $edit_user_id]);
                }

                $staff_user_id = $edit_user_id;

                // Remove only this user's page access
                $stmt = $_db->prepare("DELETE FROM user_permissions WHERE user_id = ?");
                $stmt->execute([$staff_user_id]);

            } else {

                // Create new account
                $hashed = password_hash($account_password, PASSWORD_DEFAULT);

                $stmt = $_db->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$account_username, $account_email, $hashed, $role_name]);

                $staff_user_id = (int) $_db->lastInsertId();
            }

            // Save page access for this account
            if (!empty($selected_pages)) {

                $stmt = $_db->prepare("INSERT INTO user_permissions (user_id, page_slug) VALUES (?, ?)");

                foreach ($selected_pages as $page_slug) {
                    $stmt->execute([$staff_user_id, $page_slug]);
                }
            }

            $_db->commit();

            temp('role_success', $is_editing ? 'Account updated successfully.' : 'Account created successfully.');

        } catch (Exception $e) {

            if ($_db->inTransaction()) {
                $_db->rollBack();
            }

            temp('role_error', 'Something went wrong saving the account.');
        }

        header("Location: profile.php?tab=roles");
        exit;
    }

    // Delete Staff / Supervisor account
    if ($form_type === 'delete_staff_account') {

        if (!$is_admin) {
            redirect('profile.php');
            exit;
        }

        $staff_user_id = (int) ($_POST['staff_user_id'] ?? 0);

        // Check account
        $stmt = $_db->prepare("SELECT user_id, username, role FROM users WHERE user_id = ? AND role IN ('Staff', 'Supervisor') LIMIT 1");
        $stmt->execute([$staff_user_id]);
        $staff_account = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$staff_account) {

            temp('role_error', 'Staff account not found.');

        } else {

            try {

                $_db->beginTransaction();

                // Delete page access
                $stmt = $_db->prepare("DELETE FROM user_permissions WHERE user_id = ?");
                $stmt->execute([$staff_user_id]);

                // Delete account
                $stmt = $_db->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$staff_user_id]);

                $_db->commit();

                temp('role_success', 'Account deleted successfully.');

            } catch (Exception $e) {

                if ($_db->inTransaction()) {
                    $_db->rollBack();
                }

                temp('role_error', 'Unable to delete the account.');
            }
        }

        header("Location: profile.php?tab=roles");
        exit;
    }

    // ============================================================
    // Store Management - Add Store
    // ============================================================
    if ($form_type === 'add_store') {

        if (!$is_admin) {
            redirect('profile.php');
            exit;
        }

        $store_name = trim($_POST['store_name'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $opening_hours = trim($_POST['opening_hours'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $map_query = trim($_POST['map_query'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if ($store_name === '' || $state === '' || $address === '' || $opening_hours === '' || $contact === '' || $map_query === '') {
            temp('store_error', 'Please fill in all required store fields.');
            header('Location: profile.php?tab=store');
            exit;
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        $store_image = null;

        if (isset($_FILES['store_image']) && $_FILES['store_image']['error'] === UPLOAD_ERR_OK) {

            $ext = strtolower(pathinfo($_FILES['store_image']['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $allowed_ext, true)) {
                temp('store_error', 'Invalid image type. Please upload JPG, JPEG, PNG, GIF or WEBP.');
                header('Location: profile.php?tab=store');
                exit;
            }

            if ((int) $_FILES['store_image']['size'] > 5 * 1024 * 1024) {
                temp('store_error', 'Store image must be 5MB or smaller.');
                header('Location: profile.php?tab=store');
                exit;
            }

            $filename = 'store_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $target_path = PROJECT_ROOT . STORE_IMAGE_DIR . $filename;

            if (!move_uploaded_file($_FILES['store_image']['tmp_name'], $target_path)) {
                temp('store_error', 'Unable to upload store picture.');
                header('Location: profile.php?tab=store');
                exit;
            }

            $store_image = $filename;
        }

        try {
            $stmt = $_db->prepare("\n                INSERT INTO stores\n                    (store_name, state, address, opening_hours, contact, map_query, store_image, status)\n                VALUES\n                    (?, ?, ?, ?, ?, ?, ?, ?)\n            ");

            $stmt->execute([
                $store_name,
                $state,
                $address,
                $opening_hours,
                $contact,
                $map_query,
                $store_image,
                $status
            ]);

            temp('store_success', 'Store added successfully.');

        } catch (Exception $e) {
            temp('store_error', 'Unable to add store.');
        }

        header('Location: profile.php?tab=store');
        exit;
    }

    // ============================================================
    // Store Management - Edit Store
    // ============================================================
    if ($form_type === 'edit_store') {

        if (!$is_admin) {
            redirect('profile.php');
            exit;
        }

        $store_id = (int) ($_POST['store_id'] ?? 0);
        $store_name = trim($_POST['store_name'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $opening_hours = trim($_POST['opening_hours'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $map_query = trim($_POST['map_query'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if ($store_id <= 0 || $store_name === '' || $state === '' || $address === '' || $opening_hours === '' || $contact === '' || $map_query === '') {
            temp('store_error', 'Please fill in all required store fields.');
            header('Location: profile.php?tab=store');
            exit;
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        $stmt = $_db->prepare("SELECT * FROM stores WHERE store_id = ? LIMIT 1");
        $stmt->execute([$store_id]);
        $existing_store = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$existing_store) {
            temp('store_error', 'Store not found.');
            header('Location: profile.php?tab=store');
            exit;
        }

        $store_image = $existing_store->store_image;

        if (isset($_FILES['store_image']) && $_FILES['store_image']['error'] === UPLOAD_ERR_OK) {

            $ext = strtolower(pathinfo($_FILES['store_image']['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $allowed_ext, true)) {
                temp('store_error', 'Invalid image type. Please upload JPG, JPEG, PNG, GIF or WEBP.');
                header('Location: profile.php?tab=store');
                exit;
            }

            if ((int) $_FILES['store_image']['size'] > 5 * 1024 * 1024) {
                temp('store_error', 'Store image must be 5MB or smaller.');
                header('Location: profile.php?tab=store');
                exit;
            }

            $filename = 'store_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $target_path = PROJECT_ROOT . STORE_IMAGE_DIR . $filename;

            if (!move_uploaded_file($_FILES['store_image']['tmp_name'], $target_path)) {
                temp('store_error', 'Unable to upload store picture.');
                header('Location: profile.php?tab=store');
                exit;
            }

            if (!empty($existing_store->store_image)) {
                $old_path = PROJECT_ROOT . STORE_IMAGE_DIR . $existing_store->store_image;
                if (is_file($old_path)) {
                    @unlink($old_path);
                }
            }

            $store_image = $filename;
        }

        try {
            $stmt = $_db->prepare("\n                UPDATE stores\n                SET store_name = ?, state = ?, address = ?, opening_hours = ?,\n                    contact = ?, map_query = ?, store_image = ?, status = ?\n                WHERE store_id = ?\n            ");

            $stmt->execute([
                $store_name,
                $state,
                $address,
                $opening_hours,
                $contact,
                $map_query,
                $store_image,
                $status,
                $store_id
            ]);

            temp('store_success', 'Store updated successfully.');

        } catch (Exception $e) {
            temp('store_error', 'Unable to update store.');
        }

        header('Location: profile.php?tab=store');
        exit;
    }

    // ============================================================
    // Store Management - Update Status
    // ============================================================
    if ($form_type === 'update_store_status') {

        if (!$is_admin) {
            redirect('profile.php');
            exit;
        }

        $store_id = (int) ($_POST['store_id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if ($store_id <= 0 || !in_array($status, ['active', 'inactive'], true)) {
            temp('store_error', 'Invalid store status.');
            header('Location: profile.php?tab=store');
            exit;
        }

        try {
            $stmt = $_db->prepare("UPDATE stores SET status = ? WHERE store_id = ?");
            $stmt->execute([$status, $store_id]);
            temp('store_success', 'Store status updated successfully.');
        } catch (Exception $e) {
            temp('store_error', 'Unable to update store status.');
        }

        header('Location: profile.php?tab=store');
        exit;
    }

    // ============================================================
    // Store Management - Delete Store
    // ============================================================
    if ($form_type === 'delete_store') {

        if (!$is_admin) {
            redirect('profile.php');
            exit;
        }

        $store_id = (int) ($_POST['store_id'] ?? 0);

        if ($store_id <= 0) {
            temp('store_error', 'Invalid store.');
            header('Location: profile.php?tab=store');
            exit;
        }

        $stmt = $_db->prepare("SELECT store_image FROM stores WHERE store_id = ? LIMIT 1");
        $stmt->execute([$store_id]);
        $store = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$store) {
            temp('store_error', 'Store not found.');
            header('Location: profile.php?tab=store');
            exit;
        }

        try {
            $stmt = $_db->prepare("DELETE FROM stores WHERE store_id = ?");
            $stmt->execute([$store_id]);

            if (!empty($store->store_image)) {
                $image_path = PROJECT_ROOT . PROFILE_IMAGE_DIR . $store->store_image;
                if (is_file($image_path)) {
                    @unlink($image_path);
                }
            }

            temp('store_success', 'Store deleted successfully.');
        } catch (Exception $e) {
            temp('store_error', 'Unable to delete store.');
        }

        header('Location: profile.php?tab=store');
        exit;
    }

}

// Get current user
$stmt = $_db->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch(PDO::FETCH_OBJ);
$saved_avatar = $current_user->profilepic ?? '';

// Get one value safely
function safe_scalar($db, $sql, $params = []) {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : 0;
    } catch (Exception $e) {
        return null;
    }
}

// Get rows safely
function safe_rows($db, $sql, $params = []) {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    } catch (Exception $e) {
        return [];
    }
}

// Roles tab data
if ($is_admin) {

    $available_pages = [
        'admin_products.php' => 'Manage Products',
        'admin_orders.php' => 'Manage Orders',
        'member_listing.php' => 'Member Listing'
    ];

    // Get Staff accounts
    $staff_accounts = safe_rows($_db, "SELECT user_id, username, email, role FROM users WHERE role = 'Staff' ORDER BY username ASC");

    // Get Supervisor accounts
    $supervisor_accounts = safe_rows($_db, "SELECT user_id, username, email, role FROM users WHERE role = 'Supervisor' ORDER BY username ASC");

    // Get page access for Staff accounts
    foreach ($staff_accounts as $account) {
        $perm_rows = safe_rows($_db, "SELECT page_slug FROM user_permissions WHERE user_id = ?", [$account->user_id]);
        $account->pages = array_map(fn($r) => $r->page_slug, $perm_rows);
    }

    // Get page access for Supervisor accounts
    foreach ($supervisor_accounts as $account) {
        $perm_rows = safe_rows($_db, "SELECT page_slug FROM user_permissions WHERE user_id = ?", [$account->user_id]);
        $account->pages = array_map(fn($r) => $r->page_slug, $perm_rows);
    }

    // Get selected account for editing
    $edit_user_id = (int) ($_GET['edit_user'] ?? 0);
    $edit_account = null;
    $edit_user_pages = [];

    if ($edit_user_id > 0) {

        $stmt = $_db->prepare("SELECT user_id, username, email, role FROM users WHERE user_id = ? AND role IN ('Staff', 'Supervisor') LIMIT 1");
        $stmt->execute([$edit_user_id]);
        $edit_account = $stmt->fetch(PDO::FETCH_OBJ);

        if ($edit_account) {
            $perm_rows = safe_rows($_db, "SELECT page_slug FROM user_permissions WHERE user_id = ?", [$edit_account->user_id]);
            $edit_user_pages = array_map(fn($r) => $r->page_slug, $perm_rows);
        }
    }
}


// Store tab data
$stores = [];

if ($is_admin) {
    $stores = safe_rows($_db, "SELECT * FROM stores ORDER BY created_at DESC, store_id DESC");
}

include '_head.php';
?>

<link rel="stylesheet" href="css/profile.css">
<link rel="stylesheet" href="css/admin_dashboard.css">

<div>

    <!-- Profile Header -->
    <div class="header-banner">

        <div class="user-meta-group">

            <div class="avatar-badge">

                <?php if (!empty($_SESSION['users']->profilepic)): ?>

                    <img src="<?= encode(PROFILE_IMAGE_DIR . $_SESSION['users']->profilepic) ?>" alt="Profile Picture" class="profile-image">

                <?php else: ?>

                    <?= strtoupper(substr($_SESSION['users']->username ?? '', 0, 1)) ?>

                <?php endif; ?>

            </div>

            <div class="user-details">

                <h1 class="user-name">
                    <?= encode($_SESSION['users']->username) ?>
                </h1>

                <p class="user-email">
                    <?= encode($_SESSION['users']->email) ?>
                </p>

            </div>

        </div>

    </div>


    <!-- Profile Navigation -->
    <nav class="nav-container">

        <?php if ($is_admin): ?>

            <button type="button" class="nav-item" data-tab="roles" onclick="switchTab('roles')">
                Roles
            </button>

            <button type="button" class="nav-item" data-tab="store" onclick="switchTab('store')">
                Store
            </button>

        <?php elseif ($is_member): ?>

            <button type="button" class="nav-item" data-tab="wishlist" onclick="switchTab('wishlist')">
                Wishlist
            </button>

        <?php endif; ?>

        <button type="button" class="nav-item" data-tab="settings" onclick="switchTab('settings')">
            Settings
        </button>

    </nav>

    <?php if ($is_admin): ?>

        <!-- Roles Tab -->
        <div id="roles" class="tab-content" style="display: none;">

            <h2>Manage Roles</h2>

            <!-- Add / Edit Account -->
            <form method="POST" action="" class="role-account-form">

                <input type="hidden" name="form_type" value="save_staff_account">
                <input type="hidden" name="edit_user_id" value="<?= $edit_account ? (int) $edit_account->user_id : 0 ?>">

                <div class="card-container">

                    <div class="card-header">

                        <h4>
                            <?= $edit_account ? 'Edit Account' : 'Add New Account' ?>
                        </h4>

                        <?php if ($edit_account): ?>

                            <a href="profile.php?tab=roles" class="role-btn role-btn-edit">
                                Cancel Edit
                            </a>

                        <?php endif; ?>

                    </div>


                    <div class="form-group">

                        <label>Role</label>

                        <select name="role_name" class="input-field" required>

                            <option value="">Select a role...</option>

                            <option value="Staff" <?= ($edit_account && $edit_account->role === 'Staff') ? 'selected' : '' ?>>
                                Staff
                            </option>

                            <option value="Supervisor" <?= ($edit_account && $edit_account->role === 'Supervisor') ? 'selected' : '' ?>>
                                Supervisor
                            </option>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>Page Access</label>

                        <?php foreach ($available_pages as $slug => $label): ?>

                            <label class="role-checkbox-row">

                                <input type="checkbox" name="pages[]" value="<?= encode($slug) ?>" <?= in_array($slug, $edit_user_pages, true) ? 'checked' : '' ?>>

                                <span><?= encode($label) ?></span>

                            </label>

                        <?php endforeach; ?>

                    </div>


                    <div class="form-group">

                        <label>Username</label>

                        <input type="text" name="username" class="input-field" maxlength="50" placeholder="e.g. staff_amy" value="<?= encode($edit_account ? $edit_account->username : '') ?>" required>

                    </div>


                    <div class="form-group">

                        <label>Email</label>

                        <input type="email" name="user_email" class="input-field" maxlength="100" placeholder="name@example.com" value="<?= encode($edit_account ? $edit_account->email : '') ?>" required>

                    </div>


                    <div class="form-group">

                        <label>Password</label>

                        <input type="password" name="user_password" class="input-field" maxlength="255" autocomplete="off" <?= !$edit_account ? 'required' : '' ?>>

                        <?php if ($edit_account): ?>

                            <small>
                                Leave blank to keep the current password.
                            </small>

                        <?php endif; ?>

                    </div>


                    <div class="form-group">

                        <label>Confirm Password</label>

                        <input type="password" name="user_password_confirm" class="input-field" maxlength="255" autocomplete="off" <?= !$edit_account ? 'required' : '' ?>>

                    </div>


                    <?php if (isset($_SESSION['temp_role_error'])): ?>

                        <span class="err">
                            <?= encode(temp('role_error')) ?>
                        </span>

                    <?php endif; ?>


                    <?php if (isset($_SESSION['temp_role_success'])): ?>

                        <span class="success">
                            <?= encode(temp('role_success')) ?>
                        </span>

                    <?php endif; ?>


                    <button type="submit" class="edit-profile-btn role-save-btn">
                        <?= $edit_account ? 'Update Account' : 'Create Account' ?>
                    </button>

                </div>

            </form>


            <!-- Existing Accounts -->
            <h3 class="overview-subheading">
                Existing Accounts
            </h3>


            <!-- Staff / Supervisor Tabs -->
            <div class="account-role-tabs">

                <button type="button" class="account-role-tab active" data-role-tab="staff" onclick="switchRoleAccountTab('staff')">
                    Staff
                </button>

                <button type="button" class="account-role-tab" data-role-tab="supervisor" onclick="switchRoleAccountTab('supervisor')">
                    Supervisor
                </button>

            </div>


            <!-- Staff Accounts -->
            <div id="staff-accounts" class="role-account-content">

                <?php if (empty($staff_accounts)): ?>

                    <p class="overview-empty">
                        No Staff accounts yet.
                    </p>

                <?php else: ?>

                    <table class="dash-table">

                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Page Access</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($staff_accounts as $account): ?>

                                <tr>

                                    <td><?= encode($account->username) ?></td>

                                    <td><?= encode($account->email) ?></td>

                                    <td>

                                        <?php if (empty($account->pages)): ?>

                                            <span class="no-access">
                                                No pages granted
                                            </span>

                                        <?php else: ?>

                                            <?php foreach ($account->pages as $page): ?>

                                                <span class="page-badge">
                                                    <?= encode($available_pages[$page] ?? $page) ?>
                                                </span>

                                            <?php endforeach; ?>

                                        <?php endif; ?>

                                    </td>

                                    <td class="role-actions-cell">

                                        <a href="profile.php?tab=roles&edit_user=<?= (int) $account->user_id ?>" class="role-btn role-btn-edit">
                                            Edit
                                        </a>

                                        <form method="POST" action="" class="account-delete-form" onsubmit="return confirm('Delete this Staff account?');">

                                            <input type="hidden" name="form_type" value="delete_staff_account">
                                            <input type="hidden" name="staff_user_id" value="<?= (int) $account->user_id ?>">

                                            <button type="submit" class="role-btn role-btn-delete">
                                                Delete
                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                <?php endif; ?>

            </div>


            <!-- Supervisor Accounts -->
            <div id="supervisor-accounts" class="role-account-content" hidden>

                <?php if (empty($supervisor_accounts)): ?>

                    <p class="overview-empty">
                        No Supervisor accounts yet.
                    </p>

                <?php else: ?>

                    <table class="dash-table">

                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Page Access</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($supervisor_accounts as $account): ?>

                                <tr>

                                    <td><?= encode($account->username) ?></td>

                                    <td><?= encode($account->email) ?></td>

                                    <td>

                                        <?php if (empty($account->pages)): ?>

                                            <span class="no-access">
                                                No pages granted
                                            </span>

                                        <?php else: ?>

                                            <?php foreach ($account->pages as $page): ?>

                                                <span class="page-badge">
                                                    <?= encode($available_pages[$page] ?? $page) ?>
                                                </span>

                                            <?php endforeach; ?>

                                        <?php endif; ?>

                                    </td>

                                    <td class="role-actions-cell">

                                        <a href="profile.php?tab=roles&edit_user=<?= (int) $account->user_id ?>" class="role-btn role-btn-edit">
                                            Edit
                                        </a>

                                        <form method="POST" action="" class="account-delete-form" onsubmit="return confirm('Delete this Supervisor account?');">

                                            <input type="hidden" name="form_type" value="delete_staff_account">
                                            <input type="hidden" name="staff_user_id" value="<?= (int) $account->user_id ?>">

                                            <button type="submit" class="role-btn role-btn-delete">
                                                Delete
                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                <?php endif; ?>

            </div>

        </div>



        <!-- Store Tab -->
        <div id="store" class="tab-content" style="display: none;">
            <div class="store-page-heading">
                <div>
                    <h2>Store Management</h2>
                    <p class="overview-empty">Add, update, activate, deactivate or delete SippyGo stores.</p>
                </div>
                <button type="button" class="edit-profile-btn" onclick="toggleAddStoreForm()">+ Add Store</button>
            </div>

            <?php if (isset($_SESSION['temp_store_error'])): ?>
                <span class="err"><?= encode(temp('store_error')) ?></span>
            <?php endif; ?>

            <?php if (isset($_SESSION['temp_store_success'])): ?>
                <span class="success"><?= encode(temp('store_success')) ?></span>
            <?php endif; ?>

            <!-- Add Store Form -->
            <form id="add-store-form" method="POST" action="" enctype="multipart/form-data" class="store-edit-panel" style="display: none;">
                <input type="hidden" name="form_type" value="add_store">

                <div class="store-panel-header">
                    <h4>Add New Store</h4>
                    <button type="button" class="store-close-btn" onclick="toggleAddStoreForm()">×</button>
                </div>

                <div class="store-form-grid">
                    <div class="form-group">
                        <label>Store Name</label>
                        <input type="text" name="store_name" class="input-field" maxlength="100" placeholder="e.g. SippyGo Sunway Pyramid" required>
                    </div>

                    <div class="form-group">
                        <label>State</label>
                        <select name="state" class="input-field" required>
                            <option value="">Select state...</option>
                            <?php foreach (['Kuala Lumpur','Selangor','Penang','Johor','Perak','Melaka','Negeri Sembilan','Pahang','Kedah','Perlis','Kelantan','Terengganu','Sabah','Sarawak'] as $state): ?>
                                <option value="<?= encode($state) ?>"><?= encode($state) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group store-form-row-gap">
                    <label>Address</label>
                    <input type="text" name="address" class="input-field" maxlength="255" placeholder="e.g. 3, Jalan PJS 11/15, Bandar Sunway, 47500 Subang Jaya" required>
                </div>

                <div class="store-form-grid">
                    <div class="form-group">
                        <label>Opening Hours</label>
                        <input type="text" name="opening_hours" class="input-field" maxlength="100" placeholder="e.g. 10:00 AM - 10:00 PM" required>
                    </div>

                    <div class="form-group">
                        <label>Contact</label>
                        <input type="text" name="contact" class="input-field" maxlength="30" placeholder="e.g. 03-2345 6789" required>
                    </div>
                </div>

                <div class="form-group store-form-row-gap">
                    <label>Google Maps Query</label>
                    <input type="text" name="map_query" class="input-field" maxlength="255" placeholder="e.g. Sunway Pyramid, Selangor" required>
                    <small>This becomes the destination for the member's Get Directions button.</small>
                </div>

                <div class="store-form-grid">
                    <div class="form-group">
                        <label>Store Picture</label>
                        <input type="file" name="store_image" accept=".jpg,.jpeg,.png,.gif,.webp">
                        <small>JPG, PNG, GIF or WEBP. Maximum 5MB.</small>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="input-field" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="store-edit-actions">
                    <button type="submit" class="edit-profile-btn">Add Store</button>
                    <button type="button" class="role-btn role-btn-edit" onclick="toggleAddStoreForm()">Cancel</button>
                </div>
            </form>

            <div class="store-list-heading">
                <h3 class="overview-subheading">Existing Stores</h3>
                <span class="store-count-badge"><?= count($stores) ?> store<?= count($stores) === 1 ? '' : 's' ?></span>
            </div>

            <?php if (empty($stores)): ?>
                <div class="store-empty-card">No stores have been added yet.</div>
            <?php else: ?>
                <div class="store-admin-list">
                    <?php foreach ($stores as $store): ?>

                        <div class="store-management-card">

                            <div class="store-management-image">
                                <?php if (!empty($store->store_image)): ?>
                                    <img src="<?= encode(STORE_IMAGE_DIR . $store->store_image) ?>" alt="<?= encode($store->store_name) ?>">
                                <?php else: ?>
                                    <div class="store-no-image">No Image</div>
                                <?php endif; ?>
                            </div>

                            <div class="store-management-info">

                                <div class="store-management-title-row">
                                    <div>
                                        <span class="store-state-label"><?= encode(strtoupper($store->state)) ?></span>
                                        <h3><?= encode($store->store_name) ?></h3>
                                    </div>

                                    <span class="store-status <?= $store->status === 'active' ? 'store-active' : 'store-inactive' ?>">
                                        <?= encode(ucfirst($store->status)) ?>
                                    </span>
                                </div>

                                <div class="store-management-details">
                                    <p><strong>Address:</strong> <?= encode($store->address) ?></p>
                                    <p><strong>Opening Hours:</strong> <?= encode($store->opening_hours) ?></p>
                                    <p><strong>Contact:</strong> <?= encode($store->contact) ?></p>
                                    <p><strong>Map Query:</strong> <?= encode($store->map_query) ?></p>
                                </div>

                                <div class="store-card-actions">

                                    <form method="POST" action="" class="store-inline-form">
                                        <input type="hidden" name="form_type" value="update_store_status">
                                        <input type="hidden" name="store_id" value="<?= (int) $store->store_id ?>">
                                        <select name="status" class="store-status-select" onchange="this.form.submit()">
                                            <option value="active" <?= $store->status === 'active' ? 'selected' : '' ?>>Active</option>
                                            <option value="inactive" <?= $store->status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </form>

                                    <button type="button" class="role-btn role-btn-edit" onclick="toggleStoreEdit(<?= (int) $store->store_id ?>)">Edit</button>

                                    <form method="POST" action="" class="store-inline-form" onsubmit="return confirm('Delete this store permanently?');">
                                        <input type="hidden" name="form_type" value="delete_store">
                                        <input type="hidden" name="store_id" value="<?= (int) $store->store_id ?>">
                                        <button type="submit" class="role-btn role-btn-delete">Delete</button>
                                    </form>

                                </div>
                            </div>
                        </div>

                        <!-- Edit Store -->
                        <div id="edit-store-<?= (int) $store->store_id ?>" class="store-edit-panel" style="display: none;">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <input type="hidden" name="form_type" value="edit_store">
                                <input type="hidden" name="store_id" value="<?= (int) $store->store_id ?>">

                                <div class="store-panel-header">
                                    <h4>Edit <?= encode($store->store_name) ?></h4>
                                    <button type="button" class="store-close-btn" onclick="toggleStoreEdit(<?= (int) $store->store_id ?>)">×</button>
                                </div>

                                <div class="store-form-grid">
                                    <div class="form-group">
                                        <label>Store Name</label>
                                        <input type="text" name="store_name" class="input-field" maxlength="100" value="<?= encode($store->store_name) ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label>State</label>
                                        <select name="state" class="input-field" required>
                                            <?php foreach (['Kuala Lumpur','Selangor','Penang','Johor','Perak','Melaka','Negeri Sembilan','Pahang','Kedah','Perlis','Kelantan','Terengganu','Sabah','Sarawak'] as $state): ?>
                                                <option value="<?= encode($state) ?>" <?= $store->state === $state ? 'selected' : '' ?>><?= encode($state) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group store-form-row-gap">
                                    <label>Address</label>
                                    <input type="text" name="address" class="input-field" maxlength="255" value="<?= encode($store->address) ?>" required>
                                </div>

                                <div class="store-form-grid">
                                    <div class="form-group">
                                        <label>Opening Hours</label>
                                        <input type="time" name="opening_hours" class="input-field" maxlength="100" value="<?= encode($store->opening_hours) ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label>Contact</label>
                                        <input type="text" name="contact" class="input-field" maxlength="30" value="<?= encode($store->contact) ?>" required>
                                    </div>
                                </div>

                                <div class="form-group store-form-row-gap">
                                    <label>Google Maps Query</label>
                                    <input type="text" name="map_query" class="input-field" maxlength="255" value="<?= encode($store->map_query) ?>" required>
                                </div>

                                <div class="store-form-grid">
                                    <div class="form-group">
                                        <label>Replace Store Picture</label>
                                        <input type="file" name="store_image" accept=".jpg,.jpeg,.png,.gif,.webp">
                                        <small>Leave empty to keep the existing image.</small>
                                    </div>

                                    <div class="form-group">
                                        <label>Status</label>
                                        <select name="status" class="input-field" required>
                                            <option value="active" <?= $store->status === 'active' ? 'selected' : '' ?>>Active</option>
                                            <option value="inactive" <?= $store->status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="store-edit-actions">
                                    <button type="submit" class="edit-profile-btn">Save Changes</button>
                                    <button type="button" class="role-btn role-btn-edit" onclick="toggleStoreEdit(<?= (int) $store->store_id ?>)">Cancel</button>
                                </div>
                            </form>
                        </div>

                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>

    <?php endif; ?>



    <!-- Settings Tab -->
    <div id="settings" class="tab-content" style="display: none;">

        <h2>Settings Dashboard</h2>

        <!-- Profile Form -->
        <form method="POST" action="" enctype="multipart/form-data">

            <input type="hidden" name="form_type" value="profile">

            <div class="card-container">

                <div class="card-header">

                    <h4>Profile</h4>

                    <div class="card-header-actions">

                        <input type="file" id="profilephoto-input" name="photo" accept="image/*" disabled>

                        <button type="button" id="edit-btn" class="edit-profile-btn" onclick="toggleEdit()">
                            Edit
                        </button>

                    </div>

                </div>


                <div class="form-group">

                    <label>Name</label>

                    <input type="text" id="input-name" name="user-name" class="input-field" value="<?= encode($_SESSION['users']->username) ?>" maxlength="20" disabled>

                </div>


                <div class="form-group">

                    <label>Email</label>

                    <input type="email" id="input-email" name="user-email" class="input-field" value="<?= encode($_SESSION['users']->email) ?>" disabled>

                    <?php if (isset($_SESSION['temp_email_error'])): ?>

                        <span class="err">
                            <?= encode(temp('email_error')) ?>
                        </span>

                    <?php endif; ?>

                </div>

            </div>

        </form>


        <!-- Password Form -->
        <form method="POST" action="">

            <input type="hidden" name="form_type" value="password">

            <div class="card-container">

                <div class="card-header">

                    <h4>Password</h4>

                    <button type="button" id="edit-password-btn" class="edit-profile-btn" onclick="togglePasswordEdit()">
                        Edit
                    </button>

                </div>


                <div class="form-group">

                    <label>Current Password</label>

                    <input type="password" id="input-current-password" name="current_password" class="input-field" disabled autocomplete="off">

                </div>


                <div class="form-group">

                    <label>New Password</label>

                    <input type="password" id="input-new-password" name="new_password" class="input-field" minlength="8" disabled autocomplete="off">

                </div>


                <div class="form-group">

                    <label>Confirm New Password</label>

                    <input type="password" id="input-confirm-password" name="confirm_password" class="input-field" minlength="8" disabled autocomplete="off">

                </div>


                <?php if (isset($_SESSION['temp_password_error'])): ?>

                    <span class="err">
                        <?= encode(temp('password_error')) ?>
                    </span>

                <?php endif; ?>


                <?php if (isset($_SESSION['temp_password_success'])): ?>

                    <span class="success">
                        <?= encode(temp('password_success')) ?>
                    </span>

                <?php endif; ?>

            </div>

        </form>

    </div>


<script>

// Edit profile
function toggleEdit() {

    const nameInput = document.getElementById('input-name');
    const emailInput = document.getElementById('input-email');
    const photoInput = document.getElementById('profilephoto-input');
    const editBtn = document.getElementById('edit-btn');
    const form = editBtn.closest('form');

    // Enter edit mode
    if (editBtn.textContent.trim() === 'Edit') {

        nameInput.removeAttribute('disabled');
        emailInput.removeAttribute('disabled');
        photoInput.removeAttribute('disabled');

        editBtn.textContent = 'Save';
        editBtn.style.backgroundColor = '#133932';

        nameInput.focus();

    } else {

        if (form) {
            nameInput.disabled = false;
            emailInput.disabled = false;
            form.submit();
        }
    }
}

// Edit password
function togglePasswordEdit() {

    const currentInput = document.getElementById('input-current-password');
    const newInput = document.getElementById('input-new-password');
    const confirmInput = document.getElementById('input-confirm-password');
    const editBtn = document.getElementById('edit-password-btn');
    const form = editBtn.closest('form');

    // Enter edit mode
    if (editBtn.textContent.trim() === 'Edit') {

        currentInput.removeAttribute('disabled');
        newInput.removeAttribute('disabled');
        confirmInput.removeAttribute('disabled');

        editBtn.textContent = 'Save';
        editBtn.style.backgroundColor = '#133932';

        currentInput.focus();

    } else {

        if (newInput.value.length < 8) {
            alert('New password must be at least 8 characters.');
            return;
        }

        if (newInput.value !== confirmInput.value) {
            alert('New password and confirmation do not match.');
            return;
        }

        if (form) {
            currentInput.disabled = false;
            newInput.disabled = false;
            confirmInput.disabled = false;
            form.submit();
        }
    }
}

// Switch profile tab
function switchTab(tabId) {

    document.querySelectorAll('.tab-content').forEach(function(content) {
        content.style.display = 'none';
    });

    const targetTab = document.getElementById(tabId);

    if (targetTab) {
        targetTab.style.display = 'block';
    }

    document.querySelectorAll('.nav-item').forEach(function(button) {
        button.classList.toggle('active', button.dataset.tab === tabId);
    });
}

// Switch Staff / Supervisor tab
function switchRoleAccountTab(role) {

    const staffContent = document.getElementById('staff-accounts');
    const supervisorContent = document.getElementById('supervisor-accounts');

    if (!staffContent || !supervisorContent) {
        return;
    }

    staffContent.hidden = role !== 'staff';
    supervisorContent.hidden = role !== 'supervisor';

    document.querySelectorAll('.account-role-tab').forEach(function(button) {
        button.classList.toggle('active', button.dataset.roleTab === role);
    });
}



// Store management
function toggleAddStoreForm() {
    const form = document.getElementById('add-store-form');
    if (!form) return;

    const opening = form.style.display === 'none' || form.style.display === '';
    form.style.display = opening ? 'block' : 'none';

    if (opening) {
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function toggleStoreEdit(storeId) {
    const panel = document.getElementById('edit-store-' + storeId);
    if (!panel) return;

    const opening = panel.style.display === 'none' || panel.style.display === '';

    document.querySelectorAll('.store-edit-panel[id^="edit-store-"]').forEach(function(item) {
        if (item !== panel) {
            item.style.display = 'none';
        }
    });

    panel.style.display = opening ? 'block' : 'none';

    if (opening) {
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

// Open selected main tab
<?php
if ($is_admin) {
    $default_tab = 'roles';
} elseif ($is_member) {
    $default_tab = 'wishlist';
} else {
    $default_tab = 'settings';
}
?>

const initialTab = <?= json_encode($_GET['tab'] ?? $default_tab) ?>;
switchTab(initialTab);

// Open selected Staff / Supervisor tab
const initialRoleAccountTab = <?= json_encode(isset($edit_account) && $edit_account && $edit_account->role === 'Supervisor' ? 'supervisor' : 'staff') ?>;

if (document.getElementById('staff-accounts')) {
    switchRoleAccountTab(initialRoleAccountTab);
}

</script>

</div>

<?php
include '_foot.php';
?>