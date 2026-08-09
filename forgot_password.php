<?php
require '_base.php';

$_title = "Forgot Password";

$email = post('email', '');
$reset_link = '';

if (is_post()) {
    if ($email === '') {
        $_err['email'] = "Please enter your email.";
    }
    else {
        $stmt = $_db->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate a secure random token, valid for 1 hour
            $token = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

            $stmt = $_db->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE user_id = ?");
            $stmt->execute([$token, $expires, $user->user_id]);

            // In production, email this link to the user instead of displaying it.
            // Example: mail($email, "Password Reset", "Reset link: $reset_link");
            $reset_link = "reset_password.php?token=" . $token;
        }
        else {
            $_err['email'] = "No account found with that email.";
        }
    }
}

include '_head.php';
?>

<form method="post" action="forgot_password.php">

    <div>
        <label for="email">Enter your account email</label>
        <?php html_text('email', "required") ?>
        <?php err('email') ?>
    </div>

    <button type="submit">Send Reset Link</button>

</form>

<?php if ($reset_link): ?>
    <p>
        Reset link generated (in production this would be emailed):<br>
        <a href="<?= encode($reset_link) ?>"><?= encode($reset_link) ?></a>
    </p>
<?php endif; ?>

<p><a href="login.php">Back to login</a></p>

<?php
include '_foot.php';
?>