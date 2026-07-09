<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?? 'Untitled' ?></title>
<link rel="stylesheet" href="/src/css/style.css">    
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="/src/js/app.js"></script>
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