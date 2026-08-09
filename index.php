<?php
require '_base.php';

$_title = "Welcome";

include '_head.php';
?>

<p>Welcome back, <?= encode($_SESSION['name']) ?>!</p>

<h3>Customer</h3>
<a href="order_history.php">My Orders</a>
<br>
<a href="member_registration.php">Member Registration</a>

<br><br>

<?php if ($_SESSION['role'] === 'admin'): ?>
    <h3>Admin</h3>
    <a href="pages/admin/admin_products.php">Manage Products</a>
    <br>
    <a href="admin_orders.php">Manage Orders</a>

    <br><br>
<?php endif; ?>
<h3>Admin</h3>
<a href="pages/admin/admin_products.php">Manage Products</a>
<br>
<a href="admin_orders.php">Manage Orders</a>
<br>
<a href="pages/admin/member_listing.php">Member Listing</a>
<br><br>

<h3>Products</h3>
<a href="products.php">Go to Products</a>

<?php
include '_foot.php';
?>