<?php

namespace Reg_Man_RC\Model\Stats;

/**
 * Implementors of this interface are graphical charts like a pie chart or bar chart for events.
 * They have a Chart_Config object which can be serialized and used on the client side to render the chart.
 *
 * @since v0.1.0
 *
 */

interface Chart_Model {
	
	const GREY_COLOUR					= 'rgb( 204,	204,	204	)';
	const DARK_GREY_COLOUR				= 'rgb( 102,	102,	102	)';
	const RED_COLOUR					= 'rgb( 204,	0,		0	)';
	const MAGENTA_COLOUR				= 'rgb( 204,	0,		204	)';
	const YELLOW_COLOUR					= 'rgb( 204,	204, 	0	)';
	const YELLOW_GREEN_COLOUR			= 'rgb( 153,	204, 	0	)';
	const ORANGE_GREEN_COLOUR			= 'rgb( 204,	153, 	0	)';
	const SOFT_YELLOW_GREEN_COLOUR		= 'rgba( 153,	204, 	0,	0.5	)';
	const GREEN_COLOUR					= 'rgb(	0,		204,	0	)';
	const CYAN_COLOUR					= 'rgb(	0,		204,	204	)';
	const BLUE_COLOUR					= 'rgb(	0,		0,		204	)';

	const DEFAULT_COLOUR				= self::GREY_COLOUR;

	const VISITOR_COLOUR				= self::BLUE_COLOUR;
	const FIXER_COLOUR					= self::CYAN_COLOUR;
	const NON_FIXER_COLOUR				= self::MAGENTA_COLOUR;

	const VISITOR_WITH_EMAIL_COLOUR		= self::GREEN_COLOUR;
	const VISITOR_MAIL_LIST_COLOUR		= self::YELLOW_COLOUR;
	const VISITOR_FIRST_TIME_COLOUR		= self::RED_COLOUR;

	const FIXED_ITEM_COLOUR				= self::GREEN_COLOUR;
	const REPAIRABLE_ITEM_COLOUR		= self::YELLOW_COLOUR;
	const EOL_ITEM_COLOUR				= self::RED_COLOUR;
	const UNKNOWN_STATUS_ITEM_COLOUR	= self::GREY_COLOUR;

	const NO_VOLUNTEER_ROLE_COLOUR		= self::GREY_COLOUR;

	/**
	 * Get the Chart_Config object for this chart
	 * @return	Chart_Config
	 */
	public function get_chart_config();

} // interface
