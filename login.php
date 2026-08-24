<?php

require '_base.php';

$_title = "Login";
$_displayTitle = false;
$pageClass = "login-page";
$_extra_css = ['css/login.css'];

$email = post('email', '');

if (is_post()) {
    $password = post('password');

    $stmt = $_db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);

    $user = $stmt->fetch(PDO::FETCH_OBJ);

    // Only establish a session after the submitted password matches its hash.
    if ($user && password_verify($password, $user->password)) {
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

    <div class="login-card">
        <h1>Login</h1>
        <p class="login-subtitle">Please enter your e-mail and password:</p>

        <form method="post" action="login.php" class="login-form">
            <div class="input-pill">
                <label for="email" class="visually-hidden">E-mail</label>
                <input type="email" id="email" name="email" placeholder="E-mail" value="<?= encode($email) ?>" required>
            </div>

            <div class="input-pill password-row" style="display: flex;">
                <label for="password" class="visually-hidden">Password</label>
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

<script>
    document.getElementById('togglePassword').addEventListener('click', function () {
        const passwordInput = document.getElementById('password');
        const isHidden = passwordInput.type === 'password';

        passwordInput.type = isHidden ? 'text' : 'password';
        this.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
    });
</script>

<?php include '_foot.php'; ?>
