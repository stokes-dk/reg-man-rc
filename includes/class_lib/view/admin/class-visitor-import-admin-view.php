<?php
namespace Reg_Man_RC\View\Admin;

use Reg_Man_RC\Model\Visitor;
use Reg_Man_RC\Control\Admin\Visitor_Import_Admin_Controller;
use Reg_Man_RC\Control\Scripts_And_Styles;
use Reg_Man_RC\View\Ajax_Form;
use Reg_Man_RC\View\Form_Input_List;
use Reg_Man_RC\Model\Error_Log;

/**
 * The administrative view for Visitor importing
 *
 * @since	v0.1.0
 *
 */
class Visitor_Import_Admin_View {

	const PAGE_SLUG = Visitor::POST_TYPE . '-import';

	private $import_file_attachment_id;

	private function __construct() {
	} // function

	public static function create() {
		$result = new self();
		return $result;
	} // function

	private function get_import_file_attachment_id() {
		return $this->import_file_attachment_id;
	} // endif

	public function set_import_file_attachment_id( $import_file_attachment_id ) {
		$this->import_file_attachment_id = $import_file_attachment_id;
	} // endif

	public static function register() {

		if ( is_admin() && Visitor_Import_Admin_Controller::current_user_can_import_visitors() ) {

			// Register my importer
			add_action( 'admin_init', array( __CLASS__, 'register_importer' ) );

			// Register the hook to enqueue scripts and styles as necessary
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'handle_admin_enqueue_scripts' ) );

		} // endif

	} // function

	public static function register_importer() {

		// I need to include the base import function from wordpress
		require_once ABSPATH . 'wp-admin/includes/import.php';

		$id = Visitor_Import_Admin_Controller::IMPORTER_ID;
		$name = __( 'Repair Café Visitors', 'reg-man-rc' );
		$desc = __( 'Import repair café visitors from a CSV file.', 'reg-man-rc' );
		$view_class = Visitor_Import_Admin_View::class;
		$callback = array( $view_class, 'render_tool_import_page' );
		register_importer( $id, $name, $desc, $callback );

	} // function

	public function render_form_contents() {

		$input_list = Form_Input_List::create();

		$attachment_id = $this->get_import_file_attachment_id();
		$attachment_file = ! empty( $attachment_id ) ? get_attached_file( $attachment_id ) : NULL;

		if ( empty( $attachment_file ) ) {

			$label = '';
			$desc = __( 'Use this tool to import visitor data from a CSV file.', 'reg-man-rc' );
			$input_list->add_information( $label, $desc );

			$label = __( 'The file must contain the following column headings:', 'reg-man-rc' );
			// NOTE that the column names are always in english and not to be translated
			$desc = 'Email, First Name, Last Name, Join Mail List?, First Event Key';
			$input_list->add_information( $label, $desc );

			$label = '';
			$desc = __( 'Column values are separated by a comma and optionally enclosed in double quotes.', 'reg-man-rc' );
			$input_list->add_information( $label, $desc );

			$label = '';
			$desc = __( 'For the "Join Mail List?" column use "true", "yes" or "1" for TRUE, anything else for FALSE.', 'reg-man-rc' );
			$input_list->add_information( $label, $desc );

			$label = __( 'Visitor CSV import file', 'reg-man-rc' );
			$name = 'visitor-import-file-name';
			$hint = '';
			$classes = '';
			$accept = '.csv';
			$is_required = TRUE;
			$input_list->add_file_input( $label, $name, $hint, $classes, $is_required, $accept );

			$button_text = __( 'Continue', 'reg-man-rc' );

		} else {
			$name = 'visitor-import-attachment-id';
			$value = $attachment_id;
			$input_list->add_hidden_input( $name, $value );

			$label = __( 'The following file was successfully uploaded', 'reg-man-rc' );
			$file_name = basename( $attachment_file );
			$value = "<span>$file_name</span>";
			$input_list->add_information( $label, $value );

			$label = '';
			$desc = __( 'Press the button below to import the records.', 'reg-man-rc' );
			$input_list->add_information( $label, $desc );

			$button_text = __( 'Import Visitors', 'reg-man-rc' );

		} // endif

		$label = $button_text;
		$type = 'submit';
		$classes = 'button';
		$input_list->add_form_button( $label, $type, $classes );

		$action = Visitor_Import_Admin_Controller::AJAX_ACTION;
		$method = 'POST';
		$classes = 'reg-man-rc-import-form reg-man-rc-visitor-import-form';
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
				( isset( $_REQUEST[ 'import' ] ) && ( $_REQUEST[ 'import' ]  == Visitor_Import_Admin_Controller::IMPORTER_ID ) ) ) {
			Scripts_And_Styles::enqueue_base_admin_script_and_styles();
			Scripts_And_Styles::enqueue_ajax_forms();
		} // endif
	} // function

	/**
	 * Render the page linked from the Tools > Import page.
	 *
	 */
	public static function render_tool_import_page() {

		$title = __( 'Import Repair Café Visitor Data', 'reg-man-rc' );

		echo '<div class="wrap">';

			echo "<h1>$title</h1>";

			$view = self::create();
			$view->render_form_contents();

		echo '</div>';

	} // function


} // class