<?php

/**
 * @package     Thank You Page
 * @since       4.1.6
 */

namespace NeeBPlugins\Wctr;

use NeeBPlugins\Wctr\Modules\Rules as TY_Rules;

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

		// Add top-level admin menu
		add_action( 'admin_menu', array( $this, 'submenu_entry' ), 100 );
		// Register settings for the settings page (options.php save handling)
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		// Allow Shop Managers to save, matching the page's own capability
		add_filter( 'option_page_capability_wctr_settings_group', array( $this, 'settings_capability' ) );
		// Add the settings under ‘General’ sub-menu
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'settings_products_page' ), 10, 2 );
		// Save custom settings
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_custom_settings' ) );
		// Enqueue Admin scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		// Add Filter to append body class
		add_action( 'admin_body_class', array( $this, 'admin_body_class' ) );
	}

	/**
	 * Submenu entry
	 *
	 * Registers a top-level admin menu page (separate from WooCommerce's
	 * own menu tree), matching how comparable plugins register themselves.
	 *
	 * @since 4.1.6
	 *
	 * @return void
	 */
	public function submenu_entry() {
		add_menu_page(
			__( 'Thank You Page', 'wc-thanks-redirect' ),
			__( 'Thank You Page', 'wc-thanks-redirect' ),
			'manage_woocommerce',
			'wc-thanks-redirect',
			array( $this, 'render_settings_page' ),
			'dashicons-yes-alt',
			56
		);
	}

	/**
	 * Register settings
	 *
	 * Registers each option individually so options.php can save the
	 * settings form natively (nonce, capability check, and the standard
	 * "Settings saved." notice all come from WordPress core).
	 *
	 * @since 4.3.3
	 *
	 * @return void
	 */
	public function register_settings() {

		register_setting(
			'wctr_settings_group',
			'wctr_global',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => 'no',
			)
		);

		register_setting(
			'wctr_settings_group',
			'wctr_thanks_redirect_page',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => '',
			)
		);

		register_setting(
			'wctr_settings_group',
			'wctr_thanks_redirect_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => '',
			)
		);

		register_setting(
			'wctr_settings_group',
			'wctr_failed_redirect_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => '',
			)
		);
	}

	/**
	 * Sanitize Checkbox
	 *
	 * @since 4.3.3
	 *
	 * @param string $value Raw posted value.
	 *
	 * @return string
	 */
	public function sanitize_checkbox( $value ) {
		return ( '1' === $value || 'yes' === $value ) ? 'yes' : 'no';
	}

	/**
	 * Settings Capability
	 *
	 * Shop Managers hold 'manage_woocommerce' but not 'manage_options',
	 * so options.php needs telling to accept the same capability this
	 * page's menu entry already requires.
	 *
	 * @since 4.3.3
	 *
	 * @return string
	 */
	public function settings_capability() {
		return 'manage_woocommerce';
	}

	/**
	 * Render Settings Page
	 *
	 * Top-level "Thank You Page" admin page callback.
	 *
	 * @since 4.3.3
	 *
	 * @return void
	 */
	public function render_settings_page() {

		global $wc_thanks_redirect_fs;

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_add_inline_script(
				'toastr',
				'jQuery(function(){ toastr.success(' . wp_json_encode( __( 'Settings saved.', 'wc-thanks-redirect' ) ) . ', ' . wp_json_encode( __( 'Success', 'wc-thanks-redirect' ) ) . '); });'
			);
		}

		$tab = sanitize_key( wp_unslash( $_GET['wctr-tab'] ?? 'settings' ) ); // phpcs:ignore

		echo '<div class="wrap wctr-admin-wrap">';

		$this->render_page_header( $wc_thanks_redirect_fs );
		$this->render_settings_tabs();

		if ( 'rules' === $tab ) {
			$this->render_rules_tab( $wc_thanks_redirect_fs );
		} else {
			echo '<form method="post" action="options.php">';
			settings_fields( 'wctr_settings_group' );
			echo '<input type="hidden" name="wctr_global" value="no" />';
			$this->render_settings_tab_fields( $wc_thanks_redirect_fs );
			echo '<p class="submit border-top pb-0 mt-2 mb-0">';
			submit_button( __( 'Save Changes', 'wc-thanks-redirect' ), 'primary', 'submit', false );
			echo '</p>';
			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * Render Page Header
	 *
	 * @since 4.3.3
	 *
	 * @param object $wc_thanks_redirect_fs Plugin Freemius instance.
	 *
	 * @return void
	 */
	private function render_page_header( $wc_thanks_redirect_fs ) {

		$upgrade_url = $wc_thanks_redirect_fs->get_upgrade_url();
		$support_url =
			add_query_arg(
				array(
					'utm_source'   => 'wc-thanks-redirect',
					'utm_medium'   => 'plugin-admin',
					'utm_campaign' => 'support-button',
				),
				'https://nitin247.com/support-desk'
			);

		?>

		<?php // Anchors WP core's admin_notices (ours and other plugins') right below this header instead of a stray WooCommerce header marker relocating them off-layout. ?>
		<hr class="wp-header-end" />

		<div class="wctr-page-header mb-0">

			<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
				<h1 class="h4 d-flex align-items-center gap-2 mb-0">
					<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'Thank You Page', 'wc-thanks-redirect' ); ?>
				</h1>

				<div class="wctr-page-header__actions d-flex flex-wrap align-items-center gap-2">
					<a class="button" href="<?php echo esc_url( WCTR_KB_URL ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Docs', 'wc-thanks-redirect' ); ?>
					</a>
					<a class="button" href="<?php echo esc_url( $support_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Support', 'wc-thanks-redirect' ); ?>
					</a>
					<a class="button button-primary" href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Upgrade PRO', 'wc-thanks-redirect' ); ?>
					</a>
				</div>
			</div>			

			<p class="wctr-text-muted mb-1 mt-1">
				<?php
				esc_html_e(
					'Redirect customers to a custom page after checkout, personalize it per product, and track purchases in GA4/GTM.',
					'wc-thanks-redirect'
				);
				?>
			</p>

		</div>
		<?php
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
				'url'   => admin_url( 'admin.php?page=wc-thanks-redirect&wctr-tab=settings' ),
			),
			'rules'    => array(
				'label' => __( 'Rules ( PRO )', 'wc-thanks-redirect' ),
				'url'   => admin_url( 'admin.php?page=wc-thanks-redirect&wctr-tab=rules' ),
			),
		);

		$current_tab = sanitize_key( wp_unslash( $_GET['wctr-tab'] ?? 'settings' ) ); // phpcs:ignore
		$current_tab = str_replace( 'wctr-', '', $current_tab );

		echo '<div class="wctr-tabs d-flex flex-wrap gap-2 mb-2">';

		foreach ( $tabs as $key => $tab ) {
			printf(
				'<a href="%1$s" class="settingstab-link button %2$s" %3$s>%4$s</a>',
				esc_url( $tab['url'] ),
				$current_tab === $key ? 'button-primary' : '',
				isset( $tab['target'] ) ? 'target="_blank"' : '',
				esc_html( $tab['label'] )
			);
		}

		echo '</div>';
	}

	/**
	 * Render Settings Tab Fields
	 *
	 * Hand-rendered to match the flat, native-WP-admin layout (sections,
	 * two-column field grid, inline hints) rather than WooCommerce's
	 * boxed form-table renderer. Colours come from WP core classes and
	 * the `--wp-admin-theme-color` custom property, so the page follows
	 * whatever admin colour scheme the user has selected.
	 *
	 * @since 4.3.3
	 *
	 * @param object $wc_thanks_redirect_fs Plugin Freemius instance.
	 *
	 * @return void
	 */
	private function render_settings_tab_fields( $wc_thanks_redirect_fs ) {

		$helper     = Helper::get_instance();
		$pages      = $helper->get_pages();
		$gtm_active = defined( 'GTM4WP_VERSION' ) || defined( 'GOOGLESITEKIT_VERSION' ) || defined( 'MONSTERINSIGHTS_VERSION' ) || defined( 'EXACTMETRICS_VERSION' );
		$pys_active = class_exists( '\PixelYourSite\PYS' );

		$upgrade_url = $wc_thanks_redirect_fs->get_upgrade_url();

		$global_enabled = 'yes' === get_option( 'wctr_global', 'no' );
		$redirect_page  = get_option( 'wctr_thanks_redirect_page', '' );
		$redirect_url   = get_option( 'wctr_thanks_redirect_url', '' );
		$failed_url     = get_option( 'wctr_failed_redirect_url', '' );
		$page_options   = wp_parse_args(
			$helper->shorten( $pages, 'url', 'text' ),
			array( '' => __( 'Select a page&hellip;', 'wc-thanks-redirect' ) )
		);

		?>
		<div class="wctr-intro rounded mt-1 mb-1">
			<?php
			printf(
				/* translators: 1: link to Products list, 2: link to upgrade URL */
				wp_kses_post(
					__( 'Need a per-product redirect? Set it on the <a href="%1$s">product\'s edit screen</a> &mdash; personalize it with shortcodes in the <a href="%2$s" target="_blank" rel="noopener noreferrer">PRO version</a>.', 'wc-thanks-redirect' )
				),
				esc_url( admin_url( 'edit.php?post_type=product' ) ),
				esc_url( $upgrade_url )
			);
			?>
		</div>

		<div class="wctr-section border-top-0 pt-1 pb-2">
			<h2 class="h5 mb-1"><?php esc_html_e( 'Settings', 'wc-thanks-redirect' ); ?></h2>
			<p class="wctr-text-muted small mb-1">
				<?php esc_html_e( 'Configure the global redirect and choose where customers land after a successful or failed order.', 'wc-thanks-redirect' ); ?>
			</p>

			<div class="d-flex align-items-start gap-2 mb-0">
				<input type="checkbox" id="wctr_global" name="wctr_global" value="1" class="mt-1" <?php checked( $global_enabled ); ?> />
				<div>
					<label for="wctr_global" class="fw-bold d-block"><?php esc_html_e( 'Enable Global Thank You Page', 'wc-thanks-redirect' ); ?></label>
					<p class="wctr-text-muted small mt-1 mb-0"><?php esc_html_e( 'Redirects every successful order site-wide to the URL below.', 'wc-thanks-redirect' ); ?></p>
				</div>
			</div>

			<div class="row g-3">
				<div class="col-md-4 wctr-field">
					<label for="wctr_thanks_redirect_page" class="form-label fw-bold"><?php esc_html_e( 'Thank You Page URL', 'wc-thanks-redirect' ); ?></label>
					<select id="wctr_thanks_redirect_page" name="wctr_thanks_redirect_page" class="form-select">
						<?php foreach ( $page_options as $option_url => $option_label ) : ?>
							<option value="<?php echo esc_attr( $option_url ); ?>" <?php selected( $redirect_page, $option_url ); ?>>
								<?php echo esc_html( $option_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="wctr-text-muted small mt-1 mb-0"><?php esc_html_e( 'Select a page, or leave blank and set a custom URL below.', 'wc-thanks-redirect' ); ?></p>
				</div>

				<div class="col-md-4 wctr-field">
					<label for="wctr_thanks_redirect_url" class="form-label fw-bold"><?php esc_html_e( 'Custom Thank You URL', 'wc-thanks-redirect' ); ?></label>
					<input type="text" id="wctr_thanks_redirect_url" name="wctr_thanks_redirect_url" value="<?php echo esc_attr( $redirect_url ); ?>" placeholder="https://" />
					<p class="wctr-text-muted small mt-1 mb-0"><?php esc_html_e( 'Overrides the page above &mdash; for an external URL.', 'wc-thanks-redirect' ); ?></p>
				</div>

				<div class="col-md-4 wctr-field">
					<label for="wctr_failed_redirect_url" class="form-label fw-bold"><?php esc_html_e( 'Order Failure Redirect URL', 'wc-thanks-redirect' ); ?></label>
					<input type="text" id="wctr_failed_redirect_url" name="wctr_failed_redirect_url" value="<?php echo esc_attr( $failed_url ); ?>" placeholder="https://" />
					<p class="wctr-text-muted small mt-1 mb-0"><?php esc_html_e( 'Used when payment fails or is cancelled.', 'wc-thanks-redirect' ); ?></p>
				</div>
			</div>
		</div>

		<div class="wctr-section border-top py-2">
			<h2 class="h5 mb-1"><?php esc_html_e( 'Integrations', 'wc-thanks-redirect' ); ?></h2>
			<p class="wctr-text-muted small mb-1"><?php esc_html_e( 'Detected automatically, no setup required.', 'wc-thanks-redirect' ); ?></p>

			<div class="row g-2">
				<div class="col-md-6 d-flex justify-content-between align-items-center">
					<span class="fw-bold"><?php esc_html_e( 'GTM / GA4 Purchase Tracking', 'wc-thanks-redirect' ); ?></span>
					<?php if ( $gtm_active ) : ?>
						<span class="badge rounded-pill wctr-badge-success"><?php esc_html_e( 'Active', 'wc-thanks-redirect' ); ?></span>
					<?php else : ?>
						<span class="badge rounded-pill wctr-badge-muted"><?php esc_html_e( 'Not Detected', 'wc-thanks-redirect' ); ?></span>
					<?php endif; ?>
				</div>
				<div class="col-md-6 d-flex justify-content-between align-items-center">
					<span class="fw-bold"><?php esc_html_e( 'PixelYourSite', 'wc-thanks-redirect' ); ?></span>
					<?php if ( $pys_active ) : ?>
						<span class="badge rounded-pill wctr-badge-success"><?php esc_html_e( 'Connected', 'wc-thanks-redirect' ); ?></span>
					<?php else : ?>
						<span class="badge rounded-pill wctr-badge-muted"><?php esc_html_e( 'Not Detected', 'wc-thanks-redirect' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="wctr-section wctr-section--pro border-top pt-3 pb-2">
			<h2 class="h5 mb-1 d-flex align-items-center gap-2">
				<?php esc_html_e( 'Multilingual Redirects', 'wc-thanks-redirect' ); ?>
				<span class="badge rounded-pill wctr-badge-locked"><?php esc_html_e( 'PRO', 'wc-thanks-redirect' ); ?></span>
			</h2>
			<p class="wctr-text-muted small mb-1"><?php esc_html_e( 'Translate the Thank You URL automatically per language.', 'wc-thanks-redirect' ); ?></p>

			<div class="row g-2">
				<div class="col-md-6">
					<?php $this->render_locked_field( __( 'WPML Translated URL', 'wc-thanks-redirect' ), 'wctr_wpml_active', $upgrade_url ); ?>
				</div>
				<div class="col-md-6">
					<?php $this->render_locked_field( __( 'PolyLang Translated URL', 'wc-thanks-redirect' ), 'wctr_poly_active', $upgrade_url ); ?>
				</div>
			</div>
		</div>
		<?php
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
		$upgrade_url    = $wc_thanks_redirect_fs->get_upgrade_url();

		?>
			<div class="wctr-section border-top-0 pt-0 py-1">

				<h2 class="h4 mb-1 d-flex align-items-center gap-2 pt-2">
					<?php esc_html_e( 'Rules', 'wc-thanks-redirect' ); ?>
					<span class="badge rounded-pill wctr-badge-locked"><?php esc_html_e( 'PRO', 'wc-thanks-redirect' ); ?></span>
				</h2>

				<p class="wctr-text-muted small mb-1">
					<?php
					esc_html_e(
						'Create advanced Thank You Page rules for WooCommerce based on products, categories, customer conditions, and more.',
						'wc-thanks-redirect'
					);
					?>
				</p>

				<div id="group-container"></div>

				<div id="button-container" class="wctr-locked-field d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2" style="display:none;">
					<div class="d-flex flex-wrap gap-2">
						<button type="button" class="button" id="add-group-btn">
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
					</div>

					<p class="wctr-rules-cta mb-0">
						<?php
						esc_html_e(
							'Advanced Thank You Page Rules are available in the PRO version. Upgrade to unlock dynamic post-purchase targeting.',
							'wc-thanks-redirect'
						);
						?>
					</p>
					
				</div>

			</div>

			<div class="wctr-section wctr-compare border-top py-2">

				<h2 class="h5 mb-1"><?php esc_html_e( 'Free vs. PRO', 'wc-thanks-redirect' ); ?></h2>
				<p class="wctr-text-muted small mb-1"><?php esc_html_e( 'What unlocks when you upgrade.', 'wc-thanks-redirect' ); ?></p>

				<table class="wp-list-table widefat fixed striped wctr-compare-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Feature', 'wc-thanks-redirect' ); ?></th>
							<th class="text-center"><?php esc_html_e( 'Free', 'wc-thanks-redirect' ); ?></th>
							<th class="text-center"><?php esc_html_e( 'PRO', 'wc-thanks-redirect' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$compare_rows = array(
							array( __( 'Global & product redirect URLs', 'wc-thanks-redirect' ), true, true ),
							array( __( 'GA4 / GTM purchase tracking', 'wc-thanks-redirect' ), true, true ),
							array( __( 'Rules by product, category, tag', 'wc-thanks-redirect' ), false, true ),
							array( __( 'Rules by payment gateway & user role', 'wc-thanks-redirect' ), false, true ),
							array( __( 'Personalization shortcodes', 'wc-thanks-redirect' ), false, true ),
							array( __( 'WPML / PolyLang & Multisite', 'wc-thanks-redirect' ), false, true ),
						);

						foreach ( $compare_rows as $row ) :
							list( $feature, $in_free, $in_pro ) = $row;
							?>
							<tr>
								<td><?php echo esc_html( $feature ); ?></td>
								<td class="wctr-compare-cell">
									<?php if ( $in_free ) : ?>
										<span class="dashicons dashicons-yes-alt wctr-yes" aria-hidden="true"></span>
									<?php else : ?>
										<span class="dashicons dashicons-lock wctr-lock" aria-hidden="true"></span>
									<?php endif; ?>
								</td>
								<td class="wctr-compare-cell">
									<?php if ( $in_pro ) : ?>
										<span class="dashicons dashicons-yes-alt wctr-yes" aria-hidden="true"></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

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
	 * Render Locked Field
	 *
	 * A disabled checkbox row for a PRO-only feature, with an inline
	 * upgrade link. Disabled inputs are never posted, so this never
	 * needs registering with register_settings().
	 *
	 * @since 4.3.3
	 *
	 * @param string $label Field label.
	 * @param string $id Field ID.
	 * @param string $upgrade_url Upgrade URL.
	 *
	 * @return void
	 */
	private function render_locked_field( $label, $id, $upgrade_url ) {
		?>
		<div class="wctr-locked-field h-100">
			<label for="<?php echo esc_attr( $id ); ?>">
				<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" disabled="disabled" />
				<?php echo esc_html( $label ); ?>
			</label>
			<p class="wctr-text-muted small mt-1 mb-1">
				<?php
				printf(
					/* translators: 1: feature label, 2: upgrade URL */
					wp_kses_post( __( '%1$s is a PRO feature &middot; <a href="%2$s" target="_blank" rel="noopener noreferrer">Upgrade to PRO</a>', 'wc-thanks-redirect' ) ),
					esc_html( $label ),
					esc_url( $upgrade_url )
				);
				?>
			</p>
		</div>
		<?php
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

		if ( isset( $_GET['page'] ) && 'wc-thanks-redirect' === $_GET['page'] ) { // phpcs:ignore

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
	 * Settings products page
	 *
	 * @since 4.1.6
	 *
	 * @return void
	 */
	public function settings_products_page() {

		global $wc_thanks_redirect_fs;

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

		printf(
			'<p class="form-field wctr-pro-nudge" style="padding-left:12px;color:#666;">%s <a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
			esc_html__( 'PRO lets you personalize this URL with shortcodes like customer name, order number, and order total.', 'wc-thanks-redirect' ),
			esc_url( $wc_thanks_redirect_fs->get_upgrade_url() ),
			esc_html__( 'Upgrade to PRO', 'wc-thanks-redirect' )
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
