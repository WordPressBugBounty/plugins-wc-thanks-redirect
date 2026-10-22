<?php

/**
 * @package     Thank You Page
 * @since       4.4.0
 */

namespace NeeBPlugins\Wctr\Modules;

use NeeBPlugins\Wctr\Front as WctrFront;

/**
 * Admin order preview.
 *
 * Lets a shop manager pick a real, existing order and open its actual Thank
 * You page — the same URL and redirect flow a customer would hit — without
 * placing a new order.
 *
 * @since 4.4.0
 */
class Preview {

	private static $instance;

	protected $namespace = 'wctr-api';
	protected $rest_base = 'v1';

	/**
	 * Get Instance
	 *
	 * @since 4.4.0
	 * @return object initialized object of class.
	 */
	public static function get_instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @since 4.4.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes
	 *
	 * These use `manage_woocommerce` (not `manage_options`) so Shop
	 * Managers can use the preview, matching the capability the Thank You
	 * Page settings screen itself already requires.
	 *
	 * @since 4.4.0
	 * @return void
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			$this->rest_base . '/preview/orders',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'search_orders' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'search' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$this->rest_base . '/preview/orders/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'render_preview' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'validate_callback' => function ( $value ) {
								return is_numeric( $value );
							},
						),
					),
				),
			)
		);
	}

	/**
	 * Permission check shared by both preview routes.
	 *
	 * @since 4.4.0
	 * @return bool
	 */
	public function permission_check() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Search orders by order number, customer name or email.
	 *
	 * @since 4.4.0
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public function search_orders( \WP_REST_Request $request ) {

		$search = $request->get_param( 'search' );

		$args = array(
			'limit'   => 20,
			'orderby' => 'date',
			'order'   => 'DESC',
			'type'    => 'shop_order',
		);

		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		$orders  = wc_get_orders( $args );
		$results = array();

		foreach ( $orders as $order ) {
			$name = $order->get_formatted_billing_full_name();

			$results[] = array(
				'id'   => $order->get_id(),
				'text' => sprintf(
					/* translators: 1: order number, 2: customer name or email, 3: order status */
					__( '#%1$s – %2$s (%3$s)', 'wc-thanks-redirect' ),
					$order->get_order_number(),
					$name ? $name : $order->get_billing_email(),
					wc_get_order_status_name( $order->get_status() )
				),
			);
		}

		return new \WP_REST_Response( $results, 200 );
	}

	/**
	 * Render the preview payload for one order.
	 *
	 * @since 4.4.0
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public function render_preview( \WP_REST_Request $request ) {

		$order_id = (int) $request->get_param( 'id' );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Order not found.', 'wc-thanks-redirect' ) ),
				404
			);
		}

		$front  = WctrFront::get_instance();
		$target = $front->resolve_target_url( $order );

		$redirect_label = __( 'Default WooCommerce order received page', 'wc-thanks-redirect' );

		if ( 'product' === $target['type'] ) {
			$redirect_label = __( 'Per-product redirect', 'wc-thanks-redirect' );
		} elseif ( 'global' === $target['type'] ) {
			$redirect_label = __( 'Global Thank You Page', 'wc-thanks-redirect' );
		}

		$mode          = Mode::get();
		$date_created  = $order->get_date_created();
		$billing_name  = $order->get_formatted_billing_full_name();
		$customer_line = $billing_name ? $billing_name : __( 'Guest', 'wc-thanks-redirect' );

		if ( $order->get_billing_email() ) {
			$customer_line .= ' <' . $order->get_billing_email() . '>';
		}

		$response = array(
			'order'       => array(
				'id'       => $order->get_id(),
				'number'   => $order->get_order_number(),
				'status'   => wc_get_order_status_name( $order->get_status() ),
				'date'     => $date_created ? wc_format_datetime( $date_created ) : '',
				'customer' => $customer_line,
			),
			'redirect'    => array(
				'type'  => $target['type'],
				'url'   => $target['url'],
				'label' => $redirect_label,
			),
			'mode'        => array(
				'value'              => $mode,
				'customer_sees_this' => Mode::LIVE === $mode,
			),
			/*
			 * The order's own, real order-received URL. Opening it runs the
			 * site's normal request flow, including this plugin's own
			 * safe_redirect() hook — so if a custom Thank You page is
			 * configured, the browser lands on that exact live page, styled
			 * by whatever theme or page builder actually renders it, rather
			 * than an in-admin approximation of it.
			 */
			'preview_url' => $order->get_checkout_order_received_url(),
		);

		return new \WP_REST_Response( $response, 200 );
	}
}
