$(function () {
    //control the visibility of address fields based on selected address type
    function toggleAddressFields() {
        const isNewAddress = $('input[name="address_type"]:checked').val() === 'new';
        $('#saved-address-block').toggle(!isNewAddress);
        $('#new-address-block').toggle(isNewAddress);
    }

    //control the visibility of payment reference input field based on selected payment method
    function togglePaymentReference() {
        const isCashOnDelivery = $('#payment_method').val() === 'cash_on_delivery';
        $('#payment-reference-block').toggle(!isCashOnDelivery);
        $('#payment_reference').prop('required', !isCashOnDelivery);
    }

    //update the UI based on the points input value
    function updatePointsSummary() {
        const $input = $('#points_to_use');
        if (!$input.length) {
            return;
        }

        const subtotal = Number($input.data('subtotal'));
        const maximumPoints = Number($input.data('max-points'));
        let points = Number($input.val());

        if (!Number.isInteger(points) || points < 0) {
            points = 0;
        }

        points = Math.min(points, maximumPoints);
        $input.val(points);

        const discount = points / 100;
        const amountDue = subtotal - discount;
        $('#points-discount-display').text('- RM' + discount.toFixed(2));
        $('#amount-due-display').text('RM' + amountDue.toFixed(2));
    }

    $('input[name="address_type"]').on('change', toggleAddressFields);
    $('#payment_method').on('change', togglePaymentReference);
    $('#points_to_use').on('input change', updatePointsSummary);

    toggleAddressFields();
    togglePaymentReference();
    updatePointsSummary();
});
