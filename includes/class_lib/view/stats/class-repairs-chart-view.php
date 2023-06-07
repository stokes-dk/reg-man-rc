<?php
namespace Reg_Man_RC\View\Stats;

use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\Model\Error_Log;
use Reg_Man_RC\Model\Stats\Repairs_Chart_Model;

/**
 * An instance of this class provides a view showing a simple chart of the number of items diverted from landfill.
 * The class defines a shortcode which can be placed on any page to show the chart.
 *
 * @since v0.1.0
 *
 */
class Repairs_Chart_View {
	
	/** The shortcode used to render a calendar */
	const ITEMS_FIXED_SHORTCODE	= 'rc-items-fixed';

	private $heading; // The heading for the view
	private $heading_format; // A format for the heading, used in sprintf()
	private $chart_model; // The Repairs_Chart_Model instance for this view
	
	private function __construct() { }

	/**
	 * Create a new instance of this class
	 * @return Repairs_Chart_View
	 */
	public static function create() {
		$result = new self();
		return $result;
	} // function
	
	private function get_heading_format() {
		if ( ! isset( $this->heading_format ) ) {
			$chart_model = $this->get_chart_model();
			$total_diverted = $chart_model->get_total_items_diverted();
			/* Translators: %s is a count of items fixed */
			$this->heading_format = _n( '%s Item Fixed!', '%s Items Fixed!', $total_diverted, 'reg-man-rc' );
		} // endif
		return $this->heading_format;
	} // function
	
	private function get_heading() {
		if ( ! isset( $this->heading ) ) {
			$chart_model = $this->get_chart_model();
			$total_diverted = $chart_model->get_total_items_diverted();
			$heading_format = $this->get_heading_format();
			$this->heading = sprintf( $heading_format, number_format_i18n( $total_diverted ) );
		} // endif
		return $this->heading;
	} // function
	
	/**
	 * Get the Repairs_Chart_Model for this view
	 * @return Repairs_Chart_Model
	 */
	private function get_chart_model() {
		if ( ! isset( $this->chart_model ) ) {
			$this->chart_model = Repairs_Chart_Model::create_simplified_bar_chart( NULL ); // NULL is all events
		} // endif
		return $this->chart_model;
	} // function
	
	/**
	 * Perform the necessary steps to register this view with the appropriate Wordpress hooks, actions and filters
	 *
	 * This method is called automatically during the init hook.
	 *
	 * @return void
	 * @since	v0.4.0
	 */
	public static function register() {

		// add my scripts and styles correctly for front end
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'handle_enqueue_scripts' ) );

		if ( ! is_admin() ) {

			// create my shortcode for items fixed
			add_shortcode( self::ITEMS_FIXED_SHORTCODE, array( __CLASS__, 'get_items_fixed_shortcode_content' ) );

		} // endif

	} // function
	
	/**
	 * Get the content for the items fixed shortcode
	 *
	 * This method is called automatically when the shortcode is on the current page.
	 *
	 * @return	string	The contents of the Calendar shortcode.  Wordpress will insert this into the page.
	 * @since	v0.4.0
	 */
	public static function get_items_fixed_shortcode_content( $attributes ) {

		$view = self::create();
		
		// TODO: animation duration, easing style, easing direction
		$attribute_values = shortcode_atts( array(
			'title'		=> NULL,
			'style'		=> '',
		), $attributes );

		$error_array = array();

		if ( isset( $attribute_values[ 'title' ] ) ) {
			$title = $attribute_values[ 'title' ];
			$replacement_var_count = substr_count( $title, '%s' );
			switch( $replacement_var_count ) {
				
				case 0: // No replacement variables so the title is just the heading
					$view->heading = $title;
					break;
					
				case 1: // Exactly one replacement variable so the title is a format to include the count
					$view->heading_format = $title;
					break;
					
				default: // More than 1 replacement variable, this is not legal
					/* translators: %1$s is an invalid title format in the items fixed shortcode */
					$msg = __( 'The specified title contains more than one replacement variable.  Only 1 is allowed: "%1$s"', 'reg-man-rc' );
					$error_array[] = sprintf( $msg, $title );
					break;
					
			} // endswitch
		} // endif
		
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

			$style = isset( $attribute_values[ 'style' ] ) ? $attribute_values[ 'style' ] : '';
			
			echo "<div class=\"reg-man-rc-shortcode-container items-fixed\" style=\"$style\">";

				$view->render();
			
			echo '</div>';

		$result = ob_get_clean();

		return $result;
	} // function

	/**
	 * Render this view
	 */
	public function render() {

		$chart_model = $this->get_chart_model();
		
		$heading = $this->get_heading();
		$heading_html = esc_html( $heading );
		
		echo "<div class=\"reg-man-rc-items-fixed-chart-view reg-man-rc-static-chart-view\">";
			if ( ! empty( $heading ) ) {
				echo '<div class="heading-container">';
					echo "<h4 class=\"heading\">$heading_html</h4>";
				echo '</div>';
			} // endif
			echo '<div class="reg-man-rc-chart-canvas-container">';
				echo '<canvas></canvas>';
			echo '</div>';
			echo '<script class="reg-man-rc-chart-config-data" type="application/json">'; // json data for chart
				$chart_config = $chart_model->get_chart_config();
				echo json_encode( $chart_config );
			echo '</script>';

		echo '</div>';
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
		if ( $post instanceof \WP_Post ) {
			if ( has_shortcode( $post->post_content, self::ITEMS_FIXED_SHORTCODE ) ) {
				Scripts_And_Styles::enqueue_stats_view_script_and_styles();
			} // endif
		} // endif
	} // function

} // class