<?php
namespace Reg_Man_RC;

/*
 * Template Name: Registration Manager Minimal Template
*/

// Remove theme styles and scripts after all scripts have been enqueued, i.e. do it last (1000th)
add_action( 'wp_enqueue_scripts', 'Reg_Man_RC\Control\Template_Controller::dequeue_theme_styles_and_scripts', 1000 );

// Remove the words private or protected from the page title
add_filter( 'protected_title_format', 'Reg_Man_RC\Control\Template_Controller::remove_private_protected_from_title' );
add_filter( 'private_title_format',	 'Reg_Man_RC\Control\Template_Controller::remove_private_protected_from_title' );

// I will capture the page contents first so I can know what widgets are on it and so on
// To show the content we have to loop through the posts apparently
ob_start();
	// To show the content we have to loop through the posts apparently
	// We'll just do this to allow the admin to put a note or something a the top of the page if they wish
	if ( have_posts() ) {
		while ( have_posts() ) {
			the_post();
			the_content();
		} // endwhile
	} // endif
	$page_contents = ob_get_contents();
ob_end_clean();

?>
<!DOCTYPE html>
<!--[if IE 6]>
<html id="ie6" <?php language_attributes(); ?>>
<![endif]-->
<!--[if IE 7]>
<html id="ie7" <?php language_attributes(); ?>>
<![endif]-->
<!--[if IE 8]>
<html id="ie8" <?php language_attributes(); ?>>
<![endif]-->
<!--[if !(IE 6) & !(IE 7) & !(IE 8)]><!-->
<html <?php language_attributes(); ?>>
<!--<![endif]-->
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width" />
<title>
	<?php
		wp_title( '|', TRUE, 'right' );
		bloginfo( 'name' ); // Add the blog name.
	?>
</title>
<link rel="profile" href="http://gmpg.org/xfn/11" />

<?php
// Include the theme's stylesheet by uncommenting the following
//echo '<link rel="stylesheet" type="text/css" media="all" href="' . get_bloginfo( 'stylesheet_url' ). '" />';
?>

<!--[if lt IE 9]>
<script src="<?php echo get_template_directory_uri(); ?>/js/html5.js" type="text/javascript"></script>
<![endif]-->
<?php	wp_head(); ?>
</head>

<body>
<div id="form-page" class="hfeed">
	<header id="form-banner" role="banner">
		<div class="banner-container">
			<h1 id="form-title"><?php the_title()?></h1>
<?php
/* FIXME - I need to figure out how the logo is established - How do we do settings in general???
$logoUrl = get_option( RC_Reg_Admin_Settings::FORM_LOGO_URL_SETTING_NAME );
if (isset($logoUrl) && !empty($logoUrl)) {
	echo "<img src=\"$logoUrl\">";
} // endif
*/
?>
		</div>
	</header><!-- #form-banner -->

	<div id="form-main">
		<?php echo $page_contents; ?>
	</div><!-- #form-main -->

	<footer id="form-footer" role="contentinfo">
		<?php
		if ( defined('WP_DEBUG') && WP_DEBUG === TRUE ) {
			wp_footer(); // Only show the footer if DEBUG is turned on
		} // endif
		?>
	</footer><!-- #form-footer -->
</div><!-- #form-page -->

</body>
</html>