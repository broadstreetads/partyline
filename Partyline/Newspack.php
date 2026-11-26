<?php
/**
 * Newspack compatibility.
 *
 * Newspack owns the reader account experience: its own registration/login flow
 * (passwordless email sign-in) and the reader "My Account" area. Partyline's
 * Partyliner accounts are created outside that flow (admin "Add a Partyliner",
 * public applications confirmed by email, set-password emails), so on a Newspack
 * site we turn those off for now. Phase one, agreed with the Newspack team.
 *
 * What still works on Newspack: newsroom staff using the contributor app and SMS,
 * and anonymous submissions (when enabled). Anonymous submissions create no
 * WordPress account, so they don't sidestep Newspack's login.
 *
 * Every check here is a no-op on sites without Newspack.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Partyline_Newspack {

	const COMING_SOON = 'Adding Partyliners or community Partyliners is coming to Newspack in a version coming soon.';

	/** Is this a Newspack site? (The Newspack plugin is active.) */
	public static function isNewspack() {
		$is = defined( 'NEWSPACK_PLUGIN_FILE' ) || class_exists( 'Newspack\\Newspack' );

		/**
		 * Filter whether Partyline treats this site as a Newspack site.
		 *
		 * @param bool $is True when the Newspack plugin is active.
		 */
		return (bool) apply_filters( 'partyline_is_newspack', $is );
	}

	/**
	 * May Partyline create or activate Partyliner accounts (admin "Add a
	 * Partyliner", public applications, set-password emails)? Off on Newspack.
	 */
	public static function accountsAllowed() {
		/**
		 * Filter whether Partyline may create/activate Partyliner accounts itself.
		 *
		 * @param bool $allowed False on Newspack sites, true elsewhere.
		 */
		return (bool) apply_filters( 'partyline_allow_partyliner_accounts', ! self::isNewspack() );
	}

	/** The "coming soon" message as a WP_Error, for code paths that must refuse. */
	public static function error() {
		return new WP_Error( 'partyline_newspack_accounts', self::COMING_SOON, array( 'status' => 403 ) );
	}

	/** Admin notice box (uses the Partyline admin `plg-note` style). */
	public static function adminNote() {
		return '<div class="plg-note"><strong>Coming soon:</strong> ' . esc_html( self::COMING_SOON ) . '</div>';
	}
}
