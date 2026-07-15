</main> <!-- Closes the main tag opened in header -->

    <footer>
        <p>&copy; <?= date('Y') ?> Water Bottle Shop. All rights reserved.</p>
    </footer>

    <!-- 1. Load jQuery dependency first -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

    <!-- 2. Dynamically load cart logic ONLY on pages that use the cart feature -->
    <?php 
    $current_page = basename($_SERVER['PHP_SELF']);
    $cart_supported_pages = ['products.php', 'product_detail.php', 'cart_view.php', 'index.php'];
    
    if (in_array($current_page, $cart_supported_pages)): 
    ?>
        <script src="js/cart.js"></script>
    <?php endif; ?>
</body>
</html>