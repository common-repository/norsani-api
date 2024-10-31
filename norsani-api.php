<?php
/*
Plugin Name: Norsani API
Plugin URI: https://wordpress.org/plugins/norsani-api/
Description: API to connect with <a href="https://codecanyon.net/item/norsani-multivendor-food-ordering-system/22255486">Norsani</a>.
Version: 1.3
Author: Mahmud Hamid
Author URI: https://mahmudhamid.com
Copyright: © 2009-2016 Mahmud Hamid.
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/


// don't call the file directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'NORSANI_API_PATH' ) ) {
	define( 'NORSANI_API_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'NORSANI_API_NAMESPACE' ) ) {
	define( 'NORSANI_API_NAMESPACE',  'norsani/v1' );
}

if ( ! class_exists( 'Norsani_API' ) ) {

final class Norsani_API {

	/**
	 * Constructor
	 */
	public function __construct() {
		
		//includes file
		$this->includes();

		//actions
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
	}
	public static function init() {
		static $instance = false;

		//call Norsani_API
		if ( ! $instance ) {
			$instance = new Norsani_API();
	
			do_action('norsani_api_init');
		}
		return $instance;
	}
	
	public static function activate() {
		//nothing here yet!
	}
		
	public static function deactivate() {
		//nothing here yet!
	}
	/**
	 * Translation
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'norsani-api', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
	/*
	 * Included Files
	 *
	 */
	function includes() {
		require_once ( 'includes/authenticate-api.php' );
		require_once ( 'includes/main-api.php' );
		require_once ( 'includes/location-api.php' );
		require_once ( 'includes/vendor-api.php' );
		require_once ( 'includes/cart-api.php' );
		require_once ( 'includes/checkout-api.php' );
		require_once ( 'includes/orders-api.php' );
		require_once ( 'includes/user-api.php' );
		require_once ( 'includes/search-api.php' );
		require_once ( 'includes/api-functions.php' );
		
		if (is_admin()) {
		require_once ( 'includes/admin-options.php' );
		}
	}
}

/**
* Load Frozr REST
*
* @return void
*/
function load_norsani_api() {

return Norsani_API::init();

}
add_action( 'norsani_loaded', 'load_norsani_api' );

register_activation_hook( __FILE__, array( 'Norsani_API', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Norsani_API', 'deactivate' ) );
}