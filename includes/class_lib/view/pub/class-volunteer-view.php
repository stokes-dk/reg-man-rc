<?php
namespace Reg_Man_RC\View\Pub;

use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Model\Settings;
use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Model\Error_Log;

/**
 * An instance of this class provides a user interface for a Volunteer that appears on the public-facing (frontend) side of the website.
 *
 * @since	v0.1.0
 *
 */
class Volunteer_View {

	/** The shortcode used to render volunteer profiles */
	const SHORTCODE = 'rc-volunteer-profiles';

	/** A private constructor forces users to use one of the factory methods */
	private function __construct() {
	} // constructor

	/**
	 *  Create a new instance of this class
	 *
	 *	@return	Volunteer_View
	 *  @since	v0.1.0
	 */
	public static function create() {
		$result = new self();
		return $result;
	} // function

	/**
	 *  Register the view for the Volunteer custom post type.
	 *
	 *  @since	v0.1.0
	 */
	public static function register() {

		// add my scripts and styles correctly for front end
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'handle_enqueue_scripts' ) );

		// create my shortcode
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'get_shortcode_content' ) );

	} // function

	/**
	 * Conditionally enqueue the correct scripts for this user interface on the frontend if the shortcode is present
	 *
	 * This method is called automatically when scripts are enqueued.
	 *
	 * @return void
	 * @since	v0.1.0
	 */
	public static function handle_enqueue_scripts() {
		global $post;
		if ( ( $post instanceof \WP_Post ) && has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			Scripts_And_Styles::enqueue_public_base_scripts_and_styles();
		} // endif
	} // function

	/**
	 * Return the content for the shortcode.
	 *
	 * This method is called automatically when the shortcode is on the current page.
	 *
	 * @return	string	The contents of the Volunteer Profile shortcode.  WordPress will insert this into the page.
	 * @since	v0.1.0
	 */
	public static function get_shortcode_content( $attributes ) {

		$view = self::create(); // create the view to show the volunteer profile list

		// Returns the contents for the shortcode.  WP will insert the result into the page.
		$attribute_values = shortcode_atts( array(
				// Assign defaults here
		), $attributes );

		$error_array = array();
		// Validate attributes here

		ob_start();
			// If there are any errors, show them to the author
			if ( ! empty( $error_array ) ) {
				global $post, $current_user;
				// If there is an error in the shortcode and current user is the author then I will show them their errors
				if ( is_user_logged_in() && $current_user->ID == $post->post_author )  {
					foreach( $error_array as $error ) {
						echo '<div class="reg-man-rc shortcode-error">' . $error . '</div>';
					} // endfor
				} // endif
			} // endif

			$view->render();

		$result = ob_get_clean();

		return $result;
	} // function


	/**
	 * Render this view
	 * @return	void
	 * @since	v0.1.0
	 */
	public function render() {

		global $post;
		// TODO: Should order be an attribute of the shortcode?
		$args = array(
				'post_type'				=> Volunteer::POST_TYPE,
				'post_status'			=> 'publish', // Get the public ones ONLY
				'posts_per_page'		=> -1, // get all
//				'orderby'				=> 'menu_order', // Order by menu_order (defined in post attributes by user)
				'orderby'				=> 'rand', // Random order so it's different every time
				'order'					=> 'ASC',
				'ignore_sticky_posts'	=> 1, // TRUE here means do not move sticky posts to the start of the result set
		);

		$query = new \WP_Query( $args );

		echo '<div class="reg-man-rc-volunteer-profile-list-container">';

			if ( $query->have_posts() ) {

				echo '<ul class="reg-man-rc-volunteer-profile-list">';

					while( $query->have_posts() ) {
						$query->the_post();
						$url = get_permalink( $post );
						$title = get_the_title( $post );
						$img = get_the_post_thumbnail( $post ); // The featured image
						// NOTE, I don't know why but I have to do both the following line AND the_content() below
						// or the content is not formatted correctly!  It seems the the_content filter has to run twice
						apply_filters( 'the_content', get_the_content() ); // Does wpautop etc.
						$show_label = __( 'Show More', 'reg-man-rc' );
						$hide_label = __( 'Show Less', 'reg-man-rc' );
						echo '<li class="reg-man-rc-volunteer-profile-list-item">';
							echo '<div class="reg-man-rc-volunteer-profile-header-container">';
								echo "<h2 class=\"title\"><a href=\"$url\">$title</a></h2>";
							echo '</div>';
							echo '<div class="reg-man-rc-volunteer-profile-content-container">';
								echo '<div class="reg-man-rc-show-hide-container">';
									echo '<div class="reg-man-rc-show-hide-content">';
										echo '<div class="reg-man-rc-volunteer-profile-image-container">';
											echo $img;
										echo '</div>';
										echo '<div class="reg-man-rc-volunteer-profile-content">';
											the_content();
										echo '</div>';
									echo '</div>';
									echo '<div class="reg-man-rc-show-hide-button-container">';
										echo "<button class=\"show-button\">$show_label</button>";
										echo "<button class=\"hide-button\">$hide_label</button>";
									echo '</div>';
								echo '</div>';
							echo '</div>';
						echo '</li>';
					} // endwhile

				echo '</ul>';

			} // endif

		echo '</div>';

		wp_reset_postdata(); // Required after using WP_Query()

	} // function



} // class