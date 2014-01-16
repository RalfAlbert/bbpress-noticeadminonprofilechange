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
 * @version    1.0
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
 * Version:     1.2.20140116
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
	 * If bbPress's xprofile are active, the 'xprofile_data_before_save' hook is triggered
	 * before the 'edit_user_profile_update' and 'personal_options_update' hooks
	 */
// 	add_action( 'xprofile_data_before_save', 'noticeadminonprofilechange_on_xprofile_update', 10, 2 );
// 	add_action( 'xprofile_data_before_delete', 'noticeadminonprofilechange_on_xprofile_delete', 10, 2 );

	add_action( 'personal_options_update',  'noticeadminonprofilechange_on_profile_update', 10, 2 );
	add_action( 'edit_user_profile_update', 'noticeadminonprofilechange_on_profile_update', 10, 2 );


/*
 * This hook is needed to fetch the old user data
 */
add_action( 'bp_screens',                  'noticeadminonprofilechange_on_xprofile_update', 0, 1 );

/*
 * This hooks are needed to fetch the saved / deleted data
 */
add_action( 'xprofile_data_before_save',   'noticeadminonprofilechange_on_xprofile_update', 10, 1 );
add_action( 'xprofile_data_before_delete', 'noticeadminonprofilechange_on_xprofile_update', 10, 1 );

/*
 * This hook is needed to send the data via email
 */
add_action( 'xprofile_updated_profile',    'noticeadminonprofilechange_on_xprofile_update', 10, 1 );

}

/**
 * Callback for hooks 'bp_screens', 'xprofile_data_before_save',
 * 'xprofile_data_before_delete' & 'xprofile_updated_profile'
 *
 * @param		object		$user	User object provided by BuddyPress
 * @return	boolean					False if the xprofile are not active
 */
function noticeadminonprofilechange_on_xprofile_update( $user ) {

	// check if Xprofile is acive
	if ( ! bp_is_active( 'xprofile' ) )
		return false;

	// set up static data
	static $data = array();
	static $textdomain = '';

	if ( empty( $textdomain ) ) {
		$pluginheaders = PluginHeaderReader::get_instance( 'noticeadminonprofilechange' );
		$textdomain = $pluginheaders->TextDomain;
	}

	$filter = current_filter();

	/*
	 * Get the user ID
	 * If the function is called by the hook 'bp_screens', no user-object is set.
	 * We have to get the user-object with wp_get_current_user(). The user ID is stored in the
	 * property 'ID' (uppercase)
	 *
	 * If the function is called by the hooks before_save and before_delete, the user-object is
	 * provided by BuddyPress and the user ID is stored in the property 'id' (lowercase)
	 *
	 * If the function is called by the hook 'updated_profile', the user ID is an integer.
	 */
		switch ( $filter ) {

			case 'bp_screens':
	 			$user = wp_get_current_user();
	 			$user_id = $user->ID;
 			break;

 			case 'xprofile_data_before_save':
 			case 'xprofile_data_before_delete':
 				$user_id = $user->id;
 			break;

 			case 'xprofile_updated_profile':
 			default:
 				$user_id = (int) $user;
 			break;

		}

	// fetch the user data before any changes are made
	if ( 'bp_screens' == $filter ) {
		$old_data = BP_XProfile_ProfileData::get_all_for_user( $user_id );

		foreach ( $old_data as $field_name => $field_data ) {

			if ( ! is_array( $field_data ) )
				continue;

			$data['old'][ $field_data['field_id'] ]  = $field_data['field_data'];

		}
	}


	//
	if ( true == strpos( $filter, '_save' ) )
		$key = 'new';
	elseif ( true == strpos( $filter, '_delete' ) )
		$key = 'deleted';
	else
		$key = '';

	if ( is_object( $user ) && ! empty( $key ) ) {

		$value = ( 'deleted' == $key ) ?
			sprintf( '%s (%s)', __( '[Field was deleted]', $textdomain ), $user->value ) : $user->value;

		$data[$key][$user->field_id] = $value;

	}

	// all data are saved/deleted, let's clean up some things and send the data via email
	if ( 'xprofile_updated_profile' == $filter ) {

		foreach ( $data['old'] as $field_id => $value ) {
			if ( key_exists( $field_id, $data['new'] ) ) {

				if ( $data['new'][ $field_id ] == $value )
					unset( $data['new'][ $field_id ] );
				else
					$data['changed'][ $field_id ] = $value;

			}
		}

		die( var_dump( $data ) );
		noticeadminonprofilechange_sending_data( $data, $user_id );

	}


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
 * Callback for hook 'profile_update'
 *
 * @param		integer	$user_id	ID of the user which profile was updated
 * @param		array		$olddata	Array with the old data
 */
function noticeadminonprofilechange_on_profile_update( $user_id = 0, $olddata = array() ) {

	if ( empty( $user_id ) )
		return;

	$postdata = $_POST;
	$data     = array ( 'old' => array(), 'new' => array(), 'changed' => array() );

	if ( empty( $olddata ) ) {
		$user = get_userdata( $user_id );
		$data['old'] = (array) $user->data;
	} else {
		$data['old'] = $olddata;
	}

	$data['old'] = (array) $data['old'];

	$usermetas = get_user_meta( $user_id );

	foreach ( $usermetas as $key => $value ) {

		if ( ! isset( $data['old'][ $key ] ) ) {

			if ( is_array( $value ) )
				$val = array_shift( $value );
			else
				$val = $value;

			$data['old'][ $key ] = $val;
		}

	}

	foreach ( $postdata as $key => $value ) {

		if ( key_exists( $key, $data['old'] ) ) {
			$data['new'][ $key ] = $value;

			if ( $data['old'][ $key ] != $value )
				$data['changed'][ $key ] = $value;

		}

	}

	noticeadminonprofilechange_sending_data( $data, $user_id );

}

/**
 * Callback for hook 'xprofile_data_before_save'
 *
 * @param		object		$user	User object provided by BuddyPress
 * @return	boolean					False if the xprofile are not active
 */
function _on_xprofile_update( $user, $deleted_data = array() ) {

	/*
	 * This flag prevent running the function more than once.
	 *
	 * The hook 'xprofile_data_before_save' will be called for each field to save.
	 * Unfortunatly this is the only hook which is triggered BEFORE the fields are saved.
	 * If we want to catch the old values, we have to hook in BEFORE the values are saved,
	 * unfortunately this is the only hook we can use.
	 */
	static $done = false;

	$pluginheaders = PluginHeaderReader::get_instance( 'noticeadminonprofilechange' );

	if ( ! bp_is_active( 'xprofile' ) || true == $done )
		return false;

	$field_ids = ( isset( $_POST['field_ids'] ) ) ? explode( ',', $_POST['field_ids'] ) : array ();
	$user_id   = $user->user_id;

	/*
	 * old     => data stored in the db
	 * new     => new data, formerly an empty field
	 * actual  => data send by BuddyPress (the filtered POST header fields)
	 * changed => data that are changed from an old value to a new value
	 */
	$data        = array ( 'old' => array(), 'new' => array(), 'changed' => array(), 'deleted' => $deleted_data );
	$data['old'] = BP_XProfile_ProfileData::get_all_for_user( $user_id );

	/*
	 *  convert the bbPress format into key-value format
	 *  bbPress saves the fields as a complex array. we need a simple format
	 *  to check if a value was changed
	 */
	foreach ( $data['old'] as $field => &$value ) {

		if ( ! is_array( $value ) )
			continue;

		$field_data = maybe_unserialize( $value['field_data'] );

		$value =  ( is_array( $field_data ) ) ?
			implode( ',', $field_data ) : $field_data;

	}

	// compare the data send via POST header with the saved data
	if ( ! empty( $field_ids ) ) {

		foreach ( $field_ids as $id ) {

			$field    = new BP_XProfile_Field( $id );
			$field_id = 'field_' . $id;
			$group    = xprofile_get_field_group( $field->group_id );

			$post_val = isset( $_POST[ $field_id ] ) ? $_POST[ $field_id ] : '';

			if ( is_array( $post_val ) )
				$post_val = implode( ',', $post_val );

			if ( empty( $post_val ) )
				$post_val = __( '[Field was deleted]', $pluginheaders->TextDomain );

			if ( key_exists( $field->name, $data['old'] ) ) {

				if ( $data['old'][ $field->name ] != $post_val )
					$data['changed'][ $group->name ][ $field->name ] = $post_val;

			} else {

				$data['new'][ $group->name ][ $field->name ] = $post_val;

			}

		}

		noticeadminonprofilechange_sending_data( $data, $user );

		$done = true;

	}

}


/**
 * Registering the activation- and uninstall hooks
 */
register_activation_hook(	__FILE__, 'noticeadminonprofilechange_on_activation' );
register_uninstall_hook(  __FILE__, 'noticeadminonprofilechange_on_uninstall' );

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

function noticeadminonprofilechange_on_uninstall(){

	if ( ! class_exists( 'NoticeAdminOnProfileChange_MenuPage' ) )
		require_once 'classes/noticeadminonprofilechange_menupage.php';

	$key = NoticeAdminOnProfileChange_MenuPage::OPTION_KEY;

	delete_option( $key );

}
