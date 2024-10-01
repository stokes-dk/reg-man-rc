<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Model\Venue;
use Reg_Man_RC\View\Ajax_Form;
use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\Control\Admin\Venue_Import_Admin_Controller;
use Reg_Man_RC\Control\User_Role_Controller;
use Reg_Man_RC\Control\Scripts_And_Styles;

/**
 * The administrative view for Venue importing
 *
 * @since	v0.8.5
 *
 */
class Venue_Import_Admin_View {

	const PAGE_SLUG = Venue::POST_TYPE . '-import';

	private $import_file_attachment_id;

	private function __construct() {
	} // function

	/**
	 * Create an instance of this class
	 * @return \Reg_Man_RC\View\Admin\Venue_Import_Admin_View
	 */
	public static function create() {
		$result = new self();
		return $result;
	} // function

	/**
	 * Register this view
	 */
	public static function register() {

		if ( is_admin() && Venue_Import_Admin_Controller::current_user_can_import_venues() ) {
	
			// Register my importer
			add_action( 'admin_init', array( __CLASS__, 'register_importer' ) );

			// Register the hook to enqueue scripts and styles as necessary
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'handle_admin_enqueue_scripts' ) );

		} // endif

	} // function

	/**
	 * Register this importer
	 */
	public static function register_importer() {

		// I need to include the base import function from wordpress
		require_once ABSPATH . 'wp-admin/includes/import.php';

		$id = Venue_Import_Admin_Controller::IMPORTER_ID;
		$name = __( 'Repair Café Venues', 'reg-man-rc' );
		$desc = __( 'Import repair café venues from a CSV file.', 'reg-man-rc' );
		$view_class = Venue_Import_Admin_View::class;
		$callback = array( $view_class, 'render_tool_import_page' );
		register_importer( $id, $name, $desc, $callback );

	} // function
	
	/**
	 * Conditionally enqueue the correct scripts for this user interface if we're on the right page
	 *
	 * This method is triggered by the admin_enqueue_scripts hook
	 *
	 * @return void
	 * @since	v0.1.0
	 */
	public static function handle_admin_enqueue_scripts( $hook ) {
		$screen = get_current_screen();
		if ( is_admin() && is_object( $screen ) && isset( $screen->id ) && ( $screen->id == 'admin' ) &&
				( isset( $_REQUEST[ 'import' ] ) && ( $_REQUEST[ 'import' ]  == Venue_Import_Admin_Controller::IMPORTER_ID ) ) ) {
			Scripts_And_Styles::enqueue_base_admin_script_and_styles();
			Scripts_And_Styles::enqueue_ajax_forms();
		} // endif
	} // function

	/**
	 * Render the page linked from the Tools > Import page.
	 *
	 */
	public static function render_tool_import_page() {

		echo '<div class="wrap reg-man-rc-import-form-container reg-man-rc-venue-import-form-container">';

			$title = __( 'Import Repair Café Venue Data', 'reg-man-rc' );
			echo "<h1>$title</h1>";

			$view = self::create();
			$view->render_form();

		echo '</div>';

	} // function

	/**
	 * Render the contents of the form
	 */
	public function render_form() {
/* FIXME
		$input_list = Form_Input_List::create();

		$attachment_id = $this->get_import_file_attachment_id();
		$attachment_file = ! empty( $attachment_id ) ? get_attached_file( $attachment_id ) : NULL;

		$button_format = '<span class=" reg-man-rc-icon-text-container"><i class="icon dashicons dashicons-%2$s"></i><span class="text">%1$s</span></span>';

		// To request a form reload (start over) the client will change this to TRUE
		$input_list->add_hidden_input( 'reload', 'FALSE' );
			
		if ( empty( $attachment_file ) ) {

			$label = '';
			$desc = __( 'Use this tool to import venues from a CSV file.', 'reg-man-rc' );
			$input_list->add_information( $label, $desc );

			$label = __( 'The file must contain columns for the following venue details:', 'reg-man-rc' );
			$desc = 'Name, Description, Location, Geographic Position, and Map Zoom Level';
			$input_list->add_information( $label, $desc );

			$label = '';
			$desc = __( 'Column values are separated by a comma and optionally enclosed in double quotes.', 'reg-man-rc' );
			$input_list->add_information( $label, $desc );

			$label = __( 'Venue CSV import file', 'reg-man-rc' );
			$name = 'venue-import-file-name';
			$hint = '';
			$classes = '';
			$accept = '.csv';
			$is_required = TRUE;
			$input_list->add_file_input( $label, $name, $hint, $classes, $is_required, $accept );

			$button_text = __( 'Continue', 'reg-man-rc' );

		} else {
			
			$name = 'venue-import-attachment-id';
			$value = $attachment_id;
			$input_list->add_hidden_input( $name, $value );

			$label = __( 'The following file was successfully uploaded', 'reg-man-rc' );
			$file_name = basename( $attachment_file );
			$value = "<span>$file_name</span>";
			$input_list->add_information( $label, $value );

			$label = '';
			$desc = __( 'Press the button below to import the records.', 'reg-man-rc' );
			$input_list->add_information( $label, $desc );

			$text = __( 'Back', 'reg-man-rc' );
			$label = sprintf( $button_format, $text, 'arrow-left-alt2' );
			$type = 'button';
			$classes = 'reg-man-rc-button import-reload-button';
			$input_list->add_form_button( $label, $type, $classes );

			$button_text = __( 'Import Repair Café Volunteers', 'reg-man-rc' );

		} // endif

		$label = $button_text;
		$type = 'submit';
		$classes = 'button';
		$input_list->add_form_button( $button_text, $type, $classes );
*/
		$action = Venue_Import_Admin_Controller::AJAX_ACTION;
		$method = 'POST';
		$classes = 'reg-man-rc-import-form reg-man-rc-venue-import-form';
		$form = Ajax_Form::create( $action, $method, $classes );
		$form->set_include_nonce_fields( FALSE ); // I will do this in the form inputs
		$input_list = $this->get_form_input_list();
		$form->add_input_list_to_form_content( $input_list );
		$form->set_is_file_uplaod( TRUE );

		$form->render();

	} // function

	
	
	private function get_import_file_attachment_id() {
		return $this->import_file_attachment_id;
	} // endif

	/**
	 * Get the attachment id for the import file
	 * @param int $import_file_attachment_id
	 */
	public function set_import_file_attachment_id( $import_file_attachment_id ) {
		$this->import_file_attachment_id = $import_file_attachment_id;
	} // endif

	/**
	 * Render the contents of the form
	 */
	public function get_form_contents() {
		ob_start();
			$input_list = $this->get_form_input_list();
			$input_list->render();
		$result = ob_get_clean();
		return $result;
	} // function

	/**
	 * Get the form input list
	 * @return Form_Input_List
	 */
	private function get_form_input_list() {

		$input_list = Form_Input_List::create();

		$nonce = wp_create_nonce( Venue_Import_Admin_Controller::AJAX_ACTION );
		$input_list->add_hidden_input( '_wpnonce', $nonce );
		
		$button_format = '<span class=" reg-man-rc-icon-text-container"><i class="icon dashicons dashicons-%2$s"></i><span class="text">%1$s</span></span>';

		$attachment_id = $this->get_import_file_attachment_id();
		$attachment_file = ! empty( $attachment_id ) ? get_attached_file( $attachment_id ) : NULL;
		
		// To request a form reload (start over) the client will change this to TRUE
		$input_list->add_hidden_input( 'reload', 'FALSE' );
		
		if ( empty( $attachment_file ) ) {

			$label = '';
			$desc = __( 'Import venues from a CSV file.', 'reg-man-rc' );
			$input_list->add_information( $label, $desc );

			$label = __( 'The file must contain the following column headings:', 'reg-man-rc' );
			// NOTE that the column names are always in english and not to be translated
			// FIXME - this has to be changed, the user should be able to assign which column contains what data
			$desc = 'Description, Visitor, Email, Join Mail List?, First Time?, Fixer Station, Item Type, Status ID';
			$input_list->add_information( $label, $desc );

			$label = '';
			$desc = __( 'Column values are separated by a comma and optionally enclosed in double quotes.', 'reg-man-rc' );
			$input_list->add_information( $label, $desc );

			$label = __( 'Venue CSV file', 'reg-man-rc' );
			$name = 'venue-import-file-name';
			$hint = '';
			$classes = '';
			$accept = '.csv';
			$is_required = TRUE;
			$input_list->add_file_input( $label, $name, $hint, $classes, $is_required, $accept );

			$button_label = __( 'Continue', 'reg-man-rc' );
			
		} else {
			
			$name = 'venue-import-attachment-id';
			$value = $attachment_id;
			$input_list->add_hidden_input( $name, $value );

			$label = __( 'The following file was successfully uploaded', 'reg-man-rc' );
			$file_name = basename( $attachment_file );
			$value = "<span>$file_name</span>";
			$input_list->add_information( $label, $value );

			$text = __( 'Back', 'reg-man-rc' );
			$label = sprintf( $button_format, $text, 'arrow-left-alt2' );
			$type = 'button';
			$classes = 'button import-reload-button';
			$input_list->add_form_button( $label, $type, $classes );

			$button_text = __( 'Import Venues', 'reg-man-rc' );
			$button_label = sprintf( $button_format, $button_text, 'database-import' );

		} // endif

		$label = $button_label;
		$type = 'submit';
		$classes = 'button';
		$input_list->add_form_button( $label, $type, $classes );

		return $input_list;
	} // function
	
} // class