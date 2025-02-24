<?php

/**
 * @package     Thank You Page
 * @since       4.1.6
*/

namespace NeeBPlugins\Wctr;

use NeeBPlugins\Wctr\Modules\Rules as TY_Rules;
use NeeBPlugins\Wctr\Helpler;

class Admin {

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

		// Add submenu under woocommerce
		add_action( 'admin_menu', array( $this, 'submenu_entry' ), 100 );
		// Add section under woocommerce
		add_filter( 'woocommerce_get_sections_products', array( $this, 'add_section' ) );
		// Add settings to the specific section created before
		add_filter( 'woocommerce_get_settings_products', array( $this, 'settings_page' ), 10, 2 );
		// Add the settings under ‘General’ sub-menu
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'settings_products_page' ), 10, 2 );
		// Save custom settings
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_custom_settings' ) );
		// Enqueue Admin scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		// Hide Save settings button
		add_action( 'admin_footer', array( $this, 'remove_woocommerce_save_button' ) );
		// Add Filter to append body class
		add_action( 'admin_body_class', array( $this, 'admin_body_class' ) );

	}

	public function submenu_entry() {
		add_submenu_page(
			'woocommerce',
			__( 'Thank You Page' ),
			__( 'Thank You Page' ),
			'manage_woocommerce',
			'admin.php?page=wc-settings&tab=products&section=wctr'
		);
	}

	/**
	 * Create the section beneath the products tab
	 */
	public function add_section( $sections ) {
		$sections['wctr'] = __( 'Thank You Page', 'wc-thanks-redirect' );
		return $sections;
	}

	public function settings_page( $settings, $current_section ) {
		ob_start();

		global $wc_thanks_redirect_fs;

		$default_tab = 'settings';

		$tab = isset( $_GET['wctr-tab'] ) ? $_GET['wctr-tab'] : $default_tab; // phpcs:ignore
		$tab = str_replace( 'wctr-', '', $tab );

		if ( $current_section === 'wctr' ) { //phpcs:ig

			$settings_url = $setting_fields = $settings_end = array();

			$settings_tab = admin_url( 'admin.php?page=wc-settings&tab=products&section=wctr&wctr-tab=settings' );
			$rules_tab    = admin_url( 'admin.php?page=wc-settings&tab=products&section=wctr&wctr-tab=rules' );

			echo '<div align="center" class="my-3">
					<a href="' . esc_url( $settings_tab ) . '" class="settingstab-link button ' . ( 'settings' === $tab ? 'button-primary' : '' ) . '">' . esc_html( 'Settings', 'wc-thanks-redirect' ) . '</a>
					<a href="' . esc_url( $rules_tab ) . '" class="settingstab-link button ' . ( 'rules' === $tab ? 'button-primary' : '' ) . '">' . esc_html( 'Rules ( PRO )', 'wc-thanks-redirect' ) . '</a>	
					<a target="_blank" href="' . esc_url( WCTR_KB_URL ) . '" class="settingstab-link button">' . esc_html( 'Documentation', 'wc-thanks-redirect' ) . '</a>				
					</div>';

			if ( 'wctr' === $current_section && 'settings' === $tab ) {

				$pages = Helper::get_instance()->get_pages();

				$skip_template_redirect = get_option( 'wctr_thanks_redirect_enable_template_redirect', true );
				$skip_template_redirect = filter_var( $skip_template_redirect, FILTER_VALIDATE_BOOLEAN );

				// Add Title to the Settings
				$settings_url[] = array(
					'name' => __( 'Thank You Page Settings', 'wc-thanks-redirect' ),
					'type' => 'title',
					'desc' => __( 'The following options are used to configure Thank You Page', 'wc-thanks-redirect' ),
					'id'   => 'wctr',
				);

				// Add first checkbox option
				$settings_url[] = array(
					'name'     => __( 'Global Redirect Settings', 'wc-thanks-redirect' ),
					'desc_tip' => __( 'This will add redirect for orders', 'wc-thanks-redirect' ),
					'id'       => 'wctr_global',
					'type'     => 'checkbox',
					'css'      => 'min-width:300px;',
					'desc'     => __( 'Enable Global Thank You Page', 'wc-thanks-redirect' ),
				);

				// Add second text field option
				$settings_url[] = array(
					'class'   => 'wc-enhanced-select',
					'name'    => __( 'Thank You Page URL', 'wc-thanks-redirect' ),
					'id'      => 'wctr_thanks_redirect_page',
					'type'    => 'select',
					'options' => wp_parse_args( Helper::get_instance()->shorten( $pages, 'url', 'text' ), array( '' => __( 'Select URL', 'wc-thanks-redirect' ) ) ),
					'desc'    => __( 'You can also enter the URL manually in the field below.', 'wc-thanks-redirect' ),
				);

				$settings_url[] = array(
					'name'     => '',
					'desc_tip' => __( 'This will add a redirect URL for successful orders', 'wc-thanks-redirect' ),
					'id'       => 'wctr_thanks_redirect_url',
					'type'     => 'text',
					'desc'     => __( 'Enter Valid URL!', 'wc-thanks-redirect' ),
				);

				// Add third text field option
				$settings_url[] = array(
					'name'     => __( 'Order Failure Redirect URL', 'wc-thanks-redirect' ),
					'desc_tip' => __( 'This will add a redirect URL for failed orders', 'wc-thanks-redirect' ),
					'id'       => 'wctr_failed_redirect_url',
					'type'     => 'text',
					'desc'     => __( 'Enter Valid URL!', 'wc-thanks-redirect' ),
				);

				$settings_url[] = array(
					'name'              => __( 'WPML Translated URL', 'wc-thanks-redirect' ),
					'desc_tip'          => __( 'WPML Translated URL is a PAID Feature. Please upgrade to <a href="' . esc_url( $wc_thanks_redirect_fs->get_upgrade_url() ) . '">PRO</a>', 'wc-thanks-redirect' ),
					'id'                => 'wctr_wpml_active',
					'type'              => 'checkbox',
					'default'           => 'no',
					'custom_attributes' => array( 'disabled' => 'disabled' ),
					'desc'              => __( 'Activate WPML and its done!', 'wc-thanks-redirect' ),
				);

				$settings_url[] = array(
					'type' => 'sectionend',
					'id'   => 'wctr',
				);

			} elseif ( 'wctr' === $current_section && 'rules' === $tab ) {

				$rules_instance = TY_Rules::get_instance();

				// Add Title to the Settings
				$settings_url[] = array();

				?>

				<div class="container my-5">
					<h2><?php esc_html_e( 'Thank You Page Rules', 'wc-thanks-redirect' ); ?></h2>
					<p><?php esc_html_e( 'The options below facilitate the configuration of Thank You Page Rules for WooCommerce PRO, enhancing customer engagement and optimizing post-purchase communication.', 'wc-thanks-redirect' ); ?></p>

					<div id="group-container"></div>

					<div id="button-container" style="display:none;">
						<p>
							<!-- Add New Rule Group Button -->
							<button type="button" class="button button-secondary" id="add-group-btn">
								<?php esc_html_e( 'Add New Rule', 'wc-thanks-redirect' ); ?>
							</button>
							<!-- Save Changes Button -->
							<a class="button button-primary" id="upgrade-pro" href="<?php echo esc_url( $wc_thanks_redirect_fs->get_upgrade_url() ); ?>">
								<?php esc_html_e( 'Upgrade to PRO', 'wc-thanks-redirect' ); ?>
							</a>
						</p>
						<p><?php esc_html_e( 'The Thank You Page Rules is a premium feature designed to enhance your experience. Upgrade now to access this powerful tool and take your business to the next level.', 'wc-thanks-redirect' ); ?></p>
					</div>

				</div>

				<!-- Group Template -->
				<script type="text/template" id="group-template">
					<div class="rule-group border rounded shadow p-3 mb-4">
						<div class="d-flex justify-content-between align-items-center mb-3">
							<div class="d-flex align-items-center">
								<a href="javascript:void(0)"><i class="text-secondary dashicons dashicons-menu text-decoration-none"></i></a>
							</div>
							<div class="d-flex align-items-center">
								<a class="btn btn-sm remove-group-btn" title="<?php echo esc_attr( 'Remove Group', 'wc-thanks-redirect' ); ?>">
									<i class="dashicons dashicons-remove text-danger"></i>
								</a>
							</div>
						</div>
						<div class="mb-3">
							<label class="form-label" for="page-url"><b><?php esc_html_e( 'Page URL', 'wc-thanks-redirect' ); ?>:</b></label>
							<div class="input-group">
								<?php echo $rules_instance->create_dropdown_pages(); // phpcs:ignore ?>
								<input type="url" class="form-control group-url" placeholder="<?php echo esc_attr( 'Or enter manually', 'wc-thanks-redirect' ); ?>" required>
							</div>
							<small class="form-text text-muted"><?php esc_html_e( 'You can select a URL from the dropdown or enter a custom URL.', 'wc-thanks-redirect' ); ?></small>
						</div>
						<div class="rule-list"></div> <!-- Rule list will be populated dynamically -->
						<button type="button" class="btn btn-secondary btn-sm add-rule-btn"><?php esc_html_e( 'Add Condition', 'wc-thanks-redirect' ); ?></button>
					</div>
				</script>

				<!-- Rule Template -->
				<script type="text/template" id="rule-template">
					<div class="row mb-3 rule-row">
						<div class="col-md-3">
							<?php echo $rules_instance->create_dropdown_options(); // phpcs:ignore ?>
						</div>
						<div class="col-md-3">
							<?php echo $rules_instance->create_dropdown_operators(); //phpcs:ignore ?>
						</div>
						<div class="col-md-4">
							<div class="value-input">
								<input name="value" type="text" class="form-control input-value" placeholder="<?php echo esc_attr( 'Enter value', 'wc-thanks-redirect' ); ?>">
							</div>
							<div class="value-select" style="display:none;">
								<select name="value" class="form-select select-value">
									<option value=""><?php esc_html_e( 'Select a value', 'wc-thanks-redirect' ); ?></option>
								</select>
							</div>
							<div class="value-multiselect" style="display:none;">
								<select name="value" class="form-select select2-multiselect multiselect-value" multiple>
									<option value=""><?php esc_html_e( 'Select multiple values', 'wc-thanks-redirect' ); ?></option>
								</select>
							</div>
						</div>
						<div class="col-md-1">
							<select class="form-select condition-selector">
								<option value="AND"><?php esc_html_e( 'AND', 'wc-thanks-redirect' ); ?></option>
								<option value="OR"><?php esc_html_e( 'OR', 'wc-thanks-redirect' ); ?></option>
							</select>
						</div>
						<div class="col-md-1 text-end">
							<a href="javascript:void(0)" class="btn btn-sm remove-rule-btn" title="<?php echo esc_attr( 'Remove Rule', 'wc-thanks-redirect' ); ?>">
								<i class="dashicons dashicons-remove text-danger"></i>
							</a>
						</div>
					</div>
				</script>

				<?php
			}

			// Override Admin Fields for plugin
			$setting_fields = apply_filters( 'wc_thanks_redirect_pro_settings_fields', $settings_url );

			$settings_end[] = array(
				'type' => 'sectionend',
				'id'   => 'wctr',
			);

			return wp_parse_args( $settings_end, $setting_fields );

			/**
			 * If not, return the standard settings
			 */
		} else {
			return $settings;
		}

		ob_get_clean();

	}

	public function save_custom_settings( $post_id ) {

		// save custom fields
		$wc_thanks_redirect_custom_thankyou = !empty($_POST['wc_thanks_redirect_custom_thankyou']) ? sanitize_text_field($_POST['wc_thanks_redirect_custom_thankyou']) : ''; // phpcs:ignore
		$wc_thanks_redirect_custom_failure = !empty($_POST['wc_thanks_redirect_custom_failure']) ? sanitize_text_field($_POST['wc_thanks_redirect_custom_failure']) : ''; // phpcs:ignore
		$wc_thanks_redirect_url_priority = !empty($_POST['wc_thanks_redirect_url_priority']) ? sanitize_text_field($_POST['wc_thanks_redirect_url_priority']) : ''; // phpcs:ignore

		if ( ! empty( $wc_thanks_redirect_custom_thankyou ) ) {
			update_post_meta( $post_id, 'wc_thanks_redirect_custom_thankyou', esc_attr( $wc_thanks_redirect_custom_thankyou ) );
		} else {
			delete_post_meta( $post_id, 'wc_thanks_redirect_custom_thankyou' );
		}

		if ( ! empty( $wc_thanks_redirect_custom_failure ) ) {
			update_post_meta( $post_id, 'wc_thanks_redirect_custom_failure', esc_attr( $wc_thanks_redirect_custom_failure ) );
		} else {
			delete_post_meta( $post_id, 'wc_thanks_redirect_custom_failure' );
		}

		if ( isset( $wc_thanks_redirect_url_priority ) && '' !== $wc_thanks_redirect_url_priority ) {
			update_post_meta( $post_id, 'wc_thanks_redirect_url_priority', esc_attr( $wc_thanks_redirect_url_priority ) );
		} else {
			delete_post_meta( $post_id, 'wc_thanks_redirect_url_priority' );
		}
	}

	public function admin_scripts() {

		if ( isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] && isset( $_GET['section'] ) && 'wctr' === $_GET['section'] ) {

			wp_enqueue_style( 'wctr-bootstrap', WCTR_PLUGIN_URL . 'assets/css/bootstrap.min.css', array(), '5.0.2' );
			wp_enqueue_style( 'wctr-backend', WCTR_PLUGIN_URL . 'assets/css/admin.css', array(), WCTR_VERSION );
			wp_enqueue_style( 'toastr', WCTR_PLUGIN_URL . 'assets/css/toastr.min.css', array() );
			wp_enqueue_style( 'dashicons' );

			wp_enqueue_script( 'wctr-backend', WCTR_PLUGIN_URL . 'assets/js/admin.js', array( 'wp-api' ), WCTR_VERSION, true );
			wp_enqueue_script( 'wctr-bootstrap', WCTR_PLUGIN_URL . 'assets/js/bootstrap.min.js', array( 'jquery' ), WCTR_VERSION, true );
			wp_enqueue_script( 'toastr', WCTR_PLUGIN_URL . 'assets/js/toastr.min.js', array( 'jquery' ), null, true );
			wp_enqueue_script( 'jquery-ui-core' );
			wp_enqueue_script( 'jquery-ui-sortable' );

			$helper_instance = Helper::get_instance();

			$localized_data = array(
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'restApiBase' => get_rest_url() . 'wctr-api/v1/',
				'labels'      => array(
					'page_url' => __( 'Page Url', 'wc-thanks-redirect' ),
					'add_rule' => __( 'Add Rule', 'wc-thanks-redirect' ),
				),
				'lists'       => array(
					'user_roles' => $helper_instance->get_roles_list(),
				),
			);

			wp_localize_script(
				'wctr-backend',
				'wctr_config',
				$localized_data
			);

		}
	}

	public function remove_woocommerce_save_button() {

		if ( isset( $_GET['page'] ) && isset( $_GET['wctr-tab'] ) && 'wc-settings' === $_GET['page'] ) {
			echo '<style> .notice, .update-nag { display: none !important; } </style>';

			if ( 'rules' === $_GET['wctr-tab'] ) {
				echo '<style> .woocommerce-save-button { display: none;	} </style>';
			}
		}
	}

	public function settings_products_page() {

		echo '<div class="options_group">';

		// Create a text field, for Custom Thank You
		woocommerce_wp_text_input(
			array(
				'id'          => 'wc_thanks_redirect_custom_thankyou',
				'label'       => __( 'Thank You URL', 'wc-thanks-redirect' ),
				'placeholder' => '',
				'desc_tip'    => 'true',
				'description' => __( 'Enter Valid URL.', 'wc-thanks-redirect' ),
				'type'        => 'text',
			)
		);

		// Create a text field, for Custom Thank You
		woocommerce_wp_text_input(
			array(
				'id'          => 'wc_thanks_redirect_custom_failure',
				'label'       => __( 'Failure Redirect', 'wc-thanks-redirect' ),
				'placeholder' => '',
				'desc_tip'    => 'true',
				'description' => __( 'Enter Valid URL.', 'wc-thanks-redirect' ),
				'type'        => 'text',
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => 'wc_thanks_redirect_url_priority',
				'type'        => 'number',
				'label'       => __( 'Redirect Priority', 'wc-thanks-redirect' ),
				'placeholder' => '',
				'desc_tip'    => 'true',
				'description' => __( 'Lower number means higher priority, leave empty if not required', 'wc-thanks-redirect' ),
				'type'        => 'text',
			)
		);

		echo '</div>';
	}

	public function admin_body_class( $classes ) {
		$classes .= ' wctr-admin';
		return $classes;
	}

}
