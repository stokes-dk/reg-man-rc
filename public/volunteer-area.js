/**
 * This is the javascript for volunteer area
 */

jQuery( document ).ready( function( $ ) {

	$( '.reg-man-rc-object-view-tabs-container' ).tabs();
	
	// The quick signup buttons are inside tooltipster popups which are a child of body
	$( 'body' ).on( 'click', '.reg-man-rc-volunteer-area-quick-signup-button', function( evt ) {
		var me = $( this ); // The button
		var event_key = me.data( 'event-key' );
		var area_container = $( '.volunteer-area-container' );
		var quick_signup_form = area_container.find( '.reg-man-rc-volunteer-area-quick-signup-form' );
		var event_input = quick_signup_form.find( 'input[ name = event-key ]' );
		event_input.val( event_key );
		quick_signup_form.trigger( 'ajax-submit' );
		me.trigger( 'popup-close' );
		var calendar_view = area_container.find( '.reg-man-rc-calendar-view' );
		calendar_view.trigger( 'set-calendar-is-loading', true );
	});
	$( '.reg-man-rc-volunteer-area-quick-signup-form' ).on( 'submit-end', function( evt, response ) {
		var me = $( this );
		var area_container = me.closest( '.volunteer-area-container' );
		var calendar_view = area_container.find( '.reg-man-rc-calendar-view' );
		calendar_view.trigger( 'refresh-calendar' );
	});
	// Note that the parts of the registration form will be added and removed so we need to listen on the form
	$( '.reg-man-rc-volunteer-registration-form' ).on( 'click', '.reg-man-rc-volunteer-registration-form-cancel-button', function( evt ) {
		var me = $( this );
		var form = me.closest( '.reg-man-rc-volunteer-registration-form' );
		var is_register_input = form.find( 'input[ name = is-register ]' );
		is_register_input.val( 0 ); // we want to unregister
		form.trigger( 'ajax-submit' );
	});
	$( '.reg-man-rc-volunteer-registration-form' ).on( 'change', 'select[ name = "station-id" ]', function( evt ) {
		var me = $( this );
		var form = me.closest( '.reg-man-rc-volunteer-registration-form' );
		var value = me.val();
		if ( value == '0' ) {
			form.find( 'input[ name = is-apprentice ]' ).prop( 'checked', false ).prop( 'disabled', true );
		} else {
			form.find( 'input[ name = is-apprentice ]' ).prop( 'disabled', false );
		} // endif
		form.trigger( 'ajax-submit' );
	});
	$( '.reg-man-rc-volunteer-registration-form' ).on( 'change', 'input[ name = "is-apprentice" ]', function( evt ) {
		var me = $( this );
		var form = me.closest( '.reg-man-rc-volunteer-registration-form' );
		form.trigger( 'ajax-submit' );
	});
	$( '.reg-man-rc-volunteer-registration-form' ).on( 'change', 'input[ name = "role-id[]" ]', function( evt ) {
		var me = $( this );
		var form = me.closest( '.reg-man-rc-volunteer-registration-form' );
		form.trigger( 'ajax-submit' );
	});
	$( '.reg-man-rc-volunteer-registration-form' ).on( 'change', 'input[ name = "volunteer-proxy-attending[]" ]', function( evt ) {
		var me = $( this );
		var form = me.closest( '.reg-man-rc-volunteer-registration-form' );
		form.trigger( 'ajax-submit' );
	});
	$( '.reg-man-rc-volunteer-registration-form' ).on( 'input', 'textarea[ name = "volunteer-comments" ]', function( evt ) {
		var me = $( this );
		var fieldset = me.closest( '.volunteer-comments-fieldset' );
		var save_button = fieldset.find( '.reg-man-rc-volunteer-reg-comments-save-button' );
		save_button.attr( 'disabled', false );
	});
	$( '.reg-man-rc-volunteer-registration-form' ).on( 'click', '.reg-man-rc-volunteer-reg-comments-save-button', function( evt ) {
		var me = $( this );
		var form = me.closest( '.reg-man-rc-volunteer-registration-form' );
		form.trigger( 'ajax-submit' );
	});
	$( '.reg-man-rc-volunteer-registration-form' ).on( 'submit-end', function( evt ) {
		var me = $( this );
		me.tooltipster( 'open' );
	});
	$( '.reg-man-rc-volunteer-registration-form' ).on( 'initialize-tooltip', function( evt ) {
		var me = $( this );
		var content = __( 'Updated', 'reg-man-rc' );
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
	$( '.reg-man-rc-volunteer-registration-form' ).trigger( 'initialize-tooltip' );

});
