/**
 * This is the javascript for the admin dashboard
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
	$( '.reg-man-rc-admin-dashboard-body .supplemental-items-button' ).click( function() {
		$( '.supplemental-item-dialog' ).dialog('open');
	});
	$( '.reg-man-rc-admin-dashboard-body .supplemental-visitors-button' ).click( function() {
		$( '.supplemental-visitor-dialog' ).dialog('open');
	});
	$( '.reg-man-rc-admin-dashboard-body .supplemental-volunteers-button' ).click( function() {
		$( '.supplemental-volunteer-dialog' ).dialog('open');
	});

	$( '.supplemental-data-dialog' ).on( 'supplemental-data-submit', function() {
		var me = $( this );
		me.find( '.reg-man-rc-supplemental-data-ajax-form' ).trigger( 'ajax-submit' );
	});

	$( '.supplemental-data-dialog' ).on( 'submit-success', function() {
		$( this ).dialog( 'close' );
		location.reload(); // the simplest way to refresh the select is to just reload the page so do that for now
	});

});