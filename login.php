<?php

require '_base.php';

$_title = "Login";
$_displayTitle = false;
$pageClass = "login-page";

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
require '_head.php';
?>

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

    <div class="login-card">
        <h1>Login</h1>
        <p class="login-subtitle">Please enter your e-mail and password:</p>

        <form method="post" action="login.php" class="login-form">
            <div class="input-pill">
                <label for="email" class="visually-hidden"></label>
                <input type="email" id="email" name="email" placeholder="E-mail" value="<?= encode($email) ?>" required>
            </div>

            <div class="input-pill password-row" style="display: flex;">
                <label for="password" class="visually-hidden"></label>
                <input type="password" id="password" name="password" placeholder="Password" required>
                <button type="button" id="togglePassword" class="toggle-password-btn" aria-label="Show password">👁</button>
            </div>

            <a href="forgot_password.php" class="pass-forgot-link">Forgot password?</a>


            <?php if (!empty($_err['login'])): ?>
                <div class="login-err"><?php err('login') ?></div>
            <?php endif; ?>

            <button type="submit" class="login-btn">Login</button>
        </form>

        <p class="new-customer">New customer? <a href="/register.php">Create an account</a></p>
    </div>

<?php
include '_foot.php';
?>

<script>
    $(document).ready(function() {
        $('#togglePassword').on('click', function() {
            const passwordInput = $('#password');
            
            // Check current attribute type
            if (passwordInput.attr('type') === 'password') {
                passwordInput.attr('type', 'text');
                $(this).attr('aria-label', 'Hide password');
            } else {
                passwordInput.attr('type', 'password');
                $(this).attr('aria-label', 'Show password');
            }
        });
    });
</script>