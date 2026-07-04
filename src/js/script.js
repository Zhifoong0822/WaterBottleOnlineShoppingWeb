$(document).ready(function() {
    $(".add-to-cart").on("click", function() {
        var product_id = $(this).data("product_id");
        $.post("src/php/handle_cart.php", {
            action: "add_to_cart",
            product_id: product_id,
            quantity: 1 // Always add one at a time from product listing
        }, function(response) {
            alert(response.message);
        }, "json");
    });

    $(document).on("click", ".update-quantity", function() {
        var cart_item_id = $(this).data("cart_item_id");
        var product_id = $(this).data("product_id");
        var change = $(this).data("change");
        var current_quantity = parseInt($(this).siblings(".item-quantity").val());
        var new_quantity = current_quantity + change;

        if (new_quantity > 0) {
            $.post("src/php/handle_cart.php", {
                action: "update_quantity",
                cart_item_id: cart_item_id,
                product_id: product_id,
                quantity: new_quantity
            }, function(response) {
                alert(response.message);
                if (response.message === "Cart item quantity updated.") {
                    location.reload(); // Reload to reflect changes
                }
            }, "json");
        } else if (new_quantity === 0) {
            // Optionally remove item if quantity becomes 0
            if (confirm("Are you sure you want to remove this item from your cart?")) {
                $.post("src/php/handle_cart.php", {
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

    $(document).on("change", ".item-quantity", function() {
        var cart_item_id = $(this).data("cart_item_id");
        var product_id = $(this).closest(".quantity-controls").find(".update-quantity").data("product_id"); // Get product_id from an adjacent button
        var new_quantity = parseInt($(this).val());

        if (new_quantity > 0) {
            $.post("src/php/handle_cart.php", {
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

    $(document).on("click", ".remove-item", function() {
        var cart_item_id = $(this).data("cart_item_id");
        if (confirm("Are you sure you want to remove this item from your cart?")) {
            $.post("src/php/handle_cart.php", {
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

    $("#checkout-button").on("click", function() {
        if (confirm("Proceed to checkout?")) {
            $.post("src/php/handle_checkout.php", {},
                function(response) {
                    alert(response.message);
                    if (response.order_id) {
                        window.location.href = "order_confirmation.php?order_id=" + response.order_id; // Redirect to a confirmation page
                    } else if (response.message === "Cart is empty.") {
                        location.reload();
                    }
                }, "json"
            );
        }
    });
});