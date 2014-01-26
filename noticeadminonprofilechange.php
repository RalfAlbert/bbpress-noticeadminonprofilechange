<?php
/**
 * WordPress/BuddyPress-Plugin Notice Admin On Profile Change
 *
 * PHP version 5.2
 *
 * @category   PHP
 * @package    WordPress
 * @subpackage BuddyPress
 * @author     Ralf Albert <me@neun12.de>
 * @license    GPLv3 http://www.gnu.org/licenses/gpl-3.0.txt
 * @version    1.4.20140126
 * @link       http://wordpress.com
 */

/**
 * Plugin Name: Notice Admin On Profile Change
 * Plugin URI:  http://yoda.neun12.de
 * Text Domain: noticeadminonprofilechange
 * Domain Path: /languages
 * Description: Notices the admin via e-mail if a profile was changed. Works with the normal WordPress userprofile and the BuddyPress XProfile.
 * Author:      Ralf Albert
 * Author URI:  http://yoda.neun12.de/
 * Version:     1.4.20140126
 * License:     GPLv3
 */

( ! defined( 'ABSPATH' ) ) AND die( 'Standing OPn The Shoulders Of Giants' );

add_action( 'plugins_loaded', 'noticeadminonprofilechange_init_plugin', 10, 0 );

/**
 * Perform a basic initialization
 */
function noticeadminonprofilechange_basic_init() {

	$classes = glob( plugin_dir_path( __FILE__ ) . 'classes/*.php' );

	foreach ( $classes as $class )
		require_once $class;

	PluginHeaderReader::init( __FILE__, 'noticeadminonprofilechange' );

}

/**
 * Plugin Init
 *
 * Load classes and add hooks and filters
 */
function noticeadminonprofilechange_init_plugin() {

	noticeadminonprofilechange_basic_init();

	// load textdomain and add menupage
	add_action( 'init', 'noticeadminonprofilechange_textdomain', 10, 0 );
	add_action( 'init', 'noticeadminonprofilechange_menupage', 11, 0 );

	/*
	 * Hook into save action before any data is changed. Grab the new data from POST header
	 */
	add_action( 'bp_screens', 'noticeadminonprofilechange_on_xprofile_update', 0, 1 );

	/*
	 * Hooks for the standard user profile
	 */
	add_action( 'personal_options_update',  'noticeadminonprofilechange_on_profile_update', 10, 2 );
	add_action( 'edit_user_profile_update', 'noticeadminonprofilechange_on_profile_update', 10, 2 );

}

/**
 * Load textdomain
 */
function noticeadminonprofilechange_textdomain() {

	$pluginheaders = PluginHeaderReader::get_instance( 'noticeadminonprofilechange' );

	$loaded = load_plugin_textdomain(
		$pluginheaders->TextDomain,
		false,
		dirname( plugin_basename( __FILE__ ) ) . $pluginheaders->DomainPath
	);

	return $loaded;

}

/**
 * Adding the menupage
 * Creating a menupage object and save it for later use
 */
function noticeadminonprofilechange_menupage() {

	$pluginheaders = PluginHeaderReader::get_instance( 'noticeadminonprofilechange' );
	$pluginheaders->menupageobject = new NoticeAdminOnProfileChange_MenuPage( $pluginheaders );

}

/**
 * Callback for hook 'bp_screens'
 *
 * @param  object  $user User object provided by BuddyPress
 * @return boolean       False if the xprofile are not active
 */
function noticeadminonprofilechange_on_xprofile_update( $user ) {

	// check if Xprofile is acive
	if ( function_exists( 'bp_is_active' ) && ! bp_is_active( 'xprofile' ) )
		return false;


	$data = array( 'new' => array(), 'changed' => array(), 'deleted' => array() );

	$pluginheaders = PluginHeaderReader::get_instance( 'noticeadminonprofilechange' );
	$textdomain    = $pluginheaders->TextDomain;

	$filter  = current_filter();
	$user    = wp_get_current_user();
	$user_id = ( is_object( $user ) && property_exists( $user, 'ID' ) ) ? (int) $user->ID : 0;

	$old_data = BP_XProfile_ProfileData::get_all_for_user( $user_id );

	/*
	 *  convert the bbPress format into key-value format
	 *  bbPress saves the fields as a complex array. we need a simple format
	 *  to check if a value was changed
	 */
	foreach ( $old_data as $field_name => $value ) {

		if ( ! is_array( $value ) )
			continue;

		$field_data = key_exists( 'field_data', $value ) ? maybe_unserialize( $value['field_data'] ) : '';
		$field_id   = key_exists( 'field_id', $value ) ? (int) $value['field_id'] : 0;

		$value =  ( is_array( $field_data ) ) ?
			implode( ',', $field_data ) : $field_data;

		$data['old'][ $field_id ] = $value;

	}

	unset( $old_data );

	$field_ids = isset( $_POST['field_ids'] ) ?
		explode( ',', filter_input( INPUT_POST, 'field_ids', FILTER_SANITIZE_STRING ) ) : array();

	// compare the data send via POST header with the saved data
	if ( ! empty( $field_ids ) ) {

		foreach ( $field_ids as $id ) {

			$key      = '';
			$field    = new BP_XProfile_Field( $id );
			$field_id = 'field_' . $id;
			$group    = xprofile_get_field_group( $field->group_id );

			// get the damned dateboxes under control
			if ( 'datebox' === $field->type )
				noticeadminonprofilechange_get_datebox( $id );

			$post_val = isset( $_POST[ $field_id ] ) ? $_POST[ $field_id ] : '';

			if ( is_array( $post_val ) )
				$post_val = implode( ',', $post_val );

			// field was changed
			if ( key_exists( $id, $data['old'] ) && ( $data['old'][ $id ] != $post_val ) ) {

				// field was deleted
				if ( empty( $post_val ) ) {
					$value = __( '[Field was deleted]', $textdomain );
					$key   = 'deleted';
				} else {
					$value = $post_val;
					$key   = 'changed';
				}

			}

			// field is new (empty field was filled out)
			if ( ! key_exists( $id, $data['old'] ) && ! empty( $post_val ) ) {
				$value = $post_val;
				$key   = 'new';
			}

			if ( ! empty( $key ) )
				$data[ $key ][ $group->name ][ $field->name ] = $value;

		}

	} // endif

		noticeadminonprofilechange_sending_data( $data, $user_id );

}

function noticeadminonprofilechange_get_datebox( $field_id ) {

	if ( !isset( $_POST['field_' . $field_id] ) ) {
		if ( !empty( $_POST['field_' . $field_id . '_day'] ) && !empty( $_POST['field_' . $field_id . '_month'] ) && !empty( $_POST['field_' . $field_id . '_year'] ) )
			$_POST['field_' . $field_id] = date( 'Y-m-d H:i:s', strtotime( $_POST['field_' . $field_id . '_day'] . $_POST['field_' . $field_id . '_month'] . $_POST['field_' . $field_id . '_year'] ) );
	}

}

/**
 * Callback for hook 'profile_update'
 *
 * @param integer $user_id ID of the user which profile was updated
 * @param array   $olddata Array with the old data
 */
function noticeadminonprofilechange_on_profile_update( $user_id = 0, $olddata = array() ) {

	if ( empty( $user_id ) )
		return;

	$pluginheaders = PluginHeaderReader::get_instance( 'noticeadminonprofilechange' );
	$textdomain    = $pluginheaders->TextDomain;

	$data     = array ( 'old' => array(), 'new' => array(), 'changed' => array(), 'deleted' => array() );

	if ( empty( $olddata ) ) {
		$user = get_userdata( $user_id );
		$data['old'] = property_exists( $user, 'data' ) ? (array) $user->data : array();
	} else {
		$data['old'] = $olddata;
	}

	$data['old'] = (array) $data['old'];

	$usermetas = get_user_meta( $user_id );

	// merge usermetas with old data
	foreach ( $usermetas as $key => $value ) {

		if ( ! isset( $data['old'][ $key ] ) ) {

			if ( is_array( $value ) )
				$val = array_shift( $value );
			else
				$val = $value;

			$data['old'][ $key ] = $val;
		}

	}

	// on standard profiles there are no groups, set a dummy group
	$group = __( 'Standard Profile', $textdomain );
	$skip  = array(
			'password', 'pass2', 'rich_editing', 'admin_colors', 'comment_shortcuts', 'admin_bar_front'
	);

	foreach ( $data['old'] as $index => $old ) {

		// skip unwanted field changes like password change
		if ( in_array( $index, $skip ) )
			continue;

		$key = '';
		$new = filter_input( INPUT_POST, $index );

		if ( ! empty( $new ) ) {
			if ( ( $new != $old ) && ! empty( $old ) ) {
				$value = &$new;
				$key   = 'changed';
			} elseif ( ( $new != $old ) && empty( $old ) ) {
				$value = &$new;
				$key   = 'new';
			}
		}

		if ( ( empty( $new ) && ! empty( $old ) ) && key_exists( $index, $_POST ) ) {
			$value = __( '[Field was deleted]', $textdomain );
			$key   = 'deleted';
		}

		if ( ! empty( $key ) ) {
			$index = noticeadminonprofilechange_maybe_translated( $index );
			$data[ $key ][ $group ][ $index ] = $value;
		}

	}

	noticeadminonprofilechange_sending_data( $data, $user_id );

}

/**
 * Sending the data via email
 * @param array      $data Data to send
 * @param int|object $user User ID or BuddyPress User object
 */
function noticeadminonprofilechange_sending_data( $data, $user ) {

	$pluginheaders = PluginHeaderReader::get_instance( 'noticeadminonprofilechange' );

	$sendmail = new NoticeAdminOnProfileChange_SendMail();

	$sendmail->menupageobject = $pluginheaders->menupageobject;
	$sendmail->textdomain     = $pluginheaders->TextDomain;

	$sendmail->init( $user );
	$sendmail->send( $data );

	return true;

}

/**
 * Returns a translated version of the index if available
 * @param  string $index String to translate
 * @return string	$index Translated string or original string if no translation is available
 */
function noticeadminonprofilechange_maybe_translated( $index = '' ) {

	$strings = array(
			'first_name'   => __( 'First Name' ),
			'last_name'    => __( 'Last Name' ),
			'nickname'     => __( 'Nickname' ),
			'display_name' => __( 'Display name publicly as' ),
			'email'        =>  __( 'E-mail' ),
			'url'          => __( 'Website' ),
			'description'  => __( 'About the user' ),

	);

	return ( key_exists( $index, $strings ) ) ?
		$strings[ $index ] : $index;

}

/**
 * Registering the activation- and uninstall hooks
 */
register_activation_hook(	__FILE__, 'noticeadminonprofilechange_on_activation' );
register_uninstall_hook(  __FILE__, 'noticeadminonprofilechange_on_uninstall' );

/**
 * Actions on plugin activation
 */
function noticeadminonprofilechange_on_activation(){

	// perform a basic initialization and load the plugin textdomain
	noticeadminonprofilechange_basic_init();
	noticeadminonprofilechange_textdomain();

	if ( ! class_exists( 'NoticeAdminOnProfileChange_MenuPage' ) )
		require_once 'classes/noticeadminonprofilechange_menupage.php';

	// get the pluginheaders to setup the textdomain for default values
	$pluginheaders = PluginHeaderReader::get_instance( 'noticeadminonprofilechange' );
	NoticeAdminOnProfileChange_MenuPage::$textdomain_static = $pluginheaders->TextDomain;

	$defaults = NoticeAdminOnProfileChange_MenuPage::get_default_options();
	$key      = NoticeAdminOnProfileChange_MenuPage::OPTION_KEY;

	add_option( $key, $defaults );

}

/**
 * Actions on plugin uninstall
 */

function noticeadminonprofilechange_on_uninstall(){

	if ( ! class_exists( 'NoticeAdminOnProfileChange_MenuPage' ) )
		require_once 'classes/noticeadminonprofilechange_menupage.php';

	$key = NoticeAdminOnProfileChange_MenuPage::OPTION_KEY;

	delete_option( $key );

}
