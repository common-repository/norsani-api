<?php
/**
 * Norsani General API
 *
 * @package norsani-api
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Norsani_General_API_Route extends WP_REST_Controller {

	/**
	* Register the routes for the objects of the controller.
	*/
	public function register_routes() {
		
		$namespace = NORSANI_API_NAMESPACE;

		register_rest_route( $namespace, '/loadapp', array(
		array(
			'methods'				=> WP_REST_Server::READABLE,
			'callback'				=> array( $this, 'loadapp' ),
			'permission_callback'	=> array( $this, 'get_general_permissions_check' ),
			'args'					=> array(
					'reqdata' => array(
						'required' => true,
						'type' => 'string',
						'description' => __('Default data to retrieve','norsani-api'),
						'sanitize_callback' => function( $value, $request, $param) {
							if ($value) {
								$rdata = explode(',',$value);
								return array_map('wc_clean', $rdata);
							} else {
								return array();
							}
						}
					),
					'avadata' => array(
						'type' => 'string',
						'description' => __('Available data to retrieve','norsani-api'),
						'sanitize_callback' => function( $value, $request, $param) {
							if ($value) {
								return json_decode($value);
							} else {
								return null;
							}
						}
					),
				),
			)
		));
		
		register_rest_route( $namespace, '/getproduct/(?P<id>[\d]+)', array(
		'args'	=> array(
					'id' => array(
						'required' => true,
						'description' => __( 'Unique identifier for the resource.', 'norsani-api' ),
						'type' => 'integer',
						'sanitize_callback' => function( $value, $request, $param) {
							return intval($value) ;
						}
					),
				),
		array(
			'methods'				=> WP_REST_Server::READABLE,
			'callback'				=> array( $this, 'getproduct' ),
			'permission_callback'	=> array( $this, 'get_general_permissions_check' ),
			)
		));
		
		register_rest_route( $namespace, '/loadappdata', array(
		array(
			'methods'				=> WP_REST_Server::READABLE,
			'callback'				=> array( $this, 'loadappdata' ),
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
							return esc_attr($value);
						}
					),
					'customer' => array(
						'type' => 'string',
						'description' => __('Customer account email address','norsani-api'),
						'sanitize_callback' => function( $value, $request, $param) {
							return sanitize_email($value);
						}
					),
					'locality' => array(
						'type' => 'string',
						'description' => __('User locality','norsani-api'),
						'sanitize_callback' => function( $value, $request, $param) {
							if (!empty($value)) {
								return esc_attr($value);
							} else {
								return null;
							}
						}
					),
				),
			)
		));
	
	}
	
	public function get_general_permissions_check( $request ) {
		$authenticate_class = new Norsani_REST_Authentication();
		$authenticate = $authenticate_class->authenticate($request);
		
		if ( is_wp_error( $authenticate ) ) {
			return $authenticate;
		}

		return true;
	}

	public function loadapp($request) {

		$creds = $request->get_params();
		$request_defaults = $creds['reqdata'];
		$request_data = $creds['avadata'];
		$default_locality = frozr_get_current_locality();
		$allowed_vendors = frozr_get_allowed_vendors_types();
		$default_accepted_orders = frozr_default_accepted_orders_types_display();
		$allowed_order_types = apply_filters('norsani_app_allowed_order_types',frozr_default_accepted_orders_types());
		$app_heading_title = apply_filters('norsani_app_header_title',__('Search & order from nearby chefs and more..','norsani-api'));

		$return_order_types = array_filter($default_accepted_orders, function($k) use ($allowed_order_types) {
								return isset($allowed_order_types[$k]) || in_array($k, $allowed_order_types);
							}, ARRAY_FILTER_USE_KEY);
		
		/*Load app data*/
		$default_order_type = $allowed_order_types[0];
		if (is_array($default_order_type)){
			foreach($allowed_order_types as $key => $value) {
				$default_order_type = $key;
				break;
			}
		}
		
		$request_array = array(
			'vendortype' => isset($request_data->vendortype) ? $request_data->vendortype : null,
			'customer' => isset($request_data->userdata) && isset($request_data->userdata->user->email) ? $request_data->userdata->user->email : null,
			'ordertype' => isset($request_data->ordertype) ? $request_data->ordertype : $default_order_type,
			'locality' => isset($request_data->userlocality) ? $request_data->userlocality : null,
		);
		$data = norsani_api_load_app_data($request_array);
		
		/*Get vendor types options*/
		array_unshift($allowed_vendors, __('All Vendors','norsani-api'));
		$data['vendortypes'] = $allowed_vendors;	
		
		/*Order types*/
		$data['ordertypes'] = $return_order_types;	
		
		/*Default order type*/
		$data['ordertype'] = isset($request_data->ordertype) ? $request_data->ordertype : $default_order_type;	
		
		/*App title*/
		$data['apptitle'] = $app_heading_title;	
		
		if(in_array('userlocality',$request_defaults)) {
			$data['userlocality'] =  $default_locality;
		}
		if(in_array('vendortype',$request_defaults)) {
			$data['vendortype'] =  $allowed_vendors[0];
		}
		
		/*Get distance calculation unit*/
		$data['distancedivider'] = frozr_distance_divider();
		$data['distanceunitfullname'] = frozr_distance_divider(true,true);
		$data['distanceunitshortname'] = frozr_distance_divider(true);
		
		/*Get user menu*/
		$menu_name = 'norsani_app_user';
		$locations = get_nav_menu_locations();
		$menu_id = $locations[ $menu_name ] ;
		$menu_items = wp_get_nav_menu_items($menu_id);
		$menus = array();
		
		if (!empty($menu_items)) {
			foreach( $menu_items as $menu_item ) {
				$menus[] = array(
					'url' => esc_url($menu_item->url),
					'title' => esc_attr($menu_item->title),
				);
			}
		}
		$data['server_menu'] = $menus;
		
		return new WP_REST_Response( json_encode($data), 200 );
	}

	public function getproduct($request) {
		
		$id = intval($request['id']);
			
		$product_obj = wc_get_product($id);
		$product_id = $product_obj->get_id();
		$item = get_post($product_id);
		$item_author = $item->post_author;
		$vendor_obj = norsani_get_vendor($item_author);
		$variations = array();
		$vairation_options = array();

		/*Get cats*/
		$discts = get_terms( 'product_cat', 'fields=names&hide_empty=0' );
		$itemcats = wp_get_post_terms( $product_id, 'product_cat', array("fields" => "names") );
		if (is_array($itemcats)) {
			foreach ( $itemcats as $itemcat ) {
				if (isset($item_cats[$itemcat]) && !in_array($product_id, $item_cats[$itemcat]) || !isset($item_cats[$itemcat])) {
					$item_cats[$itemcat][] = $product_id;
				}
			}
		} elseif ( ! empty( $discts ) && ! is_wp_error( $discts )) {
			$item_cats[$itemcats][] = $product_id;
		}
		
		if ( $product_obj->is_type( 'variable' ) ) {
			$lowest  = $product_obj->get_variation_price( 'min', true );
			$highest = $product_obj->get_variation_price( 'max', true );
			if ( $lowest === $highest ) {
				$price  = $lowest;
			} else {
				$price  = $lowest.' - '.$highest;
			}
			
			
			/*Get Variation Options*/
			foreach ( $product_obj->get_attributes('edit') as $attribute ) {
				if ($attribute->is_taxonomy()) {
					foreach($attribute->get_terms() as $option) {
						$vairation_options[wc_attribute_label( $attribute->get_name() )][] = esc_html($option->name);
					}
				} else {
					foreach($attribute->get_options() as $option) {
						$vairation_options[wc_attribute_label( $attribute->get_name() )][] = esc_html($option);
					}
				}
			}

			/*Variations*/
			foreach($product_obj->get_available_variations() as $variation) {
				if ($variation['is_purchasable']) {
					$single_variation = array(
						'id' => $variation['variation_id'],
						'price' => $variation['display_price'],
						'description' => strip_tags($variation['variation_description'])
					);
					foreach($variation['attributes'] as $attribute_name => $option ) {
						$attributes = $product_obj->get_attributes();
						$slug = str_replace( 'attribute_', '', str_replace( 'pa_', '', $attribute_name ) );
						$attribute = $attributes[ $slug ];
						$attr_name = $attribute->get_name();
						if ( $attribute->is_taxonomy() ) {
							$taxonomy = $attribute->get_taxonomy_object();
							$attr_name = $taxonomy->attribute_label;
						}
						
						$single_variation['options'][] = array('title' => $attr_name, 'selection' => $option);
					}
					$variations[] = $single_variation;
				}
			}
		} else {
			$price  = $product_obj->get_price();
		}

		/*item ingredients*/
		$ings = get_terms( 'ingredient', 'fields=names&hide_empty=0' );
		$ingredients_data = wp_get_post_terms( $product_id, 'ingredient', array("fields" => "names") );
		$ingredients = array();
		if (is_array($ingredients_data)) {
			foreach ( $ingredients_data as $ingredient ) {
				$ingredients[] = $ingredient;
			}
		} elseif ( ! empty( $ings ) && ! is_wp_error( $ings )) {
			$ingredients[] = $ingredients_data;
		}

		/*Get coupons*/
		$coupons = array();
		$coupons_args = apply_filters('norsani_app_vendor_coupons_args', array(
			'posts_per_page'=> -1,
			'offset'		=> 0,
			'post_type'		=> 'shop_coupon',
			'post_status'	=> array('publish'),
			'orderby'		=> 'post_date',
			'order'			=> 'DESC',
			'author'		=> $item_author,
		));
		
		$coupons_array = get_posts( $coupons_args );
		foreach ( $coupons_array as $coupon ) {
			$coupon_id = $coupon->ID;
			$vendor_obj = norsani_get_vendor($coupon->post_author);

			$coupon_obj = new WC_Coupon( $coupon_id );
			$show_cp_inshop = frozr_is_on_shop( $coupon_id );
			$coupon_product_ids = array_map( 'absint', (array) $coupon_obj->get_product_ids() );
			
			if (!in_array($product_id, $coupon_product_ids)) {
				continue;
			}
			
			if ( 0 == $coupon_obj->get_id() || !isset( $show_cp_inshop ) || $show_cp_inshop != 'yes' || $vendors_type && $vendor_obj->get_store_type() != $vendors_type || !frozr_is_seller_enabled($coupon->post_author)) {
				continue;
			}

			if ($locality && !frozr_vendor_in_locality($coupon->post_author, $locality)) {
				continue;
			}
			
			if ( ! $coupon_obj->get_id() && ! $coupon_obj->get_virtual() ) {
				continue;
			}
			
			if ( $coupon_obj->get_usage_limit() > 0 && $coupon_obj->get_usage_count() >= $coupon_obj->get_usage_limit() ) {
				continue;
			}
			
			if ( $coupon_obj->get_date_expires() && current_time( 'timestamp', true ) > $coupon_obj->get_date_expires()->getTimestamp() ) {
				continue;
			}

			$message = frozr_get_instore_message( $coupon->ID );
			
			if (empty($message)) {
				$message = $coupon_obj->get_description();
			}
			
			$coupons[] = apply_filters('norsani_app_coupon_data',array(
				'product_ids'	=> $coupon_product_ids,
				'message'		=> $message,
				'code'			=> $coupon_obj->get_code(),
			), $coupon_id);
		}

		$data = apply_filters('norsani_app_single_product_data',array(
			'id' => $product_id,
			'title' => $product_obj->get_name(),
			'excerpt' => $product_obj->get_short_description(),
			'vendor_id' => $item_author,
			'vendor_name' => $vendor_obj->get_store_name(),
			'imagelink' => $product_obj->get_image_id() > 0 ? wp_get_attachment_url( $product_obj->get_image_id() ) : null,
			'price' => $price,
			'regular_price' => $product_obj->get_regular_price(),
			'sale_price' => $product_obj->get_sale_price(),
			'rating' => floatval(min( 5, round( $product_obj->get_average_rating(), 1 ) )),
			'promotions' => norsani_api_get_product_promotions($product_id),
			'categories' => $itemcats,
			'ingredients' => $ingredients,
			'variations' => $variations,
			'preparation' => frozr_get_product_preparation_time($product_id, false),
			'maxorders' => frozr_product_get_max_orders($product_id),
			'remainingorders' => frozr_product_max_orders($product_id),
			'min_qty' => $product_obj->get_min_purchase_quantity() > 0 ? $product_obj->get_min_purchase_quantity() : 1,
			'available' => frozr_product_availability_notice($product_id, false),
			'is_special' => frozr_is_item_special($product_id),
			'is_offline' => $item->post_status == 'offline' ? true : false,
			'vairationsoptions' => $vairation_options,
			'upsells' => $product_obj->get_upsell_ids(),
			'coupons' => $coupons,
			'has_coupon' => count($coupons) > 0,
			'crosssells' => $product_obj->get_cross_sell_ids(),
		), $product_id);
		
		return new WP_REST_Response( json_encode($data), 200 );
	}
	
	public function loadappdata($request) {		
		
		$creds = $request->get_params();
		
		$data = norsani_api_load_app_data($creds);
		
		return new WP_REST_Response( json_encode($data), 200 );
	}
}
add_action('rest_api_init', function () {
	$norsani_api = new Norsani_General_API_Route();
	$norsani_api->register_routes();
});