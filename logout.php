<?php
require '_base.php';

unset($_SESSION['users']->user_id, $_SESSION['users']->name, $_SESSION['users']->role);

temp('info', "You have been logged out.");
redirect('login.php');