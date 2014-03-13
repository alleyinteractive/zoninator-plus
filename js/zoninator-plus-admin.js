( function( $ ) {

$( document ).ready( function() {

	// Handle the manual backfill process
	$( '#zp_do_backfill' ).live( 'click', function( e ) {
		$(this).val( "Backfilling..." ).attr( "disabled", "disabled" );
		$( "#zp_backfill_message" ).html("");
		
		// Run the backfill (don't run if the nonce isn't present)
		if( zp_admin_nonce != undefined && !$.isEmptyObject( zp_admin_nonce ) ) {
			$.post( ajaxurl, { action: 'zoninator_plus_backfill', _wpnonce: zp_admin_nonce.value }, function ( response ) {
				$( '#zp_do_backfill' ).parent().html( response );
			});	
		}
	} );
	
} );

} )( jQuery );