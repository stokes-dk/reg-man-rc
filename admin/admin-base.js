/**
 * This is the main javascript for the admin interface
 */
jQuery(document).ready(function($) {
	$( '.reg-man-rc-tabs-container' ).on( 'initialize-tabs', function( evt ) {
		var me = $( this );
		me.tabs({
			activate	: function( evt, ui ) {
				me.trigger( 'tab-activated' );
			}
		});
		me.trigger( 'tabs-init-complete' );
	});
	$( '.reg-man-rc-tabs-container' ).on( 'tabs-init-complete', function( evt ) {
		var me = $( this );
		me.addClass( 'tabs-init-complete' );
	});
	$( '.reg-man-rc-tabs-container' ).trigger( 'initialize-tabs' );
	
	$( '.calendar-event-category-selection input[name="event_category_any"]' ).change( function( evt ) {
		var me = $(this);
		if ( me.val() == 'TRUE' ) {
			$('.calendar-event-category-some-fieldset input[name="event_category[]"]').prop( 'disabled', true );
		} else {
			$('.calendar-event-category-some-fieldset input[name="event_category[]"]').prop( 'disabled', false );		
		} // endif
	});
	
	$( '#reg-man-rc-event-venue-metabox' ).on( 'update-venue-location-view', function( evt, update_map_markers ) {
		var metabox = $(this);
		var select = metabox.find( 'select[name="event_venue_select"]' );
		var loc_container = metabox.find( '.reg-man-rc-venue-location-container' );
		var name_input_item = loc_container.find( '.input-item.venue-name' );
		var name_input = name_input_item.find( 'input[name="venue_name"]' );
		var loc_input_item = loc_container.find( '.input-item.venue-location' );
		var loc_input = loc_input_item.find( 'input[name="venue_location"]' );
		var map_container = loc_container.find( '.reg-man-rc-google-map-container' );
		var val = select.val();
		if ( ( val == '0' ) || ( val == null ) ) {
			// No event venue selected, so blank out and disable inputs
			name_input.val( '' );
			loc_input.val( '' );
			name_input_item.slideUp();
			if ( update_map_markers ) { // during map initialization this will be done automatically
				map_container.trigger( 'set-map-markers', [ [ ] ] );
			} // endif
			name_input.prop( 'disabled', true );
			loc_input.prop( 'disabled', true );
			map_container.trigger( 'disable-user-input' );
			loc_container.fadeTo( 'fast', 0.5 );
		} else if ( val == '-1' ) {
			// Add a new event venue, show inputs and enable them
			name_input.val( '' );
			loc_input.val( '' );
			name_input_item.slideDown();
			if ( update_map_markers ) { // during map initialization this will be done automatically
				map_container.trigger( 'set-map-markers', [ [ ] ] );
			} // endif
			name_input.prop( 'disabled', false );
			loc_input.prop( 'disabled', false );
			map_container.trigger( 'enable-user-input' );
			loc_container.fadeTo( 'fast', 1 );
		} else {
			// A specific venue is selected, disable the inputs, hide the venue name input and show the rest
			var marker_id = 'reg-man-rc-venue-' + val; // This is the ID for the map marker stored in our json data
			var marker_data_array = metabox.data( 'marker-array' );

			var marker_data_array_len = ( marker_data_array !== undefined ) ? marker_data_array.length : 0;
			if ( marker_data_array_len > 0 ) {
				var marker_data, marker_args, marker;
				var found = false;
				for ( var i = 0; i < marker_data_array_len; i++ ) {
					marker_data = marker_data_array[i];
					if ( marker_data.id == marker_id ) {
						name_input.val( marker_data.name );
						loc_input.val( marker_data.location );
						if ( update_map_markers ) { // during map initialization this will be done automatically
							map_container.trigger( 'set-map-markers', [ [ marker_data ] ] );
						} // endif
						found = true;
						break; // we found it so stop looking
					} // endif
				} // endfor
				if ( ! found ) {
					name_input.val( '' );
					loc_input.val( '' );
					if ( update_map_markers ) { // during map initialization this will be done automatically
						map_container.trigger( 'set-map-markers', [ [ ] ] );
					} // endif
				} // endif
			} // endif

			name_input_item.slideUp();
			name_input.prop( 'disabled', true );
			loc_input.prop( 'disabled', true );
			map_container.trigger( 'disable-user-input' );
			loc_container.fadeTo( 'fast', 1 );
		} // endif
	});
	$( '#reg-man-rc-event-venue-metabox select[name="event_venue_select"]' ).change( function( evt ) {
		$(this).trigger( 'update-venue-location-view', [ true ] );
	});
	$( '#reg-man-rc-event-venue-metabox' ).on( 'initialize-venue-marker-data', function ( evt ) {
		var me = $(this);
		// Create the marker data object so I can switch between venues
		var marker_json_data = me.find( '.reg-man-rc-event-venue-marker-json' ).text();
		// Wordpress (on the front end) will filter and replace html chars like '-' and replace with '&#8211;'
		// In order to change them back to regular characters for use in my map I need to create a textarea
		//  insert the data into it then ask for the text back
		marker_json_data = $('<textarea />').html( marker_json_data ).text();
		if ( marker_json_data.length === 0 ) {
			marker_json_data = '[]';
		} // endif
		var marker_array = JSON.parse( marker_json_data );
		me.data( 'marker-array', marker_array );
		me.trigger( 'update-venue-location-view', [ false ] );
	});
	if ( $( '#reg-man-rc-event-venue-metabox .reg-man-rc-google-map-container' ).length === 0 ) {
		// When there is no google map in the venue metabox we will trigger the init right away
		$( '#reg-man-rc-event-venue-metabox' ).trigger( 'initialize-venue-marker-data' );
	} else {
		// When there IS a google map in the metabox we'll trigger init AFTER the map has initialized
		$( '#reg-man-rc-event-venue-metabox' ).on( 'map-initialized', function ( evt ) {
			var me = $(this);
			me.trigger( 'initialize-venue-marker-data' );
		});
	} // endif
	
	$( '.reg-man-rc-item.fixer-station-metabox select[name="fixer_station"]' ).on( 'update-item-fixer-station', function( evt, fixer_station ) {
		var me = $(this);
		me.val( fixer_station );
	});
	$( '.reg-man-rc-item.item-type-metabox select[name="item_type"]' ).change( function( evt ) {
		var me = $(this);
		var selected = me.find( ':selected' );
		var fixer_station = selected.data( 'fixer-station' );
		$( '.reg-man-rc-item.fixer-station-metabox select[name="fixer_station"]' ).trigger( 'update-item-fixer-station', fixer_station );
	});

	$( '.reg-man-rc-item.visitor-metabox' ).on( 'update-visitor-inputs', function( evt ) {
		var me = $(this);
		var select = me.find( 'select[name="item_visitor_select"]' );
		var visitor_id = select.val();
		var visitor_details = me.find( '.visitor-details-container' );
		var public_name = visitor_details.find( 'input[name="visitor_public_name"]' );
		if ( visitor_id == '-1' ) {
			visitor_details.slideDown();
			public_name.prop( 'required', true );
		} else {
			visitor_details.slideUp();
			public_name.prop( 'required', false );
		} // endif
	});
	$( '.reg-man-rc-item.visitor-metabox' ).trigger( 'update-visitor-inputs' );
	$( '.reg-man-rc-item.visitor-metabox select[name="item_visitor_select"]' ).change( function( evt ) {
		$(this).trigger( 'update-visitor-inputs' );
	});

	
/* This is to allow updates to visitor records - not implemented yet
	$( '.reg-man-rc-item.visitor-metabox input[name="is_update_visitor_record"]' ).change( function( evt ) {
		var me = $(this);
		var checked = me.prop( 'checked' );
	});
*/
	
	$( '.reg-man-rc-volunteer-reg.volunteer-metabox select[name="volunteer_registration_volunteer"]' ).change( function( evt ) {
		var me = $(this);
		me.trigger( 'update-volunteer-inputs' );
		me.trigger( 'update-volunteer-station-and-roles' );
	});
	$( '.reg-man-rc-volunteer-reg.volunteer-metabox' ).on( 'update-volunteer-inputs', function( evt ) {
		var me = $(this);
		var select = me.find( 'select[name="volunteer_registration_volunteer"]' );
		var volunteer_id = select.val();
		var volunteer_details = me.find( '.add-volunteer-input-list' );
		var public_name = volunteer_details.find( 'input[name="volunteer_public_name"]' );
		if ( volunteer_id == '-1' ) {
			volunteer_details.slideDown();
			public_name.prop( 'required', true );
		} else {
			volunteer_details.slideUp();
			public_name.prop( 'required', false );
		} // endif
	});
	
	$( '.reg-man-rc-volunteer-reg.volunteer-metabox select[name="volunteer_registration_volunteer"]' ).on( 'update-volunteer-station-and-roles', function( evt ) {		
		var me = $(this);
		var option = me.find( ':selected' );
		var station_id = option.data( 'station' );
		var input = $( 'input[name="fixer_station"][value="' + station_id + '"]' );
		input.prop( 'checked', true );
		
		var roles_list = option.data( 'roles' ) + ''; // make sure it's a string and not just a number
		var roles_array = roles_list.split(',');
		var role_id;
		var role_inputs = $( 'input[name="volunteer_role[]"]' );
		$.each( role_inputs, function( index ) {
			input = $( role_inputs[ index ] );
			if ( $.inArray( input.val(), roles_array ) !== -1 ) {
				input.prop( 'checked', true );
			} else {
				input.prop( 'checked', false );				
			} // endif
		});
	});

	$( '.media-library-select-container .media-library-launch' ).on( 'click', function( evt ) {

		evt.preventDefault();

		var me = $(this);
		var container = me.closest( '.media-library-select-container' );
		var img_container = container.find( '.media-library-img-container' );
		var id_input = container.find( 'input.media-library-attachment-id' );
		var wp_media_ui = me.data( 'wp-media-ui' ); // We will create the ui once and save it for later
		if ( wp_media_ui === undefined ) {
			wp_media_ui = wp.media({
				title		: __( 'Select image', 'reg-man-rc' ),
				library		: {
					type : 'image'
				},
				button: {
					text: __( 'Use this image', 'reg-man-rc' ) // button label text
				},
				multiple: false
			}).on( 'select', function() { // it also has "open" and "close" events
				var attachment = wp_media_ui.state().get( 'selection' ).first().toJSON();
				img_container.html( '<img src="' + attachment.url + '">' );
				id_input.val( attachment.id );
			});
			me.data( 'wp-media-ui', wp_media_ui );
		} // endif
		wp_media_ui.open();
	});

	$( '.media-library-select-container .media-library-remove' ).on( 'click', function( evt ) {
		
		evt.preventDefault();

		var me = $(this);
		var container = me.closest( '.media-library-select-container' );
		var img_container = container.find( '.media-library-img-container' );
//		var id_input = container.find( 'input[name="media-library-attachment-id"]' );
		var id_input = container.find( 'input.media-library-attachment-id' );

		img_container.html( '' );
		id_input.val( '' );
	});

	$( '.tablenav .actions .reg-man-rc-filter, .tablenav .actions #filter-by-date' ).on( 'set-filter-active', function( evt ) {
		// Make it clear to the end user when one of the filters is active
		var me = $( this );
		var val = me.val();
		if ( ( val !== '0' ) && ( val == me.data( 'initial-value' ) ) ) {
			me.addClass( 'filter-active' );
		} else {
			me.removeClass( 'filter-active' );
		} // endif
	});
	$( '.tablenav .actions .reg-man-rc-filter, .tablenav .actions #filter-by-date' ).on( 'initialize', function( evt ) {
		var me = $( this );
		me.data( 'initial-value', me.val() );
		me.trigger( 'set-filter-active' );
	});
	$( '.tablenav .actions .reg-man-rc-filter, .tablenav .actions #filter-by-date' ).trigger( 'initialize' ); // initialize
	$( '.tablenav .actions .reg-man-rc-filter, .tablenav .actions #filter-by-date' ).on( 'change', function( evt ) {
		var me = $( this );
		me.removeClass( 'filter-active' );
	});
	
	$( '.reg-man-rc-admin-object-list-datatable' ).on( 'init-datatable', function( evt, datatable_args ) {
		var me = $(this);
		datatable_args.initComplete = function( settings, json ) { me.addClass( 'datatable-init-complete' ); };
		var my_data_table = me.DataTable( datatable_args );
		me.data( 'my-datatable', my_data_table );
	});
	
	// TODO: use class names instead of column index - see stats.js
	$( '.reg-man-rc-admin-object-list-datatable.volunteer-my-reg-table' ).trigger( 'init-datatable', [{
		'columnDefs'	: [
			{
				'targets'	: [ 0 ],
				'visible'	: false // Hide the name column when showing registrations for a volunteer (always the same)
				// Note that this is also hidden with css in order to stop a flash of unformatted content
			},
			{
				'targets'		: 1, // Order the date column using data from Date ISO 8601
				'orderData'		: 5
			},
			{
				'targets'		: 5, // Hide the Date ISO 8601
				'visible'		: false,
				'searchable'	: false,
			},
		],
		'order'			: [ [ 1, 'desc' ] ]
	}] );
	$( '.reg-man-rc-admin-object-list-datatable.visitor-my-reg-table' ).trigger( 'init-datatable', [{
		'columnDefs'	: [
			{
				'targets'	: [ 0, 3, 4 ],
				'visible'	: false // Hide the name column when showing registrations for a visitor (always the same)
				// Also hide the first event and join mail list columns because these are shown elseswhere on the page
			},
			{
				'targets'		: 1, // Order the date column using data from Date ISO 8601
				'orderData'		: 6
			},
			{
				'targets'		: 6, // Hide the Date ISO 8601
				'visible'		: false,
				'searchable'	: false,
			},
		],
		'order'			: [ [ 1, 'desc' ] ]
	}] );
	$( '.reg-man-rc-ajax-form.reg-man-rc-admin-user-help-form.reg-man-rc-admin-notice-form' ).on( 'submit-success', function( evt ) {
		var me = $( this );
		var notice_container = me.closest( '.reg-man-rc-admin-user-help-notice' );
		notice_container.hide();
	});
	$( '.reg-man-rc-sup-item-table .sup-item-add-row-button' ).on( 'click', function( evt ) {
		var me = $( this );
		var table = me.closest( '.reg-man-rc-sup-item-table' );
		var template_row = table.find( '.reg-man-rc-sup-item-data-template' );
		var new_row = template_row.clone();
		new_row.removeClass( 'reg-man-rc-sup-item-data-template' );
		new_row.insertBefore( template_row );
	});
	
	$( '.reg-man-rc-admin-create-item-sugg-table .select-all-button' ).on( 'click', function( evt ) {
//		alert( 'select all' );
		var me = $( this );
		var table = me.closest( '.reg-man-rc-admin-create-item-sugg-table' );
		table.find( 'input.create_suggestion').prop( 'checked', true );
	});
	$( '.reg-man-rc-admin-create-item-sugg-table .select-none-button' ).on( 'click', function( evt ) {
//		alert( 'select none' );
		var me = $( this );
		var table = me.closest( '.reg-man-rc-admin-create-item-sugg-table' );
		table.find( 'input.create_suggestion').prop( 'checked', false );
	});
	$( '.reg-man-rc-event-fixer-station-metabox input[name="is_non_repair_event"]' ).on( 'change', function( evt ) {
		var me = $( this );
		var is_checked = me.prop( 'checked' );
		var metabox = me.closest( '.reg-man-rc-event-fixer-station-metabox' );
		var options = metabox.find( 'input[name="fixer_station[]"' );
		options.prop( 'disabled', is_checked );
	});
	
});