<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require '_base.php';

$_title = "Reset Password";

// Must have completed OTP verification, and still be inside the reset window
if (
    empty($_SESSION['otp_flow'])
    || $_SESSION['otp_flow']['stage'] !== 'verified'
    || time() > $_SESSION['otp_flow']['verified_until']
) {
    unset($_SESSION['otp_flow']);
    temp('info', "Your session expired. Please start over.");
    redirect('forgot_password.php');
    exit;
}

$user_id = $_SESSION['otp_flow']['user_id'];

if (is_post()) {
    $new_password     = post('new_password', '');
    $confirm_password = post('confirm_password', '');

    if (strlen($new_password) < 8) {
        $_err['password'] = "Password must be at least 8 characters.";
    } elseif ($new_password !== $confirm_password) {
        $_err['password'] = "Passwords do not match.";
    } else {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $_db->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE user_id = ?");
        $stmt->execute([$new_hash, $user_id]);

        // Done — clear the OTP flow entirely
        unset($_SESSION['otp_flow']);

        temp('info', "Your password has been reset. Please log in.");
        redirect('login.php');
        exit;
    }
}

include '_head.php';
?>
<link rel="stylesheet" href="css/main.css">
<link rel="stylesheet" href="css/login.css">

<main class="login-page">
    <div class="login-card">
        <h1>Reset Password</h1>
        <p class="login-subtitle">Enter your new password:</p>

        <form method="post" action="reset_password.php" class="login-form">
            <div class="input-pill">
                <label for="new_password" class="visually-hidden">New password</label>
                <input type="password" id="new_password" name="new_password" placeholder="New password" minlength="8" required>
            </div>

            <div class="input-pill">
                <label for="confirm_password" class="visually-hidden">Confirm new password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" minlength="8" required>
            </div>

            <?php if (!empty($_err['password'])): ?>
                <div class="login-err"><?php err('password') ?></div>
            <?php endif; ?>

            <button type="submit" class="login-btn">Reset Password</button>
        </form>
    </div>
</main>

<?php
include '_foot.php';
?>