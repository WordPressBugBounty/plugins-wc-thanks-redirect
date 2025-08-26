<?php

/**
 * @package     Thank You Page
 * @since       4.2.7
*/

namespace NeeBPlugins\Wctr\Compatibility;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use NeeBPlugins\Wctr\Modules\SandboxPayment as WCTR_SandboxPayment;

/**
 * Sandbox Payment Blocks Support
 *
 * Adds support for WooCommerce Blocks Checkout
 */
final class SandboxPaymentBlocksSupport extends AbstractPaymentMethodType {
	/**
	 * Payment method name defined by payment methods extending this class.
	 *
	 * @var string
	 */
	protected $name    = 'sandboxpaymentgateway-wctr';
	protected $gateway = null;

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_' . $this->name . '_settings', array() );

		foreach ( \WC_Payment_Gateways::instance()->payment_gateways() as $gateway ) {
			if ( $gateway instanceof WCTR_SandboxPayment ) {
				$this->gateway = $gateway;
				break;
			}
		}
	}

	/**
	 * Returns if this payment method should be active.
	 *
	 * @return boolean
	 */
	public function is_active() {
		// Only active if gateway is enabled AND user is admin
		return current_user_can( 'administrator' ) && ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'wctr-sandbox-blocks-integration',
			WCTR_PLUGIN_URL . 'assets/js/wctr-sandbox-block.js',
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			),
			null,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wctr-sandbox-blocks-integration', 'wc-thanks-redirect', WCTR_PLUGIN_DIR . 'languages/' );
		}

		return array( 'wctr-sandbox-blocks-integration' );
	}

	/**
	 * Returns data available to the payment method’s block script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'        => ! empty( $this->settings['title'] ) ? $this->settings['title'] : __( 'Sandbox Payment (Test Only)', 'wc-thanks-redirect' ),
			'description'  => ! empty( $this->settings['description'] ) ? $this->settings['description'] : __( 'This payment method is only available to administrators for simulating test orders.', 'wc-thanks-redirect' ),
			'instructions' => ! empty( $this->settings['instructions'] ) ? $this->settings['instructions'] : __( 'This order was placed using the Sandbox Payment Gateway (Admin only).', 'wc-thanks-redirect' ),
			'supports'     => array(
				'products',
				'refunds',
			),
		);
	}
}
