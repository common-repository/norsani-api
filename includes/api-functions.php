<?php

// don't call the file directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action('frozr_norsani_init', 'norsani_plugin_loaded');
function norsani_plugin_loaded() {
	if (!has_nav_menu('norsani_app_user')) {
		register_nav_menus( array(
			'norsani_app_user' => __( 'Norsani Mobile App User Menu', 'norsani-api' ),
		));
	}
}

/**
 * Calculate cart totals
 * 
 * @param object $cart
 * @return array
*/
function norsani_api_calculate_cart_totals(&$cart, $cart_data, $coupons) {
	
	if (version_compare( WC_VERSION, '3.6.1', '>=' )) {
		include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
		include_once WC_ABSPATH . 'includes/class-wc-cart.php';
		include_once WC_ABSPATH . 'includes/class-wc-tax.php';
		include_once WC_ABSPATH . 'includes/class-wc-customer.php';
		include_once WC_ABSPATH . 'includes/class-wc-session-handler.php';

		wc_load_cart();
	}
	
	$order_type = null;
	
	wp_cache_set( 'norsani_mobile_cart_data', $cart_data, 'api_request', 60 );

	$cart_contents = $cart_vendors = $added_data = $messages = $cross_sells = $free_delivery_vendors = array();
	
	foreach($cart_data as $vendor_id => $vendor_data) {
		
		$items = array_map('wc_clean', $vendor_data['items']);
		
		foreach($items as $item) {

			$product_id = intval($item['productID']);
			$variation_id = intval($item['variationID']);
			$quantity = intval($item['qty']);
			$order_type = sanitize_text_field($item['orderType']);
			$special_notes = sanitize_text_field($item['specialNotes']);
			$variation = $item['variations'] ? array_map('wc_clean',$item['variations']) : array();
			$variations = array();
			$cart_item_data = array('order_l_type' => $order_type);			
			$product_data = wc_get_product( $variation_id ? $variation_id : $product_id );
			$vendor_obj = norsani_get_vendor($vendor_id);
			$remaining_qty = frozr_product_max_orders($product_id);
			$cart_item_data = (array) apply_filters( 'woocommerce_add_cart_item_data', $cart_item_data, $product_id, $variation_id, $quantity );
			$cart_id = $cart->generate_cart_id( $product_id, $variation_id, $variations, $cart_item_data );
			$cart_item_key = $cart->find_product_in_cart( $cart_id );
			$in_cart_quantity	= $cart_item_key ? $cart->cart_contents[ $cart_item_key ]['quantity'] : 0;
			$store_time_sts = frozr_rest_status($vendor_id);
			
			/*Availability check*/
			if (frozr_vendor_is_manual_offline($vendor_id)) {
				$messages[] = sprintf(__('Sorry %s is %s.','norsani-api'), $vendor_obj->get_store_name(), $store_time_sts );
				continue;
			}

			if (!frozr_is_rest_open($vendor_id) && frozr_manual_vendor_online() || !frozr_is_seller_enabled($vendor_id)) {
				$messages[] = sprintf(__('Sorry you cannot order from %s at this time.','norsani-api'), $vendor_obj->get_store_name() );
				continue;
			}

			if (!frozr_is_order_type_accepted_today($order_type,$vendor_id)) {
				$messages[] = frozr_is_order_type_available_today_notice($order_type,$vendor_id);
				continue;
			}

			if (!frozr_is_order_type_accepted_now($order_type,$vendor_id)) {
				$messages[] = sprintf(__('Sorry, %s service is not available at the moment. Please choose another service.','norsani-api'),$order_type);
				continue;
			}
			
			if (!frozr_is_product_available_today($product_id)) {
				$messages[] = frozr_product_availability_notice($product_id);
				continue;
			}

			if (frozr_max_orders_reached($product_id)) {
				$messages[] = sprintf(__('Sorry, we cannot receive any more orders for %s today, please come back another day.','norsani-api'), $product_data->get_title());
				continue;
			}

			if ($remaining_qty && $remaining_qty < $quantity) {
				$messages[] = sprintf(__('Sorry, You cannot order that much quantity of %s for today. Please add a quantity lower than %s','norsani-api'), $product_data->get_title(), $remaining_qty);
				continue;
			}

			/* Security check*/
			if ( !frozr_check_item_timing($product_id) || $quantity <= 0 || ! $product_data || 'trash' === $product_data->get_status() ) {
				$messages[] = sprintf(__('Sorry, you cannot order %s at this time','norsani-api'), $product_data->get_title() );
				continue;
			}

			if ( $in_cart_quantity > 0 && $product_data->is_sold_individually()) {
				$messages[] = sprintf( __( 'You cannot add another %s to your cart.', 'norsani-api' ), $product_data->get_title() );
				continue;
			}

			/* Check product is_purchasable*/
			if ( ! $product_data->is_purchasable() ) {
				$messages[] = sprintf(__( 'Sorry, %s cannot be purchased at this time.', 'norsani-api' ), $product_data->get_title());
				continue;
			}

			/* Stock check - only check if we're managing stock and backorders are not allowed*/
			if ( ! $product_data->is_in_stock() ) {
				$messages[] = sprintf( __( 'You cannot add %s to the cart because quantity for today has finished.', 'norsani-api' ), $product_data->get_title() );
				continue;
			}

			/* Stock check - this time accounting for what's already in-cart*/
			if ( $managing_stock = $product_data->managing_stock() ) {
				$products_qty_in_cart = $cart->get_cart_item_quantities();

				if ( $product_data->is_type( 'variation' ) && true === $managing_stock ) {
					$check_qty = isset( $products_qty_in_cart[ $variation_id ] ) ? $products_qty_in_cart[ $variation_id ] : 0;
				} else {
					$check_qty = isset( $products_qty_in_cart[ $product_id ] ) ? $products_qty_in_cart[ $product_id ] : 0;
				}

				/**
				 * Check stock based on all items in the cart.
				 */
				if ( ! $product_data->has_enough_stock( $check_qty + $quantity ) ) {
					$messages[] = sprintf( __( 'You cannot add that amount of %s to the cart, only %s is available today and you already have %s in your cart.', 'norsani-api' ), $product_data->get_title(), $product_data->get_stock_quantity(), $check_qty );
					continue;
				}
			}
			
			if (!apply_filters('frozr_can_proceed_add_to_cart',true,$vendor_id,$vendor_data['items'],$item)) {
				$message[] = apply_filters('norsani_app_custom_cart_error_message', sprintf(__( 'Sorry, %s cannot be purchased at this time.', 'norsani-api' ), $product_data->get_title()), $vendor_id,$vendor_data['items'],$item);
				continue;
			}
			
			/*Start add to cart process*/
			if (!empty($special_notes)) {
				$cart_item_data['item_comments'] = $special_notes;
			}
			
			if (!empty($variation)) {
				foreach($variation as $key => $val) {
					$variations['attribute_' . sanitize_title($key)] = $val;
				}
			}

			// If cart_item_key is set, the item is already in the cart.
			if ( $cart_item_key ) {
				$new_quantity = $quantity + $cart->cart_contents[ $cart_item_key ]['quantity'];
				$cart->set_quantity( $cart_item_key, $new_quantity, false );

			} else {
				$cart_item_key = $cart_id;

				$cart->cart_contents[ $cart_item_key ] = apply_filters( 'woocommerce_add_cart_item', array_merge( $cart_item_data, array(
					'key'			=> $cart_item_key,
					'product_id'	=> $product_id,
					'variation_id'	=> $variation_id,
					'variation'		=> $variations,
					'quantity' 		=> $quantity,
					'data'			=> $product_data,
					'data_hash'		=> wc_get_cart_item_data_hash( $product_data ),
				) ), $cart_item_key );
				
			}
		}
	}
	
	$cart->cart_contents = apply_filters( 'woocommerce_cart_contents_changed', $cart->cart_contents );

	/*Apply coupons if any*/
	if (!empty($coupons) && wc_coupons_enabled()) {
		
		$ordered_coupons_data = $valid_coupons = array();
		
		$coupons = array_map('wc_clean', $coupons);
		
		/*Process coupons*/
		foreach($coupons as $coupon) {

			$coupon_code = wc_format_coupon_code( $coupon );
			
			// Get the coupon.
			$the_coupon = new WC_Coupon( $coupon_code );

			// Prevent adding coupons by post ID.
			if ( $the_coupon->get_code() !== $coupon_code ) {
				$messages[] = sprintf(__('Coupon %s does not exist.','norsani-api'), $coupon_code);
				continue;
			}

			// Check it can be used with cart.
			$discounts = new WC_Discounts( $cart );
			$valid     = $discounts->is_coupon_valid( $the_coupon );

			if ( is_wp_error( $valid ) ) {
				$messages[] = $valid->get_error_message();
				continue;
			}

			if ( $the_coupon->get_individual_use() && isset($coupons[1])) {
				$valid_coupons = array($coupon_code);
				$messages[] = sprintf(__('Coupon %s can only be used individually.','norsani-api'), $coupon_code);
				break;
			}
			
			// Check if we have a free delivery coupon
			if ($the_coupon->get_free_shipping() && $order_type == 'delivery') {
				$vendor_id = get_post_field('post_author', $the_coupon->get_id());
				$free_delivery_vendors[] = $vendor_id; // this is for later use when calculating delivery fee.
			}
			
			$valid_coupons[] = $coupon_code;
		}
		
		$cart->set_applied_coupons(array_unique($valid_coupons));
	}
	
	/*Reorder data for app*/
	foreach($cart->get_cart() as $key => $val) {
		$product_id = $val['product_id'];
		$vendor_id = get_post_field('post_author', $product_id);
		
		$val['price'] = floatval($val['data']->get_price());
		
		/*Prepare added_data for app*/
		$added_data[$vendor_id]['items'][] = $val;
		
		/*Get an array of cart vendors for later use*/
		$cart_vendors[] = intval($vendor_id);
	}
	
	$cart->calculate_totals();
	
	/*Get cart sub total*/
	$cart_subtotal_info = null;
	$cart_subtotal = 0.0;
	
	if ( $cart->display_prices_including_tax() ) {
		$cart_subtotal = $cart->get_subtotal() + $cart->get_subtotal_tax();

		if ( $cart->get_subtotal_tax() > 0 && ! wc_prices_include_tax() ) {
			$cart_subtotal_info = WC()->countries->inc_tax_or_vat();
		}
	} else {
		$cart_subtotal = $cart->get_subtotal();

		if ( $cart->get_subtotal_tax() > 0 && wc_prices_include_tax() ) {
			$cart_subtotal_info = WC()->countries->ex_tax_or_vat();
		}
	}
	
	/*Get coupon data*/
	$coupons = array();
	foreach ( $cart->get_coupons() as $code => $coupon ) {
		if ( is_string( $coupon ) ) {
			$coupon = new WC_Coupon( $coupon );
		}

		$free_delivery = null;

		$amount = $cart->get_coupon_discount_amount( $coupon->get_code(), $cart->display_cart_ex_tax );
		$discount_amount = '-' . $amount;

		if ( $coupon->get_free_shipping() && empty( $amount ) && $order_type == 'delivery' ) {
			$vendor_id = get_post_field('post_author', $coupon->get_id());
			$vendor_obj = norsani_get_vendor($vendor_id);
			
			$free_delivery = sprintf(__( '+ Free delivery from %s', 'norsani-api' ),$vendor_obj->get_store_name());
		}

		$coupons[] = array('name' => wc_cart_totals_coupon_label( $coupon, false ), 'amount' => $discount_amount, 'free_delivery' => $free_delivery, 'code' => $code);
	}
	
	/*Get cart fees*/
	$fees = array();
	foreach ( $cart->get_fees() as $fee ) {
		$cart_totals_fee = $cart->display_prices_including_tax() ? $fee->total + $fee->tax : $fee->total;
		$fee_name = $fee->name == 'Total Delivery' ? __('Total Delivery','norsani-api') : sanitize_text_field($fee->name);
		$fees[] = array('name' => $fee_name, 'fee' => $cart_totals_fee);
	}
	
	/*Get cart taxes*/
	$taxes = array();
	if ( wc_tax_enabled() && ! $cart->display_prices_including_tax() ) {
		$taxable_address = WC()->customer->get_taxable_address();
		$estimated_text  = WC()->customer->is_customer_outside_base() && ! WC()->customer->has_calculated_shipping()
				? sprintf( __( '(estimated for %s)', 'norsani-api' ), WC()->countries->estimated_for_prefix( $taxable_address[0] ) . WC()->countries->countries[ $taxable_address[0] ] )
				: '';

		if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) {
			foreach ( $cart->get_tax_totals() as $code => $tax ) {
				$taxes[] = array('name' => esc_html( $tax->label ) . $estimated_text, 'amount' => $tax->amount);
			}
		}
		else {
			$taxes[] = array('name' => esc_html( WC()->countries->tax_or_vat() ) . $estimated_text, 'amount' => $cart->get_taxes_total());
		}
	}
	
	/*Get total*/
	$cart_total = $cart->get_total( 'edit' );
	$cart_total_info = null;

	// If prices are tax inclusive, show taxes here.
	if ( wc_tax_enabled() && $cart->display_prices_including_tax() ) {
		$tax_string_array = array();
		$cart_tax_totals  = $cart->get_tax_totals();

		if ( get_option( 'woocommerce_tax_total_display' ) === 'itemized' ) {
			foreach ( $cart_tax_totals as $code => $tax ) {
				$tax_string_array[] = sprintf( '%s %s', $tax->amount, $tax->label );
			}
		} elseif ( ! empty( $cart_tax_totals ) ) {
			$tax_string_array[] = sprintf( '%s %s', $cart->get_taxes_total( true, false ), WC()->countries->tax_or_vat() );
		}

		if ( ! empty( $tax_string_array ) ) {
			$taxable_address = WC()->customer->get_taxable_address();
			/* translators: %s: country name */
			$estimated_text = WC()->customer->is_customer_outside_base() && ! WC()->customer->has_calculated_shipping() ? sprintf( ' ' . __( 'estimated for %s', 'norsani-api' ), WC()->countries->estimated_for_prefix( $taxable_address[0] ) . WC()->countries->countries[ $taxable_address[0] ] ) : '';
			/* translators: %s: tax information */
			$cart_total_info = sprintf( __( '(includes %s)', 'norsani-api' ), implode( ', ', $tax_string_array ) . $estimated_text );
		}
	}
	
	/*Get cross sells*/
	$item_ids = $cart->get_cross_sells();
	if (!empty($item_ids)) {
		foreach ($item_ids as $item_id){
			$item = get_post($item_id);
			$item_author = $item->post_author;
			$item_id = $item->ID;
			$product_obj = wc_get_product($item_id);
			$product_id = $product_obj->get_id();
			$vendor_obj = norsani_get_vendor($item_author);
			
			if ( !frozr_is_seller_enabled($item_author) || !frozr_is_product_available_today($product_id) || !frozr_is_purchasable_product($product_id) || $item->post_status == 'offline') {
				continue;
			}
			
			/*item cats*/
			$discts = get_terms( 'product_cat', 'fields=names&hide_empty=0' );
			$itemcats = wp_get_post_terms( $product_id, 'product_cat', array("fields" => "names") );
			$itemcats_slug = array();
			if (is_array($itemcats)) {
				foreach ( $itemcats as $itemcat ) {
					$itemcats_slug[] = $itemcat;
				}
				$item_cats = join( ' ', $itemcats_slug );
			} elseif ( ! empty( $discts ) && ! is_wp_error( $discts )) {
				$item_cats = $itemcats;
			}
			
			if ( $product_obj->is_type( 'variable' ) ) {
				$lowest  = $product_obj->get_variation_price( 'min', false );
				$highest = $product_obj->get_variation_price( 'max', false );
				if ( $lowest === $highest ) {
					$price  = $lowest;
				} else {
					$price  = $lowest.' - '.$highest;
				}
			} else {
				$price  = $product_obj->get_price();
			}

			$cross_sells[] = apply_filters('norsani_app_cross_sells_data',array(
				'id' => $product_id,
				'title' => $product_obj->get_name(),
				'is_variable' => $product_obj->is_type( 'variable' ),
				'author' => $vendor_obj->get_store_name(),
				'author_id' => $item_author,
				'cats' => $item_cats,
				'image' => wp_get_attachment_thumb_url( $product_obj->get_image_id() ),
				'price' => $price,
				'on_sale' => $product_obj->is_on_sale(),
				'regular_price' => $product_obj->get_regular_price(),
				'sale_price' => $product_obj->get_sale_price(),
				'rating' => floatval(min( 5, round( $product_obj->get_average_rating(), 1 ) )),
			), $product_id);
		}
	}
	
	$data = apply_filters('norsani_app_add_to_cart_data', array(
		'added_data' => $added_data,
		'messages' => $messages,
		'cross_sells' => $cross_sells,
		'totals' => array(
			'total' => $cart_total,
			'total_info' => $cart_total_info,
			'sub_total' => $cart_subtotal,
			'sub_total_info' => $cart_subtotal_info,
			'coupons' => $coupons,
			'fees' => $fees,
			'taxes' => $taxes,
		)
	), $cart, $cart_data, $coupons);

	return $data;
}

/**
 * Load App Data
 * 
 * @param array $request_params
 * @return array
*/
function norsani_api_load_app_data($request_params = array()) {
	
	if (count($request_params) < 2) {
		return array();
	}
	
	$vendors_type = $request_params['vendortype'];
	$customer_email = isset($request_params['customer']) ? $request_params['customer'] : null;
	$order_type = $request_params['ordertype'];
	$locality = isset($request_params['locality']) ? $request_params['locality'] : null;
	$allowed_vendors = frozr_get_allowed_vendors_types();
	
	if (!in_array($vendors_type, $allowed_vendors)) {
		$vendors_type = null;
	}
	
	/*Recommended Vendors*/
	$recommended_vendors = array();
	if (isset($customer_email) && is_email($customer_email)) {
		/*Get customer purchase history*/
		$get_customer_obj = get_user_by('email', $customer_email);
		$statuses = array_keys( wc_get_order_statuses() );
		$customer_orders = get_posts( apply_filters( 'norsani_app_reco_vendors_args', array(
			'numberposts'	=> 12,
			'meta_key'		=> '_customer_user',
			'meta_value'	=> $get_customer_obj->ID,
			'post_type'		=> wc_get_order_types( 'view-orders' ),
			'post_status'	=> $statuses,
			'fields'		=> 'ids',
		) ) );

		if ( $customer_orders ) {
			foreach ( $customer_orders as $customer_order ) {
				$order				= wc_get_order( $customer_order );
				$vendor_id			= frozr_get_order_author($order->get_id());
				$vendor_obj			= norsani_get_vendor($vendor_id);
				$reco_vendor_type 	= $vendor_obj->get_store_type();
				
				if (!in_array($vendor_id,$recommended_vendors) && $reco_vendor_type == $vendors_type || !in_array($vendor_id,$recommended_vendors) && !$vendors_type) {
					$recommended_vendors[] = $vendor_id;
				}
			}
		}
	}
	
	/*Get coupons*/
	$coupons = array();
	$coupons_args = apply_filters('norsani_app_coupons_args', array(
		'posts_per_page'=> -1,
		'offset'		=> 0,
		'post_type'		=> 'shop_coupon',
		'post_status'	=> array('publish'),
		'orderby'		=> 'post_date',
		'order'			=> 'DESC',
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
		
		$expiry_date	= $coupon_obj->get_date_expires() ? $coupon_obj->get_date_expires()->date( 'Y-m-d H:i' ) : null;
		$free_shipping	= $coupon_obj->get_free_shipping();
		$coupon_ends	= null;
		
		if ($expiry_date) {
			$now = new DateTime(date('Y-m-d H:i',strtotime(current_time('mysql'))));
			$expiry = new DateTime($expiry_date);
			$diff = $expiry->diff($now);
			if ($diff->m > 0) {
				$coupon_ends = sprintf(__('Ends in %s %s','norsani-api'),$diff->m, _n('Month', 'Months', $diff->m, 'norsani-api'));
			} elseif ($diff->d > 0) {
				$coupon_ends = sprintf(__('Ends in %s %s','norsani-api'),$diff->d, _n('Day', 'Days', $diff->d, 'norsani-api'));
			} elseif ($diff->h > 0) {
				$coupon_ends = sprintf(__('Ends in %s %s','norsani-api'),$diff->h, _n('Hour', 'Hours', $diff->h, 'norsani-api'));
			} elseif ($diff->i > 0) {
				$coupon_ends = sprintf(__('Ends in %s %s','norsani-api'),$diff->i, _n('Minute', 'Minutes', $diff->i, 'norsani-api'));
			} else {
				continue;
			}
		}
		
		$coupons[] = apply_filters('norsani_app_load_coupon_data',array(
			'product_ids'	=> array_map( 'absint', (array) $coupon_obj->get_product_ids() ),
			'vendor_name'	=> $vendor_obj->get_store_name(),
			'vendor_logo'	=> $vendor_obj->get_store_logo() ? wp_get_attachment_thumb_url(absint( $vendor_obj->get_store_logo() )) : '',
			'vendor_cover'	=> $vendor_obj->get_store_banner() ? wp_get_attachment_thumb_url(absint( $vendor_obj->get_store_banner() )) : '',
			'vendor_id'		=> $coupon->post_author,
			'ending'		=> $coupon_ends,
			'expiry_date'	=> $expiry_date,
			'type'			=> $coupon_obj->get_discount_type(),
			'amount'		=> $coupon_obj->get_amount(),
			'free_shipping'	=> 'yes' == $free_shipping,
			'code'			=> $coupon_obj->get_code(),
		), $coupon);
	}
	/*Reorder coupons by expiry date*/
	usort($coupons, function($a, $b) {
		$a = !isset($a['expiry_date']) ? '+ 1 year' : $a['expiry_date'];
		$b = !isset($b['expiry_date']) ? '+ 1 year' : $b['expiry_date'];
		
		return strtotime($a) - strtotime($b);
	});

	
	/*Get special items*/
	$special_items = array();
	$items_args = apply_filters('norsani_app_special_items_args', array(
		'posts_per_page'=> -1,
		'offset'		=> 0,
		'meta_key'		=> 'frozr_special_item_status',
		'meta_value'	=> 'online',
		'post_type'		=> 'product',
		'post_status'	=> array('publish'),
	));
	
	$items_array = get_posts( $items_args );
	foreach ( $items_array as $item ) {
		$item_author = $item->post_author;
		$item_id = $item->ID;
		$product_obj = wc_get_product($item_id);
		$product_id = $product_obj->get_id();
		$vendor_obj = norsani_get_vendor($item_author);

		if (!frozr_is_item_special($item_id)) {
			continue;
		}
		
		if ($vendors_type && $vendor_obj->get_store_type() != $vendors_type || !frozr_is_seller_enabled($item_author) || !frozr_is_product_available_today($product_id) || $order_type && !frozr_is_order_type_accepted_today($order_type,$item_author) || $order_type && !frozr_is_order_type_accepted_now($order_type,$item_author) || !frozr_is_purchasable_product($product_id) || $item->post_status == 'offline') {
			continue;
		}

		if ($locality && !frozr_vendor_in_locality($item_author, $locality)) {
			continue;
		}

		/*item cats*/
		$itemcats = wp_get_post_terms( $product_id, 'product_cat', array("fields" => "names") );
		
		if ( $product_obj->is_type( 'variable' ) ) {
			$lowest  = $product_obj->get_variation_price( 'min', false );
			$highest = $product_obj->get_variation_price( 'max', false );
			if ( $lowest === $highest ) {
				$price  = $lowest;
			} else {
				$price  = $lowest.' - '.$highest;
			}
		} else {
			$price  = $product_obj->get_price();
		}
		
		/*Check if product has a coupon*/
		$has_coupon = false;
		foreach($coupons as $coupon) {
			$coupon_obj = new WC_Coupon( $coupon['code'] );
			if ($coupon_obj->is_valid_for_product($product_obj)) {
				$has_coupon = true;
				break;
			}
		}
		
		$special_items[$item_author][] = apply_filters('norsani_app_load_special_item',array(
			'id' => $product_id,
			'is_variable' => $product_obj->is_type( 'variable' ),
			'title' => $product_obj->get_name(),
			'author' => $vendor_obj->get_store_name(),
			'author_id' => $item_author,
			'categories' => $itemcats,
			'image' => wp_get_attachment_thumb_url( $product_obj->get_image_id() ),
			'price' => $price,
			'on_sale' => $product_obj->is_on_sale(),
			'regular_price' => $product_obj->get_regular_price(),
			'sale_price' => $product_obj->get_sale_price(),
			'has_coupon' => $has_coupon,
			'rating' => floatval(min( 5, round( $product_obj->get_average_rating(), 1 ) )),
		), $product_id);
	}
	

	/*Get featured items*/
	$option = get_option( 'frozr_gen_settings' );
	$options = (! empty( $option['frozr_reco_items']) ) ? $option['frozr_reco_items'] : array('0');
	$featured_items = array();
	$items_args = apply_filters('norsani_app_items_args',array(
		'posts_per_page'=> -1,
		'offset'		=> 0,
		'include'		=> $options,
		'post_type'		=> 'product',
		'post_status'	=> array('publish','offline'),
	));
	
	$items_array = get_posts( $items_args );
	foreach ( $items_array as $item ) {
		
		$item_author = $item->post_author;
		$item_id = $item->ID;
		$product_obj = wc_get_product($item_id);
		$product_id = $product_obj->get_id();
		$vendor_obj = norsani_get_vendor($item_author);
		
		if ($vendors_type && $vendor_obj->get_store_type() != $vendors_type || !frozr_is_seller_enabled($item_author) || !frozr_is_product_available_today($product_id) || $order_type && !frozr_is_order_type_accepted_today($order_type,$item_author) || $order_type && !frozr_is_order_type_accepted_now($order_type,$item_author) || !frozr_is_purchasable_product($product_id) || $item->post_status == 'offline') {
			continue;
		}

		if ($locality && !frozr_vendor_in_locality($item_author, $locality)) {
			continue;
		}
		
		/*item cats*/
		$itemcats = wp_get_post_terms( $product_id, 'product_cat', array("fields" => "names") );
		
		if ( $product_obj->is_type( 'variable' ) ) {
			$lowest  = $product_obj->get_variation_price( 'min', false );
			$highest = $product_obj->get_variation_price( 'max', false );
			if ( $lowest === $highest ) {
				$price  = $lowest;
			} else {
				$price  = $lowest.' - '.$highest;
			}
		} else {
			$price  = $product_obj->get_price();
		}

		/*Check if product has a coupon*/
		$has_coupon = false;
		foreach($coupons as $coupon) {
			$coupon_obj = new WC_Coupon( $coupon['code'] );
			if ($coupon_obj->is_valid_for_product($product_obj)) {
				$has_coupon = true;
				break;
			}
		}

		$featured_items[] = apply_filters('norsani_app_load_featured_item',array(
			'id' => $product_id,
			'is_variable' => $product_obj->is_type( 'variable' ),
			'title' => $product_obj->get_name(),
			'author' => $vendor_obj->get_store_name(),
			'author_id' => $item_author,
			'categories' => $itemcats,
			'image' => wp_get_attachment_thumb_url( $product_obj->get_image_id() ),
			'price' => $price,
			'on_sale' => $product_obj->is_on_sale(),
			'regular_price' => $product_obj->get_regular_price(),
			'sale_price' => $product_obj->get_sale_price(),
			'has_coupon' => $has_coupon,
			'rating' => floatval(min( 5, round( $product_obj->get_average_rating(), 1 ) )),
		), $product_id);
	}

	/*Get Vendors*/
	$norsani_general_options = get_option( 'frozr_gen_settings' );
	$featured_vendors = array();
	$top_rated_vendors = array();

	$args = apply_filters( 'norsani_app_get_vendors_args', array(
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
	$vendors = $vendor_tags = $vendor_tags_raw = array();
	foreach($get_vendor_users as $vendor_user) {
		$vendor_id = $vendor_user->ID;
		$vendor_obj = norsani_get_vendor($vendor_id);
		$store_type = $vendor_obj->get_store_type();
		$accepted_orders = frozr_vendor_accepted_orders($vendor_id);
		$accepted_orders_closed = frozr_vendor_accepted_orders_cl($vendor_id);
		
		if($vendors_type && $store_type != $vendors_type || !isset($accepted_orders[$order_type]) && !in_array($order_type,$accepted_orders_closed)) {
			continue;
		}
		
		if ($locality && !frozr_vendor_in_locality($vendor_id, $locality)) {
			continue;
		}
		
		/*Get featured vendors*/
		if (isset($norsani_general_options['frozr_reco_sellers']) && in_array($vendor_id, $norsani_general_options['frozr_reco_sellers'])) {
			$featured_vendors[] = $vendor_id;
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
		
		/*Get tags*/
		$tags_object = $vendor_obj->get_store_classification();
		$tags_array = array();
		foreach ($tags_object as $tag) {
			$tags_array[] = $tag->name;
		}
		$vendor_tags_raw = array_unique(array_merge($vendor_tags_raw, $tags_array));
		
		$peak_orders = $vendor_obj->get_orders_number_to_set_as_busy();
		$current_processing_orders = frozr_count_user_object('wc-processing', 'shop_order',$vendor_id);
		
		$vendor_logo_src = $vendor_obj->get_store_logo() ? wp_get_attachment_image_src( absint( $vendor_obj->get_store_logo() ), 'small' ) : false;
		$vendor_logo = $vendor_logo_src ? $vendor_logo_src[0] : '';
		$vendor_cover_src = $vendor_obj->get_store_banner() ? wp_get_attachment_image_src( absint( $vendor_obj->get_store_banner() ), 'large' ) : false;
		$vendor_cover = $vendor_cover_src ? $vendor_cover_src[0] : '';

		$vendors[$vendor_id] = apply_filters('norsani_app_load_vendor_data',array(
			'name' => $vendor_obj->get_store_name(),
			'logo' => $vendor_logo,
			'cover' => $vendor_cover,
			'address' => $vendor_obj->get_store_address(),
			'contact_number' => filter_var($vendor_obj->get_contact_number(), FILTER_SANITIZE_NUMBER_INT),
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
		), $vendor_id);
		
		if (frozr_get_readable_seller_rating($vendor_id, false) != 0) {
			$top_rated_vendors[$vendor_id] = frozr_get_readable_seller_rating($vendor_id, false);
		}
	}

	arsort($top_rated_vendors);
	
	/*Get vendor tags data*/
	if (!empty($vendor_tags_raw)) {
		foreach ( $vendor_tags_raw as $vendor_tag ) {
			$term = get_term_by('name', $vendor_tag, 'vendorclass');
			$thumbnail = esc_url(frozr_get_vendorclass_term_thumb($term->term_id));
			$vendor_tags[] = array('termID' => $term->term_id, 'thumb' => $thumbnail, 'slug' => $term->slug, 'name' => $term->name);
		}
	}
	
	$data = apply_filters('norsani_app_load_data',array(
		'vendors'				=> $vendors,
		'vendors_tags'			=> $vendor_tags,
		'featured_vendors'		=> $featured_vendors,
		'featured_items'		=> $featured_items,
		'recommended_vendors'	=> $recommended_vendors,
		'top_rated_vendors'		=> $top_rated_vendors,
		'special_items'			=> $special_items,
		'coupons'				=> $coupons,
	), $request_params);
	
	return $data;
}

/**
 * Get delivery duration of a vendor if any.
 *
 * @param int $vendor_id
 * @return void
*/
function norsani_api_get_vendor_delivery_duration($vendor_id) {
	
	/*Get delivery duration if set to custom*/
	$calculate_duration_per = frozr_vendor_delivery_duration_per($vendor_id);
	$custom_delivery_duration = frozr_vendor_delivery_duration_custom_time($vendor_id);
	$deliveryDuration = $calculate_duration_per == 'custom' ? $custom_delivery_duration : null;
	
	return $deliveryDuration;
}

/**
 * Helper function: get product promotions
 *
 * @param int $product_id
 * @return array
*/
function norsani_api_get_product_promotions($product_id) {
	
	/*Get promotions*/
	$promotions = frozr_product_promotions( $product_id );
	$filtered_promotions = array();
	if (!empty($promotions)) {
		foreach($promotions as $promotion) {
			if ($promotion['get'] == "free_item") {
				$free_product_obj = wc_get_product($promotion['item']);
				if (! is_wp_error($free_product_obj)) {
					$filtered_promotions[] = array(
						'buy' => $promotion['buy'],
						'get' => __('a free','norsani-api'),
						'value' => $free_product_obj->get_name(),
					);
				}
			} elseif ($promotion['get'] == "discount") {
				$filtered_promotions[] = array(
					'buy' => $promotion['buy'],
					'get' => __('discount','norsani-api'),
					'discount' => $promotion['discount'],
				);
			}
		}
	}
	
	return $filtered_promotions;
}

/**
 * Helper function: Get Braintree payment gateway
 *
 * @return object
*/
function norsani_api_get_braintree_gateway() {
	
	require_once ( NORSANI_API_PATH.'/vendor/autoload.php' );
	
	/*Get Braintree Token*/
	$api_options = get_option( 'norsani_api' );
	$b_environment = isset($api_options['norsani_braintree_environment']) ? $api_options['norsani_braintree_environment'] : 'sandbox';
	$b_merchantId = isset($api_options['norsani_braintree_merchantId']) ? $api_options['norsani_braintree_merchantId'] : null;
	$b_publicKey = isset($api_options['norsani_braintree_publicKey']) ? $api_options['norsani_braintree_publicKey'] : null;
	$b_privateKey = isset($api_options['norsani_braintree_privateKey']) ? $api_options['norsani_braintree_privateKey'] : null;
	
	if ($b_merchantId && $b_publicKey && $b_privateKey ) {
		return new Braintree_Gateway([
			'environment' => $b_environment,
			'merchantId' => $b_merchantId,
			'publicKey' => $b_publicKey,
			'privateKey' => $b_privateKey
		]);
	}
	
	return false;
}
