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
    	}
	);

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
    	}
	);
    var con_apply_setting_value = $( '#alg_wc_custom_order_numbers_settings_to_apply' ).val();
    if ( con_apply_setting_value === 'new_order' || con_apply_setting_value === 'all_orders' ) {
        $( '[id=alg_wc_custom_order_numbers_settings_to_apply_from_date]' ).closest( 'tr' ).hide();
        $( '[id=alg_wc_custom_order_numbers_settings_to_apply_from_order_id]' ).closest( 'tr' ).hide();
    }
    if ( con_apply_setting_value === 'order_id' ) {
        $( '[id=alg_wc_custom_order_numbers_settings_to_apply_from_date]' ).closest( 'tr' ).hide();
    }
    if ( con_apply_setting_value === 'date' ) {
        $( '[id=alg_wc_custom_order_numbers_settings_to_apply_from_order_id]' ).closest( 'tr' ).hide();
    }
    $('#alg_wc_custom_order_numbers_settings_to_apply').change( function() {
        var con_apply_setting_value = $( '#alg_wc_custom_order_numbers_settings_to_apply' ).val();
        if ( con_apply_setting_value === 'order_id' ) {
            $( '[id=alg_wc_custom_order_numbers_settings_to_apply_from_order_id]' ).closest( 'tr' ).show();
        } else {
            $( '[id=alg_wc_custom_order_numbers_settings_to_apply_from_order_id]' ).closest( 'tr' ).hide();
        }
        if ( con_apply_setting_value === 'date' ) {
            $( '[id=alg_wc_custom_order_numbers_settings_to_apply_from_date]' ).closest( 'tr' ).show();
        } else {
            $( '[id=alg_wc_custom_order_numbers_settings_to_apply_from_date]' ).closest( 'tr' ).hide();        }
    });
    
    var today = new Date();
    $( '#alg_wc_custom_order_numbers_settings_to_apply_from_date' ).datepicker(
        {
            format: 'mm-dd-yyyy',
            autoclose: true,
            endDate: 'today',
            maxDate: today,
        }
    ).on(
        'changeDate',
        function (ev) {
            $( this ).datepicker( 'hide' );
        }
    );
    $( '#alg_wc_custom_order_numbers_settings_to_apply_from_date' ).keyup(
        function () {
            if ( this.value.match( /[^0-9]/g ) ) {
                this.value = this.value.replace( /[^0-9^-]/g, '' );
            }
        }
    );
});
