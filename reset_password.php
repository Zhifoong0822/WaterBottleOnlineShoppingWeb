<?php
require_once '_base.php';

$_title = 'Reset Password';
    
if (
    !isset($_SESSION['reset_user_id']) ||
    !isset($_SESSION['reset_verified']) ||
    $_SESSION['reset_verified'] !== true ||
    !isset($_SESSION['reset_otp'])
) {
    redirect('login.php');
    exit;
}

$user_id = $_SESSION['reset_user_id'];
$otp = $_SESSION['reset_otp'];

if (is_post()) {

    $password = req('password');
    $confirm = req('confirm');

    // Validate password
    if ($password == '') {
        $_err['password'] = 'Required';
    }
    else if (strlen($password) < 8) {
        $_err['password'] = 'Minimum 8 characters';
    }

    // Validate confirmation
    if ($confirm == '') {
        $_err['confirm'] = 'Required';
    }
    else if ($confirm != $password) {
        $_err['confirm'] = 'Passwords do not match';
    }

    if (!$_err) {

        // Delete expired OTP
        $_db->query('DELETE FROM password_reset_otp WHERE expires_at < NOW()');

        // Check OTP again
        $stm = $_db->prepare('
            SELECT *
            FROM password_reset_otp
            WHERE otp_code = ?
            AND user_id = ?
            AND verified = 1
        ');

        $stm->execute([
            $otp,
            $user_id
        ]);

        $token = $stm->fetch();

        if (!$token) {

            temp('info', 'Invalid or expired OTP. Try again');
            redirect('forgot_password.php');

        }
        else {

            // Hash new password
            $hash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // Update password
            $stm = $_db->prepare('UPDATE users SET password = ? WHERE user_id = ?');
            $stm->execute([$hash, $user_id]);

            // Delete used OTP
            $stm = $_db->prepare('DELETE FROM password_reset_otp WHERE otp_code = ? AND user_id = ?');
            $stm->execute([$otp, $user_id]);

            // Clear reset session
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_verified']);
            unset($_SESSION['reset_otp']);

            temp(
                'info',
                'Password reset successfully. Please login.'
            );

            redirect('login.php');
        }
    }
}

include '_head.php';
?>

<head>
<link rel="stylesheet" href="css/login.css">
</head>

<div class="login-page">
    <div class="login-card">
        <h1>Reset Password</h1>
        <p class="login-subtitle">Enter new password.</p>

        <form method="post" action="reset_password.php" class="login-form">

            <div class="input-pill">
                <label for="password" class="visually-hidden">New Password</label>
                <input type="password" id="password" name="password" placeholder="New Password"required>
            </div>

            <?php if (!empty($_err['password'])): ?>
                <div class="login-err"><?php err('password') ?></div>
            <?php endif; ?>

            <div class="input-pill">
                <label for="confirm" class="visually-hidden">Confirm Password</label>
                <input type="password" id="confirm" name="confirm" placeholder="Confirm Password" required>
            </div>

            <?php if (!empty($_err['confirm'])): ?>
                <div class="login-err"><?php err('confirm') ?></div>
            <?php endif; ?>

            <button type="submit" class="login-btn">Reset Password</button>
        </form>
    </div>
</div>

<?php
include '_foot.php';
?>