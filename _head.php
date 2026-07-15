<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?? 'Untitled' ?></title>
    
    <!-- Load structural styles -->
    <link rel="stylesheet" href="css/main.css">    

    <!-- ONLY load cart specific styles on the cart view page -->
    <?php if (basename($_SERVER['PHP_SELF']) == 'cart_view.php'): ?>
        <link rel="stylesheet" href="css/cart.css">
    <?php endif; ?>
</head>
<body>
    <!-- Flash message -->
    <div id="info"><?= temp('info') ?></div>

    <header>
        <h1>Water Bottle Shop</h1>
        <nav>
            <ul>
                <li><a href="products.php">Products</a></li>
                <li><a href="cart_view.php">View Cart</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <h1><?= $_title ?? 'Untitled' ?></h1>