<?php
/**
 * Norsani Cart API
 *
 * @package norsani-api
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Norsani_Cart_API_Route extends Norsani_General_API_Route {

	/**
	* Register the routes for the objects of the controller.
	*/
	public function register_routes() {
		
		$namespace = NORSANI_API_NAMESPACE;

		register_rest_route( $namespace, '/addtocart', array(
		array(
			'methods'				=> WP_REST_Server::CREATABLE,
			'callback'				=> array( $this, 'add_to_cart' ),
			'permission_callback'	=> array( $this, 'get_general_permissions_check' ),
			'args'					=> array(
				'cartData' => array(
					'required' => true,
					'type' => 'array',
					'description' => __('Cart data','norsani-api'),
					'validate_callback' => function( $value, $request, $param) {
						return is_array($value) && !empty($value);
					},
					'sanitize_callback' => function( $value, $request, $param) {
						return array_map('wc_clean',$value) ;
					}
				),
				'coupons' => array(
					'type' => 'array',
					'description' => __('Cart coupons','norsani-api'),
					'sanitize_callback' => function( $value, $request, $param) {
						return array_map('wc_clean',$value) ;
					}
				),
			)
		)
		));
	}

	public function add_to_cart($request) {
		$creds = $request->get_params();
		$cart_data = $creds['cartData'];
		$coupons = $creds['coupons'];
		
		$cart = new WC_Cart();		
		
		$totals = norsani_api_calculate_cart_totals($cart,$cart_data,$coupons);
		
		return new WP_REST_Response( json_encode($totals), 200 );
	}
}
add_action('rest_api_init', function () {
	$norsani_api = new Norsani_Cart_API_Route();
	$norsani_api->register_routes();
});