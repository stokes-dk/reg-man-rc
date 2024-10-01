/**
 * This javascript file contains the shared function to support FullCalendar for the plugin
 */

jQuery( document ).ready( function( $ ) {

	$( '.reg-man-rc-calendar-view' ).on( 'highlight-duration-button', function( evt ) {
		var me = $( this );
		var calendar_id = typeof me.data( 'calendar-id' ) !== 'undefined' ? me.data( 'calendar-id' ) : 'no-id'; // Defensive
		var duration = localStorage.getItem( 'fullcalendar_initial_duration_' + calendar_id );
		me.find( '.fc-' + duration + '-button' ).addClass( 'fc-button-active' );
	});

	$( '.reg-man-rc-calendar-view' ).on( 'highlight-author-button', function( evt ) {
		var me = $( this );
		var calendar_id = typeof me.data( 'calendar-id' ) !== 'undefined' ? me.data( 'calendar-id' ) : 'no-id'; // Defensive
		var author = localStorage.getItem( 'fullcalendar_initial_author_' + calendar_id );
		me.find( '.fc-' + author + '-button' ).addClass( 'fc-button-active' );
	});

	$( '.reg-man-rc-calendar-view' ).on( 'initialize-calendar', function( evt ) {
		var me = $(this);

		var calendar_id = typeof me.data( 'calendar-id' ) !== 'undefined' ? me.data( 'calendar-id' ) : 'no-id'; // Defensive
		var week_start = me.data( 'week-start' );
		var lang = me.data( 'lang' );
		
		 // The min width of a month in multimonth view
		var multi_month_min_width = ( typeof me.data( 'multi-month-min-width' ) !== 'undefined' ) ? me.data( 'multi-month-min-width' ) : '360';

		var feed_url = me.data( 'feed-url' );
		var feed_params = { }; // We will need to pass extra params to the events feed when we call it like statuses and categories
		me.data( 'feed-params', feed_params );
		
		if ( typeof me.data( 'wp-nonce' ) !== 'undefined' ) {
			feed_params._wpnonce = me.data( 'wp-nonce' );
		} // endif
	
		if ( typeof me.data( 'calendar-type' ) !== 'undefined' ) {
			feed_params.calendar_type = me.data( 'calendar-type' );
		} // endif

		if ( typeof me.data( 'calendar-id' ) !== 'undefined' ) {
			feed_params.calendar_id = me.data( 'calendar-id' );
		} // endif

		var view_list = ( typeof me.data( 'views' ) !== 'undefined' ) ? me.data( 'views' ) : 'grid_view';
		var view_array = view_list.split( ',' );
		var initial_view = localStorage.getItem( 'fullcalendar_initial_view_' + calendar_id );
		if ( ! view_array.includes( initial_view ) ) {
			initial_view = view_array[ 0 ];
			localStorage.setItem( 'fullcalendar_initial_view_' + calendar_id, initial_view );
		} // endif

		var duration_list = ( typeof me.data( 'durations' ) !== 'undefined' ) ? me.data( 'durations' ) : 'duration_1_month';
		var duration_array = duration_list.split( ',' );
		var initial_duration = localStorage.getItem( 'fullcalendar_initial_duration_' + calendar_id );
		if ( ! duration_array.includes( initial_duration ) ) {
			initial_duration = duration_array[ 0 ];
			localStorage.setItem( 'fullcalendar_initial_duration_' + calendar_id, initial_duration );
		} // endif

		var author_list = ( typeof me.data( 'authors' ) !== 'undefined' ) ? me.data( 'authors' ) : 'author_all';
		var default_author = ( typeof me.data( 'default-author' ) !== 'undefined' ) ? me.data( 'default-author' ) : 'author_all';
		var author_array = author_list.split( ',' );
		var initial_author = localStorage.getItem( 'fullcalendar_initial_author_' + calendar_id );
		if ( initial_author === null ) {
			initial_author = default_author;
			localStorage.setItem( 'fullcalendar_initial_author_' + calendar_id, initial_author );
		} // endif
		if ( ! author_array.includes( initial_author ) ) {
			initial_author = author_array[ 0 ];
			localStorage.setItem( 'fullcalendar_initial_author_' + calendar_id, initial_author );
		} // endif
		
//		console.log( initial_author );
		feed_params.event_author = initial_author;

		week_start = ( week_start !== undefined ) ? week_start : 0; // Sunday if nothing specified
		lang = ( lang !== undefined ) ? lang : 'en'; // English if nothing specified
		feed_url = ( feed_url !== undefined ) ? feed_url : '[ ]'; // Show no events if no feed url specified

		var container = me.closest( '.reg-man-rc-calendar-container' );
		container.addClass( 'reg-man-rc-calendar-loading' ); // Make the map look busy while it's initially loading
		var map_container = container.find( '.reg-man-rc-calendar-map-container' );
//		var has_map; // A flag indicating whether a map element is present in the calendar container
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

		var custom_buttons = {
			duration_1_month: {
				text: __( '1 month', 'reg-man-rc' ),
				hint: __( 'Show one month', 'reg-man-rc' ),
				click: function() { me.trigger( 'duration-changed', [ 'duration_1_month' ] ); }
			},
			duration_2_months: {
				text: __( '2 months', 'reg-man-rc' ),
				hint: __( 'Show two months', 'reg-man-rc' ),
				click: function() { me.trigger( 'duration-changed', [ 'duration_2_months' ] ); }
			},
			duration_3_months: {
				text: __( '3 months', 'reg-man-rc' ),
				hint: __( 'Show three months', 'reg-man-rc' ),
				click: function() { me.trigger( 'duration-changed', [ 'duration_3_months' ] ); }
			},
			duration_4_months: {
				text: __( '4 months', 'reg-man-rc' ),
				hint: __( 'Show four months', 'reg-man-rc' ),
				click: function() { me.trigger( 'duration-changed', [ 'duration_4_months' ] ); }
			},
			duration_6_months: {
				text: __( '6 months', 'reg-man-rc' ),
				hint: __( 'Show six months', 'reg-man-rc' ),
				click: function() { me.trigger( 'duration-changed', [ 'duration_6_months' ] ); }
			},
			duration_12_months: {
				text: __( '12 months', 'reg-man-rc' ),
				hint: __( 'Show twelve months starting with the current month', 'reg-man-rc' ),
				click: function() { me.trigger( 'duration-changed', [ 'duration_12_months' ] ); }
			},
			duration_1_year: {
				text: __( 'Calendar year', 'reg-man-rc' ),
				hint: __( 'Show one calendar year from January to December', 'reg-man-rc' ),
				click: function() { me.trigger( 'duration-changed', [ 'duration_1_year' ] ); }
			},

			author_all: {
				text: __( 'All', 'reg-man-rc' ),
				hint: __( 'Show all events', 'reg-man-rc' ),
				click: function() { me.trigger( 'author-changed', [ 'author_all' ] ); }
			},
			author_mine: {
				text: __( 'Mine', 'reg-man-rc' ),
				hint: __( 'Show only events owned by me', 'reg-man-rc' ),
				click: function() { me.trigger( 'author-changed', [ 'author_mine' ] ); }
			}
		};
		
		var fc_duration_obj, grid_view_type;
		// Convert the duration ID into a FullCalendar duration object
		switch( initial_duration ) {

			case 'duration_1_month':
			default:
				fc_duration_obj = { month: 1 };
				grid_view_type = 'dayGridMonth';
				break;

			case 'duration_2_months':
				fc_duration_obj = { months: 2 };
				grid_view_type = 'multiMonth';
				break;
			
			case 'duration_3_months':
				fc_duration_obj = { months: 3 };
				grid_view_type = 'multiMonth';
				break;
			
			case 'duration_4_months':
				fc_duration_obj = { months: 4 };
				grid_view_type = 'multiMonth';
				break;
			
			case 'duration_6_months':
				fc_duration_obj = { months: 6 };
				grid_view_type = 'multiMonth';
				break;
			
			case 'duration_12_months':
				fc_duration_obj = { months: 12 };
				grid_view_type = 'multiMonth';
				break;
			
			case 'duration_1_year':
				fc_duration_obj = { year: 1 };
				grid_view_type = 'multiMonth';
				break;
			
		} // endswitch
		
		// Add duration buttons on the left if necessary
		var left_buttons = 'prev,next today';
		if ( duration_list.includes( ',' ) ) {
			duration_buttons = duration_list;
			left_buttons = duration_buttons + ' ' + left_buttons;
		} // endif
		
		var view_buttons;
		view_buttons = view_list.includes( ',' ) ? view_list : '';

		var author_buttons;
		author_buttons = 'author_all,author_mine';
		
		var right_buttons = view_buttons;
		// Add author buttons on the right if necessary
		if ( author_list.includes( ',' ) ) {
			author_buttons = author_list;
			if ( right_buttons !== '' ) {
				right_buttons = author_buttons + ' ' + right_buttons;
			} else {
				right_buttons = author_buttons;
			} // endif
		} // endif

		var calendar_args = {
				multiMonthMinWidth: multi_month_min_width, // The min width of a month in multimonth view
				initialView: initial_view,
				initialDate: localStorage.getItem( 'fullcalendar_initial_date_' + calendar_id ),
				locale: lang,
				firstDay: week_start,
				navLinks: false, // when true allows user to click on a day link and switch to day view
//				aspectRatio: 1.5,
				height: '80vh',
				headerToolbar: {
					left: left_buttons,
					center: 'title',
					right: right_buttons
				},
				stickyHeaderDates: false, // Don't use sticky headers because it's not working right now
				loading :	function( is_loading ) {
					container.trigger( 'set-calendar-is-loading', is_loading );
					// When we're done loading, I need to reset the flag for proper scrolling on list view
					if ( ! is_loading ) {
						me.find( '.fc-list_view-view .fc-scroller' ).data( 'reg-man-rc-is-scrolled', false );
					} // endif
				},
				datesSet: function( date_info ) {
					// Called whenever the date is changed, save in local storage
					var curr_date = new Date( date_info.view.currentStart );
					localStorage.setItem( 'fullcalendar_initial_date_' + calendar_id, curr_date.toISOString() );
				},
				customButtons: custom_buttons,
				views: {
					grid_view: {
						type: grid_view_type, // dayGridMonth or multiMonth depending on current duration setting
						duration : fc_duration_obj,
//						buttonText: __( 'Calendar', 'reg-man-rc' )
						buttonText: __( 'Grid', 'reg-man-rc' )
					},
					list_view: {
						type: 'list',
						duration : fc_duration_obj,
						listDayFormat: {
							month: 'long',
							day: 'numeric',
							weekday: 'long'
						},
						buttonText: __( 'List', 'reg-man-rc' )
					},
					map_view: {
						classNames: [ 'custom-map-view' ],
						duration : fc_duration_obj,
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
							var calendar_type_input = ajax_form.find( 'input[ name="calendar_type" ]' );
							var calendar_id_input = ajax_form.find( 'input[ name="calendar_id" ]' );
							var is_show_past_events_input = ajax_form.find( 'input[ name="is_show_past_events" ]' );
							var event_author_input = ajax_form.find( 'input[ name="event_author" ]' );
							calendar_type_input.val( me.data( 'calendar-type' ) );
							calendar_id_input.val( me.data( 'calendar-id' ) );
							is_show_past_events_input.val( feed_params.is_show_past_events );
							event_author_input.val( feed_params.event_author );
							// I wanted to avoid reloading the map when the date has not changed but this causes
							// issues when I added things like All | Mine for author.  The date has not changed
							// but the map must be reloaded.  For now, I'm going to skip this check and reaload every
							// time the map is shown.
//							if ( ( active_start_date_string != min_date_input.val() ) ||
//								 ( active_end_date_string != max_date_input.val() ) ) {
								min_date_input.val( active_start_date_string );
								max_date_input.val( active_end_date_string );
								container.trigger( 'set-calendar-is-loading', [ true ] );
								ajax_form.submit(); // This will trigger the map to go busy and load the markers via Ajax
//							} // endif
							var dom_nodes = map_container;
							return { domNodes: dom_nodes }
						},
						buttonText: __( 'Map', 'reg-man-rc' )
					},
				},
				viewDidMount: function( arg ) {
					// This is currently not being triggered for custom view, see bug #7275
					var view = arg.view;
					localStorage.setItem( 'fullcalendar_initial_view_' + calendar_id, view.type );
				},
/*
eventRender: function( info ) {
	console.log( info );
},
*/
				eventDidMount: function( calendar_entry ) {
					var element = $( calendar_entry.el );
					// Create the tool tip
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
						},
						functionBefore: function( instance, helper ) {
							var origin = $( helper.origin );
							origin.addClass( 'popup-showing' );
							container.trigger( 'popup-changed' );
						},
						functionAfter: function( instance, helper ) {
							var origin = $( helper.origin );
							origin.removeClass( 'popup-showing' );
							container.trigger( 'popup-changed' );
						},
					});
/* FIXME - I don't think we need to do this just for list view, we don't do it for grid view, right?
					// Scroll the list view to today
					if ( calendar_entry.view.type == 'list_view' ) {
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
*/
				},
				eventSources: [
					{
						url			: feed_url,
						extraParams	: feed_params
					}
				],
				eventSourceFailure( error ) {
					if ( error instanceof Error ) {
						var status = error.response.status;
						var msg;
						if ( status === 403 ) {
							var err_format = __( 'Failed to get events.  %1$s %2$s', 'reg-man-rc' );
							msg = __( 'Your security token has expired.  Please refresh the page', 'reg-man-rc' );
						} else {
							$status_text = error.response.statusText;
							var err_format = __( 'Failed to get events.  %1$s %2$s', 'reg-man-rc' );
							msg = sprintf( err_format, status, error.response.statusText );
						} // endif
						console.log( msg );
						alert( msg );
					} // endif
				}
		};

		var calendar = new FullCalendar.Calendar( me[ 0 ], calendar_args );
		calendar.render();
		me.data( 'calendar-obj', calendar );
		container.find( '.reg-man-rc-calendar-legend' ).show(); // make sure to draw the legend
		me.trigger( 'highlight-duration-button' );
		me.trigger( 'highlight-author-button' );
	});
	

	$( '.reg-man-rc-calendar-view' ).trigger( 'initialize-calendar' );

	$( '.reg-man-rc-calendar-container' ).on( 'popup-changed', function() {
		var me = $( this );
		var tooltipsters = me.find( '.fc-event.popup-showing' );
		var is_popup_showing = ( tooltipsters.length > 0 );
		if ( is_popup_showing ) {
			me.addClass( 'popup-showing' );
		} else {
			me.removeClass( 'popup-showing' );
		} // endif
	});

	$( '.reg-man-rc-calendar-view' ).on( 'duration-changed', function( evt, duration ) {

		var me = $( this );
		var calendar_id = typeof me.data( 'calendar-id' ) !== 'undefined' ? me.data( 'calendar-id' ) : 'no-id'; // Defensive
//		console.log( 'Duration changed: ' + duration );
		var calendar = me.data( 'calendar-obj' );
		var view = calendar.view;
//		console.log( 'Duration changed: ' + view.type );
		// Because of bug #7275, the viewDidMount event is not being triggered for a custom view, like my map
		// So instead, I am saving the current view as the initial view in local storage so we're on the right
		// view after a duration change
		localStorage.setItem( 'fullcalendar_initial_view_' + calendar_id, view.type );
		
		calendar.destroy();
		
		localStorage.setItem( 'fullcalendar_initial_duration_' + calendar_id, duration );

		me.trigger( 'initialize-calendar' );
	});

	$( '.reg-man-rc-calendar-view' ).on( 'author-changed', function( evt, author ) {
		var me = $( this );
		var calendar_id = typeof me.data( 'calendar-id' ) !== 'undefined' ? me.data( 'calendar-id' ) : 'no-id'; // Defensive
//		console.log( 'Author changed: ' + author );
		var calendar = me.data( 'calendar-obj' );
		var view = calendar.view;
		console.log( 'Author changed: ' + view.type );
		// Because of bug #7275, the viewDidMount event is not being triggered for a custom view, like my map
		// So instead, I am saving the current view as the initial view in local storage so we're on the right
		// view after an author change
		localStorage.setItem( 'fullcalendar_initial_view_' + calendar_id, view.type );
		
		calendar.destroy();
		
		localStorage.setItem( 'fullcalendar_initial_author_' + calendar_id, author );

		me.trigger( 'initialize-calendar' );
	});

	$( '.reg-man-rc-calendar-view' ).on( 'refresh-calendar', function( evt ) {
		var me = $( this );
		var calendar = me.data( 'calendar-obj' );
		calendar.refetchEvents();
		var map_ajax_form = me.data( 'map-marker-form' );
		if ( map_ajax_form !== undefined ) {
			var min_date_input = map_ajax_form.find( 'input[ name="min_date" ]' );
			min_date_input.val( '' ); // This will trigger the map to load the markers via Ajax when it's shown
		} // endif
	});
	$( '.reg-man-rc-calendar-view' ).on( 'rerender-calendar', function( evt ) {
		var me = $( this );
		var calendar = me.data( 'calendar-obj' );
		calendar.render();
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
	$( '.reg-man-rc-calendar-container .reg-man-rc-ical-feed-copy-to-clipboard' ).on( 'click', function( evt ) {
		var me = $( this );
		var url = me.data( 'url' );
		try {
			navigator.clipboard.writeText( url );
			me.tooltipster( 'open' );
		} catch( error ) {
			/* Translators: %1$s is a url for an iCalendar event feed */
			var msg_format = __( 'Unable to access clipboard.  Feed URL is: %1$s', 'reg-man-rc' );
			var msg = sprintf( msg_format, url );
			alert( msg );
		} // endtry
	});
	$( '.reg-man-rc-calendar-container .reg-man-rc-ical-feed-copy-to-clipboard' ).on( 'initialize-tooltip', function( evt ) {
		var me = $( this );
		var content = __( 'Copied', 'reg-man-rc' );
		me.tooltipster({
					zIndex			: 10000,
					interactive		: true,
					content			: content,
					position		: 'top',
					theme			: 'tooltipster-shadow',
					trigger			: 'custom',
					functionReady: function() {
						setTimeout( function() {
							me.tooltipster( 'close' );
						}, 3000 );
					}
		});
	});
	$( '.reg-man-rc-calendar-container .reg-man-rc-ical-feed-copy-to-clipboard' ).trigger( 'initialize-tooltip' );
});
