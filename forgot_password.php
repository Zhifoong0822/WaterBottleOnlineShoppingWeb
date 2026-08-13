<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require '_base.php';

$_title = "Forgot Password";

$email = post('email', '');

if (is_post()) {
    if ($email === '') {
        $_err['email'] = "Please enter your email.";
    } else {
        $stmt = $_db->prepare("SELECT user_id, email FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_OBJ);

        if ($user) {
            // Generate a 6-digit numeric OTP
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $otp_hash = password_hash($otp, PASSWORD_DEFAULT);
            $expires = date("Y-m-d H:i:s", strtotime("+10 minutes"));

            // Reuse reset_token / reset_expires columns to store the hashed OTP + expiry
            $stmt = $_db->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE user_id = ?");
            $stmt->execute([$otp_hash, $expires, $user->user_id]);

            // Send the OTP by email.
            // NOTE: mail() requires a working mail transport on the server (sendmail/Postfix)
            // or a properly configured SMTP relay. Swap this for PHPMailer + SMTP in production
            // for reliable delivery.
            $subject = "Your password reset code";
            $body = "Your one-time password reset code is: $otp\n\nThis code expires in 10 minutes. If you did not request this, you can ignore this email.";
            $headers = "From: no-reply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            @mail($user->email, $subject, $body, $headers);

            // Track the pending OTP flow in the session (never trust a resubmitted email/user_id from the client)
            $_SESSION['otp_flow'] = [
                'user_id'  => $user->user_id,
                'email'    => $user->email,
                'stage'    => 'otp_sent',
                'attempts' => 0,
                'expires'  => strtotime($expires),
            ];

            redirect('verify_otp.php');
            exit;
        } else {
            $_err['email'] = "No account found with that email.";
        }
    }
}

include '_head.php';
?>
<link rel="stylesheet" href="css/main.css">
<link rel="stylesheet" href="css/login.css">

<main class="login-page">
    <div class="login-card">
        <h1>Forgot Password</h1>
        <p class="login-subtitle">Enter your account email and we'll send you a reset code:</p>

        <form method="post" action="forgot_password.php" class="login-form">
            <div class="input-pill">
                <label for="email" class="visually-hidden">E-mail</label>
                <input type="email" id="email" name="email" placeholder="E-mail" value="<?= encode($email) ?>" required>
            </div>

            <?php if (!empty($_err['email'])): ?>
                <div class="login-err"><?php err('email') ?></div>
            <?php endif; ?>

            <button type="submit" class="login-btn">Send Reset Code</button>
        </form>

        <p class="new-customer"><a href="login.php">Back to login</a></p>
    </div>
</main>

<?php
include '_foot.php';
?>