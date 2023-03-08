/**
 * This javascript file contains the shared function to support FullCalendar for the plugin
 */
jQuery( document ).ready( function( $ ) {

	$( '.reg-man-rc-calendar-view' ).on( 'initialize', function( evt ) {
		var me = $(this);

		var week_start = me.data( 'week-start' );
		var lang = me.data( 'lang' );
		var feed_url = me.data( 'feed-url' );
		var feed_params = { }; // We will need to pass extra params to the events feed when we call it like statuses and categories
		me.data( 'feed-params', feed_params );
		
		if ( typeof me.data( 'wp-nonce' ) !== 'undefined' ) {
			feed_params._wpnonce = me.data( 'wp-nonce' );
		} // endif
		if ( typeof me.data( 'calendar-id' ) !== 'undefined' ) {
			feed_params.calendar_id = me.data( 'calendar-id' );
		} // endif

		var view_list = ( typeof me.data( 'views' ) !== 'undefined' ) ? me.data( 'views' ) : 'dayGridMonth';
		var initial_view = ( typeof me.data( 'initial-view' ) !== 'undefined' ) ? me.data( 'initial-view' ) : 'dayGridMonth';

		week_start = ( week_start !== undefined ) ? week_start : 0; // Sunday if nothing specified
		lang = ( lang !== undefined ) ? lang : 'en'; // English if nothing specified
		feed_url = ( feed_url !== undefined ) ? feed_url : '[ ]'; // Show no events if no feed url specified

		var container = me.closest( '.reg-man-rc-calendar-container' );
		container.addClass( 'reg-man-rc-calendar-loading' ); // Make the map look busy while it's initially loading
		var map_container = container.find( '.reg-man-rc-calendar-map-container' );
		var has_map; // A flag indicating whether a map element is present in the calendar container
		if ( map_container.length !== 0 ) {
			has_map = true;
			me.data( 'map-container', map_container );
			var map_marker_form_id = map_container.data( 'map-marker-ajax-form-id' );
			var map_marker_form = $( '#' + map_marker_form_id );
			me.data( 'map-marker-form', map_marker_form );
		} else {
			has_map = false;
		} // endif
		
		// Past events
		var is_show_past_events = me.data( 'is-show-past-events' );
		if ( typeof is_show_past_events !== 'boolean' ) {
			is_show_past_events = true; // show past events by default
		} // endif
		feed_params.is_show_past_events = is_show_past_events;

		var right_buttons;
		var custom_buttons = null;
		right_buttons = view_list.includes( ',' ) ? view_list : '';
		
		var calendar_args = {
				initialView: initial_view,
				locale: lang,
				firstDay: week_start,
				navLinks: false, // when true allows user to click on a day link and switch to day view
//				aspectRatio: 1.5,
				height: '80vh',
				headerToolbar: {
					left: 'prev,next', // prev,next,today
					center: 'title',
					right: right_buttons
				},
				stickyHeaderDates: false, // Don't use sticky headers because it's not working right now
				loading :	function( is_loading ) {
					container.trigger( 'set-calendar-is-loading', is_loading );
					// When we're done loading, I need to reset the flag for proper scrolling on list view
					if ( ! is_loading ) {
						me.find( '.fc-listMonth-view .fc-scroller' ).data( 'reg-man-rc-is-scrolled', false );
					} // endif
				},
				customButtons: custom_buttons,
				views: {
					dayGridMonth: {
						buttonText: __( 'Calendar', 'reg-man-rc' )
					},
					listMonth: {
						buttonText: __( 'List', 'reg-man-rc' )
					},
					custom_map_view: {
						classNames: [ 'custom-map-view' ],
						duration :	{ months: 1 },
						content: function( props ) {
							// props contains currentRange and activeRange but I can't see any difference
							var active_start_date = props.dateProfile.activeRange.start;
							var active_end_date = props.dateProfile.activeRange.end;
							var active_start_date_string = active_start_date.toISOString();
							var active_end_date_string = active_end_date.toISOString();
							var map_container = me.data( 'map-container' );
							var ajax_form = me.data( 'map-marker-form' );
							var min_date_input = ajax_form.find( 'input[ name="min_date" ]' );
							var max_date_input = ajax_form.find( 'input[ name="max_date" ]' );
							var calendar_id_input = ajax_form.find( 'input[ name="calendar_id" ]' );
							var is_show_past_events_input = ajax_form.find( 'input[ name="is_show_past_events" ]' );
							calendar_id_input.val( me.data( 'calendar-id' ) );
							is_show_past_events_input.val( feed_params.is_show_past_events );
							if ( ( active_start_date_string != min_date_input.val() ) ||
								 ( active_end_date_string != max_date_input.val() ) ) {
								min_date_input.val( active_start_date_string );
								max_date_input.val( active_end_date_string );
								container.trigger( 'set-calendar-is-loading', [ true ] );
								ajax_form.submit(); // This will trigger the map to go busy and load the markers via Ajax
							} // endif
							var dom_nodes = map_container;
							return { domNodes: dom_nodes }
						},
						buttonText: __( 'Map', 'reg-man-rc' )
					},
				},
				viewDidMount: function( arg ) {
					var view = arg.view;
					
					// Set a cookie when the view is changed
					const date = new Date();
					date.setTime( date.getTime() + ( 365 * 24 * 60 * 60 * 1000 ) );
					let expires = "expires="+ date.toUTCString();
					document.cookie = 'reg-man-rc-calendar-view' + "=" + view.type + ";" + expires + ";samesite=Lax";
				
				},
				eventDidMount: function( calendar_entry ) {
					var element = $( calendar_entry.el );
					var info_content = $( calendar_entry.event.extendedProps.info );
					element.tooltipster({
						zIndex			: 10000,
						interactive		: true,
						content			: info_content,
						position		: 'top',
						theme			: 'tooltipster-shadow',
						trigger			: 'click',
						functionReady: function() { 
							info_content.on( 'popup-close', function( ) {
								element.tooltipster( 'close' );
							});
						}
					});
					if ( calendar_entry.view.type == 'listMonth' ) {
						// I want to scroll the list view to today or first day in the future
						// Note that this event occurs AFTER ALL the events are inserted into the DOM
						// Also note that I rely on the loading event to unset my flag
						var scroller = element.closest( '.fc-scroller' );
						if ( scroller.scrollTop() === 0 ) {
							var is_scrolled = scroller.data( 'reg-man-rc-is-scrolled' );
							if ( ! is_scrolled ) {
								var future_days = scroller.find( '.fc-list-day.fc-day-today, .fc-list-day.fc-day-future' );
								if ( future_days.length > 0 ) {
									var first_day = $( future_days[ 0 ] );
									scroller.scrollTop( first_day.offset().top - scroller.offset().top );
									scroller.data( 'reg-man-rc-is-scrolled', true );
								} // endif
							} // endif
						} // endif
					} // endif
				},
				eventSources: [
					{
						url			: feed_url,
						extraParams	: feed_params
					}
				]
		};

		var calendar = new FullCalendar.Calendar( me[ 0 ], calendar_args );
		calendar.render();
		me.data( 'calendar-obj', calendar );
		container.find( '.reg-man-rc-calendar-legend' ).show(); // make sure to draw the legend
	});

	$( '.reg-man-rc-calendar-view' ).trigger( 'initialize' );

	$( '.reg-man-rc-calendar-view' ).on( 'refresh-calendar', function( evt ) {
		var me = $( this );
		var calendar = me.data( 'calendar-obj' );
		var map_ajax_form = me.data( 'map-marker-form' );
		calendar.refetchEvents();
		var min_date_input = map_ajax_form.find( 'input[ name="min_date" ]' );
		min_date_input.val( '' ); // This will trigger the map to load the markers via Ajax when it's shown
	});
	$( '.reg-man-rc-calendar-container' ).on( 'set-calendar-is-loading', function( evt, is_loading ) {
		var me = $( this );
		if ( is_loading ) {
			me.addClass( 'reg-man-rc-calendar-loading' );
		} else {
			me.removeClass( 'reg-man-rc-calendar-loading' );
		} // endif
	});
	$( '.reg-man-rc-calendar-container.reg-man-rc-ajax-form-listener' ).on( 'submit-end', function( evt ) {
		var me = $( this );
		me.trigger( 'set-calendar-is-loading', [ false ] );
	});
	
});
