<?php

/**
 * @package     Thank You Page
 * @since       4.1.6
 */

namespace NeeBPlugins\Wctr;

use NeeBPlugins\Wctr\Compatibility\PYS_Tracking as Wctr_PYS;
use NeeBPlugins\Wctr\Modules\SandBoxPayment as WCTR_SandBoxPayment;

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

		$order_key = ! empty( $_GET['order_key'] ) ? wp_kses_post( $_GET['order_key'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_id  = wc_get_order_id_by_order_key( $order_key );
		$order     = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$order_items           = $order->get_items( apply_filters( 'woocommerce_purchase_order_item_types', 'line_item' ) );
		$show_purchase_note    = $order->has_status( apply_filters( 'woocommerce_purchase_note_order_statuses', array( 'completed', 'processing' ) ) );
		$show_customer_details = $order->get_user_id() === get_current_user_id();
		$downloads             = $order->get_downloadable_items();
		$show_downloads        = $order->has_downloadable_item() && $order->is_download_permitted();
		ob_start();
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
							<?php esc_html_e( 'Order number', 'wc-thanks-redirect' ); ?>: <strong><?php echo wp_kses_post( $order_id ); ?></strong>
						</li>
	
						<li class="woocommerce-order-overview__order order">
							<?php
							$wctr_date_format = get_option( 'date_format' );
							esc_html_e( 'Date', 'wc-thanks-redirect' );
							?>
							: <strong><?php echo wp_kses_post( wp_date( $wctr_date_format, strtotime( $order->get_date_created() ) ) ); ?></strong>
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
										'order'         => $order,
										'item_id'       => $item_id,
										'item'          => $item,
										'show_purchase_note' => $show_purchase_note,
										'purchase_note' => $product ? $product->get_purchase_note() : '',
										'product'       => $product,
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
									<td><?php echo ( 'payment_method' === $key ) ? wp_kses_post( $total['value'] ) : wp_kses_post( $total['value'] ); // phpcs:ignore ?></td>
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
		/**
		 * Action hook fired after the order details.
		 *
		 * @since 4.4.0
		 * @param WC_Order $order Order data.
		 */
		do_action( 'woocommerce_after_order_details', $order );

		if ( $show_customer_details ) {
			wc_get_template( 'order/order-details-customer.php', array( 'order' => $order ) );
		}

		$shortcode_output = ob_get_clean();
		return $shortcode_output;
	}

	/**
	 * Safe Redirect.
	 *
	 * @since 4.2.0
	 * @param WC_Order $order_id
	 * @return void
	 */
	public function safe_redirect( $order_id ) {
		$wctr_global = get_option( 'wctr_global' );

		$order     = wc_get_order( $order_id );
		$order_key = $order->order_key;

		$order_status = $order->get_status();

		$order_items = $order->get_items();
		$redirects   = array();
		$priority    = array();

		foreach ($order_items as $key => $_item) { // phpcs:ignore
			$product_id              = $_item->get_product_id();
			$product_meta_thanks_url = get_post_meta( $product_id, 'wc_thanks_redirect_custom_thankyou', true );

			if ( ! empty( $product_meta_thanks_url ) ) {
				$order_string  = "&order_key=$order_key";
				$thank_you_url = wp_parse_url( get_post_meta( $product_id, 'wc_thanks_redirect_custom_thankyou', true ) );
				$url_priority  = get_post_meta( $product_id, 'wc_thanks_redirect_url_priority', true );

				$product_thanks = $thank_you_url['scheme'] . '://' . $thank_you_url['host'] . $thank_you_url['path'] . '?' . ( ! empty( $thank_you_url['query'] ) ? $thank_you_url['query'] : '' ) . $order_string;
				$product_failed = get_post_meta( $product_id, 'wc_thanks_redirect_custom_failure', true );

				$priority['thankyou'] = $product_thanks;
				$priority['failed']   = $product_failed;
				$priority['priority'] = $url_priority;

				$redirects[] = $priority;

			}
		}

		if ( ! empty( $redirects ) ) {

			array_multisort( array_column( $redirects, 'priority' ), SORT_ASC, $redirects );

			if ( $order_status !== 'failed' ) {
					// Check If URL is valid
				if ( filter_var( $redirects[0]['thankyou'], FILTER_VALIDATE_URL ) ) {
					wp_redirect( $redirects[0]['thankyou'] );
					exit;
				}
			} else {
				// Check If URL is valid
				if ( filter_var( $redirects[0]['failed'], FILTER_VALIDATE_URL ) ) {
					wp_redirect( $redirects[0]['failed'] );
					exit;
				}
			}
		}

		if ( isset( $wctr_global ) && strtolower( $wctr_global ) === 'yes' ) {
			$thank_you_url = get_option( 'wctr_thanks_redirect_url' );
			$fail_url      = get_option( 'wctr_failed_redirect_url' );

			$thank_you_url = wp_parse_url( $thank_you_url );

			$order_string = "&order_key=$order_key";

			$thanks_url = $thank_you_url['scheme'] . '://' . $thank_you_url['host'] . ( ! empty( $thank_you_url['port'] ) ? ':' . $thank_you_url['port'] : '' ) . $thank_you_url['path'] . '?'
			. ( ! empty( $thank_you_url['query'] ) ? $thank_you_url['query'] : '' )
			. $order_string;

			if ( $order_status !== 'failed' ) {
				wp_redirect( $thanks_url );
				exit;
			} else {
				wp_redirect( $fail_url );
				exit;
			}
		}
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
