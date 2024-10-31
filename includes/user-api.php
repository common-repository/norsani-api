<?php
/**
 * Norsani User API
 *
 * @package norsani-api
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Norsani_User_API_Route extends Norsani_General_API_Route {

	/**
	* Register the routes for the objects of the controller.
	*/
	public function register_routes() {
		
		$namespace = NORSANI_API_NAMESPACE;

		register_rest_route( $namespace, '/getfavoritevendors', array(
		array(
			'methods'				=> WP_REST_Server::READABLE,
			'callback'				=> array( $this, 'getfavoritevendors' ),
			'permission_callback'	=> array( $this, 'get_general_permissions_check' ),
			'args'					=> array(
					'ids' => array(
						'required' => true,
						'type' => 'string',
						'description' => __("IDs of user's favorite vendors","norsani-api"),
						'sanitize_callback' => function( $value, $request, $param) {
							if ($value) {
								$rdata = explode(',',$value);
								return array_map('intval', $rdata);
							} else {
								return array();
							}
						}
					),
				),
			)
		));
		
		register_rest_route( $namespace, '/getfavoriteitems', array(
		array(
			'methods'				=> WP_REST_Server::READABLE,
			'callback'				=> array( $this, 'getfavoriteitems' ),
			'permission_callback'	=> array( $this, 'get_general_permissions_check' ),
			'args'					=> array(
					'ids' => array(
						'required' => true,
						'type' => 'string',
						'description' => __("IDs of user's favorite items","norsani-api"),
						'sanitize_callback' => function( $value, $request, $param) {
							if ($value) {
								$rdata = explode(',',$value);
								return array_map('intval', $rdata);
							} else {
								return array();
							}
						}
					),
				),
			)
		));
	}
	
	public function getfavoriteitems($request) {
		$creds = $request->get_params();
		$items_id = $creds['ids'];
		$items = array();
		
		foreach($items_id as $item_id) {			
			$item = get_post($item_id);
			$product_obj = wc_get_product($item_id);
			$product_id = $product_obj->get_id();
			$variations = array();
			$vairation_options = array();
			$item_author = $item->post_author;
			$vendor = norsani_get_vendor($item_author);
			
			/*Get cats*/
			$itemcats = wp_get_post_terms( $product_id, 'product_cat', array("fields" => "names") );
			
			/*Price and variations*/
			$regular_price = $product_obj->get_regular_price();

			if ( $product_obj->is_type( 'variable' ) ) {
				$lowest  = $product_obj->get_variation_price( 'min', false );
				$highest = $product_obj->get_variation_price( 'max', false );
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
							'description' => $variation['variation_description']
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

			$image_src = $product_obj->get_image_id() > 0 ? wp_get_attachment_image_src( absint( $product_obj->get_image_id() ), 'large' ) : false;
			$image = $image_src ? $image_src[0] : null;

			$items[] = array(
				'id' => $product_id,
				'title' => $product_obj->get_name(),
				'is_variable' => $product_obj->is_type( 'variable' ),
				'excerpt' => $product_obj->get_short_description(),
				'vendor_id' => $item_author,
				'vendor_name' => $vendor->get_store_name(),
				'imagelink' => $image,
				'price' => $price,
				'on_sale' => $product_obj->is_on_sale(),
				'regular_price' => $regular_price,
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
				'crosssells' => $product_obj->get_cross_sell_ids(),
			);		
		}
		
		return new WP_REST_Response( json_encode($items), 200 );
	}
	
	public function getfavoritevendors($request) {		
		
		$creds = $request->get_params();
		$vendors_id = $creds['ids'];
		$vendors = array();

		foreach($vendors_id as $vendor_id) {
			$vendor_obj = norsani_get_vendor($vendor_id);
			$store_type = $vendor_obj->get_store_type();
			$accepted_orders = frozr_vendor_accepted_orders($vendor_id);
			$accepted_orders_closed = frozr_vendor_accepted_orders_cl($vendor_id);
			
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
			
			/*Get tags*/
			$tags_object = $vendor_obj->get_store_classification();
			$tags_array = array();
			foreach ($tags_object as $tag) {
				$tags_array[] = $tag->name;
			}
			
			$peak_orders = $vendor_obj->get_orders_number_to_set_as_busy();
			$current_processing_orders = frozr_count_user_object('wc-processing', 'shop_order',$vendor_id);
			
			$vendor_logo_src = $vendor_obj->get_store_logo() ? wp_get_attachment_image_src( absint( $vendor_obj->get_store_logo() ), 'small' ) : false;
			$vendor_logo = $vendor_logo_src ? $vendor_logo_src[0] : '';
			$vendor_cover_src = $vendor_obj->get_store_banner() ? wp_get_attachment_image_src( absint( $vendor_obj->get_store_banner() ), 'large' ) : false;
			$vendor_cover = $vendor_cover_src ? $vendor_cover_src[0] : '';

			$vendors[] = array(
				'id' => $vendor_id,
				'name' => $vendor_obj->get_store_name(),
				'logo' => $vendor_logo,
				'cover' => $vendor_cover,
				'address' => $vendor_obj->get_store_address(),
				'contact_number' => esc_html( $vendor_obj->get_contact_number() ),
				'address_geo' => $vendor_obj->get_store_geolocation_address(),
				'is_busy' => intval($peak_orders) > 0 && intval($current_processing_orders) > intval($peak_orders),
				'delivery_fee' => frozr_delivery_settings($vendor_id,'delivery_fee',true),
				'delivery_fee_by' => frozr_vendor_delivery_fee_by($vendor_id),
				'custom_delivery_duration' => norsani_api_get_vendor_delivery_duration($vendor_id),
				'min_delivery' => $vendor_obj->get_delivery_minimum_order_amount(),
				'delivery_zone' => $vendor_obj->get_delivery_zone_filtered(),
				'rating' => $rating_avarage > 0 ? number_format(min( 5, $rating_avarage ), 1) : 0,
				'vendorclass' => $tags_array,
				'notice' => $vendor_obj->get_store_notice(),
				'is_open' => frozr_is_rest_open($vendor_id),
				'timing_status' => frozr_rest_status($vendor_id),
				'social' => array('twitter' => esc_url( $vendor_obj->get_social_twitter() ), 'facebook' => esc_url( $vendor_obj->get_social_fb() ), 'youtube' => esc_url( $vendor_obj->get_social_youtube() ), 'instagram' => esc_url( $vendor_obj->get_social_insta() )),
				'orders_made' => intval(frozr_count_user_object('wc-completed','shop_order',$vendor_id)),
			);
		}
		
		return new WP_REST_Response( json_encode($vendors), 200 );
	}
}
add_action('rest_api_init', function () {
	$norsani_api = new Norsani_User_API_Route();
	$norsani_api->register_routes();
});