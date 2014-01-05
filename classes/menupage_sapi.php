<?php
/**
 * MenuPage_SAPI
 * @author Ralf Albert
 *
 * Class using the Settings API (SAPI) to create a menupage
 *
 */

if ( ! class_exists( 'MenuPage_SAPI' ) ) {

abstract class MenuPage_SAPI
{

	/**
	 * Array for sections
	 * @var array
	 */
	public $sections = array();

	/**
	 * Array for fields
	 * @var array
	 */
	public $fields = array();

	/**
	 * Option Group
	 * @var string
	 */
	public $option_group = '';

	/**
	 * Option Name
	 * @var string
	 */
	public $option_name = '';

	/**
	 * Callback to render the main page content
	 * @var array|string
	 */
	public $page_callback = null;

	/**
	 * Callback for validating ( and sanitizing) the options
	 * @var array|string
	 */
	public $validate_callback = null;

	/**
	 * The slug for the menu page
	 * @var string
	 */
	public $menu_slug  = '';

	/**
	 * The menu title
	 * @var string
	 */
	public $menu_title = 'Empty Menu Title';

	/**
	 * The page title
	 * @var string
	 */
	public $page_title = 'Empty Page Title';

	/**
	 * Capabilities a user must have to see the page
	 * @var unknown
	 */
	public $capabilities = 'manage_options';

	/**
	 * Pagehook of the menu page
	 * @var string
	 */
	public $pagehook = '';

	/**
	 * The constructor add the needed hooks
	 */
	public function __construct() {

		if ( true == $this->check_settings() ) {

			add_action( 'admin_init', array( $this, 'settings_api_init' ), 1, 0 );
			add_action( 'admin_menu', array( $this, 'add_menu_page' ), 10, 0 );

		}

	}

	/**
	 * Checks if everything was correctly setup
	 * @return boolean True on success, false on error
	 */
	private function check_settings() {

		$success = true;

		if ( empty( $this->page_callback ) ) {
			trigger_error( 'The page callback can not be empty!', E_USER_WARNING );
			$success = false;
		}

		if ( empty( $this->option_name ) ) {
			trigger_error( 'The options-name can not be empty!', E_USER_WARNING );
			$success = false;
		}

		// create a random menu slug if it is not set
		if ( empty( $this->menu_slug ) )
			$this->menu_slug = 'empty-menu-slug' . rand( 0, 999 );

		return $success;

	}

	/**
	 * Initialise the WordPress Settings-API
	 * - Register the settings
	 * - Register the sections
	 * - Register the fields for each section
	 */
	public function settings_api_init() {

		// register settings
		register_setting(
			$this->option_group,
			$this->option_name,
			$this->validate_callback
		);

		// register each section
		$number = 1;
		foreach ( $this->sections as $id => $args ) {

			$title = isset( $args['title'] ) ?
				esc_html( $args['title'] ) : __( 'Section #', 'textdomain' ) . $number;

			$callback =  isset( $args['callback'] ) ?
				array ( $this, $args['callback'] ) : '';

			add_settings_section(
				$id,
				$title,
				$callback,
				$this->menu_slug
			);

			$number++;
		}

		// register each field in it's section
		$number = 1;
		foreach ( $this->fields as $id => $args ) {

			if ( ! isset( $args['section'] ) || ! key_exists( $args['section'], $this->sections ) )
				continue;
			else
				$section = $args['section'];

			$title = isset( $args['title'] ) ?
				esc_html( $args['title'] ) : __( 'Section #', 'textdomain' ) . $number;

			$callback =  isset( $args['callback'] ) ?
				array ( $this, $args['callback'] ) : '';

			add_settings_field(
				$id,
				$title,
				$callback,
				$this->menu_slug,
				$section
			);

			$number++;
		}

	}

	/**
	 * Add a page to the dashboard-menu
	 */
	public function add_menu_page() {

		if ( ! current_user_can( $this->capabilities ) )
			return false;

		$this->pagehook = add_options_page(
				$this->page_title,
				$this->menu_title,
				$this->capabilities,
				$this->menu_slug,
				$this->page_callback
		);

	}

	/**
	 * Validate saved options
	 *
	 * @param array $input Options send
	 * @return array $input Validated options
	 */
	abstract public function validate_callback( $input );

	/**
	 * Outputs the main content
	 */
	abstract public function pagecontent_callback();

}

}