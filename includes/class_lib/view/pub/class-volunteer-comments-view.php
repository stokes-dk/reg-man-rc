<?php
namespace Reg_Man_RC\View\Pub;
// NOTE that Public is a reserved word and cannot be used in a namespace.  We use Pub instead

use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Control\Template_Controller;

/**
 * A user interface element for the comments portion of the volunteer area including the display of existing comments
 *  and form to add a new one.
 *
 * @since v0.1.0
 *
 */
class Volunteer_Comments_View {

	private function __construct() {
	} // constructor

	public static function create() {
		$result = new self();
		return $result;
	} // function

	public function render() {

		$volunteer = Volunteer::get_current_volunteer();
		$page_allows_comments = TRUE; // TODO: do not show this on the home page or preferences page

		if ( isset( $volunteer ) && $page_allows_comments ) {

			echo '<div id="comments" class="comments-area">';

				// Show the comments, if any
				if ( have_comments() ) {

					$this->render_title();

					$this->render_comments_list();

					if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) {
						$this->render_navigation( 'below' );
					} // endif

				} // endif

				// If comments are closed but the page has comments, then inform the user?
				if ( ! comments_open() && get_comments_number() ) {

					$label = __( 'Comments are closed', 'reg-man-rc' );
					echo "<p class=\"no-comments\">$label</p>";

				} // endif

				comment_form();

			echo '</div><!-- #comments -->';

		} // endif

	} // function

	/**
	 * Render the title for the comments section
	 */
	private function render_title() {

		$comment_count = get_comments_number();

		/* Translators: %1$s is a count of comments */
		$title_format = _n( '%1$s comment', '%1$s comments', $comment_count, 'reg-man-rc' );
		$title = sprintf( $title_format, number_format_i18n( $comment_count ) );
		echo '<div class="reg-man-rc-comments-count-container">';
			echo "<h3 class=\"comments-title\">$title</h3>";
		echo '</div>';

	} // function

	/**
	 * Render the actual list of comments
	 */
	private function render_comments_list() {

		echo '<ol class="reg-man-rc-comment-list">';
			wp_list_comments(
				array(
//					'callback' => 'astra_theme_comment',
					'style'    => 'ol',
				)
			);
		echo '</ol><!-- .reg-man-rc-comment-list -->';

	} // function

	/**
	 * Render the navigation links.
	 * @param string $location	Either "above" or "below" indicating where the navigation is in relation to the list of comments
	 */
	private function render_navigation( $location ) {

		$id = "comment-nav-$location";
		$classes = 'navigation comment-navigation';
		$aria_label = esc_attr__( 'Comment navigation', 'reg-man-rc' );

		echo "<nav id=\"$id\" class=\"$classes\" aria-label=\"$aria_label\">";

			$previous_label = __( 'Previous', 'reg-man-rc' ); // TODO: could say "Older" or "Newer"
			$previous_href = ''; // FIXME
			$previous_link = "<a href=\"$previous_href\">$previous_label</a>";

			$next_label = __( 'Next', 'reg-man-rc' ); // TODO: could say "Older" or "Newer"
			$next_href = ''; // FIXME
			$next_link = "<a href=\"$next_href\">$next_label</a>";

			echo '<div class="nav-links">';

				echo "<div class=\"nav-previous\">$previous_link</div>";
				echo "<div class=\"nav-next\">$next_link</div>";

			echo '</div><!-- .nav-links -->';

		echo "</nav><!-- #comment-nav-$location -->";

	} // function


	/**
	 * Perform the necessary steps to register this view with the appropriate Wordpress hooks, actions and filters
	 *
	 * This method is called automatically during the init hook.
	 *
	 * @return void
	 *
	 * @since	v0.1.0
	 */
	public static function register() {

		// Add my scripts and styles
//		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'handle_wp_enqueue_scripts' ) );

		// Filter whether comments are allowed. We only want comments when the $_GET arg includes an event
		add_filter( 'comments_open', array( __CLASS__, 'filter_comments_open' ), 10, 2 );

		// Filter comments template
		add_filter( 'comments_template', array( __CLASS__, 'filter_comments_template' ), 10, 1 );

		// Filter which comments to show
		add_filter( 'comments_array', array( __CLASS__, 'filter_comments_array' ), 10, 2 );

		// Filter which comments to show
		add_filter( 'preprocess_comment', array( __CLASS__, 'filter_preprocess_comment' ), 10, 1 );

	} // function

	public static function filter_comments_open( $is_open, $post_id ) {
		// If we are on the volunteer area page then comments are open only when there is a volunteer
		$vol_area_post_id = Volunteer_Area::get_page_id();
		if ( $post_id == $vol_area_post_id ) {
			$volunteer = Volunteer::get_current_volunteer();
			$result = ! empty( $volunteer ) ? $is_open : FALSE;
//			Error_Log::var_dump( ! empty( $volunteer ), $result );
		} else {
			$result = $is_open;
		} // endif
		return $result;
	} // function

	public static function filter_comments_template( $theme_template ) {

//		Error_Log::var_dump( $theme_template );
		global $post;
		$post_id = $post->ID;

		$vol_area_post_id = Volunteer_Area::get_page_id();

		if ( $post_id == $vol_area_post_id ) {

			$result = Template_Controller::get_comments_template_file_name();

		} else {

			$result = $theme_template;
		} // endif

		return $result;
	} // function

	public static function filter_comments_array( $comments_array, $post_id ) {
		// If we are on the volunteer area page then show the comments for the specified event
		$vol_area_post_id = Volunteer_Area::get_page_id();
		if ( $post_id == $vol_area_post_id ) {

			// If there is no volunteer logged in then show no comments
			$volunteer = Volunteer::get_current_volunteer();
			if ( empty( $volunteer ) ) {
				$result = array();
			} else {
				$result = $comments_array;
/*
				$event = Volunteer_Area::get_event_from_request();
				if ( ! empty( $event ) ) {
					// We have an event so show its comments ONLY
					$result = array(); // TODO!!!
				} else {
					// We have no event so we have no comments
					$result = array();
				} // endif
*/
			} // endif

		} else {
			$result = $comments_array;
		} // endif
		return $result;
	} // function


	public static function filter_preprocess_comment( $comment_data ) {
//		Error_Log::var_dump( $comment_data );
		return $comment_data;
	} // function



} // class