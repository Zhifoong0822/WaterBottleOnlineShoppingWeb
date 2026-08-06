<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require '_base.php';

if (!isset($_SESSION['users'])) {
    redirect('login.php');
    exit;
}

$_title = "My Profile";
$user_id = $_SESSION['users']->user_id;

// --- PHP BACKEND: Handle Profile Data Updates ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Clean and sanitize the raw input from the form
    $updated_name = trim($_POST['user-name']);
    $updated_profilepic = null;
    
    //check if the user selected photos or not 
    if(isset($_FILES['photo']) && $_FILES['photo']['error']===0) {
        
    $folder = "update/profile/";

    //create filename
    $filename = time() . "_" . $_FILES['photo']['name'];
    $updated_profilepic = $folder . $filename;
    // Move image to folder
        move_uploaded_file(
            $_FILES['photo']['tmp_name'],
            $updated_profilepic
        );
    }

    // Update database
    if ($updated_profilepic) {

        $stmt = $_db->prepare(
            "UPDATE users 
             SET username = ?, profilepic = ?
             WHERE user_id = ?"
        );

        $stmt->execute([
            $updated_name,
            $updated_profilepic,
            $user_id
        ]);

    } else {
        $stmt = $_db->prepare(
            "UPDATE users 
             SET username = ?
             WHERE user_id = ?"
        );

        $stmt->execute([
            $updated_name,
            $user_id
        ]);
    }

    // Update session
    $_SESSION['users']->username = $updated_name;

    if ($updated_profilepic) {
        $_SESSION['users']->profilepic = $updated_profilepic;
    }


    header("Location: profile.php");
    exit;
}

// Fetch current user details
$stmt = $_db->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch(PDO::FETCH_OBJ);
$saved_avatar = $current_user->profilepic ?? '';

include '_head.php';
?>
<link rel="stylesheet" href="css/profile.css">

<main>

    <!-- Mockup Header Section Matching Image Component -->
    <div class="header-banner">
        <div class="user-meta-group">
            <div class="avatar-badge">
            <?php if (!empty($_SESSION['users']->profilepic)): ?>
                <img 
                    src="<?= $_SESSION['users']->profilepic ?>" 
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

<!-- Settings Tab Content -->
<div id="settings" class="tab-content">
    <h2>Settings Dashboard</h2>
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="card-container">
            <div class="card-header">
                <h4>Profile</h4>
                <!-- Edit -->
                <input type ="file" id="profilephoto-input" name=photo accept="image/*" disabled>
                <button type="button" id="edit-btn" class="edit-profile-btn" onclick="toggleEdit()">Edit</button>
            </div>

            <div class="form-group">
                <label>Name</label>
                <input type="text" id="input-name" name="user-name" class="input-field" value="<?= encode($_SESSION['users']->username) ?>"disabled>
            </div>

            <div class="form-group">
                <label>Email</label>
                <div class="input-field"><?= encode($_SESSION['users']->email) ?></div>
            </div>
        </div>
    </form>
</div>

<script>
function toggleEdit() {
  const nameInput = document.getElementById('input-name');
  const photoInput = document.getElementById('profilephoto-input');
  const editBtn = document.getElementById('edit-btn');
  const form = editBtn.closest('form'); 

  // Enter edit mode
  if (editBtn.textContent.trim() === 'Edit') {
    
    // allow editing
    nameInput.removeAttribute('disabled');
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

        form.submit();
    }
  }
}

function switchTab(tabId) {
  document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
  const targetTab = document.getElementById(tabId);
  if (targetTab) targetTab.style.display = 'block';
}
</script>

</main>

<?php
include '_foot.php';
?>
