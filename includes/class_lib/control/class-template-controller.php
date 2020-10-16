<?php
namespace Reg_Man_RC\Control;

/**
 * Controller for page templates
 *
 * The plugin uses a page tempate that provides a very minimal presentation stripped of all the things Wordpress and the theme would normally include.
 * This is needed so that our visitor and volunteer-facing interfaces like the Visitor Registration Manager
 * can be shown on a page that does not allow the user to do anything other than interact with that interface.
 * The page may be used in kiosk mode on a tablet for example so that visitors can register but they can't do anything else.
 *
 * @since v0.1.0
 *
 */
class Template_Controller {
	/**
	 * The slug used to identify the Minimal page template.
	 */
	const MINIMAL_TEMPLATE_SLUG = 'reg_man_rc_minimal_template_slug';

	/**
	 * Set up the action and filter hooks for our templates
	 *
	 * This method is called by the plugin controller
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function initialize() {
		add_filter( 'page_template', array(	__CLASS__, 'get_page_template_filename' ) );
		add_filter( 'theme_page_templates', array( __CLASS__, 'add_template_to_page_attrs_section' ), 10, 3 );
	} // function

	/**
	 * Filter hook to get the filename for the page template
	 *
	 * If the slug for the current page template is our template slug then we will return the appropriate filename for our template.
	 * Otherwise we will leave the template filename as it was passed in.
	 *
	 * @return	string	filename for the page template to be used
	 * @param	string	$page_template	The filename for the template currently assigned to the page
	 * @since	v0.1.0
	 */
	public static function get_page_template_filename( $page_template ) {
		if ( get_page_template_slug() == self::MINIMAL_TEMPLATE_SLUG) {
			$result = dirname( dirname ( dirname ( __FILE__) ) ). DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'minimal-template.php';
		} else {
			$result = $page_template; // The original template filename passed in
		} // endif
		return $result;
	} // function

	/**
	 * Filter hook to add our page template name to the page attributes section of the editor
	 *
	 * If the slug for the current page template is our template slug then we will return the appropriate filename for our template.
	 * Otherwise we will leave the template filename as it was passed in.
	 *
	 * @return	array[string]string	An associative array of representing the page template slugs and names to be shown in the editor
	 * @param	array[string]string	$post_templates	The current associative array of slugs and names for page templates (we will add to this)
	 * @param	WP_Theme			$wp_theme	The theme object
	 * @param	WP_Post				$post		The post being edited, provided for context, or null.
	 * @since	v0.1.0
	 */
	public static function add_template_to_page_attrs_section( $post_templates, $wp_theme, $post ) {
		$post_templates[ self::MINIMAL_TEMPLATE_SLUG ] = __('Registration Manager Minimal Template');
		return $post_templates;
	} // function

	/**
	 * Remove (dequeue) styles and scripts associated with the theme
	 *
	 * For the minimal template used to show our public interfaces we need to remove the styles and scripts enqueued by the theme.
	 * Those styles, for example, may assign a background colour, change the font size, change the way forms are displayed and so on.
	 * To make the page as simple and minimal as possible we will simply remove (dequeue) all those styles and scripts.
	 * This method should be attached to the wp_enqueue_scripts action by the template
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function dequeue_theme_styles_and_scripts() {
		global $wp_scripts, $wp_styles;

		foreach ($wp_styles->queue as $handle) {
			// If the src includes wp-content/themes them dequeue it
			$src = isset($wp_styles->registered[$handle]) ? $wp_styles->registered[$handle]->src : '';
			if (strpos($src, 'wp-content/themes') !== FALSE) {
				wp_dequeue_style($handle);
			} // endif
		} // endfor

		foreach ($wp_scripts->queue as $handle) {
			// If the src includes wp-content/themes them dequeue it
			$src = isset($wp_scripts->registered[$handle]) ? $wp_scripts->registered[$handle]->src : '';
			if (strpos($src, 'wp-content/themes') !== FALSE) {
				wp_dequeue_script($handle);
			} // endif
		} // endfor
	} // function

	/**
	 * Remove "Protected" or "Private" from the page title (when its visibility is password protected or private)
	 *
	 * For the minimal template used to show our public interfaces we want to remove the words "Protected" or "Private"
	 * from the page title for any page whose visibility is set to password protected or private.
	 * Words like those in the page title may confuse people using the interface.
	 * This method should be attached to the protected_title_format and private_title_format filters in the template.
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	//
	public static function remove_private_protected_from_title( $format ) {
		return '%s';
	} // function

} // class