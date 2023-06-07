<?php
namespace Reg_Man_RC\View\Object_View;

use Reg_Man_RC\View\Event_Descriptor_View;
use Reg_Man_RC\View\Event_View;
use Reg_Man_RC\View\Map_View;
use Reg_Man_RC\View\Volunteer_Registration_View;
use Reg_Man_RC\Model\Error_Log;

/**
 * This template renders an event descriptor group
 */
class Event_Descriptor_Group_View_Template extends Abstract_Object_View_Template {
	
	private function __construct() {
	} // funciton

	/**
	 * Create an instance of this class using the specified object view
	 * @param	Event_Descriptor_Group_View		$event_descriptor_group_view
	 * @return	Object_View_Template
	 */
	public static function create( $event_descriptor_group_view ) {
		$result = new self();
		$result->object_view = $event_descriptor_group_view;
		return $result;
	} // funciton
	
	/**
	 * Get the event descriptor group view
	 * @return Event_Descriptor_Group_View
	 */
	private function get_event_descriptor_group_view() {
		return $this->object_view;
	} // function


	/**
	 * Get the content for this view
	 * @return string
	 * @since	v0.4.0
	 */
	public function get_content() {
		ob_start();

		$object_view = $this->get_object_view();
		if ( isset( $object_view ) ) {
			// The following can be used to help debug by showing the current object's class name
//				$reflect = new \ReflectionClass( $this->get_object_view() );
//				echo $reflect->getShortName();

				$classes = $this->get_classes();
				echo "<div class=\"reg-man-rc-object-view-container reg-man-rc-event-descriptor-group-view-container $classes\">";
	
					// Title
					$this->render_title();
	
					// After title section
					$this->render_after_title_section();
	
					// Event Descriptors content
					$this->render_event_descriptors();
					
				echo '</div>';
		} // endif
		
		$result = ob_get_clean();
		
		return $result;
		
	} // function

	/**
	 * Render the collection of event descriptors
	 */
	private function render_event_descriptors() {
		
		$group_view = $this->get_event_descriptor_group_view();
		$map_type = $group_view->get_info_window_map_type();
		$group_marker = $group_view->get_event_group_map_marker();
		$descriptors_array = $group_marker->get_event_descriptors_array();

		echo '<div class="reg-man-rc-event-descriptor-group-view-descriptor-list-container">';
		
			foreach( $descriptors_array as $event_descriptor ) {
	
				$events_array = $group_marker->get_events_by_descriptor( $event_descriptor );
	
				if ( count( $events_array ) === 1 ) {
					
					$event = $events_array[ 0 ];
	
					switch( $map_type ) {
			
						case Map_View::MAP_TYPE_CALENDAR_VOLUNTEER_REG:
							$view = Volunteer_Registration_View::create_for_map_info_window( $event, $map_type );
							break;
			
						default:
							$view = Event_View::create_for_map_info_window( $event, $map_type );
							break;
			
					} // endswitch
					
				} else {
					
					$view = Event_Descriptor_View::create_for_map_info_window( $event_descriptor, $map_type );
					$view->set_events_array( $events_array );
					
				} // endif
				
				$content = $view->get_object_view_content();
				
				echo '<div class="reg-man-rc-event-descriptor-group-view-descriptor-container">';
					echo $content;
				echo '</div>';
	
			} // endfor
		
		echo '</div>';
	
	} // function

} // class