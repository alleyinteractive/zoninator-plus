( function( $ ) {

var zone_group_manage_form = function( zone_group_id, zone_group_title, zone_group_zones ) {
	
	if( zone_group_zones == "" ) zone_group_zones = new Array();
	// Remove any forms currently in use
	$( '.zone-edit-form' ).remove();
	
	// Build HTML for the row
	var innerHTML = $( '<div></div>' );
	
	// Set the size of the select. Allow for larger groups.
	var select_size = 5;
	if( $.isArray( zone_group_zones ) && zone_group_zones.length > 5 ) select_size = zone_group_zones.length;
	
	innerHTML.append(
		$( '<tr></tr>' ).append(
			$( '<td></td>' ).append(
				$( '<input></input>' )
					.attr( 'id', 'zone-group-name' )
					.attr( 'name', 'zone_group_name' )
					.attr( 'type', 'text' )
					.attr( 'placeholder', 'Zone Group Name' )
					.attr( 'value', zone_group_title )
			)
		)
		.append(
			$( '<td></td>' )
				.addClass( 'zone-group-zones-wrapper' )
				.append(
					$( '<div></div>' )
					.addClass( 'zones-available-wrapper' )
					.addClass( 'zone-group-controls' )
					.append(
						$( '<select></select>' )
							.attr( 'id', 'zones-available' )
							.attr( 'name', 'zones_available[]' )
							.attr( 'multiple', 'multiple' )
							.attr( 'size', select_size )
					)
				)
				.append(
					$( '<div></div>' )
					.addClass( 'zone-add-remove' )
					.addClass( 'zone-group-controls' )
					.append(
						$( '<button></button>' )
							.attr( 'id', 'zone-add' )
							.attr( 'name', 'zone_add' )
							.attr( 'type', 'button' )
							.text( 'Add >' )
							.addClass( 'button' )
					)
					.append(
						$( '<button></button>' )
							.attr( 'id', 'zone-remove' )
							.attr( 'name', 'zone_remove' )
							.attr( 'type', 'button' )
							.text( '< Remove' )
							.addClass( 'button' )
					)
				)
				.append(
					$( '<div></div>' )
					.addClass( 'zones-selected-wrapper' )
					.addClass( 'zone-group-controls' )
					.append(
						$( '<div></div>' )
							.addClass( 'zones-selected-select' )
							.append(
								$( '<select></select>' )
									.attr( 'id', 'zones-selected' )
									.attr( 'name', 'zones_selected[]' )
									.attr( 'multiple', 'multiple' )
									.attr( 'size', select_size )
							)
					)
					.append(
						$( '<div></div>' )
							.addClass( 'zones-selected-order' )
							.append(
								$( '<button></button>' )
									.attr( 'id', 'zone-up' )
									.attr( 'name', 'zone_up' )
									.attr( 'type', 'button' )
									.text( 'Up' )
									.addClass( 'button' )
							)
							.append(
								$( '<button></button>' )
									.attr( 'id', 'zone-down' )
									.attr( 'name', 'zone_down' )
									.attr( 'type', 'button' )
									.text( 'Down' )
									.addClass( 'button' )
							)
					)
				)
		)
		.append(
			$( '<td></td>' ).append(
				$( '<button></button>' )
					.attr( 'id', 'zone-group-submit' )
					.attr( 'name', 'zone_group_submit' )
					.attr( 'type', 'submit' )
					.text( 'Save Zone Group' )
					.addClass( 'button-primary' )
			)
			.append(
				$( '<button></button>' )
					.attr( 'id', 'zone-group-cancel' )
					.attr( 'name', 'zone_group_cancel' )
					.attr( 'type', 'button' )
					.text( 'Cancel' )
					.addClass( 'button' )
					.addClass( 'zone-group-cancel' )
			)
		)
		.addClass( 'zone-edit-form' )
	);
		
	// Add options to the selects
	$( '#zones-available' ).children().remove();
	$.each( zp_zone_groups.zone_data, function( index, value ) {
		// Only include zones that have not yet been added to a group
		if( value.zone_group == "" ) {
			var zone_option = $( '<option></option>' ).val( value.zone.term_id ).text( value.zone.name );
			innerHTML.find( '#zones-available' ).append( zone_option );
		}
	} );
	$( '#zones-selected' ).children().remove();
	if( $.isArray( zone_group_zones ) ) {
		$.each( zone_group_zones, function( index, value ) {
			var zone_option = $( '<option></option>' ).val( value.id ).text( value.name );
			innerHTML.find( '#zones-selected' ).append( zone_option );
		} );
	}

	// Decide where to place the row based on the ID
	if( zone_group_id == "" ) {
		$( '#zone-groups > tbody > tr:first' ).before( innerHTML.html() );
		$( '#zone-group-action' ).val( 'insert' );
		$( '#zone-group-id' ).val( "" );
	} else {
		$( '#zone-group-' + zone_group_id ).hide();
		$( '#zone-group-' + zone_group_id ).after( innerHTML.html() );
		$( '#zone-group-action' ).val( 'update' );
		$( '#zone-group-id' ).val( zone_group_id );
	}	
}

$( document ).ready( function() {

	// Handle the manual backfill process
	$( '#add-zone-group' ).live( 'click', function( e ) {
		zone_group_manage_form( "", "", "" );
		e.preventDefault();
	} );
	
	// Handle the edit link
	$( '.edit-zone-group' ).live( 'click', function( e ) {
		if( $( '#zone-group-id' ).val() != "" ) $( '#zone-group-' + $( '#zone-group-id' ).val() ).show();
		zone_group_manage_form( $(this).data( 'zoneGroupId' ),  $(this).parent().siblings( '.zone-group-title' ).text(), $(this).parent().siblings( '.zone-group-zones' ).data( 'zoneGroupZones' ) );
		e.preventDefault();
	} );
	
	// Handle the delete link
	$( '.delete-zone-group' ).live( 'click', function( e ) {
		e.preventDefault();
		$( '#zone-group-id' ).val( $(this).data( 'zoneGroupId' ) );
		$( '#zone-group-action' ).val( 'delete' );
		$( '#zone-group-form' ).submit();
	} );
	
	// Handle the submit action
	$( '#zone-group-submit' ).live( 'click', function( e ) {
		e.preventDefault();
		// Select all options in the zones-selected select so they are sent in the POST data
		$( '#zones-selected option' ).each( function( index, value ) {
			$(this).attr( "selected", "selected" );
		} );
		$( '#zone-group-form' ).submit();
	} );
	
	// Handle the cancel action
	$( '.zone-group-cancel' ).live( 'click', function( e ) {
		$(this).parents('.zone-edit-form').remove();
		if( $( '#zone-group-id' ).val() != "" ) $( '#zone-group-' + $( '#zone-group-id' ).val() ).show();
	} );
	
	// Handle adding a zone to a group
	$( '#zone-add' ).live( 'click', function( e ) {
        $('#zones-available option:selected').each( function() {
                $('#zones-selected').append(
                	$( "<option></option>" )
                		.val( $(this).val() )
                		.text( $(this).text() )
                );
            $(this).remove();
        });
    });
    
    // Handle remove a zone from a group
    $( '#zone-remove' ).live( 'click', function( e ) {
        $('#zones-selected option:selected').each( function() {
            $('#zones-available').append(
                	$( "<option></option>" )
                		.val( $(this).val() )
                		.text( $(this).text() )
                );
            $(this).remove();
        });
    });
    
    // Handle moving a zone up in the order
    $( '#zone-up' ).live( 'click', function( e ) {
        $('#zones-selected option:selected').each( function() {
            var newPos = $('#zones-selected option').index(this) - 1;
            if (newPos > -1) {
                $('#zones-selected option').eq(newPos).before(
                	$( "<option></option>" )
                		.val( $(this).val() )
                		.text( $(this).text() )
                		.attr( "selected", "selected" )
                );
                $(this).remove();
            }
        });
    });
    
    // Handle moving a zone down in the order
    $( '#zone-down' ).live( 'click', function( e ) {
        var countOptions = $('#zones-selected option').size();
        $('#zones-selected option:selected').each( function() {
            var newPos = $('#zones-selected option').index(this) + 1;
            if (newPos < countOptions) {
                $('#zones-selected option').eq(newPos).after(
                	$( "<option></option>" )
                		.val( $(this).val() )
                		.text( $(this).text() )
                		.attr( "selected", "selected" )
                );
                $(this).remove();
            }
        });
    });
    
    // Add a dropdown to filter display by zone groups
    if( $( "body.toplevel_page_zoninator" ).length > 0 ) {    
		var zoneFilter = $( '<div></div>' )
		  .text( "Filter by zone group: " )
		  .addClass( "zone-group-filter" )
		  .append(
			$( '<select></select>' )
				.attr( 'id', 'zone-group-filter' )
				.attr( 'name', 'zone_group_filter' )
				.append(
					$( '<option></option>' )
						.val("-1")
						.text("Ungrouped")
				)
		  );
		
		// Add options to the filter select
		var zone_group_selected = $( '#zone-group-selected' ).val();
		$.each( zp_zone_groups.group_data, function( index, value ) {
			var zone_option = $( '<option></option>' ).val( value.term_id ).text( value.name );
			zoneFilter.find( '#zone-group-filter' ).append( zone_option );
		} );
		zoneFilter.find( "option" ).each( function( index ) {
			if( zone_group_selected == $(this).val() ) $(this).attr( "selected", "selected" );
		} );
		
		// If the zone group filter is set, add it to each tab URL to preserve it
		if( zone_group_selected != "" ) {
			$( '.zone-tabs' ).children().each( function( index ) {
				var zone_url = $(this).attr( "href" );
				zone_url = zone_url + "&zone_group_selected=" + zone_group_selected;
				$(this).attr( "href", zone_url );
			} );
		}
		
		$( '.wrap > h2' ).after( zoneFilter );
	}
	
	 // Handle a zone group filter action
    $( '#zone-group-filter' ).live( 'change', function( e ) {
    	// Get the current URL up to the first ampersand
    	var location_parts = location.href.split("&");
    	var zoninator_url = location_parts[0];
    	
    	// Add the selected group
    	zoninator_url = zoninator_url + "&zone_group_selected=" + $(this).find("option:selected").val();
    	
    	// Refresh the page
    	window.location = zoninator_url;
    });
	
} );

} )( jQuery );