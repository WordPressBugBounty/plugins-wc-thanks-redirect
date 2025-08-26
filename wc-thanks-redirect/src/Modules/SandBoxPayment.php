<?php

/**
 * @package     Thank You Page
 * @since       4.2.7
*/

namespace NeeBPlugins\Wctr\Modules;

use NeeBPlugins\Wctr\Helper as WCTR_Helper;
use NeeBPlugins\Wctr\Compatibility\SandBoxPaymentBlocksSupport;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class SandBoxPayment extends \WC_Payment_Gateway {

	public $instructions;

    /**
     * Init
     *
     * @since 4.2.7
     */
	public function __construct() {
		$this->id                 = 'sandboxpaymentgateway-wctr';
		$this->icon               = apply_filters( 'woocommerce_sandbox_payment_gateway_icon', '' );
		$this->has_fields         = false;
		$this->method_title       = __( 'SandBox Payment', 'wc-thanks-redirect' );
		$this->method_description = __( 'Simulate test purchases – only visible to Administrators.', 'wc-thanks-redirect' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		$this->title        = $this->get_option( 'title', __( 'SandBox Payment (Test Only)', 'wc-thanks-redirect' ) );
		$this->description  = $this->get_option( 'description', __( 'This payment method is only available to administrators for simulating test orders.', 'wc-thanks-redirect' ) );
		$this->instructions = $this->get_option( 'instructions', __( 'This order was placed using the SandBox Payment Gateway (Admin only).', 'wc-thanks-redirect' ) );
		$this->enabled      = $this->get_option( 'enabled', 'yes' );

		// Restrict gateway visibility for admins only
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'restrict_to_admins' ) );

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

		$this->init_blocks_integration();
	}

    /**
     * Init Form Fields
     * 
     *  * @since 4.2.7
     */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'      => array(
				'title'   => __( 'Enable/Disable', 'wc-thanks-redirect' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable SandBox Payment Gateway', 'wc-thanks-redirect' ),
				'default' => 'yes',
			),
			'title'        => array(
				'title'   => __( 'Title', 'wc-thanks-redirect' ),
				'type'    => 'text',
				'default' => __( 'SandBox Payment (Test Only)', 'wc-thanks-redirect' ),
			),
			'description'  => array(
				'title'   => __( 'Description', 'wc-thanks-redirect' ),
				'type'    => 'textarea',
				'default' => __( 'This payment method is only available to administrators for simulating test orders.', 'wc-thanks-redirect' ),
			),
			'instructions' => array(
				'title'   => __( 'Instructions', 'wc-thanks-redirect' ),
				'type'    => 'textarea',
				'default' => __( 'This order was placed using the SandBox Payment Gateway (Admin only).', 'wc-thanks-redirect' ),
			),
		);
	}

	/**
	 * Restrict gateway to administrators only
	 */
	public function restrict_to_admins( $available_gateways ) {
		if ( ! current_user_can( 'administrator' ) ) {
			unset( $available_gateways[ $this->id ] );
		}
		return $available_gateways;
	}

    /**
     * Output for the payment gateway.
     *
     * @access public
     */
	public function thankyou_page() {
		if ( $this->instructions ) {
			echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) );
		}
	}

    /**
     * Add content to the WC emails.
     *
     * @access public
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) { // phpcs:ignore
		if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() ) {
			echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) ) . PHP_EOL;
		}
	}

    /**
     * Process payment
     *
     * @since 4.2.7
     */
	public function process_payment( $order_id ) {
		if ( absint( $order_id ) > 0 ) {
			$order = wc_get_order( $order_id );

			// Mark as processing (simulate successful payment)
			$order->payment_complete();

			// Reduce stock levels
			wc_reduce_stock_levels( $order_id );

			// Empty cart
			WC()->cart->empty_cart();

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}
	}

    /**
     * Init Blocks integration
     *
     * @since 4.2.7
     */
	public function init_blocks_integration() {
		if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			return;
		}

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( $registry ) {
				if ( ! class_exists( __NAMESPACE__ . '\SandBoxPaymentBlocksSupport.php' ) ) {
					require_once __DIR__ . '/SandBoxPaymentBlocksSupport.php';
				}
				$registry->register( new SandBoxPaymentBlocksSupport() );
			}
		);

		add_filter(
			'woocommerce_block_cart_and_checkout_payment_methods',
			function ( $payment_method_ids ) {
				if ( current_user_can( 'administrator' ) && in_array( $this->id, WC()->payment_gateways->get_payment_gateway_ids(), true ) ) {
					$payment_method_ids[] = $this->id;
				}
				return array_unique( $payment_method_ids );
			}
		);
	}
}
