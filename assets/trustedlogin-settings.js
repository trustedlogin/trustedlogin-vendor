jQuery(document).ready(function( $ ) {

	$('.chosen').chosen();

	$('.is-destructive').click( function( event ) {
		event.preventDefault();

        if ( confirm( tl_obj.lang.confirm_reset ) ) {
            window.location.href = tl_obj.reset_keys_url
        }
	});

});
