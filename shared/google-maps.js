/**
 * This javascript file contains the shared function to support Google Maps for the plugin
 */

jQuery( document ).ready( function( $ ) {

	$( '.reg-man-rc-google-map-container' ).on( 'fit-bounds-to-markers', function( evt ) {
		var me = $(this);
		var map_element = me.find( '.reg-man-rc-google-map' );
		var map = map_element.data( 'map-object' );
		var marker_array = map_element.data( 'map-marker-array' );
		var marker;
		if ( marker_array instanceof Array ) {
			var bounds = new google.maps.LatLngBounds(); // I will adjust map boundary to include all markers
			marker_array.forEach( function( marker ) {
				bounds.extend( marker.position );
			});
			map.fitBounds( bounds );
			
			if ( marker_array.length == 0 ) {
			
				var lat = map_element.data( 'lat' );
				var lng = map_element.data( 'lng' );
				var zoom = map_element.data( 'zoom' );
				map.setZoom( zoom );
				map.setCenter( { lat: lat, lng: lng } );

			} else if ( marker_array.length == 1 ) {
				marker = marker_array[ 0 ];
				var zoom;
				if ( ( typeof( marker.data.zoom ) != 'undefined') && ( marker.data.zoom != null ) ) {
					zoom = marker.data.zoom;
				} else {
					zoom = 16; // just use a default if no zoom level is provided
				} // endif
				map.setZoom( zoom );
			} // endif
		} // endif
	});

	$( '.reg-man-rc-google-map-container' ).on( 'set-map-markers', function( evt, marker_data_array ) {
		var me = $(this);
		var map_element = me.find( '.reg-man-rc-google-map' );
		var map = map_element.data( 'map-object' );
		var marker_svg_path = me.data( 'marker-svg-path' );
		var default_colour = me.data( 'default-marker-colour' );

		// Bet rid of any old message that was on the map
		var message_area = me.find( '.reg-man-rc-map-message-area' );
		if ( message_area.length !== 0 ) {
			message_area.remove();
		} // endif

		// Remove any existing markers
		var marker_array = map_element.data( 'map-marker-array' );
		if ( marker_array instanceof Array ) {
			marker_array.forEach( function( marker ) {
				marker.setMap( null ); // remove the old marker				
			});
		} // endif

		marker_array = []; // create a new array to hold the markers
		map_element.data( 'map-marker-array', marker_array ); // save the markers for later
			
		// I'll use a single info window and update its content when markers are clicked
		var info_window = new google.maps.InfoWindow();

		var marker_data_array_len = marker_data_array.length;

		var marker_data, marker_args, marker, colour, opacity, title, position, label;
		var marker_count = 0; // a count of markers on the map
		var no_position_titles = []; // an array of marker titles that have no position and so cannot be mapped
		
		for ( var i = 0; i < marker_data_array_len; i++ ) {
			marker_data = marker_data_array[ i ];
			colour = ( ( typeof( marker_data.colour ) != 'undefined' ) && ( marker_data.colour != null ) ) ? marker_data.colour : default_colour;
			opacity = ( ( typeof( marker_data.opacity ) != 'undefined' ) && ( marker_data.opacity != null ) ) ? marker_data.opacity : 1;
			title = ( ( typeof( marker_data.title ) != 'undefined' ) && ( marker_data.title != null ) ) ? marker_data.title : '';
			position = ( ( typeof( marker_data.position ) != 'undefined' ) && ( marker_data.position != null ) ) ? marker_data.position : '';
			label = ( ( typeof( marker_data.label ) != 'undefined' ) && ( marker_data.label != null ) ) ? marker_data.label : null;
			if ( position == '' ) {
				no_position_titles.push( ( title != '' ) ? title : __( 'Missing marker title', 'reg-man-rc' ) );
			} else {
				marker_count++;
				marker_args = {
					position	: marker_data.position,
					title		: title,
					label		: label,
					icon		: {
						path			: marker_svg_path,
						fillColor		: colour,
						fillOpacity		: opacity,
						strokeWeight	: 1,
						strokeColor		: 'black',
						rotation		: 0,
						scale			: 2,
						anchor			: new google.maps.Point( 15, 30 )
					},
					zIndex		: 10,
					data		: marker_data, // save the marker data so we can access it later as needed
					map			: map
				};
				marker = new google.maps.Marker( marker_args );
				marker_array.push( marker );
				if ( ( typeof( marker.data.infocontent ) != 'undefined') && ( marker.data.infocontent != null ) ) {
					google.maps.event.addListener( marker, 'click', function() {
						var data = this.data;
						info_window.setContent( data.infocontent );
						info_window.open( map, this );
					});
				} // endif
			} // endif
		} // endfor

		var info_msg = '';
		var no_position_count = no_position_titles.length; // a count of markers that have no position
		if ( marker_data_array_len == 0 ) {
			var no_markers_msg = me.data( 'no-markers-message' );
			if ( ( typeof( no_markers_msg ) != 'undefined') && ( no_markers_msg != null ) && ( no_markers_msg != '' ) ) {
				info_msg = no_markers_msg;
			} // endif
		} else if ( no_position_count !== 0 ) {
			var missing_loc_flag = me.data( 'show-missing-location-message' );
			if ( ( typeof( missing_loc_flag ) != 'undefined') && ( missing_loc_flag != null ) ) {
				info_msg = $( '<details class="missing-location-message"></details>' );
				var head_msg = sprintf(
							_n(	'%s marker not shown because it has no location',
								'%s markers not shown because they have no location', no_position_count, 'reg-man-rc' ),
							no_position_count );
				var summary = $( '<summary></summary>' );
				summary.text( head_msg );
				info_msg.append( summary );
				var title_list = $( '<ul></ul>' );
				var title_item;
				for ( var i = 0; i < no_position_count; i++ ) {
					title_item = $( '<li></li>' );
					title_item.text ( no_position_titles[ i ] );
					title_list.append( title_item );
				} // endfor
				info_msg.append( title_list );
			} // endif
		} // endif

		if ( info_msg !== '' ) {
			message_area = $( '<div class="reg-man-rc-map-message-area"> ');
			message_area.html( info_msg );
			map.controls[ google.maps.ControlPosition.TOP_LEFT ].push( message_area[ 0 ] );
			message_area.css( 'zIndex', 1 ); // make sure this is above the legend
		} // endif
		
		me.trigger( 'fit-bounds-to-markers' );

	});
	
	$( '.reg-man-rc-google-map-container' ).on( 'submit-start', function( evt ) {
		var me = $( this );
		me.addClass( 'reg-man-rc-map-loading' );
	});
	$( '.reg-man-rc-google-map-container' ).on( 'submit-end', function( evt ) {
		var me = $( this );
		me.removeClass( 'reg-man-rc-map-loading' );
	});
	$( '.reg-man-rc-google-map-container' ).on( 'submit-success', function( evt, response ) {
		var me = $( this );
		var marker_json_data = response;
		me.trigger( 'set-map-markers', [ marker_json_data ] );
	});

	$( '.reg-man-rc-google-map-container' ).on( 'init-map-markers', function( evt, map_marker_array ) {
		// Initialize the map markers specified on the server side in the script data in the map container
		var me = $(this);
		var marker_json_data = me.find( '.reg-man-rc-map-marker-json' ).text();
		// Wordpress (on the front end) will filter and replace html chars like '-' and replace with '&#8211;'
		// In order to change them back to regular characters for use in my map I need to create a textarea
		//  insert the data into it then ask for the text back
		marker_json_data = $('<textarea />').html( marker_json_data ).text();
		if ( ( marker_json_data.length !== 0 ) && ( marker_json_data !== '[]' ) ) {
			var marker_json_data = JSON.parse( marker_json_data );
			me.trigger( 'set-map-markers', [ marker_json_data ] );
		} // endif
	});
	
	$( '.reg-man-rc-google-map-container' ).on( 'enable-user-input', function( evt ) {
		var me = $(this);
		var map_element = me.find( '.reg-man-rc-google-map' );
		var map = map_element.data( 'map-object' );
		map.setOptions({
			gestureHandling	: 'auto',
			zoomControl		: true
		});
	});
	$( '.reg-man-rc-google-map-container' ).on( 'disable-user-input', function( evt ) {
		var me = $(this);
		var map_element = me.find( '.reg-man-rc-google-map' );
		var map = map_element.data( 'map-object' );
		map.setOptions({
			gestureHandling	: 'none',
			zoomControl		: false
		});
	});

	$( '.reg-man-rc-google-map-container' ).on( 'init-map', function( evt ) {
		// First make sure google maps is loaded
		if ( typeof google === 'object' && typeof google.maps === 'object' ) {
			var me = $(this);
			var map_element = me.find( '.reg-man-rc-google-map' );
			var lat = map_element.data( 'lat' );
			var lng = map_element.data( 'lng' );
			var zoom = map_element.data( 'zoom' );
			var map = new google.maps.Map( map_element[ 0 ], {
				mapTypeControl: false,				
				center: { lat: lat, lng: lng },
			    zoom: zoom,
			});
			map_element.data( 'map-object', map );
			
			map.addListener('zoom_changed', function(evt) {
				$( '.google-map-zoom-change-listener' ).trigger( 'zoom-changed', [ map ] );
			});
			
			google.maps.event.addListenerOnce( map, 'idle', function() {
				// map is ready
				me.trigger( 'init-map-markers' );
			});
			map_element.trigger( 'map-initialized' );
		} // endif
	});
//	init-map is triggered below, after maps library has loaded

	$( '.reg-man-rc-settings-google-map-container').on( 'init-auto-complete', function( evt ) {
		// Initialize the autocomplete elements for the venue location
		var me = $(this);
		var map_element = me.find( '.reg-man-rc-google-map' );
		var map = map_element.data( 'map-object' );
		var name_autocomplete_input = me.find( 'input[name="reg-man-rc-google-maps-default-centre-place"]' );
		var name_autocomplete_obj = new google.maps.places.Autocomplete( name_autocomplete_input[0] );
		name_autocomplete_input[0].addEventListener( 'keydown', function(evt) {
			// Prevent the default form submission when down arrow and enter are pressed by user
			if ( evt.keyCode === 13 ) evt.preventDefault();
		});
		// Bind the map's bounds (viewport) property to the autocomplete object so that the autocomplete requests
		// use the current map bounds for the bounds option in the request.
		name_autocomplete_obj.bindTo( 'bounds', map );
		name_autocomplete_obj.addListener( 'place_changed', () => {
			// Put the place name into the input then trigger the place changed event
			var place = name_autocomplete_obj.getPlace();
			if ( ( typeof( place ) != 'undefined') && ( place != null ) ) {
				name_autocomplete_input.trigger( 'input' );
			} // endif
			$( '.google-places-autocomplete-place-change-listener' ).trigger( 'autocomplete_place_changed', [ name_autocomplete_obj ] );
		});
		var centre_input = me.find( 'input[name="reg-man-rc-google-maps-default-centre-geo"' );
		map.addListener( 'center_changed', () => {
			var centre = map.getCenter();
			var pos = JSON.stringify( centre.toJSON() );
			centre_input.val( pos );
		});
	});

//	init-auto-complete is triggered below, after maps library has loaded

	$( '.reg-man-rc-settings-google-map-container' ).on( 'autocomplete_place_changed', function( evt, autocomplete ) {
		var me = $(this);
		var map_element = me.find( '.reg-man-rc-google-map' );
		var map = map_element.data( 'map-object' );
		var place = autocomplete.getPlace();
		if ( ( typeof( place ) != 'undefined') && ( place != null ) ) {
			var name = place.name;
			if ( ( typeof( place.geometry ) != 'undefined') &&
				 ( typeof( place.geometry.location ) != 'undefined' ) && ( place.geometry.location != null ) ) {
				var loc = place.geometry.location;
				me.find( 'input[name="reg-man-rc-google-maps-default-centre-geo"]' ).val( JSON.stringify( loc ) );
				if ( place)
				var marker_data = {
						position	: loc,
				};
//				map_element.trigger( 'set-map-markers', [ [ marker_data ] ] );
				var bounds = new google.maps.LatLngBounds();
				if ( place.geometry.viewport ) {
					// Only geocodes have viewport
					bounds.union( place.geometry.viewport );
				} else {
					bounds.extend( place.geometry.location );
				} // endif
				map.fitBounds( bounds );
			} // endif
		} // endif
	});

	$( '.reg-man-rc-settings-google-map-container').on( 'zoom-changed', function( evt, map ) {
		var me = $(this);
		zoom_input = me.find( 'input[name="reg-man-rc-google-maps-default-zoom"]');
		zoom_input.val( map.getZoom() );
	});

	$( '.reg-man-rc-venue-location-container').on( 'init-auto-complete', function( evt ) {
		// Initialize the autocomplete elements for the venue location
		var me = $(this);
		var map_element = me.find( '.reg-man-rc-google-map' );
		var map = map_element.data( 'map-object' );
		var post_type = $( '#post_type' );
		if ( ( post_type.length !== 0 ) && ( ( post_type.val() == 'reg-man-rc-venue' ) || ( post_type.val() == 'reg-man-rc-event' ) ) ) {
			var name_autocomplete_input;
			var name_autocomplete_obj;
			if ( post_type.val() == 'reg-man-rc-venue' ) {
				// We want to make the post title into a Google maps name listener
				// Note that this DOES NOT WORK in the block editor
				name_autocomplete_input = $( 'input[name="post_title"]' ); // Classic editor ONLY
			} else {
				name_autocomplete_input = $( '#reg-man-rc-event-venue-metabox input[name="venue_name"]' );
			} // endif
			if ( name_autocomplete_input.length !== 0 ) {
				name_autocomplete_obj = new google.maps.places.Autocomplete( name_autocomplete_input[0] );
				name_autocomplete_input[0].addEventListener( 'keydown', function(evt) {
					// Prevent the default form submission when down arrow and enter are pressed by user
					if ( evt.keyCode === 13 ) evt.preventDefault();
				});
				name_autocomplete_input.attr( 'placeholder', '' ); // stop google maps from putting "Enter a location"
				// Bind the map's bounds (viewport) property to the autocomplete object so that the autocomplete requests
				// use the current map bounds for the bounds option in the request.
				name_autocomplete_obj.bindTo( 'bounds', map );
				name_autocomplete_obj.addListener( 'place_changed', () => {
					// Put the place name into the input then trigger the place changed event
					var place = name_autocomplete_obj.getPlace();
					if ( ( typeof( place ) != 'undefined') && ( place != null ) ) {
						var name = place.name;
						name_autocomplete_input.val( name );
						name_autocomplete_input.trigger( 'input' );
					} // endif
					$( '.google-places-autocomplete-place-change-listener' ).trigger( 'autocomplete_place_changed', [ name_autocomplete_obj ] );
				});
			} // endif
		} // endif
		var location_autocomplete_input = me.find( 'input[name="venue_location"]' );
		if ( location_autocomplete_input.length !== 0 ) {
			var location_autocomplete_obj = new google.maps.places.Autocomplete( location_autocomplete_input[0] );
			location_autocomplete_input[0].addEventListener( 'keydown', function(evt) {
				// Prevent the default form submission when down arrow and enter are pressed by user
				if ( evt.keyCode === 13 ) evt.preventDefault();
			});
			location_autocomplete_input.attr( 'placeholder', '' ); // stop google maps from putting "Enter a location"
			// Bind the map's bounds (viewport) property to the autocomplete object so that the autocomplete requests
			// use the current map bounds for the bounds option in the request.
			location_autocomplete_obj.bindTo( 'bounds', map );
			location_autocomplete_obj.addListener( 'place_changed', () => {
				$( '.google-places-autocomplete-place-change-listener' ).trigger( 'autocomplete_place_changed', [ location_autocomplete_obj ] );
			});
		} // endif
	});
	
//	init-auto-complete is triggered below, after maps library has loaded

	$( '.reg-man-rc-venue-location-container' ).on( 'autocomplete_place_changed', function( evt, autocomplete ) {
		var me = $(this);
		var map_element = me.find( '.reg-man-rc-google-map' );
		var map = map_element.data( 'map-object' );
		var place = autocomplete.getPlace();
		if ( ( typeof( place ) != 'undefined') && ( place != null ) ) {
			var name = place.name;
			// Update the post title if it's currently empty and we're editing a Venue
			
			var post_type = $( '#post_type' );
			if ( ( post_type.length !== 0 ) && ( post_type.val() == 'reg-man-rc-venue' ) ) {
				var post_title = $( 'input[name="post_title"]' );
				if ( post_title.length !== 0 ) {
					if ( post_title.val() == '' ) {
						post_title.val( name );
						post_title.trigger( 'input' );
					} // endif
				} // endif
			} // endif
			var addr = place.formatted_address;
			me.find( 'input[name="venue_location"]' ).val( addr );
			if ( ( typeof( place.geometry ) != 'undefined') &&
				 ( typeof( place.geometry.location ) != 'undefined' ) && ( place.geometry.location != null ) ) {
				var loc = place.geometry.location;
				me.find( 'input[name="venue_lat_lng"]' ).val( JSON.stringify( loc ) );
				if ( place)
				var marker_data = {
						position	: loc,
				};
				map_element.trigger( 'set-map-markers', [ [ marker_data ] ] );
				var bounds = new google.maps.LatLngBounds();
				if ( place.geometry.viewport ) {
					// Only geocodes have viewport
					bounds.union( place.geometry.viewport );
				} else {
					bounds.extend( place.geometry.location );
				} // endif
				map.fitBounds( bounds );
			} // endif
		} // endif
	});
	
	$( '.reg-man-rc-venue-location-container').on( 'zoom-changed', function( evt, map ) {
		var me = $(this);
		zoom_input = me.find( 'input[name="venue_map_zoom"]');
		zoom_input.val( map.getZoom() );
	});

});

/*
 * This callback is required by Google Maps API library.
 * It is included in the script tag and called automatically by Google
 */
async function reg_man_rc_google_maps_callback() {
	
	// Wait for the library to load
	const { Map } = await google.maps.importLibrary("maps");

	// Then trigger my initialization
	jQuery( document ).ready( function( $ ) {
		
		$( '.reg-man-rc-google-map-container' ).trigger( 'init-map' ); // initialize the map

		$( '.reg-man-rc-settings-google-map-container' ).trigger( 'init-auto-complete' ); // initialize the place autocomplete
		$( '.reg-man-rc-venue-location-container' ).trigger( 'init-auto-complete' ); // initialize the place autocomplete

	});
} // function

