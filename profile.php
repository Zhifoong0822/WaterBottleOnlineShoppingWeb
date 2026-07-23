<?php
// Ensure session tracking starts immediately
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require '_base.php';

// Route visitors out if they are not logged in
if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
    exit;
}

$_title = "My Profile";
$user_id = $_SESSION['user_id'];

// --- PHP BACKEND: Handle Profile Picture Upload ---
if (is_post() && isset($_FILES['profile_pic'])) {
    $file = $_FILES['profile_pic'];
    
    // Check if a file was actually uploaded without errors
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        
        if (in_array($file['type'], $allowed_types)) {
            // Create a unique filename using the user ID
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = "profile_" . $user_id . "_" . time() . "." . $ext;
            $upload_path = "uploads/" . $filename;
            
            // Create uploads folder if it doesn't exist
            if (!is_dir('uploads')) {
                mkdir('uploads', 0777, true);
            }
            
            // Move file and update database
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Update the database to remember the file path
                $stmt = $_db->prepare("UPDATE users SET profile_pic = ? WHERE user_id = ?");
                $stmt->execute([$upload_path, $user_id]);
                
                temp('info', "Profile picture updated successfully!");
                redirect('profile.php');
                exit;
            }
        } else {
            $_err['upload'] = "Invalid file type. Only JPG, PNG, and WEBP are allowed.";
        }
    }
}

// Fetch current user details from database to see if they already have an avatar image
$stmt = $_db->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch(PDO::FETCH_OBJ);
$saved_avatar = $current_user->profile_pic ?? '';

include '_head.php';
?>

<main style="max-width: 600px; margin: 60px auto; padding: 40px; background-color: #fcfbfa; border: 1px solid #eaeaea; border-radius: 16px; font-family: sans-serif;">

    <!-- Profile Header Info -->
     
    <div style="text-align: center; margin-bottom: 40px;">
        <div style="width: 80px; height: 80px; background-color: #16221f; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: bold; margin: 0 auto 15px auto;">
           <?= strtoupper(substr($_SESSION['name'], 0, 1))?>
        </div>
        <h2 style="margin: 0; font-size: 24px; color: #111;">Account Dashboard</h2>
        <p style="margin: 5px 0 0 0; color: #777;">Manage your personal account information</p>
    </div>

    <!-- Read-Only Account Overview Metrics -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 35px;">
        <div style="background-color: #fff; padding: 15px; border: 1px solid #eaeaea; border-radius: 8px;">
            <span style="font-size: 12px; text-transform: uppercase; color: #999; font-weight: 600;">Username</span>
            <div style="font-size: 16px; font-weight: bold; color: #111; margin-top: 5px;"><?= encode($_SESSION['name']) ?></div>
        </div>
        <div style="background-color: #fff; padding: 15px; border: 1px solid #eaeaea; border-radius: 8px;">
            <span style="font-size: 12px; text-transform: uppercase; color: #999; font-weight: 600;">Access Group</span>
            <div style="font-size: 16px; font-weight: bold; color: #16221f; margin-top: 5px; text-transform: capitalize;"><?= encode($_SESSION['role']) ?></div>
        </div>
    </div>

    <!-- Profile Management Action Form -->
    <form method="post" action="profile.php" style="display: flex; flex-direction: column; gap: 20px;">
        
        <div>
            <label for="email" style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 8px;">Email Address</label>
            <input type="email" id="email" name="email" value="<?= encode($_SESSION['email'] ?? '') ?>" required
                   style="width: 100%; padding: 12px 16px; border: 1px solid #ccc; border-radius: 8px; font-size: 15px; box-sizing: border-box; background-color: #fff;">
        </div>

        <div>
            <label for="password" style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 8px;">New Password (leave blank to keep current)</label>
            <input type="password" id="password" name="password" placeholder="••••••••"
                   style="width: 100%; padding: 12px 16px; border: 1px solid #ccc; border-radius: 8px; font-size: 15px; box-sizing: border-box;">
        </div>

        <div style="margin-top: 10px; display: flex; gap: 15px;">
            <button type="submit" style="flex: 1; padding: 14px; background-color: #16221f; color: #fff; border: none; border-radius: 25px; font-weight: 500; font-size: 15px; cursor: pointer;">
                Save Changes
            </button>
            <a href="products.php" style="flex: 1; padding: 14px; background-color: #fff; color: #555; text-decoration: none; border: 1px solid #ccc; border-radius: 25px; font-weight: 500; font-size: 15px; text-align: center; box-sizing: border-box;">
                Back to Shop
            </a>
        </div>
        
    </form>
</main>

<?php
include '_foot.php';
?>
