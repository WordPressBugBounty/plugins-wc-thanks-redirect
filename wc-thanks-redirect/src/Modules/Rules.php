<?php

/**
 * @package     Thank You Page
 * @since       4.1.6
*/

namespace NeeBPlugins\Wctr\Modules;

use NeeBPlugins\Wctr\Helper;

class Rules {

	protected $options   = array();
	protected $operators = array();
	protected $rules     = array();

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

	public function __construct() {
		// Define options and option groups
		$this->options = array(
			'products' => array(
				'product_name'      => __( 'Product ', 'wc-thanks-redirect' ),
				'product_variation' => __( 'Product Variation', 'wc-thanks-redirect' ),
				'product_category'  => __( 'Product Category', 'wc-thanks-redirect' ),
				'product_tag'       => __( 'Product Tag', 'wc-thanks-redirect' ),
			),
			'users'    => array(
				'user_role' => __( 'User Role ', 'wc-thanks-redirect' ),				
			),
		);

		// Define operators for each option type
		$this->operators = array(
			'default'            => array(
				'in'     => __( 'Includes', 'wc-thanks-redirect' ),
				'not_in' => __( 'Does Not Include', 'wc-thanks-redirect' ),
				// '='  => __( 'Equals', 'wc-thanks-redirect' ),
				// '!=' => __( 'Not Equals', 'wc-thanks-redirect' ),
			),
			'product_name'       => array(
				'='      => __( 'Is', 'wc-thanks-redirect' ),
				'!='     => __( 'Is Not', 'wc-thanks-redirect' ),
				'in'     => __( 'Includes', 'wc-thanks-redirect' ),
				'not_in' => __( 'Does Not Include', 'wc-thanks-redirect' ),
			),
			'order_total'        => array(
				'='  => __( 'Equals', 'wc-thanks-redirect' ),
				'!=' => __( 'Not Equals', 'wc-thanks-redirect' ),
				'>'  => __( 'Greater Than', 'wc-thanks-redirect' ),
				'<'  => __( 'Less Than', 'wc-thanks-redirect' ),
			),
			'order_status'       => array(
				'='  => __( 'Is', 'wc-thanks-redirect' ),
				'!=' => __( 'Is Not', 'wc-thanks-redirect' ),
			),
			'customer_email'     => array(
				'='    => __( 'Equals', 'wc-thanks-redirect' ),
				'!='   => __( 'Not Equals', 'wc-thanks-redirect' ),
				'like' => __( 'Contains', 'wc-thanks-redirect' ),
			),
			'returning_customer' => array(
				'='  => __( 'Yes', 'wc-thanks-redirect' ),
				'!=' => __( 'No', 'wc-thanks-redirect' ),
			),
			'order_date'         => array(
				'='  => __( 'Equals', 'wc-thanks-redirect' ),
				'!=' => __( 'Not Equals', 'wc-thanks-redirect' ),
				'>'  => __( 'After', 'wc-thanks-redirect' ),
				'<'  => __( 'Before', 'wc-thanks-redirect' ),
			),
		);
	}

	// Method to get option groups
	public function get_option_groups() {
		return $this->options;
	}

	// Method to get operators for a given option (fallback to default if no specific operators found)
	public function get_operators( $option ) {
		if ( isset( $this->operators[ $option ] ) ) {
			return $this->operators[ $option ];
		}

		// Return default operators if no specific operators are found for the option
		return $this->operators['default'];
	}

	// Method to generate dropdown options for option groups
	public function create_dropdown_options() {
		$optionGroups = $this->get_option_groups();

		$html  = '<select name="rule_options" class="form-select item-selector" required>';
		$html .= '<option value="">' . esc_html__( 'Select Option', 'thank-you-page-pro' ) . '</option>';

		foreach ( $optionGroups as $groupName => $options ) {
			$html .= '<optgroup label="' . ucfirst( $groupName ) . '">';

			foreach ( $options as $value => $label ) {
				$html .= '<option value="' . $value . '">' . $label . '</option>';
			}

			$html .= '</optgroup>';
		}

		$html .= '</select>';

		return $html;
	}

	// Method to generate dropdown operators based on the selected option
	public function create_dropdown_operators( $selectedOption = '' ) {
		$operators = $this->get_operators( $selectedOption );

		$html  = '<select class="form-select operator-selector" required>';
		$html .= '<option value="">' . esc_html__( 'Select Operator', 'thank-you-page-pro' ) . '</option>';
		foreach ( $operators as $value => $label ) {
			$html .= '<option value="' . $value . '">' . $label . '</option>';
		}

		$html .= '</select>';

		return $html;
	}

	public function create_dropdown_pages() {
		$html  = '<select name="rule_pages" class="form-select url-dropdown" required>';
		$html .= '<option value="">' . esc_html__( 'Select Page', 'thank-you-page-pro' ) . '</option>';

		$pages = Helper::get_instance()->get_pages();

		foreach ( $pages as $page ) {
			$html .= '<option value="' . $page['url'] . '">' . $page['text'] . '</option>';
		}

		$html .= '</select>';

		return $html;
	}

	/**
	 * Validate WooCommerce Order on Thank You page and redirect based on rules.
	 *
	 * @param int $order_id Order ID.
	 */
	public function process_rules( $order_id, $saved_rules = array() ) {

		// if Saved rules is empty return
		if ( empty( $saved_rules ) ) {
			return;
		}

		// Get the order object
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Get the order details
		$order_data = array(
			'payment_method' => $order->get_payment_method(),
			'product_name'  => array_reduce(
				$order->get_items(),
				function( $carry, $item ) {
					$carry[ $item->get_product_id() ] = $item->get_name();
					return $carry;
				},
				array()
			),
			'product_variation' => array_reduce(
				$order->get_items(),
				function( $carry, $item ) {
					if ( $item->get_variation_id() ) {
						$carry[ $item->get_variation_id() ] = $item->get_variation();
					}
					return $carry;
				},
				array()
			),
			'product_category' => array_reduce(
				$order->get_items(),
				function( $carry, $item ) {
					$product_id = $item->get_product_id();
					$terms = get_the_terms( $product_id, 'product_cat' );
					if ( $terms && ! is_wp_error( $terms ) ) {
						foreach ( $terms as $term ) {
							$carry[] = $term->name;
						}
					}
					return $carry;
				},
				array()
			),
			'product_tag' => array_reduce(
				$order->get_items(),
				function( $carry, $item ) {
					$product_id = $item->get_product_id();
					$terms = get_the_terms( $product_id, 'product_tag' );
					if ( $terms && ! is_wp_error( $terms ) ) {
						foreach ( $terms as $term ) {
							$carry[] = $term->name;
						}
					}
					return $carry;
				},
				array()
			),
			'user_role' => wp_get_current_user()->roles,
		);

		// Loop through each group of rules
		foreach ( $saved_rules as $rule_group ) {
			$group_url = $rule_group['groupUrl'];
			$rules     = $rule_group['rules'];

			// Process the rules based on condition
			$valid = $this->evaluate_rules( $rules, $order_data );

			// Redirect to the Thank You page if valid
			if ( $valid ) {
				return esc_url( $group_url );
			}
		}
	}

	/**
	 * Evaluate multiple rules based on the specified condition.
	 *
	 * @param array $rules Array of rules to evaluate.
	 * @param array $order_data Order data to match against.
	 * @return bool True if the rules are satisfied, false otherwise.
	 */
	private function evaluate_rules( $rules, $order_data ) {

		$condition = $rules[0]['condition'] ?? 'AND'; // Default condition

		foreach ( $rules as $index => $rule ) {
			$item           = $rule['item'];
			$operator       = $rule['operator'];
			$value          = $rule['value'];
			$rule_condition = $rule['condition'];

			$data_value = $this->get_order_data_value( $item, $order_data );

			$rule_valid = $this->evaluate_rule( $data_value, $operator, $value );

			// Handle AND/OR conditions
			if ( $index > 0 ) {
				if ( $condition === 'AND' && ! $rule_valid ) {
					return false; // If condition is AND and any rule is invalid, return false
				}
				if ( $condition === 'OR' && $rule_valid ) {
					return true; // If condition is OR and any rule is valid, return true
				}
			} else {
				// For the first rule, simply set the validity
				$valid = $rule_valid;
			}
		}

		return $condition === 'AND' ? $valid : false;
	}

	/**
	 * Get the value from order data based on the rule item.
	 *
	 * @param string $item Rule item.
	 * @param array $order_data Order data.
	 * @return mixed Value from the order data.
	 */
	private function get_order_data_value( $item, $order_data ) {
		switch ( $item ) {
			case 'payment_method':
				return $order_data['payment_method'];
			case 'product_name':
				return array_keys( $order_data['product_names'] );
			default:
				return '';
		}
	}

	/**
	 * Evaluate a single rule.
	 *
	 * @param mixed $data_value The value to compare.
	 * @param string $operator The operator to use for comparison.
	 * @param mixed $value The value to compare against.
	 * @return bool True if the rule is satisfied, false otherwise.
	 */
	private function evaluate_rule( $data_value, $operator, $value ) {

		switch ( $operator ) {
			case '=':
				return $data_value == $value;
			case '!=':
				return $data_value != $value;
			case '>':
				return $data_value > $value;
			case '<':
				return $data_value < $value;
			case 'in':
				return is_array( $data_value ) ? array_intersect( $data_value, $value ) : in_array( $data_value, $value, true );
			case 'in':
				return is_array( $data_value ) ? ! array_intersect( $data_value, $value ) : ! in_array( $data_value, $value, true );
			default:
				return false;
		}
	}
}


