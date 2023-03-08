<?php

use Reg_Man_RC\View\Pub\Volunteer_Comments_View;

/**
 * A custom template for displaying comments.
 *
 * @since 0.1.0
 */

// If someone attempts to access this file direct then exit
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // endif

// If a password is required but not entered then return
if ( post_password_required() ) {
	return;
} // endif

$view = Volunteer_Comments_View::create();
$view->render();
