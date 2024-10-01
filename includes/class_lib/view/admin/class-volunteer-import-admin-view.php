<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Model\Volunteer;
use Reg_Man_RC\Control\Admin\Volunteer_Import_Admin_Controller;
use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\View\Ajax_Form;
use Reg_Man_RC\View\Form_Input_List;

/**
 * The administrative view for Volunteer importing
 *
 * @since	v0.1.0
 *
 */
class Volunteer_Import_Admin_View {

	const PAGE_SLUG = Volunteer::POST_TYPE . '-import';

	private $import_file_attachment_id;

	private function __construct() {
	} // function

	/**
	 * Create an instance of this class
	 * @return \Reg_Man_RC\View\Admin\Volunteer_Import_Admin_View
	 */
	public static function create() {
		$result = new self();
		return $result;
	} // function

	private function get_import_file_attachment_id() {
		return $this->import_file_attachment_id;
	} // endif

	/**
	 * Assign the import file attachment ID
	 * @param int $import_file_attachment_id
	 */
	public function set_import_file_attachment_id( $import_file_attachment_id ) {
		$this->import_file_attachment_id = $import_file_attachment_id;
	} // endif

	/**
	 * Register this view
	 */
	public static function register() {

		if ( is_admin() && Volunteer_Import_Admin_Controller::current_user_can_import_volunteers() ) {
			
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

		$id = Volunteer_Import_Admin_Controller::IMPORTER_ID;
		$name = __( 'Repair Café Volunteers', 'reg-man-rc' );
		$desc = __( 'Import repair café volunteer information from a CSV file.', 'reg-man-rc' );
		$view_class = Volunteer_Import_Admin_View::class;
		$callback = array( $view_class, 'render_tool_import_page' );
		register_importer( $id, $name, $desc, $callback );

	} // function

	/**
	 * Render the contents of the form
	 */
	public function render_form_contents() {

		$input_list = Form_Input_List::create();

		$attachment_id = $this->get_import_file_attachment_id();
		$attachment_file = ! empty( $attachment_id ) ? get_attached_file( $attachment_id ) : NULL;

		if ( empty( $attachment_file ) ) {

			$label = '';
			$desc = __( 'Use this tool to import fixers and volunteers from a CSV file.', 'reg-man-rc' );
			$input_list->add_information( $label, $desc );

			$label = __( 'The file must contain the following column headings:', 'reg-man-rc' );
			$desc = 'First Name, Last Name, Email, Preferred Fixer Station, Apprentice, Preferred Volunteer Roles';
			$input_list->add_information( $label, $desc );

			$label = '';
			$desc = __( 'Column values are separated by a comma and optionally enclosed in double quotes.', 'reg-man-rc' );
			$input_list->add_information( $label, $desc );

			$label = '';
			$desc = __( 'If multiple preferred fixer stations, or volunteer roles are specified they should be separated by a pipe character, e.g. "Greeter|Setup & Cleanup".', 'reg-man-rc' );
			$input_list->add_information( $label, $desc );

			$label = __( 'Volunteer CSV import file', 'reg-man-rc' );
			$name = 'volunteer-import-file-name';
			$hint = '';
			$classes = '';
			$accept = '.csv';
			$is_required = TRUE;
			$input_list->add_file_input( $label, $name, $hint, $classes, $is_required, $accept );

			$button_text = __( 'Continue', 'reg-man-rc' );

		} else {
			$name = 'volunteer-import-attachment-id';
			$value = $attachment_id;
			$input_list->add_hidden_input( $name, $value );

			$label = __( 'The following file was successfully uploaded', 'reg-man-rc' );
			$file_name = basename( $attachment_file );
			$value = "<span>$file_name</span>";
			$input_list->add_information( $label, $value );

			$label = '';
			$desc = __( 'Press the button below to import the records.', 'reg-man-rc' );
			$input_list->add_information( $label, $desc );

			$button_text = __( 'Import Repair Café Volunteers', 'reg-man-rc' );

		} // endif

		$label = $button_text;
		$type = 'submit';
		$classes = 'button';
		$input_list->add_form_button( $button_text, $type, $classes );

		$action = Volunteer_Import_Admin_Controller::AJAX_ACTION;
		$method = 'POST';
		$classes = 'reg-man-rc-import-form reg-man-rc-volunteer-import-form';
		$form = Ajax_Form::create( $action, $method, $classes );
		$form->add_input_list_to_form_content( $input_list );
		$form->set_is_file_uplaod( TRUE );

		$form->render();

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
				( isset( $_REQUEST[ 'import' ] ) && ( $_REQUEST[ 'import' ]  == Volunteer_Import_Admin_Controller::IMPORTER_ID ) ) ) {
			Scripts_And_Styles::enqueue_base_admin_script_and_styles();
			Scripts_And_Styles::enqueue_ajax_forms();
		} // endif
	} // function

	/**
	 * Render the page linked from the Tools > Import page.
	 *
	 */
	public static function render_tool_import_page() {

		echo '<div class="wrap">';

			$title = __( 'Import Repair Café Volunteer Data', 'reg-man-rc' );
			echo "<h1>$title</h1>";

			$view = self::create();
			$view->render_form_contents();

		echo '</div>';

	} // function


} // class