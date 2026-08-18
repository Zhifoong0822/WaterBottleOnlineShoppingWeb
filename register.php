<?php
require '_base.php';

$_title = "Create Account";
$_displayTitle = false;
$pageClass = "reg-page";

$name = post('name', '');
$email = post('email', '');

if (is_post()) {
    $password = post('password');
    $confirm_password = post('confirm_password');

    if ($name === '') {
        $_err['name'] = "Name is required.";
    }

    if ($email === '') {
        $_err['email'] = "Email is required.";
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_err['email'] = "Please enter a valid email.";
    }
    elseif (!is_unique($email, 'users', 'email')) {
        $_err['email'] = "This email is already registered.";
    }

    if ($password === '') {
        $_err['password'] = "Password is required.";
    }
    elseif (strlen($password) < 6) {
        $_err['password'] = "Password must be at least 6 characters.";
    }

    if ($confirm_password !== $password) {
        $_err['confirm_password'] = "Passwords do not match.";
    }

    // No errors collected - create the account
    if (!$_err) {
        // password_hash() generates a real bcrypt hash for storage
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $_db->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'customer')");
        $stmt->execute([$name, $email, $hashed]);

        temp('info', "Account created! Please login.");
        redirect('login.php');
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

<link rel="stylesheet" href="css/login.css">

<div class="reg-card">
    <h1>Create Account</h1>
    <p class="login-subtitle">Please enter your details:</p>

    <form method="post" action="register.php" class="reg-form">

        <div class ="reginput-pill">
            <input type="name" id="name" name="name" placeholder="Full Name" value="<?= encode($name) ?>" required>
        </div>

        <div class ="reginput-pill">
            <input type="email" id="email" name="email" placeholder="Email" value="<?= encode($email) ?>" required>
        </div>

        <div class ="reginput-pill">
            <input type="password" id="password" name="password" placeholder="Password" required>
        </div>

        <div class ="reginput-pill">
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
        </div>

        <button type="submit" class="reg-btn">Create Account</button>
    </form>

    <p class="existing-customer">Already have an account? <a href="/login.php">Login</a></p>
</div>

<?php
include '_foot.php';
?>