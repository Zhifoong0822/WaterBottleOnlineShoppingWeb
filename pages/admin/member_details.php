<?php
// Core Components
require_once '../../_base.php';
require_admin('../../products.php');
$_title = 'Member Details';
include '../../_head.php';

// Define user fields
$userFields = [
    'user_id'       => 'Id',
    'username'      => 'Username',
    'email'         => 'Email',
    // 'role'          => 'Role',
    'created_at'    => 'Created At',
];

// Page Query Parameters
$userId = intval(req('id', 1));
$page = max(1, intval(req('page', 1)));

// Database Query
$baseSQL = "
    SELECT user_id, username, email, role, created_at
    FROM users
    WHERE 1=1
    AND role = 'member'
    AND user_id = {$userId}    
";

$member = $_db->query("{$baseSQL}")->fetch(PDO::FETCH_ASSOC);

?>

<link rel="stylesheet" href="../../css/admin.css">
<link rel="stylesheet" href="../../css/main.css">

<div class="admin-container">
    <div class="view-card">
        <div class="view-content">
            <!-- Profile Image -->
            <div class="view-image">
                <?php
                    $imagePath = "../../img/member/{$member['user_id']}";
                    $extensions = ['jpg', 'jpeg', 'png', 'webp'];

                    foreach ($extensions as $ext) {
                        if (file_exists($imagePath . "." . $ext)) {
                            $imagePath .= "." . $ext;
                            break;
                        }
                    }
                ?>

                <img
                    src="<?= $imagePath ?>"
                    alt="<?= $member['username'] . ' Profile' ?>"
                    class="view-product-image"
                >

                <!-- <input
                    type="file"
                    name="image"
                > -->
            </div>

            <!-- Details -->
            <div class="view-details">
                <?php foreach ($userFields as $field => $label): ?>
                    <div class="detail-row">
                        <label><?= encode($label) ?></label>
                        <span><?= encode($member[$field]) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="view-footer">
            <a href="member_listing.php?page=<?= $page ?>" class="btn-view">
                Back
            </a>
        </div>
    </div>
</div>

<?php include '../../_foot.php'; ?>
