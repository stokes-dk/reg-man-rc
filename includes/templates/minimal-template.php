<?php
/**
 *  Template Name: Minimal Template (Registration Manager for Repair Café)
 */
namespace Reg_Man_RC;

use Reg_Man_RC\Model\Settings;

/*
 * Template Name: Minimal Template - Registration Manager for Repair Café
 */

// Remove theme styles and scripts after all scripts have been enqueued, i.e. do it last (1000th)
add_action( 'wp_enqueue_scripts', '\Reg_Man_RC\Control\Template_Controller::dequeue_theme_styles_and_scripts', 1000 );

// Remove the words private or protected from the page title
add_filter( 'protected_title_format', '\Reg_Man_RC\Control\Template_Controller::remove_private_protected_from_title' );
add_filter( 'private_title_format',	 '\Reg_Man_RC\Control\Template_Controller::remove_private_protected_from_title' );

$title = get_the_title();
if ( isset( $title ) ) {
	$stripped_title = wp_strip_all_tags( $title );
	add_filter( 'pre_get_document_title', function() use ( $stripped_title ) { return $stripped_title; } );
} // endif

// I will capture the page contents first so I can know what widgets are on it and so on
ob_start();
	// Capture the post content
	if ( have_posts() ) {
		// There will not be multiple posts when using this template (I think)
		// And for some reason I am getting three posts for the registration manager page which is causing
		//  three copies of the content to be inserted into the page in the subdomain "test.repaircafe..."
//		while ( have_posts() ) {
			the_post();
			the_content();
//		} // endwhile
	} // endif
	$page_contents = ob_get_contents();
ob_end_clean();

if ( FALSE ) {
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
<?php wp_head(); ?>
</head>

<?php
} // endif
wp_head();
?>
<body class='minimal-template'>
<div id="form-page" class="hfeed">
	<header id="form-banner" role="banner">
		<div class="banner-container">
			<h1 id="form-title"><?php the_title()?></h1>
<?php
	$attachment_id = get_theme_mod( 'custom_logo' );
	if ( ! empty( $attachment_id ) ) {
		$size = 'medium';
		$attr = array(
				'class' => 'reg-man-rc-logo-image',
		);
		$img_tag = wp_get_attachment_image( $attachment_id, $size, $icon = FALSE, $attr );
		echo $img_tag;
	} // endif
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