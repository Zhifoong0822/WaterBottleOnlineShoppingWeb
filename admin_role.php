<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require '_base.php';

$_title = "Manage Roles";

// Guard: only the top-level 'admin' role can create/manage staff roles
if (!isset($_SESSION['users'])) {
    redirect('login.php');
    exit;
}
if ($_SESSION['users']->role !== 'admin') {
    redirect('products.php');
    exit;
}

// Reserved role names that can never be created/edited/deleted here,
// since they're hardcoded elsewhere in the app (login checks, nav, etc.)
$reserved_roles = ['admin', 'member'];

// --- CREATE a new role ---
if (is_post() && post('action') === 'create') {
    $role_name  = trim(post('role_name', ''));
    $description = trim(post('description', ''));

    if ($role_name === '') {
        $_err['role_name'] = "Role name is required.";
    } elseif (in_array(strtolower($role_name), $reserved_roles, true)) {
        $_err['role_name'] = "'$role_name' is reserved and can't be used as a custom role.";
    } else {
        try {
            $stmt = $_db->prepare("INSERT INTO roles (role_name, description) VALUES (?, ?)");
            $stmt->execute([$role_name, $description !== '' ? $description : null]);
            temp('info', "Role \"$role_name\" created.");
            redirect('admin_roles.php');
            exit;
        } catch (PDOException $e) {
            // Likely a duplicate role_name (UNIQUE constraint)
            $_err['role_name'] = "A role with that name already exists.";
        }
    }
}

// --- DELETE a role ---
if (is_post() && post('action') === 'delete') {
    $role_id = (int) post('role_id');

    // Look up the role name first so we can check for it being reserved/in-use
    $stmt = $_db->prepare("SELECT role_name FROM roles WHERE role_id = ?");
    $stmt->execute([$role_id]);
    $role_row = $stmt->fetch(PDO::FETCH_OBJ);

    if (!$role_row) {
        temp('info', "Role not found.");
    } else {
        // Block deletion if any user currently holds this role
        $stmt = $_db->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
        $stmt->execute([$role_row->role_name]);
        $in_use = (int) $stmt->fetchColumn();

        if ($in_use > 0) {
            temp('info', "Can't delete \"{$role_row->role_name}\" — $in_use staff member(s) still have this role. Reassign them first.");
        } else {
            $stmt = $_db->prepare("DELETE FROM roles WHERE role_id = ?");
            $stmt->execute([$role_id]);
            temp('info', "Role \"{$role_row->role_name}\" deleted.");
        }
    }

    redirect('admin_roles.php');
    exit;
}

// Fetch all custom roles, with a live count of how many staff hold each one
$roles = $_db->query("
    SELECT r.role_id, r.role_name, r.description, r.created_at,
           (SELECT COUNT(*) FROM users u WHERE u.role = r.role_name) AS staff_count
    FROM roles r
    ORDER BY r.role_name ASC
")->fetchAll(PDO::FETCH_OBJ);

include '_head.php';
?>
<!-- Adjust this to match wherever your shared admin stylesheet actually lives -->
<link rel="stylesheet" href="css/admin.css">

<div class="admin-container">
    <div class="page-header">
        <div class="page-title">
            <h1>Manage Roles</h1>
            <p>Create custom staff roles below Admin. "Admin" and "Member" are built-in and can't be edited here.</p>
        </div>
    </div>

    <div class="edit-card">
        <div class="edit-header">
            <h2>Add a New Role</h2>
        </div>

        <form method="post" action="admin_roles.php" class="edit-form">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label for="role_name">Role Name <span class="required">*</span></label>
                <input type="text" id="role_name" name="role_name" maxlength="50" required
                       value="<?= encode(post('role_name', '')) ?>">
                <?php if (!empty($_err['role_name'])): ?>
                    <span class="error"><?= encode($_err['role_name']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group full-width">
                <label for="description">Description</label>
                <textarea id="description" name="description" maxlength="255"><?= encode(post('description', '')) ?></textarea>
            </div>

            <div class="edit-footer" style="grid-column: 1 / -1;">
                <button type="submit" class="add-button">Create Role</button>
            </div>
        </form>
    </div>

    <div class="table-wrapper" style="margin-top: 30px;">
        <?php if (empty($roles)): ?>
            <p class="no-products">No custom roles yet. Create one above.</p>
        <?php else: ?>
            <table class="product-table">
                <thead>
                    <tr>
                        <th>Role Name</th>
                        <th>Description</th>
                        <th>Staff Assigned</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role): ?>
                        <tr>
                            <td><?= encode($role->role_name) ?></td>
                            <td><?= encode($role->description ?: '—') ?></td>
                            <td><?= (int) $role->staff_count ?></td>
                            <td><?= encode(date('M j, Y', strtotime($role->created_at))) ?></td>
                            <td class="action-buttons">
                                <form method="post" action="admin_roles.php"
                                      onsubmit="return confirm('Delete the role &quot;<?= encode(addslashes($role->role_name)) ?>&quot;?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="role_id" value="<?= (int) $role->role_id ?>">
                                    <button type="submit" class="btn-delete" style="border:none; cursor:pointer;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php
include '_foot.php';
?>