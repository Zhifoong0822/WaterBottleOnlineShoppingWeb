<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?? 'Untitled' ?></title>
    
    <!-- Load main stylesheet -->
    <link rel="stylesheet" href="css/main.css">    

    <!-- ONLY load order styles and icons on order-related pages -->
    <?php if (in_array(basename($_SERVER['PHP_SELF']), [
        'order_history.php',
        'order_detail.php',
        'order_feedback.php',
        'admin_orders.php',
        'admin_order_detail.php'
    ])): ?>

    <link rel="stylesheet" href="css/orders.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

<?php endif; ?>
    
</head>
<body>
    <div id="info"><?= temp('info') ?></div>

    <header class="site-header">
        <div class="header-container">
            <a href="index.php" class="logo-link">
                <h1>Sippy<span>Go</span></h1>
            </a>
        
            <nav class="main-nav">
                <ul class="nav-list">
                    <!-- Left Navigation Group -->
                    <div class="nav-group">
                        <li><a href="products.php" class="nav-link">Products</a></li>

                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li><a href="cart_view.php" class="nav-link">View Cart</a></li>
                            <li><a href="order_history.php" class="nav-link">My Orders</a></li>
                            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                                <li><a href="admin_orders.php" class="nav-link">Manage Orders</a></li>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Right Navigation Group -->
                    <div class="nav-group">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li class="user-greeting">
                                Hi, <a href="profile.php" class="user-name"><?= encode($_SESSION['name'] ?? $_SESSION['users']->username) ?></a>
                            </li>
                            <li><a href="logout.php" class="nav-link nav-link-btn">Logout</a></li>
                        <?php else: ?>
                            <li><a href="login.php" class="nav-link nav-link-btn">Login</a></li>
                        <?php endif; ?>
                    </div>
                </ul>
            </nav>
        </div>
    </header>

    <main<?= !empty($_hide_page_title) ? ' class="main-without-page-title"' : '' ?>>
        <?php if (empty($_hide_page_title)): ?>
            <h1><?= $_title ?? 'Untitled' ?></h1>
        <?php endif; ?>

<?php
$current_page = basename($_SERVER["PHP_SELF"]);
?>

<?php if (
    $current_page === "order_history.php" ||
    $current_page === "order_detail.php" ||
    $current_page === "admin_orders.php" ||
    $current_page === "admin_order_detail.php"
): ?>
    <link rel="stylesheet" href="css/orders.css">
<?php endif; ?>
