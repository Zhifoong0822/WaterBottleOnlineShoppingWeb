<?php
require_once '_base.php';

$_title = 'Forgot Password';

$email = '';

if (is_post()) {

    $email = req('email');

    // Validate email
    if ($email == '') {
        $_err['email'] = 'Required';
    }
    else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_err['email'] = 'Invalid email';
    }

    // Send OTP if valid
    if (!$_err) {

        // Select user based on email
        $stm = $_db->prepare('SELECT * FROM users WHERE email = ?');
        $stm->execute([$email]);
        $u = $stm->fetch();

        if (!$u) {
            $_err['email'] = 'Email not found';
        }
        else {

            // Generate 6-digit OTP
            $otp = (string) random_int(100000, 999999);

            // Delete old OTP
            $stm = $_db->prepare('DELETE FROM password_reset_otp WHERE user_id = ?');
            $stm->execute([$u->user_id]);

            // Insert new OTP
            $stm = $_db->prepare('
                INSERT INTO password_reset_otp (user_id, otp_code, expires_at)
                VALUES (?, ?, ADDTIME(NOW(), "00:05"))
            ');
            $stm->execute([$u->user_id, $otp]);

            // Send email
            $m = get_mail();
            $m->addAddress($u->email, $u->username);
            $m->isHTML(true);
            $m->Subject = 'Sippy Go Password Reset OTP';

            $m->Body = "
                <h2>Password Reset</h2>
                <p>Dear $u->username,</p>
                <p>Your OTP is:</p>
                <h1>$otp</h1>
                <p>This OTP will expire in 5 minutes.</p>
                <p>If you did not request this password reset, please ignore this email.</p>
                <p>From, Sippy Go</p>
            ";

            $m->send();

            // Save reset user temporarily
            $_SESSION['reset_user_id'] = $u->user_id;
            $_SESSION['reset_email'] = $u->email;

            redirect('verify_otp.php');
        }
    }
}

require '_head.php';
?>

<div class="auth-container">

    <h2>Forgot Password</h2>

    <form method="post">

        <label for="email">Email</label>

        <input
            type="email"
            id="email"
            name="email"
            value="<?= encode($email) ?>"
            required
        >

        <?= err('email') ?>

        <button type="submit">
            Send OTP
        </button>

    </form>

    <p>
        <a href="login.php">Back to Login</a>
    </p>

</div>

<?php require '_foot.php'; ?>