<?php
/**
 * SendMail
 *
 * Class to sending a report about changed fields when a user profile was updated
 *
 * @author Ralf Albert
 *
 */
( ! defined( 'ABSPATH' ) ) AND die( 'Standing OPn The Shoulders Of Giants' );

if ( ! class_exists( 'NoticeAdminOnProfileChange_SendMail' ) ) {

class NoticeAdminOnProfileChange_SendMail
{

	/**
	 * Container for the menupage object. This object is a connection
	 * to the options stored in the database
	 * @var object
	 */
	public  $menupageobject = null;

	/**
	 * Var for used textdomain
	 * @var string
	 */
	public $textdomain = '';

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
	 * Directory path to the templates
	 * @var string
	 */
	protected $template_dir = '';

	/**
	 * Available template files
	 * @var array
	 */
	protected $template_files = array();

	/**
	 * The constructor setup the internal user object and the mail-templates
	 */
	public function __construct() {

		$this->temp_csv_file  = WP_CONTENT_DIR . '/adminnotice_csv.csv';
		$this->template_dir   = dirname( dirname( __FILE__ ) ) . '/mail_templates';
		$this->template_files = $this->setup_template_files();

		/*
		 * How to setup new delimiters for internal sprintf() function
		 */
// 		add_filter( 'noticeadminonprofilechange_set_delimiters', function( $delimiters ) { return array( 'start_del' => '[[', 'end_del' => ']]' ); }, 0, 1 );

	}

	/**
	 * Setup the user and mail_body
	 *
	 * @param	integer|object	$user	Depending on the hook (wordpress standard or bbPress xprofile)
	 */
	public function init( $user = null ) {

		$this->setup_user( $user );

		if ( isset( $this->template_files->mail_body ) && ! empty( $this->template_files->mail_body ) ) {

			$values = new stdClass();
			$values->_name_     = __( 'Name', $this->textdomain );
			$values->_email_    = __( 'Email', $this->textdomain );
			$values->_headline_ = __( 'changed this fields', $this->textdomain );
			$values->user_name  = $this->user->name;
			$values->user_email = $this->user->email;

			$this->mail_body = $this->sprintf( $this->template_files->mail_body, $values );

		} else {

			$this->mail_body  = sprintf( '%s: %s', __( 'Name', $this->textdomain ), $this->user->name );
			$this->mail_body .= sprintf( '%s: %s', __( 'Email', $this->textdomain ), $this->user->email );
			$this->mail_body .= __( 'changed this fields', $this->textdomain );
			$this->mail_body .= sprintf( "\r\n%s\r\n", str_repeat( '=', 80 ) );
			$this->mail_body .= '{body}';

		}

	}

	/**
	 * Creates and send the report
	 *
	 * @param		array		$data		Array with the old, new and changed values
	 * @return	boolean					False if no changed data was found
	 */
	public function send( $data ) {

		// no changed or new data to process
		if ( empty( $data['changed'] ) && empty( $data['new'] ) )
			return false;

		$this->setup_maildata();

		$message  = $this->sprintf(
				$this->mail_body,
				array(
						'table' => $this->get_mail_body_table( $data )
				)
		);

		$this->get_attachment_csv( $data );

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
	 *
	 * @param		array		$data		Array with field values
	 * @return	string					ASCII table with field names and new values
	 */
	protected function get_mail_body_table( $data ) {

		$data_to_send = $data_to_send = $this->merge_deep( $data, array( 'new', 'changed' ) ); //array_merge( $data['new'], $data['changed'] );

		if ( isset( $this->template_files->mail_table ) && ! empty( $this->template_files->mail_table ) ) {

			$template = $this->template_files->mail_table;
			$pattern  = '#\[loop\](.+)\[/loop\]#is';

			preg_match( $pattern, $template, $result );
			$head = preg_replace( $pattern, '', $template );

			if ( is_array( $result ) && isset( $result[1] ) ) {

				$row = $result[1];
				unset( $result );

			} else  {

				$row = '{{filed}}|{{value}}';

			}

			$out = '';

			foreach ( $data_to_send as $group_name => $group ) {

				$values = new stdClass();
				$values->_group_ = __( 'Group', $this->textdomain );
				$values->group   = $group_name;

				$out .= $this->sprintf( $head, $values );

				foreach ( $group as $field => $value ) {

					$values = new stdClass();
					$values->field = str_pad( $field, 39, ' ');
					$values->value = $value;

					$out .= $this->sprintf( $row, $values );

				}

			}

		} else {

			$out = '';

			foreach ( $data_to_send as $group_name => $group ) {

				$out .= sprintf(
						"%s: %s\r\n%s\r\n",
						__( 'Group', $this->textdomain ),
						$group_name,
						str_repeat( '-', 80 )
				);

				foreach ( $group as $field => $value )
					$out .= sprintf( "%s| %s\r\n", str_pad( $field, 39, ' '), $value );
			}

		}

		return $out;

	}

	/**
	 * Creates the csv-file attachment
	 *
	 * @param		array		$data		Array with changed fields/values
	 */
	protected function get_attachment_csv( $data ) {

		$data_to_send = $this->merge_deep( $data, array( 'new', 'changed' ) ); //array_merge( $data['new'], $data['changed'] );

		$fp = fopen( $this->temp_csv_file, 'w+' );

		if ( true == $fp ) {

			fwrite(
				$fp,
				sprintf(
					"\"%s\":,\"%s\"\r\n\"%s\":,\"%s\"\r\n",
					__( 'User', $this->textdomain),
					addslashes( $this->user->name ),
					__( 'Email', $this->textdomain ),
					addslashes( $this->user->email )
				)
			);

			/*
			 * field names and values are enclosed in double quotes and sanitized with addslashes()
			 * if a field name or value contain an comma.
			 */
			foreach ( $data_to_send as $group_name => $group ) {

				fwrite(
					$fp,
					sprintf(
						"\"%s\":,\"%s\"\r\n",
						__( 'Group', $this->textdomain),
						addslashes( $group_name )
					)
				);

				foreach( $group as $field => $value )
					fwrite(
						$fp,
						sprintf(
							"\"%s\",\"%s\"\r\n",
							addslashes( $field ),
							addslashes( $value )
						)
					);

			} // end outer foreach

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
				$userdata->data->user_login : __( 'Unknown', $this->textdomain );

			$this->user->email = ( property_exists( $userdata->data, 'user_email' ) ) ?
				$userdata->data->user_email : __( 'no-email@example.com', $this->textdomain );

			$this->user->dname = ( property_exists( $userdata->data, 'display_name' ) ) ?
				$userdata->data->display_name : __( 'Unknown', $this->textdomain );

		} else {

			$this->user->name  = __( 'Unknown', $this->textdomain );
			$this->user->email = __( 'no-email@example.com', $this->textdomain );
		}

	}

	/**
	 * Create an object with filename (w/o file extension) => filepath
	 * @return object
	 */
	protected function setup_template_files() {

		$files = glob( $this->template_dir . '/*.txt' );

		if ( is_array( $files ) && ! empty( $files ) ) {

			foreach ( $files as $key => $file ) {
				$new_key = str_replace( '.txt', '', basename( $file ) );
				$files[ $new_key ] = file_get_contents( $file );
				unset( $files[ $key ] );
			}

		} else {

			$files = new stdClass();

		}

		$this->template_files = (object) $files;

		unset( $files );

		return $this->template_files;

	}

	/**
	 * Setup the data for sending the mail
	 */
	protected function setup_maildata() {

		if ( ! is_object( $this->menupageobject ) || 'NoticeAdminOnProfileChange_MenuPage' != get_class( $this->menupageobject ) ) {

			$this->mail_to      = get_option( 'admin_email' );

			$subject_pattern = str_replace(
					array( '{', '}' ),
					array( '{{', '}}' ),
					__( 'Profile from user {user} was changed', $this->textdomain )
			);

			$this->mail_subject = $this->sprintf(
					$subject_pattern,
					array(
							'user'         => $this->user->name
					)
			);

			$this->mail_headers = false;

		} else {

			$mpo = $this->menupageobject;
			$this->mail_headers = array();

			// create mail-to and subject
			$this->mail_to = $mpo->get_sanitized_optval( 'mail_to' );

			$this->mail_subject = $this->sprintf(
					str_replace( array( '{', '}' ), array( '{{', '}}' ), $mpo->get_sanitized_optval( 'mail_subject' ) ),
					array(
							'user'         => $this->user->name,
							'display_name' => $this->user->dname
					)
			);

			// create the send-from header
			if ( true == $mpo->get_sanitized_optval( 'use_sendfrom_header' ) ) {

				$name = $mpo->get_sanitized_optval( 'sendfrom_header_name' );
				$mail = $mpo->get_sanitized_optval( 'sendfrom_header_mail' );

				if ( empty( $mail ) )
					$mail = get_option( 'admin_email' );

				if ( ! empty( $name ) && ! empty( $mail ) )
					$pat = 'From: {{name}} <{{mail}}>';
				elseif( empty( $name ) && ! empty( $mail ) )
					$pat = 'From: {{mail}}';
				else
					$pat = '';

				if ( ! empty( $pat ) ) {

					$this->mail_headers[] = $this->sprintf(
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

						$this->mail_headers[] = $this->sprintf(
								'{{proto}}: {{mail}}',
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

	/**
	 *
	 * Replacing values in a format-string
	 * @param string $format
	 * @param array|object $values
	 * @throws Exception
	 * @return string|bool	Returns the formated string or FALSE on failure
	 */
	protected function sprintf( $format = '', $values = NULL, $delimiters = array() ) {

		$delimiters = array_merge(
			array( 'start_del' => '{{', 'end_del' => '}}' ),
			apply_filters( 'noticeadminonprofilechange_set_delimiters', $delimiters )
		);

		extract( $delimiters );

		/*
		 * Do the replacement
		 */
		foreach ( $values as $key => $value ) {

			$matches	= array();
			$search_key	= sprintf( '%s%s%s', $start_del, $key, $end_del );
			$pattern	= sprintf( '/%%%s\[(.*)\]%%/iU', $key );

			// search for the values in format-string. find %key% or %key[format]%
			preg_match_all( $pattern, $format, $matches );

			// the '[format]' part was not found. replace only the key with the value
			if ( empty( $matches[1] ) ) {
				$format = str_replace( $search_key, $value, $format );
			}
			// one or more keys with a '[format]' part was found.
			// walk over the formats and replace the key with a formated value
			else {

				foreach ( $matches[1] as $match ) {
					$replace = sprintf( '%' . $match, $value );
					$search = sprintf( '%s%s[%s]%s', $start_del, $key, $match, $end_del );
					$format = str_replace( $search, $replace, $format );
				}
			}
		}

		// return the formatted string
		return $format;

	}

	/**
	 * Merges two assozitive arrays, keeps the indexes
	 *
	 * @param		array		$data			Array with data
	 * @param		array		$indexes	Array with indexes to merge
	 * @return	array		$new_data	Merged arrays
	 */
	protected function merge_deep( $data, $indexes ) {

		$new_data = array();

		foreach ( $indexes as $index ) {

			foreach ( $data[ $index ] as $key => $fields ) {

				if ( ! key_exists( $key, $new_data ) )
					$new_data[ $key ] = $fields;

				if ( key_exists( $key, $new_data ) )
					$new_data[ $key ] = array_merge( $new_data[ $key ], $fields );

			}

		}

		return  $new_data;

	}

}

}