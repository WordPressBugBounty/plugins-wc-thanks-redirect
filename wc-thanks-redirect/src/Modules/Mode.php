<?php

/**
 * @package     Thank You Page
 * @since       4.4.0
 */

namespace NeeBPlugins\Wctr\Modules;

/**
 * Sandbox / Live mode.
 *
 * In Sandbox, custom Thank You pages/redirects only apply to administrators.
 * When no mode has been saved yet, the plugin defaults to Live.
 *
 * @since 4.4.0
 */
class Mode {

	const OPTION_KEY = 'wctr_mode';
	const SANDBOX    = 'sandbox';
	const LIVE       = 'live';

	/**
	 * Get the current mode.
	 *
	 * @since 4.4.0
	 * @return string 'sandbox'|'live'
	 */
	public static function get() {
		$mode = get_option( self::OPTION_KEY, self::LIVE );
		return self::LIVE === $mode ? self::LIVE : self::SANDBOX;
	}

	/**
	 * Is the plugin currently in Sandbox mode?
	 *
	 * @since 4.4.0
	 * @return bool
	 */
	public static function is_sandbox() {
		return self::SANDBOX === self::get();
	}

	/**
	 * Whether the current user bypasses Sandbox restrictions.
	 *
	 * Matches the existing SandBox Payment Gateway's own admin-only rule
	 * (NeeBPlugins\Wctr\Modules\SandBoxPayment::restrict_to_admins), so
	 * "Sandbox" means the same thing everywhere in this plugin.
	 *
	 * @since 4.4.0
	 * @return bool
	 */
	public static function current_user_can_bypass_sandbox() {
		return current_user_can( 'administrator' );
	}

	/**
	 * Whether custom Thank You pages/redirects should apply to the current
	 * visitor right now.
	 *
	 * @since 4.4.0
	 * @return bool
	 */
	public static function applies_to_current_user() {
		if ( ! self::is_sandbox() ) {
			return true;
		}

		return self::current_user_can_bypass_sandbox();
	}
}
