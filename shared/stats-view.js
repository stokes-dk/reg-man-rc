/**
 * This is the javascript for the stats view interface
 */
jQuery(document).ready(function($) {

	$( '.reg-man-rc-admin-stats-view-container .reg-man-rc-stats-table-view' ).on( 'filter_changed', function( evt, year, category, author ) {
		var me = $( this );
		var table = me.find( '.admin-stats-table' );
		// Only re-load the table if the filter has changed since the last update
		var curr_year = table.data( 'event_filter_year' );
		var curr_category = table.data( 'event_filter_category' );
		var curr_author = table.data( 'event_filter_author' );
		if ( ( curr_year !== year ) || ( curr_category !== category ) || ( curr_author !== author ) ) {
			table.data( 'event_filter_year', year );
			table.data( 'event_filter_category', category );
			table.data( 'event_filter_author', author );
			table.trigger( 'load_datatable' );
		} // endif
	});

	$( '.reg-man-rc-admin-stats-view-container .reg-man-rc-ajax-chart-view' ).on( 'filter_changed', function( evt, year, category, author ) {
		var me = $( this );
		var ajax_form = me.find( '.reg-man-rc-get-chart-data-form' );
		// Only re-load the chart if the filter has changed since the last update
		var year_input = ajax_form.find( 'input[name="event_filter_year"]' );
		var curr_year = year_input.val();
		var category_input = ajax_form.find( 'input[name="event_filter_category"]' );
		var curr_category = category_input.val();
		var author_input = ajax_form.find( 'input[name="event_filter_author"]' );
		var curr_author = author_input.val();
		if ( ( curr_year !== year ) || ( curr_category !== category ) || ( curr_author !== author ) ) {
			year_input.val( year );
			category_input.val( category );
			author_input.val( author );
			ajax_form.submit();
		} // endif
	});

	$( '.reg-man-rc-admin-events-map-container' ).on( 'filter_changed', function( evt, year, category, author ) {
		var me = $( this );
		var form_id = me.data( 'map-marker-ajax-form-id' );
		var ajax_form = $( '#' + form_id );
		// Only re-load the map markers if the filter has changed since the last update
		var year_input = ajax_form.find( 'input[ name="event_filter_year" ]' );
		var curr_year = year_input.val();
		var category_input = ajax_form.find( 'input[ name="event_filter_category" ]' );
		var curr_category = category_input.val();
		var author_input = ajax_form.find( 'input[name="event_filter_author"]' );
		var curr_author = author_input.val();
		if ( ( curr_year !== year ) || ( curr_category !== category ) || ( curr_author !== author ) ) {
			year_input.val( year );
			category_input.val( category );
			author_input.val( author );
			ajax_form.submit(); // This will trigger the map to go busy and load the markers via Ajax
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
//		active_panel.find( '.reg-man-rc-stats-table-view' ).trigger( 'load_event_stats', [ event_key ] );
		
		// Load datatable, if there is one
		var table = active_panel.find( '.admin-stats-table' );
			// Only re-load the table if it's not already loaded
		if ( table.length > 0 ) {
			var curr_event_key = table.data( 'curr-event-key' );
			if ( typeof curr_event_key == 'undefined' ) {
				table.data( 'curr-event-key', event_key );
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
		var author_select = me.find( 'select[name="event_filter_author"]' );
		var author = author_select.val();
		var tabs_container = me.find( '.reg-man-rc-tabs-container' );
		var active_index = tabs_container.tabs( 'option', 'active' );
		var tabs_panel = tabs_container.find( '.tab-panel' );
		var active_panel = $( tabs_panel[ active_index ] );
		active_panel.find( '.event-filter-change-listener' ).trigger( 'filter_changed', [ year, category, author ] );
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

	$( '.reg-man-rc-admin-stats-view-container .reg-man-rc-stats-table-view.row-grouping-table-view' ).on( 'update-row-grouping', function( evt, datatable, row_group_column_class_name ) {
		var me = $(this);

		if ( row_group_column_class_name == '' ) {

			// This means turn off grouping
			datatable.rowGroup().disable();
			datatable.draw();

		} else {
			
			var column = datatable.column( '.' + row_group_column_class_name );
			var index = column.index();
	
			datatable.rowGroup().enable();
			datatable.rowGroup().dataSrc( index );
			datatable.order( [ index, 'asc' ], [ 0, 'asc' ] ); // Always include the first column (e.g. name)
			datatable.draw();
			
		} // endif
	});


	$( '.admin-stats-table' ).on( 'init-datatable', function( evt ) {
		var me = $(this);

		var export_file_name = me.data( 'export-file-name' );
		var print_page_title = me.data( 'print-page-title' );
		export_file_name = ( typeof export_file_name === 'undefined' ) ? __( 'Repair Café Data Export', 'reg-man-rc' ) : export_file_name;
		print_page_title = ( typeof print_page_title === 'undefined' ) ? __( 'Repair Café Data', 'reg-man-rc' ) : print_page_title;

		var supplemental_button_class =  me.data( 'supplemental-data-button-class' );
		var is_include_suppl_data_button = ( typeof supplemental_button_class !== 'undefined' );

		var import_button_class =  me.data( 'import-data-button-class' );
		var is_include_import_button = ( typeof import_button_class !== 'undefined' );

		var emails_button_class =  me.data( 'email-list-button-class' );
		var is_include_email_button = ( typeof emails_button_class !== 'undefined' );

		var row_group_column_class_name = me.data( 'row-group-column-class-name' );
		var is_allow_row_grouping = ( typeof row_group_column_class_name !== 'undefined' );

		var add_record_button_class_name = me.data( 'add-record-button-class' );
		var is_allow_add_record = ( typeof add_record_button_class_name !== 'undefined' );

		var update_record_button_class_name = me.data( 'update-record-button-class' );
		var is_allow_update_record = ( typeof update_record_button_class_name !== 'undefined' );

		var delete_record_button_class_name = me.data( 'delete-record-button-class' );
		var is_allow_delete_record = ( typeof delete_record_button_class_name !== 'undefined' );

		var is_allow_single_select = ( is_allow_update_record || is_allow_delete_record);
		
		var datatable_args = { };
		datatable_args.responsive = true;
		
		var view_container = me.closest( '.reg-man-rc-stats-table-view' );

		datatable_args.initComplete = function( settings, json ) {
			me.addClass( 'datatable-init-complete' );
			if ( is_allow_row_grouping ) {
				var datatable = this.api(); // This is how you're supposed to get the table!
				me.trigger( 'update-row-grouping', [ datatable, row_group_column_class_name ] ); // update the row grouping
			} // endif
		};
		
		if ( is_allow_row_grouping ) {
			// To allow row grouping, the arguments MUST include rowGroup.  It can't be enabled later
			datatable_args.rowGroup = {
				// We need to update the correct column during init, doing it here does not work properly
				startRender: function ( rows, group ) {
					/* Translators: %1$s is a row group heading, %2$s is a count of rows in that group*/
					var group_heading_format = __( '%1$s (%2$s)', 'reg-man-rc' );
					return sprintf( group_heading_format, group, rows.count() );
				} // endif
			};
		} // endif
		
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
				'targets'	: 'string-with-empty-placeholder',
				'type'		: 'string-with-empty-placeholder'
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
				'orderable'	: false,
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
		
		var icon_format =
			'<span class="reg-man-rc-icon-text-container">' + 
				'<span class="icon dashicons dashicons-%2$s"></span>' + 
				'<span class="text">%1$s</span>' + 
			'</span>';

//		var column_vis_button_text = sprintf( icon_format, __( 'Columns', 'reg-man-rc' ), 'columns' );
		var column_vis_button_text = __( 'Columns', 'reg-man-rc' );
		var column_vis_button_title = __( 'Select which columns are visible in the table', 'reg-man-rc' );

		var refresh_button_text = sprintf( icon_format, __( 'Refresh', 'reg-man-rc' ), 'update' );
		var refresh_button_title = __( 'Refresh the table contents', 'reg-man-rc' );

		var print_button_text = sprintf( icon_format, __( 'Print', 'reg-man-rc' ), 'printer' );
		var print_button_title = __( 'Print the table contents', 'reg-man-rc' );

		var copy_button_text = sprintf( icon_format, __( 'Copy', 'reg-man-rc' ), 'admin-page' );
		var copy_button_title = __( 'Copy the table contents to your clipboard', 'reg-man-rc' );
		
		var csv_button_text = sprintf( icon_format, __( 'Export', 'reg-man-rc' ), 'download' );
		var csv_button_title = __( 'Export the table contents to a CSV file', 'reg-man-rc' );

		var table_buttons = [
			'pageLength',
			{
				extend		: 'colvis',
				text		: column_vis_button_text,
				titleAttr	: column_vis_button_title,
				columns		: ':not(.always-hidden)'
			},
			{
				text		: refresh_button_text,
				titleAttr	: refresh_button_title,
				action		: function( e, dt, node, config ) {
					me.trigger( 'load_datatable' );
				}
			},
			{
				extend		: 'print',
				text		: print_button_text,
				titleAttr	: print_button_title,
				title		: print_page_title,
				exportOptions: {
					columns: ':visible',
					modifier:	{ selected: null } // print all rows not just selected
				},
			},
			{
				extend		: 'copy',
				text		: copy_button_text,
				titleAttr	: copy_button_title,
				exportOptions: {
					modifier:	{ selected: null } // copy all rows not just selected
				},
			},
			{
				extend		: 'csv',
				text		: csv_button_text,
				titleAttr	: csv_button_title,
				filename	: export_file_name,
				exportOptions: {
					columns: [ ':visible', '.always-export' ],
					modifier:	{ selected: null } // export all rows not just selected
				},
			},
		];

		var data_buttons = [];

		// Add New
		if ( is_allow_add_record ) {
			var add_new_button_text = sprintf( icon_format, __( 'Add New&hellip;', 'reg-man-rc' ), 'plus-alt' );
			var add_new_button_title = __( 'Add a new record to this table', 'reg-man-rc' );
			data_buttons.push({
					text		: add_new_button_text,
					titleAttr	: add_new_button_title,
					className	: add_record_button_class_name,
			});
		} // endif
		
		// Supplemental Data
		if ( is_include_suppl_data_button ) {
			var supplemental_button_text = sprintf( icon_format, __( 'Supplemental Data&hellip;', 'reg-man-rc' ), 'database-add' );
			var supplemental_button_title = __( 'Change the supplemental data for this table', 'reg-man-rc' );
			data_buttons.push({
					text		: supplemental_button_text,
					titleAttr	: supplemental_button_title,
					className	: supplemental_button_class,
			});
		} // endif
		
		// Import
		if ( is_include_import_button ) {
			var import_button_text = sprintf( icon_format, __( 'Import&hellip;', 'reg-man-rc' ), 'upload' );
			var import_button_title = __( 'Import data from a CSV file', 'reg-man-rc' );
			data_buttons.push({
					text		: import_button_text,
					titleAttr	: import_button_title,
					className	: import_button_class,
			});
		} // endif

		// Email button
		if ( is_include_email_button ) {
			var emails_button_text = sprintf( icon_format, __( 'Emails&hellip;', 'reg-man-rc' ), 'email-alt' );
			var emails_button_title = __( 'Show the list of volunteer emails for this event', 'reg-man-rc' );
			table_buttons.push({
					text		: emails_button_text,
					titleAttr	: emails_button_title,
					className	: emails_button_class,
			});
		} // endif
		
		// Declare the layout:
		var datatable_layout = {
			top2Start:	{
				'buttons':	data_buttons,
			},
			topStart:	{
				'buttons':	table_buttons,
			},
			bottomStart:	{ 
				info: {},
			},
			bottomEnd:		{
				paging: {},
			}
		};

		// Place the search box at the top with data buttons if they exist
		if ( data_buttons.length != 0 ) {
			datatable_layout.top2End =	{ search: { } };
			datatable_layout.topEnd =	{ };
		} else {
			datatable_layout.top2End =	{ };
			datatable_layout.topEnd =	{ search: { } };
		} // endif
		
		// Assign the layout
		datatable_args.layout = datatable_layout;
		
		if ( is_allow_single_select ) {
			datatable_args.select = {
				style: 'single',
			}
		} // endif
		
		// Create the datatable
		var my_data_table = me.DataTable( datatable_args );
		
		// Store the datatable object for later reference
		me.data( 'my-datatable', my_data_table );
		
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
					'_wpnonce' 				: me.data( 'ajax-nonce' ),
					'table_type'			: me.data( 'table-type' ),
					'group_by'				: me.data( 'group-by' ),
					'event_filter_year'		: me.data( 'event_filter_year' ),
					'event_filter_category'	: me.data( 'event_filter_category' ),
					'event_filter_author'	: me.data( 'event_filter_author' ),
					'event_key'				: me.data( 'event-key' )
				}
			}).done( function( result ) {
				if ( result.error ) {
					alert( result.error );
				} else {
					me.trigger( 'datatable-replace-data', [ result.data ] );
				} // endif
			}).fail( function( jqXHR, textStatus, error ) {
				console.log( 'Admin stats table Ajax load failed, text status: ' + textStatus + ', error: ' + error );
			}).always( function( ) {
				view_container.removeClass( 'reg-man-rc-table-loading' );
			});
		});
		
		if ( is_allow_single_select ) {
			my_data_table.on( 'select', function ( evt, datatable, type, indexes ) {
				my_data_table.trigger( 'selection-changed' );
//				console.log( 'select event' );
			});
			my_data_table.on( 'deselect', function ( evt, datatable, type, indexes ) {
				my_data_table.trigger( 'selection-changed' );
//				console.log( 'deselect event' );
			});
			my_data_table.on( 'selection-changed', function() {
				var rows = my_data_table.rows( { selected: true } );
				var rows_data = rows.data();
				if ( rows_data.length > 0 ) {
					console.log( rows_data.length );
					var row_data = rows_data[ 0 ];
					console.log( row_data );
				} // endif
			});
		} // endif
		
	});
	$( '.admin-stats-table' ).trigger( 'init-datatable' );

	$.fn.dataTableExt.oSort[ 'string-with-empty-placeholder-asc' ] = function( x, y ) {
		// Make sure it's a string
		var a = String( x );
		var b = String( y );
		var result;
		if ( ( a === '' ) || ( a === '—' ) ) {
			result = ( b === '' ) || ( b === '—' ) ? 0 : 1;
		} else if ( ( b === '' ) || ( b === '—' ) ) {
			result = -1;
		} else if ( a == b) {
			result = 0;
		} else {
			result = ( a < b ) ? -1 : 1;
		} // endif
		return result;
	};
	$.fn.dataTableExt.oSort[ 'string-with-empty-placeholder-desc' ] = function( x, y ) {
		// Make sure it's a string
		var a = String( x );
		var b = String( y );
		var result;
		if ( ( a === '' ) || ( a === '—' ) ) {
			result = ( ( b === '' ) || ( b === '—' ) ) ? 0 : 1;
		} else if ( ( b === '' ) || ( b === '—' ) ) {
			result = -1;
		} else if ( a == b) {
			result = 0;
		} else {
			result = ( a > b ) ? -1 : 1;
		} // endif
		return result;
	};

	$.fn.dataTableExt.oSort[ 'num-with-empty-placeholder-asc' ] = function( x, y ) {
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

	$( '.reg-man-rc-admin-stats-view-container .reg-man-rc-stats-table-view.row-grouping-table-view .group-by-select' ).on( 'change', function( evt ) {
		var me = $(this);
		var row_group_column_class_name = me.val(); // The value is the class name for the column to group
		
		var container = me.closest( '.reg-man-rc-stats-table-view' ); // container for select + table
		var table = container.find( '.admin-stats-table' ); // the actual table
		var datatable = table.data( 'my-datatable' );
		
		me.trigger( 'update-row-grouping', [ datatable, row_group_column_class_name ] );
	});

	$( '.admin-stats-table-container.admin-cpt-vol-reg-list-change-listener' ).on( 'admin-cpt-vol-reg-list-changed', function( evt ) {
		var me = $(this);
		var table = me.find( '.admin-stats-table' ); // the actual table
		table.trigger( 'load_datatable' );
	});
});
