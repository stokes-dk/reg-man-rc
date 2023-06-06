/**
 * This is the javascript for the stats view interface
 */
jQuery(document).ready(function($) {

	$( '.reg-man-rc-admin-stats-view-container .reg-man-rc-stats-table-view' ).on( 'filter_changed', function( evt, year, category ) {
		var me = $( this );
		var table = me.find( '.admin-stats-table' );
		// Only re-load the table if the filter has changed since the last update
		var curr_year = table.data( 'event_filter_year' );
		var curr_category = table.data( 'event_filter_category' );
		if ( ( curr_year !== year ) || ( curr_category !== category ) ) {
			table.data( 'event_filter_year', year );
			table.data( 'event_filter_category', category );
			table.trigger( 'load_datatable' );
		} // endif
	});

	$( '.reg-man-rc-admin-stats-view-container .reg-man-rc-ajax-chart-view' ).on( 'filter_changed', function( evt, year, category ) {
		var me = $( this );
		var ajax_form = me.find( '.reg-man-rc-get-chart-data-form' );
		// Only re-load the chart if the filter has changed since the last update
		var year_input = ajax_form.find( 'input[name="event_filter_year"]' );
		var curr_year = year_input.val();
		var category_input = ajax_form.find( 'input[name="event_filter_category"]' );
		var curr_category = category_input.val();
		if ( ( curr_year !== year ) || ( curr_category !== category ) ) {
			year_input.val( year );
			category_input.val( category );
			ajax_form.submit();
		} // endif
	});

	$( '.reg-man-rc-ajax-chart-view' ).on( 'submit-start', function( evt ) {
		var me = $( this );
		me.addClass( 'reg-man-rc-chart-loading' );
		var old_chart = me.data( 'chart-object' );
		if ( ( old_chart !== null ) && ( typeof old_chart !== 'undefined' ) ) {
			old_chart.destroy();
		} // endif
	});
	$( '.reg-man-rc-ajax-chart-view' ).on( 'submit-end', function( evt ) {
		var me = $( this );
		me.removeClass( 'reg-man-rc-chart-loading' );
	});

	$( '.reg-man-rc-ajax-chart-view' ).on( 'submit-success', function( evt, response ) {
		var me = $( this );
		var data = response.result_data;
		var chart_context = me.find( 'canvas' );
		// I have to destroy the old chart before creating the new one
		var previous_chart = me.data( 'chart-object' );
		if ( previous_chart !== undefined ) {
			previous_chart.destroy();
		} // endif
		var chart = new Chart(
				chart_context,
				data // chart_config
		);
		me.data( 'chart-object', chart );
	});

	$( '.reg-man-rc-admin-stats-view-container.reg-man-rc-admin-single-event-stats' ).on( 'load_all_stats_data', function( evt ) {
		var me = $( this );
		var event_key = me.data( 'event-key' );
		var tabs_container = me.find( '.reg-man-rc-tabs-container' );
		var active_index = tabs_container.tabs( 'option', 'active' );
		var tabs_panel = tabs_container.find( '.tab-panel' );
		var active_panel = $( tabs_panel[ active_index ] );
		active_panel.find( '.reg-man-rc-stats-table-view' ).trigger( 'load_event_stats', [ event_key ] );
		
		// Load datatable, if there is one
		var table = active_panel.find( '.admin-stats-table' );
		if ( table.length > 0 ) {
			// Only re-load the table if it's not already loaded
			var curr_event = table.data( 'event-key' );
	//		console.log( curr_event );
			if ( curr_event != event_key ) {
				table.data( 'event-key', event_key );
				table.trigger( 'load_datatable' );
			} // endif
		} // endif

		var chart_ajax_forms = active_panel.find( '.reg-man-rc-get-chart-data-form' );
		var curr_form, event_key_input, curr_event_key;
		if ( chart_ajax_forms.length > 0 ) {
//			var curr_form;
			chart_ajax_forms.each( function( index, elem ) {
				curr_form = $( elem );
				// Only load the chart if it's not already loaded
				event_key_input = curr_form.find( 'input[name="event-key"]' );
				curr_event_key = event_key_input.val();
				if ( curr_event_key != event_key ) {
					event_key_input.val( event_key );
					curr_form.submit();
				} // endif
			});

		} // endif
	});

	$( '.reg-man-rc-static-chart-view' ).on( 'load_all_stats_data', function( evt ) {
		var me = $( this );
		var chart_context = me.find( 'canvas' );

		var chart_config_data = me.find( '.reg-man-rc-chart-config-data' ).text();
		// Wordpress (on the front end) will filter and replace html chars like '-' and replace with '&#8211;'
		// In order to change them back to regular characters for use in my map I need to create a textarea
		//  insert the data into it then ask for the text back
		chart_config_data = $('<textarea />').html( chart_config_data ).text();

		if ( ( chart_config_data.length !== 0 ) && ( chart_config_data !== '[]' ) ) {
			chart_config_data = JSON.parse( chart_config_data );
		
			// I have to destroy the old chart before creating the new one
			var previous_chart = me.data( 'chart-object' );
			if ( previous_chart !== undefined ) {
				previous_chart.destroy();
			} // endif
			var chart = new Chart(
					chart_context,
					chart_config_data
			);
			me.data( 'chart-object', chart );

		} // endif
	});
	$( '.reg-man-rc-static-chart-view' ).trigger( 'load_all_stats_data' );

	$( '.reg-man-rc-admin-stats-view-container.reg-man-rc-admin-filtered-stats' ).on( 'load_all_stats_data', function( evt ) {
		var me = $( this );
		var year_select = me.find( 'select[name="event_filter_year"]' );
		var year = year_select.val();
		var category_select = me.find( 'select[name="event_filter_category"]' );
		var category = category_select.val();
		var tabs_container = me.find( '.reg-man-rc-tabs-container' );
		var active_index = tabs_container.tabs( 'option', 'active' );
		var tabs_panel = tabs_container.find( '.tab-panel' );
		var active_panel = $( tabs_panel[ active_index ] );
		active_panel.find( '.event-filter-change-listener' ).trigger( 'filter_changed', [ year, category ] );
	});
	
	$( '.reg-man-rc-admin-stats-view-container' ).on( 'tab-activated', function( evt ) {
		var me = $( this );
		me.trigger( 'load_all_stats_data' ); // Load the stats data when a tab is activated
	});
	$( '.reg-man-rc-admin-stats-view-container' ).on( 'tabs-init-complete', function( evt ) {
		var me = $( this );
		me.trigger( 'load_all_stats_data' ); // Load the stats data when tabs get initialized
	});
	// Load stats if the tabs have already been initialized by the time we get here
	$( '.reg-man-rc-admin-stats-view-container.tabs-init-complete' ).trigger( 'load_all_stats_data' );

	$( '.reg-man-rc-admin-stats-view-container .event_filter_inputs' ).change( function( evt, ui ) {
		var me = $( this );
		me.trigger( 'load_all_stats_data' );
	});

	$( '.admin-stats-table' ).on( 'init-datatable', function( evt ) {
		var me = $(this);
		var datatable_args = { };
		var view_container = me.closest( '.reg-man-rc-stats-table-view' );

		datatable_args.initComplete = function( settings, json ) { me.addClass( 'datatable-init-complete' ); };
		datatable_args.dom = 'lBfrtip'; // Default 'lfrtip';
		datatable_args.stateSave = true; // Save the user's state between visits
		
		datatable_args.columnDefs = [
			{
				'targets'	: 'text-align-right',
				'className'	: 'dt-body-right'
			},
			{
				'targets'	: 'text-align-center',
				'className'	: 'dt-body-center'
			},
			{
				'targets'	: 'num-with-empty-placeholder',
				'className'	: 'dt-body-right',
				'type'		: 'num-with-empty-placeholder'
			},
			{
				'targets'	: 'col-hidden',
				'visible'	: false,
			},
			{
				'targets'	: 'col-not-searchable',
				'searchable': false,
			},
			{
				'targets'	: 'col-not-sortable',
				'sortable'	: false,
			},
		];

		// Sort the event date text using the column after it when present
		var date_column_index = me.find( 'th.event-date-text' ).index();
		if ( date_column_index >= 0 ) {
			datatable_args.columnDefs.push(
				{
					'targets'		: 'event-date-text', // Order the date column using data from the next column
					'orderData'		: date_column_index + 1
				}			
			);
		} // endif
		
		var column_vis_button_text = __( 'Columns', 'reg-man-rc' );
		var column_vis_button_title = __( 'Select which columns are visible in the table', 'reg-man-rc' );

		var copy_button_text = __( 'Copy', 'reg-man-rc' );
		var copy_button_title = __( 'Copy the table contents to your clipboard', 'reg-man-rc' );

		var csv_button_text = __( 'Export', 'reg-man-rc' );
		var csv_button_title = __( 'Export the table contents to a CSV file', 'reg-man-rc' );

		var print_button_text = __( 'Print', 'reg-man-rc' );
		var print_button_title = __( 'Print the table contents', 'reg-man-rc' );

		datatable_args.buttons = [
			{
				extend		: 'colvis',
				text		: column_vis_button_text,
				titleAttr	: column_vis_button_title,
				columns		: ':not(.always-hidden)'
			},
			{
				extend		: 'copy',
				text		: copy_button_text,
				titleAttr	: copy_button_title,
				exportOptions: {
					columns: ':visible'
				},
			},
			{
				extend		: 'csv',
				text		: csv_button_text,
				titleAttr	: csv_button_title,
				exportOptions: {
					columns: ':visible'
				},
			},
			{
				extend		: 'print',
				text		: print_button_text,
				titleAttr	: print_button_title,
				exportOptions: {
					columns: ':visible'
				},
			},
			{
				text: 'Refresh',
				action: function( e, dt, node, config ) {
					me.trigger( 'load_datatable' );
				}
			}
		];
		var my_data_table = me.DataTable( datatable_args );
		me.on( 'datatable-replace-data', function( evt, new_data ) {
			my_data_table.clear();
			my_data_table.rows.add( new_data );
			my_data_table.columns.adjust();
			my_data_table.draw();
		});
		me.on( 'load_datatable', function( evt ) {
			view_container.addClass( 'reg-man-rc-table-loading' );
			my_data_table.search( '' );
			$.ajax({
				'url'  : me.data( 'ajax-url' ),
				'dataType'	: 'json',
				'data' : {
					'action'				: me.data( 'ajax-action' ),
					'table_type'			: me.data( 'table-type' ),
					'group_by'				: me.data( 'group-by' ),
					'event_filter_year'		: me.data( 'event_filter_year' ),
					'event_filter_category'	: me.data( 'event_filter_category' ),
					'event_key'				: me.data( 'event-key' )
				}
			}).done( function( result ) {
				me.trigger( 'datatable-replace-data', [ result.data ] );
			}).fail( function( jqXHR, textStatus, error ) {
				console.log( 'Admin stats table Ajax load failed, text status: ' + textStatus + ', error: ' + error );
			}).always( function( ) {
				view_container.removeClass( 'reg-man-rc-table-loading' );
			});
		});
		me.data( 'my-datatable', my_data_table );
	});
	$( '.admin-stats-table' ).trigger( 'init-datatable' );

/*
	$( '.admin-stats-table.events-admin-table' ).trigger( 'init-datatable', [{
		'columnDefs' : [
			{
				'targets'	: 'num-with-empty-placeholder', //[ 5 ],
				'className'	: 'dt-body-right',
				'type'		: 'num-with-empty-placeholder'
			},
			{
				'targets'		: 'event-date-text', // Order the date column using data from Date ISO 8601
				'orderData'		: 8 // 'event-date-iso-8601' - Note that orderData ONLY accepts a column index, not name
			},
			{
				'targets'		: 'event-date-iso-8601', // Hide the Date ISO 8601
				'visible'		: false,
				'searchable'	: false,
			},
		]
	}] );
	$( '.admin-stats-table.items-admin-table' ).trigger( 'init-datatable', [{
		'columnDefs' : [
			{
				'targets'		: 'event-date-text', //1, // Order the date column using data from Date ISO 8601
				'orderData'		: 7 // 'event-date-iso-8601' - Note that orderData ONLY accepts a column index, not name
			},
			{
				'targets'		: 'event-date-iso-8601', // Hide the Date ISO 8601
				'visible'		: false,
				'searchable'	: false,
			},
		]
	}] );
	$( '.admin-stats-table.vol-reg-admin-table' ).trigger( 'init-datatable', [{
		'columnDefs' : [
			{
				'targets'		: 1, // Order the event column using data from Date ISO 8601
				'orderData'		: 7
			},
			{
				'targets'		: 7, // Hide the Date ISO 8601
				'visible'		: false,
				'searchable'	: false,
			},
		]
	}] );
	$( '.admin-stats-table.visitors-admin-table' ).trigger( 'init-datatable', [{
		'columnDefs' : [
			{
				'targets'		: 1, // Order the event column using data from Date ISO 8601
				'orderData'		: 6
			},
			{
				'targets'		: 6, // Hide the Date ISO 8601
				'visible'		: false,
				'searchable'	: false,
			},
		]
	}] );
	$( '.admin-stats-table.items-fixed-admin-table' ).trigger( 'init-datatable', [{
		'columnDefs' : [
			{
				'targets'	: [ 3, 4, 5, 6, 7, 8, 9 ],
				'className'	: 'dt-body-right'
			},
			{
				'targets'	: [ 7, 10 ], // Hide these, user can make visible with colvis
				'visible'	: false,
			},
			{
				'targets'	: [ 10 ],
				'sortable'	: false,
				'className'	: 'dt-body-center'
			},
			{
				'targets'	: [ 8, 9 ],
				'type'		: 'num-with-empty-placeholder'
			}
		]
	}] );
*/
	$.fn.dataTableExt.oSort[ 'num-with-empty-placeholder-asc' ] = function( x, y ) {
		var result;
		// Convert everything to a string (like null) and remove commas in numbers like 1,432
		var a = parseInt( String(x).replaceAll( ',', '' ) );
		var b = parseInt( String(y).replaceAll( ',', '' ) );
		var result;
		if ( isNaN( a ) ) {
			result = isNaN( b ) ? 0 : 1;
		} else if (isNaN( b ) ) {
			result = -1;
		} else if ( a == b) {
			result = 0;
		} else {
			result = ( a < b ) ? -1 : 1;
		} // endif
		return result;
	};
	$.fn.dataTableExt.oSort[ 'num-with-empty-placeholder-desc' ] = function( x, y ) {
		// Convert everything to a string (like null) and remove commas in numbers like 1,432
		var a = parseInt( String(x).replaceAll( ',', '' ) );
		var b = parseInt( String(y).replaceAll( ',', '' ) );
		var result;
		if ( isNaN( a ) ) {
			result = isNaN( b ) ? 0 : 1;
		} else if (isNaN( b ) ) {
			result = -1;
		} else if ( a == b) {
			result = 0;
		} else {
			result = ( a > b ) ? -1 : 1;
		} // endif
		return result;
	};
	$( '.reg-man-rc-admin-stats-view-container .reg-man-rc-stats-table-view.items-fixed-table .group-by-select' ).on( 'change', function( evt ) {
		var me = $(this);
		var container = me.closest( '.reg-man-rc-stats-table-view' ); // container for select + table
		var table = container.find( '.admin-stats-table' ); // the actual table
		table.data( 'group-by', me.val() );
		table.trigger( 'load_datatable' );
	});

	$( '.reg-man-rc-admin-events-map-container' ).on( 'filter_changed', function( evt, year, category ) {
		var me = $( this );
		var form_id = me.data( 'map-marker-ajax-form-id' );
		var ajax_form = $( '#' + form_id );
		// Only re-load the map markers if the filter has changed since the last update
		var year_input = ajax_form.find( 'input[ name="event_filter_year" ]' );
		var curr_year = year_input.val();
		var category_input = ajax_form.find( 'input[ name="event_filter_category" ]' );
		var curr_category = category_input.val();
		if ( ( curr_year !== year ) || ( curr_category !== category ) ) {
			year_input.val( year );
			category_input.val( category );
			ajax_form.submit(); // This will trigger the map to go busy and load the markers via Ajax
		} // endif
	});
	
});
