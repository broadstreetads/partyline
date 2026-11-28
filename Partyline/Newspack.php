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
 * Anonymous app submissions are off on Newspack too: without stronger spam
 * protection (reCAPTCHA or similar) the Newspack team won't allow open public
 * submission on their platform.
 *
 * What still works on Newspack: signed-in users submitting through the
 * contributor app, and SMS.
 *
 * Every check here is a no-op on sites without Newspack.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Partyline_Newspack {

	const COMING_SOON = 'Adding Partyliners or community Partyliners is coming to Newspack in a version coming soon.';

	const ANONYMOUS_OFF = 'Anonymous submissions aren\'t available on Newspack sites yet.';

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

	/**
	 * May the contributor app take anonymous (logged-out) submissions? Off on
	 * Newspack, whatever the stored setting says.
	 */
	public static function anonymousAllowed() {
		/**
		 * Filter whether anonymous app submissions may be enabled.
		 *
		 * @param bool $allowed False on Newspack sites, true elsewhere.
		 */
		return (bool) apply_filters( 'partyline_allow_anonymous_submissions', ! self::isNewspack() );
	}

	/** The "coming soon" message as a WP_Error, for code paths that must refuse. */
	public static function error() {
		return new WP_Error( 'partyline_newspack_accounts', self::COMING_SOON, array( 'status' => 403 ) );
	}

	/** Admin notice box (uses the Partyline admin `plg-note` style). */
	public static function adminNote( $message = self::COMING_SOON ) {
		return '<div class="plg-note"><strong>Coming soon:</strong> ' . esc_html( $message ) . '</div>';
	}
}
