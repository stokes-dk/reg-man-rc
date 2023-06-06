/**
 * This is the main javascript shared by the public and admin interfaces
 */
const { __, _x, _n, _nx, sprintf } = wp.i18n;

jQuery(document).ready(function($) {
	
	$(document.body).append('<div class="reg-man-rc-application-busy spinner"></div>');
	$(window).on( 'unload', function( evt ) {
		// Make sure the busy div is not active when the page unloads
		// This is to avoid having it active if the user presses the back button after it was set as active 
		$( '.reg-man-rc-application-busy' ).removeClass( 'is-active' );
	});

//	We may need to initialize a combobox that is inserted dynamically after the page is rendered
	$( document.body ).on( 'init-combobox', 'select.combobox', function( evt ) {

		var me = $( this );

		var template_function = function ( option ) {
			var result;
			if ( ! option.element ) {
				result = option.text;
			} else {
				var el = $( option.element );
				if ( el.hasClass( 'select_option_none' ) ) {
					var content = '<span class="dashicons dashicons-dismiss"></span>' + option.text;
					result = $('<span class="select_option_none"></span>').html( content );
				} else if ( el.hasClass( 'select_option_add' ) ) {
					var content = '<span class="dashicons dashicons-plus-alt"></span>' + option.text;
					result = $('<span class="select_option_add"></span>').html( content );
				} else {
					result = option.text;
				} // endif
			} // endif
			return result;
		};
		var match_start = function ( params, data ) {
			// If there are no search terms, return all of the data
			if ( $.trim( params.term ) === '' ) {
				return data;
			} // endif

			var lower_term = params.term.toLowerCase();

			if ( data.element.nodeName === 'OPTION' ) {

				if ( data.text.toLowerCase().includes( lower_term ) ) {
					return data;
				} else {
					var el = $( data.element );
//					if ( el.hasClass( 'select_option_none' ) || el.hasClass( 'select_option_add' ) ) {
					if ( el.hasClass( 'select_option_add' ) ) {
						return data;
					} else {
						return null;
					} // endif
				} // endif

			} else if ( data.element.nodeName === 'OPTGROUP' ) {

				// Skip if there is no 'children' property
				if ( typeof data.children === 'undefined' ) {
					return null;
				} // endif
				
				// data.children contains the actual options that we are matching against
				var filtered_children = [];
				$.each( data.children, function ( idx, child ) {
					if ( child.text.toLowerCase().includes( lower_term ) ) {
						filtered_children.push( child );
					} else {
						var el = $( child.element );
//						if ( el.hasClass( 'select_option_none' ) || el.hasClass( 'select_option_add' ) ) {
						if ( el.hasClass( 'select_option_add' ) ) {
							filtered_children.push( child );
						} // endif
					} // endif
				});
	
				// If we matched any of the group's children, then set the matched children on the group
				// and return the group object
				if ( filtered_children.length ) {
					var modified_data = $.extend( {}, data, true );
					modified_data.children = filtered_children;
					return modified_data;
				} // endif
	
				// Return `null` if the term should not be displayed
				return null;
			} // endif
		} // endif
		
		me.select2({
			matcher :			match_start,
			templateResult :	template_function,
			templateSelection :	template_function
		});
		
		me.prop( 'disabled', false );

	});
	
	$( 'select.combobox' ).trigger( 'init-combobox' );
	
	$('.reg-man-rc-ajax-form').on( 'submit-success-html-update', function( evt ) {
		// When an ajax form is updated, look for any new combobox and initialize it
		var me = $( this );
		me.find( 'select.combobox' ).trigger( 'init-combobox' );
	});
	
	$( '.radio-input-list input.radio' ).on( 'change', function( evt ) {
		var list = $(this).closest( '.radio-input-list' );
		if ( list.length !== 0 ) {
			var custom = list.find( 'input[type="radio"].radio.custom' );
			if ( custom.length !== 0 ) {
				var text_input = list.find( 'input[type="text"].radio-custom-input' );
//				var selected = list.find( 'input[type="radio"]:CHECKED' );
//				if ( custom == selected ) {
				if ( custom.is( ':CHECKED' ) ) {
					text_input.prop( 'disabled', false );
					text_input.prop( 'required', true );
				} else {
					text_input.prop( 'disabled', true );
					text_input.prop( 'required', false );					
				} // endif
			} // endif
		} // endif
	});
	
	$( '.visitor-details-container input[name="visitor_full_name"]' ).change( function( evt ) {
		var me = $(this);
		var container = me.closest( '.visitor-details-container' );
		var public_name_input = container.find( 'input[name="visitor_public_name"]' );
		var name = me.val();
		var split = name.split( ' ' );
		var pub_name = '';
		if ( split.length == 1 ) {
			pub_name = name;
		} else {
			var first_name = split[ 0 ];
			var last_name = split[ split.length - 1 ];
			var last_init = ( last_name.length > 0 ) ? last_name[ 0 ] : '';
			pub_name = first_name + ' ' + last_init;
		} // endif
		public_name_input.val( pub_name );
	});

	/* Popup editable */	
	$( 'body' ).on( 'init-popup-editable', '.reg-man-rc-popup-editable-container', function( evt ) {
		var me = $( this );
		var editable = me.find( '.reg-man-rc-popup-editable' );
		var editor_form = me.find( '.reg-man-rc-popup-editor-form' );
		var tooltipster_args = {
				interactive		: true,
				position		: 'top',
				theme			: 'tooltipster-shadow',
				trigger			: 'click',
		};
		editor_form.data( 'editable', editable ); // We need this in the form so it can signal events to us
		tooltipster_args.content = editor_form;
		editable.tooltipster( tooltipster_args );
	});
	
	$( '.reg-man-rc-popup-editable-container' ).trigger( 'init-popup-editable' );
	
	$( 'body' ).on( 'input', '.reg-man-rc-popup-editor-form', function( evt ) {
		var me = $( this ); // the input that was changed
		var editable = me.data( 'editable' );
		var method = me.attr( 'method' );
		var settings = {
				url		: me.attr( 'action' ),
				type	: method,
		};
		settings.data = { action : me.data( 'ajax-action' ) };
		settings.data.formData = me.serialize();
		editable.trigger( 'edit-submit-start' );

		$.ajax(
			settings
			).done( function( response, textStatus, jqXHR ) {
				editable.trigger( 'edit-submit-response-returned', [ response, jqXHR, textStatus ] );
//				console.log( response );
			}).fail( function( jqXHR, textStatus, error ) {
				editable.trigger( 'edit-submit-fail', [ jqXHR, textStatus, error ] );
				console.log( 'Editable form submit failed, text status: ' + textStatus + ', error: ' + error );
			}).always( function( ) {
				editable.trigger( 'edit-submit-end' );
		});
	});
	
	$( 'body' ).on( 'edit-submit-start', '.reg-man-rc-popup-editable', function( evt ) {
		var me = $( this );
		me.tooltipster( 'close' );
		me.addClass( 'editable-busy' );
		me.text( __( 'updating...', 'reg-man-rc' ) );
	});

	$( 'body' ).on( 'edit-submit-end', '.reg-man-rc-popup-editable', function( evt ) {
		var me = $( this );
		me.removeClass( 'editable-busy' );
	});

	$( 'body' ).on( 'edit-submit-response-returned', '.reg-man-rc-popup-editable', function( evt, response, jqXHR, textStatus ) {
		var me = $( this );
		// Wordpress (on the front end) will filter and replace html chars like '-' and replace with '&#8211;'
		// In order to change them back to regular characters I need to create a textarea
		//	insert the data into it then ask for the text back
		response = $('<textarea />').html( response ).text();
		if ( response.length === 0 ) response = '{}';
		var text = JSON.parse( response );
		me.text( text );
	});

	$( 'body' ).on( 'edit-submit-failed', '.reg-man-rc-popup-editable', function( evt, jqXHR, textStatus, error ) {
		var me = $( this );
		me.text( __( '[ error ]', 'reg-man-rc' ) );
	});

	/* Show any initially hidden elements */
	$('.initially-hidden').removeClass( 'initially-hidden' );

});