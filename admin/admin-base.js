/**
 * This is the main javascript for the admin interface
 */
jQuery(document).ready(function($) {
	
	$( '.reg-man-rc-tabs-container' ).on( 'initialize-tabs', function( evt ) {
		var me = $( this );
		var var_name = me.data( 'save-active-tab' );
		var active_index = 0;
		if ( var_name !== undefined ) {
			var_name = var_name + window.location.pathname +  window.location.search;
			active_index = localStorage[ var_name ] ? localStorage[ var_name ] : 0;
		} // endif
		me.tabs({
			activate	: function( evt, ui ) {
				if ( var_name !== undefined ) {
					localStorage[ var_name ] = ui.newTab.index();
				} // endif
				me.trigger( 'tab-activated', [ ui.newTab.index() ] );
			},
			active		: active_index
		});
		me.trigger( 'tabs-init-complete' );
	});
	$( '.reg-man-rc-tabs-container' ).on( 'tabs-init-complete', function( evt ) {
		var me = $( this );
		me.addClass( 'tabs-init-complete' );
	});
	$( '.reg-man-rc-tabs-container' ).trigger( 'initialize-tabs' );
	
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
	
	$( '.reg-man-rc-item.visitor-metabox' ).on( 'update-visitor-inputs', function( evt ) {
		var me = $(this);
		var select = me.find( 'select[name="item_visitor"]' );
		var visitor_id = select.val();
		var visitor_details = me.find( '.add-visitor-input-list' );
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
	$( '.reg-man-rc-item.visitor-metabox select[name="item_visitor"]' ).change( function( evt ) {
		$(this).trigger( 'update-visitor-inputs' );
	});

	$( '.reg-man-rc-volunteer-reg.event-metabox' ).on( 'update-volunteer-reg-options-enablement', function( evt ) {
		// Disable volunteers already registered for currently selected event
		var me = $(this);
		event_select = me.find( 'select[name="volunteer_registration_event"]' );
		var selected_option = event_select.find( ':selected' );
		var vol_ids = selected_option.data( 'vol-ids' );
		if ( vol_ids !== undefined ) {
			var vol_select = $( '.reg-man-rc-volunteer-reg.volunteer-metabox select[name="volunteer_registration_volunteer"]' );
			var i, curr_vol_id, vol_option;
			vol_select.find( 'option' ).attr( 'disabled', false ); // enable everything
			vol_option = vol_select.find( 'option[value=""]' ).attr( 'disabled', true ); // disable "please select" if exists
			for( i = 0; i < vol_ids.length; i++ ) {
				curr_vol_id = vol_ids[ i ];
				vol_option = vol_select.find( 'option[value="' + curr_vol_id + '"]' );
				vol_option.attr( 'disabled', true );
			} // endfor
			
			// We need to re-enable the original volunteer for the original event for this registration
			var selected_event_key = selected_option.val();
			var original_event_key = me.data( 'original-event' );
			var original_vol_id = me.data( 'original-vol-id' );
			
			if ( ( original_event_key == selected_event_key ) && ( original_vol_id !== '' ) ) {
				vol_option = vol_select.find( 'option[value="' + original_vol_id + '"]' );
				vol_option.attr( 'disabled', false );
			} // endif
		} // endif
	});
	
	$( '.reg-man-rc-volunteer-reg.event-metabox' ).trigger( 'update-volunteer-reg-options-enablement' ); // initialize
	$( '.reg-man-rc-volunteer-reg.event-metabox select[name="volunteer_registration_event"]' ).change( function( evt ) {
		var me = $(this);
		me.trigger( 'update-volunteer-reg-options-enablement' );
	});

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
		var is_apprentice = option.data( 'is-apprentice' );
		var input = $( 'input[name="fixer_station"][value="' + station_id + '"]' );
		input.prop( 'checked', true );
		input = $( 'input[name="is_apprentice"]' );
		input.prop( 'checked', is_apprentice );
		
		var roles_list = option.data( 'roles' ) + ''; // make sure it's a string and not just a number
		var roles_array = roles_list.split(',');
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
/* FIXME - NOT USED
	$( '.media-library-select-container .media-library-launch' ).on( 'click', function( evt ) {

		evt.preventDefault();

		var me = $(this);
		var container = me.closest( '.media-library-select-container' );
		var clone_source = container.find( '.media-library-select-container-clone-item' );
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
				var item_list = clone_source.closest( 'ul' );
				var attachment = wp_media_ui.state().get( 'selection' ).first().toJSON();
				var new_item = clone_source.clone();
				var id_input = new_item.find( 'input[name="media-library-attachment-id[]"]' );
				id_input.prop( 'disabled', false );
				var img = $( '<img src="' + attachment.url + '">' );
				new_item.prepend( img );
				id_input.val( attachment.id );
				new_item.removeClass( 'media-library-select-container-clone-item' );
				item_list.append( new_item );
			});
			me.data( 'wp-media-ui', wp_media_ui );
		} // endif
		wp_media_ui.open();
	});

	$( '.media-library-select-container' ).on( 'click', ' .media-library-remove', function( evt ) {
		evt.preventDefault();
		var me = $(this);
		var list_item = me.closest( 'li' );
		list_item.remove();
	});

	$( '.media-library-select-container .fixer-station-icons-list' ).sortable();
*/
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

	$( '.event-dates-and-times-container' ).on( 'get-recur-event-input-values', function( evt ) {
		var me = $( this );
//		var recur_input_list = me.find( '.recurring-event-input-list' );
		var recur_input_values = me.data( 'recur_input_values' );
		
		if ( ! recur_input_values ) {
			recur_input_values = []; // create an object with the input values so we can notify listeners
			
			var inputs = me.find( '.rrule-text-input input, .rrule-text-input select' );
			var curr_input, curr_input_name;

			// Put my inputs into the feed params for the calendar
			inputs.each( function() {
				curr_input = $( this );
				curr_input_name = curr_input.attr( 'name' );
				recur_input_values[ curr_input_name ] = curr_input.val();
			});

			// This is an array of checkboxes so it's more complicated
			var weekly_by_day_values = [];
			var weekly_by_day_inputs = me.find( '.rrule-weekly-by-day-input input:checked' );
			weekly_by_day_inputs.each( function() {
				curr_input = $( this );
				weekly_by_day_values.push( curr_input.val() );
			});
			recur_input_values[ 'recur_weekly_by_day_json' ] = JSON.stringify( weekly_by_day_values );

			// These are multi selects so it's more complicated
			var monthly_by_day_select = me.find( 'select[ name="recur_monthly_by_day[]" ]' );
			recur_input_values[ 'recur_monthly_by_day_json' ] = JSON.stringify( monthly_by_day_select.val() );
			
			var yearly_by_month_select = me.find( 'select[ name="recur_yearly_by_month[]" ]' );
			recur_input_values[ 'recur_yearly_by_month_json' ] = JSON.stringify( yearly_by_month_select.val() );

			var yearly_by_day_select = me.find( 'select[ name="recur_yearly_by_day[]" ]' );
			recur_input_values[ 'recur_yearly_by_day_json' ] = JSON.stringify( yearly_by_day_select.val() );
			
			var cancel_date_input = me.find( 'select[ name="recur_cancel_dates[]" ]' );
			var cancel_dates_array = cancel_date_input.val();
			recur_input_values[ 'recur_cancel_dates' ] = JSON.stringify( cancel_dates_array );

			// Make sure all the required inputs have a value then trigger an update to my listeners
// DKS Oct 28 2024 - Because event category is required, the event dates won't update until a category is selected
// Rather than force validation on the client side, we'll let the server figure if anything is missing  
/*
			var is_valid;
			var parent_form = me.closest( 'form' );
			var parent_form_element = parent_form[ 0 ];
			is_valid = parent_form_element.checkValidity();
			if ( is_valid ) {
*/
				me.data( 'recur_input_values', recur_input_values );
//			} // endif
			
		} // endif
	});
	
	$( '.event-dates-and-times-container .recur-rule-input' ).on( 'change', function( evt ) {
		var me = $( this );
		var container = me.closest( '.event-dates-and-times-container' );
		container.removeData( 'recur_input_values' ); // These need to be re-acquired
		container.trigger( 'recur-event-dates-settings-change' );
	});
	
	$( '.event-dates-and-times-container' ).on( 'recur-event-dates-settings-change', function( evt ) {
		var me = $( this );

		me.trigger( 'get-recur-event-input-values' ); // will assign to data attribute

		var recur_input_values = me.data( 'recur_input_values' ); // check to make sure we have input values
		if ( recur_input_values ) {

			// Update the calendar with the new input values
			me.trigger( 'update-recur-events-calendar' );
			
			// Update the recurring event dates and notify all listeners
			me.trigger( 'update-recur-event-dates' );
			 
		} // endif
	});
	
	$( '.event-dates-and-times-container' ).on( 'update-recur-event-dates', function( evt ) {
		var me = $( this );

		me.trigger( 'get-recur-event-input-values' ); // will assign to data attribute

		var recur_input_values = me.data( 'recur_input_values' ); // check to make sure we have input values
		if ( recur_input_values ) {

			var listeners = me.find( '.recur-event-dates-change-listener' );

			// Get the new event dates and update listeners
			var ajax_url = me.data( 'get-recur-event-dates-ajax-url' );
			var ajax_action = me.data( 'get-recur-event-dates-ajax-action' );
			var nonce = me.data( 'get-recur-event-dates-ajax-nonce' );
			
			var ajax_data = { action: ajax_action };
			for( var key in recur_input_values ) {
				ajax_data[ key ] = recur_input_values[ key ];
			} // endfor
			ajax_data[ '_wpnonce' ] = nonce;
			
			var settings = {
				url			: ajax_url,
				data		: ajax_data,
				type		: 'GET',
				dataType	: 'json'
			};

			$.ajax(
					settings
				).done( function( response, textStatus, jqXHR ) {
					listeners.trigger( 'recur-event-dates-change', [ response ] );
				}).fail( function( jqXHR, textStatus, error ) {
					console.log( 'Ajax submit form failed, text status: ' + textStatus + ', error: ' + error );
	//				form.trigger( 'submit-fail', [ jqXHR, textStatus, error ] );
				}).always( function( ) {
	//				form.trigger( 'submit-end' );
			});

		} // endif
	});


	$( '.event-dates-and-times-container .recur-event-dates-multi-select' ).on( 'recur-event-dates-change', function( evt, response ) {
		var me = $( this );
		var curr_selected = me.val();
		if ( response ) {
			me.empty();
			var new_option;
			$.each( response, function( value, event_data ) {
				new_option = $( '<option>', {
					value: value,
					text: event_data.label
				}, '</option>' );
				me.append( new_option );
			});
			me.val( curr_selected ); // reset the selected options if possible
		} // endif
	});

	$( '.event-dates-and-times-container select[ name="recur_cancel_dates[]" ]' ).on( 'change', function( evt ) {
		var me = $( this );
		// Update the cancel dates and then update the calendar
		var container = me.closest( '.event-dates-and-times-container' );
		container.trigger( 'get-recur-event-input-values' ); // Make sure we have the input values, if possible
		var recur_input_values = container.data( 'recur_input_values' );
		if ( recur_input_values ) {
			// Update the data for the recur event dates to include the new cancel dates then update the calendar
			var cancel_dates_array = me.val();
			recur_input_values[ 'recur_cancel_dates' ] = JSON.stringify( cancel_dates_array );
			container.data( 'recur_input_values', recur_input_values );
			container.trigger( 'update-recur-events-calendar' );
		} // endif
	});

	$( '.event-dates-and-times-container' ).on( 'update-recur-events-calendar', function( evt ) {
		var me = $( this );
		var calendar = me.find( '.event-dates-calendar' );
		var calendar_view = calendar.find( '.reg-man-rc-calendar-view' );

		var recur_input_values = me.data( 'recur_input_values' );
		
		var feed_params = calendar_view.data( 'feed-params' );

		if ( feed_params && recur_input_values ) {
			for( var key in recur_input_values ) {
				feed_params[ key ] = recur_input_values[ key ];
			} // endfor
			calendar_view.trigger( 'refresh-calendar' );
		} // endif
	});

	$( '.event-dates-and-times-container' ).on( 'update-recur-on-inputs-visibility', function( evt ) {
		var me = $( this );
		var freq_select = me.find( 'select[ name="event_recur_frequency" ]' );
		var freq = freq_select.val();
		var recur_on_inputs = me.find( '.recur-rule-repeat-on-input-list' );
		recur_on_inputs.hide();
		var visible = me.find( '.recur-rule-repeat-on-input-list' + '.' + freq );
		visible.show();
	});
	$( '.event-dates-and-times-container select[ name="event_recur_frequency" ]' ).on( 'change', function( evt ) {
		var me = $( this );
		var container = me.closest( '.event-dates-and-times-container' );
		container.trigger( 'update-recur-on-inputs-visibility' );
	});

	$( '.event-dates-and-times-container input[ name="event_recur_flag" ]' ).on( 'change', function( evt ) {
		var me = $( this );
		var container = me.closest( '.event-dates-and-times-container' );
		container.trigger( 'update-recur-inputs-visibility' );
	});

	$( '.event-dates-and-times-container' ).on( 'update-recur-inputs-visibility', function( evt ) {
		var me = $( this );
		var recur_checkbox = me.find( 'input[ name="event_recur_flag" ]' );
		var is_recurring = recur_checkbox.is( ':checked' );
		input_container = me.find( '.recurring-event-input-container' );

		input_container.find( 'input, select' ).attr( 'disabled', ! is_recurring );

		if ( is_recurring ) {
			input_container.slideDown();
			var calendar_view = me.find( '.reg-man-rc-calendar-view' );
			calendar_view.trigger( 'rerender-calendar' ); // It was hidden so may be scrunched up
		} else {
			input_container.hide();
		} // endif

		me.trigger( 'update-recur-on-inputs-visibility' );

		// Enable the inputs iff it's a recurring event and the unlock checkbox is checked
		var metabox = me.closest( '.event-dates-times-metabox-container' );
		var unlock_checkbox = metabox.find( '.event-dates-input-unlock' );
		// If there is no unlock checkbox then this will be assumed true
		var is_unlock = ( unlock_checkbox.length > 0 ) ? unlock_checkbox.is( ':checked' ) : true;
		var enable_inputs = ( is_unlock && is_recurring );
		if ( enable_inputs ) {
			input_container.find( 'li.input-item' ).removeClass( 'disabled' );
		} else {
			input_container.find( 'li.input-item' ).addClass( 'disabled' );
		} // endif

	});
	
	$( '.event-dates-and-times-container' ).trigger( 'update-recur-inputs-visibility' ); // initialize it
	
	$( 'body' ).on( 'click', '.recur-event-cancel-date-button', function( evt ) {
		var me = $( this );
		var date = me.data( 'event-date' ).toString();
		var is_cancel = me.hasClass( 'cancel-date' );
		
		var cancel_dates_select = $( '.event-dates-and-times-container select[ name="recur_cancel_dates[]" ]' );
		var cancel_dates_array = cancel_dates_select.val();

		if ( is_cancel ) {
			cancel_dates_array.push( date );
		} else {
			var index = cancel_dates_array.indexOf( date );
			if ( index > -1 ) {
				cancel_dates_array.splice( index, 1 );
			} // endif
		} // endif
		
		cancel_dates_select.val( cancel_dates_array );
		cancel_dates_select.change(); // Trigger change event to update everything
	});


	$( '.event-dates-times-metabox-container' ).on( 'update-input-enablement', function( evt ) {
		var me = $( this );
		var unlock_checkbox = me.find( '.event-dates-input-unlock' );
		var inputs_container = me.find( '.event-dates-and-times-container' );
		inputs_container.trigger( 'update-recur-inputs-visibility' ); // Update all the recur visibility and enablement
		var disable_inputs = ! unlock_checkbox.is( ':checked' );
		var input_list = inputs_container.find( '.basic-event-dates-times-input-list' );
		input_list.find( 'input, select' ).attr( 'disabled', disable_inputs );
		if ( disable_inputs ) {
			input_list.find( 'li.input-item' ).addClass( 'disabled' );
		} else {
			input_list.find( 'li.input-item' ).removeClass( 'disabled' );
		} // endif
	});
	$( '.event-dates-times-metabox-container .event-dates-input-unlock' ).on( 'change', function( evt ) {
		var me = $( this );
		var container = me.closest( '.event-dates-times-metabox-container' );
		container.trigger( 'update-input-enablement' );
	});
	$( '.event-dates-times-metabox-container.inputs-require-enablement' ).trigger( 'update-input-enablement' );
	
	$( '.reg-man-rc-calendar-ical-feed-metabox-container' ).on( 'update-input-enablement', function( evt ) {
		var me = $( this );
		var has_feed_input = me.find( 'input[ name="has-ical-feed" ]' );
		var has_feed = has_feed_input.is( ':checked' );
		var feed_input_list = me.find( '.ical-feed-input-list' );
		if ( has_feed ) {
			feed_input_list.removeClass( 'has-no-feed' );
		} else {
			feed_input_list.addClass( 'has-no-feed' );
		} // endif
		feed_input_list.find( '.input-container input' ).attr( 'disabled', ! has_feed );
	});
	$( '.reg-man-rc-calendar-ical-feed-metabox-container input[ name="has-ical-feed" ]' ).on( 'change', function( evt ) {
		var me = $( this );
		var container = me.closest( '.reg-man-rc-calendar-ical-feed-metabox-container' );
		container.trigger( 'update-input-enablement' );
	});
	$( '.reg-man-rc-calendar-ical-feed-metabox-container' ).trigger( 'update-input-enablement' );
	
	$('.reg-man-rc-item-import-form-container').on( 'submit-success-html-update', function( evt ) {
		// When an ajax form is updated, look for any new combobox and initialize it
		var me = $( this );
		me.find( 'select.combobox' ).trigger( 'init-combobox' );
	});
	// The reload button is dynamically created inside a dialog so we need to listen on the dialog
	$( '.reg-man-rc-import-form-container' ).on( 'click',  '.import-reload-button', function() {
		var me = $( this );
		me.trigger( 'handle-reload-import-form' );
	});
	$( '.reg-man-rc-import-form-container' ).on( 'handle-reload-import-form', function( evt ) {
		var me = $( this );
		var form = me.find( '.reg-man-rc-import-form' );
		var event_input = form.find( 'select[name="item-import-event"]' );
		event_input.val( '' ); // unset the any event selection
		var reload_input = form.find( 'input[name="reload"]' );
		reload_input.val( 'TRUE' );
		form.trigger( 'ajax-submit' );
	});


});