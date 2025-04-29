/**
 * WooCommerce Create User from Guest Orders - Admin JS
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Use event delegation for dynamically added buttons
        $(document.body).on('click', '.wcufg-create-user-btn', function(e) {
            e.preventDefault();
            
            const orderId = $(this).data('order-id');
            const nonce = $(this).data('nonce');
            const href = $(this).attr('href');
            
            // Ask for confirmation
            const sendEmail = confirm(
                'Do you want to send login credentials to this user?'
            );
            
            // Construct the URL with appropriate parameters
            let redirectUrl = href + '&send_email=' + (sendEmail ? '1' : '0');
            
            // Redirect to the appropriate URL
            window.location.href = redirectUrl;
        });
    });
})(jQuery);