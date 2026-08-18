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

    <!-- Load order specific styles -->
    <?php
    $current_page = basename($_SERVER["PHP_SELF"]);

    if (
        $current_page === "order_history.php" ||
        $current_page === "order_detail.php" ||
        $current_page === "admin_orders.php" ||
        $current_page === "admin_order_detail.php"
    ):
    ?>
        <link rel="stylesheet" href="css/orders.css">
    <?php endif; ?>
</head>

<body>

    <div id="info"><?= temp('info') ?></div>

    <header class="site-header">
        <div class="header-container">

            <a href="index.php" class="logo-link">
                <h1>Water Bottle Shop</h1>
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
                                    <a href="cart_view.php" class="nav-link">
                                        View Cart
                                    </a>
                                </li>

                                <li>
                                    <a href="order_history.php" class="nav-link">
                                        My Orders
                                    </a>
                                </li>

                            <?php endif; ?>

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


    <main<?= !empty($pageClass) ? ' class="' . $pageClass . '"' : '' ?>>

        <?php if ($_displayTitle != false): ?>

            <h1><?= $_title ?? 'Untitled' ?></h1>

        <?php endif;?>