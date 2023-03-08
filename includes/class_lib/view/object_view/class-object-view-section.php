<?php
namespace Reg_Man_RC\View\Object_View;

/**
 * An instance of this class represents a section shown in an object view like an event's map or details list
 *
 */
interface Object_View_Section {
	/**
	 * Render this section
	 */
	public function render_section();
} // interface