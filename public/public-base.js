/**
 * This is the base javascript for the public website
 */
jQuery(document).ready(function($) {
	$( '.reg-man-rc-show-hide-button-container .show-button' ).on( 'click', function( evt ) {
		var me = $( this );
		me.closest( '.reg-man-rc-show-hide-container' ).addClass( 'show' );
	});
	$( '.reg-man-rc-show-hide-button-container .hide-button' ).on( 'click', function( evt ) {
		var me = $( this );
		var container = me.closest( '.reg-man-rc-show-hide-container' );
		container.removeClass( 'show' );
		var show_button = container.find( '.show-button' );
		var element = show_button[ 0 ];
		element.scrollIntoView(); // scroll back up so that this button is showing
	});
});