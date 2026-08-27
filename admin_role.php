<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '_base.php';

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

// The set of pages an admin can grant access to when creating a role.
// Edit this list to match the real pages in your site.
$available_pages = [
    'products.php' => 'Manage Products',
    'orders.php'   => 'Manage Orders',
    'members.php'  => 'Member Listing',
    'cart.php'     => 'View Cart',
    'myorders.php' => 'My Orders',
];

// --- CREATE a new role (optionally with a staff login attached) ---
if (is_post() && post('action') === 'create') {
    $role_name   = trim(post('role_name', ''));
    $description = trim(post('description', ''));
    $selected_pages = post('allowed_pages', []); // array of checked page_slug values

    // Only keep pages that are actually in our known list (avoid arbitrary strings)
    $selected_pages = array_values(array_intersect($selected_pages, array_keys($available_pages)));

    // Optional staff-account fields
    $username            = trim(post('username', ''));
    $user_email          = trim(post('user_email', ''));
    $user_password       = post('user_password', '');
    $user_password_conf  = post('user_password_confirm', '');

    $creating_user = ($username !== '' || $user_email !== '' || $user_password !== '' || $user_password_conf !== '');

    if ($role_name === '') {
        $_err['role_name'] = "Role name is required.";
    } elseif (in_array(strtolower($role_name), $reserved_roles, true)) {
        $_err['role_name'] = "'$role_name' is reserved and can't be used as a custom role.";
    }

    if ($creating_user) {
        if ($username === '') {
            $_err['username'] = "Username is required to create a staff login.";
        } else {
            $stmt = $_db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ((int) $stmt->fetchColumn() > 0) {
                $_err['username'] = "That username is already taken.";
            }
        }

        if ($user_email === '') {
            $_err['user_email'] = "Email is required to create a staff login.";
        } elseif (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
            $_err['user_email'] = "Enter a valid email address.";
        } else {
            $stmt = $_db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$user_email]);
            if ((int) $stmt->fetchColumn() > 0) {
                $_err['user_email'] = "An account with that email already exists.";
            }
        }

        if ($user_password === '' || strlen($user_password) < 8) {
            $_err['user_password'] = "Password must be at least 8 characters.";
        } elseif ($user_password !== $user_password_conf) {
            $_err['user_password_confirm'] = "Passwords do not match.";
        }
    }

    if (empty($_err)) {
        try {
            $_db->beginTransaction();

            $stmt = $_db->prepare(
                "INSERT INTO roles (role_name, description) VALUES (?, ?)"
            );
            $stmt->execute([$role_name, $description !== '' ? $description : null]);
            $new_role_id = (int) $_db->lastInsertId();

            if (!empty($selected_pages)) {
                $stmt = $_db->prepare(
                    "INSERT INTO role_permissions (role_id, page_slug) VALUES (?, ?)"
                );
                foreach ($selected_pages as $page_slug) {
                    $stmt->execute([$new_role_id, $page_slug]);
                }
            }

            if ($creating_user) {
                $hashed = password_hash($user_password, PASSWORD_DEFAULT);

                // NOTE: users.role must be VARCHAR (not ENUM('admin','member'))
                // for a custom role name to be stored here.
                $stmt = $_db->prepare(
                    "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([$username, $user_email, $hashed, $role_name]);
            }

            $_db->commit();

            $page_count = count($selected_pages);
            $msg = "\"$role_name\" role saved with $page_count page(s) of access.";
            if ($creating_user) {
                $msg .= " Login created for $user_email.";
            }
            temp('info', $msg);
            redirect('profile.php?tab=roles');
            exit;

        } catch (PDOException $e) {
            $_db->rollBack();
            $_err['role_name'] = "A role with that name already exists, or a database error occurred.";
        }
    }
}

// --- DELETE a role ---
if (is_post() && post('action') === 'delete') {
    $role_id = (int) post('role_id');

    $stmt = $_db->prepare("SELECT role_name FROM roles WHERE role_id = ?");
    $stmt->execute([$role_id]);
    $role_row = $stmt->fetch(PDO::FETCH_OBJ);

    if (!$role_row) {
        temp('info', "Role not found.");
    } else {
        $stmt = $_db->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
        $stmt->execute([$role_row->role_name]);
        $in_use = (int) $stmt->fetchColumn();

        if ($in_use > 0) {
            temp('info', "Can't delete \"{$role_row->role_name}\" — $in_use staff member(s) still have this role. Reassign them first.");
        } else {
            // role_permissions rows are removed automatically via ON DELETE CASCADE
            $stmt = $_db->prepare("DELETE FROM roles WHERE role_id = ?");
            $stmt->execute([$role_id]);
            temp('info', "Role \"{$role_row->role_name}\" deleted.");
        }
    }

    redirect('profile.php?tab=roles');
    exit;
}

// Fetch all custom roles, with a live count of staff and their permitted pages
$roles = $_db->query("
    SELECT r.role_id, r.role_name, r.description, r.created_at,
           (SELECT COUNT(*) FROM users u WHERE u.role = r.role_name) AS staff_count,
           (SELECT GROUP_CONCAT(rp.page_slug SEPARATOR '||')
              FROM role_permissions rp WHERE rp.role_id = r.role_id) AS pages
    FROM roles r
    ORDER BY r.role_name ASC
")->fetchAll(PDO::FETCH_OBJ);

$checked_pages = post('allowed_pages', []);
?>

<!-- ================= ADD A NEW ROLE ================= -->
<div class="card-container">
    <div class="card-header">
        <h4>Add a New Role</h4>
    </div>

    <form method="post" action="admin_role.php" class="personal-info-card" style="max-width:none;">
        <input type="hidden" name="action" value="create">

        <div class="form-group">
            <label for="role_name">Role Name</label>
            <input type="text" id="role_name" name="role_name" maxlength="50" required
                   value="<?= encode(post('role_name', '')) ?>" placeholder="e.g. Staff">
            <?php if (!empty($_err['role_name'])): ?>
                <span class="error"><?= encode($_err['role_name']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="description">Description (optional)</label>
            <input type="text" id="description" name="description" maxlength="255"
                   value="<?= encode(post('description', '')) ?>">
        </div>

        <div class="form-group">
            <label>Page Access</label>
            <?php foreach ($available_pages as $slug => $label): ?>
                <label class="role-checkbox-row">
                    <input type="checkbox" name="allowed_pages[]" value="<?= encode($slug) ?>"
                           <?= in_array($slug, $checked_pages, true) ? 'checked' : '' ?>>
                    <?= encode(strtoupper($label)) ?>
                </label>
            <?php endforeach; ?>
        </div>

        <!-- Staff login created together with the role -->
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" maxlength="50"
                   value="<?= encode(post('username', '')) ?>" placeholder="Staff login username">
            <?php if (!empty($_err['username'])): ?>
                <span class="error"><?= encode($_err['username']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="user_email">Email</label>
            <input type="email" id="user_email" name="user_email" maxlength="100"
                   value="<?= encode(post('user_email', '')) ?>" placeholder="name@example.com">
            <?php if (!empty($_err['user_email'])): ?>
                <span class="error"><?= encode($_err['user_email']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="user_password">Password</label>
            <input type="password" id="user_password" name="user_password" maxlength="255">
            <?php if (!empty($_err['user_password'])): ?>
                <span class="error"><?= encode($_err['user_password']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="user_password_confirm">Confirm Password</label>
            <input type="password" id="user_password_confirm" name="user_password_confirm" maxlength="255">
            <?php if (!empty($_err['user_password_confirm'])): ?>
                <span class="error"><?= encode($_err['user_password_confirm']) ?></span>
            <?php endif; ?>
        </div>

        <div class="button-group" style="justify-content:flex-end;">
            <button type="submit" class="btn-save" style="flex:none; padding:12px 24px;">Save Role</button>
        </div>
    </form>
</div>

<!-- ================= EXISTING ROLES ================= -->
<h3 class="overview-subheading">Existing Roles</h3>

<?php if (empty($roles)): ?>
    <p class="overview-empty">No custom roles yet. Create one above.</p>
<?php else: ?>
    <table class="dash-table">
        <thead>
            <tr>
                <th>Role Name</th>
                <th>Page Access</th>
                <th>Staff Assigned</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($roles as $role): ?>
                <tr>
                    <td><?= encode($role->role_name) ?></td>
                    <td>
                        <?php if (!empty($role->pages)): ?>
                            <?php foreach (explode('||', $role->pages) as $slug): ?>
                                <span class="page-badge"><?= encode($available_pages[$slug] ?? $slug) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="no-access">No restriction</span>
                        <?php endif; ?>
                    </td>
                    <td class="staff-count-cell"><?= (int) $role->staff_count ?></td>
                    <td class="role-actions-cell">
                        <button type="button" class="role-btn role-btn-edit">Edit</button>
                        <form method="post" action="admin_role.php" style="display:inline;"
                              onsubmit="return confirm('Delete the role &quot;<?= encode(addslashes($role->role_name)) ?>&quot;?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="role_id" value="<?= (int) $role->role_id ?>">
                            <button type="submit" class="role-btn role-btn-delete">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>