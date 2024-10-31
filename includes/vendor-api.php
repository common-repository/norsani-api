<?php
/**
 * Norsani Vendor API
 *
 * @package norsani-api
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Norsani_Vendor_API_Route extends Norsani_General_API_Route {

	/**
	* Register the routes for the objects of the controller.
	*/
	public function register_routes() {
		
		$namespace = NORSANI_API_NAMESPACE;

		register_rest_route( $namespace, '/vendor/(?P<id>[\d]+)', array(
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
			'callback'				=> array( $this, 'get_vendor' ),
			'permission_callback'	=> array( $this, 'get_general_permissions_check' ),
			)
		));
	}

	public function get_vendor($request) {

		$id = intval($request['id']);
		$creds = $request->get_params();
		$vendor = norsani_get_vendor($id);
		
		if (!$vendor) {
			return new WP_Error(
				"norsani_rest_invalid_id", __( 'Invalid Vendor ID.', 'norsani-api' ), array(
					'status' => 404,
				)
			);
		}
		
		/*Get Store Menus*/
		$menus = $vendor->get_store_menus();
		$menus_data = array();
		$active_menu = null;
		$filterd_menus = isset($menus[0]) && is_array($menus) ? array_filter($menus[0]) : array();
		if (is_array($menus) && !empty($filterd_menus)) {
			$now = new DateTime(date('H:i', strtotime(current_time('mysql'))));
			$loop_size = count($menus);
			$count_loop = 1;
			
			foreach ($menus as $menu){
				$startime = new DateTime($menu['start']);
				$endtime = new DateTime($menu['end']);
				$menu_title = rawurldecode(sanitize_title(wp_unslash($menu['title'])));
				
				if (intval(date('H:i',strtotime($menu['start'])))== 0 && intval(date('H:i',strtotime($menu['end']))) == 0) {
					$timing = __('All Time','norsani-api');
				} else {
					$timing = date_i18n(frozr_get_time_date_format(),strtotime($menu['start'])).' '.__('to','norsani-api').' '.date_i18n(frozr_get_time_date_format(),strtotime($menu['end']));
				}
				if ($startime <= $now && $now < $endtime && !$active_menu || $count_loop == $loop_size && !$active_menu) {
					$active_menu = $menu_title;
				}
				
				$menus_data[] = array('title' => $menu_title, 'timing' => $timing);
				
				$count_loop++;
			}
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
			'author'		=> $id,
		));
		
		$coupons_array = get_posts( $coupons_args );
		foreach ( $coupons_array as $coupon ) {
			$coupon_id = $coupon->ID;
			$vendor_obj = norsani_get_vendor($coupon->post_author);

			$coupon_obj = new WC_Coupon( $coupon_id );
			$show_cp_inshop = frozr_is_on_shop( $coupon_id );

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
				'product_ids'	=> array_map( 'absint', (array) $coupon_obj->get_product_ids() ),
				'message'		=> $message,
				'code'			=> $coupon_obj->get_code(),
			), $coupon_id);
		}
		
		/*Get Products*/
		$products = array();
		$item_cats = array();
		$args = array(
			'posts_per_page'=> -1,
			'offset'		=> 0,
			'author'		=> $id,
			'post_type'		=> 'product',
			'post_status'	=> array('publish','offline'),
		);
		
		$products_array = get_posts( $args );
		foreach ( $products_array as $item ) {
			
			$item_id = $item->ID;
			$product_obj = wc_get_product($item_id);
			$product_id = $product_obj->get_id();
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
			
			/*Price and variations*/
			$regular_price = $product_obj->get_regular_price();
			
			if ( $product_obj->is_type( 'variable' ) ) {
				$lowest  = $product_obj->get_variation_price( 'min', true );
				$highest = $product_obj->get_variation_price( 'max', true );
				if ( $lowest === $highest ) {
					$price  = $lowest;
				} else {
					$price  = $lowest.' - '.$highest;
				}
				
				if ($product_obj->is_on_sale()) {
					$lowest  = $product_obj->get_variation_regular_price( 'min', true );
					$highest = $product_obj->get_variation_regular_price( 'max', true );
					if ( $lowest === $highest ) {
						$regular_price  = $lowest;
					} else {
						$regular_price  = $lowest.' - '.$highest;
					}
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
			
			/*Get Coupons*/
			$product_coupons = array_filter($coupons, function($coupon) use ($product_obj) {
				$coupon_obj = new WC_Coupon( $coupon['code'] );
				return $coupon_obj->is_valid_for_product($product_obj);
			});
			
			$image_src = $product_obj->get_image_id() > 0 ? wp_get_attachment_image_src( absint( $product_obj->get_image_id() ), 'large' ) : false;
			$image = $image_src ? $image_src[0] : null;
						
			$products[] = apply_filters('norsani_app_product_full_data',array(
				'id' => $product_id,
				'title' => $product_obj->get_name(),
				'is_variable' => $product_obj->is_type( 'variable' ),
				'excerpt' => $product_obj->get_short_description(),
				'vendor_id' => $id,
				'vendor_name' => $vendor->get_store_name(),
				'imagelink' => $image,
				'price' => $price,
				'on_sale' => $product_obj->is_on_sale(),
				'regular_price' => $regular_price,
				'sale_price' => $product_obj->get_sale_price(),
				'menus' => frozr_product_menus($product_id),
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
				'coupons' => $product_coupons,
				'has_coupon' => count($product_coupons) > 0,
				'crosssells' => $product_obj->get_cross_sell_ids(),
			), $product_id );
		}
		
		/*Get vendor's rating*/
		$rating_avarage = 0;
		$vendor_rating = $vendor->get_store_rating();
		
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
		
		/*Get tags*/
		$tags_object = $vendor->get_store_classification();
		$tags_array = array();
		foreach ($tags_object as $tag) {
			$tags_array[] = $tag->name;
		}
			
		$peak_orders = $vendor->get_orders_number_to_set_as_busy();
		$current_processing_orders = frozr_count_user_object('wc-processing', 'shop_order',$id);

		$data = apply_filters('norsani_app_vendor_full_data',array(
			'name' => $vendor->get_store_name(),
			'logo' => $vendor->get_store_logo() ? wp_get_attachment_url(absint( $vendor->get_store_logo() )) : '',
			'cover' => $vendor->get_store_banner() ? wp_get_attachment_url(absint( $vendor->get_store_banner() )) : '',
			'address' => $vendor->get_store_address(),
			'contact_number' => filter_var($vendor->get_contact_number(), FILTER_SANITIZE_NUMBER_INT),
			'address_geo' => $vendor->get_store_geolocation_address(),
			'is_busy' => intval($peak_orders) > 0 && intval($current_processing_orders) > intval($peak_orders),
			'delivery_fee' => frozr_delivery_settings($id,'delivery_fee',true),
			'delivery_fee_by' => frozr_vendor_delivery_fee_by($id),
			'custom_delivery_duration' => norsani_api_get_vendor_delivery_duration($id),
			'min_delivery' => $vendor->get_delivery_minimum_order_amount(),
			'delivery_zone' => $vendor->get_delivery_zone_filtered(),
			'rating' => $rating_avarage > 0 ? number_format(min( 5, $rating_avarage ), 1) : 0,
			'review_count' => $total_count,
			'vendorclass' => $tags_array,
			'notice' => $vendor->get_store_notice(),
			'is_open' => frozr_is_rest_open($id),
			'timing_status' => frozr_rest_status($id),
			'social' => array('twitter' => esc_url( $vendor->get_social_twitter() ), 'facebook' => esc_url( $vendor->get_social_fb() ), 'youtube' => esc_url( $vendor->get_social_youtube() ), 'instagram' => esc_url( $vendor->get_social_insta() )),
			'orders_made' => intval(frozr_count_user_object('wc-completed','shop_order',$id)),
			'productcats' => $item_cats,
			'menus' => $menus_data,
			'activemenu' => $active_menu,
			'products' => $products,

		), $id);
		
		return new WP_REST_Response( json_encode($data), 200 );
	}
}
add_action('rest_api_init', function () {
	$norsani_api = new Norsani_Vendor_API_Route();
	$norsani_api->register_routes();
});