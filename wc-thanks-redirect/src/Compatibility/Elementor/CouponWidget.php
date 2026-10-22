<?php

/**
 * @package     Thank You Page
 * @since       4.4.0
 */

namespace NeeBPlugins\Wctr\Compatibility\Elementor;

use NeeBPlugins\Wctr\Front;

class CouponWidget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'wctr-coupon';
	}

	public function get_title() {
		return __( 'Post-Purchase Coupon (Thank You Page)', 'wc-thanks-redirect' );
	}

	public function get_icon() {
		return 'eicon-tags';
	}

	public function get_categories() {
		return array( 'wctr-thank-you-page' );
	}

	public function get_keywords() {
		return array( 'thank you', 'coupon', 'discount', 'woocommerce' );
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
				'raw'             => __( 'Shows the coupon configured in Thank You Page → Settings — the same output as the [TRFW_COUPON] shortcode. No settings needed.', 'wc-thanks-redirect' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {

		$output = Front::get_instance()->shortcode_coupon();

		if ( empty( $output ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			echo '<p style="padding:12px;border:1px dashed #ccd0d4;background:#f6f7f7;color:#646970;">' . esc_html__( 'Post-Purchase Coupon — the configured coupon message appears here after checkout.', 'wc-thanks-redirect' ) . '</p>';
			return;
		}

		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already-escaped HTML built in Front::shortcode_coupon().
	}
}
