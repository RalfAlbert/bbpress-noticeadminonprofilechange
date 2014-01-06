<?php
/**
 * SendMail
 *
 * Class to sending a report about changed fields when a user profile was updated
 *
 * @author Ralf Albert
 *
 */

if ( ! class_exists( 'NoticeAdminOnProfileChange_SendMail' ) ) {

class NoticeAdminOnProfileChange_SendMail
{

	/**
	 * Formatter object
	 * @var object
	 */
	protected $formatter = null;

	/**
	 * Container for the menupage object. This object is a connection
	 * to the options stored in the database
	 * @var object
	 */
	public $menupageobject = null;

	/**
	 * User object
	 * @var object
	 */
	protected $user = null;

	/**
	 * Email where the report will be send to
	 * @var string
	 */
	public $mail_to = '';

	/**
	 * Template for the mail subject
	 * @var string
	 */
	public $mail_subject = '';

	/**
	 * Additional headers to send
	 * @var string
	 */
	public $mail_headers = '';

	/**
	 * Template for the mail body
	 * @var string
	 */
	public $mail_body = '';

	/**
	 * The data as temporary csv-file
	 * @var string
	 */
	public $temp_csv_file = '';

	/**
	 * The constructor setup the internal user object and the mail-templates
	 *
	 * @param	integer|object	$user	Depending on the hook (wordpress standard or bbPress xprofile)
	 */
	public function __construct( $user = null ) {

		$this->formatter = new Formatter();
		$this->formatter->set_delimiter( '{', '}' );

		$this->setup_user( $user );

		$this->mail_body  = __( 'Changed fields:', 'noticeadminonprofilechange' );
		$this->mail_body .= sprintf( "\n%s\n", str_repeat( '-', 80 ) );
		$this->mail_body .= '{changed}';

		$this->temp_csv_file = WP_CONTENT_DIR . '/adminnotice_csv_temp.csv';

	}

	/**
	 * Creates and send the report
	 *
	 * @param		array		$data		Array with the old, new and changed values
	 * @return	boolean					False if no changed data was found
	 */
	public function send( $data ) {

		if ( empty( $data['changed'] ) )
			return false;

		$this->setup_maildata();

		$message  = $this->formatter->sprintf(
				$this->mail_body,
				array(
						'changed' => $this->get_changed_values_table( $data['changed'] )
				)
		);

		$this->get_attachment_csv( $data['changed'] );

		$fileexists = file_exists( $this->temp_csv_file );

		$attachment = ( true == $fileexists ) ? $this->temp_csv_file : false;

		wp_mail( $this->mail_to, $this->mail_subject, $message, $this->mail_headers, $attachment );

		// remove the filter (set in setup_maildata) even if it wasn't set
		remove_filter( 'wp_mail_from_name', array( $this, 'set_correct_from_header' ), 99 );
		remove_filter( 'wp_mail_from', array( $this, 'set_correct_from_mail' ), 99 );

		if ( $fileexists )
			unlink( $this->temp_csv_file );

	}

	/**
	 * Creates an ascii table with the changed data (field name | new value)
	 * Also creates the csv attachment
	 *
	 * @param		array		$data		Array with field values
	 * @return	string					ASCII table with field names and new values
	 */
	protected function get_changed_values_table( $data ) {

		$out = '';

		foreach ( $data as $field => $value )
			$out .= sprintf( "%s| %s\r\n", str_pad( $field, 39, ' '), $value );

		return $out;

	}

	/**
	 * Creates the csv-file attachment
	 *
	 * @param		array		$data		Array with changed fields/values
	 */
	protected function get_attachment_csv( $data ) {

		$fp = fopen( $this->temp_csv_file, 'w+' );

		if ( true == $fp ) {

			/*
			 * field names and values are enclosed in double quotes and sanitized with addslashes()
			 * if a field name or value contain an comma.
			 */
			foreach( $data as $field => $value )
				fwrite( $fp, sprintf( "\"%s\",\"%s\"\r\n", addslashes( $field ), addslashes( $value ) ) );

			fclose( $fp );

		}

	}

	/**
	 * Setup the internal user object. Extract the user id from an bbPress user object or
	 * copy a given user id (integer)
	 *
	 * @param	object|integer	$user		The user object or user id
	 */
	protected function setup_user( $user = null ) {

		$this->user = new stdClass();

		if ( is_object( $user ) && property_exists( $user, 'user_id' ) )
			$this->user->id = (int) $user->user_id;
		else
			$this->user->id = ( int ) $user;

		$userdata = get_userdata( $this->user->id );

		if ( property_exists( $userdata, 'data' ) ) {
			$this->user->name = ( property_exists( $userdata->data, 'user_login' ) ) ?
				$userdata->data->user_login : __( 'Unknown', 'noticeadminonprofilechange' );
		} else {
			$this->user->name = __( 'Unknown', 'noticeadminonprofilechange' );
		}

	}

	/**
	 * Setup the data for sending the mail
	 */
	protected function setup_maildata() {

		if ( ! is_object( $this->menupageobject ) || 'NoticeAdminOnProfileChange_MenuPage' != get_class( $this->menupageobject ) ) {

			$this->mail_to      = get_option( 'admin_email' );
			$this->mail_subject = $this->formatter->sprintf(
					__( 'Profile from user {user} was changed', 'noticeadminonprofilechange' ),
					array(
							'user' => $this->user->name
					)
			);
			$this->mail_headers = false;

		} else {

			$mpo = $this->menupageobject;
			$this->mail_headers = array();

			// create mail-to and subject
			$this->mail_to = $mpo->get_sanitized_optval( 'mail_to' );

			$this->mail_subject = $this->formatter->sprintf(
					$mpo->get_sanitized_optval( 'mail_subject' ),
					array(
							'user' => $this->user->name
					)
			);

			// create the send-from header
			if ( true == $mpo->get_sanitized_optval( 'use_sendfrom_header' ) ) {

				$name = $mpo->get_sanitized_optval( 'sendfrom_header_name' );
				$mail = $mpo->get_sanitized_optval( 'sendfrom_header_mail' );

				if ( empty( $mail ) )
					$mail = get_option( 'admin_email' );

				if ( ! empty( $name ) && ! empty( $mail ) )
					$pat = 'From: {name} <{mail}>';
				elseif( empty( $name ) && ! empty( $mail ) )
					$pat = 'From: {mail}';
				else
					$pat = '';

				if ( ! empty( $pat ) ) {

					$this->mail_headers[] = $this->formatter->sprintf(
							$pat,
							array(
									'name' => $name,
									'mail' => $mail
							)
					);

					/*
					 * Plugins can override the from-name used in the from-header and some plugins like
					 * BuddyPress will do so.
					 * Idiots! Why should I set the from header when a plugin can override it???
					 */
					add_filter( 'wp_mail_from_name', array( $this, 'set_correct_from_header' ), 99, 1 );
					add_filter( 'wp_mail_from', array( $this, 'set_correct_from_mail' ), 99, 1 );

				}

			}

			// insert CC and BCC to the headers
			foreach ( array( 'mail_cc' => 'Cc', 'mail_bcc' => 'Bcc' ) as $opt => $proto) {

				$carbon = $mpo->get_sanitized_optval( $opt );

				if ( is_array( $carbon ) && ! empty( $carbon ) ) {

					foreach ( $carbon as $mail ) {

						$this->mail_headers[] = $this->formatter->sprintf(
								'{proto}: {mail}',
								array(
										'proto' => $proto,
										'mail'  => $mail
								)
						);

					} // end inner foreach

				} // endif

			} // end outer foreach

			// if no headers was set, set the headers to false
			if ( empty( $this->mail_headers ) )
				$this->mail_headers = false;

		} // end elseif

	}

	/**
	 * Callback to set the correct From-Header name.
	 *
	 * @param		string	$name Name to insert into the from-header
	 * @return	string				If it was set, the From-Header name. Else the name passed to the callback
	 */
	public function set_correct_from_header( $name ) {

		$sendfrom = $this->menupageobject->get_sanitized_optval( 'sendfrom_header_name' );

		return ( ! empty( $sendfrom ) ) ?
			$sendfrom : $name;

	}

	/**
	 * Callback to set the correct From-Header name.
	 *
	 * @param		string	$mail Mail to insert into the from-header
	 * @return	string				If it was set, the From-Header mail. Else the mail passed to the callback
	 */
	public function set_correct_from_mail( $mail ) {

		$sendfrom = $this->menupageobject->get_sanitized_optval( 'sendfrom_header_mail' );

		return ( ! empty( $sendfrom ) ) ?
			$sendfrom : $mail;

	}

}

}