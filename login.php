<?php
// Start session immediately before loading any other files
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require '_base.php';

$_title = "Login";

$email = post('email', '');

if (is_post()) {
    $password = post('password');

    $stmt = $_db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    
    $user = $stmt->fetch(PDO::FETCH_OBJ);

    // Verify password and build the session using object arrow syntax
    if ($user && password_verify($password, $user->password)) {
        session_regenerate_id(true);

        // Keep the session keys used by both merged codebases. Profile pages
        // use the user object, while cart/checkout/order pages use these keys.
        $_SESSION['users'] = $user;
        $_SESSION['user_id'] = (int) $user->user_id;
        $_SESSION['name'] = $user->username;
        $_SESSION['role'] = $user->role;

        temp('info', "Welcome back, " . encode($_SESSION['users']->username) . "!");

        // Route users according to their access roales
        redirect($_SESSION['users']->role === 'admin' ? 'admin_orders.php' : 'products.php');
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
    
    <!-- Load structural styles -->
    <link rel="stylesheet" href="css/main.css">    

    <!-- ONLY load cart specific styles on the cart view page -->
    <?php if (basename($_SERVER['PHP_SELF']) == 'cart_view.php'): ?>
        <link rel="stylesheet" href="css/cart.css">
    <?php endif; ?>
</head>
<body>
    <!-- Flash message -->
    <div id="info"><?= temp('info') ?></div>

    <header>
        <h1>Water Bottle Shop</h1>
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

    <main>
        <h1><?= $_title ?? 'Untitled' ?></h1>

        <form method="post" action="login.php">
            <div>
                <label for="email">Email</label>
                <?php html_text('email', "required") ?>
            </div>

            <div>
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <?php err('login') ?>

            <button type="submit">Login</button>
        </form>

        <p>
            <a href="forgot_password.php">Forgot password?</a>
            &nbsp;|&nbsp;
            <a href="register.php">Create account</a>
        </p>
    </main>

<?php
include '_foot.php';
?>
