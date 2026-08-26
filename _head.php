<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?? 'Untitled' ?></title>
    
    <!-- Load main stylesheet -->
    <link rel="stylesheet" href="css/main.css">

    <!-- ONLY load cart specific styles on the cart view page -->
    <?php if (basename($_SERVER['PHP_SELF']) == 'cart_view.php'): ?>
        <link rel="stylesheet" href="css/cart.css">
    <?php endif; ?>

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

    <?php foreach ($_extra_css ?? [] as $stylesheet): ?>
        <link rel="stylesheet" href="<?= encode($stylesheet) ?>">
    <?php endforeach; ?>
</head>

<body>

    <div id="info"><?= temp('info') ?></div>

    <header class="site-header">
        <div class="header-container">

            <a href="/products.php" class="logo-link" aria-label="SippyGo products">
                <h1>Sippy<span>Go</span></h1>
            </a>

            <nav class="main-nav">
                <ul class="nav-list">

                    <!-- Left Navigation Group -->
                    <div class="nav-group">

                        <?php if (isset($_SESSION['users'])): ?>

                            <!-- Admin Navigation -->
                            <?php if (($_SESSION['users']->role ?? '') === 'admin'): ?>

                                <li>
                                    <a href="/pages/admin/admin_products.php" class="nav-link">
                                        Manage Products
                                    </a>
                                </li>

                                <li>
                                    <a href="/admin_orders.php" class="nav-link">
                                        Manage Orders
                                    </a>
                                </li>

                                <li>
                                    <a href="/pages/admin/member_listing.php" class="nav-link">
                                        Member Listing
                                    </a>
                                </li>

                            <?php endif; ?>


                            <!-- Logged-in User Navigation -->
                            <?php if (isset($_SESSION['users']->user_id)): ?>
                                <li>
                                    <a href="/products.php" class="nav-link">
                                        Products
                                    </a>
                                </li>

                                <li>
                                    <a href="/cart_view.php" class="nav-link">
                                        View Cart
                                    </a>
                                </li>

                                <li>
                                    <a href="/order_history.php" class="nav-link">
                                        My Orders
                                    </a>
                                </li>
                            <?php endif; ?>

                            <li>
                                <a href="/live_chat.php" class="nav-link">
                                    Chat Support
                                </a>
                            </li>
                        <?php endif; ?>

                    </div>


                    <!-- Right Navigation Group -->
                    <div class="nav-group">

                        <?php if (isset($_SESSION['users'])): ?>

                            <li class="user-greeting">

                                <a href="/profile.php" class="user-name">

                                    <img
                                        src="/img/user-icon.png"
                                        alt="User Profile"
                                        class="nav-user-icon"
                                        style="width: 35px; height: 35px; margin-top: 10px;"
                                    >

                                </a>

                            </li>

                            <li>
                                <a href="/logout.php" class="nav-link nav-link-btn">
                                    Logout
                                </a>
                            </li>

                        <?php else: ?>

                            <li>
                                <a href="/login.php" class="nav-link nav-link-btn">
                                    Login
                                </a>
                            </li>

                        <?php endif; ?>

                    </div>

                </ul>
            </nav>

        </div>
    </header>

    <?php
    $main_classes = array_filter([
        $pageClass ?? '',
        !empty($_hide_page_title) ? 'main-without-page-title' : '',
    ]);
    $show_page_title = ($_displayTitle ?? true) !== false && empty($_hide_page_title);
    ?>
    <main<?= $main_classes ? ' class="' . encode(implode(' ', $main_classes)) . '"' : '' ?>>
        <?php if ($show_page_title): ?>
            <h1<?= !empty($_page_title_class) ? ' class="' . encode($_page_title_class) . '"' : '' ?>><?= $_title ?? 'Untitled' ?></h1>
        <?php endif; ?>
