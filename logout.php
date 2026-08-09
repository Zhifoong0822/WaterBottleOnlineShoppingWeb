<?php
require '_base.php';

unset(
    $_SESSION['users'],
    $_SESSION['user_id'],
    $_SESSION['name'],
    $_SESSION['role']
);

temp('info', "You have been logged out.");
redirect('login.php');
