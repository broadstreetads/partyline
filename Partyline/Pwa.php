<?php
/**
 * Partyline PWA + REST submission channel.
 *
 * A second ingestion path alongside the Twilio SMS webhook: a logged-in
 * contributor uses the installable web app to capture a photo and dictate a
 * story, the audio is transcribed (OpenAI Whisper), AI drafts a title/body, and
 * result is submitted as the same kind of draft post the SMS path creates.
 *
 * EVERYTHING here is gated behind the `pwa_enabled` setting (default off), so
 * the module is completely inert until the feature is deliberately enabled.
 *
 * @author Broadstreet Ads <labs@broadstreetads.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Partyline_Pwa' ) ):

class Partyline_Pwa {

	const REST_NAMESPACE   = 'partyline/v1';
	const WHISPER_ENDPOINT = 'https://api.openai.com/v1/audio/transcriptions';
	const WHISPER_MODEL    = 'whisper-1';
	const CHAT_ENDPOINT    = 'https://api.openai.com/v1/chat/completions';
	const STORY_MODEL      = 'gpt-4o-mini'; // multimodal: accepts the photo for context
	const TURNSTILE_VERIFY = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	/** URL path the installable app is served under (no slashes). */
	const APP_PATH = 'partyline';

	/** Bump to invalidate the service-worker precache. */
	const PWA_ASSET_VERSION = '20';

	/**
	 * Register hooks. The contributor app is ON by default (see isEnabled), so
	 * this normally runs; it bails only when the app is explicitly disabled.
	 */
	public static function init() {
		if ( ! self::isEnabled() ) {
			return;
		}

		add_action( 'rest_api_init', array( __CLASS__, 'registerRoutes' ) );

		// Serve the installable app + service worker + manifest under /partyline/.
		// Priority 0 so we intercept before redirect_canonical (priority 10) can act.
		add_action( 'template_redirect', array( __CLASS__, 'maybeServeApp' ), 0 );
	}

	/** Is the contributor app enabled? Defaults to ON unless explicitly disabled. */
	public static function isEnabled() {
		$s = Partyline_Utility::getSettings();
		return ! isset( $s->pwa_enabled ) ? true : (bool) $s->pwa_enabled;
	}

	/** May people submit without logging in? Opt-in, default OFF. */
	public static function allowAnonymous() {
		$s = Partyline_Utility::getSettings();
		return ! empty( $s->pwa_allow_anonymous );
	}

	/** The current visitor is submitting anonymously (not logged in, but allowed). */
	public static function isAnonymousVisitor() {
		return ! is_user_logged_in() && self::allowAnonymous();
	}

	/** Is the public Partyliner signup page open? Opt-in, default OFF. */
	public static function signupEnabled() {
		$s = Partyline_Utility::getSettings();
		return ! empty( $s->partyliner_signup_enabled );
	}

	/** WordPress site title, sanitized for display. */
	private static function siteName() {
		return trim( wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) );
	}

	/** The installable app's title, e.g. "Partyline: Red Bank Green". */
	private static function appTitle() {
		$site = self::siteName();
		return '' !== $site ? 'Partyline: ' . $site : 'Partyline';
	}

	/** Home-screen headline (customizable in Settings; falls back to a default). */
	private static function homeTitle() {
		$s = Partyline_Utility::getSettings();
		$t = isset( $s->app_home_title ) ? trim( (string) $s->app_home_title ) : '';
		return '' !== $t ? $t : 'Send in a Partyline';
	}

	/** Home-screen subtext (customizable; generic, non-town-specific default). */
	private static function homeSubtitle() {
		$s = Partyline_Utility::getSettings();
		$t = isset( $s->app_home_subtitle ) ? trim( (string) $s->app_home_subtitle ) : '';
		return '' !== $t ? $t : 'Snap a photo and tell us what\'s happening around town. We\'ll take it from there.';
	}

	/**
	 * App icon URL at a given size: the WordPress Site Icon if one is set,
	 *  otherwise the bundled Partyline icon.
	 *
	 * @param int    $size         Desired icon size in px.
	 * @param string $fallback_rel Bundled icon path under Public/pwa/ to fall back to.
	 */
	private static function iconUrl( $size, $fallback_rel ) {
		$site_icon = get_site_icon_url( $size );
		if ( $site_icon ) {
			return self::secureUrl( $site_icon );
		}
		return self::assetUrl( $fallback_rel ) . '?v=' . self::PWA_ASSET_VERSION;
	}

	/* --------------------------------------------------------------------- */
	/* PWA shell routing:  /partyline/  ·  /sw.js  ·  /manifest.webmanifest */
	/* --------------------------------------------------------------------- */

	/** Absolute URL of the app root. */
	public static function appUrl( $sub = '' ) {
		return self::secureUrl( home_url( '/' . self::APP_PATH . '/' . ltrim( $sub, '/' ) ) );
	}

	/** URL of a static asset under Public/pwa/. */
	public static function assetUrl( $rel ) {
		return self::secureUrl( plugins_url( 'Public/pwa/' . ltrim( $rel, '/' ), __FILE__ ) );
	}

	/**
	 * PWAs require a secure context. This site's siteurl is http (the origin
	 * sits behind Cloudflare's TLS), so home_url()/plugins_url() return http —
	 * which the browser blocks as mixed content and which service workers
	 * reject. Upgrade to https, except on local dev hosts (treated as secure
	 * over http by browsers).
	 */
	private static function secureUrl( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( $host && ! preg_match( '/(^localhost$)|(^127\.)|(^192\.168\.)|(\.local$)|(\.test$)/', $host ) ) {
			return set_url_scheme( $url, 'https' );
		}
		return $url;
	}

	/**
	 * If the current request targets /partyline[/...], serve the matching
	 * resource and exit. Anything else falls through untouched.
	 */
	public static function maybeServeApp() {
		$path = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
		$path = '/' . trim( (string) $path, '/' );
		$base = '/' . self::APP_PATH;

		if ( $path !== $base && 0 !== strpos( $path, $base . '/' ) ) {
			return; // not ours
		}

		$sub = ltrim( substr( $path, strlen( $base ) ), '/' );

		switch ( $sub ) {
			case '':
				self::serveShell();
				break;
			case 'sw.js':
				self::serveServiceWorker();
				break;
			case 'manifest.webmanifest':
				self::serveManifest();
				break;
			case 'signup':
				self::serveSignup();
				break;
			case 'confirm':
				self::serveConfirm();
				break;
			default:
				return; // unknown sub-path -> let WordPress 404 it
		}
		exit;
	}

	/** The app shell HTML. Logged-in gets the full app; anonymous (if allowed) a lighter one. */
	public static function serveShell() {
		$logged_in = is_user_logged_in();
		if ( ! $logged_in && ! self::allowAnonymous() ) {
			wp_safe_redirect( wp_login_url( self::appUrl() ) );
			exit;
		}

		// WordPress marked this URL a 404 (it matches no route); override to 200.
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );

		$settings      = Partyline_Utility::getSettings();
		$anon          = ! $logged_in; // reaching here logged-out means anonymous is allowed
		$user          = wp_get_current_user();
		$name          = $logged_in ? ( $user->display_name ? $user->display_name : $user->user_login ) : '';
		$can_publish   = current_user_can( 'edit_others_posts' ); // editors/admins: publish now
		$turnstile_key = ( $anon && isset( $settings->turnstile_site_key ) ) ? trim( $settings->turnstile_site_key ) : '';
		// Anonymous submitters get the same lightweight math check the signup page
		// uses, so spam is filtered even when Turnstile isn't configured.
		$challenge     = $anon ? self::makeChallenge() : array( 'question' => '', 'token' => '' );

		$config = array(
			'restBase'     => esc_url_raw( rest_url( self::REST_NAMESPACE . '/' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'swUrl'        => esc_url_raw( self::appUrl( 'sw.js' ) ),
			'scope'        => '/' . self::APP_PATH . '/',
			'user'         => array( 'name' => $name ),
			'loggedIn'     => $logged_in,
			'anon'         => $anon,
			'turnstileKey' => $turnstile_key,
			'mathToken'    => $challenge['token'],
		);

		$css      = esc_url( self::assetUrl( 'app.css' ) ) . '?v=' . self::PWA_ASSET_VERSION;
		$js       = esc_url( self::assetUrl( 'app.js' ) ) . '?v=' . self::PWA_ASSET_VERSION;
		$icon     = esc_url( self::iconUrl( 192, 'icons/icon-192.png' ) );
		$apple    = esc_url( self::iconUrl( 180, 'icons/icon-180.png' ) );
		$manifest = esc_url( self::appUrl( 'manifest.webmanifest' ) );

		echo '<!doctype html><html lang="en"><head>';
		echo '<meta charset="utf-8">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">';
		echo '<title>' . esc_html( self::appTitle() ) . '</title>';
		echo '<link rel="manifest" href="' . $manifest . '">';
		echo '<meta name="theme-color" content="#18181b">';
		echo '<meta name="mobile-web-app-capable" content="yes">';
		echo '<meta name="apple-mobile-web-app-capable" content="yes">';
		echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">';
		echo '<meta name="apple-mobile-web-app-title" content="Partyline">';
		echo '<link rel="apple-touch-icon" href="' . $apple . '">';
		echo '<link rel="icon" href="' . $icon . '">';
		echo '<link rel="stylesheet" href="' . $css . '">';
		if ( $turnstile_key ) {
			echo '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
		}
		echo '</head><body>';

		echo '<div id="app">';
		$site = self::siteName();
		echo '<header class="pl-header">';
		echo '<span class="pl-brand"><img src="' . $icon . '" alt=""><span>Partyline';
		if ( '' !== $site ) {
			echo ' <span style="font-weight:400;font-size:13px;color:rgba(255,255,255,0.55);letter-spacing:0;">' . esc_html( $site ) . '</span>';
		}
		echo '</span></span>';
		echo '<span class="pl-user">' . esc_html( $logged_in ? $name : 'Guest' ) . '</span>';
		echo '</header>';

		echo '<main class="pl-main">';

		// --- HOME ---
		echo '<section id="screen-home" class="pl-screen">';
		echo '<div class="pl-hero"><h1>' . esc_html( self::homeTitle() ) . '</h1><p>' . esc_html( self::homeSubtitle() ) . '</p></div>';
		echo '<div id="pl-install" class="pl-install">';
		echo '<span id="pl-install-msg">Install Partyline to your home screen for one-tap access.</span>';
		echo '<button id="pl-install-btn" class="pl-btn pl-btn--lime" type="button">Install</button>';
		echo '</div>';
		echo '<div class="pl-actions">';
		echo '<button id="pl-start" class="pl-btn pl-btn--primary" type="button">Start a Partyline</button>';
		echo '<button id="pl-open-drafts" class="pl-btn pl-btn--ghost" type="button">📁 Your Partylines</button>';
		echo '</div>';
		echo '</section>';

		// --- DRAFTS / SAVED (local) ---
		echo '<section id="screen-drafts" class="pl-screen pl-hidden">';
		echo '<h2 class="pl-step" style="margin-top:4px;">Your Partylines</h2>';
		echo '<div id="pl-drafts-list" class="pl-drafts"></div>';
		echo '<div class="pl-actions"><button id="pl-drafts-back" class="pl-btn pl-btn--ghost" type="button">Back</button></div>';
		echo '</section>';

		// --- CAPTURE (all steps on one screen) ---
		echo '<section id="screen-capture" class="pl-screen pl-hidden">';

		// Step 1 — photo
		echo '<h2 class="pl-step"><span class="pl-stepnum">1</span> Take or upload a photo</h2>';
		echo '<input id="pl-input-camera" type="file" accept="image/*" capture="environment" hidden>';
		echo '<input id="pl-input-library" type="file" accept="image/*" hidden>';
		echo '<div class="pl-photo-actions">';
		echo '<button id="pl-photo-camera" class="pl-photo-btn2" type="button"><span>📷</span> Take photo</button>';
		echo '<button id="pl-photo-library" class="pl-photo-btn2" type="button"><span>🖼️</span> Choose photo</button>';
		echo '</div>';
		echo '<canvas id="pl-photo-canvas" class="pl-photo-canvas pl-hidden"></canvas>';
		echo '<div id="pl-filters" class="pl-filters pl-hidden">';
		echo '<button class="pl-chip is-active" data-filter="none" type="button">Original</button>';
		echo '<button class="pl-chip" data-filter="bw" type="button">B&amp;W</button>';
		echo '<button class="pl-chip" data-filter="warm" type="button">Warm</button>';
		echo '<button class="pl-chip" data-filter="cool" type="button">Cool</button>';
		echo '<button class="pl-chip" data-filter="vivid" type="button">Vivid</button>';
		echo '</div>';

		// Step 2 — story
		echo '<h2 class="pl-step"><span class="pl-stepnum">2</span> Tell the story</h2>';
		if ( $logged_in ) {
			// Voice dictation + AI write-up are logged-in only.
			echo '<div class="pl-record">';
			echo '<button id="pl-rec-btn" class="pl-recbtn" type="button" aria-label="Record"><span class="pl-recdot"></span></button>';
			echo '<div id="pl-rec-status" class="pl-status">Tap to dictate, or type it below.</div>';
			echo '</div>';
		}
		echo '<label class="pl-label" for="pl-title">Title</label>';
		echo '<input id="pl-title" class="pl-input" type="text" placeholder="Headline (optional)">';
		echo '<label class="pl-label" for="pl-body">Story</label>';
		echo '<textarea id="pl-body" class="pl-textarea" rows="6" placeholder="Tell us what happened…"></textarea>';

		// Step 3 — submit
		echo '<h2 class="pl-step"><span class="pl-stepnum">3</span> Submit</h2>';
		echo '<p id="pl-submit-hint" class="pl-hint">Add a photo and a story to submit.</p>';
		if ( $anon ) {
			// Contact info, so we can credit the submitter and follow up.
			echo '<p class="pl-hint" style="background:#f6f6f8;border-radius:10px;padding:11px 13px;margin-bottom:14px;line-height:1.5;">🔒 Your name is credited as the author of your Partyline. Your email and phone are never shown on the site or shared; the newsroom keeps them privately so we can let you know if your Partyline runs. They&rsquo;re also saved on this device so you don&rsquo;t have to enter them again next time.</p>';
			echo '<label class="pl-label" for="pl-name">Your name</label>';
			echo '<input id="pl-name" class="pl-input" type="text" autocomplete="name" placeholder="Jane Doe">';
			echo '<label class="pl-label" for="pl-email">Your email</label>';
			echo '<input id="pl-email" class="pl-input" type="email" autocomplete="email" placeholder="you@example.com">';
			echo '<label class="pl-label" for="pl-phone">Your phone</label>';
			echo '<input id="pl-phone" class="pl-input" type="tel" autocomplete="tel" placeholder="(732) 555-0123">';
		}
		if ( $can_publish ) {
			echo '<label class="pl-check"><input type="checkbox" id="pl-immediate"> Post immediately <span class="pl-check-note">(publish now, skip the draft)</span></label>';
			echo '<p class="pl-hint" style="margin-top:-8px;">Only editors and administrators see this option.</p>';
		}
		if ( $anon ) {
			// Simple anti-robot math check (always on for anonymous submitters).
			echo '<label class="pl-label" for="pl-math">' . esc_html( $challenge['question'] ) . ' <span style="color:#a1a1aa;font-weight:400;">(quick spam check)</span></label>';
			echo '<input id="pl-math" class="pl-input" type="text" inputmode="numeric" autocomplete="off" placeholder="Type the number">';
			// Honeypot — hidden from people, tripped by bots that fill every field.
			echo '<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;">';
			echo '<label>Leave this field empty<input id="pl-website" name="website" type="text" tabindex="-1" autocomplete="off"></label>';
			echo '</div>';
		}
		if ( $turnstile_key ) {
			echo '<div id="pl-turnstile" class="cf-turnstile pl-turnstile" data-sitekey="' . esc_attr( $turnstile_key ) . '" data-callback="plTurnstileCb" data-expired-callback="plTurnstileCb" data-error-callback="plTurnstileCb"></div>';
		}
		echo '<div class="pl-actions">';
		echo '<button id="pl-submit" class="pl-btn pl-btn--primary" type="button" disabled>Submit Partyline</button>';
		echo '<button id="pl-cancel" class="pl-btn pl-btn--ghost" type="button">Save draft and close</button>';
		echo '</div>';
		echo '</section>';

		// --- SUCCESS ---
		echo '<section id="screen-success" class="pl-screen pl-hidden">';
		echo '<div class="pl-hero"><h1>🎉 Sent!</h1><p id="pl-success-msg">Your Partyline was submitted for the newsroom.</p></div>';
		echo '<div class="pl-actions"><button id="pl-again" class="pl-btn pl-btn--lime" type="button">Send another</button></div>';
		echo '</section>';

		echo '</main>';

		echo '<footer class="pl-footer">redbankgreen · Partyline</footer>';
		echo '</div>';

		echo '<script>window.PARTYLINE_PWA=' . wp_json_encode( $config ) . ';</script>';
		echo '<script src="' . $js . '" defer></script>';
		echo '</body></html>';
	}

	/** The web app manifest. */
	public static function serveManifest() {
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		echo wp_json_encode( array(
			'name'             => 'Partyline',
			'short_name'       => 'Partyline',
			'description'      => 'Send a photo and a story to the ' . ( '' !== self::siteName() ? self::siteName() : 'local' ) . ' newsroom.',
			'start_url'        => self::appUrl(),
			'scope'            => self::appUrl(),
			'display'          => 'standalone',
			'orientation'      => 'portrait',
			'background_color' => '#ffffff',
			'theme_color'      => '#18181b',
			'icons'            => array(
				array(
					'src'     => self::iconUrl( 192, 'icons/icon-192.png' ),
					'sizes'   => '192x192',
					'type'    => 'image/png',
					'purpose' => 'any',
				),
				array(
					'src'     => self::iconUrl( 512, 'icons/icon-512.png' ),
					'sizes'   => '512x512',
					'type'    => 'image/png',
					'purpose' => 'any',
				),
			),
		) );
	}

	/** The service worker script (must be served at the app scope path). */
	public static function serveServiceWorker() {
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: /' . self::APP_PATH . '/' );

		$cache = 'partyline-pwa-v' . self::PWA_ASSET_VERSION;
		$precache = wp_json_encode( array(
			self::assetUrl( 'app.css' ) . '?v=' . self::PWA_ASSET_VERSION,
			self::assetUrl( 'app.js' ) . '?v=' . self::PWA_ASSET_VERSION,
			self::assetUrl( 'icons/icon-192.png' ) . '?v=' . self::PWA_ASSET_VERSION,
			self::assetUrl( 'icons/icon-512.png' ) . '?v=' . self::PWA_ASSET_VERSION,
		) );

		echo "/* Partyline PWA service worker */\n";
		echo "const CACHE = " . wp_json_encode( $cache ) . ";\n";
		echo "const PRECACHE = " . $precache . ";\n";
		echo <<<'JS'
self.addEventListener('install', function (e) {
	e.waitUntil(caches.open(CACHE).then(function (c) { return c.addAll(PRECACHE); }).then(function () { return self.skipWaiting(); }));
});
self.addEventListener('activate', function (e) {
	e.waitUntil(caches.keys().then(function (keys) {
		return Promise.all(keys.filter(function (k) { return k !== CACHE; }).map(function (k) { return caches.delete(k); }));
	}).then(function () { return self.clients.claim(); }));
});
self.addEventListener('fetch', function (e) {
	var req = e.request;
	if (req.method !== 'GET') { return; }
	var url = new URL(req.url);
	// Never cache dynamic/authenticated endpoints.
	if (url.pathname.indexOf('/wp-json/') !== -1 || url.pathname.indexOf('/wp-admin/') !== -1) { return; }
	// Cache-first for our precached static assets; network otherwise.
	e.respondWith(caches.match(req).then(function (hit) { return hit || fetch(req); }));
});
JS;
	}

	/* --------------------------------------------------------------------- */
	/* Public Partyliner signup:  /partyline/signup  +  /partyline/confirm    */
	/* --------------------------------------------------------------------- */

	/** Full URL of the signup page. */
	public static function signupUrl() {
		return self::appUrl( 'signup' );
	}

	/** Wrap body HTML in a minimal, app-styled standalone page. */
	private static function renderPage( $body ) {
		$css  = esc_url( self::assetUrl( 'app.css' ) ) . '?v=' . self::PWA_ASSET_VERSION;
		$icon = esc_url( self::iconUrl( 192, 'icons/icon-192.png' ) );
		$settings      = Partyline_Utility::getSettings();
		$turnstile_key = isset( $settings->turnstile_site_key ) ? trim( $settings->turnstile_site_key ) : '';

		$html  = '<!doctype html><html lang="en"><head><meta charset="utf-8">';
		$html .= '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">';
		$html .= '<title>' . esc_html( self::appTitle() ) . '</title><meta name="theme-color" content="#18181b">';
		$html .= '<link rel="icon" href="' . $icon . '"><link rel="stylesheet" href="' . $css . '">';
		if ( $turnstile_key ) {
			$html .= '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
		}
		$html .= '</head><body><div id="app">';
		$site  = self::siteName();
		$html .= '<header class="pl-header"><span class="pl-brand"><img src="' . $icon . '" alt=""><span>Partyline';
		if ( '' !== $site ) {
			$html .= ' <span style="font-weight:400;font-size:13px;color:rgba(255,255,255,0.55);letter-spacing:0;">' . esc_html( $site ) . '</span>';
		}
		$html .= '</span></span></header>';
		$html .= '<main class="pl-main">' . $body . '</main>';
		$html .= '<footer class="pl-footer">redbankgreen &middot; Partyline</footer>';
		$html .= '</div></body></html>';
		return $html;
	}

	/* --- Simple "are you human" math check (stateless, HMAC-signed) ------- */

	/** Secret used to sign the math challenge. */
	private static function challengeSecret() {
		return wp_salt( 'auth' ) . '|partyline-signup-challenge';
	}

	/**
	 * Build a small addition challenge. Returns array( question, token ) where
	 *  the token is an HMAC of the answer + expiry, so it can be verified
	 *  statelessly (no session or transient) when the form is posted back.
	 */
	private static function makeChallenge() {
		$a       = wp_rand( 1, 9 );
		$b       = wp_rand( 1, 9 );
		$answer  = $a + $b;
		$expires = time() + 30 * MINUTE_IN_SECONDS;
		$sig     = hash_hmac( 'sha256', $answer . '|' . $expires, self::challengeSecret() );
		return array(
			'question' => sprintf( 'What is %d + %d?', $a, $b ),
			'token'    => $expires . '.' . $sig,
		);
	}

	/** Verify a submitted answer against its signed, unexpired token. */
	private static function verifyChallenge( $answer, $token ) {
		$answer = trim( (string) $answer );
		if ( ! preg_match( '/^\d{1,3}$/', $answer ) ) {
			return false;
		}
		$parts = explode( '.', (string) $token, 2 );
		if ( count( $parts ) !== 2 ) {
			return false;
		}
		list( $expires, $sig ) = $parts;
		if ( ! ctype_digit( $expires ) || (int) $expires < time() ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', (int) $answer . '|' . $expires, self::challengeSecret() );
		return hash_equals( $expected, (string) $sig );
	}

	/** GET /partyline/signup — the public signup form. */
	public static function serveSignup() {
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );

		if ( ! self::signupEnabled() ) {
			echo self::renderPage( '<section class="pl-screen"><div class="pl-hero"><h1>Signups are closed</h1><p>Public signup isn\'t open right now.</p></div></section>' );
			return;
		}

		$settings      = Partyline_Utility::getSettings();
		$turnstile_key = isset( $settings->turnstile_site_key ) ? trim( $settings->turnstile_site_key ) : '';

		$b  = '<section id="screen-signup" class="pl-screen">';
		$b .= '<div class="pl-hero"><h1>Become a Partyliner</h1><p>Sign up to send in photos and stories from around town. We&rsquo;ll email you to confirm.</p></div>';
		$b .= '<label class="pl-label" for="s-name">Name</label><input id="s-name" class="pl-input" type="text" autocomplete="name" placeholder="Jane Doe">';
		$b .= '<label class="pl-label" for="s-email">Email</label><input id="s-email" class="pl-input" type="email" autocomplete="email" placeholder="you@example.com">';
		$b .= '<label class="pl-label" for="s-phone">Phone</label><input id="s-phone" class="pl-input" type="tel" autocomplete="tel" placeholder="(732) 555-0123">';
		$b .= '<label class="pl-label" for="s-address">Address <span style="color:#a1a1aa;font-weight:400;">(optional)</span></label><input id="s-address" class="pl-input" type="text" autocomplete="street-address" placeholder="123 Broad St, Red Bank">';

		// Simple anti-robot math check (always on, no third party required).
		$challenge = self::makeChallenge();
		$b .= '<label class="pl-label" for="s-math">' . esc_html( $challenge['question'] ) . ' <span style="color:#a1a1aa;font-weight:400;">(quick spam check)</span></label>';
		$b .= '<input id="s-math" class="pl-input" type="text" inputmode="numeric" autocomplete="off" placeholder="Type the number">';

		// Honeypot — hidden from people, but bots that fill every field trip it.
		$b .= '<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;">';
		$b .= '<label>Leave this field empty<input id="s-website" name="website" type="text" tabindex="-1" autocomplete="off"></label>';
		$b .= '</div>';

		if ( $turnstile_key ) {
			$b .= '<div id="s-turnstile" class="cf-turnstile pl-turnstile" data-sitekey="' . esc_attr( $turnstile_key ) . '" data-callback="sTurnstileCb"></div>';
		}
		$b .= '<div class="pl-actions"><button id="s-submit" class="pl-btn pl-btn--primary" type="button" disabled>Sign up</button></div>';
		$b .= '<p id="s-status" class="pl-status"></p>';
		$b .= '</section>';

		$cfg = wp_json_encode( array(
			'restBase'     => esc_url_raw( rest_url( self::REST_NAMESPACE . '/' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'turnstileKey' => $turnstile_key,
			'mathToken'    => $challenge['token'],
		) );

		$b .= '<script>window.PL_SIGNUP=' . $cfg . ';</script>';
		$b .= '<script src="' . esc_url( self::assetUrl( 'signup.js' ) ) . '?v=' . self::PWA_ASSET_VERSION . '" defer></script>';

		echo self::renderPage( $b );
	}

	/** GET /partyline/confirm?token=… — activate the account from an email link. */
	public static function serveConfirm() {
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended — the token IS the secret.
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		$data  = $token ? get_transient( 'partyline_signup_' . $token ) : false;

		if ( ! is_array( $data ) || empty( $data['email'] ) ) {
			echo self::renderPage( '<section class="pl-screen"><div class="pl-hero"><h1>Link expired</h1><p>This confirmation link is invalid or has already been used. Please sign up again.</p></div><div class="pl-actions"><a class="pl-btn pl-btn--primary" href="' . esc_url( self::signupUrl() ) . '">Sign up again</a></div></section>' );
			return;
		}

		$uid = Partyline_Utility::findOrCreatePartyliner( $data );
		delete_transient( 'partyline_signup_' . $token );

		if ( is_wp_error( $uid ) ) {
			echo self::renderPage( '<section class="pl-screen"><div class="pl-hero"><h1>Something went wrong</h1><p>' . esc_html( $uid->get_error_message() ) . '</p></div><div class="pl-actions"><a class="pl-btn pl-btn--primary" href="' . esc_url( self::signupUrl() ) . '">Try again</a></div></section>' );
			return;
		}

		echo self::renderPage( '<section class="pl-screen"><div class="pl-hero"><h1>🎉 You&rsquo;re in!</h1><p>Your Partyliner account is confirmed. Tap below to send your first Partyline.</p></div><div class="pl-actions"><a class="pl-btn pl-btn--primary" href="' . esc_url( self::appUrl() ) . '">Open Partyline</a></div></section>' );
	}

	/** POST /partyline/v1/signup — validate, Turnstile, email a confirmation link. */
	public static function restSignup( WP_REST_Request $request ) {
		if ( ! self::signupEnabled() ) {
			return new WP_Error( 'partyline_signup_off', 'Signups are not open.', array( 'status' => 403 ) );
		}

		$name    = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$email   = sanitize_email( (string) $request->get_param( 'email' ) );
		$phone   = sanitize_text_field( (string) $request->get_param( 'phone' ) );
		$address = sanitize_textarea_field( (string) $request->get_param( 'address' ) );

		if ( '' === $name || ! is_email( $email ) || strlen( preg_replace( '/\D/', '', $phone ) ) < 7 ) {
			return new WP_Error( 'partyline_signup_fields', 'Please provide your name, a valid email, and a phone number.', array( 'status' => 400 ) );
		}

		// Honeypot: real people never fill this. Silently accept so bots that
		//  trip it think they succeeded (and stop retrying).
		if ( '' !== trim( (string) $request->get_param( 'website' ) ) ) {
			return rest_ensure_response( array(
				'message' => 'Thanks! Check your email to confirm your Partyliner account.',
			) );
		}

		// Simple math anti-robot check — always required.
		if ( ! self::verifyChallenge( $request->get_param( 'math_answer' ), (string) $request->get_param( 'math_token' ) ) ) {
			return new WP_Error( 'partyline_signup_math', 'That answer to the math question was not quite right. Please try again.', array( 'status' => 400 ) );
		}

		// Verify Turnstile only when it's configured.
		$settings = Partyline_Utility::getSettings();
		if ( ! empty( $settings->turnstile_site_key ) ) {
			$verify = self::verifyTurnstile( (string) $request->get_param( 'turnstile' ) );
			if ( is_wp_error( $verify ) ) {
				return $verify;
			}
		}

		$generic = rest_ensure_response( array(
			'message' => 'Thanks! Check your email to confirm your Partyliner account.',
		) );

		// Already a member? Don't leak that; just nudge them to the app.
		if ( email_exists( $email ) ) {
			self::sendSignupEmail( $email, $name, self::appUrl(), 'already' );
			return $generic;
		}

		$token = wp_generate_password( 32, false );
		set_transient( 'partyline_signup_' . $token, array(
			'name'    => $name,
			'email'   => $email,
			'phone'   => Partyline_Utility::normalizePhone( $phone ),
			'address' => $address,
		), 2 * DAY_IN_SECONDS );

		$confirm_url = add_query_arg( 'token', rawurlencode( $token ), self::appUrl( 'confirm' ) );
		self::sendSignupEmail( $email, $name, $confirm_url, 'confirm' );

		return $generic;
	}

	/** Send the signup confirmation (or "already a member") email. */
	private static function sendSignupEmail( $email, $name, $url, $type ) {
		$greeting = $name !== '' ? 'Hi ' . $name . ',' : 'Hi there,';
		if ( 'already' === $type ) {
			$intro = 'You&rsquo;re already a Partyliner. Thanks! Whenever you spot something worth sharing, use the link below to send it in.';
			$cta   = 'Open Partyline';
			$subj  = 'You\'re already a Partyliner';
		} else {
			$intro = 'Thanks for signing up to be a Partyliner. Confirm your account with the button below and you&rsquo;re all set to send in photos and stories.';
			$cta   = 'Confirm my account';
			$subj  = 'Confirm your Partyliner account';
		}

		$logo = set_url_scheme( Partyline_Utility::getImageBaseURL() . 'partyline-black.png', 'https' );

		ob_start();
		?>
<div style="background:#f4f4f5;margin:0;padding:24px 12px;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;"><tr><td align="center">
    <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="border-collapse:collapse;max-width:600px;width:100%;background:#fff;border:1px solid #e4e4e7;border-radius:16px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
      <tr><td style="padding:28px 24px 22px;text-align:center;border-bottom:1px solid #f0f0f1;"><img src="<?php echo esc_url( $logo ); ?>" width="170" alt="Partyline" style="width:170px;max-width:60%;height:auto;"></td></tr>
      <tr><td style="padding:26px 30px 4px;color:#3f3f46;font-size:15px;line-height:1.6;"><?php echo esc_html( $greeting ); ?></td></tr>
      <tr><td style="padding:8px 30px 0;color:#3f3f46;font-size:15px;line-height:1.65;"><?php echo wp_kses_post( $intro ); ?></td></tr>
      <tr><td style="padding:24px 30px 8px;"><a href="<?php echo esc_url( $url ); ?>" style="display:inline-block;background:#18181b;color:#fff;text-decoration:none;font-weight:700;font-size:15px;padding:13px 24px;border-radius:12px;"><?php echo esc_html( $cta ); ?></a></td></tr>
      <tr><td style="padding:8px 30px 28px;color:#a1a1aa;font-size:12px;line-height:1.6;">If you didn&rsquo;t request this, you can ignore this email.</td></tr>
    </table>
    <div style="max-width:600px;margin:14px auto 0;color:#a1a1aa;font-size:12px;text-align:center;">redbankgreen &middot; Partyline</div>
  </td></tr></table>
</div>
		<?php
		wp_mail( $email, $subj, (string) ob_get_clean(), array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	/**
	 * Register REST routes. AI routes (transcribe/generate) are always
	 * logged-in only. /submit allows anonymous when the setting is on.
	 */
	public static function registerRoutes() {
		register_rest_route( self::REST_NAMESPACE, '/transcribe', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'restTranscribe' ),
			'permission_callback' => array( __CLASS__, 'permissionLoggedIn' ),
		) );

		register_rest_route( self::REST_NAMESPACE, '/generate', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'restGenerate' ),
			'permission_callback' => array( __CLASS__, 'permissionLoggedIn' ),
		) );

		register_rest_route( self::REST_NAMESPACE, '/submit', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'restSubmit' ),
			'permission_callback' => array( __CLASS__, 'permissionSubmit' ),
		) );

		register_rest_route( self::REST_NAMESPACE, '/signup', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'restSignup' ),
			'permission_callback' => array( __CLASS__, 'signupEnabled' ),
		) );
	}

	/** Logged-in only (used for the AI endpoints). */
	public static function permissionLoggedIn() {
		if ( is_user_logged_in() ) {
			return true;
		}
		return new WP_Error( 'partyline_not_logged_in', 'You must be logged in to use this.', array( 'status' => 401 ) );
	}

	/** Submit: logged-in, or anonymous when that's allowed. */
	public static function permissionSubmit() {
		if ( is_user_logged_in() || self::allowAnonymous() ) {
			return true;
		}
		return new WP_Error( 'partyline_not_logged_in', 'You must be logged in to submit a Partyline.', array( 'status' => 401 ) );
	}

	/* --------------------------------------------------------------------- */
	/* POST /transcribe  — multipart audio file -> OpenAI Whisper -> text      */
	/* --------------------------------------------------------------------- */
	public static function restTranscribe( WP_REST_Request $request ) {
		$settings = Partyline_Utility::getSettings();
		// Reuse the OpenAI key already configured for ChatGPT.
		$key = isset( $settings->chatgpt_api_key ) ? trim( $settings->chatgpt_api_key ) : '';

		if ( empty( $key ) ) {
			return new WP_Error( 'partyline_no_openai_key', 'Transcription is not configured (missing OpenAI API key).', array( 'status' => 500 ) );
		}

		if ( empty( $_FILES['audio'] ) || empty( $_FILES['audio']['tmp_name'] ) || ! is_uploaded_file( $_FILES['audio']['tmp_name'] ) ) {
			return new WP_Error( 'partyline_no_audio', 'No audio provided.', array( 'status' => 400 ) );
		}

		$tmp  = $_FILES['audio']['tmp_name'];
		$type = ! empty( $_FILES['audio']['type'] ) ? sanitize_text_field( $_FILES['audio']['type'] ) : 'application/octet-stream';
		$name = isset( $_FILES['audio']['name'] ) ? sanitize_file_name( $_FILES['audio']['name'] ) : 'recording.webm';

		// Whisper infers the container from the filename extension.
		$allowed = array( 'flac', 'm4a', 'mp3', 'mp4', 'mpeg', 'mpga', 'oga', 'ogg', 'wav', 'webm' );
		$ext     = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $allowed, true ) ) {
			$ext = self::extForMime( $type );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = file_get_contents( $tmp );
		if ( false === $contents || '' === $contents ) {
			return new WP_Error( 'partyline_bad_audio', 'Could not read the audio.', array( 'status' => 400 ) );
		}

		$fields = array( 'model' => self::WHISPER_MODEL );
		$terms  = self::dictionary();
		if ( ! empty( $terms ) ) {
			// Whisper's optional prompt biases spelling toward these proper nouns.
			$fields['prompt'] = implode( ', ', $terms );
		}

		$boundary = 'partyline' . wp_generate_password( 16, false );
		$body     = self::multipartBody( $fields, 'file', 'audio.' . $ext, $type, $contents, $boundary );

		$response = wp_remote_post( self::WHISPER_ENDPOINT, array(
			'timeout' => 60,
			'headers' => array(
				'Authorization' => 'Bearer ' . $key,
				'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
			),
			'body'    => $body,
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'partyline_whisper_error', $response->get_error_message(), array( 'status' => 502 ) );
		}

		$code    = wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) $code || ! isset( $decoded['text'] ) ) {
			Partyline_Log::add( 'error', 'Whisper transcription failed (' . $code . '): ' . wp_remote_retrieve_body( $response ) );
			return new WP_Error( 'partyline_whisper_failed', 'Transcription failed.', array( 'status' => 502 ) );
		}

		return rest_ensure_response( array( 'text' => trim( $decoded['text'] ) ) );
	}

	/**
	 * Optional transcription vocabulary (local place names, etc.), from the
	 * `transcription_dictionary` setting (comma/newline separated). Filterable.
	 */
	public static function dictionary() {
		$settings = Partyline_Utility::getSettings();
		$raw      = isset( $settings->transcription_dictionary ) ? (string) $settings->transcription_dictionary : '';
		$terms    = array_filter( array_map( 'trim', preg_split( '/[\n,]+/', $raw ) ) );
		return apply_filters( 'partyline_pwa_dictionary', array_values( $terms ) );
	}

	/** Map an audio mime type to a file extension Whisper recognizes. */
	private static function extForMime( $type ) {
		$type = strtolower( (string) $type );
		if ( false !== strpos( $type, 'webm' ) ) { return 'webm'; }
		if ( false !== strpos( $type, 'ogg' ) || false !== strpos( $type, 'opus' ) ) { return 'ogg'; }
		if ( false !== strpos( $type, 'mp4' ) || false !== strpos( $type, 'm4a' ) || false !== strpos( $type, 'aac' ) ) { return 'mp4'; }
		if ( false !== strpos( $type, 'mpeg' ) || false !== strpos( $type, 'mp3' ) ) { return 'mp3'; }
		if ( false !== strpos( $type, 'wav' ) ) { return 'wav'; }
		return 'webm';
	}

	/** Build a multipart/form-data body with scalar fields plus one file part. */
	private static function multipartBody( array $fields, $file_field, $filename, $filetype, $filecontents, $boundary ) {
		$eol  = "\r\n";
		$body = '';
		foreach ( $fields as $fname => $fvalue ) {
			$body .= '--' . $boundary . $eol;
			$body .= 'Content-Disposition: form-data; name="' . $fname . '"' . $eol . $eol;
			$body .= $fvalue . $eol;
		}
		$body .= '--' . $boundary . $eol;
		$body .= 'Content-Disposition: form-data; name="' . $file_field . '"; filename="' . $filename . '"' . $eol;
		$body .= 'Content-Type: ' . $filetype . $eol . $eol;
		$body .= $filecontents . $eol;
		$body .= '--' . $boundary . '--' . $eol;
		return $body;
	}

	/* --------------------------------------------------------------------- */
	/* POST /generate  — transcript (+ optional photo) -> {title, body}        */
	/* --------------------------------------------------------------------- */
	public static function restGenerate( WP_REST_Request $request ) {
		$transcript = $request->get_param( 'transcript' );
		$transcript = is_string( $transcript ) ? trim( wp_strip_all_tags( $transcript ) ) : '';

		// Optional photo, base64'd into a data URL for the vision model.
		$image_data_url = null;
		if ( ! empty( $_FILES['image'] ) && ! empty( $_FILES['image']['tmp_name'] ) && is_uploaded_file( $_FILES['image']['tmp_name'] ) ) {
			$type = ! empty( $_FILES['image']['type'] ) ? $_FILES['image']['type'] : 'image/jpeg';
			if ( 0 === strpos( $type, 'image/' ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$bytes = file_get_contents( $_FILES['image']['tmp_name'] );
				if ( false !== $bytes && '' !== $bytes ) {
					$image_data_url = 'data:' . $type . ';base64,' . base64_encode( $bytes );
				}
			}
		}

		if ( '' === $transcript && null === $image_data_url ) {
			return new WP_Error( 'partyline_no_input', 'Nothing to write from.', array( 'status' => 400 ) );
		}

		return rest_ensure_response( self::generateStory( $transcript, $image_data_url ) );
	}

	/**
	 * Rewrite a dictated account into a short, professional blurb + headline,
	 * optionally using the photo for visual context. Returns array{title, body}.
	 */
	public static function generateStory( $transcript, $image_data_url = null ) {
		$settings = Partyline_Utility::getSettings();
		$key      = isset( $settings->chatgpt_api_key ) ? trim( $settings->chatgpt_api_key ) : '';

		// No API key: hand back the raw transcript so the contributor can edit.
		if ( empty( $key ) ) {
			$words = str_word_count( $transcript, 1 );
			return array(
				'title' => $words ? ucfirst( implode( ' ', array_slice( $words, 0, 6 ) ) ) : Partyline_Core::DEFAULT_TITLE,
				'body'  => $transcript,
			);
		}

		$user_content = array( array(
			'type' => 'text',
			'text' => '' !== $transcript
				? ( "Reader's dictated account:\n" . $transcript )
				: 'The reader did not dictate anything — base the blurb on the photo.',
		) );
		if ( $image_data_url ) {
			$user_content[] = array(
				'type'      => 'image_url',
				'image_url' => array( 'url' => $image_data_url, 'detail' => 'low' ),
			);
		}

		$response = wp_remote_post( self::CHAT_ENDPOINT, array(
			'timeout' => 60,
			'headers' => array(
				'Authorization' => 'Bearer ' . $key,
				'Content-Type'  => 'application/json',
			),
			'body' => wp_json_encode( array(
				'model'           => self::STORY_MODEL,
				'max_tokens'      => 500,
				'response_format' => array( 'type' => 'json_object' ),
				'messages'        => array(
					array( 'role' => 'system', 'content' => self::storyPrompt() ),
					array( 'role' => 'user', 'content' => $user_content ),
				),
			) ),
		) );

		// On any failure, fall back to the transcript + a naive title.
		$fallback = array(
			'title' => Partyline_Utility::generateTitle( $transcript ),
			'body'  => $transcript,
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			Partyline_Log::add( 'error', 'Story generation failed: ' . ( is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response ) ) );
			return $fallback;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		$content = isset( $decoded['choices'][0]['message']['content'] ) ? $decoded['choices'][0]['message']['content'] : '';
		$parsed  = json_decode( $content, true );

		if ( ! is_array( $parsed ) || ( empty( $parsed['title'] ) && empty( $parsed['body'] ) ) ) {
			return $fallback;
		}

		return array(
			'title' => isset( $parsed['title'] ) ? sanitize_text_field( $parsed['title'] ) : $fallback['title'],
			'body'  => isset( $parsed['body'] ) ? trim( wp_kses_post( $parsed['body'] ) ) : $transcript,
		);
	}

	/** The shared editorial voice, plus the blurb + JSON output contract. */
	public static function storyPrompt() {
		return Partyline_Utility::aiPrompt() . "\n\n"
			. 'Respond ONLY with a JSON object of the form {"title": "...", "body": "..."}. '
			. 'The "body" is the reader\'s submission cleaned up per the instructions above, staying as close as possible to their original wording. '
			. 'The "title" is a short, factual headline for it. Do not invent facts beyond the submission and photo.';
	}

	/* --------------------------------------------------------------------- */
	/* POST /submit  — multipart: title, body, optional image file            */
	/* --------------------------------------------------------------------- */
	public static function restSubmit( WP_REST_Request $request ) {
		$anon = self::isAnonymousVisitor();

		$title     = sanitize_text_field( trim( (string) $request->get_param( 'title' ) ) );
		$body      = wp_kses_post( trim( (string) $request->get_param( 'body' ) ) );
		$has_image = ! empty( $_FILES['image'] ) && ! empty( $_FILES['image']['name'] );

		$submitter = null;
		if ( $anon ) {
			// Anonymous submissions require name + email, a photo, story text, and
			// a valid Cloudflare Turnstile token.
			$sub_name  = sanitize_text_field( (string) $request->get_param( 'name' ) );
			$sub_email = sanitize_email( (string) $request->get_param( 'email' ) );
			$sub_phone = sanitize_text_field( (string) $request->get_param( 'phone' ) );

			if ( '' === $sub_name || ! is_email( $sub_email ) || strlen( preg_replace( '/\D/', '', $sub_phone ) ) < 7 ) {
				return new WP_Error( 'partyline_contact', 'Please provide your name, a valid email, and a phone number.', array( 'status' => 400 ) );
			}
			if ( '' === $body || ! $has_image ) {
				return new WP_Error( 'partyline_incomplete', 'A photo and a story are both required.', array( 'status' => 400 ) );
			}

			// Honeypot: real people never fill this. Silently accept so bots that
			//  trip it think they succeeded (and stop retrying) — no post created.
			if ( '' !== trim( (string) $request->get_param( 'website' ) ) ) {
				return rest_ensure_response( array(
					'post_id'   => 0,
					'published' => false,
					'edit_link' => '',
					'view_link' => '',
					'message'   => 'Thanks! Your Partyline was submitted for review.',
				) );
			}

			// Simple math anti-robot check — always required for anonymous submits.
			if ( ! self::verifyChallenge( $request->get_param( 'math_answer' ), (string) $request->get_param( 'math_token' ) ) ) {
				return new WP_Error( 'partyline_math', 'That answer to the math question was not quite right. Please try again.', array( 'status' => 400 ) );
			}

			// Cloudflare Turnstile is the most reliable filter — verify it too,
			//  but only when a site key is configured (it's optional).
			$settings = Partyline_Utility::getSettings();
			if ( ! empty( $settings->turnstile_site_key ) ) {
				$verify = self::verifyTurnstile( (string) $request->get_param( 'turnstile' ) );
				if ( is_wp_error( $verify ) ) {
					return $verify;
				}
			}
			$submitter = array( 'name' => $sub_name, 'email' => $sub_email, 'phone' => $sub_phone );
		}

		if ( '' === $title && '' === $body && ! $has_image ) {
			return new WP_Error( 'partyline_empty', 'Nothing to submit.', array( 'status' => 400 ) );
		}

		if ( $anon ) {
			$author_id   = 1; // attribute anonymous posts to the site admin
			$author_name = $submitter['name'];
			$from        = $submitter['email'];
		} else {
			$user        = wp_get_current_user();
			$author_id   = $user->ID;
			$author_name = $user->display_name ? $user->display_name : $user->user_login;
			$from        = $user->user_email;
		}

		// "Post immediately" — editors/admins only (never anonymous).
		$immediate = $request->get_param( 'immediate' );
		$immediate = ! empty( $immediate ) && 'false' !== $immediate && '0' !== $immediate;
		$publish   = $immediate && current_user_can( 'edit_others_posts' );

		$attachment_id = 0;
		if ( $has_image ) {
			$attachment_id = self::handleImageUpload( 'image' );
			if ( is_wp_error( $attachment_id ) ) {
				return $attachment_id;
			}
		}

		// Raw dictation/typed text as originally sent (shown in the notification email).
		$original = sanitize_textarea_field( (string) $request->get_param( 'original' ) );

		$post_id = self::createPost( array(
			'title'         => '' !== $title ? $title : Partyline_Core::DEFAULT_TITLE,
			'body'          => $body,
			'attachment_id' => $attachment_id,
			'author_id'     => $author_id,
			'author_name'   => $author_name,
			'from'          => $from,
			'status'        => $publish ? 'publish' : 'draft',
			'submitter'     => $submitter,
			'original'      => $original,
		) );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return rest_ensure_response( array(
			'post_id'   => $post_id,
			'published' => $publish,
			'edit_link' => is_user_logged_in() ? ( get_admin_url() . 'post.php?post=' . $post_id . '&action=edit' ) : '',
			'view_link' => $publish ? get_permalink( $post_id ) : '',
			'message'   => $publish
				? 'Published! Your Partyline is live.'
				: ( $anon ? 'Thanks! Your Partyline was submitted for review.' : 'Thanks! Your Partyline was submitted as a draft.' ),
		) );
	}

	/** Verify a Cloudflare Turnstile token for anonymous submissions. */
	private static function verifyTurnstile( $token ) {
		$settings = Partyline_Utility::getSettings();
		$secret   = isset( $settings->turnstile_secret_key ) ? trim( $settings->turnstile_secret_key ) : '';

		if ( '' === $secret ) {
			return new WP_Error( 'partyline_turnstile_unconfigured', 'Public submissions are temporarily unavailable.', array( 'status' => 503 ) );
		}
		if ( '' === $token ) {
			return new WP_Error( 'partyline_turnstile', 'Please complete the verification.', array( 'status' => 400 ) );
		}

		$ip   = '';
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $k ) {
			if ( ! empty( $_SERVER[ $k ] ) ) {
				$ip = sanitize_text_field( trim( explode( ',', wp_unslash( $_SERVER[ $k ] ) )[0] ) );
				break;
			}
		}

		$resp = wp_remote_post( self::TURNSTILE_VERIFY, array(
			'timeout' => 15,
			'body'    => array( 'secret' => $secret, 'response' => $token, 'remoteip' => $ip ),
		) );
		if ( is_wp_error( $resp ) ) {
			return new WP_Error( 'partyline_turnstile', 'Verification failed, please try again.', array( 'status' => 502 ) );
		}
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $data['success'] ) ) {
			return new WP_Error( 'partyline_turnstile', 'Verification failed, please try again.', array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Shared draft creation for the PWA/REST path.
	 *
	 * NOTE: the SMS path in Partyline_Core::catchTwilioWebhook() still builds
	 * its own post inline; leaving it untouched keeps the live SMS intake safe.
	 * Once the PWA path is proven, that path can be refactored onto this method.
	 *
	 * @param array $args title, body, attachment_id, author_id, author_name, from
	 * @return int|WP_Error post ID
	 */
	public static function createPost( array $args ) {
		$settings = Partyline_Utility::getSettings();
		$category = isset( $settings->partyline_category ) ? $settings->partyline_category : 0;

		$title         = isset( $args['title'] ) ? $args['title'] : Partyline_Core::DEFAULT_TITLE;
		$body          = isset( $args['body'] ) ? $args['body'] : '';
		$attachment_id = isset( $args['attachment_id'] ) ? (int) $args['attachment_id'] : 0;
		$author_id     = isset( $args['author_id'] ) ? (int) $args['author_id'] : 1;
		$author_name   = isset( $args['author_name'] ) ? $args['author_name'] : 'Anonymous Partyliner';
		$from          = isset( $args['from'] ) ? $args['from'] : $author_name;
		$status        = ( isset( $args['status'] ) && 'publish' === $args['status'] ) ? 'publish' : 'draft';

		$post_content = '';
		if ( $attachment_id ) {
			$post_content .= wp_get_attachment_image( $attachment_id, 'full' );
		}
		$post_content .= wpautop( $body );
		$post_content .= '<p><em>Submitted by ' . esc_html( $author_name ) . '</em></p>';

		$post_id = wp_insert_post( array(
			'post_title'    => $title,
			'post_content'  => $post_content,
			'post_status'   => $status,
			'post_author'   => $author_id,
			'post_category' => $category ? array( $category ) : array(),
		), true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Mark this as a Partyline submission so the publish notifier can find it.
		update_post_meta( $post_id, '_partyline_submission', 1 );

		if ( $attachment_id ) {
			set_post_thumbnail( $post_id, $attachment_id );
			wp_update_post( array( 'ID' => $attachment_id, 'post_parent' => $post_id ) );
		}

		// Hold onto anonymous submitters' contact info for follow-up.
		$submitter_phone = '';
		if ( ! empty( $args['submitter'] ) && is_array( $args['submitter'] ) ) {
			update_post_meta( $post_id, '_partyline_submitter_name', sanitize_text_field( $args['submitter']['name'] ) );
			update_post_meta( $post_id, '_partyline_submitter_email', sanitize_email( $args['submitter']['email'] ) );
			if ( ! empty( $args['submitter']['phone'] ) ) {
				$submitter_phone = sanitize_text_field( $args['submitter']['phone'] );
				update_post_meta( $post_id, '_partyline_submitter_phone', $submitter_phone );
			}
		}

		Partyline_Utility::sendNotificationEmail( array(
			'post_id'       => $post_id,
			'from'          => $from,
			'author_name'   => $author_name,
			'title'         => $title,
			'description'   => $body,
			'original'      => isset( $args['original'] ) ? $args['original'] : '',
			'attachment_id' => $attachment_id,
			'phone'         => $submitter_phone,
		) );

		// Published immediately (editor/admin "post now")? Notify the Partyliner
		//  now, since transition_post_status fired before this meta existed.
		if ( 'publish' === $status ) {
			Partyline_Utility::notifyPartylinePublished( $post_id );
		}

		return $post_id;
	}

	/**
	 * Sideload an uploaded image into the media library.
	 *
	 * @param string $field The $_FILES field name.
	 * @return int|WP_Error attachment ID
	 */
	public static function handleImageUpload( $field ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$type = isset( $_FILES[ $field ]['type'] ) ? $_FILES[ $field ]['type'] : '';
		if ( 0 !== strpos( (string) $type, 'image/' ) ) {
			return new WP_Error( 'partyline_bad_image', 'Uploaded file is not an image.', array( 'status' => 400 ) );
		}

		$attachment_id = media_handle_upload( $field, 0, array(), array( 'test_form' => false ) );

		if ( is_wp_error( $attachment_id ) ) {
			return new WP_Error( 'partyline_upload_failed', $attachment_id->get_error_message(), array( 'status' => 400 ) );
		}
		return $attachment_id;
	}
}

endif;
