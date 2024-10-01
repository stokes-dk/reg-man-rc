/**
 * This is the base javascript to support the "Remove" operation for our CPTs
 */
jQuery(document).ready(function($) {
	$( '.reg-man-rc-remove-cpt-dialog' ).dialog({
		autoOpen	: false,
		// This should be modal but it's not working right now because the Select2 input is disabled
//		modal		: true,
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
	$( '.reg-man-rc-remove-cpt-button' ).on( 'click', function( evt ) {
		var me = $( this );
		var remove_dialog = $( '.reg-man-rc-remove-cpt-dialog' );
		var record_id = me.data( 'record-id' );
		var loader_form = remove_dialog.find( '.cpt-remove-form-loader' );
		var record_id_input = loader_form.find( 'input[ name="record-id" ]');
		record_id_input.val( record_id );
		loader_form.trigger( 'ajax-submit' );
		var inputs_container = remove_dialog.find( '.reg-man-rc-remove-form-inputs-container' );
//		var text = __( 'Loading&hellip;', 'reg-man-rc' );
//		inputs_container.html( text );
		remove_dialog.dialog( 'open' );
	});

	$( '.cpt-remove-form-loader' ).on( 'submit-success', function( evt, response ) {
		var me = $( this );
		var container = me.closest( '.rc-reg-man-dynamic-remove-form-container' );
		var inputs_container = container.find( '.reg-man-rc-remove-form-inputs-container' );
//		inputs_container.html( response.html_data );
//		inputs_container.find( 'select.combobox' ).trigger( 'init-combobox' );
	});

});