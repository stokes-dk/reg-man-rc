<?php
namespace Reg_Man_RC\View\Object_View;

/**
 * Implementors of this interface provide the rendering function for object view according to a specific template.
 */
interface Object_View_Template {

	/**
	 * Set the css classes for the outermost html element
	 * @param	string	$classes	The css classes for the html element containing this view
	 */
	public function set_classes( $classes );

	/**
	 * Set the object view to be rendered by this template
	 * @param	Object_View	$object_view	The object view to be rendered
	 */
	public function set_object_view( $object_view );
	
	/**
	 * Get the content for this view
	 * @return string
	 * @since	v0.1.0
	 */
	public function get_content();

} // class