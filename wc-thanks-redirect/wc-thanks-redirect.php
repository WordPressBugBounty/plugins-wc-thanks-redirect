<?php

/**
 *
 * @link    https://nitin247.com/plugin/wc-thanks-redirect/
 * @since   1.1
 * @package WC_Thanks_Redirect
 *
 * @wordpress-plugin
 * Plugin Name:       Thank You Page for WooCommerce
 * Plugin URI:        https://nitin247.com/plugin/wc-thanks-redirect/
 * Description:       Thank You Page for WooCommerce allows adding Thank You Page or Thank You URL for WooCommerce Products for your Customers, now supports Order Details on Thank You Page. This plugin does not support Multisite.
 * Version:           4.2.4
 * Author:            Nitin Prakash
 * Author URI:        http://www.nitin247.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wc-thanks-redirect
 * Domain Path:       /languages/
 * Requires PHP:      7.4
 * Requires at least: 6.2
 * WC requires at least: 8.2
 * WC tested up to: 10.0
 */

use NeeBPlugins\Wctr\Admin as WctrAdmin;
use NeeBPlugins\Wctr\Front as WctrFront;
use NeeBPlugins\Wctr\Api as WctrApi;

// Exit if accessed directly
defined( 'ABSPATH' ) || die( 'WordPress Error! Opening plugin file directly' );

defined( 'WCTR_VERSION' ) || define( 'WCTR_VERSION', '4.2.4' );
defined( 'WCTR_DIR' ) || define( 'WCTR_DIR', plugin_dir_path( __DIR__ ) );
defined( 'WCTR_FILE' ) || define( 'WCTR_FILE', __FILE__ );
defined( 'WCTR_PLUGIN_DIR' ) || define( 'WCTR_PLUGIN_DIR', plugin_dir_path( WCTR_FILE ) );
defined( 'WCTR_PLUGIN_URL' ) || define( 'WCTR_PLUGIN_URL', plugin_dir_url( WCTR_FILE ) );
defined( 'WCTR_KB_URL' ) || define( 'WCTR_KB_URL', 'https://nitin247.com/docs/thank-you-page/' );

// Include dependencies
if ( file_exists( WCTR_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once WCTR_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	wp_die( 'Plugin dependencies not installed!!!' );
}

if ( ! function_exists( 'wc_thanks_redirect_fs' ) ) {
	// Create a helper function for easy SDK access.
	function wc_thanks_redirect_fs() {
		global $wc_thanks_redirect_fs;

		if ( ! isset( $wc_thanks_redirect_fs ) ) {
			// Freemius SDK loaded via composer.
			include_once WCTR_PLUGIN_DIR . '/vendor/freemius/wordpress-sdk/start.php';

			$wc_thanks_redirect_fs = fs_dynamic_init(
				array(
					'id'              => '5290',
					'slug'            => 'wc-thanks-redirect',
					'type'            => 'plugin',
					'public_key'      => 'pk_a2ce319e73a5895901df9374e2a05',
					'is_premium'      => false,
					'is_premium_only' => false,
					'has_addons'      => false,
					'has_paid_plans'  => true,
					'has_affiliation' => false,
					'menu'            => array(
						'first-path' => 'admin.php?page=wc-settings&tab=products&section=wctr',
						'support'    => false,
					),
					'anonymous_mode'  => true,
				)
			);

		}

		return $wc_thanks_redirect_fs;
	}

	// Init Freemius.
	wc_thanks_redirect_fs();
	// Signal that SDK was initiated.
	do_action( 'wc_thanks_redirect_fs_loaded' );

	wc_thanks_redirect_fs()->add_filter(
		'plugin_icon',
		function () {
			return WCTR_PLUGIN_DIR . 'assets/img/icon.png';
		}
	);
}

if ( ! class_exists( 'WCTR_Plugin' ) ) {

	/**
	 * Class WCTR_Plugin
	 */
	final class WCTR_Plugin {

		private static $instance;

		/**
		 * Get Instance
		 *
		 * @since 4.1.7
		 * @return object initialized object of class.
		 */
		public static function get_instance() {

			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		public function __construct() {
			// Init plugin
			add_action( 'init', array( $this, 'before_plugin_load' ) );
			// Run plugin
			add_action( 'init', array( $this, 'run_plugin' ) );
			// HPOS Support
			add_action( 'before_woocommerce_init', array( $this, 'hpos_support' ) );
			// PRO Plugin Action links
			add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );
		}

		public function before_plugin_load() {

			if ( ! class_exists( 'woocommerce' ) ) {
				add_action( 'admin_notices', array( $this, 'wc_not_active' ) );
				return;
			}

			if ( is_multisite() ) {
				add_action( 'admin_notices', array( $this, 'multisite_admin_notice' ) );
				return;
			}
		}

		public function run_plugin() {
			// Initialize Plugin Admin
			$wctradmin = WctrAdmin::get_instance(); // phpcs:ignore		
			// Initialize Plugin Front
			$wctrfront = WctrFront::get_instance(); // phpcs:ignore
			// Initialize REST API Handler
			$wctrapi = WctrApi::get_instance(); // phpcs:ignore
		}

		public function hpos_support() {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}

		public function action_links( $links ) {
			$upgrade_url = wc_thanks_redirect_fs()->get_upgrade_url();
			$links       = array_merge(
				array(
					'<a href="' . esc_url( site_url() . '/wp-admin/admin.php?page=wc-settings&tab=products&section=wctr' ) . '">' . __( 'Settings', 'wc-thanks-redirect' ) . '</a>',
					'<a style="color:green;font-weight:bold;" href="' . esc_url( $upgrade_url ) . '">' . __( 'Go PRO!', 'wc-thanks-redirect' ) . '</a>',
					'<a target="_blank" href="' . esc_url( WCTR_KB_URL ) . '">' . __( 'Documentation', 'wc-thanks-redirect' ) . '</a>',
					'<a target="_blank" href="' . esc_url( 'https://nitin247.com/support/' ) . '">' . __( 'Support Desk', 'wc-thanks-redirect' ) . '</a>',
				),
				$links
			);
			return $links;
		}

		public function multisite_admin_notice() {
			echo '<div class="notice notice-error">';
			echo '<p>' . wp_kses_post( __( 'Thank You Page for WooCommerce is not designed for Multisite, you may need to buy this short plugin. <a target="_blank" href="https://bit.ly/2RwaIQB">Thank You Page for WooCommerce PRO</a>!', 'wc-thanks-redirect' ) ) . '</p>';
			echo '</div>';
		}
	}

	// Initiate Plugin Instance
	WCTR_Plugin::get_instance();

}

/* Get Order ID from request */
function wc_thanks_redirect_get_order_id() {
	global $wp;

	$order_id = 0;

	if ( isset( $_GET['key'] ) && ! empty( $_GET['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_key = ! empty( $_GET['key'] ) ? sanitize_text_field( $_GET['key'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_id  = wc_get_order_id_by_order_key( $order_key );
	} elseif ( isset( $_GET['order_key'] ) && ! empty( $_GET['order_key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_key = ! empty( $_GET['order_key'] ) ? sanitize_text_field( $_GET['order_key'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_id  = wc_get_order_id_by_order_key( $order_key );
	} else {
		$current_url = home_url( add_query_arg( array(), $wp->request ) );
		$parsed_url  = wp_parse_url( $current_url );
		$order_id    = array_pop( explode( '/', $parsed_url['path'] ) );
	}

	return $order_id;
}
