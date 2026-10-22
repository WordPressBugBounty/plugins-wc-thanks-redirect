<?php

/**
 * @package     Thank You Page
 * @since       4.4.0
 */

namespace NeeBPlugins\Wctr\Compatibility\Elementor;

use NeeBPlugins\Wctr\Front;

class OrderDetailsWidget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'wctr-order-details';
	}

	public function get_title() {
		return __( 'Order Details (Thank You Page)', 'wc-thanks-redirect' );
	}

	public function get_icon() {
		return 'eicon-price-table';
	}

	public function get_categories() {
		return array( 'wctr-thank-you-page' );
	}

	public function get_keywords() {
		return array( 'thank you', 'order', 'woocommerce', 'receipt' );
	}

	protected function register_controls() {

		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'wc-thanks-redirect' ),
			)
		);

		$this->add_control(
			'wctr_notice',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => __( 'Shows the customer\'s order number, date, items, totals and address — the same output as the [TRFW_ORDER_DETAILS] shortcode. No settings needed.', 'wc-thanks-redirect' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {

		$output = Front::get_instance()->shortcode_order_details();

		if ( empty( $output ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			echo '<p style="padding:12px;border:1px dashed #ccd0d4;background:#f6f7f7;color:#646970;">' . esc_html__( 'Order Details — the customer\'s order summary appears here after checkout.', 'wc-thanks-redirect' ) . '</p>';
			return;
		}

		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already-escaped HTML built in Front::shortcode_order_details().
	}
}
