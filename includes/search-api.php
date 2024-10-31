<?php
/**
 * Norsani Search API
 *
 * @package norsani-api
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Norsani_Search_API_Route extends Norsani_General_API_Route {

	/**
	* Register the routes for the objects of the controller.
	*/
	public function register_routes() {
		
		$namespace = NORSANI_API_NAMESPACE;

		register_rest_route( $namespace, '/searchvendors', array(
		array(
			'methods'				=> WP_REST_Server::READABLE,
			'callback'				=> array( $this, 'searchvendors' ),
			'permission_callback'	=> array( $this, 'get_general_permissions_check' ),
			'args'					=> array(
					'vendortype' => array(
						'required' => true,
						'type' => 'string',
						'description' => __('Type of vendors to retrieve','norsani-api'),
						'sanitize_callback' => function( $value, $request, $param) {
							return esc_attr($value);
						}
					),
					'ordertype' => array(
						'required' => true,
						'type' => 'string',
						'description' => __('Order type','norsani-api'),
						'sanitize_callback' => function( $value, $request, $param) {
							return $value != null ? esc_attr($value) : null;
						}
					),
					'keyword' => array(
						'required' => true,
						'type' => 'string',
						'description' => __('Search Keyword','norsani-api'),
						'sanitize_callback' => function( $value, $request, $param) {
							return wc_clean($value);
						}
					),
				),
			)
		));		
	}
	
	public function searchvendors($request) {		
		
		$creds = $request->get_params();
		$vendors_type = $creds['vendortype'];
		$key_word = $creds['keyword'];
		$order_type = $creds['ordertype'];
		$allowed_vendors = frozr_get_allowed_vendors_types();
		
		if (!$vendors_type || !in_array($vendors_type, $allowed_vendors)) {
			$vendors_type = null;
		}
		
		/*Get Vendors*/
		$args = apply_filters( 'frozr_rest_get_vendors', array(
			'role' => 'seller',
			'orderby' => 'registered',
			'order' => 'ASC',
			'meta_query' => array(
				array(
					'key' => 'frozr_enable_selling',
					'value' => array('yes',1),
					'compare' => 'IN'
				)
			)
		));

		$vendor_user_query = new WP_User_Query( $args );
		$get_vendor_users = $vendor_user_query->get_results();
		$vendors = array();
		foreach($get_vendor_users as $vendor_user) {
			$vendor_id = $vendor_user->ID;
			$vendor_obj = norsani_get_vendor($vendor_id);
			$store_type = $vendor_obj->get_store_type();
			$orders_accept = frozr_vendor_accepted_orders($vendor_id);
			$orders_accept_cl = frozr_vendor_accepted_orders_cl($vendor_id);
			
			if($vendors_type && $store_type != $vendors_type || $order_type && !isset($orders_accept[$order_type]) && !in_array($order_type, $orders_accept) && !in_array($order_type, $orders_accept_cl)) {
				continue;
			}
			
			$getalltyps= get_terms( 'vendorclass', 'fields=names&hide_empty=0' );
			$tags_object = $vendor_obj->get_store_classification();
			$tags_array = array();
			if (is_array($tags_object)) {
				foreach ( $tags_object as $restype ) {
					$tags_array[] = $restype->name;
				}
				$grestypes = join( ', ', $tags_array );
			} elseif ( ! empty( $getalltyps ) && ! is_wp_error( $getalltyps )) {
				$grestypes = $tags_object;
			}
			
			/*Get vendor's rating*/
			$rating_avarage = 0;
			$vendor_rating = $vendor_obj->get_store_rating();
			
			if (!empty($vendor_rating)) {
				$rating_count = array();
				$ratings = array();
				foreach($vendor_rating as $key => $val) {
					$rating_count[] = $key;
					$ratings[] = $val;
				}
				$total_count = count($rating_count);
				$rating_avarage = array_sum($ratings)/$total_count;
			}
			
			$vend_name = stripos($vendor_obj->get_store_name(),$key_word);
			$vend_tag = stripos($grestypes,$key_word);
			$vend_address = stripos($vendor_obj->get_store_address(),$key_word);
			
			$vendor_logo_src = $vendor_obj->get_store_logo() ? wp_get_attachment_image_src( absint( $vendor_obj->get_store_logo() ), 'small' ) : false;
			$vendor_logo = $vendor_logo_src ? $vendor_logo_src[0] :'';
			
			if($vend_name !== false || $vend_tag !== false || $vend_address !== false) {
				$vendors[] = array(
					'id' => $vendor_id,
					'name' => $vendor_obj->get_store_name(),
					'logo' => $vendor_logo,
					'address' => $vendor_obj->get_store_address(),
					'address_geo' => $vendor_obj->get_store_geolocation_address(),
					'delivery_zone' => $vendor_obj->get_delivery_zone_filtered(),
					'rating' => $rating_avarage > 0 ? number_format(min( 5, $rating_avarage ), 1) : 0,
					'vendorclass' => $tags_array,
					'timing_status' => frozr_is_rest_open($vendor_id),
					'orders_made' => intval(frozr_count_user_object('wc-completed','shop_order',$vendor_id)),
				);
			}
		}
		
		return new WP_REST_Response( json_encode($vendors), 200 );
	}
}
add_action('rest_api_init', function () {
	$norsani_api = new Norsani_Search_API_Route();
	$norsani_api->register_routes();
});