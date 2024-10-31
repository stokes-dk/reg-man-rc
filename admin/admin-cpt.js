/**
 * This is the javascript to support operations for our CPTs
 */
jQuery(document).ready(function($) {
	
	// Add dialog
	$( '.reg-man-rc-add-new-cpt-dialog' ).dialog({
		autoOpen	: false,
		// This should be modal but it's not working right now because the Select2 input is disabled
//		modal		: true,
		width		: '80%',
		height		: 'auto',
		position	: { my: 'top', at: 'top+150' }, // Try to put it 150px from top
		// Note that the dialog buttons cannot properly perform form validation, so the buttons must be inside the form
	});

	$( '.reg-man-rc-add-new-cpt-dialog' ).on( 'click', '.reg-man-rc-add-new-cpt-form-cancel', function( evt ) {
		// Form buttons are added dynamically so we need to listen on the parent dialog
		var me = $( this ); // This is the button
		var add_dialog = me.closest( '.reg-man-rc-add-new-cpt-dialog' );
		add_dialog.dialog( 'close' );
	});

	$( '.reg-man-rc-admin-calendar-page-body .add-volunteer-reg-button' ).click( function() {
		var me = $( this );
		var add_dialog = $( '.add-new-volunteer-reg-dialog' );
		var add_form = add_dialog.find( '.reg-man-rc-cpt-add-form' );
		var request_type_input = add_form.find( 'input[ name="request-type" ]');
		if ( request_type_input.val() == 'get-form' ) {
			add_form.trigger( 'ajax-submit' );
		} else {
//			add_form[ 0 ].reset();
			var vol_select = add_form.find( 'select[ name="volunteer-id" ]');
			vol_select.val( '' ).change();
			add_form.trigger( 'remove-all-errors' );
		} // endif
		$( '.add-new-volunteer-reg-dialog' ).dialog('open');
	});

	$( '.reg-man-rc-add-new-cpt-dialog' ).on( 'submit-success-html-update', function( evt ) {
		// When an ajax form is updated, look for any new combobox and initialize it
		var me = $( this );
		me.find( 'select.combobox' ).trigger( 'init-combobox' );
	});

	$( '.reg-man-rc-add-new-cpt-dialog' ).on( 'submit-success-complete', function( evt ) {
		// This is triggered only when there is no html update and no errors occurred
		var me = $( this ); // This is the button
		var add_dialog = me.closest( '.reg-man-rc-add-new-cpt-dialog' );
		add_dialog.dialog( 'close' );
	});

	$( '.reg-man-rc-add-new-cpt-dialog.add-new-volunteer-reg-dialog' ).on( 'submit-success-complete', function( evt ) {
		$( '.admin-cpt-vol-reg-list-change-listener' ).trigger( 'admin-cpt-vol-reg-list-changed' );
	});


	// Remove dialog
	$( '.reg-man-rc-remove-cpt-dialog' ).dialog({
		autoOpen	: false,
		// This should be modal but it's not working right now because the Select2 input is disabled
//		modal		: true,
		width		: '80%',
		height		: 'auto',
		position	: { my: 'top', at: 'top+150' }, // Try to put it 150px from top
		// Note that the dialog buttons cannot properly perform form validation, so the buttons must be inside the form
	});

	$( '.reg-man-rc-remove-cpt-dialog' ).on( 'click', '.reg-man-rc-remove-cpt-form-cancel', function( evt ) {
		// Form buttons are added dynamically so we need to listen on the parent dialog
		var me = $( this ); // This is the button
		var remove_dialog = me.closest( '.reg-man-rc-remove-cpt-dialog' );
		remove_dialog.dialog( 'close' );
	});

	$( '.reg-man-rc-remove-cpt-button' ).on( 'click', function( evt ) {
		var me = $( this );
		var remove_dialog = $( '.reg-man-rc-remove-cpt-dialog' );
		var record_id = me.data( 'record-id' );
		var remove_form = remove_dialog.find( '.reg-man-rc-cpt-remove-form' );
		var record_id_input = remove_form.find( 'input[ name="record-id" ]');
		record_id_input.val( record_id );
		var request_type_input = remove_form.find( 'input[ name="request-type" ]');
		request_type_input.val( 'get-form' );
		var details_container = remove_form.find( '.rc-reg-man-remove-form-details-container' );
		details_container.empty();
		remove_form.trigger( 'ajax-submit' );
		remove_dialog.dialog( 'open' );
	});
	
	$('.reg-man-rc-remove-cpt-dialog').on( 'submit-success-html-update', function( evt ) {
		// When an ajax form is updated, look for any new combobox and initialize it
		var me = $( this );
		me.find( 'select.combobox' ).trigger( 'init-combobox' );
	});

});