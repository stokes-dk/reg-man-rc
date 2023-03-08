<?php
namespace Reg_Man_RC\View\Object_View;

/**
 * An instance of this class acts as a template for rendering the content of our objects like Event, Venue and so on.
 *
 */
class Object_View_Template {

	private $classes = ''; // css classes to be assigned to the outermost container element
	private $object_view; // the view being rendered

	private function __construct() {
	} // funciton

	/**
	 * Create an instance of this class using the specified object view
	 * @param	Object_View		$object_view
	 * @return	Object_View_Template
	 */
	public static function create( $object_view ) {
		$result = new self();
		$result->object_view = $object_view;
		return $result;
	} // funciton

	/**
	 * Get the css classes for the outermost html element
	 * @return	string	The css classes for the html element containing this view
	 */
	private function get_classes() {
		return $this->classes;
	} // function

	/**
	 * Get the object view
	 * @return	Object_View	The object view being rendered by this template
	 */
	private function get_object_view() {
		return $this->object_view;
	} // function

	/**
	 * Set the css classes for the outermost html element
	 * @param	string	$classes	The css classes for the html element containing this view
	 */
	public function set_classes( $classes ) {
		$this->classes = $classes;
	} // function

	/**
	 * Render the title if one has been assigned
	 * @since	v0.1.0
	 */
	protected function render_title() {
		$title = $this->get_object_view()->get_object_view_title();
		if ( isset( $title ) ) {
			echo "<h2 class=\"reg-man-rc-object-view-title\">$title</h2>";
		} // endif
	} // function

	/**
	 * Render the contents that appear after the title.  Subclasses may override.
	 * @since	v0.1.0
	 */
	protected function render_after_title_section() {
		$after_title_section = $this->get_object_view()->get_object_view_after_title_section();
		if ( ! empty( $after_title_section ) ) {
			echo '<div class="reg-man-rc-object-view-after-title-section-container">';
				$after_title_section->render_section();
			echo '</div>';
		} // endif
	} // function

	/**
	 * Render the main content for this object
	 * @since	v0.1.0
	 */
	protected function render_main_content() {
		echo '<div class="reg-man-rc-object-view-main-content">';
			$sections = $this->get_object_view()->get_object_view_main_content_sections_array();
			if ( ! empty( $sections ) ) {
				foreach( $sections as $section ) {
					$section->render_section();
				} // endfor
			} // endif
		echo '</div>';
	} // function

	/**
	 * Get the content for this view
	 * @return string
	 * @since	v0.1.0
	 */
	public function get_content() {
		ob_start();

		// The following can be used to help debug by showing the current object's class name
//			$reflect = new \ReflectionClass( $this->get_object_view() );
//			echo $reflect->getShortName();

			$classes = $this->get_classes();
			echo "<div class=\"reg-man-rc-object-view-container $classes\">";

				// Title
				$this->render_title();

				// After title section
				$this->render_after_title_section();

				// Main content
				$this->render_main_content();

			echo '</div>';

		$result = ob_get_clean();
		return $result;
	} // function

	// FIXME - There should be a better place for these


} // class