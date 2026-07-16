// File Path: js/cart.js

$(document).ready(function() {

    // 1. ADD TO CART ACTION (Strictly validates against inventory limits)
    $(document).on("click", ".add-to-cart", function() {
        var product_id = $(this).data("product_id");
        
        var parentContainer = $(this).closest('.purchase-controls');
        var qtyInput = parentContainer.find(".product-qty");
        
        if (!qtyInput.length) {
            qtyInput = $("#purchase-qty");
        }

        var qtyToAdd = qtyInput.length ? parseInt(qtyInput.val()) : 1;
        var maxStock = qtyInput.length ? parseInt(qtyInput.attr("max")) : null;
        
        // Grab the value of the checked sizing radio element on the detail screen
        var selectedSize = $("input[name='product-size']:checked").val() || "Medium (18oz / 530ml)";

        if (isNaN(qtyToAdd) || qtyToAdd < 1) {
            alert("Please enter a valid quantity of 1 or more.");
            return;
        }

        if (maxStock !== null && !isNaN(maxStock)) {
            if (qtyToAdd > maxStock) {
                alert("You cannot add " + qtyToAdd + " units. Only " + maxStock + " units are available in stock.");
                if(qtyInput.length) qtyInput.val(maxStock);
                return;
            }
        }
        
        // Fire AJAX Pipeline (Now bundling the size parameter option)
        $.post("handle_cart.php", {
            action: "add_to_cart",
            product_id: product_id,
            quantity: qtyToAdd,
            size: selectedSize // Sent directly to backend handle_cart.php script
        }, function(response) {
            if (response.success || response.message === "Product added to cart." || response.message === "Product quantity updated in cart.") {
                showCartConfirmation(response.message);
            } else {
                alert(response.message);
            }
        }, "json");
    });

    // 2. UPDATE QUANTITY VIA BUTTONS (+ / -) (Blocks increments above maximum stock)
    $(document).on("click", ".update-quantity", function() {
        var cart_item_id = $(this).data("cart_item_id");
        var product_id = $(this).data("product_id");
        var change = $(this).data("change");
        
        var siblingInput = $(this).siblings(".item-quantity");
        var current_quantity = parseInt(siblingInput.val());
        var maxStock = parseInt(siblingInput.attr("max")); 
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

    // 3. UPDATE QUANTITY VIA DIRECT INPUT CHANGE (Validates manual typed values)
    $(document).on("change", ".item-quantity", function() {
        var cart_item_id = $(this).data("cart_item_id");
        var product_id = $(this).data("product_id");        
        var new_quantity = parseInt($(this).val());
        var maxStock = parseInt($(this).attr("max"));

        if (new_quantity > maxStock) {
            alert("Invalid entry. Only " + maxStock + " units available in stock.");
            $(this).val(maxStock); 
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

    // 5. MODAL EVENT HANDLERS (Now kept neatly inside the document ready wrapper)
    $(document).on("click", "#cart-confirmation-close", function() {
        var modal = document.getElementById("cart-confirmation-modal");
        if (modal) {
            modal.classList.remove("is-visible");
            modal.setAttribute("aria-hidden", "true");
        }
    });

    $(document).on("click", "#cart-confirmation-view", function() {
        window.location.href = "cart_view.php";
    });

    $(document).on("click", "#cart-confirmation-modal", function(event) {
        if (event.target === this) {
            $("#cart-confirmation-close").trigger("click");
        }
    });

});

// Global Function (Must remain outside of ready wrapper so other files can trigger it)
function showCartConfirmation(message) {
    var modal = document.getElementById("cart-confirmation-modal");

    if (!modal) {
        if (confirm(message + " View cart?")) {
            window.location.href = "cart_view.php";
        }
        return;
    }

    document.getElementById("cart-confirmation-message").textContent = message + " View your cart?";
    modal.classList.add("is-visible");
    modal.setAttribute("aria-hidden", "false");
    document.getElementById("cart-confirmation-view").focus();
}