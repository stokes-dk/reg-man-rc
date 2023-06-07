<?php
namespace Reg_Man_RC\View\Object_View;

/**
 * This template renders the object inside a details (disclosure) element with summary and expandable details
 */
class Details_Disclosure_Object_View_Template extends Abstract_Object_View_Template {

	private function __construct() {
	} // funciton

	/**
	 * Create an instance of this class using the specified object view
	 * @param	Object_View		$object_view
	 * @return	Object_View_Template
	 */
	public static function create( $object_view = NULL ) {
		$result = new self();
		$result->object_view = $object_view;
		return $result;
	} // funciton

	/**
	 * Get the content for this view
	 * @return string
	 * @since	v0.1.0
	 */
	public function get_content() {
		ob_start();

		$object_view = $this->get_object_view();
		if ( isset( $object_view ) ) {
			// The following can be used to help debug by showing the current object's class name
//				$reflect = new \ReflectionClass( $this->get_object_view() );
//				echo $reflect->getShortName();

				$classes = $this->get_classes();
				echo "<details class=\"reg-man-rc-object-view-container reg-man-rc-object-view-disclosure-container $classes\">";

					// Summary
					echo '<summary>';
					
						echo '<div class="reg-man-rc-object-view-summary-container">';
						
							// Title
							$this->render_title();
		
							// After title section (included in summary)
							$this->render_after_title_section();
							
						echo '</div>';
						
					echo '</summary>';
	
					// Main content
					$this->render_main_content();
	
				echo '</details>';
		} // endif
		
		$result = ob_get_clean();
		
		return $result;
		
	} // function

} // class