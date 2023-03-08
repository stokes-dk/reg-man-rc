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
	
	
	// TOP
	
	$('.visitor-reg-list-table').on('init-datatable', function(evt) {
		var me = $(this);
		var container = me.closest('.visitor-reg-manager-container');
		var domSetting = me.data('dom-setting');
		var myDataTable = me.DataTable({
			'ajax' : {
				'url'  : me.data('ajax-url'),
				'data' : {
					'action'	: me.data( 'ajax-action' ),
					'event_key'	: me.data( 'event-key' )					
				},
			},
			orderFixed: [0, 'asc'],
			rowGroup: {
				dataSrc: 0
			},
			'autoWidth' : false,
			'dom': domSetting,
			'paging' : false, // hide pagination and just show everything
			'processing' : true,
			'columns': [
				{ className: 'visitor-short-name', 'visible' : false  }, // this is the row grouping column
				{ className: 'visitor-item-priority', 'visible' : false },
				{ className: 'visitor-item-desc', 'orderable' : false },
				{ className: 'visitor-item-status', 'orderable' : false },
				{ className: 'visitor-item-station', 'orderable' : false },
				{ className: 'visitor-item-id', 'visible' : false },
				{ className: 'visitor-is-surveyed', 'visible' : false },
				{ className: 'visitor-id', 'visible' : false }
			],
			'language' : {
				'emptyTable'		: __('No visitors registered', 'reg-man-rc'),
				// info is handled by a callback below
				// 'info'			: __('Total events: _TOTAL_', 'reg-man-rc'),
			    'thousands'			: _x(',', 'Thousands separator', 'reg-man-rc'),
				// translators: _MENU_ is a replacement variable replaced with a count of table entries
			    'lengthMenu'		: __('Show _MENU_ entries', 'reg-man-rc'),
				'loadingRecords'	: ' ',
				'processing'		: __('Loading. Please wait...', 'reg-man-rc'),
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
				$(row).addClass('visitor-reg-list-item'); // Allow me to launch dialog when row clicked
				// We need to be able to access data in hidden columns like the item id
				//  but datatables actually removes the column from the table when it's hidden
				// So to make it accessible we need to store the data in a data attribute
				$(row).attr('data-item-id', data[5]); // Store the item ids as a data attribute
				$(row).attr('data-is-surveyed', data[6]); // Store the surveyed item flag
				$(row).attr('data-visitor-id', data[7]); // Store the surveyed item flag
			},
			'infoCallback' : function( settings, start, end, max, total, pre ) {
				var result;
				if (max === 0) {
					result = '';
					container.find('input[type=search]').removeClass('filter-on');
				} else if (total !== max) { // the filter is being used so not all items are showing
					// translators: %1$s is replaced with a count of items showing, %2$s with the total count of registered items
					result = sprintf(_n('Showing %1$s item (filtered from a total of %2$s registered)',
										'Showing %1$s items (filtered from a total of %2$s registered)', total, 'reg-man-rc'),
										total, max);
					result = '<span class="visitor-reg-list-filter-info">' + result + '</span>';
					container.find('input[type=search]').addClass('filter-on'); // highlight the filter
				} else { // all items showing, give full details on the list
					container.find('input[type=search]').removeClass('filter-on');
					var itemCount = max; // the item count is the total number of rows
					var visitorArray = {}, visitorCount = 0, visitorId;
					var isSurveyed, itemSurveyCount = 0, responseRate;
					var rows = me.find('tbody tr'), currRow;
					rows.each(function(index, value) {
						currRow = $(value);
						isSurveyed = currRow.data('is-surveyed');
						if (isSurveyed) itemSurveyCount++;
						visitorId = currRow.data('visitor-id');
						if ( !(visitorId in visitorArray) ) {
							visitorArray[visitorId] = 1; // mark the visitor array so we can keep count
							visitorCount++; // this visitor is new to the list so increment counter
						} // endif
					});
					responseRate = Math.round(itemSurveyCount / itemCount * 100);
					var visitorsText = _n('visitor', 'visitors', visitorCount, 'reg-man-rc');
					var regItemsText = _n('registered item', 'registered items', itemCount, 'reg-man-rc');
					var surveyedItemsText = _n('item', 'items', itemSurveyCount, 'reg-man-rc');
					var percentSign = '%';
					/* translators: %1$s is replaced with the visitor count, %2$s with 'visitor' or 'visitors' depending on count
					  %3$s with the registered item count
					  %4$s with 'registered item' or 'registered items' depending on the registered item count
					  %5$s with the response rate of surved of items surveyed as a percentage, and %6$s with a percent sign, i.e. "%"
					*/
					var format = __('%1$s %2$s, %3$s %4$s, %5$s%6$s response rate', 'reg-man-rc');
					result = sprintf(format, visitorCount, visitorsText, itemCount, regItemsText, responseRate, percentSign);
				} // endif
				return result;
			},
			'initComplete': function(settings, json) { $(this).addClass('datatable-init-complete'); }
		});
		me.on('datatable-reload', function(evt) {
			myDataTable.search('');
			myDataTable.ajax.reload();
		});
		me.on('datatable-replace-data', function(evt, newData) {
			myDataTable.clear();
			myDataTable.rows.add(newData);
			myDataTable.draw();
		});
	});
	$('.visitor-reg-list-table').trigger('init-datatable');
	
	
	/// BOTTOM
		
		
	$('.visitor-reg-manager-container').on('initialize', function(evt) {
		var me = $(this);
		var myTable = $(this).find('.visitor-reg-manager-table');
		// Translators: %1$s is replaced button icon, e.g. a pencil, %2$s with button text, e.g. "Edit"
		var buttonLabelFormat = _x('%1$s%2$s', 'Creating a label for a button with icon and text', 'reg-man-rc');
		var refreshButtonIcon = '<span class="dashicons dashicons-update"></span>';
		var refreshButtonText = '<span class="button-text">' + __('Refresh List', 'reg-man-rc') + '</span>';
		var surveyButtonIcon = '<span class="dashicons dashicons-clipboard"></span>';
//		var surveyButtonText = '<span class="button-text">' + __('Visitor Feedback', 'reg-man-rc') + '</span>';
		var addButtonIcon = '<span class="dashicons dashicons-plus-alt"></span>';
		var addButtonText = '<span class="button-text">' + __('Add Visitor', 'reg-man-rc') + '</span>';
		$('#visitor-list-table-toolbar').html(
				'<button class="reg-man-rc-button reg-manager-button toolbar-button reg-manager-refresh">' +
					sprintf(buttonLabelFormat, refreshButtonIcon, refreshButtonText) +
				'</button>' +
//				'<button class="reg-man-rc-button reg-manager-button toolbar-button reg-manager-survey">' +
//				sprintf(buttonLabelFormat, surveyButtonIcon, surveyButtonText) +
				'</button>' +
				'<button class="reg-man-rc-button reg-manager-button toolbar-button reg-manager-add">' +
				sprintf(buttonLabelFormat, addButtonIcon, addButtonText) +
				'</button>'
		).addClass('datatable-toolbar');
		$('.reg-manager-refresh').click(function() {
			myTable.trigger('datatable-reload');
		});
		$('.reg-manager-survey').click(function() {
			var surveyDialog = $('.visitor-survey-dialog');
			surveyDialog.find('.visitor-survey-thank-you-message').hide();
			surveyDialog.find('.visitor-survey-form').trigger('form-reset'); // reset the form (remove errors etc.)
			surveyDialog.dialog('open');
		});
		$('.reg-manager-add').click(function() {
			var regDialog = $('.visitor-reg-dialog');
			var regForm = regDialog.find('.visitor-reg-form');
			regForm.trigger('form-reset'); // reset the form (remove errors etc.)
			regForm.find('.item-list-item.input-item.clone').remove(); // remove all the clones (additional items)
			var myAccordion = regDialog.find('.reg-man-rc-accordion-container');
			myAccordion.accordion('option', 'animate', false); // we don't want the animation in progress when we open the dialog
			myAccordion.accordion('option', 'active', 0);
			myAccordion.accordion('option', 'animate', true);
			// DKS - always enable rules ack, it's up to the user to scroll to the bottom
			// regForm.find('.house-rules').addClass('verify-scroll-bottom');
			// regForm.find('input[name="rules-ack"]').prop('disabled', true);
			regDialog.dialog('open');
			regDialog.find('input[name="item-desc[]"]').focus(); // Set focus on the first item input
		});
	});
	$('.visitor-reg-manager-container').trigger('initialize'); // initialize if there's data already on the page
	
	// Inline editors
	$('body').on('click', function(evt) {
		// Close any open editor on a click that does not come from inside my editable or editor
		var me = $(evt.target);
		var editable = me.closest('.reg-man-rc-inline-editable');
		var editor = me.closest('.reg-man-rc-inline-editor');
		if ( ( editable.length === 0 ) && ( editor.length === 0 ) ) {
			$('.reg-man-rc-inline-editor.editor-open').trigger('close-editor'); // close open editor
		} // endif
	});
	$( '.reg-man-rc-inline-editor' ).on( 'input', function( evt ) {
		var me = $( this );
		me.find( '.reg-man-rc-ajax-form' ).trigger( 'ajax-submit' ); // Save open editor
		me.trigger( 'set-editor-submitted' );
	});
	$('.reg-man-rc-inline-editor').on('set-editor-submitted', function(evt) {
		editor = $(this);
		var parent = editor.parent();
		editor.hide();
		var saving_message = __( 'Saving...', 'reg-man-rc' );
		parent.find('.reg-man-rc-inline-editable').text( saving_message ).show(); // show the contents
	});
	$('.reg-man-rc-inline-editor').on('close-editor', function(evt) {
		editor = $(this);
		var parent = editor.parent();
		editor.remove();
		parent.find('.reg-man-rc-inline-editable').show(); // show the contents
	});
	$('body').on('submit-end', '.reg-man-rc-inline-editor', function(evt) { // end ALWAYS triggers even on fail
		// attach to body so other elements can process it first
		$(this).trigger('close-editor');
	});
//	$('body').on('submit-fail', '.reg-man-rc-inline-editor', function(evt) {
//		$(this).trigger('close-editor');
//	});
	$('.reg-man-rc-inline-editor .cancel').on('click', function(evt) {
		$(this).trigger('close-editor');
	});
	$('.reg-man-rc-inline-editor .submit').on('click', function(evt) {
		$(this).closest('.reg-man-rc-inline-editor').find('.reg-man-rc-ajax-form').trigger('ajax-submit');
	});
	// The editables will be replaced on the client side using AJAX so we have to bind to body 
	$('body').on('click', '.reg-man-rc-inline-editable', function(evt) {
		var editable = $(this);
		$('.reg-man-rc-inline-editor.editor-open').trigger('close-editor'); // close any open editors on the page
		var parent = editable.parent();
		var templateSelector = editable.data('reg-man-rc-inline-editor-selector');
		var editorTemplate = $(templateSelector);
		var editor = editorTemplate.clone(true); // clone with all data and events handlers copied
		editor.removeClass('template').addClass('clone');
		editable.hide();
		parent.append(editor);
		editor.show();
		editor.addClass('editor-open');
		editor.trigger('reg-man-rc-inline-editor-init-inputs'); // Set up the inputs
	});
	$('.visitor-reg-item-status-inline-editor').on('reg-man-rc-inline-editor-init-inputs', function(evt) {
		var me = $(this);
		var parent = me.parent();
		var editable = parent.find('.reg-man-rc-inline-editable');
		var regListItem = me.closest('.visitor-reg-list-item');
		if ((editable.length !== 0) && (regListItem.length !== 0)) {
			var itemId = regListItem.data('item-id');
			var itemStatus = editable.data('item-status');
			me.find('input[name="item-id"]').val(itemId);
			var sel = me.find('select[name="item-status"]');
			sel.val(itemStatus);
		} // endif
	});
	$('.visitor-reg-fixer-station-inline-editor').on('reg-man-rc-inline-editor-init-inputs', function(evt) {
		var me = $(this);
		var parent = me.parent();
		var editable = parent.find('.reg-man-rc-inline-editable');
		var regListItem = me.closest('.visitor-reg-list-item');
		if ((editable.length !== 0) && (regListItem.length !== 0)) {
			var itemId = regListItem.data('item-id');
			var fixerStation = editable.data('item-fixer-station');
			me.find('input[name="item-id"]').val(itemId);
			var sel = me.find('select[name="fixer-station"]');
			sel.val(fixerStation);
		} // endif
	});
	
	$('.visitor-reg-manager-table').on('submit-end', '.reg-man-rc-inline-editor-form', function(evt) {
		evt.stopPropagation();
		$('.visitor-reg-manager-table').trigger('datatable-reload');
	});
	$('.visitor-reg-register-item-inline-editor').on('reg-man-rc-inline-editor-init-inputs', function(evt) {
		var me = $(this);
		var regListItem = me.closest('.visitor-reg-list-item');
		if (regListItem.length !== 0) {
			var itemId = regListItem.data('item-id');
			var checkbox = me.find('input[name="register-item"]');
			checkbox.val(itemId);
			checkbox.prop('checked', true);
		} // endif
	});
	// Note that submit-complete is no longer used, also we need to catch this on the parent above
//	$('.visitor-reg-register-item-inline-editor').on('submit-complete', function(evt) {
//		$('.visitor-reg-manager-table').trigger('datatable-reload');
//	});

	$('.visitor-reg-manager-container').on('click', '.visitor-reg-add-visitor-item-button', function(evt) {
		var target_row = $( evt.target ).closest( 'tr'); // Get the row that the click came from
		var dialog = $( '.add-item-to-visitor-reg-dialog' );
		var form = dialog.find( '.add-item-to-visitor-reg-form' );
		form.trigger( 'form-reset' ); // reset the form (remove errors etc.)
		form.find( '.input-item.clone' ).remove(); // remove any cloned items (previously added items)
		var visitor_name = target_row.find( '.visitor-reg-list-visitor-short-name' ).text();
		form.find( '.visitor-name' ).text( visitor_name ); // insert the visitor's name
		var first_item_row = target_row.next('.visitor-reg-list-item');
		var visitor_id = first_item_row.data( 'visitor-id' );
		form.find( 'input[name="visitor-id"]' ).val( visitor_id ); // insert the visitor's ID
		dialog.dialog('open');
	});


	// Initialize the returning visitor data
	var RC_REG_RETURNING_VISITOR_DATA;
	$('.visitor-reg-form.reg-man-rc-ajax-form').on('initialize-returning-visitor-data', function () {
		var me = $(this);
		var data = me.find('.visitor-reg-returning-visitor-data').text();
		// Wordpress (on the front end) will filter and replace html chars like '-' and replace with '&#8211;'
		// In order to change them back to regular characters I need to create a textarea
		//	insert the data into it then ask for the text back
		data = $('<textarea />').html(data).text();
		if (data.length === 0) data = '{}';
		RC_REG_RETURNING_VISITOR_DATA = JSON.parse(data);		
	});
	$('.visitor-reg-form.reg-man-rc-ajax-form').trigger('initialize-returning-visitor-data');
	
	$('.visitor-reg-form.reg-man-rc-ajax-form input[name="email"]').blur(function () {
		var me = $(this);
		if (me.val() !== '') { // if they're changing the email input and it's not blank then uncheck "I have no email"
			me.closest('.visitor-reg-form.reg-man-rc-ajax-form').find('input[name="no-email"]').prop('checked', false);
		} // endif
	});

	$('.visitor-reg-form.reg-man-rc-ajax-form input[name="no-email"]').change(function () {
		var me = $(this);
		if (me.is(':checked')) {
			me.closest('.visitor-reg-form.reg-man-rc-ajax-form').find('input[name="email"]').val('');
		} // endif
	});


	// Standalone Server
	$('.visitor-reg-manager-container').on('refresh-event-select', function(evt) {
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
	$('.standalone-download-events-dialog').on('set-button-enablement', function(evt) {
		var me = $(this);
		var eventInput = me.find('input[name="event[0][name]"]'); // do I have inputs to submit?
		var disabled = (eventInput.length === 0); // If there are none then disable the done button
		$('.standalone-download-events-dialog-done-button').prop('disabled', disabled);		
	});
	$('.standalone-download-events-dialog').on('submit-success', function(evt, response) {
		var htmlData = response.htmlData;
		if ((typeof(htmlData) == 'undefined') && (htmlData == null)) {
			$(this).dialog('close'); // close my dialog, provided this isn't the result of getting the form contents
			$('.visitor-reg-manager-container').trigger('refresh-event-select');
		} // endif
	});
	$('.standalone-download-events-dialog').on('submit-success-html-update', function(evt) {
		// This event occurrs after the html has been updated.  I need this so I can inspect the new html
		$(this).trigger('set-button-enablement');
	});
	$('.standalone-download-events-dialog').on('submit-success-complete', function(evt) {
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
	
	
	// Dialogs, Accordion, Autocomplete
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
	
	$('.visitor-reg-manager-event-select-form').submit(function(evt) {
		// add a busy indicator
		$( '.reg-man-rc-application-busy' ).addClass( 'is-active' ); // shrowd the entire UI while we're busy, no clicks anywhere
	});
	
	$('.reg-manager-message-dialog').dialog({
		autoOpen	: false,
		modal		: true,
		width		: 'auto',
		height		: 'auto',
		buttons : [
			{ 
				'text' : __('Close', 'reg-man-rc'),
				'click': function() { $( this ).dialog( 'close' ); }
			}
		]
	});
	$('.visitor-reg-dialog').dialog({
		autoOpen	: false,
		modal		: true,
		position	: { my: 'top', at: 'top+100' }, // Put it near the top to avoid going off bottom of screen
		width		: 'auto',
		height		: 'auto'
	});
	$('.visitor-reg-dialog').on('visitor-reg-ajax-form-submit', function() {
		$(this).find('.visitor-reg-form.reg-man-rc-ajax-form').trigger('ajax-submit'); // triggers my ajax form submission
	});

	$('.visitor-reg-input-accordion').accordion({
		'heightStyle' : 'content'
	});
	$('.accordion-buttons button[name="back"]').click(function() {
		var me = $(this);
		var myAccordion = me.closest('.reg-man-rc-accordion-container');
		var current = myAccordion.accordion('option', 'active');
		if (current > 0) {
			myAccordion.accordion('option', 'active', current - 1); // don't go past start
		} // endif
	});
	$('.accordion-buttons button[name="next"]').click(function() {
		var me = $(this);
		var myAccordion = me.closest('.reg-man-rc-accordion-container');
		var current = myAccordion.accordion('option', 'active');
		var paneCount = myAccordion.find('h3').length;
		if (current + 1 < paneCount) { // don't go past end
			myAccordion.accordion('option', 'active', current + 1);
		} // endif
	});
	$('.reg-man-rc-accordion-container').on('accordionbeforeactivate', function (evt, ui) {
		var me = $(this);
		var myAccordion = me.closest('.reg-man-rc-accordion-container');
		var myPanels = myAccordion.children('div');
		var oldPanel = ui.oldPanel;
		var newPanel = ui.newPanel;
		var oldPanelIndex = 0, newPanelIndex = 0;
		myPanels.each(function(index) {
			var currPanel = $(this);
			if (currPanel.is(oldPanel)) oldPanelIndex = index;
			if (currPanel.is(newPanel)) newPanelIndex = index;
		});
		if (oldPanelIndex < newPanelIndex) { // we're moving forward so validate intervening panels
			var validateIndex = newPanelIndex - 1; // check the panel right before the target (newPanel)
			var validatePanel = $(myPanels[validateIndex]);
			var validateSection = (validatePanel.hasClass('reg-man-rc-js-validation')) ? validatePanel : ui.validatePanel.find('.reg-man-rc-js-validation');
			var isValid = (validateSection.length !== 0) ? validateSection.triggerHandler('reg-man-rc-validate-form') : true;
			if (!isValid) { // only move to the next section if validation succeeds
				// If the panel before the target does not validate then target cancel this event and try targetting that panel instead
				evt.preventDefault();
				if (oldPanelIndex !== validateIndex) myAccordion.accordion('option', 'active', validateIndex);
			} // endif			
		} // endif
	});
	$('.reg-man-rc-accordion-container').on('accordionactivate', function (evt, ui) {
		// HOUSE RULES IS NO LONGER IN SCROLLABLE DIV	ui.newPanel.find('.house-rules').scrollTop(0);
		var firstInput = ui.newPanel.find('input').first();
		// I want to automaticaly set the focus to the first input
		// But if the first input is the house rules acknowledgement then I don't want to do this, I want the user to
		//  scroll through the rules and then click the checkbox.  So in that case we will not auto-focus
		if (firstInput.prop('name') !== 'rules-ack') {
			firstInput.focus();
		} // endif
	});
	$('.visitor-reg-done-button').click(function() {
		$(this).trigger('visitor-reg-ajax-form-submit');
	});
	
	$('.visitor-reg-form .visitor-reg-item-list + .visitor-item-add, ' +
			'.add-item-to-visitor-reg-form .add-item-to-visitor-reg-new-item-list + .visitor-item-add').click(function() {
		var list = $(this).prev();
		var cln = list.find('li:first').clone(); // copy the first element
		cln.prepend('<div class="reg-item-remove-button reg-man-button"><span class="dashicons dashicons-dismiss"></span></div>');
		cln.find('input').val(''); // unset the input data
		cln.removeClass('error').find('.error-container').html(''); // unset any error message
		cln.find('select').removeAttr('selected'); // remove selected options
		cln.addClass('uninitialized clone'); // mark it as uninitialized and a clone
		cln.appendTo(list); // append the new item to the list
		cln.trigger('initialize-item-inputs'); // initialize it
		cln.find('input').first().focus();
	});
	$( '.visitor-reg-item-list' ).on('click', '.reg-item-remove-button', function(evt) {
		var targetItem = $(evt.target).closest('.item-list-item'); // Get the item that the click came from
		targetItem.remove();
	});
	$('.visitor-reg-dialog').on('submit-success', function(event, response) {
		var me = $(this);
		me.dialog('close');
		var thankYouDialog = $('.reg-man-rc-visitor-registration-thank-you-dialog');
		thankYouDialog.dialog('open');		
		$('.visitor-reg-manager-table').trigger('datatable-reload');
	});
	$('.visitor-reg-dialog').on('submit-error', function(event, response) {
		// we had errors so find the input(s) with the error and make sure it's visible
		var me = $(this);
		var myAccordion = me.find('.reg-man-rc-accordion-container');
		var myPanels = myAccordion.children('div');
		myPanels.each(function(index) {
			// if this panel has an error on it then make it the active and break the loop
			var currPanel = $(this);
			if (currPanel.find('.input-item.error').length !== 0) {
				myAccordion.accordion('option', 'active', index); // if there's an error, go to that panel
				return false;
			} // endif
		});
		// If the user was adding multiple items there may have been some successes and some failures
		// Refresh the table to show any updates
		$('.visitor-reg-manager-table').trigger('datatable-reload');
	});

	$('.add-item-to-visitor-reg-dialog').dialog({
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
				'click' : function() { $(this).trigger('add-item-to-visitor-reg-form-submit') },
				'class' : 'add-item-to-visitor-reg-dialog-button'
			}
		]
	});
	$('.add-item-to-visitor-reg-dialog').on('add-item-to-visitor-reg-form-submit', function() {
		var me = $( this );
		var my_form = me.find('.add-item-to-visitor-reg-form.reg-man-rc-ajax-form');
		my_form.trigger('ajax-submit'); // triggers my ajax form submission
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
	$('.visitor-survey-dialog').on('visitor-survey-form-submit', function() {
		$(this).find('.visitor-survey-form.reg-man-rc-ajax-form').trigger('ajax-submit'); // triggers my ajax form submission
	});
	$('.visitor-survey-dialog').on('submit-success', function(event, response) {
		var me = $(this);
		me.dialog('close');
		var thankYouDialog = $('.reg-man-rc-visitor-survey-thank-you-dialog');
		thankYouDialog.dialog('open');
	});
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
		var input_containers = me.find('.item-list-item.uninitialized');
//		var autocomplete_data = me.find('.visitor-reg-item-autocomplete-data').text();
		var autocomplete_data = $( 'body' ).find( '.visitor-reg-item-autocomplete-data' ).text();
		// Wordpress (on the front end) will filter and replace html chars like '-' and replace with '&#8211;'
		// In order to change them back to regular characters I need to create a textarea
		//	insert the data into it then ask for the text back
		autocomplete_data = $('<textarea />').html(autocomplete_data).text();
		if (autocomplete_data.length === 0) autocomplete_data = '[]';
		var autocomplete_data_array = JSON.parse(autocomplete_data);

		// translators: %1$s is an item description, e.g. "Lamp", %2$s is a list of alternate descriptions, e.g. "Light, Desklight"
		var with_alt_format = __( '%1$s (%2$s)', 'reg-man-rc' ); // used to construct autocomplete suggestion labels

		input_containers.each( function() {
			var container = $( this );
			container.on( 'item-desc-autocomplete-select', function( evt, desc, type, station ) {
				var me = $( this );
				me.find('input[name="item-desc[]"]').val(desc);
				me.find('select[name="item-type[]"]').val(type);
				me.find('select[name="fixer-station[]"]').val(station);
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
			// translators: %1$s is replaced with a full name, %2$s with a partially obscured email
			var name = sprintf( __( '%1$s (%2$s)', 'reg-man-rc' ), item.full_name, item.obs_email );
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
//		var public_name_input	= me.find( 'input[name="public-name"]' );
		var email_input			= me.find( 'input[name="email"]' );
		var no_email_input		= me.find( 'input[name="no-email"]' );
		var reset_button		= me.find( '.visitor-name-reset' );

		id_input.val( '' );
		full_name_input.prop( 'disabled', false ); // Note that we don't always want to remove the value
//		public_name_input.val( '' ).prop( 'disabled', false );
		email_input.val( '' ).prop( 'disabled', false );
		no_email_input.prop( 'checked', false ).prop( 'disabled', false );
		reset_button.hide();
		
		if ( is_name_disabled ) {
			container.trigger( 'initialize-visitor-name-inputs' ); // The autocomplete needs to be re-initialized
		} // endif

	});
	
	$( '.visitor-reg-form' ).on( 'reset', function( evt ) {
		var me = $( this );
		var container = me.closest( '.autocomplete-visitor-name-container' );
		container.trigger( 'reset-name-fields' );
	});

	
	
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

	
});