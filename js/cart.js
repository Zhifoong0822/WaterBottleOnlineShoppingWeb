// File Path: js/cart.js

$(document).ready(function() {

    // 1. ADD TO CART ACTION (Redirects to products.php after adding)
    // 1. ADD TO CART ACTION (Guaranteed redirect to products.php)
    $(document).on("click", ".add-to-cart", function(e) {
        // PREVENT default form submission or link click from reloading page early!
        e.preventDefault();

        var $btn = $(this);
        var product_id = $btn.data("product_id");
        
        var parentContainer = $btn.closest('.purchase-controls');
        var qtyInput = parentContainer.find(".product-qty");
        
        if (!qtyInput.length) {
            qtyInput = $("#purchase-qty");
        }

        var qtyToAdd = qtyInput.length ? parseInt(qtyInput.val()) : 1;
        var maxStock = qtyInput.length ? parseInt(qtyInput.attr("max")) : null;
        
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
        
        // Fire AJAX Request
        $.ajax({
            url: "handle_cart.php",
            type: "POST",
            data: {
                action: "add_to_cart",
                product_id: product_id,
                quantity: qtyToAdd
            },
            dataType: "json",
            success: function(response) {
                if (response.redirect) {
                    if (response.message) {
                        alert(response.message);
                    }
                    window.location.href = response.redirect;
                    return;
                }
                if (response.message) {
                    alert(response.message);
                }
                // Redirect immediately back to products.php
                window.location.href = "products.php";
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", error, xhr.responseText);
                // Fallback: If server responded with non-JSON or failed auth, redirect to login.php
                window.location.href = "login.php";
            }
        });
    });

    // 2. UPDATE QUANTITY VIA BUTTONS (+ / -)
    $(document).on("click", ".update-quantity", function() {
        var $btn = $(this);
        var cart_item_id = $btn.data("cart_item_id");
        var product_id = $btn.data("product_id");
        var change = $btn.data("change");
        
        var siblingInput = $btn.siblings(".item-quantity");
        var current_quantity = parseInt(siblingInput.val());
        var maxStock = parseInt(siblingInput.attr("max")); 
        var new_quantity = current_quantity + change;

        // Enforce upper boundary conditions
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
                if (response.success || (response.message && response.message.toLowerCase().includes("updated"))) {
                    location.reload(); 
                } else {
                    alert(response.message);
                }
            }, "json");
        } else if (new_quantity === 0) {
            if (confirm("Are you sure you want to remove this item from your cart?")) {
                $.post("handle_cart.php", {
                    action: "remove_from_cart",
                    cart_item_id: cart_item_id
                }, function(response) {
                    if (response.success || (response.message && response.message.toLowerCase().includes("remove"))) {
                        // Visually fade out and reload
                        $btn.closest('.cart-item-card').fadeOut(300, function() {
                            $(this).remove();
                            location.reload();
                        });
                    } else {
                        alert(response.message);
                        location.reload();
                    }
                }, "json");
            }
        }
    });

    // 3. UPDATE QUANTITY VIA DIRECT INPUT CHANGE
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
                if (response.success || (response.message && response.message.toLowerCase().includes("updated"))) {
                    location.reload();
                } else {
                    alert(response.message);
                }
            }, "json");
        } else {
            alert("Quantity must be at least 1.");
            location.reload();
        }
    });

    // 4. REMOVE ITEM COMPLETELY ACTION
    $(document).on("click", ".remove-item", function() {
        var $btn = $(this);
        var cart_item_id = $btn.data("cart_item_id");

        if (confirm("Are you sure you want to remove this item from your cart?")) {
            $.post("handle_cart.php", {
                action: "remove_from_cart",
                cart_item_id: cart_item_id
            }, function(response) {
                if (response.success || (response.message && response.message.toLowerCase().includes("remove"))) {
                    // Remove card from DOM instantly and refresh page data
                    $btn.closest('.cart-item-card').fadeOut(300, function() {
                        $(this).remove();
                        location.reload();
                    });
                } else {
                    alert(response.message || "Failed to remove item.");
                    location.reload();
                }
            }, "json");
        }
    });

    // 5. MODAL EVENT HANDLERS
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

// Global Function
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
