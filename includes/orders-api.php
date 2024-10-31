<?php
/**
 * Norsani Orders API
 *
 * @package norsani-api
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Norsani_Orders_API_Route extends Norsani_General_API_Route {

	/**
	* Register the routes for the objects of the controller.
	*/
	public function register_routes() {
		
		$namespace = NORSANI_API_NAMESPACE;
		
		register_rest_route( $namespace, '/getorders', array(
		array(
			'methods'				=> WP_REST_Server::READABLE,
			'callback'				=> array( $this, 'getorders' ),
			'permission_callback'	=> array( $this, 'get_general_permissions_check' ),
			'args'					=> array(
					'email' => array(
						'required' => true,
						'type' => 'string',
						'description' => __('User Email','norsani-api'),
						'validate_callback' => function( $value, $request, $param) {
							return is_email($value);
						},
						'sanitize_callback' => function( $value, $request, $param) {
							return sanitize_email($value) ;
						}
					),
					'numberorders' => array(
						'type' => 'integer',
						'description' => __('Orders count to retrieve','norsani-api'),
						'sanitize_callback' => function( $value, $request, $param) {
							return intval($value) ;
						}
					),
				)
			)
		));
		
		register_rest_route( $namespace, '/getorder/(?P<id>[\d]+)', array(
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
			'callback'				=> array( $this, 'getorder' ),
			'permission_callback'	=> array( $this, 'get_general_permissions_check' ),
		)
		));
	}
	
	public function getorder($request) {
		$order_id			= intval($request['id']);
		$order				= wc_get_order( $order_id );
		$vendor_id			= frozr_get_order_author($order->get_id());
		$vendor_obj			= norsani_get_vendor($vendor_id);
		$store_name			= $vendor_obj->get_store_name();
		$line_items			= $order->get_items();
		$payment_gateway	= wc_get_payment_gateway_by_order( $order );
		$line_items_fee		= $order->get_items( 'fee' );
		$order_taxes		= wc_tax_enabled() ? $order->get_taxes() : array();
		$refunds			= $order->get_refunds();
		$order_date			= $order->get_date_created()->date( 'Y-m-d h:i:s a' );
		$hidden_order_itemmeta = apply_filters(
			'woocommerce_hidden_order_itemmeta', array(
				'_qty',
				'_tax_class',
				'_product_id',
				'_variation_id',
				'_line_subtotal',
				'_line_subtotal_tax',
				'_line_total',
				'_line_tax',
				'method_id',
				'cost',
				'_reduced_stock',
			)
		);	
		
		/*General and Vendor*/
		$data = array(
			'vendor_id'		=> $vendor_id,
			'store_name'	=> $store_name,
			'store_logo'	=> $vendor_obj->get_store_logo() ? wp_get_attachment_url(absint( $vendor_obj->get_store_logo() )) : '',
			'address'		=> $vendor_obj->get_store_address(),
			'is_store_open'	=> frozr_is_rest_open($vendor_id),
			'coupons'		=> $order->get_used_coupons(),
			'payment_gateway' => false !== $payment_gateway ? ( ! empty( $payment_gateway->method_title ) ? $payment_gateway->method_title : $payment_gateway->get_title() ) : __( 'Payment gateway', 'norsani-api' ),
			'discount'		=> 0 < $order->get_total_discount() ? $order->get_total_discount() : 0,
			'sub_total'		=> $order->get_subtotal(),
			'total'			=> $order->get_total(),
			'date'			=> $order_date,
			'status'		=> $order->get_status()
		);
		
		/*Taxes*/
		if (wc_tax_enabled()) {
			foreach ( $order->get_tax_totals() as $code => $tax ) {
				$data['taxes'][] = array('label' => esc_html($tax->label), 'amount' => preg_replace("/&#?[a-z0-9]{2,8};/i","",strip_tags( $tax->formatted_amount )) );
			}
		}
		
		/*Fees*/
		foreach ( $line_items_fee as $item_id => $item ) {
			$data['fees'][] = array('name' => $item->get_name() ? $item->get_name() : __( 'Fee', 'norsani-api' ), 'amount' => $item->get_total());
		}
		
		/*Refunds*/
		if ( $refunds ) {
			foreach ( $refunds as $refund ) {
				$who_refunded = new WP_User( $refund->get_refunded_by() );
				if ( $who_refunded->exists() ) {
					$details = sprintf(
						/* translators: 1: refund id 2: refund date 3: username */
						esc_html__( 'Refund #%1$s - %2$s by %3$s', 'norsani-api' ),
						esc_html( $refund->get_id() ),
						esc_html( wc_format_datetime( $refund->get_date_created(), get_option( 'date_format' ) . ', ' . get_option( 'time_format' ) ) ),
						sprintf(
							'<abbr class="refund_by" title="%1$s">%2$s</abbr>',
							/* translators: 1: ID who refunded */
							sprintf( esc_attr__( 'ID: %d', 'norsani-api' ), absint( $who_refunded->ID ) ),
							esc_html( $who_refunded->display_name )
						)
					);
				} else {
					$details = sprintf(
						/* translators: 1: refund id 2: refund date */
						esc_html__( 'Refund #%1$s - %2$s', 'norsani-api' ),
						esc_html( $refund->get_id() ),
						esc_html( wc_format_datetime( $refund->get_date_created(), get_option( 'date_format' ) . ', ' . get_option( 'time_format' ) ) )
					);
				}
				$data['refunds'][] = array(
					'id'		=> $refund->get_id(),
					'details'	=> $details,
					'reason'	=> $refund->get_reason() ? esc_html( $refund->get_reason() ) : null,
					'amount'	=> $refund->get_amount(),
					'currency'	=> $refund->get_currency()
				);
			}
		}
		
		/*Items*/
		foreach ( $line_items as $item_id => $item ) {
			$product	= $item->get_product();
			$taxes		= array();
			$meta_data	= array();
			
			if ( ( $tax_data = $item->get_taxes() ) && wc_tax_enabled() ) {
				foreach ( $order_taxes as $tax_item ) {
					$tax_item_id		= $tax_item->get_rate_id();
					$tax_item_total		= isset( $tax_data['total'][ $tax_item_id ] ) ? $tax_data['total'][ $tax_item_id ] : '';
					$tax_item_subtotal	= isset( $tax_data['subtotal'][ $tax_item_id ] ) ? $tax_data['subtotal'][ $tax_item_id ] : '';
					
					$taxes[] = array(
						'id'		=> $tax_item_id,
						'total'		=> $tax_item_total,
						'subtotal'	=> $tax_item_subtotal,
					);
				}
			}
			
			/*Meta data*/
			if ( $metadata = $item->get_formatted_meta_data() ) {
				foreach ( $metadata as $meta_id => $meta ) {
				if ( in_array( $meta->key, $hidden_order_itemmeta, true ) ) {
					continue;
				}
					$meta_data[strip_tags($meta->display_key)] = strip_tags($meta->display_value);
				}
			}
			
			$data['items'][] = array(
				'id' => $product->get_parent_id() > 0 ? $product->get_parent_id() : $product->get_id(),
				'name' => $item->get_name(),
				'imagelink' => wp_get_attachment_thumb_url(absint($product->get_image_id())),
				'variation_id' => $item->get_variation_id() ? $item->get_variation_id() : 0,
				'price' => $order->get_item_total($item),
				'total' => $order->get_line_total($item, true, false),
				'discount' => $item->get_subtotal() !== $item->get_total() ? wc_format_decimal( $order->get_item_subtotal( $item, false, false ) - $order->get_item_total( $item, false, false )) : 0,
				'qty' => $item->get_quantity(),
				'meta_data' => $meta_data,
				'currency' => $order->get_currency(),
				'refunded' => $refunded = $order->get_total_refunded_for_item( $item_id ) ? $refunded : 0,
				'taxes' => $taxes,
			);
		}

		return new WP_REST_Response( json_encode($data), 200 );
	}
	
	public function getorders($request) {

		$creds = $request->get_params();
		$user_email = sanitize_email($creds['email']);
		$order_count = intval($creds['numberorders']);
		$data = array();
		
		$orders = wc_get_orders(array('limit' => -1, 'customer' => $user_email));
		
		$orders_count = count($orders);
		
		if($orders_count > 0) {
			foreach ( $orders as $order_id ) {
				if ($order_count == 0 ) {
					break;
				}
				
				$order				= wc_get_order( $order_id );
				$is_parent_order	= frozr_is_parent_order( $order->get_id() );
				
				if ($is_parent_order) {
					continue;
				}
				
				$item_count			= count($order->get_items( 'line_item' ));
				$vendor_id			= frozr_get_order_author($order->get_id());
				$vendor_obj			= norsani_get_vendor($vendor_id);
				$store_name			= $vendor_obj->get_store_name();
				$order_date			= $order->get_date_created()->date( 'Y-m-d h:i a' );
			
				$data['orders'][] = array(
					'order_id'	=> $order->get_id(),
					'date'		=> $order_date,
					'details'	=> sprintf(__('%s %s from %s','norsani-api'), $item_count, _n('product', 'products',$item_count,'norsani-api'), $store_name),
					'status'	=> $order->get_status()
				);
				$order_count--;
			}
		}
		
		$data['count'] = $orders_count;
		
		return new WP_REST_Response( json_encode($data), 200 );
	}
}
add_action('rest_api_init', function () {
	$norsani_api = new Norsani_Orders_API_Route();
	$norsani_api->register_routes();
});