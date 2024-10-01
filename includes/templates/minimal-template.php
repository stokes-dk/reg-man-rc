<?php
/**
 *  Template Name: Minimal Template (Registration Manager for Repair Café)
 */
namespace Reg_Man_RC;

use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Model\Error_Log;

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

echo '<!DOCTYPE html>';

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