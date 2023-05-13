/**
 * To dismiss the success notice coming in the admin pages..
 *
 * @namespace custom_order_numbers_lite
 * @since 1.3.0
 */
jQuery(document).ready(function($) {
    jQuery( '.con-lite-success-message' ).on(
		'click',
		'.notice-dismiss',
		function() {
			console.log( 'here' );
			var data = {
				security: con_dismiss_param.nonce,
				admin_choice: 'dismissed',
				action: 'alg_custom_order_numbers_admin_notice_dismiss'
			};
			jQuery.post( con_dismiss_param.ajax_url, data, function() {
			});
    	});

	jQuery( '.con-lite-meta-key-success-message' ).on(
		'click',
		'.notice-dismiss',
		function() {
			var data = {
				alg_admin_choice: 'dismissed',
				security: con_dismiss_param.nonce,
				action: 'alg_custom_order_numbers_admin_meta_key_notice_dismiss'
			};
			jQuery.post( con_dismiss_param.ajax_url, data, function() {
			});
    	});
});
