<?php

/**
 * @package     Thank You Page
 * @since       4.2.6
 */

namespace NeeBPlugins\Wctr\Compatibility;

/**
 * PYS Tracking class
 *
 * @since       4.2.6
 */
class PYS_Tracking {

	private static $instance;
	private $pys;

	/**
	 * Get Instance
	 *
	 * @since 4.2.6
	 * @return object initialized object of class.
	 */
	public static function get_instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;

		$this->pys = PYS::instance();
	}

	/**
	 * Constructor
	 *
	 * @since 4.2.6
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'add_tracking_event' ), 20 );
	}

	/**
	 * Add Tracking Event
	 *
	 * @since 4.2.6
	 */
	public function add_tracking_event() {

		// Initiate FB Pixel Classes
		$pixel = \PixelYourSite\Facebook();
		$pys   = \PixelYourSite\PYS();

		if ( $pixel->enabled() && $pixel->configured() ) {

			$purchase_params                        = $this->woo_purchase_event_params();
			$purchase_params['payload']['pixelIds'] = $pixel->getPixelIDs();

			$event = new \PixelYourSite\SingleEvent(
				'woo_purchase',
				'static',
				'woo'
			);

			// Set parameters and payload
			$event->params  = $purchase_params['params'];
			$event->payload = $purchase_params['payload'];
			// Set parameters for the pixel
			$pixel->slug       = 'facebook';
			$pixel->values     = get_option( 'pys_facebook', null );
			$pixel->option_key = 'pys_facebook';
			// Add Facebook Static Event
			$pys->getEventsManager()->addStaticEvent( $event, $pixel, 'woo' );
		}
	}

	/**
	 * Get Purchase Event Params
	 *
	 * @since 4.2.6
	 *
	 * @return array $event
	 */
	private function woo_purchase_event_params() {

		$order_id = wc_thanks_redirect_get_order_id();
		$event    = array();

		if ( absint( $order_id ) > 0 ) {

			$order = wc_get_order( $order_id );

			if ( $order ) {

				$content_ids    = array();
				$contents       = array();
				$content_names  = array();
				$category_names = array();
				$tags           = array();
				$num_items      = 0;

				foreach ( $order->get_items() as $item ) {

					$product = $item->get_product();
					if ( $product ) {
						$quantity   = $item->get_quantity();
						$num_items += $quantity;

						$content_ids[]   = $product->get_id();
						$content_names[] = $product->get_name();

						// Get product category (first category only for FB param)
						$product_categories = get_the_terms( $product->get_id(), 'product_cat' );
						if ( $product_categories && ! is_wp_error( $product_categories ) ) {
							$category_names[] = $product_categories[0]->name;
						}

						// ✅ Get product tags (Facebook Pixel style)
						$product_tags = get_the_terms( $product->get_id(), 'product_tag' );
						if ( $product_tags && ! is_wp_error( $product_tags ) ) {
							foreach ( $product_tags as $tag_term ) {
								$tags[] = $tag_term->name;
							}
						}

						// Build contents array as in the object structure
						$contents[] = array(
							'id'         => $product->get_id(),
							'quantity'   => $quantity,
							'item_price' => (float) ( $item->get_total() / $quantity ),
						);
					}
				}

				// Unique, trimmed tags — Facebook Pixel expects up to 100 tags
				$tags        = array_unique( $tags );
				$tags        = array_slice( $tags, 0, 100 );
				$tags_string = implode( ', ', $tags );

				$event['params'] = array(
					'content_type'  => 'product',
					'content_ids'   => $content_ids,
					'content_name'  => implode( ', ', $content_names ),
					'category_name' => implode( ', ', array_unique( $category_names ) ),
					'contents'      => $contents,
					'tags'          => $tags_string,
					'num_items'     => $num_items,
					'value'         => (float) $order->get_total(),
					'currency'      => $order->get_currency(),
					'order_id'      => $order_id,
					'fees'          => (float) $order->get_total_fees(),
					'page_title'    => get_the_title(),
					'post_type'     => get_post_type(),
					'post_id'       => get_the_ID(),
					'plugin'        => 'PixelYourSite',
					'user_role'     => 'customer',
					'event_url'     => get_permalink() . '?order_id=' . $order_id,
				);

				$event['payload'] = array(
					'delay'     => 0,
					'type'      => 'static',
					'name'      => 'Purchase',
					'woo_order' => $order_id,
					'pixelIds'  => array(), // Will be populated by PYS
					'eventID'   => wp_generate_uuid4(),
				);

			}
		}

		return $event;
	}
}
