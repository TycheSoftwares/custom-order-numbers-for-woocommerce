/**
 * To dismiss the success notice coming in the admin pages..
 *
 * @namespace custom_order_numbers_lite
 * @since 1.3.0
 */
jQuery(document).ready(function($) {
    jQuery( '.con-lite-success-message' ).on( 'click', '.notice-dismiss', function() {
		console.log('here');
		var data = {
			admin_choice: 'dismissed',
			action: 'alg_custom_order_numbers_admin_notice_dismiss'
		};
		jQuery.post( con_dismiss_param.ajax_url, data, function() {
		});
    });
});