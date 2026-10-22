<?php

/**
 * @package     Thank You Page
 * @since       4.4.0
 */

namespace NeeBPlugins\Wctr\Compatibility;

use NeeBPlugins\Wctr\Compatibility\Elementor\OrderDetailsWidget;
use NeeBPlugins\Wctr\Compatibility\Elementor\CouponWidget;

class Elementor {

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
	 * Bails out entirely when Elementor isn't active, so the widgets are
	 * only ever registered on sites that have Elementor installed.
	 *
	 * @since 4.4.0
	 */
	public function __construct() {

		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );

		// Elementor hooks Plugin::init() to WP's 'init' action at priority 0,
		// while this class is only constructed from WCTR_Plugin::run_plugin()
		// at the default 'init' priority (10) — so by the time we get here,
		// elementor/init has already fired and templates_manager already
		// exists. Hooking 'elementor/init' here would register a callback
		// for an event that already happened, so call directly instead.
		$this->maybe_create_page_template();
	}

	/**
	 * Register a dedicated "Thank You Page" widget category, so the
	 * plugin's widgets group together in the Elementor panel instead of
	 * scattering across the default categories.
	 *
	 * @since 4.4.0
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 * @return void
	 */
	public function register_category( $elements_manager ) {

		$elements_manager->add_category(
			'wctr-thank-you-page',
			array(
				'title' => __( 'Thank You Page', 'wc-thanks-redirect' ),
				'icon'  => 'fa fa-plug',
			)
		);
	}

	/**
	 * Register the Order Details and Post-Purchase Coupon widgets.
	 *
	 * Both reuse the exact same render methods as the [TRFW_ORDER_DETAILS]
	 * and [TRFW_COUPON] shortcodes (and the matching Gutenberg blocks), so
	 * output never drifts apart between the three.
	 *
	 * @since 4.4.0
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public function register_widgets( $widgets_manager ) {

		$widgets_manager->register( new OrderDetailsWidget() );
		$widgets_manager->register( new CouponWidget() );
	}

	/**
	 * Register a ready-made "Thank You Page Layout" template in Elementor's
	 * native Template Library (the "My Templates" source), so it's available
	 * to insert from the "Add Template" library the moment a user starts
	 * building the Thank You page in Elementor — no separate plugin screen.
	 *
	 * Runs once: the created template's post ID is cached in an option, and
	 * re-checked (self-healing if the template post was deleted) rather than
	 * re-creating it on every request.
	 *
	 * @since 4.4.0
	 * @return void
	 */
	public function maybe_create_page_template() {

		$template_id = get_option( 'wctr_elementor_template_id' );

		if ( $template_id && 'elementor_library' === get_post_type( $template_id ) ) {
			return;
		}

		$source = \Elementor\Plugin::$instance->templates_manager->get_source( 'local' );

		if ( ! $source ) {
			return;
		}

		$template_id = $source->save_item(
			array(
				'title'   => __( 'Thank You Page — Order Details & Coupon', 'wc-thanks-redirect' ),
				'type'    => 'page',
				'content' => $this->get_page_template_content(),
			)
		);

		if ( is_wp_error( $template_id ) ) {
			return;
		}

		update_option( 'wctr_elementor_template_id', $template_id );

		wp_set_object_terms( $template_id, __( 'Thank You Page', 'wc-thanks-redirect' ), \Elementor\TemplateLibrary\Source_Local::TAXONOMY_CATEGORY_SLUG );
	}

	/**
	 * Build the Elementor element tree for the "Thank You Page Layout"
	 * template: a heading, an intro line, and the Order Details and Coupon
	 * widgets. Element IDs are regenerated automatically by
	 * Source_Local::save_item(), so no IDs are needed here.
	 *
	 * @since 4.4.0
	 * @return array
	 */
	private function get_page_template_content() {

		return array(
			array(
				'elType'   => 'container',
				'settings' => array(
					'content_width' => 'boxed',
				),
				'elements' => array(
					array(
						'elType'     => 'widget',
						'widgetType' => 'heading',
						'settings'   => array(
							'title'       => __( 'Thank You for Your Order!', 'wc-thanks-redirect' ),
							'header_size' => 'h1',
							'align'       => 'center',
						),
						'elements'   => array(),
					),
					array(
						'elType'     => 'widget',
						'widgetType' => 'text-editor',
						'settings'   => array(
							'editor' => '<p style="text-align:center;">' . esc_html__( 'Your order has been received and is now being processed.', 'wc-thanks-redirect' ) . '</p>',
						),
						'elements'   => array(),
					),
					array(
						'elType'     => 'widget',
						'widgetType' => 'wctr-order-details',
						'settings'   => array(),
						'elements'   => array(),
					),
					array(
						'elType'     => 'widget',
						'widgetType' => 'wctr-coupon',
						'settings'   => array(),
						'elements'   => array(),
					),
				),
			),
		);
	}
}
