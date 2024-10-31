<?php
/**
 * Norsani Checkout API
 *
 * @package norsani-api
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Norsani_Checkout_API_Route extends Norsani_General_API_Route {

	/**
	* Register the routes for the objects of the controller.
	*/
	public function register_routes() {
		
		$namespace = NORSANI_API_NAMESPACE;

		register_rest_route( $namespace, '/getcheckoutform', array(
		array(
			'methods'				=> WP_REST_Server::CREATABLE,
			'callback'				=> array( $this, 'get_checkout_form' ),
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
				'orderType' => array(
					'required' => true,
					'type' => 'string',
					'description' => __('Order Type','norsani-api'),
					'sanitize_callback' => function( $value, $request, $param) {
						return sanitize_text_field($value);
					}
				),
				'userEmail' => array(
					'type' => 'string',
					'description' => __('User Email','norsani-api'),
					'sanitize_callback' => function( $value, $request, $param) {
						return sanitize_email($value);
					}
				),
			)
		)
		));
		
		register_rest_route( $namespace, '/verifycheckout', array(
		array(
			'methods'				=> WP_REST_Server::CREATABLE,
			'callback'				=> array( $this, 'verify_checkout' ),
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
				'orderType' => array(
					'required' => true,
					'type' => 'string',
					'description' => __('Order Type','norsani-api'),
					'sanitize_callback' => function( $value, $request, $param) {
						return sanitize_text_field($value);
					}
				),
				'ordertimings' => array(
					'type' => 'array',
					'description' => __('Order Preparation Time','norsani-api'),
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
				'billingForm' => array(
					'required' => true,
					'type' => 'array',
					'description' => __('Billing Form','norsani-api'),
					'validate_callback' => function( $value, $request, $param) {
						return is_array($value) && !empty($value);
					},
					'sanitize_callback' => function( $value, $request, $param) {
						return array_map('wc_clean',$value) ;
					}
				),
				'paymentMethod' => array(
					'required' => true,
					'type' => 'string',
					'description' => __('Payment Method','norsani-api'),
					'sanitize_callback' => function( $value, $request, $param) {
						return sanitize_text_field($value);
					}
				),
				'customerNote' => array(
					'type' => 'string',
					'description' => __('Delivery Notes','norsani-api'),
					'sanitize_callback' => function( $value, $request, $param) {
						return sanitize_text_field($value);
					}
				),
				'userLocation' => array(
					'type' => 'string',
					'description' => __('User Location','norsani-api'),
					'sanitize_callback' => function( $value, $request, $param) {
						return sanitize_text_field($value);
					}
				),
				'userLocationGeo' => array(
					'type' => 'string',
					'description' => __('User Geo Location','norsani-api'),
					'sanitize_callback' => function( $value, $request, $param) {
						return sanitize_text_field($value);
					}
				),
			)
		)
		));
		
		register_rest_route( $namespace, '/pay_braintree', array(
		array(
			'methods'				=> WP_REST_Server::CREATABLE,
			'callback'				=> array( $this, 'pay_braintree' ),
			'permission_callback'	=> array( $this, 'get_general_permissions_check' ),
			'args'					=> array(
				'nonce' => array(
					'required' => true,
					'type' => 'string',
					'description' => __('Payment nonce','norsani-api'),
					'sanitize_callback' => function( $value, $request, $param) {
						return sanitize_text_field($value);
					}
				),
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
				'userEmail' => array(
					'type' => 'string',
					'description' => __('User Email','norsani-api'),
					'sanitize_callback' => function( $value, $request, $param) {
						return sanitize_email($value);
					}
				),
				'ordertimings' => array(
					'type' => 'array',
					'description' => __('Order Preparation Time','norsani-api'),
					'sanitize_callback' => function( $value, $request, $param) {
						return array_map('wc_clean',$value) ;
					}
				),
			)
		)
		));

	}

	public function pay_braintree($request) {
		$creds = $request->get_params();
		$nonce = sanitize_text_field($creds['nonce']);
		$cart_data = $creds['cartData'];
		$order_timings = $creds['ordertimings'];
		$coupons = $creds['coupons'];
		$user_email = isset($creds['userEmail']) ? sanitize_email($creds['userEmail']) : false;
		$cart = new WC_Cart();
		
		$add_to_cart_result = norsani_api_calculate_cart_totals($cart,$cart_data,$coupons);
		
		if (count($add_to_cart_result['messages']) > 0) {
			return new WP_Error( 'rest_cannot_process', json_encode($add_to_cart_result), array( 'status' => 400 ) );
		}
		
		try{
			
			frozr_is_order_valid($cart, $order_timings, true );
		
		} catch ( Exception $e ) {
			
			$add_to_cart_result['messages'][] = $e->getMessage();
			
			return new WP_Error( 'rest_cannot_process', json_encode($add_to_cart_result), array( 'status' => 400 ) );
		}
		
		
		// Order is ok lets proceed payment.
		$gateway = norsani_api_get_braintree_gateway();
		$customer_obj = $user_email ? get_user_by('email',$user_email) : false;
		$payment_args = array(
			'amount' => floatval($add_to_cart_result['totals']['total']),
			'paymentMethodNonce' => $nonce,
			'options' => array(
				'submitForSettlement' => true,
				'storeInVaultOnSuccess' => true
			)
		);
		
		if (!$gateway) {
			return new WP_Error( 'rest_cannot_process', __('Unable to process payment.','norsani-api'), array( 'status' => 403 ) );
		}
		
		if ($customer_obj) {
			try {
				$customer = $gateway->customer()->find($customer_obj->ID);
				$payment_args['customerId'] = $customer_obj->ID;
			} catch ( Exception $e) {
				$payment_args['customer'] = array('id' => $customer_obj->ID);
			}
		}
		
		$paymentResult = $gateway->transaction()->sale($payment_args);
		
		if ($paymentResult->success) {
			return new WP_REST_Response( json_encode(array('success' => true)), 200 );
		} else {
			return new WP_Error( 'rest_cannot_process', __('Unable to process payment.','norsani-api'), array( 'status' => 403 ) );
		}
	}
	
	public function verify_checkout($request) {
		$creds = $request->get_params();
		$cart_data = $creds['cartData'];
		$order_type = $creds['orderType'];
		$coupons = $creds['coupons'];
		$billing_form_raw = $creds['billingForm'];
		$order_timings = $creds['ordertimings'];
		$payment_method = $creds['paymentMethod'];
		$customer_note = isset($creds['customerNote']) ? strip_tags(esc_attr($creds['customerNote'])) : null;
		$userLocationGeo = isset($creds['userLocationGeo']) ? strip_tags($creds['userLocationGeo']) : null;
		$userLocation = isset($creds['userLocation']) ? strip_tags($creds['userLocation']) : null;
		
		$cart = new WC_Cart();

		$add_to_cart_result = norsani_api_calculate_cart_totals($cart,$cart_data,$coupons);
		
		if (count($add_to_cart_result['messages']) > 0) {
			return new WP_REST_Response( json_encode($add_to_cart_result), 200 );
		}
		
		try{
			
			frozr_is_order_valid($cart, $order_timings, true );
		
		} catch ( Exception $e ) {
			
			$add_to_cart_result['messages'][] = $e->getMessage();
			
			return new WP_REST_Response( json_encode($add_to_cart_result), 200 );
		}
		
		/*Prepare the data for checkout*/
		$vendors = $free_delivery_vendors = $vendor_coupons = $checkout_data = $billing_form = array();
		$is_delivery_order = false;
		
		/*Get cart vendors*/
		foreach($add_to_cart_result['added_data'] as $vendor_id => $vendor_data) {
			$product_id = intval($vendor_data['items'][0]['product_id']);
			
			if (!in_array($vendor_id, $vendors)) {
				$vendors[] = $vendor_id;
			}
		}
		
		/*Check coupons for each vendor*/
		foreach ( $cart->get_applied_coupons() as $code ) {
			$coupon = new WC_Coupon( $code );
			$coupon_id = wc_get_coupon_id_by_code( $coupon->get_code() );
			$coupon_author = get_post_field('post_author', $coupon_id);
			
			if($coupon->get_free_shipping() && $order_type == 'delivery') {
				$free_delivery_vendors[] = $coupon_author;
			}
			
			$vendor_coupons[$coupon_author][] = array('code' => $coupon->get_code());
		}
		
		/*Billing form*/
		foreach($billing_form_raw as $key => $val) {
			$billing_form[str_replace( 'billing_', '', sanitize_text_field($key) )] = sanitize_text_field($key) == 'billing_email' ? sanitize_email($val) : sanitize_text_field($val);
		}
		
		/*Get each vendor's cart data*/
		foreach ($vendors as $vendor_id) {
			$delivery_total = $fee_lines = $items = $total_order_preparation_time = array();
			
			$vendor = norsani_get_vendor($vendor_id);

			foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
				$product_id		= $cart_item['product_id'];
				$item_author	= get_post_field('post_author', $product_id);
				
				if ($item_author == $vendor_id) {
					
					if ($cart_item['order_l_type'] == 'delivery') {
						$is_delivery_order = true;
					}
					
					/*Check delivery fee*/
					if ( !in_array($vendor_id, $free_delivery_vendors) && frozr_delivery_settings($item_author, 'delivery_fee',true) != 0) {
						if (frozr_vendor_delivery_fee_by($vendor_id) == 'item' && $cart_item['order_l_type'] == 'delivery') {
							$delivery_total[] = $cart_item['quantity'];					
						} elseif (frozr_vendor_delivery_fee_by($vendor_id) != 'item' && $cart_item['order_l_type'] == 'delivery') {
							$delivery_total[0] = 'bycart';
						}
					} else {
						$delivery_total[0] = 'free';
					}
					
					/*Check preparation time*/
					$preparation_time = frozr_get_product_preparation_time($product_id,false);
					
					if ($preparation_time > 0) {
						$total_order_preparation_time[] = $preparation_time;
					}
					
					$item = array(
						'product_id' => $product_id,
						'quantity' => $cart_item['quantity'],
						'meta_data' => array(
							array(
								'key' => 'order-type',
								'value' => sanitize_text_field($cart_item['order_l_type'])
							)
						)
					);
					
					if ( $cart_item['data']->is_type( 'variation' ) && is_array( $cart_item['variation'] ) ) {
						foreach ( $cart_item['variation'] as $name => $value ) {
							$taxonomy = wc_attribute_taxonomy_name( str_replace( 'attribute_pa_', '', urldecode( $name ) ) );

							if ( taxonomy_exists( $taxonomy ) ) {
								// If this is a term slug, get the term's nice name.
								$term = get_term_by( 'slug', $value, $taxonomy );
								if ( ! is_wp_error( $term ) && $term && $term->name ) {
									$value = $term->name;
								}
								$label = wc_attribute_label( $taxonomy );
							} else {
								// If this is a custom option slug, get the options name.
								$value = apply_filters( 'woocommerce_variation_option_name', $value );
								$label = wc_attribute_label( str_replace( 'attribute_', '', $name ), $cart_item['data'] );
							}

							// Check the nicename against the title.
							if ( '' === $value || wc_is_attribute_in_product_name( $value, $cart_item['data']->get_name() ) ) {
								continue;
							}

							$item['meta_data'][] = array(
								'key'   => $label,
								'value' => $value,
							);
						}
					}
					
					if (isset($cart_item['item_comments'])) {
						$item['meta_data'][] = array(
							'key' => 'special-comments',
							'value' => sanitize_text_field($cart_item['item_comments'])
						);
					}
					
					if (isset($cart_item['applied_promotions'])) {
						$item['meta_data'][] = array(
							'key' => 'promotions',
							'value' => sanitize_text_field($cart_item['applied_promotions'])
						);
					}
					
					if (isset($cart_item['variation_id']) && $cart_item['variation_id'] > 0) {
						$item['variation_id'] = $cart_item['variation_id'];
					}
					
					$items[] = $item;
				}
			}

			if ($order_type == 'delivery') {
				/*Get distance to the customer*/
				$distance_to_customer = intval($cart_data[$vendor_id]['distance']);
				
				/*Calculate total delivery for each vendor*/
				$default_delivery = frozr_delivery_settings($vendor_id, 'delivery_fee', false, null, $distance_to_customer);
				$default_adl_item_delivery = frozr_delivery_settings($vendor_id, 'delivery_pro_adtl_cost');
				$total_delivery = array_sum($delivery_total);
				$total_formatted_delivery = 0;

				if (isset($delivery_total[0]) && $delivery_total[0] == 'bycart' || $total_delivery == 1) {
					$total_formatted_delivery =  $default_delivery;
				} elseif ($total_delivery > 1) {
					$_adl_fees_add = ($total_delivery - 1) * $default_adl_item_delivery;
					$total_formatted_delivery = ($default_delivery + $_adl_fees_add);
				}
				
				/*Fee Lines*/
				if ($total_formatted_delivery > 0) {
					$fee_lines = array(
						array(
							'name' => "Total Delivery", // This must not be translated.
							'total' => $total_formatted_delivery,
						)
					);
				}
			}
			
			/*Coupon Lines*/
			$coupon_lines = isset($vendor_coupons[$vendor_id]) ? $vendor_coupons[$vendor_id] : array();
			
			/*Prepare order timings*/
			$order_time = date('H:i',strtotime(current_time('mysql')));
			$order_date = date('Y-m-d',strtotime(current_time('mysql')));
			
			if (!empty($order_timings)) {
				foreach($order_timings as $order_timing) {
					if ($order_timing['vendor_id'] == $vendor_id) {
						$order_time = isset($order_timing['time']) ? $order_timing['time'] : date('H:i',strtotime(current_time('mysql')));
						$order_date = isset($order_timing['date']) ? $order_timing['date'] : date('Y-m-d',strtotime(current_time('mysql')));
						break;
					}
				}
			}
			
			/*Preparation duration*/
			$order_preduration = frozr_cal_total_pretime($total_order_preparation_time,$vendor_id);
			
			/*Meta Data*/
			$meta_data = array(
				array(
					'key' => '_frozr_vendor',
					'value' => $vendor_id
				),
				array(
					'key' => '_order_pretime',
					'value' => date('Y-m-d H:i',strtotime(current_time('mysql')))
				),
				array(
					'key' => '_order_time_date',
					'value' => date('Y-m-d H:i',strtotime($order_time.' '.$order_date))
				),
				array(
					'key' => '_order_preduration',
					'value' => floatval($order_preduration)
				),
				
			);
			
			if ($is_delivery_order) {
				if ($userLocationGeo) {
					$meta_data[] = array(
						'key' => '_user_geo_location',
						'value' => $userLocationGeo
					);
				}
				
				if ($userLocation) {
					$meta_data[] = array(
						'key' => '_user_del_location',
						'value' => $userLocation
					);
				}
			}

			
			$final_vendor_order = apply_filters('norsani_app_create_single_order', array(
				'payment_method' => $payment_method,
				'payment_method_title' => $payment_method == 'cod' ? __('Cash on Delivery','norsani-api') : $payment_method,
				'billing' => $billing_form,
				'set_paid' => false,
				'status' => 'on-hold',
				'meta_data' => $meta_data,
				'line_items' => $items,
				'coupon_lines' => $coupon_lines,
				'fee_lines' => $fee_lines,
			), $cart, $vendor_id );
			
			if($customer_note) {
				$final_vendor_order['customer_note'] = $customer_note;
			}
			
			$checkout_data[] = $final_vendor_order;
		}
		
		$data = array(
			'create' => $checkout_data,
		);

		return new WP_REST_Response( json_encode($data), 200 );
	}
	
	public function get_checkout_form($request) {
		$creds = $request->get_params();
		$cart_data = $creds['cartData'];
		$coupons = $creds['coupons'];
		$order_type = $creds['orderType'];
		$user_email = isset($creds['userEmail']) ? sanitize_email($creds['userEmail']) : false;
		
		$cart = new WC_Cart();
		$checkout = WC()->checkout();
						
		$vendors = $free_delivery_vendors = $checkout_data = $vendor_random_products = array();

		$totals = norsani_api_calculate_cart_totals($cart,$cart_data,$coupons);
		
		/*Get cart vendors*/
		foreach($totals['added_data'] as $vendor_id => $data) {
			$product_id = intval($data['items'][0]['product_id']);
			
			if (!in_array($vendor_id, $vendors)) {
				$vendor_random_products[$vendor_id] = $product_id;
				$vendors[] = $vendor_id;
			}
		}
		
		/*Check coupons for each vendor*/
		foreach ( $cart->get_applied_coupons() as $code ) {
			$coupon = new WC_Coupon( $code );
			if($coupon->get_free_shipping() && $order_type == 'delivery') {
				$coupon_id = wc_get_coupon_id_by_code( $coupon->get_code() );
				$free_delivery_vendors[] = get_post_field('post_author', $coupon_id);
			}
		}
		
		/*Get each vendor's cart data*/
		foreach ($vendors as $vendor_id) {
			$delivery_total = $total_preparation_time = array();
			$total_formatted_delivery = null;
			$preparation_time = __('Immediately','norsani-api');
			
			$vendor = norsani_get_vendor($vendor_id);
			$is_pre_order = !frozr_is_rest_open($vendor_id) ? true : false;

			foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
				$_product		= $cart_item['data'];
				$product_id		= $cart_item['product_id'];
				$item_author	= get_post_field('post_author', $product_id);
				
				if ($item_author == $vendor_id) {
					$pretime = frozr_get_product_preparation_time($product_id,false);
					
					if ($pretime > 0) {
						$total_preparation_time[] = $pretime;
					}

					/*Check delivery fee*/
					if ( !in_array($vendor_id, $free_delivery_vendors) && frozr_delivery_settings($vendor_id, 'delivery_fee',true) != 0) {
						if (frozr_vendor_delivery_fee_by($vendor_id) == 'item' && $cart_item['order_l_type'] == 'delivery') {
							$delivery_total[] = $cart_item['quantity'];					
						} elseif (frozr_vendor_delivery_fee_by($vendor_id) != 'item' && $cart_item['order_l_type'] == 'delivery') {
							$delivery_total[0] = 'bycart';
						}
					} else {
						$delivery_total[0] = 'free';
					}
				}
			}
			
			if ($order_type == 'delivery') {
				/*Get distance to the customer*/
				$distance_to_customer = intval($cart_data[$vendor_id]['distance']);
				
				/*Calculate total delivery for each vendor*/
				$default_delivery = frozr_delivery_settings($vendor_id, 'delivery_fee', false, null, $distance_to_customer);
				$default_adl_delivery = frozr_delivery_settings($vendor_id, 'delivery_pro_adtl_cost');
				$total_delivery = array_sum($delivery_total);

				if (isset($delivery_total[0]) && $delivery_total[0] == 'free') {
					$total_formatted_delivery = __('Free Delivery','norsani-api');
				} elseif (isset($delivery_total[0]) && $delivery_total[0] == 'bycart' || $total_delivery == 1) {
					$total_formatted_delivery =  $default_delivery;
				} elseif ($total_delivery == 0) {
					$total_formatted_delivery = __('N/A','norsani-api');
				} elseif ($total_delivery > 1) {
					$_adl_fees_add = ($total_delivery - 1) * $default_adl_delivery;
					$total_formatted_delivery = ($default_delivery + $_adl_fees_add);
				}
				
				/*Calculate preparation time*/
				$total_time = frozr_cal_total_pretime($total_preparation_time,$vendor_id);
				if ($total_time) {
					$preparation_time = $total_time .' '. __('Minutes','norsani-api');
				} else {
					$total_time = 0;
				}
			}

			$timeing_options = frozr_get_order_pretime_options($vendor_id,$vendor_random_products[$vendor_id],false);
			
			$checkout_data[] = array(
				'vendor_id' => $vendor_id,
				'name' => $vendor->get_store_name(),
				'address' => $vendor->get_store_address(),
				'is_pre_order' => $is_pre_order,
				'preparation_time' => $preparation_time,
				'total_delivery' => $total_formatted_delivery,
				'timing_options' => $timeing_options,
			);
		}
		
		/*Billing Form*/
		$billing_form = $checkout->get_checkout_fields( 'billing' );
		
		if ($order_type != 'delivery') {
			unset( $billing_form[ 'billing_address_1' ] );
			unset( $billing_form[ 'billing_address_2' ] );
		}
		
		/*Get Braintree Token*/
		$client_token = null;
		$gateway = norsani_api_get_braintree_gateway();
		$token_args = array();
		
		if ($gateway) {
			$customer_obj = $user_email ? get_user_by('email',$user_email) : false;
			if ($customer_obj) {
				try {
					$customer = $gateway->customer()->find($customer_obj->ID);
					$token_args['customerId'] = $customer_obj->ID;
				} catch ( Exception $e) {
					$token_args = array();
				}
			}
		
			$client_token = $gateway->clientToken()->generate($token_args);
		}
		
		$data = array(
			'totals' => $totals['totals'],
			'messages' => $totals['messages'],
			'added_data' => $totals['added_data'],
			'checkout_data' => $checkout_data,
			'login_required' => $checkout->is_registration_enabled(),
			'billing_form' => $billing_form,
			'braintree_token' => $client_token,
		);
		
		return new WP_REST_Response( json_encode($data), 200 );
	}
}
add_action('rest_api_init', function () {
	$norsani_api = new Norsani_Checkout_API_Route();
	$norsani_api->register_routes();
});