jQuery(document).ready(function( $ ) {

	$('#tls_approved_roles').chosen();

	$('#trustedlogin-access-key').focus();

	$('#trustedlogin-reset-button').click( function( event ) {
		event.preventDefault();

        if ( confirm( tl_obj.lang.confirm_reset ) ) {
            window.location.href = tl_obj.reset_keys_url
        }
	});

});