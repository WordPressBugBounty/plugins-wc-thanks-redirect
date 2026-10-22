<?php

/**
 * @package     Thank You Page
 * @since       4.1.6
 */

namespace NeeBPlugins\Wctr;

use NeeBPlugins\Wctr\Compatibility\PYS_Tracking as Wctr_PYS;
use NeeBPlugins\Wctr\Modules\SandBoxPayment as WCTR_SandBoxPayment;
use NeeBPlugins\Wctr\Modules\Mode as WCTR_Mode;

class Front {

	private static $instance;

	/**
	 * Get Instance
	 *
	 * @since 4.1.6
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
	 * @since 4.1.6
	 */
	public function __construct() {
		/* Add Plugin shortcode */
		add_shortcode( 'TRFW_ORDER_DETAILS', array( $this, 'shortcode_order_details' ) );
		add_shortcode( 'TRFW_COUPON', array( $this, 'shortcode_coupon' ) );
		/* Redirect to thank you */
		add_action( 'woocommerce_thankyou', array( $this, 'safe_redirect' ), 99, 1 );
		/* Add action for Footer */
		add_action( 'wp_footer', array( $this, 'datalayer_purchase_event' ) );
		// Add the SandBox Payment gateway to the list of available payment gateways
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_sandbox_gateway' ) );
		// Load tracking
		$this->load_tracking();
	}

	/**
	 * Shortcode Order Details
	 *
	 * @since 4.2.0
	 * @return string
	 */
	public function shortcode_order_details() {

		$order_key = ! empty( $_GET['order_key'] ) ? wp_kses_post( wp_unslash( $_GET['order_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_id  = wc_get_order_id_by_order_key( $order_key );
		$order     = wc_get_order( $order_id );

		if ( ! $order ) {
			return '';
		}

		return $this->render_order_details_html( $order );
	}

	/**
	 * Render Order Details HTML
	 *
	 * Shared renderer behind the [TRFW_ORDER_DETAILS] shortcode and the
	 * admin preview tool, so both stay in sync from a single source.
	 *
	 * @since 4.4.0
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return string
	 */
	public function render_order_details_html( $order ) {

		$order_items           = $order->get_items( apply_filters( 'woocommerce_purchase_order_item_types', 'line_item' ) );
		$show_purchase_note    = $order->has_status( apply_filters( 'woocommerce_purchase_note_order_statuses', array( 'completed', 'processing' ) ) );
		$show_customer_details = $order->get_user_id() === get_current_user_id();
		$downloads             = $order->get_downloadable_items();
		$show_downloads        = $order->has_downloadable_item() && $order->is_download_permitted();

		ob_start();

		/**
		 * Fires before the plugin renders the order details block.
		 *
		 * @since 4.4.0
		 * @param \WC_Order $order Order object.
		 */
		do_action( 'wctr_before_order_details', $order );

		?>
		<div class="wctr-order-details">
			<?php
			if ( $show_downloads ) {
				wc_get_template(
					'order/order-downloads.php',
					array(
						'downloads'  => $downloads,
						'show_title' => true,
					)
				);
			}
			?>
			<div class="woocommerce">
				<div class="woocommerce-order">
					<section class="woocommerce-order-details">
						<?php do_action( 'woocommerce_order_details_before_order_table', $order ); ?>
						<header class="entry-header">
							<h1 class="entry-title" itemprop="headline"><?php esc_html_e( 'Order received', 'wc-thanks-redirect' ); ?></h1>
						</header>
						<p>&nbsp;</p>
						<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">

							<li class="woocommerce-order-overview__order order">
								<?php esc_html_e( 'Order number', 'wc-thanks-redirect' ); ?>: <strong><?php echo wp_kses_post( $order->get_order_number() ); ?></strong>
							</li>

							<li class="woocommerce-order-overview__order order">
								<?php
								$wctr_date_format = get_option( 'date_format' );
								esc_html_e( 'Date', 'wc-thanks-redirect' );
								?>
								: <strong><?php echo wp_kses_post( wp_date( $wctr_date_format, $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time() ) ); ?></strong>
							</li>

							<li class="woocommerce-order-overview__order order">
								<?php esc_html_e( 'Name', 'wc-thanks-redirect' ); ?>: <strong><?php echo wp_kses_post( $order->get_formatted_billing_full_name() ); ?></strong>
							</li>

							<li class="woocommerce-order-overview__order order">
								<?php esc_html_e( 'Payment Method', 'wc-thanks-redirect' ); ?>: <strong><?php echo wp_kses_post( $order->get_payment_method_title() ); ?></strong>
							</li>

						</ul>
						<h2 class="woocommerce-order-details__title"><?php esc_html_e( 'Order details', 'wc-thanks-redirect' ); ?></h2>

						<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">

							<thead>
								<tr>
									<th class="woocommerce-table__product-name product-name"><?php esc_html_e( 'Product', 'wc-thanks-redirect' ); ?></th>
									<th class="woocommerce-table__product-table product-total"><?php esc_html_e( 'Total', 'wc-thanks-redirect' ); ?></th>
								</tr>
							</thead>

							<tbody>
								<?php
								do_action( 'woocommerce_order_details_before_order_table_items', $order );
								foreach ( $order_items as $item_id => $item ) {

									$product = $item->get_product();

									wc_get_template(
										'order/order-details-item.php',
										array(
											'order'               => $order,
											'item_id'             => $item_id,
											'item'                => $item,
											'show_purchase_note'  => $show_purchase_note,
											'purchase_note'       => $product ? $product->get_purchase_note() : '',
											'product'             => $product,
										)
									);
								}
								do_action( 'woocommerce_order_details_after_order_table_items', $order );
								?>
							</tbody>

							<tfoot>
								<?php
								foreach ( $order->get_order_item_totals() as $key => $total ) {
									?>
									<tr>
										<th scope="row"><?php echo esc_html( $total['label'] ); ?></th>
										<td><?php echo wp_kses_post( $total['value'] ); ?></td>
									</tr>
									<?php
								}
								?>
								<?php if ( $order->get_customer_note() ) : ?>
									<tr>
										<th><?php esc_html_e( 'Note', 'wc-thanks-redirect' ); ?>:</th>
										<td><?php echo wp_kses_post( nl2br( wptexturize( $order->get_customer_note() ) ) ); ?></td>
									</tr>
								<?php endif; ?>
							</tfoot>
						</table>
						<?php do_action( 'woocommerce_order_details_after_order_table', $order ); ?>
					</section>
				</div>
			</div>
			<?php
			if ( $show_customer_details ) {
				wc_get_template( 'order/order-details-customer.php', array( 'order' => $order ) );
			}
			?>
		</div>
		<?php

		/**
		 * Fires after the plugin renders the order details block.
		 *
		 * @since 4.4.0
		 * @param \WC_Order $order Order data.
		 */
		do_action( 'wctr_after_order_details', $order );

		return ob_get_clean();
	}

	/**
	 * Shortcode Coupon
	 *
	 * Renders the post-purchase coupon message configured in Settings, as
	 * long as the selected coupon still exists and hasn't expired. Admins
	 * create the coupon itself in WooCommerce → Coupons; this only surfaces
	 * it on the Thank You page.
	 *
	 * @since 4.4.0
	 * @return string
	 */
	public function shortcode_coupon() {

		$code = get_option( 'wctr_coupon_code', '' );

		if ( empty( $code ) ) {
			return '';
		}

		$coupon = new \WC_Coupon( $code );

		if ( ! $coupon->get_id() ) {
			return '';
		}

		$expiry_date = $coupon->get_date_expires();

		if ( $expiry_date && $expiry_date->getTimestamp() < time() ) {
			return '';
		}

		$message = get_option( 'wctr_coupon_message', Helper::get_instance()->default_coupon_message() );
		$message = str_replace(
			'{coupon_code}',
			'<strong>' . esc_html( $coupon->get_code() ) . '</strong>',
			esc_html( $message )
		);

		return '<p class="wctr-coupon-notice">' . $message . '</p>';
	}

	/**
	 * Build the per-product and global redirect candidates for an order.
	 *
	 * Kept separate from resolve_target_url() so the admin preview tool can
	 * inspect both the "success" and "failed" candidate at once, while
	 * safe_redirect() only cares about the one matching the order's actual
	 * status.
	 *
	 * @since 4.4.0
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return array {
	 *     @type array $product { @type string|null $thankyou @type string|null $failed }
	 *     @type array $global  { @type string|null $thankyou @type string|null $failed }
	 * }
	 */
	private function get_redirect_candidates( $order ) {

		$order_key    = $order->get_order_key();
		$order_string = '&order_key=' . $order_key;

		$product = array(
			'thankyou' => null,
			'failed'   => null,
		);
		$global  = array(
			'thankyou' => null,
			'failed'   => null,
		);

		$redirects = array();

		foreach ( $order->get_items() as $_item ) { // phpcs:ignore
			$product_id  = $_item->get_product_id();
			$thanks_meta = get_post_meta( $product_id, 'wc_thanks_redirect_custom_thankyou', true );

			if ( empty( $thanks_meta ) ) {
				continue;
			}

			$parsed = wp_parse_url( $thanks_meta );

			if ( empty( $parsed['host'] ) ) {
				continue;
			}

			$thanks_url = $parsed['scheme'] . '://' . $parsed['host']
				. ( ! empty( $parsed['port'] ) ? ':' . $parsed['port'] : '' )
				. ( $parsed['path'] ?? '' ) . '?' . ( $parsed['query'] ?? '' ) . $order_string;

			$url_priority = get_post_meta( $product_id, 'wc_thanks_redirect_url_priority', true );

			$redirects[] = array(
				'thankyou' => $thanks_url,
				'failed'   => get_post_meta( $product_id, 'wc_thanks_redirect_custom_failure', true ),
				'priority' => ( '' !== $url_priority ) ? (int) $url_priority : PHP_INT_MAX,
			);
		}

		if ( ! empty( $redirects ) ) {
			array_multisort( array_column( $redirects, 'priority' ), SORT_ASC, $redirects );

			if ( filter_var( $redirects[0]['thankyou'], FILTER_VALIDATE_URL ) ) {
				$product['thankyou'] = $redirects[0]['thankyou'];
			}

			if ( filter_var( $redirects[0]['failed'], FILTER_VALIDATE_URL ) ) {
				$product['failed'] = $redirects[0]['failed'];
			}
		}

		if ( 'yes' === strtolower( (string) get_option( 'wctr_global', 'no' ) ) ) {

			$parsed   = wp_parse_url( (string) get_option( 'wctr_thanks_redirect_url' ) );
			$fail_url = get_option( 'wctr_failed_redirect_url' );

			if ( ! empty( $parsed['host'] ) ) {
				$thanks_url = $parsed['scheme'] . '://' . $parsed['host']
					. ( ! empty( $parsed['port'] ) ? ':' . $parsed['port'] : '' )
					. ( $parsed['path'] ?? '' ) . '?' . ( $parsed['query'] ?? '' ) . $order_string;

				if ( filter_var( $thanks_url, FILTER_VALIDATE_URL ) ) {
					$global['thankyou'] = $thanks_url;
				}
			}

			if ( filter_var( $fail_url, FILTER_VALIDATE_URL ) ) {
				$global['failed'] = $fail_url;
			}
		}

		return array(
			'product' => $product,
			'global'  => $global,
		);
	}

	/**
	 * Resolve the redirect target for an order.
	 *
	 * Product-level redirects always take precedence over the global
	 * fallback, matching the plugin's existing (pre-4.4.0) behaviour.
	 *
	 * @since 4.4.0
	 *
	 * @param \WC_Order   $order  Order object.
	 * @param string|null $status Order status to resolve for; defaults to the order's current status.
	 *
	 * @return array { @type string $type 'product'|'global'|'none' @type string|null $url }
	 */
	public function resolve_target_url( $order, $status = null ) {

		$status     = $status ?? $order->get_status();
		$candidates = $this->get_redirect_candidates( $order );
		$key        = ( 'failed' === $status ) ? 'failed' : 'thankyou';

		if ( ! empty( $candidates['product'][ $key ] ) ) {
			return array(
				'type' => 'product',
				'url'  => $candidates['product'][ $key ],
			);
		}

		if ( ! empty( $candidates['global'][ $key ] ) ) {
			return array(
				'type' => 'global',
				'url'  => $candidates['global'][ $key ],
			);
		}

		return array(
			'type' => 'none',
			'url'  => null,
		);
	}

	/**
	 * Safe Redirect.
	 *
	 * @since 4.2.0
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function safe_redirect( $order_id ) {

		// Basic guards
		if ( ! $order_id || is_admin() || wp_doing_ajax() || defined( 'REST_REQUEST' ) ) {
			return;
		}

		// Prevent infinite redirect loops
		if ( isset( $_GET['wctr_redirect_done'] ) ) { // phpcs:ignore
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		/*
		|--------------------------------------------------------------------------
		| Mollie Async Safety
		|--------------------------------------------------------------------------
		| If returning from Mollie, ensure payment is completed
		| Mollie adds: filter_flag=onMollieReturn
		*/
		if ( isset( $_GET['filter_flag'] ) && 'onMollieReturn' === $_GET['filter_flag'] && ! $order->is_paid() ) { // phpcs:ignore
			return;
		}

		// Sandbox mode: custom redirects only apply to administrators.
		if ( ! WCTR_Mode::applies_to_current_user() ) {
			return;
		}

		$target = $this->resolve_target_url( $order );

		if ( 'none' === $target['type'] || empty( $target['url'] ) ) {
			return;
		}

		$redirect_url = add_query_arg( 'wctr_redirect_done', '1', $target['url'] );

		wp_redirect( esc_url_raw( $redirect_url ) ); // phpcs:ignore WordPress.Security.SafeRedirect
		exit;
	}

	/**
	 * Datalayer purchase event.
	 *
	 * @since 4.2.5
	 * @param array $atts Array of attributes.
	 */
	public function datalayer_purchase_event( $atts = array() ) { // phpcs:ignore

		// Check if order received ID exists in the query var
		$order_id = wc_thanks_redirect_get_order_id();
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Fetch order data
		$order_items = $order->get_items();
		$items_data  = array();

    	foreach ( $order_items as $item_id => $item ) { // phpcs:ignore
			$product      = $item->get_product();
			$item_data    = array(
				'item_name'     => $item->get_name(),
				'item_id'       => $product->get_sku() ? $product->get_sku() : $product->get_id(),
				'price'         => $product->get_price(),
				'item_brand'    => $this->get_product_brand( $product->get_id() ),
				'item_category' => $product ? wc_get_product_category_list( $product->get_id(), ', ' ) : '',
				'quantity'      => $item->get_quantity(),
			);
			$items_data[] = $item_data;
		}

		// DataLayer variables
		$currency       = get_woocommerce_currency();
		$value          = $order->get_total();
		$tax            = $order->get_total_tax();
		$shipping       = $order->get_shipping_total();
		$affiliation    = get_bloginfo( 'name' );
		$transaction_id = $order->get_order_number();
		$coupon_code    = $order->get_coupon_codes() ? implode( ',', $order->get_coupon_codes() ) : '';

		// Prepare the JavaScript dataLayer and Facebook Pixel script
		?>
		<script>
			// DataLayer for GTM
			window.dataLayer = window.dataLayer || [];
			window.dataLayer.push({
				event: 'purchase',
				ecommerce: {
					currency: '<?php echo esc_js( $currency ); ?>',
					value: <?php echo esc_js( $value ); ?>,
					tax: <?php echo esc_js( $tax ); ?>,
					shipping: <?php echo esc_js( $shipping ); ?>,
					affiliation: '<?php echo esc_js( $affiliation ); ?>',
					transaction_id: '<?php echo esc_js( $transaction_id ); ?>',
					coupon: '<?php echo esc_js( $coupon_code ); ?>',
					items: <?php echo wp_json_encode( $items_data ); ?>
				}
			});
		</script>
		<?php
	}

	/**
	 * Get Product Brand.
	 *
	 * @since 4.2.5
	 * @param WC_Product $product_id
	 */
	public function get_product_brand( $product_id ) {

		$brand = '';

		if ( ! empty( $product_id ) ) {
			$brand_terms = get_the_terms( $product_id, 'product_brand' );

			if ( ! empty( $brand_terms ) && ! is_wp_error( $brand_terms ) ) {
				$brand = $brand_terms[0]->name; // Use the first brand term.
			}
		}

		return $brand;
	}

	/**
	 * Load Tracking.
	 *
	 * @since 4.2.6
	 * @return void
	 */
	public function load_tracking() {
		if ( isset( $_GET['order_key'] ) && class_exists( '\PixelYourSite\PYS' ) ) { // phpcs:ignore
			Wctr_PYS::get_instance();
		}
	}

	/**
	 * Add the SandBox gateway to the list of available payment gateways.
	 *
	 * @param array $gateways The list of available payment gateways.
	 * @return array The list of available payment gateways with the SandBox gateway added.
	 */
	public function add_sandbox_gateway( $gateways ) {
		$gateways[] = WCTR_SandBoxPayment::class;
		return $gateways;
	}
}
