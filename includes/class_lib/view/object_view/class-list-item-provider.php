<?php
namespace Reg_Man_RC\View\Object_View;

/**
 * Implementors of this interface provide list items for an object view list section
 */
interface List_item_Provider {

	/**
	 * Get a list item based on its name.  May return NULL if the name is not known or there's no content to display.
	 * @return List_Item|NULL
	 */
	public function get_list_item( $item_name );

} // interface