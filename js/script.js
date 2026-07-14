$(document).ready(function() {
    
    // 1. ADD TO CART ACTION (Updated for dynamic stock allocation extraction)
   // 1. ADD TO CART ACTION (Enhanced to strictly validate against inventory limits)
$(".add-to-cart").on("click", function() {
    var product_id = $(this).data("product_id");
    
    // 1. Try to find a local quantity input near this specific button first, 
    // fall back to global ID fallback if it's a standalone detail page.
    var parentContainer = $(this).closest('.purchase-controls');
    var qtyInput = parentContainer.find(".product-qty");
    
    if (!qtyInput.length) {
        qtyInput = $("#purchase-qty");
    }

    // 2. Parse quantity choices and real-time backend maximum boundaries
    var qtyToAdd = qtyInput.length ? parseInt(qtyInput.val()) : 1;
    var maxStock = qtyInput.length ? parseInt(qtyInput.attr("max")) : null;

    // 3. Fallback catch: validation if string manipulation corrupted values
    if (isNaN(qtyToAdd) || qtyToAdd < 1) {
        alert("Please enter a valid quantity of 1 or more.");
        return;
    }

    // 4. Front-end roadblock: Stop execution BEFORE reaching handle_cart.php
    if (maxStock !== null && !isNaN(maxStock)) {
        if (qtyToAdd > maxStock) {
            alert("You cannot add " + qtyToAdd + " units. Only " + maxStock + " units are available in stock.");
            
            // Auto-reset their input back down to the maximum allowed limit for convenience
            if(qtyInput.length) qtyInput.val(maxStock);
            return;
        }
    }
    
    // 5. Fire AJAX Pipeline only after clearing security checks
    $.post("handle_cart.php", {
        action: "add_to_cart",
        product_id: product_id,
        quantity: qtyToAdd 
    }, function(response) {
        if (response.message === "Product added to cart." || response.message === "Product quantity updated in cart.") {
            if (confirm(response.message + " View cart?")) {
                window.location.href = "cart_view.php";
            }
        } else {
            alert(response.message);
        }
    }, "json");
});

    // 2. UPDATE QUANTITY VIA BUTTONS (+ / -) (Updated to block increments above maximum stock)
    $(document).on("click", ".update-quantity", function() {
        var cart_item_id = $(this).data("cart_item_id");
        var product_id = $(this).data("product_id");
        var change = $(this).data("change");
        
        var siblingInput = $(this).siblings(".item-quantity");
        var current_quantity = parseInt(siblingInput.val());
        var maxStock = parseInt(siblingInput.attr("max")); // Read the stock maximum bound
        var new_quantity = current_quantity + change;

        // Enforce upper boundary conditions before firing AJAX pipeline
        if (change > 0 && new_quantity > maxStock) {
            alert("Cannot increase quantity. Only " + maxStock + " units are available in inventory.");
            return;
        }

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

    // 3. UPDATE QUANTITY VIA DIRECT INPUT CHANGE (Updated to validate input against max stock limits)
    $(document).on("change", ".item-quantity", function() {
        var cart_item_id = $(this).data("cart_item_id");
        var product_id = $(this).data("product_id");        
        var new_quantity = parseInt($(this).val());
        var maxStock = parseInt($(this).attr("max"));

        if (new_quantity > maxStock) {
            alert("Invalid entry. Only " + maxStock + " units available in stock.");
            $(this).val(maxStock); // Reset input field to maximum allowed value locally
            location.reload();
            return;
        }

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