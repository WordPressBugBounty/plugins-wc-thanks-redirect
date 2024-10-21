<?php

/**
 * @package     Thank You Page
 * @since       4.1.6
*/

namespace NeeBPlugins\Wctr;

class Helper {

	private static $instance;

	/**
	 * Get Instance
	 *
	 * @since 4.1.6
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
	 * Get roles
	 *
	 * @since 4.1.6
	 * @return array
	 */
	public function get_roles() {
		$roles = get_option( 'wp_user_roles' );
		return $roles;
	}

	/**
	 * Get roles
	 *
	 * @since 4.1.6
	 * @return array
	 */
	public function get_roles_list() {
		$roles         = array();
		$wp_user_roles = $this->get_roles();

		if ( ! empty( $wp_user_roles ) ) {
			foreach ( $wp_user_roles as $key => $value ) {
				$roles[] = array(
					'id'   => $key,
					'text' => $value['name'],
				);
			}
		}
		return $roles;
	}

	/**
	 * Get Pages
	 *
	 * @since 4.1.6
	 * @return array
	 */
	public function get_pages() {
		// Get all pages
		$pages = get_pages();

		// Initialize an array to hold page information
		$page_info = array();

		// Loop through each page and extract the required information
		foreach ( $pages as $page ) {
			$page_info[] = array(
				'id'   => $page->ID,
				'text' => get_the_title( $page->ID ), // Page Title
				'url'  => get_permalink( $page->ID ), // Page URL
			);
		}

		return $page_info;
	}

	public function get_payment_gateways() {
		// Get available payment gateways
		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

		// Initialize an array to hold gateway information
		$gateway_info = array();

		// Loop through each available gateway and extract the required information
		foreach ( $available_gateways as $gateway ) {

			$gateway_info[] = array(
				'id'   => $gateway->id,
				'text' => $gateway->get_method_title(),
			);

		}

		return $gateway_info;
	}

	public static function search_product( $keyword = '', $variations_only = false ) {
		global $wpdb;
		$products = array();

		if ( ! empty( $keyword ) ) {
			// Check if the keyword is an integer (for product ID)
			if ( is_int( $keyword ) || ctype_digit( $keyword ) ) {
				// If it's an ID, filter by ID
				$product_id = intval( $keyword );
				$product    = wc_get_product( $product_id );

				if ( $product ) {
					// If variations_only is true and the product is variable, get variations
					if ( $variations_only && $product->is_type( 'variable' ) ) {
						$variation_ids = $product->get_children(); // Get variation IDs
						foreach ( $variation_ids as $variation_id ) {
							$variation  = wc_get_product( $variation_id );
							$products[] = array(
								'id'        => $variation->get_id(),
								'text'      => $product->get_name() . ' - ' . $variation->get_name(), // Concatenate product name with variation name
								'permalink' => $variation->get_permalink(),
								'price'     => get_post_meta( $variation->get_id(), '_price', true ),
							);
						}
					} else {
						// Add the main product if variations_only is false or product is not variable
						$products[] = array(
							'id'        => $product->get_id(),
							'text'      => $product->get_name(),
							'permalink' => $product->get_permalink(),
							'price'     => get_post_meta( $product->get_id(), '_price', true ),
						);
					}
				}
			} else {
				// If it's a keyword, search by product title
				$keyword = sanitize_text_field( $keyword ) . '%';

				// Custom SQL query to get product IDs based on title
				$product_ids = $wpdb->get_col(
					$wpdb->prepare(
						"
						SELECT ID FROM {$wpdb->prefix}posts 
						WHERE post_type = 'product' 
						AND post_status = 'publish' 
						AND post_title LIKE %s
					",
						$keyword
					)
				);

				// Retrieve product details for each found ID
				foreach ( $product_ids as $id ) {
					$product = wc_get_product( $id );

					// If variations_only is true and the product is variable, get variations
					if ( $variations_only && $product->is_type( 'variable' ) ) {
						$variation_ids = $product->get_children(); // Get variation IDs
						foreach ( $variation_ids as $variation_id ) {
							$variation  = wc_get_product( $variation_id );
							$products[] = array(
								'id'        => $variation->get_id(),
								'text'      => $variation->get_name(),
								'permalink' => $variation->get_permalink(),
								'price'     => get_post_meta( $variation->get_id(), '_price', true ),
							);
						}
					} else {
						// Add the main product if variations_only is false or product is not variable
						$products[] = array(
							'id'        => $product->get_id(),
							'text'      => $product->get_name(),
							'permalink' => $product->get_permalink(),
							'price'     => get_post_meta( $product->get_id(), '_price', true ),
						);
					}
				}
			}
		}

		return $products;
	}

	public static function search_categories( $search_term, $post_type = 'post' ) {

		// Get categories that match the search term
		$args = array(
			'taxonomy'   => ( 'product' === $post_type ) ? 'product_cat' : 'category',
			'name__like' => $search_term,
			'hide_empty' => true,
		);

		$categories = get_categories( $args );
		$results    = array();

		// Loop through categories and get posts for the specified post type
		foreach ( $categories as $category ) {
			$results[] = array(
				'id'   => $category->term_id,
				'text' => $category->name,
			);
		}

		return $results;
	}

	public static function search_tags( $search_term, $post_type = 'product' ) {

		// Sanitize the search term
		$search_term = sanitize_text_field( $search_term );

		// Determine the taxonomy based on the post type
		$taxonomy = ( 'product' === $post_type ) ? 'product_tag' : 'post_tag';

		// Get tags that match the search term
		$tags = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'name__like' => $search_term,
				'hide_empty' => false,
			)
		);

		$results = array();

		if ( ! empty( $tags ) ) {
			foreach ( $tags as $tag ) {
				$results[] = array(
					'id'   => $tag->term_id,
					'text' => $tag->name,
				);
			}
		}

		return $results;
	}

	public static function product_details( $ids ) {
		$products = array();
		// Get the product IDs from the request
		$product_ids = $ids;

		// Check if product_ids is provided and is an array
		if ( ! empty( $product_ids ) && is_array( $product_ids ) ) {

			// Loop through each product ID and fetch details
			foreach ( $product_ids as $product_id ) {
				// Get the WooCommerce product object by ID
				$product = wc_get_product( $product_id );

				// Check if product exists
				if ( $product ) {
					// Add product details to the response
					$products[] = array(
						'id'   => $product->get_id(),
						'text' => $product->get_name(),
					);
				}
			}

			return $products;

		}

	}

	public static function term_details( $ids, $taxonomy = 'product_cat' ) {
		
		$results  = array();
		$term_ids = $ids;

		// Check if product_ids is provided and is an array
		if ( ! empty( $term_ids ) && is_array( $term_ids ) ) {

			foreach ( $term_ids as $term_id ) {

				$term = get_term( $term_id, $taxonomy );

				if ( isset( $term->name ) ) {
					$results[] = array(
						'id'   => $term->term_id,
						'text' => $term->name,
					);
				}
			}
		}

		return $results;

	}

	public function shorten( $input_array, $key_field, $value_field ) {
		$shortened_array = array();

		foreach ( $input_array as $item ) {
			if ( isset( $item[ $key_field ] ) && isset( $item[ $value_field ] ) ) {
				$shortened_array[ $item[ $key_field ] ] = $item[ $value_field ];
			}
		}

		return $shortened_array;
	}

}
