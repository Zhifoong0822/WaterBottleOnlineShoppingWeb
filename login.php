<?php

require_once '_base.php';

$_title = "Login";

$email = post('email', '');

if (is_post()) {
    $password = post('password');

    $stmt = $_db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);

    $user = $stmt->fetch(PDO::FETCH_OBJ);

    // --- Check if this account is currently blocked ---
    if ($user && $user->failed_attempts < 0) {
        $_err['login'] = "Your account has been blocked.";
    }
    // --- Check if this account is currently locked out ---
    elseif ($user && $user->locked_until !== null && strtotime($user->locked_until) > time()) {
        $seconds_left = strtotime($user->locked_until) - time();
        $minutes_left = (int) ceil($seconds_left / 60);
        $_err['login'] = "This account is temporarily locked due to too many failed attempts. Try again in $minutes_left minute" . ($minutes_left === 1 ? '' : 's') . ".";
    }
    // Verify password and build the session using object arrow syntax
    elseif ($user && password_verify($password, $user->password)) {
        // Successful login — clear any failed-attempt tracking
        $stmt = $_db->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE user_id = ?");
        $stmt->execute([$user->user_id]);

        session_regenerate_id(true);   // prevents session fixation + wipes old leftover keys tied to old session
        $_SESSION = [];                // start clean

        // Keep the session keys used by both merged codebases. Profile pages
        // use the user object, while cart/checkout/order pages use these keys.
        $_SESSION['users'] = $user;
        $_SESSION['user_id'] = (int) $user->user_id;
        $_SESSION['name'] = $user->username;
        $_SESSION['email'] = $user->email;
        $_SESSION['role'] = $user->role;

        temp('info', "Welcome back, " . encode($user->username) . "!");
        redirect($user->role === 'admin' ? 'admin_dashboard.php' : 'products.php');
        exit;
    } elseif ($user) {
        // Wrong password on a real account — count the failed attempt
        $new_attempts = $user->failed_attempts + 1;

        if ($new_attempts >= 3) {
            $locked_until = date('Y-m-d H:i:s', strtotime('+5 minutes'));
            $stmt = $_db->prepare("UPDATE users SET failed_attempts = 0, locked_until = ? WHERE user_id = ?");
            $stmt->execute([$locked_until, $user->user_id]);
            $_err['login'] = "Too many failed attempts. This account is locked for 5 minutes.";
        } else {
            $stmt = $_db->prepare("UPDATE users SET failed_attempts = ? WHERE user_id = ?");
            $stmt->execute([$new_attempts, $user->user_id]);
            $remaining = 3 - $new_attempts;
            $_err['login'] = "Invalid email or password. $remaining attempt" . ($remaining === 1 ? '' : 's') . " remaining before the account is temporarily locked.";
        }
    } else {
        // No matching account — same generic message, no lockout tracking possible
        $_err['login'] = "Invalid email or password.";
    }
}
include '_head.php';
?>


<head>
    <!-- ONLY load cart specific styles on the cart view page -->
    <?php if (basename($_SERVER['PHP_SELF']) == 'cart_view.php'): ?>
    <?php endif; ?>

<link rel="stylesheet" href="css/login.css">
</head>

<div>
    <div>
        <?php if (isset($_SESSION['users'])): ?>
            <li><a href="cart_view.php" class="nav-link">View Cart</a></li>
            <li><a href="order_history.php" class="nav-link">My Orders</a></li>

            <?php if ($_SESSION['users']->role === 'admin'): ?>
                <li><a href="admin_orders.php" class="nav-link">Manage Orders</a></li>
            <?php endif; ?>

            <li class="user-greeting">Hi, <span class="user-name"><?= encode($_SESSION['users']->username) ?></span></li>
        <?php else: ?>
        <?php endif; ?>
        </div>

    <div class="login-page">
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
                </div>

                <a href="forgot_password.php" class="forgot-link">Forgot password?</a> 

                <?php if (!empty($_err['login'])): ?>
                    <div class="login-err"><?php err('login') ?></div>
                <?php endif; ?>

                <button type="submit" class="login-btn">Login</button>
            </form>

            <p class="new-customer">New customer? <a href="register.php">Create an account</a></p>
        </div>
    </div>
</div>

<?php
include '_foot.php';
?>