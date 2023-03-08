/**
 * The script used for term ordering
 */
jQuery(document).ready(function($) {
	$( '#reg-man-rc-term-order-action' ).on( 'initialize', function( evt ) {
		var me = $( this );
		var parent = me.parent();
		var bulk_actions = parent.find( '.tablenav.top .bulkactions' );
		me.detach();
		bulk_actions.append( me );
		me.show();
	});
	$( '#reg-man-rc-term-order-action' ).trigger( 'initialize' );
	
	$( '.term-order-dialog' ).dialog({
		autoOpen	: false,
		modal		: true,
		width		: 'auto',
		height		: 'auto',
		position	: { my: 'top', at: 'top+150' }, // Try to put it 150px from top
		buttons : [
			{
				'text' : __( 'Cancel', 'reg-man-rc' ),
				'click': function() { $( this ).dialog( 'close' ); }
			},
			{
				'text' : __( 'Save', 'reg-man-rc' ),
				'click': function() { $( this ).trigger( 'submit-form' ); }
			}
		]
	});
	$( '.term-order-dialog' ).on( 'submit-form', function( evt ) {
		var me = $( this );
		var form = me.find( '.reg-man-rc-assign-term-order-form' );
		form.trigger( 'ajax-submit' );
	});
	$( '#reg-man-rc-term-order-action' ).on( 'click', function( evt ) {
		var dialog = $( '.term-order-dialog' );
		var get_form = $( '.reg-man-rc-get-terms-order-form-content-form' );
		get_form.trigger( 'ajax-submit' );
		var contents = dialog.find( '.term-order-dialog-contents' );
		contents.html( '' ); // remove any previous contents
		dialog.dialog( 'open' );
	});
	
	$( '.reg-man-rc-get-terms-order-form-content-form' ).on( 'submit-response-returned', function( evt, response, xmlHttpRequest, textStatus ) {
		var dialog = $( '.term-order-dialog' );
		var contents = dialog.find( '.term-order-dialog-contents' );
		var html_data = response.html_data, item_list;
		if ( ( typeof( html_data ) != 'undefined' ) && ( html_data != null ) ) {
			contents.html( html_data );
			item_list = contents.find( '.reg-man-rc-term-order-item-list' );
			item_list.sortable({
				containment: 'parent'
			});
		} // endif
	});
	$( '.reg-man-rc-get-terms-order-form-content-form' ).on( 'submit-fail', function( evt ) {
		var dialog = $( '.term-order-dialog' );
		dialog.dialog( 'close' );
	});
	
});