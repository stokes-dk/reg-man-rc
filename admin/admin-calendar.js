/**
 * This is the javascript for the admin calendar
 */
jQuery(document).ready(function($) {

	$( '.supplemental-data-dialog' ).dialog({
		autoOpen	: false,
		modal		: true,
		width		: '80%',
		height		: 'auto',
		position	: { my: 'top', at: 'top+150' }, // Try to put it 150px from top
		buttons : [
			{
				'text' : __( 'Cancel', 'reg-man-rc' ),
				'click': function() { $( this ).dialog( 'close' ); }
			},
			{
				'text' : __( 'Save', 'reg-man-rc' ),
				'click': function() { $( this ).trigger( 'supplemental-data-submit' ); }
			}
		]
	});
	$( '.reg-man-rc-admin-calendar-page-body .supplemental-items-button' ).click( function() {
		$( '.supplemental-item-dialog' ).dialog('open');
	});
	$( '.reg-man-rc-admin-calendar-page-body .supplemental-visitors-button' ).click( function() {
		$( '.supplemental-visitor-dialog' ).dialog('open');
	});
	$( '.reg-man-rc-admin-calendar-page-body .supplemental-volunteers-button' ).click( function() {
		$( '.supplemental-volunteer-dialog' ).dialog('open');
	});

	$( '.supplemental-data-dialog' ).on( 'supplemental-data-submit', function() {
		var me = $( this );
		me.find( '.reg-man-rc-supplemental-data-ajax-form' ).trigger( 'ajax-submit' );
	});

	$( '.supplemental-data-dialog' ).on( 'submit-success', function() {
		$( this ).dialog( 'close' );
		location.reload(); // the simplest way to refresh is to just reload the page so do that for now
	});
	
	$( '.email-list-dialog' ).dialog({
		autoOpen	: false,
		modal		: true,
		width		: 'auto',
		height		: 'auto',
		position	: { my: 'top', at: 'top+150' }, // Try to put it 150px from top
	});
	$( '.reg-man-rc-admin-calendar-page-body .volunteer-reg-email-list-button' ).click( function() {
		$( '.volunteer-reg-email-list-dialog' ).dialog('open');
	});


	$( '.import-data-dialog' ).dialog({
		autoOpen	: false,
		modal		: true,
		width		: 'auto',
		height		: 'auto',
		position	: { my: 'top', at: 'top+150' }, // Try to put it 150px from top
	});

	$( '.import-items-dialog' ).on( 'submit-success-html-update', function( evt, response ) {
		var me = $( this );
		var form = me.find( '.reg-man-rc-item-import-form' );
		var reload_input = form.find( 'input[name="reload"]' );
		console.log( reload_input.val() );
		if ( reload_input.val() == 'TRUE' ) {
			// The import is complete so we should reload the table
			var items_table = $( '.admin-stats-table.items-admin-table' );
			items_table.trigger( 'load_datatable' );
		} // endif
	});

	$( '.reg-man-rc-admin-calendar-page-body .import-items-button' ).click( function() {
		var import_dialog = $( '.import-items-dialog' );
		import_dialog.trigger( 'handle-reload-dialog' );
		import_dialog.dialog( 'open' );
	});
	// The reload button is dynamically created inside a dialog so we need to listen on the dialog
	$( '.import-items-dialog' ).on( 'click',  '.import-reload-button', function() {
		var me = $( this );
		me.trigger( 'handle-reload-dialog' );
	});
	$( '.import-items-dialog' ).on( 'handle-reload-dialog', function( evt ) {
		var me = $( this );
		var form = me.find( '.reg-man-rc-item-import-form' );
		var reload_input = form.find( 'input[name="reload"]' );
		reload_input.val( 'TRUE' );
		form.trigger( 'ajax-submit' );
	});
/* TODO: Work in progress to allow users to import data like items
	$( '.import-data-dialog' ).on( 'import-data-submit', function() {
		var me = $( this );
		me.find( '.reg-man-rc-import-data-ajax-form' ).trigger( 'ajax-submit' );
	});
*/
	// The active tab is stored so it can be shown when the page is reloaded, I need to load data for that tab 
	$( '.reg-man-rc-admin-event-calendar-event-view-container' ).on( 'init-active-tab', function( evt ) {
		var me = $( this );
		me.trigger( 'load_all_stats_data' ); // initialize the active tab
	});
 	$( '.reg-man-rc-admin-event-calendar-event-view-container .reg-man-rc-tabs-container' ).on( 'tabs-init-complete', function( evt ) {
		var me = $( this );
		me.trigger( 'init-active-tab' );
	});
	// In case initialization is already complete at this point, we still need to perform this
 	$( '.reg-man-rc-admin-event-calendar-event-view-container .reg-man-rc-tabs-container.tabs-init-complete' ).trigger( 'init-active-tab' );

});