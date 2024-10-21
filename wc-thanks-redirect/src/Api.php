<?php

/**
 * @package     Thank You Page
 * @since       4.1.6
*/

namespace NeeBPlugins\Wctr;

use NeeBPlugins\Wctr\Helper;

class Api {

	private static $instance;

	protected $namespace = 'wctr-api';
	protected $option    = 'wctr-tyrules';
	protected $rest_base = 'v1';
	protected $response  = array();

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
	 * Initialize the class and set its properties.
	 *
	 * @since 4.1.6
	 * @return object initialized object of class.
	 */
	public function __construct() {

		// Initialize the class and set its properties.

		add_action(
			'rest_api_init',
			array( $this, 'register_routes' )
		);

	}

	public function register_routes() {

		register_rest_route(
			$this->namespace,
			$this->rest_base . '/tyrules/save',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_ty_rules' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
					'args'                => array(
						'settings' => array(
							'description'       => __( 'Thank You Rules', 'wc-thanks-redirect' ),
							'type'              => 'json',
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => array( $this, 'sanitize_request' ),
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$this->rest_base . '/tyrules/fetch',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'fetch_ty_rules' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$this->rest_base . '/tyrules/search-product',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'search_product' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$this->rest_base . '/tyrules/search-category',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'search_category' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$this->rest_base . '/tyrules/search-tag',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'search_tags' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$this->rest_base . '/tyrules/products/details',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'product_details' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$this->rest_base . '/tyrules/post/terms',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'get_postterms' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
			)
		);

	}

	public function save_ty_rules( \WP_REST_Request $request ) {

		$posted_data = $request->get_params();
		$tyrules     = isset( $posted_data['rules'] ) ? $posted_data['rules'] : '';

		update_option( $this->option, $tyrules );

		return new \WP_REST_Response( $tyrules, 200 );

	}

	public function search_product() {

		$keyword         = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';
		$variations_only = isset( $_GET['variations_only'] ) ? sanitize_text_field( $_GET['variations_only'] ) : false;

		$products = Helper::search_product( $keyword, $variations_only );
		return $products;

	}

	public function search_category() {

		$keyword   = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';
		$post_type = isset( $_GET['post_type'] ) ? sanitize_text_field( $_GET['post_type'] ) : 'post';

		$products = Helper::search_categories( $keyword, $post_type );
		return $products;

	}

	public function search_tags() {

		$keyword   = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';
		$post_type = isset( $_GET['post_type'] ) ? sanitize_text_field( $_GET['post_type'] ) : 'post';

		$products = Helper::search_tags( $keyword, $post_type );
		return $products;

	}

	public function product_details( \WP_REST_Request $request ) {
		// Get the product IDs from the request
		$product_ids = $request->get_param( 'ids' );

		// Check if product_ids is provided and is an array
		if ( empty( $product_ids ) || ! is_array( $product_ids ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Invalid product IDs provided.',
				),
				400
			);
		}

		// Initialize an empty array to store product details
		$products = Helper::product_details( $product_ids );

		// Return the response
		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $products,
			),
			200
		);
	}

	public function get_postterms( \WP_REST_Request $request ) {
		// Get the product IDs from the request
		$term_ids = $request->get_param( 'ids' );
		$taxonomy = $request->get_param( 'taxonomy' );

		// Check if product_ids is provided and is an array
		if ( empty( $term_ids ) || ! is_array( $term_ids ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Invalid term IDs provided.',
				),
				400
			);
		}

		// Initialize an empty array to store product details
		$terms = Helper::term_details( $term_ids, $taxonomy );

		// Return the response
		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $terms,
			),
			200
		);
	}

	public function fetch_ty_rules() {

		$tyrules = get_option( $this->option, array() );
		return new \WP_REST_Response( array( 'data' => $tyrules ), 200 );

	}

	public function get_write_api_permission_check() {
		return current_user_can( 'manage_options' ) ? true : false;
	}

}

