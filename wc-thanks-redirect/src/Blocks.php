<?php

/**
 * @package     Thank You Page
 * @since       4.4.0
 */

namespace NeeBPlugins\Wctr;

class Blocks {

	private static $instance;

	/**
	 * Get Instance
	 *
	 * @since 4.4.0
	 * @static
	 * @access public
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
		add_filter( 'block_categories_all', array( $this, 'register_block_category' ), 10, 2 );
		// Called directly (not re-hooked to 'init') because get_instance() already
		// runs from within WCTR_Plugin::run_plugin(), itself an 'init' callback at
		// the default priority — a callback added to that same, already-iterating
		// priority bucket is not guaranteed to run within the current request.
		$this->register_blocks();
		$this->register_pattern();
	}

	/**
	 * Register a dedicated "Thank You Page" block category, so the plugin's
	 * blocks group together in the inserter instead of scattering across
	 * core categories.
	 *
	 * @since 4.4.0
	 * @param array $categories Existing block categories.
	 * @return array
	 */
	public function register_block_category( $categories ) {

		return array_merge(
			array(
				array(
					'slug'  => 'wctr-thank-you-page',
					'title' => __( 'Thank You Page', 'wc-thanks-redirect' ),
					'icon'  => null,
				),
			),
			$categories
		);
	}

	/**
	 * Register the Order Details and Post-Purchase Coupon blocks.
	 *
	 * Both are dynamic (server-rendered) blocks that reuse the exact same
	 * render methods as the [TRFW_ORDER_DETAILS] and [TRFW_COUPON]
	 * shortcodes, so block output and shortcode output never drift apart.
	 *
	 * @since 4.4.0
	 * @return void
	 */
	public function register_blocks() {

		$front = Front::get_instance();

		register_block_type(
			WCTR_PLUGIN_DIR . 'assets/blocks/order-details',
			array(
				'render_callback' => array( $front, 'shortcode_order_details' ),
			)
		);

		register_block_type(
			WCTR_PLUGIN_DIR . 'assets/blocks/coupon',
			array(
				'render_callback' => array( $front, 'shortcode_coupon' ),
			)
		);
	}

	/**
	 * Register a ready-made "Thank You Page Layout" block pattern, so the
	 * Order Details and Coupon blocks can be inserted together with one
	 * click from the block inserter's Patterns tab, under the same
	 * "Thank You Page" category as the individual blocks.
	 *
	 * @since 4.4.0
	 * @return void
	 */
	public function register_pattern() {

		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}

		if ( function_exists( 'register_block_pattern_category' ) ) {
			register_block_pattern_category(
				'wctr-thank-you-page',
				array( 'label' => __( 'Thank You Page', 'wc-thanks-redirect' ) )
			);
		}

		register_block_pattern(
			'wctr/thank-you-page-layout',
			array(
				'title'       => __( 'Thank You Page Layout', 'wc-thanks-redirect' ),
				'description' => __( 'A ready-made layout with a heading, order details and a post-purchase coupon.', 'wc-thanks-redirect' ),
				'categories'  => array( 'wctr-thank-you-page' ),
				'keywords'    => array( 'thank you', 'order', 'coupon', 'woocommerce', 'layout', 'template' ),
				'content'     => $this->get_pattern_content(),
			)
		);
	}

	/**
	 * Build the block markup for the "Thank You Page Layout" pattern.
	 *
	 * @since 4.4.0
	 * @return string
	 */
	private function get_pattern_content() {

		$heading = __( 'Thank You for Your Order!', 'wc-thanks-redirect' );
		$intro   = __( 'Your order has been received and is now being processed.', 'wc-thanks-redirect' );

		return '<!-- wp:heading {"textAlign":"center","level":1} -->' .
			'<h1 class="wp-block-heading has-text-align-center">' . esc_html( $heading ) . '</h1>' .
			'<!-- /wp:heading -->' .

			'<!-- wp:paragraph {"align":"center"} -->' .
			'<p class="has-text-align-center">' . esc_html( $intro ) . '</p>' .
			'<!-- /wp:paragraph -->' .

			'<!-- wp:wctr/order-details /-->' .

			'<!-- wp:wctr/coupon /-->';
	}
}
