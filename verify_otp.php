<?php
require_once '_base.php';

$_title = 'Verify OTP';

if (!isset($_SESSION['reset_user_id'])) {
    redirect('forgot_password.php');
    exit;
}

$user_id = $_SESSION['reset_user_id'];
$email = $_SESSION['reset_email'] ?? '';

$otp = '';

if (is_post()) {

    $otp = req('otp');

    // Validate OTP
    if ($otp == '') {
        $_err['otp'] = 'Required';
    }
    else if (!preg_match('/^\d{6}$/', $otp)) {
        $_err['otp'] = 'OTP must be 6 digits';
    }

    if (!$_err) {

        // Delete expired OTP
        $_db->query('DELETE FROM password_reset_otp WHERE expires_at < NOW()');

        // Check OTP
        $stm = $_db->prepare('
            SELECT *
            FROM password_reset_otp
            WHERE otp_code = ?
            AND user_id = ?
        ');

        $stm->execute([
            $otp,
            $user_id
        ]);

        $token = $stm->fetch();

        if (!$token) {

            $_err['otp'] = 'Invalid or expired OTP';

        }
        else {

            // Mark this OTP as verified
            $stm = $_db->prepare('UPDATE password_reset_otp SET verified = 1 WHERE reset_id = ?');
            $stm->execute([$token->reset_id]);

            // OTP verified
            $_SESSION['reset_verified'] = true;
            $_SESSION['reset_otp'] = $otp;

            redirect('reset_password.php');
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
        <h1>Verify OTP</h1>
        <p class="login-subtitle">
            We sent a 6-digit code to<br>
            <strong><?= encode($email) ?></strong>
        </p>

        <form method="post" action="verify_otp.php" class="login-form">

            <div class="input-pill">
                <label for="otp" class="visually-hidden">OTP</label>
                <input type="text" id="otp" name="otp" placeholder="6-digit code" inputmode="numeric" pattern="\d{6}" autocomplete="one-time-code" autofocus maxlength="6" value="<?= encode($otp) ?>" required>
            </div>

            <?php if (!empty($_err['otp'])): ?>
                <div class="login-err"><?php err('otp') ?></div>
            <?php endif; ?>

            <button type="submit" class="login-btn">Continue</button>
        </form>

        <p class="new-customer">
            Didn't get a code? <a href="forgot_password.php">Request a new OTP</a>
        </p>
    </div>
</div>

<?php
include '_foot.php';
?>