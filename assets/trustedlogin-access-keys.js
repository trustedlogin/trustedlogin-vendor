jQuery( document ).ready( function ( $ ) {

	$( '#trustedlogin-access-key-login' ).on( 'submit', function ( e ) {

		e.preventDefault();

		var $form = $( this );

		$.ajax( {
			url: tl_access_keys.ajaxurl,
			type: 'POST',
			dataType: 'json',
			data: $form.serialize(),
			success: function ( response ) {

				if ( tl_access_keys.debug ) {
					console.log( 'TrustedLogin response: ', response );
				}

				// Create a temporary form to redirect to the site.
				$redirect_form = $( '<form method="post"></form>' )
					.attr( 'action', response.siteurl )
					.append( $('<input type="hidden" name="action">').attr( 'value', 'trustedlogin' ) )
					.append( $('<input type="hidden" name="endpoint">').attr('value', response.endpoint ) )
					.append( $('<input type="hidden" name="identifier">').attr('value', response.identifier ) )
					.appendTo( $( '.trustedlogin-response__success' ) );

				$redirect_form.submit();

				// Clean up; remove the form from the DOM just in case? Not sure how we'd get here.
				$redirect_form.remove();
			},
			error: function ( response ) {
				$( '.trustedlogin-response__error' ).html( '<p>' + response.responseJSON.data + '</p>' );

				if ( ! tl_access_keys.debug ) {
					return;
				}

				console.log( 'TrustedLogin response: ', response );
			},
		} ).always( function ( response ) {

			if ( ! tl_access_keys.debug ) {
				return;
			}

			console.log( 'TrustedLogin response: ', response );
		} );

		return false;
	} );
} );
