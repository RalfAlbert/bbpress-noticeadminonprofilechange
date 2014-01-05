<?php
/**
 * Concrete implementation of the abstract class MenuPage_SAPI
 * @author Ralf Albert
 *
 */

require_once 'menupage_sapi.php';

if ( ! class_exists( 'NoticeAdminOnProfileChange_MenuPage' ) ) {

class NoticeAdminOnProfileChange_MenuPage extends MenuPage_SAPI
{

	/**
	 * Key for the data in the options table
	 * @var string
	 */
	const OPTION_KEY = 'noticeadminonprofilechange';

	/**
	 * Option Name
	 * @var string
	 */
	public $option_name = '';

	/**
	 * Option Group
	 * @var string
	 */
	public $option_group = '';

	/**
	 * Array with options used by methods
	 */
	protected $options = array();

	/**
	 * The default Options
	 * @var array
	 */
	public static $default_options = array();

	/**
	 * Menu Slug
	 * @var string
	 */
	public $menu_slug  = 'noticeadminonprofilechange';

	/**
	 * Menu Title
	 * @var string
	 */
	public $menu_title = '';

	/**
	 * Page Title
	 * @var string
	 */
	public $page_title = '';

	/**
	 * Callback to render the page content
	 * @var array|string
	 */
	public $page_callback;

	/**
	 * Callback to validate and sanitize the saved options
	 * @var array|string
	 */
	public $validate_callback;

	/**
	 * Constructor setup the class vars
	 */
	public function __construct() {

		$this->menu_title = $this->page_title = __( 'Notice On Profile Change', 'noticeadminonprofilechange' );

		$this->option_group = self::OPTION_KEY;
		$this->option_name  = self::OPTION_KEY;
		$this->options      = $this->get_sanitized_options();

		$this->page_callback     = array( $this, 'pagecontent_callback' );
		$this->validate_callback = array ( $this, 'validate_callback' );

		// the sections
		$this->sections = array (
			// section-id => title, callback
			'section_mail' => array (
				'title' => __( 'Mail Settings', 'noticeadminonprofilechange' ),
// 				'callback' => 'section_mail'
			)
		);

		// fields for the sections
		$this->fields = array (
			// field-id => in-section, title, callback
			'field_1' => array (
				'section' => 'section_mail',
				'title' => __( 'Mail Adress', 'noticeadminonprofilechange' ),
				'callback' => 'mail_field'
			),

			'field_2' => array (
				'section' => 'section_mail',
				'title' => __( 'Subject', 'noticeadminonprofilechange' ),
				'callback' => 'subject_field'
			),

			'field_3' => array (
					'section' => 'section_mail',
					'title' => __( 'Send From Header', 'noticeadminonprofilechange' ),
					'callback' => 'sendfrom_field'
			),

			'field_4' => array (
					'section' => 'section_mail',
					'title' => __( 'Mail CC', 'noticeadminonprofilechange' ),
					'callback' => 'cc_field'
			),

			'field_5' => array (
					'section' => 'section_mail',
					'title' => __( 'Mail BCC', 'noticeadminonprofilechange' ),
					'callback' => 'bcc_field'
			),

		);

		parent::__construct();

	}

	/**
	 * Validate saved options
	 *
	 * @param array $input Options send
	 * @return array $input Validated options
	 */
	public function validate_callback( $input ) {

		$output = array();

		$input = array_merge( self::get_default_options(), $input );

		$output['mail_to']              = filter_var( $input['mail_to'], FILTER_SANITIZE_EMAIL );
		$output['mail_subject']         = filter_var( $input['mail_subject'], FILTER_SANITIZE_STRING );
		$output['sendfrom_header_name'] = filter_var( $input['sendfrom_header_name'], FILTER_SANITIZE_STRING );
		$output['sendfrom_header_mail'] = filter_var( $input['sendfrom_header_mail'], FILTER_SANITIZE_STRING );
		$output['use_sendfrom_header']  = filter_var( $input['use_sendfrom_header'], FILTER_VALIDATE_BOOLEAN );
		$output['mail_cc']              = filter_var( $input['mail_cc'], FILTER_SANITIZE_STRING );
		$output['mail_bcc']             = filter_var( $input['mail_bcc'], FILTER_SANITIZE_STRING );

		$output['mail_cc']  = explode( "\r\n", $output['mail_cc'] );
		$output['mail_bcc'] = explode( "\r\n", $output['mail_bcc'] );

		return $output;

	}

	/**
	 * Returns the default options
	 *
	 * @return array
	 */
	public static function get_default_options() {

		return array(
				'mail_to'      => get_option( 'admin_email' ),
				'mail_subject' => __( 'Profile from user {user} was changed', 'noticeadminonprofilechange' ),

				'sendfrom_header_name' => '',
				'sendfrom_header_mail' => get_option( 'admin_email' ),
				'use_sendfrom_header'  => false,

				'mail_cc'  => array(),
				'mail_bcc' => array(),
		);

	}

	/**
	 * Helper function to create name and id attribute for input fields
	 *
	 * @param string $name Name of the field
	 * @return string
	 */
	public function get_name_arg( $name='' ) {
		return sprintf( ' name="%1$s[%2$s]" id="%1$s-%2$s"', $this->option_name, $name );
	}

	/**
	 * Helper function to create a label tag
	 *
	 * @param string $name Name of the label
	 * @return string
	 */
	public function get_label( $name='' ) {
		return sprintf( '<label for="%s-%s">', $this->option_name, $name );
	}

	/**
	 * Get an array with valid options
	 *
	 * @return array
	 */
	public function get_sanitized_options() {
		return wp_parse_args( (array) get_option( $this->option_name ), self::get_default_options() );
	}

	/**
	 * Returns an option value and convert it into the requested type
	 *
	 * @param string $name	Name of the option
	 * @param string $type	Type to convert it into
	 * @return mixed	Depending on the convertion
	 */
	public function get_sanitized_optval( $name = '', $type = '' ) {

		$value = isset( $this->options[ $name ] ) ?
			$this->options[ $name ] : '';

		switch ( strtolower( $type ) ) {

			case 'string':
		 		$value = (string) $value;
		 	break;

			case 'bool':
			case 'boolean':
				$value = (bool) $value;
			break;

			case 'array':
				$value = (array) $value;
			break;

			case 'int':
			case 'integer':
				$value = (int) $value;
			break;

			default:
				$value = $value;
			break;

		}

		return $value;

	}

	/**
	 * Outputs the main content
	 * @see MenuPage_SAPI::main_frame()
	 */
	public function pagecontent_callback() {

		if( ! current_user_can( 'manage_options' ) )
			return;

		echo '<div class="wrap">';
		printf( '<h1>%s</h1>', esc_html( __( 'Notice Admin On Profile Change', 'noticeadminonprofilechange' ) ) );
		echo '<form action="options.php" method="post">';

		settings_fields( $this->option_group );
		do_settings_sections( $this->menu_slug );

		submit_button( __( 'Save Changes', 'noticeadminonprofilechange' ), 'primary', 'submit_options', true );

		echo '</form>';
		echo '</div>';

	}

	/**
	 * Callback to render the content of the mail section
	 */
	public function section_mail() {}

	/**
	 * Callback to render the content of the mail field
	 */
	public function mail_field() {

		$name  = 'mail_to';
		$value = $this->get_sanitized_optval( $name, 'string' );

		printf(
			'<p>%s%s<br><input %s type="text" size="45" value="%s"></label></p>',
			$this->get_label( $name ),
			esc_html( __( 'Please enter the mail-adress where the report should be send to.', 'noticeadminonprofilechange' ) ),
			$this->get_name_arg( $name ),
			$value
		);

	}

	/**
	 * Callback to render the content of the subject field
	 */
	public function subject_field() {

		$name  = 'mail_subject';
		$value = $this->get_sanitized_optval( $name, 'string' );

		printf(
			'<p>%s%s<br><input %s type="text" size="80" value="%s"></label></p>',
			$this->get_label( $name ),
			esc_html( __( 'Please enter the subject for the mails. Use {user} as placeholder for the username from the changed profile.', 'noticeadminonprofilechange' ) ),
			$this->get_name_arg( $name ),
			$value
		);

	}

	/**
	 * Callback to render the content of the subject field
	 */
	public function sendfrom_field() {

		$name  = 'use_sendfrom_header';
		$value = $this->get_sanitized_optval( $name, 'bool' );

		$checked = checked( true, $value, false );

		printf(
			'<p>%s<input %s type="checkbox" %s /> %s</label></p>',
			$this->get_label( $name ),
			$this->get_name_arg( $name ),
			$checked,
			esc_html( __( 'Use a send-from header', 'noticeadminonprofilechange' ) )
		);

		$name  = 'sendfrom_header_name';
		$value = $this->get_sanitized_optval( $name, 'string' );

		printf(
			'<p>%s%s<br><input %s type="text" size="60" value="%s"></label></p>',
			$this->get_label( $name ),
			esc_html( __( 'Enter the name of the sender if you wish to use a send-from header. This field is optional, you can leave it blank.', 'noticeadminonprofilechange' ) ),
			$this->get_name_arg( $name ),
			$value
		);

		$name  = 'sendfrom_header_mail';
		$value = $this->get_sanitized_optval( $name, 'string' );

		printf(
			'<p>%s%s<br><input %s type="text" size="60" value="%s"></label></p>',
			$this->get_label( $name ),
			esc_html( __( 'Enter the mail of the sender if you wish to use a send-from header. If this field is empty, the admin email will be used.', 'noticeadminonprofilechange' ) ),
			$this->get_name_arg( $name ),
			$value
		);

	}

	/**
	 * Callback to render the content of the CC field
	 */
	public function cc_field() {

		$name  = 'mail_cc';
		$value = $this->get_sanitized_optval( $name, 'array' );
		$value = implode( "\r\n", $value );

		printf(
			'%s%s<br><textarea %s cols="80" rows="5">%s</textarea></label>',
			$this->get_label( $name ),
			esc_html( __( 'If you wish to send the report as a CC, enter the adresses here. One adress per line!', 'noticeadminonprofilechange' ) ),
			$this->get_name_arg( $name ),
			$value
		);


	}

	/**
	 * Callback to render the content of the BCC field
	 */
	public function bcc_field() {

		$name  = 'mail_bcc';
		$value = $this->get_sanitized_optval( $name, 'array' );
		$value = implode( "\r\n", $value );

		printf(
			'%s%s<br><textarea %s cols="80" rows="5">%s</textarea></label>',
			$this->get_label( $name ),
			esc_html( __( 'If you wish to send the report as a BCC, enter the adresses here. One adress per line!', 'noticeadminonprofilechange' ) ),
			$this->get_name_arg( $name ),
			$value
		);


	}

}

}
