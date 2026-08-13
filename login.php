<?php

require '_base.php';

$_title = "Login";

$email = post('email', '');

if (is_post()) {
    $password = post('password');

    $stmt = $_db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);

    $user = $stmt->fetch(PDO::FETCH_OBJ);

    // Verify password and build the session using object arrow syntax
    if ($user) {
        session_regenerate_id(true);   // prevents session fixation + wipes old leftover keys tied to old session
        $_SESSION = [];                // start clean

        $_SESSION['users'] = $user;

        temp('info', "Welcome back, " . encode($user->username) . "!");
        redirect($user->role === 'admin' ? 'admin_orders.php' : 'products.php');
        exit;
    } else {
        $_err['login'] = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?? 'Untitled' ?></title>

    <link rel="stylesheet" href="css/main.css">

    <!-- ONLY load cart specific styles on the cart view page -->
    <?php if (basename($_SERVER['PHP_SELF']) == 'cart_view.php'): ?>
        <link rel="stylesheet" href="css/cart.css">
    <?php endif; ?>

    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <!-- Flash message -->
    <div id="info"><?= temp('info') ?></div>

    <header>
        <h1>SippyGo</h1>
        <nav>
            <!-- Flexbox navigation line layout -->
            <ul style="display: flex; align-items: center; list-style: none; margin: 0; padding: 0; gap: 20px;">
                <li><a href="products.php">Products</a></li>

                <?php if (isset($_SESSION['users'])): ?>
                    <li><a href="cart_view.php">View Cart</a></li>
                    <li><a href="order_history.php">My Orders</a></li>

                    <?php if ($_SESSION['users']->role === 'admin'): ?>
                        <li><a href="admin_orders.php">Manage Orders</a></li>
                    <?php endif; ?>

                    <li>Hi, <?= encode($_SESSION['users']->username) ?></li>

                    <!-- Pushes Logout to the right -->
                    <li style="margin-left: auto;"><a href="logout.php">Logout</a></li>
                <?php else: ?>
                    <!-- Pushes Login to the right -->
                    <li style="margin-left: auto;"><a href="login.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main class="login-page">
        <div class="login-card">
            <h1>Login</h1>
            <p class="login-subtitle">Please enter your e-mail and password:</p>

            <form method="post" action="login.php" class="login-form">
                <div class="input-pill">
                    <label for="email" class="visually-hidden">E-mail</label>
                    <input type="email" id="email" name="email" placeholder="E-mail" value="<?= encode($email) ?>" required>
                </div>

                <div class="input-pill password-row">
                    <label for="password" class="visually-hidden">Password</label>
                    <input type="password" id="password" name="password" placeholder="Password" required>
                    <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
                </div>

                <?php if (!empty($_err['login'])): ?>
                    <div class="login-err"><?php err('login') ?></div>
                <?php endif; ?>

                <button type="submit" class="login-btn">Login</button>
            </form>

            <p class="new-customer">New customer? <a href="register.php">Create an account</a></p>
        </div>
    </main>

<?php
include '_foot.php';
?>