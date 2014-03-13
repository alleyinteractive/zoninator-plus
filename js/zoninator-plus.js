( function( $ ) {

var zp_check_zone_backfill = function() {
	if( $("#zone-backfill option:selected").val() == "none" ) {
		$("#zone-allowed-content-types").parent().hide();
		$("#zone-content-terms").parent().hide();
		zp_hide_backfill_button();
	} else {
		$("#zone-allowed-content-types").parent().show();
		$("#zone-content-terms").parent().show();
		zp_show_backfill_button();
	}
	
}

var zp_show_backfill_button = function() {
	$( ".zone-posts-wrapper .zone-search-wrapper" ).last().after( function() {
		var innerHTML = $( '<div></div>' );
		
		innerHTML.append(
			$( '<div></div>' )
				.addClass( 'zone-search-wrapper' )
				.addClass( 'zone-backfill-wrapper' )
				.append(
					$( '<button></button>' )
						.text( 'Run Backfill' )
						.attr( 'id', 'zp-do-backfill' )
						.addClass( 'button-primary' )
				)
		);
		
		return innerHTML.html();
	} );
}

var zp_hide_backfill_button = function() {
	$( ".zone-backfill-wrapper" ).remove();
}

$( document ).ready( function() {

	// Check if we should display the backfill configuration fields when the backfill type changes
	$( '#zone-backfill' ).live( 'change', function( e ) {
		zp_check_zone_backfill();
	} );
	
	// Update the stored display values for post types on change
	$( "#zone-allowed-content-types-display" ).chosen().change( function() {
		var types = new Array();
		$(this).find( "option:selected" ).each( function( index ) {
			var type = {};
			type.label = $(this).text();
			type.name = $(this).val();
			types.push( type );
		} );
		$( '#zone-allowed-content-types' ).val( JSON.stringify( types ) );
	} );
	
	// Update the stored display values for post types on change
	$( "#zone-content-terms-display" ).chosen().change( function() {	
		var terms = new Array();
		$(this).find( "option:selected" ).each( function( index ) {
			var term = {};
			term.label = $(this).text();
			term.id = $(this).val();
			term.taxonomy = $(this).parent().data( 'taxonomy' );
			terms.push( term );
		} );
		$( '#zone-content-terms' ).val( JSON.stringify( terms ) );
	} );
	
	$( '#zp-do-backfill' ).live( 'click', function( e ) {
		console.log( "starting backfill" );
		$.post( ajaxurl, { 
			action: 'zoninator_plus_backfill', 
			_wpnonce: zp_backfill_nonce.value, 
			zone_group_id: $( '#zone-group option:selected' ).val(),
			zone_id: $( '#zone_id' ).val()
		}, 
		function ( response ) {
			console.log( response );
			location.reload();
		} );
	} );
	
	// Check if we should display the backfill configuration fields on load
	zp_check_zone_backfill();
	
} );

} )( jQuery );