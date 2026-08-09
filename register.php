<?php
require '_base.php';

$_title = "Create Account";

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

include '_head.php';
?>

<form method="post" action="register.php">

    <div>
        <label for="name">Full Name</label>
        <?php html_text('name', "required") ?>
        <?php err('name') ?>
    </div>

    <div>
        <label for="email">Email</label>
        <?php html_text('email', "required") ?>
        <?php err('email') ?>
    </div>

    <div>
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        <?php err('password') ?>
    </div>

    <div>
        <label for="confirm_password">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
        <?php err('confirm_password') ?>
    </div>

    <button type="submit">Create Account</button>

</form>

<p><a href="login.php">Already have an account? Login</a></p>

<?php
include '_foot.php';
?>