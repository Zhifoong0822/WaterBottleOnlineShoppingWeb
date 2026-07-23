<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?? 'Untitled' ?></title>
    
    <!-- Load structural styles -->
    <link rel="stylesheet" href="css/main.css">    

    <!-- ONLY load cart specific styles on the cart view page -->
    <?php if (basename($_SERVER['PHP_SELF']) == 'cart_view.php'): ?>
        <link rel="stylesheet" href="css/cart.css">
    <?php endif; ?>
</head>
<body>
    <div id="info"><?= temp('info') ?></div>

    <header style="display: flex; align-items: center; justify-content: space-between; padding: 15px 30px; background-color: #fff; font-family: sans-serif; border-bottom: 1px solid #eaeaea;">
    
        <h1 style="margin: 0; font-size: 24px; font-weight: bold; color: #222;">Water Bottle Shop</h1>
    
    <nav style="flex-grow: 1; margin-left: 40px;">
        <ul style="display: flex; align-items: center; justify-content: space-between; list-style: none; margin: 0; padding: 0; width: 100%;">
            
            <!-- Left Navigation Group (Side-by-Side Links) -->
            <div style="display: flex; align-items: center; gap: 20px;">
                <li><a href="products.php" style="text-decoration: none; color: #0000ee; font-weight: 500;">Products</a></li>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="cart_view.php" style="text-decoration: none; color: #0000ee; font-weight: 500;">View Cart</a></li>
                    <li><a href="order_history.php" style="text-decoration: none; color: #0000ee; font-weight: 500;">My Orders</a></li>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <li><a href="admin_orders.php" style="text-decoration: none; color: #0000ee; font-weight: 500;">Manage Orders</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div style="display: flex; align-items: center; gap: 20px;">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li style="color: #333; font-weight: 500;">
                        Hi, <a href="profile.php" style="text-decoration: underline; color: #0000ee; font-weight: bold;"><?= encode($_SESSION['name']) ?></a>
                    </li>
                    <li><a href="logout.php" style="text-decoration: none; color: #0000ee; font-weight: 500;">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php" style="text-decoration: none; color: #0000ee; font-weight: 500;">Login</a></li>
                <?php endif; ?>
            </div>

        </ul>
    </nav>
</header>

    <main>
        <h1><?= $_title ?? 'Untitled' ?></h1>