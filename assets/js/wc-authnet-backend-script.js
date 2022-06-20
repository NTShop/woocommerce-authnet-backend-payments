/**
 * WC Authorize.net Backend Payment script
 *
 * Shows or hides the payment form depending on whether an existing or new card is selected.
 *
 * @package Authnet_Backend_Payments
 */
jQuery( document ).ready( function( $ ) {
	$( '.wc-authnet-token' ).change( function() {
		var token = $( this ).val();
		if ( 'new' === token) {
			$( '#wc-authnet-payment-form-wrapper' ).show();
		} else {
			$( '#wc-authnet-payment-form-wrapper' ).hide();
		}
	});
	$( '.wc-authnet-token:checked' ).trigger( 'change' );
});