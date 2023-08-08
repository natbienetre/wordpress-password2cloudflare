jQuery( document ).ready( function( $ ) {
	const { _x, __ } = wp.i18n;

	const submit = $( '#submit' );
	const button = $( '<input type="button" id="p2cf-options-check-button" class="button button-secondary"></input>' )
		.on( 'click', function ( e ) {
			e.preventDefault();

			const url = new URL( $( this ).parents( 'form' ).data( 'ajax-url' ) );
			const data = new URLSearchParams( $( this ).parents( 'form' ).serialize() );
			
			data.append( 'action', 'p2cf-options-check' );

			$( '.p2cf-status' ).remove();
		
			button.prop( "disabled", true );
			fetch( url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					'Cache-Control': 'no-cache',
				},
				body: data,
			}).then(response => {
				button.prop( "disabled", false );
				
				response.json().then( data => {
					if ( data.success ) {
						group.before( $( '<div class="p2cf-status notice notice-success notice-alt inline"></div>' ).append( $( '<p></p>' ).text( __( 'Settings are valid.', 'p2cf' ) ) ) );

						return;
					}

					group.before( $( '<div class="p2cf-status notice notice-error notice-alt inline"></div>' ).append( $( '<p></p>' ).text( data.data.message ) ) );
				});
			} );
		} )
		.val( _x( 'Check the settings', 'The text on the button that ensure settings are valid', 'p2cf' ) );
	const group = $( '<div class="button-group"></div>' ).appendTo( submit.parent() ).append( button ).append( submit );
} );

jQuery( document ).ready( function( $ ) {
    $( "#p2cf-content" ).tooltip();
} );
