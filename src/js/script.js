$(document).ready(function() {
    
    // 1. ADD TO CART ACTION
    $(".add-to-cart").on("click", function() {
        var product_id = $(this).data("product_id");
        
        // Target handle_cart.php directly since it is in the same directory as the product listing
        $.post("handle_cart.php", {
            action: "add_to_cart",
            product_id: product_id,
            quantity: 1 
        }, function(response) {
            if (response.message === "Product added to cart." || response.message === "Product quantity updated in cart.") {
                if (confirm(response.message + " View cart?")) {
                    // Redirect to cart_view.php in the same folder
                    window.location.href = "cart_view.php";
                }
            } else {
                alert(response.message);
            }
        }, "json");
    });

    // 2. UPDATE QUANTITY VIA BUTTONS (+ / -)
    $(document).on("click", ".update-quantity", function() {
        var cart_item_id = $(this).data("cart_item_id");
        var product_id = $(this).data("product_id");
        var change = $(this).data("change");
        var current_quantity = parseInt($(this).siblings(".item-quantity").val());
        var new_quantity = current_quantity + change;

        if (new_quantity > 0) {
            $.post("handle_cart.php", {
                action: "update_quantity",
                cart_item_id: cart_item_id,
                product_id: product_id,
                quantity: new_quantity
            }, function(response) {
                alert(response.message);
                if (response.message === "Cart item quantity updated.") {
                    location.reload(); 
                }
            }, "json");
        } else if (new_quantity === 0) {
            if (confirm("Are you sure you want to remove this item from your cart?")) {
                $.post("handle_cart.php", {
                    action: "remove_from_cart",
                    cart_item_id: cart_item_id
                }, function(response) {
                    alert(response.message);
                    if (response.message === "Product removed from cart.") {
                        location.reload();
                    }
                }, "json");
            }
        }
    });

    // 3. UPDATE QUANTITY VIA DIRECT INPUT CHANGE
    $(document).on("change", ".item-quantity", function() {
        var cart_item_id = $(this).data("cart_item_id");
        var product_id = $(this).closest(".quantity-controls").find(".update-quantity").data("product_id"); 
        var new_quantity = parseInt($(this).val());

        if (new_quantity > 0) {
            $.post("handle_cart.php", {
                action: "update_quantity",
                cart_item_id: cart_item_id,
                product_id: product_id,
                quantity: new_quantity
            }, function(response) {
                alert(response.message);
                if (response.message === "Cart item quantity updated.") {
                    location.reload();
                }
            }, "json");
        } else {
            alert("Quantity must be at least 1.");
            location.reload();
        }
    });

    // 4. REMOVE ITEM COMPLETELY ACTION
    $(document).on("click", ".remove-item", function() {
        var cart_item_id = $(this).data("cart_item_id");
        if (confirm("Are you sure you want to remove this item from your cart?")) {
            $.post("handle_cart.php", {
                action: "remove_from_cart",
                cart_item_id: cart_item_id
            }, function(response) {
                alert(response.message);
                if (response.message === "Product removed from cart.") {
                    location.reload();
                }
            }, "json");
        }
    });

});