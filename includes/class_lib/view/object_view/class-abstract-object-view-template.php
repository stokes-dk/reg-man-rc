<?php
namespace Reg_Man_RC\View\Object_View;

/**
 * Provides a foundation for implementing the Object_View_Template interface
 * An instance of this class acts as a template for rendering the content of our objects like Event, Venue and so on.
 *
 */
abstract class Abstract_Object_View_Template implements Object_View_Template {

	protected $classes = ''; // css classes to be assigned to the outermost container element
	protected $object_view; // the view being rendered

	/**
	 * Get the css classes for the outermost html element
	 * @return	string	The css classes for the html element containing this view
	 */
	protected function get_classes() {
		return $this->classes;
	} // function

	/**
	 * Get the object view
	 * @return	Object_View	The object view being rendered by this template
	 */
	protected function get_object_view() {
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
	 * Set the object view to be rendered by this template
	 * @param	Object_View	$object_view	The object view to be rendered
	 */
	public function set_object_view( $object_view ) {
		$this->object_view = $object_view;
	} // function

	/**
	 * Render the title if one has been assigned
	 * @since	v0.1.0
	 */
	protected function render_title() {
		$object_view = $this->get_object_view();
		if ( isset( $object_view ) ) {
			$title = $object_view->get_object_view_title();
			if ( isset( $title ) ) {
				echo "<h2 class=\"reg-man-rc-object-view-title\">$title</h2>";
			} // endif
		} // endif
	} // function

	/**
	 * Render the contents that appear after the title.  Subclasses may override.
	 * @since	v0.1.0
	 */
	protected function render_after_title_section() {
		$object_view = $this->get_object_view();
		if ( isset( $object_view ) ) {
			$after_title_section = $object_view->get_object_view_after_title_section();
			if ( ! empty( $after_title_section ) ) {
				echo '<div class="reg-man-rc-object-view-after-title-section-container">';
					$after_title_section->render_section();
				echo '</div>';
			} // endif
		} // endif
	} // function

	/**
	 * Render the main content for this object
	 * @since	v0.1.0
	 */
	protected function render_main_content() {
		$object_view = $this->get_object_view();
		if ( isset( $object_view ) ) {
			echo '<div class="reg-man-rc-object-view-main-content">';
				$sections = $object_view->get_object_view_main_content_sections_array();
				if ( ! empty( $sections ) ) {
					foreach( $sections as $section ) {
						$section->render_section();
					} // endfor
				} // endif
			echo '</div>';
		} // endif
	} // function

	/**
	 * Get the content for this view
	 * @return string
	 * @since	v0.1.0
	 */
	public abstract function get_content();

} // class