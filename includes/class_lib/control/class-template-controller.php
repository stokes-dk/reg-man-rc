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
	const MINIMAL_TEMPLATE_SLUG = 'reg-man-rc-minimal-template-slug.php';

	/**
	 * Register the action and filter hooks for our templates
	 *
	 * This method is called by the plugin controller
	 *
	 * @return	void
	 * @since	v0.1.0
	 */
	public static function register() {

		// Filter all template includess so we can replace the ones used for our pages
		// We allow the user to select the template for our pages
		// And we use the standard post template for our events
//		add_filter( 'template_include', array( __CLASS__, 'filter_template_include' ) );

		// Filter the filename for the page template returning our minimal template file name when appropriate
		add_filter( 'page_template', array( __CLASS__, 'filter_page_template' ), 10, 4 );

		// Filter the list of templates shown for a page
		add_filter( 'theme_page_templates', array( __CLASS__, 'filter_theme_page_templates' ), 10, 2 );

	} // function

	/**
	 * Filter the actual template file name based on a template's slug
	 * @param	string		$template
	 * @param	string		$type
	 * @param	string[]	$template_array
	 * @return	string	The filtered page template
	 */
	public static function filter_page_template( $template, $type, $template_array ) {
//		Error_Log::var_dump( $template, $type, $template_array );
		if ( get_page_template_slug() == self::MINIMAL_TEMPLATE_SLUG ) {
			$result = self::get_minimal_template_file_name();
		} else {
			$result = $template;
		} // endif
		return $result;
	} // function

	/**
	 * Filter the list of templates for a page
	 * @param string[][]	$page_templates
	 * @param \WP_Theme		$theme_object
	 */
	public static function filter_theme_page_templates( $page_templates, $theme_object ) {
		$label = __( 'Minimal Template (Registration Manager for Repair Café)', 'reg-man-rc' );
		$page_templates[ self::MINIMAL_TEMPLATE_SLUG ] = $label;
//		Error_Log::var_dump( $page_templates );
		return $page_templates;
	} // function

	/**
	 * Get the file name for our internal "minimal" template
	 * @return string
	 */
	public static function get_minimal_template_file_name() {
		$plugin_dir = dirname( \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		$templates_dir = $plugin_dir . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'templates';
		$result = $templates_dir . DIRECTORY_SEPARATOR . 'minimal-template.php';
		return $result;
	} // function

	/**
	 * Get the file name for our internal comments template
	 * @return string
	 */
	public static function get_comments_template_file_name() {
		$plugin_dir = dirname( \Reg_Man_RC\PLUGIN_BOOTSTRAP_FILENAME );
		$templates_dir = $plugin_dir . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'templates';
		$result = $templates_dir . DIRECTORY_SEPARATOR . 'comment-template.php';
		return $result;
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

		foreach ( $wp_styles->queue as $handle ) {
			// If the src includes wp-content/themes them dequeue it
			$src = isset( $wp_styles->registered[ $handle ] ) ? $wp_styles->registered[ $handle ]->src : '';
			if ( strpos( $src, 'wp-content/themes' ) !== FALSE) {
				wp_dequeue_style( $handle );
			} // endif
		} // endfor

		foreach ($wp_scripts->queue as $handle) {
			// If the src includes wp-content/themes them dequeue it
			$src = isset( $wp_scripts->registered[ $handle ] ) ? $wp_scripts->registered[ $handle ]->src : '';
			if ( strpos( $src, 'wp-content/themes' ) !== FALSE) {
				wp_dequeue_script( $handle );
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