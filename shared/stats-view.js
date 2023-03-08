/**
 * This is the javascript for the stats view interface
 */
jQuery(document).ready(function($) {

	$( '.reg-man-rc-stats-table-view' ).on( 'filter_changed', function( evt, year, category ) {
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

	$( '.reg-man-rc-chart-view' ).on( 'filter_changed', function( evt, year, category ) {
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
	
	$( '.reg-man-rc-chart-view' ).on( 'submit-start', function( evt ) {
		var me = $( this );
		me.addClass( 'reg-man-rc-chart-loading' );
		var old_chart = me.data( 'chart-object' );
		var old_chart_type = typeof old_chart;
		if ( (old_chart !== null ) && ( typeof old_chart !== 'undefined' ) ) {
			old_chart.destroy();
		} // endif
	});
	$( '.reg-man-rc-chart-view' ).on( 'submit-end', function( evt ) {
		var me = $( this );
		me.removeClass( 'reg-man-rc-chart-loading' );
	});
	
	$( '.reg-man-rc-chart-view' ).on( 'submit-success', function( evt, response ) {
		var me = $( this );
		var data = response.result_data;
		var chart_context = me.find( 'canvas' );
		var chart = new Chart(
				chart_context,
				data // chart_config
		);
		me.data( 'chart-object', chart );
	});

	$( '.reg-man-rc-stats-view-container' ).on( 'load_all_stats_data', function( evt ) {
		var me = $( this );
		var year_select = me.find( 'select[name="event_filter_year"]' );
		var year = year_select.val();
		var category_select = me.find( 'select[name="event_filter_category"]' );
		var category = category_select.val();
		var tabs_container = me.find( '.reg-man-rc-tabs-container' );
		var active_index = tabs_container.tabs( 'option', 'active' );;
		var tabs_panel = tabs_container.find( '.tab-panel' );
		var active_panel = $( tabs_panel[ active_index ] );
		active_panel.find( '.event-filter-change-listener' ).trigger( 'filter_changed', [ year, category ] );
	});
	
	$( '.reg-man-rc-stats-view-container' ).on( 'tab-activated', function( evt ) {
		var me = $( this );
		me.trigger( 'load_all_stats_data' ); // Load the stats data when a tab is activated
	});
	$( '.reg-man-rc-stats-view-container' ).on( 'tabs-init-complete', function( evt ) {
		var me = $( this );
		me.trigger( 'load_all_stats_data' ); // Load the stats data when tabs get initialized
	});
	// Load stats if the tabs have already been initialized by the time we get here
	$( '.reg-man-rc-stats-view-container.tabs-init-complete' ).trigger( 'load_all_stats_data' );

	$( '.reg-man-rc-stats-view-container .event_filter_inputs' ).change( function( evt, ui ) {
		var me = $( this );
		me.trigger( 'load_all_stats_data' );
	});

	$( '.admin-stats-table' ).on( 'init-datatable', function( evt, datatable_args ) {
		var me = $(this);
		var view_container = me.closest( '.reg-man-rc-stats-table-view' );
//		var dom_setting = me.data( 'dom-setting' );
		datatable_args.initComplete = function( settings, json ) { me.addClass( 'datatable-init-complete' ); };
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
	$( '.admin-stats-table.events-admin-table' ).trigger( 'init-datatable', [{
		'columnDefs' : [
			{
				'targets'	: [ 5 ],
				'className'	: 'dt-body-right',
				'type'		: 'num-with-empty-placeholder'
			},
			{
				'targets'		: 1, // Order the date column using data from Date ISO 8601
				'orderData'		: 8
			},
			{
				'targets'		: 8, // Hide the Date ISO 8601
				'visible'		: false,
				'searchable'	: false,
			},
		]
	}] );
	$( '.admin-stats-table.items-admin-table' ).trigger( 'init-datatable', [{
		'columnDefs' : [
			{
				'targets'		: 1, // Order the date column using data from Date ISO 8601
				'orderData'		: 7
			},
			{
				'targets'		: 7, // Hide the Date ISO 8601
				'visible'		: false,
				'searchable'	: false,
			},
		]
	}] );
	$( '.admin-stats-table.vol-reg-admin-table' ).trigger( 'init-datatable', [{
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

	$.fn.dataTableExt.oSort[ 'num-with-empty-placeholder-asc' ] = function( x, y ) {
		var result;
		var a = parseInt( x );
		var b = parseInt( y );
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
		var a = parseInt( x );
		var b = parseInt( y );
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
	$( '.reg-man-rc-stats-table-view.items-fixed-table .group-by-select' ).on( 'change', function( evt ) {
		var me = $(this);
		var container = me.closest( '.reg-man-rc-stats-table-view' );
		var table = container.find( '.admin-stats-table' );
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
