<?php
require '_base.php';

if (!isset($_SESSION['users'])) {
    redirect('login.php');
    exit;
}

$_title = "My Profile";
$user_id = $_SESSION['users']->user_id;

// --- PHP BACKEND: Handle Profile / Password Updates ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $form_type = $_POST['form_type'] ?? '';

    // =====================================================
    // 1. PROFILE INFO UPDATE (name / email / photo)
    // =====================================================
    if ($form_type === 'profile') {

        $updated_name = trim($_POST['user-name'] ?? '');
        $updated_email = trim($_POST['user-email'] ?? '');
        $updated_profilepic = null;

        if ($updated_name === '') {
            temp('email_error', 'Name cannot be empty.');
            redirect('profile.php');
            exit;
        }

        // Validate email format
        if (!filter_var($updated_email, FILTER_VALIDATE_EMAIL)) {
            temp('email_error', 'Please enter a valid email address.');
            redirect('profile.php');
            exit;
        }

        // Prevent duplicate emails belonging to other accounts
        $stmt_check = $_db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
        $stmt_check->execute([$updated_email, $user_id]);
        if ($stmt_check->fetchColumn() > 0) {
            temp('email_error', 'This email is already in use by another account.');
            redirect('profile.php');
            exit;
        }

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
            $folder = __DIR__ . '/update/profile';
            if (!is_dir($folder)) {
                mkdir($folder, 0777, true);
            }

            // Basic upload safety: keep original extension only, generate our own filename
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $allowed_ext, true)) {
                temp('email_error', 'Invalid photo file type.');
                redirect('profile.php');
                exit;
            }

            $filename = $user_id . '_' . time() . '.' . $ext;
            $target_path = $folder . '/' . $filename;

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_path)) {
                $updated_profilepic = 'update/profile/' . $filename;
            } else {
                $updated_profilepic = null;
                error_log('Profile photo upload failed for user ' . $user_id);
            }
        }

        if ($updated_profilepic) {
            $stmt = $_db->prepare(
                "UPDATE users 
                 SET username = ?, email = ?, profilepic = ?
                 WHERE user_id = ?"
            );
            $stmt->execute([$updated_name, $updated_email, $updated_profilepic, $user_id]);
        } else {
            $stmt = $_db->prepare(
                "UPDATE users 
                 SET username = ?, email = ?
                 WHERE user_id = ?"
            );
            $stmt->execute([$updated_name, $updated_email, $user_id]);
        }

        // Update session so it reflects immediately without re-login
        $_SESSION['users']->username = $updated_name;
        $_SESSION['users']->email = $updated_email;

        if ($updated_profilepic) {
            $_SESSION['users']->profilepic = $updated_profilepic;
        }

        header("Location: profile.php");
        exit;
    }

    // =====================================================
    // 2. PASSWORD UPDATE
    // =====================================================
    if ($form_type === 'password') {

        $current_password = $_POST['current_password'] ?? '';
        $new_password     = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Re-fetch the hash from DB — never trust session data for this
        $stmt = $_db->prepare("SELECT password FROM users WHERE user_id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            temp('password_error', 'User not found.');
            redirect('profile.php');
            exit;
        }

        // Step 1: verify current password matches DB
        if (!password_verify($current_password, $row['password'])) {
            temp('password_error', 'Current password is incorrect.');
            redirect('profile.php');
            exit;
        }

        // Step 2: validate the new password
        if (strlen($new_password) < 8) {
            temp('password_error', 'New password must be at least 8 characters.');
            redirect('profile.php');
            exit;
        }

        if ($new_password !== $confirm_password) {
            temp('password_error', 'New password and confirmation do not match.');
            redirect('profile.php');
            exit;
        }

        if (password_verify($new_password, $row['password'])) {
            temp('password_error', 'New password must be different from the current password.');
            redirect('profile.php');
            exit;
        }

        // Step 3: hash + update
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);

        $update = $_db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $update->execute([$new_hash, $user_id]);

        temp('password_success', 'Password updated successfully.');
        header("Location: profile.php");
        exit;
    }
}

// Fetch current user details
$stmt = $_db->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch(PDO::FETCH_OBJ);
$saved_avatar = $current_user->profilepic ?? '';

// --- OVERVIEW TAB DATA ---
// ASSUMPTIONS: orders(order_id, user_id, status, total, created_at)
// Adjust column/table names below if yours differ.
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

$is_admin = ($_SESSION['users']->role === 'admin');

if ($is_admin) {
    // Site-wide stats for admins
    $overview_total_orders   = safe_scalar($_db, "SELECT COUNT(*) FROM orders");
    $overview_pending_orders = safe_scalar($_db, "SELECT COUNT(*) FROM orders WHERE status = 'pending'");
    $overview_total_revenue  = safe_scalar($_db, "SELECT SUM(total) FROM orders WHERE status != 'cancelled'");
    $overview_total_users    = safe_scalar($_db, "SELECT COUNT(*) FROM users WHERE role = 'member'");
} else {
    // Personal stats for a regular member
    $overview_my_orders = safe_scalar($_db, "SELECT COUNT(*) FROM orders WHERE user_id = ?", [$user_id]);
    $overview_my_spend  = safe_scalar($_db, "SELECT SUM(total) FROM orders WHERE user_id = ? AND status != 'cancelled'", [$user_id]);
}

include '_head.php';
?>
<link rel="stylesheet" href="css/profile.css">
<link rel="stylesheet" href="css/admim.css">

    <!-- Mockup Header Section Matching Image Component -->
    <div class="header-banner">
        <div class="user-meta-group">
            <div class="avatar-badge">
            <?php if (!empty($_SESSION['users']->profilepic)): ?>
                <img 
                    src="<?= encode($_SESSION['users']->profilepic) ?>" 
                    alt="Profile Picture"
                    class="profile-image">

            <?php else: ?>
                <?= strtoupper(substr($_SESSION['users']->username ?? '', 0, 1)) ?>
            <?php endif; ?>
            </div>
            <div class="user-details">
                <h1 class="user-name"><?= encode($_SESSION['users']->username) ?></h1>
                <p class="user-email"><?= encode($_SESSION['users']->email) ?></p>
            </div>
        </div>
    </div>

<nav class="nav-container">
  <button onclick="switchTab('overview')">Overview</button>
  <button onclick="switchTab('wishlist')">Wishlist</button>
  <button onclick="switchTab('settings')">Settings</button>
</nav>

<!-- Overview Tab Content -->
<div id="overview" class="tab-content">
    <?php if ($is_admin): ?>
        <h2>Store Overview</h2>
        <div class="overview-stats">
            <div class="overview-card">
                <span class="overview-label">Total Orders</span>
                <span class="overview-value"><?= $overview_total_orders !== null ? (int) $overview_total_orders : '—' ?></span>
            </div>
            <div class="overview-card">
                <span class="overview-label">Pending Orders</span>
                <span class="overview-value"><?= $overview_pending_orders !== null ? (int) $overview_pending_orders : '—' ?></span>
            </div>
            <div class="overview-card">
                <span class="overview-label">Total Revenue</span>
                <span class="overview-value">
                    <?= $overview_total_revenue !== null ? '$' . number_format((float) $overview_total_revenue, 2) : '—' ?>
                </span>
            </div>
            <div class="overview-card">
                <span class="overview-label">Registered Customers</span>
                <span class="overview-value"><?= $overview_total_users !== null ? (int) $overview_total_users : '—' ?></span>
            </div>
        </div>
        <p class="overview-link"><a href="admin_orders.php">View all orders →</a></p>
    <?php else: ?>
        <h2>Your Overview</h2>
        <div class="overview-stats">
            <div class="overview-card">
                <span class="overview-label">Orders Placed</span>
                <span class="overview-value"><?= $overview_my_orders !== null ? (int) $overview_my_orders : '—' ?></span>
            </div>
            <div class="overview-card">
                <span class="overview-label">Total Spent</span>
                <span class="overview-value">
                    <?= $overview_my_spend !== null ? '$' . number_format((float) $overview_my_spend, 2) : '—' ?>
                </span>
            </div>
        </div>
        <p class="overview-link"><a href="order_history.php">View your order history →</a></p>
    <?php endif; ?>
</div>

<!-- Wishlist Tab Content -->
<div id="wishlist" class="tab-content" style="display: none;">
    <h2>Wishlist</h2>
    <p class="overview-empty">Your saved items will show up here.</p>
</div>

<!-- Settings Tab Content -->
<div id="settings" class="tab-content" style="display: none;">
    <h2>Settings Dashboard</h2>

    <!-- ==================== PROFILE INFO FORM ==================== -->
    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="form_type" value="profile">
        <div class="card-container">
            <div class="card-header">
                <h4>Profile</h4>
                <!-- Edit -->
                <input type="file" id="profilephoto-input" name="photo" accept="image/*" disabled>
                <button type="button" id="edit-btn" class="edit-profile-btn" onclick="toggleEdit()">Edit</button>
            </div>

            <div class="form-group">
                <label>Name</label>
                <input type="text" id="input-name" name="user-name" class="input-field" value="<?= encode($_SESSION['users']->username) ?>" maxlength="20" disabled>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" id="input-email" name="user-email" class="input-field" value="<?= encode($_SESSION['users']->email) ?>" disabled>
                <?php if (isset($_SESSION['temp_email_error'])): ?>
                    <span class="err"><?= encode(temp('email_error')) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <!-- ==================== PASSWORD FORM ==================== -->
    <form method="POST" action="">
        <input type="hidden" name="form_type" value="password">
        <div class="card-container">
            <div class="card-header">
                <h4>Password</h4>
                <button type="button" id="edit-password-btn" class="edit-profile-btn" onclick="togglePasswordEdit()">Edit</button>
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
                <span class="err"><?= encode(temp('password_error')) ?></span>
            <?php endif; ?>

            <?php if (isset($_SESSION['temp_password_success'])): ?>
                <span class="success"><?= encode(temp('password_success')) ?></span>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
function toggleEdit() {
  const nameInput = document.getElementById('input-name');
  const emailInput = document.getElementById('input-email');
  const photoInput = document.getElementById('profilephoto-input');
  const editBtn = document.getElementById('edit-btn');
  const form = editBtn.closest('form'); 

  // Enter edit mode
  if (editBtn.textContent.trim() === 'Edit') {
    
    // allow editing
    nameInput.removeAttribute('disabled');
    emailInput.removeAttribute('disabled');
    photoInput.removeAttribute('disabled');
    
    // edit button change to save button
    editBtn.textContent = 'Save';
    editBtn.style.backgroundColor = '#133932'; 
    
    // it focus the name input column (default)
    nameInput.focus();
    
  } else {
    // If the button text is already "Save", submit the data to PHP
    if (form) {
        nameInput.disabled = false;
        emailInput.disabled = false;
        form.submit();
    }
  }
}

function togglePasswordEdit() {
  const currentInput = document.getElementById('input-current-password');
  const newInput = document.getElementById('input-new-password');
  const confirmInput = document.getElementById('input-confirm-password');
  const editBtn = document.getElementById('edit-password-btn');
  const form = editBtn.closest('form');

  if (editBtn.textContent.trim() === 'Edit') {
    // Enter edit mode
    currentInput.removeAttribute('disabled');
    newInput.removeAttribute('disabled');
    confirmInput.removeAttribute('disabled');

    editBtn.textContent = 'Save';
    editBtn.style.backgroundColor = '#133932';

    currentInput.focus();
  } else {
    // Basic client-side sanity check before submitting
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

function switchTab(tabId) {
  document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
  const targetTab = document.getElementById(tabId);
  if (targetTab) targetTab.style.display = 'block';
}

// Show the Overview tab by default when the page loads
switchTab('overview');
</script>

</main>

<?php
include '_foot.php';
?>