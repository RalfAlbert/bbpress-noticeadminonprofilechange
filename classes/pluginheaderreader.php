<?php
/**
 * WordPress Class to read and keep the plugin file headers
 *
 * PHP version 5.2
 *
 * @category   PHP
 * @package    WordPress
 * @subpackage PluginHeaderReader
 * @author     Ralf Albert <me@neun12.de>
 * @license    GPLv3 http://www.gnu.org/licenses/gpl-3.0.txt
 * @version    1.0
 * @link       http://wordpress.com
 */

/**
 * PluginHeaderReader
 * @author Ralf Albert
 * @version 1.0
 *
 * Reads the plugin header from a given file and stores the data
 */
( ! defined( 'ABSPATH' ) ) AND die( 'Standing OPn The Shoulders Of Giants' );

if ( ! class_exists( 'PluginHeaderReader' ) ){

class PluginHeaderReader implements I_PluginHeaderReader, IteratorAggregate
{
	/**
	 * Object for data from plugin header
	 * @var Object
	 */
	public static $data = array();

	/**
	 * Instance identifier
	 * @var string
	 */
	public static $id = '';

	/**
	 * Flag to show if the pluginheaders was read
	 * @var boolean
	 */
	public static $headers_was_set = false;

	/**
	 * Reads the plugin header from given filename
	 * @param string $filename File with plugin header
	 * @return boolean False if the file does not exists
	 * @return boolean Returns false on error or true on success
	 */
	public static function init( $filename = '', $id = '' ) {

		if ( ! defined( 'ABSPATH' ) )
			trigger_error( 'This class requires WordPress. ABSPATH not found', E_USER_ERROR );

		if ( empty( $filename ) || ! file_exists( $filename ) )
			return false;

		if ( empty( $id ) || ! is_string( $id ) )
			return false;

		if ( ! function_exists( 'get_plugin_data' ) )
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

		$headers = get_plugin_data( $filename );

		if ( ! empty( $headers ) && is_array( $headers ) ) {

			if ( ! is_object( self::$data ) )
				self::$data = new stdClass();

			self::$data->$id = new stdClass();

			self::$data->$id = (object) $headers;
			self::$data->$id->headers_was_set = true;

		}

		unset( $headers );

		return true;

	}

	/**
	 * Returns an instance of itself
	 * @return object Instance of itself
	 */
	public static function get_instance( $id ) {

		if ( empty( $id ) || ! is_string( $id ) )
			trigger_error( 'Error in ' . __METHOD__ . ': parameter (string) id expected', E_USER_NOTICE );

		self::$id = $id;

		return new self();

	}

	/**
	 * Returns a value
	 * @param string $name Name of the value
	 * @return mixed The value if it is set, else null
	 */
	public function __get( $name ) {

		if ( empty( $name ) )
			trigger_error( 'Error in ' . __METHOD__ . ': parameter (string) name expected', E_USER_NOTICE );

		if ( empty( self::$id ) )
			trigger_error( 'Error in ' . __METHOD__ . ': call get_instance( $id ) first to set up the id', E_USER_NOTICE );

		$id = self::$id;

		return ( isset( self::$data->$id->$name ) ) ?
			self::$data->$id->$name : null;

	}

	/**
	 * Set a value
	 * @param string $name Name of the value
	 * @param string $value The value itself
	 */
	public function __set( $name, $value = null ) {

		if ( empty( $name ) )
			trigger_error( 'Error in ' . __METHOD__ . ': parameter (string) name expected', E_USER_NOTICE );

		if ( empty( self::$id ) )
			trigger_error( 'Error in ' . __METHOD__ . ': call get_instance( $id ) first to set up the id', E_USER_NOTICE );

		$id = self::$id;

		if ( ! is_object( self::$data ) )
			self::$data = new stdClass();

		if ( ! is_object( self::$data->$id ) )
			self::$data->$id = new stdClass();

		self::$data->$id->$name = $value;

	}

	/**
	 * Implements the isset() functionality to check if a propperty is set with isset()
	 * @param string $name Name of the propperty to check
	 * @return boolean True if the popperty is set, else false
	 */
	public function __isset( $name ) {

		if ( empty( self::$id ) )
			trigger_error( 'Error in ' . __METHOD__ . ': call get_instance( $id ) first to set up the id', E_USER_NOTICE );

		$id = self::$id;

		if ( ! is_object( self::$data->$id ) )
			return false;

		return ( property_exists( self::$data->$id, $name ) ) ?
			true : false;

	}

	/**
	 * Returns the iterator
	 * @return \ArrayIterator
	 */
	public function getIterator() {

		if ( empty( self::$id ) )
			trigger_error( 'Error in ' . __METHOD__ . ': call get_instance( $id ) first to set up the id', E_USER_NOTICE );

		$id = self::$id;

		return new ArrayIterator( self::$data->$id );

	}

}

}