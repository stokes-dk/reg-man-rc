/**
 * This is the base javascript for visitor registration on the public-facing side
 */

jQuery(document).ready(function($) {
	$('.form-user-message-dialog').dialog({
		autoOpen	: true,
		modal		: true,
		width		: 'auto',
		height		: 'auto',
		position	: { my: 'top', at: 'top+150' }, // Try to put it 150px from top
		buttons : [
			{ 
				'text' : __( 'Close', 'reg-man-rc' ), // The button has an icon so no text is needed
				'click': function() { $( this ).dialog( 'close' ); }
			}
		]
	});
	
	$( '.visitor-reg-list-table' ).on( 'init-datatable', function( evt ) {
		
		var me = $( this );
		var container = me.closest( '.visitor-reg-manager-container' );
		var dom_setting = me.data( 'dom-setting' );

		var visitor_id_col_index = 6;
		var is_outcome_reported_col_index = 7;
		
		var datatable = me.DataTable({
			'ajax' : {
				'url'  : me.data( 'ajax-url' ),
				// We'll use a function here so it gets executed on every reload
				'data' : function( data ) {
					data.action			= me.data( 'ajax-action' ),
					data.event_key		= me.data( 'event-key' ),
					data._wpnonce		= me.data( 'ajax-nonce' ),
					data.fixer_station	= me.data( 'fixer-station' )
				},
			},
			responsive: {
				details: {
					type: 'column',
					target: 0
				}
			},
			rowGroup: { // rowGroup option MUST be included, it can't be enabled later otherwise
				// We need to update the correct column during init, doing it here does not work properly
				startRender: function ( rows, group ) {
					/* Translators: %1$s is a row group heading, %2$s is a count of rows in that group */
					var group_heading_format = __( '%1$s (%2$s)', 'reg-man-rc' );
					return sprintf( group_heading_format, group, rows.count() );
				} // endif
			},
			'autoWidth' : false,
			'dom': dom_setting,
			'paging' : false, // hide pagination and just show everything
			'processing' : false, // hide processing indicator so we can use our own
			'columnDefs'	: [
				{
					className: 'dtr-control',
					orderable: false,
					target: 0
				},
				{
					'targets'	: 'col-hidden',
					'searchable': false, // don't allow searching on hidden columns
					'visible'	: false,
				},
				{
					'targets'	: 'col-searchable-hidden',
					'searchable': true, // allow searching on these special hidden columns used just for searching
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
			],
			'language' : {
				'emptyTable'		: __('No visitors registered', 'reg-man-rc'),
			    'thousands'			: _x(',', 'Thousands separator', 'reg-man-rc'),
				/* translators: _MENU_ is a replacement variable for the count of table entries to show */
			    'lengthMenu'		: __('Show _MENU_ entries', 'reg-man-rc'),
				'loadingRecords'	: __('Loading&hellip;', 'reg-man-rc'),
				'processing'		: __('Updating', 'reg-man-rc'),
			    'search'			: __('Search:', 'reg-man-rc'),
			    'zeroRecords'		: __('No matching records found', 'reg-man-rc'),
			    'paginate': {
			        'first'			: __('First', 'reg-man-rc'),
			        'last'			: __('Last', 'reg-man-rc'),
			        'next'			: __('Next', 'reg-man-rc'),
			        'previous'		: __('Previous', 'reg-man-rc')
			    },
			},

			createdRow: function( row, data, dataIndex ) {
				var row = $( row );
				var visitor_id = data[ visitor_id_col_index ];
				row.attr( 'data-visitor-id', visitor_id ); // Store the visitor ID so we can count visitors
				var is_outcome_reported = data[ is_outcome_reported_col_index ];
				row.attr( 'data-is-outcome-reported', is_outcome_reported ); // Store the flag for outcome reported
			},

			'infoCallback' : function( settings, start, end, max, total, pre ) {
				var result;
				if (max === 0) {
					result = '';
					container.find( 'input[type=search]' ).removeClass( 'filter-on' );
				} else if (total !== max) { // the filter is being used so not all items are showing
					// translators: %1$s is replaced with a count of items showing, %2$s with the total count of registered items
					result = sprintf( _n( 'Showing %1$s item (filtered from a total of %2$s registered)',
										'Showing %1$s items (filtered from a total of %2$s registered)', total, 'reg-man-rc' ),
									total, max );
					result = '<span class="visitor-reg-list-filter-info">' + result + '</span>';
					container.find('input[type=search]').addClass('filter-on'); // highlight the filter
				} else { // all items showing, give full details on the list
					container.find('input[type=search]').removeClass('filter-on');
					var item_count = max; // the item count is the total number of rows
					var visitor_array = [], visitors_count = 0, visitor_id;
					var is_outcome_reported, outcome_reported_count = 0, response_rate;
					var curr_row;
					var rows = datatable.rows().nodes().toJQuery();
					rows.each( function( index, value ) {
						curr_row = $(value);
						is_outcome_reported = curr_row.data( 'is-outcome-reported' );
						if ( is_outcome_reported ) outcome_reported_count++;
						visitor_id = curr_row.data( 'visitor-id' );
						if ( ! ( visitor_id in visitor_array ) ) {
							visitor_array[ visitor_id ] = 1; // mark the visitor array so we can keep count
							visitors_count++; // this visitor is new to the list so increment counter
						} // endif
					});
					/* Translators: %s is a count of visitors */
					var visitors_text = sprintf( _n( '%s visitor', '%s visitors', visitors_count, 'reg-man-rc' ), visitors_count );
					/* Translators: %s is a count of items */
					var reg_items_text = sprintf( _n( '%s item', '%s items', item_count, 'reg-man-rc' ), item_count );
					response_rate = Math.round( outcome_reported_count / item_count * 100 );
					/* Translators: %s is a percentage of items whose repair outcome is reported divided by total items.
						Note that %s%% will produce a number followed by a single percent sign
					 */
					var response_rate_text = sprintf( __( '%s%% outcome reported', response_rate, 'reg-man-rc' ), response_rate );
					/* translators: %1$s is visitor count text like "45 visitors"
					  %2$s is the item count text like "50 items"
					  %3$s is the response rate text like "28% response rate"
					*/
					var format = __( '%1$s, %2$s, %3$s', 'reg-man-rc' );
					result = sprintf( format, visitors_text, reg_items_text, response_rate_text );
				} // endif
				return result;
			},
			'initComplete': function( settings, json ) {
				// Executes after initialization
				var me = $( this );
				me.addClass( 'datatable-init-complete' );
			},
		});
		
		me.data( 'datatable', datatable );
	});
	
	$( '.visitor-reg-list-table' ).trigger( 'init-datatable' );
	
	$( '.visitor-reg-list-table' ).on( 'processing.dt', function( evt, settings, is_processing ) {
		var me = $( this );
		var datatable_container = me.closest( '.reg-manager-datatable-container' );
		var busy_div = datatable_container.find( '.reg-man-rc-visitor-reg-busy' );
		if ( is_processing ) {
			busy_div.addClass( 'is-active' ); // shrowd the the table UI while we're busy, no clicks anywhere
		} else {
			busy_div.removeClass( 'is-active' );
		} // endif
	});

	$( '.visitor-reg-list-table' ).on( 'datatable-reload', function(evt) {
		var me = $( this );
		var datatable = me.data( 'datatable' );
		datatable.search( '' );
		datatable.ajax.reload();
	});
	
	$( '.reg-manager-refresh' ).click(function() {
		var me = $(this);
		var container = me.closest( '.visitor-reg-manager-container' );
		var table = container.find( '.visitor-reg-manager-table' );
		table.trigger( 'datatable-reload' );
	});
		
	$( '.visitor-reg-list-table' ).on( 'in-place-edit-submit-start', function( evt ) {
		$( '.reg-man-rc-application-busy' ).addClass( 'is-active' ); // shrowd the entire UI while we're busy, no clicks anywhere
	});
		
	$( '.visitor-reg-list-table' ).on( 'in-place-edit-submit-end', function( evt ) {
		// When the item status has been changed I need to trigger a table reload to update everything
		// Note that even when the submit fails, like the visitor has too many active items,
		//  I still need to reload the status for the item to display the current value
		var me = $( this );
		var table = me.closest( '.visitor-reg-list-table' );
		table.trigger( 'datatable-reload' );
		$( '.reg-man-rc-application-busy' ).removeClass( 'is-active' );
	});

	$( '.reg-manager-show-station' ).change( function( evt ) {
		var me = $(this);
		var container = me.closest( '.reg-manager-datatable-container' );
		container.trigger( 'update-show-fixer-station' );
	});

	$( '.reg-manager-datatable-container' ).on( 'update-show-fixer-station', function( evt ) {
		var me = $( this );
		var station_select = me.find( '.reg-manager-show-station' );
		var fixer_station_id = station_select.val();
		var table = me.find( '.visitor-reg-manager-table' );
		table.data( 'fixer-station',fixer_station_id ); // store the fixer station so it will be loaded
		
		var datatable = table.data( 'datatable' );
		var station_col = datatable.column( '.item-fixer-station' );
		var status_text_col = datatable.column( '.item-status-text' );
		var status_order_col = datatable.column( '.item-status-order' );
		var time_enqueued_col = datatable.column( '.item-date-time-enqueued' );
		var is_show_all_stations = ( fixer_station_id == 0 );
		
		station_col.visible( is_show_all_stations );

		if ( is_show_all_stations ) {
			datatable.rowGroup().enable();
			datatable.rowGroup().dataSrc( station_col.index() );
			datatable.order( [ station_col.index(), 'asc' ], [ status_order_col.index(), 'asc' ], [ time_enqueued_col.index(), 'asc' ] );
			station_col.visible( false );
		} else {
			datatable.rowGroup().enable();
			datatable.rowGroup().dataSrc( status_text_col.index() );
			datatable.order( [ status_order_col.index(), 'asc' ], [ time_enqueued_col.index(), 'asc' ] );
			station_col.visible( false );
		} // endif


//		me.trigger( 'update-sort-by' ); // we may need to change the grouping etc.
		table.trigger( 'datatable-reload' );
	});
	
	// Initialize the displayed fixer station, provided there is a datatable container
	$( '.reg-manager-datatable-container' ).trigger( 'update-show-fixer-station' ); // initialize it
/*
	$( '.reg-manager-sort-by' ).change( function( evt ) {
		var me = $(this);
		var container = me.closest( '.reg-manager-datatable-container' );
		container.trigger( 'update-sort-by' );
	});
	
	$( '.reg-manager-datatable-container' ).on( 'update-sort-by', function( evt ) {
		var me = $( this );
		var sort_by_select = me.find( '.reg-manager-sort-by' );
		var station_select = me.find( '.reg-manager-show-station' );
		var sort_by = sort_by_select.val();
		var fixer_station_id = station_select.val();
		var is_show_all_stations = ( fixer_station_id == 0 );
		var table = me.find( '.visitor-reg-manager-table' );
		var datatable = table.data( 'datatable' );
		
		var visitor_name_col = datatable.column( '.visitor-short-name' );
		var item_id_col = datatable.column( '.item-id' );
		var station_col = datatable.column( '.item-fixer-station' );
		var status_text_col = datatable.column( '.item-status-text' );
		var status_order_col = datatable.column( '.item-status-order' );
		var time_enqueued_col = datatable.column( '.item-date-time-enqueued' );
		
		switch ( sort_by ) {
			
			case 'item-id':
				// This means turn off grouping
				datatable.rowGroup().disable();
				datatable.order( [ item_id_col.index(), 'asc' ] );
				visitor_name_col.visible( true );
				station_col.visible( true );
				break;

			case 'visitor-short-name':
				datatable.rowGroup().enable();
				datatable.rowGroup().dataSrc( visitor_name_col.index() );
				datatable.order( [ visitor_name_col.index(), 'asc' ], [ status_order_col.index(), 'asc' ], [ time_enqueued_col.index(), 'asc' ] );
				visitor_name_col.visible( false );
				station_col.visible( true );
				break;
*/
/*
			case 'item-fixer-station':
				datatable.rowGroup().enable();
				datatable.rowGroup().dataSrc( station_col.index() );
				datatable.order( [ station_col.index(), 'asc' ], [ status_order_col.index(), 'asc' ], [ time_enqueued_col.index(), 'asc' ] );
				visitor_name_col.visible( true );
				station_col.visible( false );
				break;
*/
/*
			case 'item-status':
				if ( is_show_all_stations ) {
					datatable.rowGroup().enable();
					datatable.rowGroup().dataSrc( station_col.index() );
					datatable.order( [ station_col.index(), 'asc' ], [ status_order_col.index(), 'asc' ], [ time_enqueued_col.index(), 'asc' ] );
					visitor_name_col.visible( true );
					station_col.visible( false );
				} else {
					datatable.rowGroup().enable();
					datatable.rowGroup().dataSrc( status_text_col.index() );
					datatable.order( [ status_order_col.index(), 'asc' ], [ time_enqueued_col.index(), 'asc' ] );
					visitor_name_col.visible( true );
					station_col.visible( false );
				} // endif
				break;

		} // endswitch
		
		datatable.draw();
	});
	
	// Initialize the sort-by, provided there is a datatable container
	$( '.reg-manager-datatable-container' ).trigger( 'update-sort-by' ); // initialize it
*/

/*		
	$('.reg-manager-survey').click(function() {
		var surveyDialog = $('.visitor-survey-dialog');
		surveyDialog.find('.visitor-survey-thank-you-message').hide();
		surveyDialog.find('.visitor-survey-form').trigger('form-reset'); // reset the form (remove errors etc.)
		surveyDialog.dialog('open');
	});
*/
	$( '.reg-manager-add' ).click( function( evt ) {
		var me = $( this );
		var container = me.closest( '.visitor-reg-manager-container' );
		var reg_form = container.find( '.visitor-reg-form' );
		reg_form.trigger( 'form-reset' );
		container.trigger( 'show-swappable', [ '.reg-man-rc-visitor-add-visitor-container' ] );	
		var house_rules = container.find( '.house-rules' );
		house_rules.scrollTop( 0 ); // has to be showing to work properly
	});
	
	$( '.visitor-reg-manager-container' ).on( 'show-swappable', function( evt, selector ) {
		var me = $( this );
		var swap_container = me.find( '.reg-man-rc-swappable-container' );
		var swap_out = swap_container.find( '.reg-man-rc-swappable-element:visible' );
		var swap_in = swap_container.find( selector );
		swap_out.hide();
		swap_in.show();
		swap_in.trigger( 'swappable-shown' );
		swap_container.scrollTop( 0 );
	});
	
	$( '.reg-man-rc-visitor-reg-list-container' ).on( 'swappable-shown', function ( evt ) {
		var me = $( this );
		var table = me.find( '.visitor-reg-manager-table' );
		var datatable = table.data( 'datatable' );
		 // adjust the columns in case the table has been updated, otherwise responsive does not work properly
		datatable.columns.adjust();
	});

	
	$( '.visitor-reg-manager-container' ).on( 'click', '.visitor-reg-single-visitor-details-button', function( evt ) {
		var me = $( this );
		var visitor_id = me.val();
		var cell = me.closest( 'td' );
		var visitor_name = cell.find( '.visitor-reg-list-visitor-name' ).text();
		var container = me.closest( '.visitor-reg-manager-container' );
		var form_container = container.find( '.visitor-reg-update-visitor-form-container' );
		form_container.trigger( 'load-visitor', [ visitor_id, visitor_name ] );
		container.trigger( 'show-swappable', [ '.reg-man-rc-visitor-details-container' ] );
	});
 	
	$( '.visitor-reg-update-visitor-form-container' ).on( 'load-visitor', function( evt, visitor_id, visitor_name ) {
		var me = $( this );
		
		var table = me.find( '.visitor-reg-visitor-items-table' );
		table.data( 'visitor-id', visitor_id );
		// The table contents must be loaded when it's shown to get responsive columns to work properly

		var add_item_form = me.find( '.visitor-reg-add-item-to-visitor-form' );
		add_item_form.trigger( 'form-reset' );
		var visitor_name_container = me.find( '.visitor-details-name' );
		visitor_name_container.text( visitor_name );
		var visitor_input = add_item_form.find( 'input[ name="visitor-id" ]' );
		visitor_input.val( visitor_id );
		
	});

	$( '.visitor-reg-manager-container' ).on( 'click', '.visitor-reg-view-item-button', function( evt ) {
		var me = $( this );
		var item_id = me.val();
//		var fixer_station = me.data( 'fixer-station' );
//		var item_type = me.data( 'item-type' );
//		var item_status = me.data( 'item-status' );
//		var cell = me.closest( 'td' );
//		var item_desc = cell.find( '.visitor-reg-list-item' ).text();
		var container = me.closest( '.visitor-reg-manager-container' );
		var form_container = container.find( '.reg-man-rc-item-details-container' );
//		form_container.trigger( 'load-item', [ item_id, item_desc, fixer_station, item_type, item_status ] );
		form_container.trigger( 'load-item', [ item_id ] );
		container.trigger( 'show-swappable', [ '.reg-man-rc-item-details-container' ] );
	});
 	
	$( '.reg-man-rc-item-details-container' ).on( 'load-item', function( evt, item_id ) {
		var me = $( this );
		var inputs_container = me.find( '.visitor-reg-upate-item-inputs-container' );
		inputs_container.html( '' ); // Clear the inputs before getting the item
		var load_form = me.find( '.visitor-reg-get-item-update-content-form' );
		var item_input = load_form.find( 'input[ name="item-id" ]' );
		item_input.val( item_id );
		load_form.trigger( 'ajax-submit' );
	});
	
	$( '.reg-man-rc-item-details-container' ).on( 'submit-success', function( evt, response ) {
		var me = $( this );
		var inputs_container = me.find( '.visitor-reg-upate-item-inputs-container' );
		inputs_container.html( response.html_data );
	}); 

	// Initialize the returning visitor data
	var RC_REG_RETURNING_VISITOR_DATA;
	$( '.visitor-reg-add-visitor-form' ).on( 'initialize-returning-visitor-data', function () {
		var me = $(this);
		var data = me.find( '.visitor-reg-returning-visitor-data' ).text();
		// Wordpress (on the front end) will filter and replace html chars like '-' and replace with '&#8211;'
		// In order to change them back to regular characters I need to create a textarea
		//	insert the data into it then ask for the text back
		data = $('<textarea />').html(data).text();
		if (data.length === 0) data = '{}';
		RC_REG_RETURNING_VISITOR_DATA = JSON.parse(data);		
	});
	$( '.visitor-reg-add-visitor-form' ).trigger( 'initialize-returning-visitor-data' );
	
	$('.visitor-reg-add-visitor-form input[name="email"]').blur(function () {
		var me = $(this);
		if (me.val() !== '') { // if they're changing the email input and it's not blank then uncheck "I have no email"
			me.closest('.visitor-reg-add-visitor-form').find('input[name="no-email"]').prop('checked', false);
		} // endif
	});

	$( '.visitor-reg-add-visitor-form input[name="no-email"]' ).change( function () {
		var me = $(this);
		var email_input = me.closest( '.visitor-reg-add-visitor-form' ).find( 'input[name="email"]' );
		if ( me.is( ':checked' ) ) {
			email_input.val('');
			email_input.prop( 'required', false );
		} else {
			email_input.prop( 'required', true );
		} // endif
	});

	// Standalone Server
	$('.visitor-reg-manager-container').on( 'refresh-event-select', function(evt) {
		$( '.reg-man-rc-application-busy' ).addClass( 'is-active' ); // shrowd the entire UI while we're busy, no clicks anywhere
		location.reload(); // the simplest way to refresh the select is to just reload the page so do that for now
	})
	$('.standalone-download-events-dialog').dialog({
		autoOpen	: false,
		modal		: true,
		width		: 'auto',
		height		: 'auto',
		buttons : [
			{ 
				'text' : __('Cancel', 'reg-man-rc'),
				'click': function() { $(this).dialog('close'); }
			},
			{
				'text' : __('OK', 'reg-man-rc'),
				'click' : function() { $(this).trigger('standalone-download-events-form-submit') },
				'class' : 'standalone-download-events-dialog-done-button'
			}
		]
	});
	$( '.standalone-download-events-dialog' ).on( 'set-button-enablement', function( evt ) {
		var me = $(this);
		var eventInput = me.find('input[name="event[0][name]"]'); // do I have inputs to submit?
		var disabled = (eventInput.length === 0); // If there are none then disable the done button
		$('.standalone-download-events-dialog-done-button').prop('disabled', disabled);		
	});
	$( '.standalone-download-events-dialog' ).on( 'submit-success', function( evt, response ) {
		var htmlData = response.htmlData;
		if ( ( typeof( htmlData ) == 'undefined' ) && ( htmlData == null ) ) {
			$(this).dialog('close'); // close my dialog, provided this isn't the result of getting the form contents
			$('.visitor-reg-manager-container').trigger('refresh-event-select');
		} // endif
	});
	$( '.standalone-download-events-dialog' ).on( 'submit-success-html-update', function(evt) {
		// This event occurrs after the html has been updated.  I need this so I can inspect the new html
		$(this).trigger('set-button-enablement');
	});
	$( '.standalone-download-events-dialog' ).on( 'submit-success-complete', function(evt) {
		$('.visitor-reg-manager-container').trigger('refresh-event-select'); // refresh the event select
	});
	$('.visitor-reg-manager-container .standalone-download-events').click(function(evt) {
		evt.preventDefault();
		var downloadDialog = $('.standalone-download-events-dialog');
		// When the form is shown we need to remove any old inputs then get a fresh form from the server
		// The form is refreshed by submitting a blank form, the server will respond with the new contents
		downloadDialog.find('.standalone-download-events-form').html('Retrieving events.  Please wait...');
		$('.standalone-download-events-dialog-done-button').prop('disabled', true);
		downloadDialog.find('.standalone-download-events-form').trigger('standalone-download-events-form-submit');
		downloadDialog.dialog('open');
	});
	$('.standalone-download-events-dialog').on('standalone-download-events-form-submit', function() {
		$(this).find('.standalone-download-events-form').trigger('ajax-submit'); // triggers my ajax form submission
	});
/*
	$('.standalone-create-event-dialog').dialog({
		autoOpen	: false,
		modal		: true,
		width		: 'auto',
		height		: 'auto',
		buttons : [
			{ 
				'text'	: __('Cancel', 'reg-man-rc'),
				'click'	: function() { $(this).dialog('close'); }
			},
			{
				'text'	: __('Create', 'reg-man-rc'),
				'click' : function() { $(this).trigger('standalone-create-event-form-submit') },
				'class' : 'standalone-create-event-dialog-done-button'
			}
		]
	});
	$('.visitor-reg-manager-container .standalone-create-event').click(function(evt) {
		evt.preventDefault();
		var createDialog = $('.standalone-create-event-dialog');
		createDialog.find('.standalone-create-event-form').trigger('form-reset'); // reset the form (remove errors etc.)
		createDialog.dialog('open');
	});
	$('.standalone-create-event-dialog').on('standalone-create-event-form-submit', function() {
		$(this).find('.standalone-create-event-form').trigger('ajax-submit'); // triggers my ajax form submission
	});
	$('.standalone-create-event-dialog').on('submit-success-complete', function(evt) {
		$(this).dialog('close'); // close my dialog after successful submit
		$('.visitor-reg-manager-container').trigger('refresh-event-select');
	});
*/
	
	// Dialogs, Accordion, Autocomplete
	$( '.visitor-reg-add-event-dialog' ).dialog({
		autoOpen	: false,
		modal		: true,
		width		: 'auto',
		height		: 'auto',
	});
	$( '.visitor-reg-add-event-dialog' ).on( 'visitor-reg-add-event-form-submit', function( event ) {
		var me = $( this );
		var form = me.find( '.visitor-reg-add-event-form' );
		form.trigger( 'submit' );
	});
	$( '.visitor-reg-add-event-dialog' ).on( 'submit-success', function( event, response ) {
		var me = $( this );
		me.dialog( 'close' );
		// Refresh the calendar!
		$( '.reg-man-rc-calendar-view' ).trigger( 'refresh-calendar' );
	});
	$( '.visitor-reg-add-event' ).on( 'click', function( event ) {
		var me = $( this );
		var add_event_dialog = $( '.visitor-reg-add-event-dialog' );
		var form = add_event_dialog.find( '.visitor-reg-add-event-form' );
		form.trigger( 'reset' );
		add_event_dialog.dialog( 'open' );
	});
	$( '.visitor-reg-add-event-dialog-cancel-button' ).on( 'click', function( event ) {
		var me = $( this );
		var add_event_dialog = $( '.visitor-reg-add-event-dialog' );
		add_event_dialog.dialog( 'close' );
	});

	$( '.form-user-message-dialog' ).dialog({
		autoOpen	: true,
		modal		: true,
		width		: 'auto',
		height		: 'auto',
		position	: { my: 'top', at: 'top+150' }, // Try to put it 150px from top
		buttons : [
			{ 
				'text' : __( 'Close', 'reg-man-rc' ), // The button has an icon so no text is needed
				'click': function() { $( this ).dialog( 'close' ); }
			}
		]
	});
	
	$( '.visitor-reg-manager-event-select-form' ).submit(function(evt) {
		// add a busy indicator
		$( '.reg-man-rc-application-busy' ).addClass( 'is-active' ); // shrowd the entire UI while we're busy, no clicks anywhere
	});
	
	$( '.reg-manager-message-dialog' ).dialog({
		autoOpen	: false,
		modal		: true,
		width		: 'auto',
		height		: 'auto',
		buttons : [
			{ 
				'text' : __( 'Close', 'reg-man-rc' ),
				'click': function() { $( this ).dialog( 'close' ); }
			}
		]
	});

	// Draggable/sortable items
	$( '.visitor-reg-form .visitor-reg-item-list' ).sortable({
		placeholder: 'ui-state-highlight',
		forcePlaceholderSize: true,
	});

	$( '.visitor-reg-form-cancel' ).click( function() {
		var me = $( this );
		var container = me.closest( '.visitor-reg-manager-container' );
		container.trigger( 'show-swappable', [ '.reg-man-rc-visitor-reg-list-container' ] );	
	});

	$( '.visitor-reg-form .visitor-item-add, .add-item-to-visitor-reg-form .visitor-item-add' ).click(function() {
		var me = $( this );
		var container = me.closest( '.visitor-reg-items-section' );
		var list = container.find( '.visitor-reg-item-list' );
		var cln = list.find( 'li:first' ).clone(); // copy the first element
		cln.find( 'input' ).val( '' ); // unset the input data
		cln.removeClass( 'error' ).find( '.error-container' ).html( '' ); // unset any error message
		cln.find( 'select' ).removeAttr( 'selected' ); // remove selected options
		cln.addClass( 'uninitialized' ); // mark it as uninitialized
		cln.appendTo( list ); // append the new item to the list
		cln.trigger( 'initialize-item-inputs' ); // initialize it
		cln.find( 'input' ).first().focus();
	});
	
	$( '.visitor-reg-item-list' ).on( 'click', '.reg-item-remove-button', function(evt) {
		var me = $( this );
		var target_item = me.closest( '.item-list-item-fieldset' );
		target_item.remove();
	});
	
	$( '.visitor-reg-add-visitor-form, .visitor-reg-update-item-form' ).on( 'submit-success', function( event, response ) {
		var me = $( this );
		var container = me.closest( '.visitor-reg-manager-container' );
		container.trigger( 'show-swappable', [ '.reg-man-rc-visitor-reg-list-container' ] );	
		$( '.visitor-reg-manager-table' ).trigger( 'datatable-reload' );
	});

	$( '.visitor-reg-add-item-to-visitor-form' ).on( 'submit-success', function( event, response ) {
		var me = $( this );
		var form_container = me.closest( '.visitor-reg-update-visitor-form-container' );
		var table = form_container.find( '.visitor-reg-visitor-items-table' );
		table.trigger( 'datatable-reload' );
		$( '.visitor-reg-manager-table' ).trigger( 'datatable-reload' );
	});

/*
	$('.add-item-to-visitor-reg-dialog').on('add-item-to-visitor-reg-form-submit', function() {
		var me = $( this );
		var my_form = me.find('.add-item-to-visitor-reg-form.reg-man-rc-ajax-form');
		my_form.trigger( 'ajax-submit' ); // triggers my ajax form submission
	});
	
	$('.add-item-to-visitor-reg-dialog').on('submit-success', function(event, response) {
		var me = $(this);
		me.dialog('close');
		// Refresh the table to show any updates
		$('.visitor-reg-manager-table').trigger('datatable-reload');
	});

	$('.visitor-is-fixed-dialog').dialog({
		autoOpen	: false,
		modal		: true,
		width		: 'auto',
		height		: 'auto',
		buttons : [
			{ 
				'text' : __('Cancel', 'reg-man-rc'),
				'click': function() { $(this).dialog('close'); }
			},
			{
				'text' : __('OK', 'reg-man-rc'),
				'click' : function() { $(this).trigger('visitor-is-fixed-form-submit') },
				'class' : 'visitor-is-fixed-dialog-button'
			}
		]
	});
	$('.visitor-is-fixed-dialog').on('visitor-is-fixed-form-submit', function() {
		$(this).find('.is-fixed-form.reg-man-rc-ajax-form').trigger('ajax-submit'); // triggers my ajax form submission
	});
	
	$('.visitor-is-fixed-dialog').on('submit-success', function(event, response) {
		var me = $(this);
		me.dialog('close');
		var fixedVal = me.find('input[name="item-is-fixed"]:checked').val();
		if (fixedVal !== 'NULL') {
			var surveyDialog = $('.visitor-survey-dialog'); 
			surveyDialog.find('.visitor-survey-thank-you-message').show();
			surveyDialog.find('.visitor-survey-form').trigger('form-reset'); // reset the form (remove errors etc.)
			surveyDialog.dialog('open');
		} // endif
		// Refresh the table to show any updates
		$('.visitor-reg-manager-table').trigger('datatable-reload');
	});
	
	$('.visitor-survey-dialog').dialog({
		autoOpen	: false,
		modal		: true,
		width		: 'auto',
		height		: 'auto',
		buttons : [
			{ 
				'text' : __('No Thanks', 'reg-man-rc'),
				'click': function() { $(this).dialog('close'); },
				'class' : 'visitor-survey-dialog-close-button'
			},
			{
				'text' : __('Done', 'reg-man-rc'),
				'click' : function() { $(this).trigger('visitor-survey-form-submit') },
				'class' : 'visitor-survey-dialog-submit-button'
			}
		]
	});
	$( '.visitor-survey-dialog').on('visitor-survey-form-submit', function() {
		$(this).find('.visitor-survey-form.reg-man-rc-ajax-form').trigger('ajax-submit'); // triggers my ajax form submission
	});
	$( '.visitor-survey-dialog').on('submit-success', function(event, response) {
		var me = $(this);
		me.dialog('close');
		var thankYouDialog = $( '.reg-man-rc-visitor-survey-thank-you-dialog' );
		thankYouDialog.dialog('open');
	});
*/	
/* This stuff was removed from the survey form
	$('.visitor-survey-form .visitor-survey-item-list + .visitor-item-add').click(function() {
		var list = $(this).prev();
		var cln = list.find('li:first').clone(); // copy the first element
		cln.find('input').val(''); // unset the input data
		cln.removeClass('error').find('.error-container').html(''); // unset any error message
		cln.find('select').removeAttr('selected'); // remove selected options
		cln.addClass('uninitialized'); // mark it as uninitialized
		cln.appendTo(list); // append the new item to the list
		cln.trigger('initialize-item-inputs'); // initialize it
	});
*/
	
	$( '.autocomplete-item-desc-container' ).on( 'initialize-item-inputs', function( evt ) {
		var me = $(this);
		var input_containers = me.find( '.item-list-item-fieldset.uninitialized' );
		var autocomplete_data = $( 'body' ).find( '.visitor-reg-item-autocomplete-data' ).text();
		// Wordpress (on the front end) will filter and replace html chars like '-' and replace with '&#8211;'
		// In order to change them back to regular characters I need to create a textarea
		//	insert the data into it then ask for the text back
		autocomplete_data = $('<textarea />').html( autocomplete_data ).text();
		if ( autocomplete_data.length === 0 ) autocomplete_data = '[]';
		var autocomplete_data_array = JSON.parse( autocomplete_data );

		// translators: %1$s is an item description, e.g. "Lamp", %2$s is a list of alternate descriptions, e.g. "Light, Desklight"
		var with_alt_format = __( '%1$s (%2$s)', 'reg-man-rc' ); // used to construct autocomplete suggestion labels

		input_containers.each( function() {
			var container = $( this );
			container.on( 'item-desc-autocomplete-select', function( evt, desc, type, station ) {
				var me = $( this );
				me.find( 'input[name="item-desc[]"]' ).val( desc );
				me.find( 'select[name="item-type[]"]' ).val( type );
				me.find( 'select[name="fixer-station[]"]' ).val( station );
			});
			var item_desc_input = container.find('input[name="item-desc[]"]');
			item_desc_input.autocomplete({
				source:
					function(request, response) { // I need my own function to match 'desc' rather than 'value'
						var term = $.ui.autocomplete.escapeRegex( request.term );
						var desc_exp = '^' + term; // match at the start of the line plus the term
						var alt_exp = desc_exp + '|,\\s*' + term // match start of line or after comma
						var desc_matcher = new RegExp( desc_exp, 'i' );
						var alt_matcher = new RegExp( alt_exp, 'i' );
						response($.grep(autocomplete_data_array, function( value ) {
							var desc_match = desc_matcher.test( value.item_desc );
							var alt_match = alt_matcher.test( value.item_alt_desc )
							return ( desc_match || alt_match );
						}));
					},
				select: function( evt, ui ) {
					container.trigger( 'item-desc-autocomplete-select', [ ui.item.item_desc, ui.item.type_id, ui.item.station_id ] );
					evt.preventDefault();
				},
				minLength: 2,
//				focus: function( evt, ui ) {
//					container.trigger( 'item-desc-autocomplete-select', [ ui.item.item_desc, ui.item.type_id ] );
//					evt.preventDefault();
//				},
			})
			.data( 'ui-autocomplete' )._renderItem = function( ul, item ) {
				var name;
				if ( item.item_alt_desc == '' ) {
					name = item.item_desc;
				} else {
					name = sprintf( with_alt_format, item.item_desc, item.item_alt_desc );
				} // endif
				return $( '<li>' )
					.append( '<a>' + name + '</a>' )
					.appendTo( ul );
			};
		});
		input_containers.removeClass( 'uninitialized' ); // don't do this twice
	});
	$( '.autocomplete-item-desc-container' ).trigger( 'initialize-item-inputs' );

	$( '.autocomplete-visitor-name-container' ).on( 'visitor-name-autocomplete-select', function( evt, data ) {
		var me = $( this );
		var id_input			= me.find( 'input[name="visitor-id"]' );
		var full_name_input		= me.find( 'input[name="full-name"]' );
//		var public_name_input	= me.find( 'input[name="public-name"]' );
		var email_input			= me.find( 'input[name="email"]' );
		var no_email_input		= me.find( 'input[name="no-email"]' );
		var reset_button		= me.find( '.visitor-name-reset' );

		if ( typeof( data.id ) !== 'undefined' ) {
			id_input.val( data.id );
		} // endif

		if ( typeof( data.full_name ) !== 'undefined' ) {
			full_name_input.val( data.full_name );
		} // endif

//		if ( typeof( data.public_name ) !== 'undefined' ) {
//			public_name_input.val( data.public_name );
//		} // endif
		
		if ( typeof( data.obs_email ) !== 'undefined' ) {
			email_input.val( data.obs_email );
			var is_no_email = ( ( data.obs_email == '' ) || ( data.obs_email == null ) );
			no_email_input.prop( 'checked', is_no_email );
		} // endif
		
		var is_join = typeof( data.join_list !== 'undefined' ) ? data.join_list : 0;
		if ( is_join == 1 ) {
			me.find( 'input[name="mail-list"][value="ALREADY"]' ).prop( 'checked', true );
		} else {
			me.find( 'input[name="mail-list"][value="NO"]' ).prop( 'checked', true );
		} // endif
		
		full_name_input.prop( 'disabled', true );
//		public_name_input.prop( 'disabled', true );
		email_input.prop( 'disabled', true );
		no_email_input.prop( 'disabled', true );

		reset_button.show();
		
		me.find('.input-item.error').trigger('remove-error');

	});


	$( '.autocomplete-visitor-name-container' ).on( 'initialize-visitor-name-inputs', function(evt) {
		// This function will turn autocomplete on or off for visitor names depending on whether they are first timer
		var me = $(this);
		var full_name_input = me.find('input[name="full-name"]');
//		var emailInput = me.find('input[name="email"]');
//		var noEmailInput = me.find('input[name="no-email"]');
		var autocomplete_data_array = RC_REG_RETURNING_VISITOR_DATA;
		
		full_name_input.autocomplete({
			source:
				function( request, response ) { // I need my own function to match full name rather than 'value'
					var is_returning = me.find( 'input[ name="first-time" ][ value="NO" ]' ).prop( 'checked' );
//					var full_name = full_name_input.val();
					if ( is_returning ) {
						var name_matcher = new RegExp( '^' + $.ui.autocomplete.escapeRegex( request.term ), 'i' );
						var grep_result;
						grep_result = $.grep( autocomplete_data_array, function( value ) {
							return name_matcher.test( value.full_name );
						});
//						console.log( 'grep result length: ' + grep_result.length );
						response( grep_result );
					} else {
						return []; // Return no options
					} // endif
				},
			select: function( evt, ui ) {
//				console.log( 'You picked ' + ui.item.label );
				me.trigger( 'visitor-name-autocomplete-select', [ ui.item ] );
				evt.preventDefault();
			},
			minLength: 2,
		}).data( 'ui-autocomplete' )._renderItem = function( ul, item ) {

			var name;
			if ( item.obs_email !== '' ) {
				
				// translators: %1$s is replaced with a full name, %2$s with a partially obscured email
				name = sprintf( __( '%1$s (%2$s)', 'reg-man-rc' ), item.full_name, item.obs_email );
				
			} else {

				// translators: %1$s is replaced with a full name
				name = sprintf( __( '%1$s (no email)', 'reg-man-rc' ), item.full_name );
				
			} // endif
//			console.log( name );
			return $( '<li>' ).append( '<a>' + name + '</a>' ).appendTo( ul );

		};
	});
	$( '.autocomplete-visitor-name-container' ).trigger( 'initialize-visitor-name-inputs' );

	$( '.autocomplete-visitor-name-container input[name="first-time"]' ).on( 'change', function( evt ) {
		var me = $( this );
		container = me.closest( '.autocomplete-visitor-name-container' );
		container.trigger( 'reset-name-fields' );
	});
	
	$( '.autocomplete-visitor-name-container .visitor-name-reset' ).on( 'click', function( evt ) {
		var me = $( this );
		container = me.closest( '.autocomplete-visitor-name-container' );
		container.trigger( 'reset-name-fields' );
	});
	
	$( '.autocomplete-visitor-name-container' ).on( 'reset-name-fields', function( evt ) {
		var me = $( this );
		var id_input			= me.find( 'input[name="visitor-id"]' );
		var full_name_input		= me.find( 'input[name="full-name"]' );
		var is_name_disabled = full_name_input.prop( 'disabled' );
		var email_input			= me.find( 'input[name="email"]' );
		var no_email_input		= me.find( 'input[name="no-email"]' );
		var reset_button		= me.find( '.visitor-name-reset' );

		id_input.val( '' );
		full_name_input.prop( 'disabled', false ); // Note that we don't always want to remove the value
		email_input.val( '' );
		email_input.prop( 'disabled', false );
		email_input.prop( 'required', true );
		no_email_input.prop( 'checked', false );
		no_email_input.prop( 'disabled', false );
		reset_button.hide();
		
		if ( is_name_disabled ) {
			container.trigger( 'initialize-visitor-name-inputs' ); // The autocomplete needs to be re-initialized
		} // endif

	});
	
	$( '.visitor-reg-form' ).on( 'form-reset', function( evt ) {
		var me = $( this );
		me.find( '.item-list-item-fieldset' ).not( ':first' ).remove(); // remove all the additional items
		var container = me.closest( '.autocomplete-visitor-name-container' );
		container.trigger( 'reset-name-fields' );
	});
	
/*
	// Validation
	$('.fixer-reg-form').on('js-additional-verification', function(evt) {
		// Additional validation for the visitor reg form
		// We need to make sure that 'Apprentice' is not the only item checked in the roles section
		var me = $(this);
		var fixerRoles = me.find('.fixer-checkbox-list');
		var checked = fixerRoles.find('input:checked');
		var apprenticeCheckbox = me.find('input[name="is-apprentice"]');
		var isApprentice = apprenticeCheckbox.is(':checked');
		if ((checked.length == 1) && (isApprentice)) {
			var inputItem = apprenticeCheckbox.closest('.input-item');
			errMsg = __('You must select the fixer role you will apprentice with', 'reg-man-rc');
			inputItem.addClass('error');
			inputItem.find('> .error-container').html(errMsg);
			fixerRoles.find('input:checkbox').on('change', function() {
				inputItem.trigger('remove-error');
			});
		} // endif
	});

	// Additional verifications for visitor registration form
	$('.visitor-reg-form .visitor-reg-items-section, .add-item-to-visitor-reg-form').on('js-additional-verification', function(evt) {
		// Additional validation for the items section of the visitor reg form
		// We need to make sure that at least one item description is provided and that for each
		//	item description that's not blank, the corresponding fields are provided
		var me = $(this);
		var itemDescArr = me.find('input[name="item-desc[]"]');
		var hasItems = false;
		itemDescArr.each(function(index, itemDescElem) {
			if ($(itemDescElem).val()) {
				hasItems = true;
			} // endif
		});
		if (hasItems == false) {
			var firstItem = me.find('.visitor-reg-item-list .item-list-item').first();
			firstItem.addClass('error');
			firstItem.find('> .error-container').html(__('Please provide an item description', 'reg-man-rc'));
			firstItem.find('input[name="item-desc[]"]').on('input', function() {
				firstItem.trigger('remove-error');
			});
		} // endif
	});
	
	$('.visitor-reg-form .visitor-reg-name-section').on('js-additional-verification', function(evt) {
		// Additional validation for the name section of the visitor reg form
		// We need to make sure that either email is filled in or the checkbox is checked for 'I have no email'
		var me = $(this);
		var emailInput = me.find('input[name="email"]');
		var noEmailInput = me.find('input[name="no-email"]');
		var emailVal = emailInput.val();
		var noEmailVal = !(noEmailInput.is(':checked'));
		if (!(emailInput.val()) && !(noEmailInput.is(':checked'))) {
			var emailInputItem = emailInput.closest('.input-item');
			emailInputItem.addClass('error');
			var msg = __('Please provide your email or check the box to indicate that you have none', 'reg-man-rc');
			emailInputItem.find('> .error-container').html(msg);
			emailInput.on('input', function() {
				emailInputItem.trigger('remove-error');
			});
			noEmailInput.on('change', function() {
				emailInputItem.trigger('remove-error');
			});
		} // endif
	});
*/


	$( '.visitor-reg-visitor-items-table' ).on( 'init-datatable', function( evt ) {
		
		var me = $( this );
		var dom_setting = 'rt';

		var status_type_col_index = 3;
		var status_order_col_index = 4;
		var status_type_order_col_index = 5;
		
		var datatable = me.DataTable({
			'ajax' : {
				'url'  : me.data( 'ajax-url' ),
				// We'll use a function here so it gets executed on every reload
				'data' : function( data ) {
					data.action			= me.data( 'ajax-action' ),
					data.event_key		= me.data( 'event-key' ),
					data._wpnonce		= me.data( 'ajax-nonce' ),
					data.visitor_id		= me.data( 'visitor-id' )
				},
			},
			order : [
				[ status_type_order_col_index, 'asc' ],
				[ status_order_col_index, 'asc' ],
			],
			responsive: true,
			rowGroup : { // rowGroup option MUST be included, it can't be enabled later otherwise
				// We need to update the correct column during init, doing it here does not work properly
				dataSrc: status_type_col_index,
				startRender: function ( rows, group ) {
					/* Translators: %1$s is a row group heading, %2$s is a count of rows in that group */
					var group_heading_format = __( '%1$s (%2$s)', 'reg-man-rc' );
					return sprintf( group_heading_format, group, rows.count() );
				} // endif
			},
			'autoWidth' : false,
			'dom': dom_setting,
			'paging' : false, // hide pagination and just show everything
			'processing' : false,
			'columnDefs'	: [
				{
					'targets'	: 'col-hidden',
					'searchable': false, // don't allow searching on hidden columns
					'visible'	: false,
				},
				{
					'targets'	: 'col-not-sortable',
					'sortable'	: false,
				},
			],
			'language' : {
				'emptyTable'		: '', //__( 'No items registered', 'reg-man-rc' ),
			    'zeroRecords'		: '', //__( '', 'reg-man-rc' ),
			    'thousands'			: _x(',', 'Thousands separator', 'reg-man-rc' ),
				'loadingRecords'	: ' ',
				'processing'		: __( 'Loading&hellip;.', 'reg-man-rc' ),
			},
		});
		
		me.data( 'datatable', datatable );
	});
	
	$( '.visitor-reg-visitor-items-table' ).on( 'processing.dt', function( evt, settings, is_processing ) {
		var me = $( this );
		var datatable_container = me.closest( '.visitor-reg-visitor-item-list-section' );
		var busy_div = datatable_container.find( '.reg-man-rc-visitor-reg-busy' );
		if ( is_processing ) {
			busy_div.addClass( 'is-active' ); // shrowd the the table UI while we're busy, no clicks anywhere
		} else {
			busy_div.removeClass( 'is-active' );
		} // endif
	});


	$( '.visitor-reg-visitor-items-table' ).trigger( 'init-datatable' );

	$( '.visitor-reg-visitor-items-table' ).on( 'datatable-reload', function( evt ) {
		var me = $( this );
		var datatable = me.data( 'datatable' );
		datatable.ajax.reload();
	});

	$( '.visitor-reg-visitor-items-table' ).on( 'in-place-edit-submit-start', function( evt ) {
		$( '.reg-man-rc-application-busy' ).addClass( 'is-active' ); // shrowd the entire UI while we're busy, no clicks anywhere
	});
		
	$( '.visitor-reg-visitor-items-table' ).on( 'in-place-edit-submit-end', function( evt ) {
		// When the item status has been changed I need to trigger a table reload to update everything
		// Note that even when the submit fails, like the visitor has too many active items,
		//  I still need to reload the status for the item to display the current value
		var me = $( this );
		me.trigger( 'datatable-reload' );
		$( '.reg-man-rc-application-busy' ).removeClass( 'is-active' );

		// Also update the main visitor registration list table
		var reg_list_table = $( '.visitor-reg-list-table' );
		reg_list_table.trigger( 'datatable-reload' );
	});

	$( '.reg-man-rc-visitor-details-container' ).on( 'swappable-shown', function ( evt ) {
		var me = $( this );
		var table = me.find( '.visitor-reg-visitor-items-table' );
		var datatable = table.data( 'datatable' );
		
		// To get responsive to work properly we must load the data AFTER the table is shown
		datatable.clear().draw(); // blank out the table before we reload this visitor
		table.trigger( 'datatable-reload' );
	});

});