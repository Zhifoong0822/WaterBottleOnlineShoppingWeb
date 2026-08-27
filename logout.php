<?php
require_once '_base.php';

unset(
    $_SESSION['users'],
    $_SESSION['users']->user_id,
    $_SESSION['users']->username,
    $_SESSION['users']->role
);

temp('info', "You have been logged out.");
redirect('login.php');

?>