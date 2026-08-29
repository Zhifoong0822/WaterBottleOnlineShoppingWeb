<?php
// Core Components
require_once '../../_base.php';
require_admin('../../products.php');
$_title = 'Member Details';

// Current User
$user_is_admin = $_SESSION['users']?->role === 'admin';

// Query Params
$user_id = intval(req('id', 1));
$return_page = req('from', 'member_listing.php');

$member = $_db->query("
        SELECT user_id, username, email, role, profile_pic, reward_points, failed_attempts, created_at
        FROM users
        WHERE 1=1
        AND role = 'member'
        AND user_id = {$user_id}    
    ")->fetch(PDO::FETCH_ASSOC);

$is_blocked = (int)$member['failed_attempts'] < 0;
?>

<?php
// Handle AJAX Request

function updateFailed(string $errMsg) {
    temp('error', $errMsg);
    redirect();
    exit;
}

if (is_post()) {
    // Only admins can update memebers
    if (!$user_is_admin) {
        updateFailed('Only admins are authroised.');
    }

    // User record must exist
    if (!$member) {
        updateFailed('Unable to find user record.');
    }

    // Handle user block toggle
    if (isset($_POST['toggle_block'])) {
        $is_blocked = (int)$member['failed_attempts'] < 0;

        if ($is_blocked) {
            // Unblock user
            $stmt = $_db->prepare("
                UPDATE users
                SET failed_attempts = 0
                WHERE user_id = ?
                AND role = 'member'
            ");

            $stmt->execute([$user_id]);

            temp('info', 'Member has been unblocked.');

        } else {
            // Block user
            $stmt = $_db->prepare("
                UPDATE users
                SET failed_attempts = -1
                WHERE user_id = ?
                AND role = 'member'
            ");

            $stmt->execute([$user_id]);

            temp('info', 'Member has been blocked.');
        }

        redirect();
        exit;
    }

    // Handle updating user details
    if (isset($_POST['update_user'])) {
        $username = trim(post('username', ''));
        $email = trim(post('email', ''));
        $rewardPoints = post('reward_points', -1);

        $profileUpdated = false;
        $profilePic = $_FILES['profile_pic'] ?? null;

        // Validate fields
        if ($username === '') {
            updateFailed('Username cannot be empty.');
        }

        if ($email === '') {
            updateFailed('Email cannot be empty.');
        }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            updateFailed('Please enter a valid email.');
        }
        elseif ($email !== $member['email'] && !is_unique($email, 'users', 'email')) {
            updateFailed('This email is already registered.');
        }

        if (!is_numeric($rewardPoints) || $rewardPoints < 0) {
            updateFailed('Reward points must be a valid non-negative number.');
        }

        $updated_profile_pic = $member['profile_pic'];
        if ($profilePic && $profilePic['error'] !== UPLOAD_ERR_NO_FILE) {

            if ($profilePic['error'] !== UPLOAD_ERR_OK) {
                updateFailed('Failed to upload profile image.');
                exit;
            }

            if ($profilePic['size'] > 5 * 1024 * 1024) {
                updateFailed('Profile image must be smaller than 5MB.');
                exit;
            }

            $imageInfo = getimagesize($profilePic['tmp_name']);
            if ($imageInfo === false) {
                updateFailed('Invalid profile image.');
            }

            $allowed_mimes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
            ];

            $mime = $imageInfo['mime'];

            if (!isset($allowed_mimes[$mime])) {
                updateFailed('Invalid profile image type.');
            }

            $ext = $allowed_mimes[$mime];
            $filename = $user_id . '_' . time() . '.' . $ext;
            $target_path = PROJECT_ROOT . PROFILE_IMAGE_DIR . $filename;

            if (!move_uploaded_file($profilePic['tmp_name'], $target_path)) {
                updateFailed('Failed to save profile image.');
            }

            $updated_profile_pic = $filename;

        }

        // Update user data
        $update_stmt = $_db->prepare("
            UPDATE users
            SET
                username = ?,
                email = ?,
                reward_points = ?,
                profile_pic = ?
            WHERE user_id = ?
            AND role = 'member'
        ");

        $update_success = $update_stmt->execute([
            $username,
            $email,
            $rewardPoints,
            $updated_profile_pic,
            $user_id,
        ]);

        if (!$update_success) {
            updateFailed('Failed to update member profile.');
        }
        else {
            temp('info', 'Profile updated successfully.');
            redirect();
        }

        exit;
    }
}

?>
    
<?php
// Define user fields
$userFields = [
    'user_id' => [
        'label' => 'User ID',
        'type' => 'text',
        'disabled' => true,
    ],

    'username' => [
        'label' => 'Username',
        'type' => 'text',
    ],

    'email' => [
        'label' => 'Email',
        'type' => 'email',
    ],

    'reward_points' => [
        'label' => 'Reward Points',
        'type' => 'number',
    ],

    'created_at' => [
        'label' => 'Created At',
        'type' => 'text',
        'disabled' => true,
    ],
];

include '../../_head.php';
?>

<link rel="stylesheet" href="../../css/admin.css">
<link rel="stylesheet" href="../../css/main.css">

<div class="admin-container">
    <form 
        method="post"
        enctype="multipart/form-data"
        class="view-card"
    >
        <div class="view-content">
            <!-- Profile Image -->
            <div class="view-image">
                <!-- Img Preview -->
                <img
                    id="profileImg"
                    src="<?= encode(PROFILE_IMAGE_DIR . ($member['profile_pic'] ?? "defaultProfile.png") ) ?>"
                    alt="<?= encode(($member['username'] ?? '') . ' Profile') ?>"
                    class="view-product-image"
                    style="width: 250px; height: 250px;"
                >

                <!-- Drag & drop / click to upload -->
                <div
                    id="photoDropzone"
                    class="photo-dropzone"
                    style="margin-top: 18px; padding: 16px;"
                >
                    <div class="photo-dropzone-icon">
                        &#8593;
                    </div>

                    <div class="photo-dropzone-text">
                        Drag &amp; drop or click to upload
                    </div>

                    <div class="photo-dropzone-sub">
                        JPG, PNG, WebP up to 5MB &middot; 1 image
                    </div>
                </div>
            </div>

            <!-- Details -->
            <div class="view-details" style="margin-left: 20px;">
                <input
                    id="profileFileInput"
                    name="profile_pic"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    hidden
                >

                <div class="detail-row" style="margin-bottom: 10px;">
                    <label class="form-group">Account Status</label>

                    <div class="">
                        <?php if ((int)$member['failed_attempts'] < 0): ?>
                            <span class="badge badge-blocked">Blocked</span>
                        <?php else: ?>
                            <span class="badge badge-active">Active</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php foreach ($userFields as $field => $config): ?>
                    <div class="detail-row">
                        <label class="form-group"><?= encode($config['label']) ?></label>

                        <div class="form-group">
                            <input
                                id="<?= $field ?>"
                                name="<?= $field ?>"
                                type="<?= $config['type'] ?? 'text' ?>"
                                value="<?= encode($member[$field]) ?>"

                                <?= ($config['type'] ?? '') === 'number' ? 'min="0"' : '' ?>
                                <?= ($config['disabled'] ?? false) ? 'disabled' : '' ?>
                            >
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="view-footer">
            <button
                type="submit"
                name="toggle_block" value="1"
                class="<?= (int)$member['failed_attempts'] < 0 ? 'btn-restore' : 'btn-delete' ?>"
            >
                <?= (int)$member['failed_attempts'] < 0 ? 'Unblock User' : 'Block User' ?>
            </button>
            
            <button 
                type="submit"
                name="update_user" value="1"
                class="btn-edit"
            >
                Update
            </button>

            <a href="<?= encode($return_page) ?>" class="btn-view">
                Back
                </a>
        </div>
    </form>
</div>

<?php include '../../_foot.php'; ?>

<script>
$(function () {
    const FILE_MAX_MB_SIZE = 5; 

    const $fileInput = $('#profileFileInput');
    const $profileImg = $('#profileImg');
    const $dropzone = $('#photoDropzone');

    // Prompt for file on click
    $('#profileImg, #photoDropzone').on('click', function () {
        $fileInput.trigger('click');
    });

    // File selected
    $fileInput.on('change', function () {
        const file = this.files[0];
        handleNewFileInput(file);
    });

    // Drag indicator styling
    $dropzone.on('dragenter dragover', function (e) {
        e.preventDefault();
        $(this).addClass('is-dragover');
    });

    $dropzone.on('dragleave drop', function (e) {
        e.preventDefault();
        $(this).removeClass('is-dragover');
    });

    // Hanlde file being dropped
    $dropzone.on('drop', function (e) {
        if (e.originalEvent.dataTransfer) {
            const files = e.originalEvent.dataTransfer.files;

            if (files.length) {
                $fileInput[0].files = files;
                handleNewFileInput(files[0]);
            }
        }
    });

    // Front file validation and update preview profile
    function handleNewFileInput(file) {
        if (!file) {
            return;
        }

        // Validate image
        if (!file.type.startsWith('image/')) {
            alert('Only image file is allowed.');
            $fileInput.val('');
            return;
        }
 
        // Validate 5MB
        if (file.size > (FILE_MAX_MB_SIZE * 1024 * 1024)) {
            alert(`Image must be smaller than ${FILE_MAX_MB_SIZE}MB.`);
            $fileInput.val('');
            return;
        }

        // Set Preview
        const reader = new FileReader();
        reader.onload = function (e) {
            $profileImg.attr('src', e.target.result);
        };
        reader.readAsDataURL(file);
    }

});
</script>