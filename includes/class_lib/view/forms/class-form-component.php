<?php

namespace Reg_Man_RC\View\Forms;

/**
 * An implementation of this interface represents any component inside a form like
 * an input field, a fieldset, a list of components etc.
 *
 */
interface Form_Component {

	/**
	 * Get the content as a string, e.g. '<input type="text"></input>'
	 * @return string
	 */
	public function get_content();
	
} // interface