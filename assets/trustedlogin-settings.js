jQuery(document).ready(function( $ ) {

	$('#tls_approved_roles').chosen();

	$('#trustedlogin-access-key').focus();

	$('#trustedlogin-reset-button').click( function( event ) {
		event.preventDefault();

        if ( confirm( tl_obj.confirm_reset ) ) {
            alert( 'Redirect authenticated user to reset url.' );
        }           
	});

});