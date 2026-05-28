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

	/**
	 * Constructor
	 *
	 * @since 4.1.6
	 */
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

	/**
	 * Submenu entry
	 *
	 * @since 4.1.6
	 *
	 * @return void
	 */
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

	/**
	 * Settings Page
	 *
	 * @since 4.1.6
	 *
	 * @return string ob_get_clean
	 */
	public function settings_page( $settings, $current_section ) {

		global $wc_thanks_redirect_fs;

		// Prevent loading on AJAX / REST
		if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $settings;
		}

		if ( 'wctr' !== $current_section ) {
			return $settings;
		}

		$tab = sanitize_key( wp_unslash( $_GET['wctr-tab'] ?? 'settings' ) ); // phpcs:ignore
		$tab = str_replace( 'wctr-', '', $tab );

		$this->render_settings_tabs();

		if ( 'rules' === $tab ) {
			$this->render_rules_tab( $wc_thanks_redirect_fs );

			return array(
				array(
					'type' => 'sectionend',
					'id'   => 'wctr',
				),
			);
		}

		$fields = $this->get_settings_tab_fields( $wc_thanks_redirect_fs );

		return apply_filters( 'wc_thanks_redirect_pro_settings_fields', $fields );
	}

	/**
	 * Render Settings Tabs
	 *
	 * @since 4.3.2
	 *
	 * @return void
	 */
	private function render_settings_tabs() {

		$tabs = array(
			'settings' => array(
				'label' => __( 'Settings', 'wc-thanks-redirect' ),
				'url'   => admin_url( 'admin.php?page=wc-settings&tab=products&section=wctr&wctr-tab=settings' ),
			),
			'rules'    => array(
				'label' => __( 'Rules ( PRO )', 'wc-thanks-redirect' ),
				'url'   => admin_url( 'admin.php?page=wc-settings&tab=products&section=wctr&wctr-tab=rules' ),
			),
			'docs'     => array(
				'label'  => __( 'Documentation', 'wc-thanks-redirect' ),
				'url'    => WCTR_KB_URL,
				'target' => '_blank',
			),
		);

		$current_tab = sanitize_key( wp_unslash( $_GET['wctr-tab'] ?? 'settings' ) ); // phpcs:ignore
		$current_tab = str_replace( 'wctr-', '', $current_tab );

		echo '<div align="center" class="my-3">';

		foreach ( $tabs as $key => $tab ) {
			printf(
				'<a href="%1$s" class="settingstab-link button %2$s" %3$s>%4$s</a> ',
				esc_url( $tab['url'] ),
				$current_tab === $key ? 'button-primary' : '',
				isset( $tab['target'] ) ? 'target="_blank"' : '',
				esc_html( $tab['label'] )
			);
		}

		echo '</div>';
	}

	/**
	 * Get Settings Tab Fields
	 *
	 * @since 4.3.2
	 *
	 * @param object $wc_thanks_redirect_fs Plugin Freemius instance.
	 *
	 * @return array
	 */
	private function get_settings_tab_fields( $wc_thanks_redirect_fs ) {

		$helper     = Helper::get_instance();
		$pages      = $helper->get_pages();
		$pys_active = class_exists( '\PixelYourSite\PYS' );

		$upgrade_url = esc_url( $wc_thanks_redirect_fs->get_upgrade_url() );

		return array(

			array(
				'name' => __( 'Thank You Page Settings', 'wc-thanks-redirect' ),
				'type' => 'title',
				'desc' => __( 'Configure your WooCommerce Thank You Page settings.', 'wc-thanks-redirect' ),
				'id'   => 'wctr',
			),

			array(
				'name'     => __( 'Global Redirect Settings', 'wc-thanks-redirect' ),
				'desc_tip' => __( 'Enable redirect for all orders.', 'wc-thanks-redirect' ),
				'id'       => 'wctr_global',
				'type'     => 'checkbox',
				'css'      => 'min-width:300px;',
				'desc'     => __( 'Enable Global Thank You Page', 'wc-thanks-redirect' ),
			),

			array(
				'class'   => 'wc-enhanced-select',
				'name'    => __( 'Thank You Page URL', 'wc-thanks-redirect' ),
				'id'      => 'wctr_thanks_redirect_page',
				'type'    => 'select',
				'options' => wp_parse_args(
					$helper->shorten( $pages, 'url', 'text' ),
					array(
						'' => __( 'Select URL', 'wc-thanks-redirect' ),
					)
				),
				'desc'    => __( 'Select a page or enter a custom URL below.', 'wc-thanks-redirect' ),
			),

			array(
				'id'       => 'wctr_thanks_redirect_url',
				'type'     => 'text',
				'desc_tip' => __( 'Redirect URL for successful orders.', 'wc-thanks-redirect' ),
				'desc'     => __( 'Enter a valid URL.', 'wc-thanks-redirect' ),
			),

			array(
				'name'     => __( 'Order Failure Redirect URL', 'wc-thanks-redirect' ),
				'id'       => 'wctr_failed_redirect_url',
				'type'     => 'text',
				'desc_tip' => __( 'Redirect URL for failed orders.', 'wc-thanks-redirect' ),
				'desc'     => __( 'Enter a valid URL.', 'wc-thanks-redirect' ),
			),

			array(
				'name'     => __( 'PixelYourSite Integration', 'wc-thanks-redirect' ),
				'type'     => 'checkbox',
				'default'  => $pys_active ? 'yes' : 'no',
				'desc'     => $pys_active
					? __( 'PixelYourSite integration is active.', 'wc-thanks-redirect' )
					: __( 'Activate PixelYourSite to enable integration.', 'wc-thanks-redirect' ),
				'disabled' => true,
			),

			$this->get_locked_feature_field(
				__( 'WPML Translated URL', 'wc-thanks-redirect' ),
				'wctr_wpml_active',
				$upgrade_url
			),

			$this->get_locked_feature_field(
				__( 'PolyLang Translated URL', 'wc-thanks-redirect' ),
				'wctr_poly_active',
				$upgrade_url
			),

			array(
				'type' => 'sectionend',
				'id'   => 'wctr',
			),
		);
	}

	/**
	 * Render Rules Tab
	 *
	 * @since 4.3.2
	 *
	 * @param object $wc_thanks_redirect_fs Plugin Freemius instance.
	 *
	 * @return void
	 */
	private function render_rules_tab( $wc_thanks_redirect_fs ) {

		$rules_instance = TY_Rules::get_instance();
		$upgrade_url    = esc_url( $wc_thanks_redirect_fs->get_upgrade_url() );

		?>
	<div class="container my-5">

		<h2><?php esc_html_e( 'Thank You Page Rules', 'wc-thanks-redirect' ); ?></h2>

		<p>
			<?php
			esc_html_e(
				'Create advanced Thank You Page rules for WooCommerce based on products, categories, customer conditions, and more.',
				'wc-thanks-redirect'
			);
			?>
		</p>

		<div id="group-container"></div>

		<div id="button-container" style="display:none;">
			<p>
				<button type="button" class="button button-secondary" id="add-group-btn">
					<?php esc_html_e( 'Add New Rule', 'wc-thanks-redirect' ); ?>
				</button>

				<a
					class="button button-primary"
					id="upgrade-pro"
					href="<?php echo $upgrade_url; // phpcs:ignore ?>"
					target="_blank"
					rel="noopener noreferrer"
				>
					<?php esc_html_e( 'Upgrade to PRO', 'wc-thanks-redirect' ); ?>
				</a>
			</p>

			<p>
				<?php
				esc_html_e(
					'Advanced Thank You Page Rules are available in the PRO version. Upgrade to unlock dynamic post-purchase targeting.',
					'wc-thanks-redirect'
				);
				?>
			</p>
		</div>

	</div>

	<!-- Group Template -->
	<script type="text/template" id="group-template">
		<div class="rule-group border rounded shadow p-3 mb-4">

			<div class="d-flex justify-content-between align-items-center mb-3">

				<div class="d-flex align-items-center">
					<a href="javascript:void(0)">
						<i class="text-secondary dashicons dashicons-menu text-decoration-none"></i>
					</a>
				</div>

				<div class="d-flex align-items-center">
					<a
						class="btn btn-sm remove-group-btn"
						title="<?php echo esc_attr__( 'Remove Group', 'wc-thanks-redirect' ); ?>"
					>
						<i class="dashicons dashicons-remove text-danger"></i>
					</a>
				</div>

			</div>

			<div class="mb-3">

				<label class="form-label" for="page-url">
					<b><?php esc_html_e( 'Page URL', 'wc-thanks-redirect' ); ?>:</b>
				</label>

				<div class="input-group">
					<?php echo $rules_instance->create_dropdown_pages(); // phpcs:ignore ?>

					<input
						type="url"
						class="form-control group-url"
						placeholder="<?php echo esc_attr__( 'Or enter manually', 'wc-thanks-redirect' ); ?>"
						required
					>
				</div>

				<small class="form-text text-muted">
					<?php
					esc_html_e(
						'Select a page from the dropdown or enter a custom URL manually.',
						'wc-thanks-redirect'
					);
					?>
				</small>

			</div>

			<div class="rule-list"></div>

			<button type="button" class="btn btn-secondary btn-sm add-rule-btn">
				<?php esc_html_e( 'Add Condition', 'wc-thanks-redirect' ); ?>
			</button>

		</div>
	</script>

	<!-- Rule Template -->
	<script type="text/template" id="rule-template">

		<div class="row mb-3 rule-row">

			<div class="col-md-3">
				<?php echo $rules_instance->create_dropdown_options(); // phpcs:ignore ?>
			</div>

			<div class="col-md-3">
				<?php echo $rules_instance->create_dropdown_operators(); // phpcs:ignore ?>
			</div>

			<div class="col-md-4">

				<div class="value-input">
					<input
						name="value"
						type="text"
						class="form-control input-value"
						placeholder="<?php echo esc_attr__( 'Enter value', 'wc-thanks-redirect' ); ?>"
					>
				</div>

				<div class="value-select" style="display:none;">
					<select name="value" class="form-select select-value">
						<option value="">
							<?php esc_html_e( 'Select a value', 'wc-thanks-redirect' ); ?>
						</option>
					</select>
				</div>

				<div class="value-multiselect" style="display:none;">
					<select
						name="value"
						class="form-select select2-multiselect multiselect-value"
						multiple
					>
						<option value="">
							<?php esc_html_e( 'Select multiple values', 'wc-thanks-redirect' ); ?>
						</option>
					</select>
				</div>

			</div>

			<div class="col-md-1">
				<select class="form-select condition-selector">
					<option value="AND">
						<?php esc_html_e( 'AND', 'wc-thanks-redirect' ); ?>
					</option>
					<option value="OR">
						<?php esc_html_e( 'OR', 'wc-thanks-redirect' ); ?>
					</option>
				</select>
			</div>

			<div class="col-md-1 text-end">
				<a
					href="javascript:void(0)"
					class="btn btn-sm remove-rule-btn"
					title="<?php echo esc_attr__( 'Remove Rule', 'wc-thanks-redirect' ); ?>"
				>
					<i class="dashicons dashicons-remove text-danger"></i>
				</a>
			</div>

		</div>

	</script>
		<?php
	}

	/**
	 * Get Locked Feature Field
	 *
	 * @since 4.3.2
	 *
	 * @param string $label Field label.
	 * @param string $id Field ID.
	 * @param string $upgrade_url Upgrade URL.
	 *
	 * @return array
	 */
	private function get_locked_feature_field( $label, $id, $upgrade_url ) {

		return array(
			'name'              => $label,
			'id'                => $id,
			'type'              => 'checkbox',
			'default'           => 'no',
			'custom_attributes' => array(
				'disabled' => 'disabled',
			),
			'desc_tip'          => sprintf(
				__(
					'%s is a PRO feature. Upgrade now.',
					'wc-thanks-redirect'
				),
				$label
			),
			'desc'              => sprintf(
				'<a href="%s">%s</a>',
				$upgrade_url,
				__( 'Upgrade to PRO', 'wc-thanks-redirect' )
			),
		);
	}

	/**
	 * Save custom settings
	 *
	 * @since 4.1.6
	 *
	 * @return void
	 */
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

	/**
	 * Admin Scripts
	 *
	 * @since 4.1.6
	 *
	 * @return void
	 */
	public function admin_scripts() {

		if ( isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] && isset( $_GET['section'] ) && 'wctr' === $_GET['section'] ) { // phpcs:ignore

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
					'payment_gateways' => $helper_instance->get_payment_gateways(),
					'user_roles'       => $helper_instance->get_roles_list(),
					'shipping_methods' => $helper_instance->get_shipping_methods(),
				),
			);

			wp_localize_script(
				'wctr-backend',
				'wctr_config',
				$localized_data
			);

		}
	}

	/**
	 * Remove WooCommerce Save Button
	 *
	 * @since 4.1.6
	 *
	 * @return void
	 */
	public function remove_woocommerce_save_button() {

		if ( isset( $_GET['page'] ) && isset( $_GET['wctr-tab'] ) && 'wc-settings' === $_GET['page'] ) { // phpcs:ignore
			echo '<style> .notice, .update-nag { display: none !important; } </style>';

			if ( 'rules' === $_GET['wctr-tab'] ) { // phpcs:ignore
				echo '<style> .woocommerce-save-button { display: none;	} </style>';
			}
		}
	}

	/**
	 * Settings products page
	 *
	 * @since 4.1.6
	 *
	 * @return void
	 */
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

	/**
	 * Add body class
	 *
	 * @since 4.1.6
	 *
	 * @return string $classes
	 */
	public function admin_body_class( $classes ) {
		$classes .= ' wctr-admin';
		return $classes;
	}
}
