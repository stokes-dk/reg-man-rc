/**
 * This javascript file contains the shared function to support AJAX for the plugin
 */
jQuery(document).ready(function($) {

	$( '.reg-man-rc-ajax-form-initializer' ).on( 'initialize', function( evt ) {
		// This is a div that contains the form inputs
		// We need to create the form element
		var form_initializer = $(this);
		var form_id = form_initializer.data( 'form-id' );
		var form_action = form_initializer.data( 'form-action' );
		var form_method = form_initializer.data( 'form-method' );
		var form_enc_type = form_initializer.data( 'form-enc-type' );
		var is_footer = ( form_initializer.data( 'is-render-in-footer' ) == 'TRUE' );

		var form = $('<form></form>');
		form.attr( 'id', form_id );
		form.attr( 'action', form_action );
		form.attr( 'method', form_method );
		if ( form_enc_type !== '' ) {
			form.attr( 'encType', form_enc_type );
		} // endif
		
		// Get all attributes of the initializer and apply them to the form
		var form_attributes = form_initializer.prop( 'attributes' );
		$.each( form_attributes, function() {
			form.attr( this.name, this.value );
		});

		form.removeClass( 'reg-man-rc-ajax-form-initializer' ); // This is no longer an initializer
		form.addClass( 'reg-man-rc-ajax-form' ); // It's an Ajax form!

		if ( is_footer ) {
			// Because the form's events no longer bubble up to its context, I will look for a listener and attach it to the form
			form.addClass( 'reg-man-rc-ajax-footer-form' );
			var listener = form_initializer.closest( '.reg-man-rc-ajax-form-listener' );
			form.data( 'ajax-form-listener', listener );
			form.appendTo( 'body' ); // Append the form to the body
		} else {
			form_initializer.after( form ); // Insert the form after the initializer
		} // endif
		
		form.append( form_initializer.contents() ); // Move the form contents to the form
		
		form_initializer.remove(); // We no longer need this
	});

	$( '.reg-man-rc-ajax-form-initializer' ).trigger( 'initialize' );
	
	$( '.reg-man-rc-ajax-footer-form' ).on( 'ajax-submit submit-start submit-response-returned submit-fail submit-end', function( evt ) {
		var me = $( this );
		var listener = me.data( 'ajax-form-listener' );
		listener.trigger( evt );
	});
	$( '.reg-man-rc-ajax-footer-form' ).on( 'submit-success submit-error', function( evt, response ) {
		var me = $( this );
		var listener = me.data( 'ajax-form-listener' );
		listener.trigger( evt, [ response ] );
	});
	
	$( '.reg-man-rc-ajax-form' ).on( 'submit', function( evt ) {
		evt.preventDefault(); // we will do the submit via ajax
		$(this).trigger( 'ajax-submit' );
	});

	$( '.reg-man-rc-ajax-form' ).on( 'ajax-submit', function( evt ) {
		var form = $(this);
		evt.preventDefault(); // we will do the submit via ajax
		form.find('.ajax-form-message-container').remove(); // remove any error messages before we attempt the submit
		form.find('.input-item.error').trigger('remove-error');
		var result = form.triggerHandler('reg-man-rc-validate-form');
		if (result === false) {
			// If there were form validation errors then don't do the ajax sumbit
		} else {
//			if (typeof performance !== 'undefined' && typeof performance.now !== 'undefined') {
//				var start_time = performance.now();
//			} // endif
			form.trigger('submit-start');
			var method = form.attr( 'method' );
			var settings = {
					url: form.attr('action'),
					type: method,
					dataType: 'json'
			};
			
			// FIXME - TESTING PROGRESS!!!
/*
			settings.xhr = function() {
				var xhr = new XMLHttpRequest();
				xhr.upload.addEventListener( 'progress', function( evt ) {
					if ( evt.lengthComputable ) {
						var uploadpercent = evt.loaded / evt.total; 
						uploadpercent = (uploadpercent * 100); //optional Math.round(uploadpercent * 100)
						console.log( 'Percent complete: ' + uploadpercent );
//						$('.progress-bar').text(uploadpercent + '%');
//						$('.progress-bar').width(uploadpercent + '%');
//						if (uploadpercent == 100) {
//							$('.progress-bar').text('Completed');
//						} // endif
					} // endif
				}, false );
				return xhr;
			} // function
*/
	
			if ( form.attr('encType') == 'multipart/form-data' ) {
				settings.cache = false;
				settings.contentType = false;
				settings.processData = false;
				var form_data = new FormData( form[0] );
				form_data.append( 'action', form.data( 'ajax-action' ) );
				settings.data = form_data;
			} else {
				settings.data = { action : form.data( 'ajax-action' ) };
				settings.data.formData = form.serialize();
			} // endif
	
			$.ajax(
				settings
				).done( function( response, textStatus, jqXHR ) {
//					if (typeof performance !== 'undefined' && typeof performance.now !== 'undefined') {
//						var end_time = performance.now();
//						console.log('AJAX Submit time: ' + (end_time - start_time));
//					} // endif
					form.trigger( 'submit-response-returned', [ response, jqXHR, textStatus ] );
				}).fail( function( jqXHR, textStatus, error ) {
					console.log( 'Ajax submit form failed, text status: ' + textStatus + ', error: ' + error );
					form.trigger( 'submit-fail', [ jqXHR, textStatus, error ] );
				}).always( function( ) {
					form.trigger( 'submit-end' );
			});
		} // endif - form validation succeeded
	});
	$('.reg-man-rc-ajax-form').on('form-reset', function(event) {
		var me = $(this);
		me.find('.ajax-form-message-container').remove(); // Get rid of old top of form message
//		me.find('.message-container').hide(); // hide old messages
		me.find('.error-container').html(''); // remove old input item error messages
		if (me.is('form')) me.get(0).reset(); // reset the form, it could be more or...
		else me.find('form').get(0).reset(); // it could be descendant
	});
	$('.reg-man-rc-ajax-form.reset-form-on-form-shown').on('form-shown', function(event) {
		$(this).trigger('form-reset');
	});
	var reg_man_rc_ajax_form_timeout; // show reg_man_rc_ajax_form_timeout indicator if the dynamic form takes time to submit
	$('.reg-man-rc-ajax-form').on('submit-start', function(event) {
		var me = $(this);
		if ( ! me.hasClass( 'no-busy' ) ) { // sometimes I don't want to do this, those classes are marked
			if ( reg_man_rc_ajax_form_timeout ) clearTimeout( reg_man_rc_ajax_form_timeout );
			reg_man_rc_ajax_form_timeout = setTimeout( function() {
				$( '.reg-man-rc-application-busy' ).addClass( 'is-active' ); // shrowd the entire UI while we're busy, no clicks anywhere
			}, 250); // but wait a tick so that we only show it on longer requests
		} // endif
		me.find( '.error' ).removeClass( 'error' ); // hide old errors before submit
		me.find( '.input-item .error-container' ).text( '' ); // remove old error text before submit
	});
	$('.reg-man-rc-ajax-form').on('submit-fail', function(event, xmlHttpRequest, textStatus, error) {
		if (reg_man_rc_ajax_form_timeout) clearTimeout(reg_man_rc_ajax_form_timeout);
		$('.reg-man-rc-application-busy').removeClass( 'is-active' );
		if ((typeof(xmlHttpRequest) !== 'undefined') && (xmlHttpRequest !== null)) {
				console.log(__('An ajax error occurred, the following details were in the ajax response', 'reg-man-rc'));
				if (typeof(error) !== 'undefined') console.log('ERROR: ' + error);
				if (typeof(xmlHttpRequest.responseType) !== 'undefined') console.log('RESPONSE TYPE: ' + xmlHttpRequest.responseType);
				if (typeof(xmlHttpRequest.response) !== 'undefined') console.log('RESPONSE: ' + xmlHttpRequest.responseText);
				if (typeof(xmlHttpRequest.responseText) !== 'undefined') console.log('RESPONSE TEXT: ' + xmlHttpRequest.responseText);
				if (typeof(xmlHttpRequest.responseXML) !== 'undefined') console.log('RESPONSE XML: ' + xmlHttpRequest.responseXML);
			} // endif
			msgBegin = __('Sorry, the form could not be submitted.', 'reg-man-rc');
			var msg = msgBegin + '\n\n';
			switch (error) {
				// Note that the errors like 'Unauthorized' and 'No Such Page' are not created by this
				//  plugin but by the underlying JQuery Ajax functions.  So they are not translated!
				case 'Unauthorized':
					msg += __('You are not authorized to perform this action.', 'reg-man-rc');
					msg += __('\n\nPlease ensure that you are logged in with appropriate authority and that your login has not expired.', 'reg-man-rc');
					break;
				case 'No Such Page':
				case 'Not Found':
					msg += __('The target for the form submission was not found.', 'reg-man-rc');
					msg += __('\n\nThis is an internal error on the web site.', 'reg-man-rc');
					msg += __('\n\nPlease contact your system administrator.', 'reg-man-rc');
					break;
				case 'Bad Request':
					msg += __('The request could not be understood by the server.', 'reg-man-rc');
					msg += __('\n\nThis is an internal error on the web site.', 'reg-man-rc');
					msg += __('\n\nPlease contact your system administrator.', 'reg-man-rc');
					break;
				case 'Wordpress Error': // added by me in submit-response-returned when Wordpress returns error code
					msg += __('The request could not be understood by Wordpress.', 'reg-man-rc');
					msg += __('\n\nThis is an internal error on the web site.', 'reg-man-rc');
					msg += __('\n\nPlease contact your system administrator.', 'reg-man-rc');
					break;
				default:
					msg += __('Please ensure that your internet connection is active then try again.', 'reg-man-rc');
					msg += __('\n\nIf you continue to have this error, please contact your system administrator.', 'reg-man-rc');
					break;
			} // endswitch
			alert(msg);
	});
	$('.reg-man-rc-ajax-form').on('submit-response-returned', function(event, response, xmlHttpRequest, textStatus) {
		// Response may be 0 or -1 from Wordpress or a json encoded object
		var me = $(this);
		if (typeof(response) === 'number') { // if it's a number we can assume it's a Wordpress failure
			me.trigger('submit-fail', [xmlHttpRequest, textStatus, 'Wordpress Error'])
		} else if ((typeof(response) === 'object') && (response !== null)) {
			var errors = response.error_array;
			if ( typeof(errors) == 'undefined' ) errors = [];
			if ( errors.length == 0 ) { // no errors
				me.trigger('submit-success', [response]); // signal actual success on the form to any listeners
				var redirect = response.redirect_url;
				var html_data = response.html_data;
				if ((typeof(redirect) != 'undefined') && (redirect != null)) {
					window.location.replace(redirect);
				} else if ((typeof(html_data) != 'undefined') && (html_data != null)) {
					var auto_replace = me.data( 'auto-replace-html' );
					if ( auto_replace == 'TRUE' ) {
						me.html(html_data); // Replace my html with the new form supplied
					} // endif
					$('.reg-man-rc-application-busy').removeClass( 'is-active' );
					me.trigger('submit-success-html-update'); // signal that the form has been updated
				} else {
					if (reg_man_rc_ajax_form_timeout) clearTimeout(reg_man_rc_ajax_form_timeout);
					$('.reg-man-rc-application-busy').removeClass( 'is-active' );
					me.trigger('submit-success-complete'); // signal submit success and all processing complete
				} // endif
			} else { // errors in the response object
				me.find('.message-container').show();
				me.trigger( 'msg-arrival', [errors, true] );
				// If there are errors then don't redirect
				if (reg_man_rc_ajax_form_timeout) clearTimeout(reg_man_rc_ajax_form_timeout);
				$('.reg-man-rc-application-busy').removeClass( 'is-active' );
				me.trigger('submit-error', [response]);
			} // endif
		} else {
			// The response was either null or a non object so nothing more we can do
			if (reg_man_rc_ajax_form_timeout) clearTimeout(reg_man_rc_ajax_form_timeout);
			$('.reg-man-rc-application-busy').removeClass( 'is-active' );
			me.trigger('submit-error', [response]);
		} // endif
	});
	$( '.reg-man-rc-ajax-form' ).on( 'msg-arrival', function( event, msg_array, is_error ) {
		var me = $(this);
		var curr_msg;
		for ( var field_name in msg_array ) {
			curr_msg = msg_array[ field_name ];
			if ( is_error ) {
				// Find the input with the specified name and add the message to its error container
				// If there is no input with the specified name or no error container then add the message to a general message div
				var curr_input = me.find( '[name="' + field_name + '"]' );
				var error_container = null;
				var target_input = null;
				if ( curr_input.length !== 0 ) {
					// There may be multiple inputs with this name, e.g. item-desc[]
					// We'll only mark the first one (not all).  To do this we need to find the first
					//	parent with class 'input-item' that has a child with class 'error-container'
					// It would be nice if .closest('.input-item > .error-container') worked but it doesn't
					target_input = curr_input.first();
					var input_items = target_input.parents( '.input-item' );
					$.each( input_items, function( index, parent ) {
						if ( $(parent).is( ':visible' ) ) {
							error_container = $(parent).find( '> .error-container' );
							if (error_container.length !== 0) {
								$(this).addClass( 'error' );
								return false; // Break the each loop, just add msg to first error container we find
							} // endif
						} // endif
					});
				} // endif
				if ( ( error_container !== null ) && ( error_container.length !== 0 ) ) {
					error_container.html( curr_msg.error_msg );
					if ( ( target_input !== null ) && ( target_input.length !== 0 ) ) {
						target_input.on( 'change', { err_cntr : error_container }, function(evt) {
							evt.data.err_cntr.html(''); // Remove the error message when the user changes the input
							$(this).off(evt); // Passing the event ensures only this change listener is removed
						});						
					} // endif
				} else {
					// There is no input field with the specified message name or the input has no error container
					// so put the message in a general message div
					var message_container = me.find('.ajax-form-message-container');
					if ( message_container.length === 0 ) { // there is no message container so create it
						message_container = $(
							'<div class="ajax-form-message-container">' + 
								'<div class="message-header">' + 
									'<span class="message-title">' + __('Error', 'reg-man-rc') + '</span>' + 
									'<i class="dashicons dashicons-no message-close"></i>' + 
								'</div>' +
								'<ul></ul>' +
							'</div>'
						);
						message_container.prependTo( me );
					} // endif
					var message_list = message_container.find('ul');
					if ( message_list.length === 0 ) { // there is no message list so create it
						message_list = $('<ul></ul>').appendTo( message_container );
					} // endif
					message_list.append( '<li>' + curr_msg.error_msg + '</li>' );
				} // endif
			} else {
				// it's not an error, it's an info message so trigger its arrival
				// TODO: We currently don't support info messages so this can be removed
				me.trigger( 'user-info-msg-arrival', curr_msg.error_msg );
			} // endif
		} //endfor
	});
	$('.reg-man-rc-ajax-form').on('click', '.ajax-form-message-container .message-close', function(evt) {
		var targetMsg = $(evt.target).closest('.ajax-form-message-container'); // Get the message container where the click occurred
		targetMsg.remove();
	});
	
	
	
	// Validation
	$('.form-input-list .input-item').on('remove-error', function() {
		$(this).removeClass('error').find('.error-container').html('');						
	});
	
	$('.reg-man-rc-js-validation').submit(function(evt) {
		var result = $(this).triggerHandler('reg-man-rc-validate-form');
		if (result === false) evt.preventDefault(); // don't submit the form when there are errors
	});
	$('.reg-man-rc-js-validation').on('reg-man-rc-validate-form', function() {
		// This handler returns a boolean value!  true if the form validates without error, false on errors
		// NOTE that it is meant to be called using triggerHandler() as above to get the function result
		// Note also that when you use triggerHandler the event does not bubble up the DOM so you can check just one part
		//  of a larger form, as is the case with the visitor registration form split into accordion sections
		var me = $(this);
		me.find('.input-item.error').trigger('remove-error'); // clear old errors if any
		me.triggerHandler('js-additional-verification'); // visitor survey listens for this, it will add any errors it finds
		var requiredItems = me.find('.input-item.required');
		var errMsg;
		requiredItems.each(function(index, item) {
			errMsg = '';
			var reqItem = $(item);
			if (reqItem.hasClass('radio-group')) {
				var checked = reqItem.find('input:checked');
				if (checked.length == 0) {
					errMsg = __('Please select an option', 'reg-man-rc');
					reqItem.find('input:radio').on('change', function() {
						reqItem.trigger('remove-error');
					});
				} else if (checked.hasClass('custom')) {
					var customInput = reqItem.find('.radio-custom-input');
					if ($.trim(customInput.val()) == '') {
						errMsg = __('This field is required', 'reg-man-rc');
						reqItem.find('input:radio').on('change', function() {
							reqItem.trigger('remove-error');
						});
						customInput.on('input', function() {
							reqItem.trigger('remove-error');
						});
					} // endif
				} // endif
			} else if (reqItem.hasClass('check-list')) {
				var checked = reqItem.find('input:checked');
				if (checked.length == 0) {
					errMsg = __('This field is required', 'reg-man-rc');
					reqItem.find('input:checkbox').on('change', function() {
						reqItem.trigger('remove-error');
					});
				} else if (checked.hasClass('custom')) {
					var customInput = reqItem.find('.checkbox-custom-input');
					if ($.trim(customInput.val()) == '') {
						errMsg = __('This field is required', 'reg-man-rc');
						reqItem.find('input:checkbox').on('change', function() {
							reqItem.trigger('remove-error');
						});
						customInput.on('input', function() {
							reqItem.trigger('remove-error');
						});
					} // endif
				} // endif
			} else {
				if (reqItem.hasClass('textarea')) {
					var input = reqItem.find('textarea');
				} else {
					var input = reqItem.find('input');
					if (input.length == 0) input = reqItem.find('select'); // no input look for select 
				} // endif
				if ($.trim(input.val()) == '') {
					errMsg = __('This field is required', 'reg-man-rc');
					input.on('input', function() {
						reqItem.trigger('remove-error');
					});
				} // endif
			} // endif
			if (errMsg != '') {
				reqItem.addClass('error');
				reqItem.find('> .error-container').html(errMsg);
			} // endif
		}); // end each required item
		var errorInputs = me.find('.input-item.error');
		var result;
		if (errorInputs.length != 0) {
			errorInputs.first().find('input').focus();
			if (me.hasClass('auto-scroll-to-error-input')) {
				// Scroll the view so that we are showing the first error
				var offset = errorInputs.first().offset(); // Contains .top and .left
				$('html, body').animate({
						scrollTop: (offset.top - 20),
						scrollLeft: (offset.left - 20) // we shouldn't actually have any horizontal scroll
				});
			} // endif
			// me.trigger('.reg-man-rc-js-validation-failure'); // DO I need event to indicate this has failed?
			result = false;
		} else {
			result = true;
		} // endif
		return result;
	});
});
