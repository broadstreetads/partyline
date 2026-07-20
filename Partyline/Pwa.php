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
	const PWA_ASSET_VERSION = '12';

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

		$config = array(
			'restBase'     => esc_url_raw( rest_url( self::REST_NAMESPACE . '/' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'swUrl'        => esc_url_raw( self::appUrl( 'sw.js' ) ),
			'scope'        => '/' . self::APP_PATH . '/',
			'user'         => array( 'name' => $name ),
			'loggedIn'     => $logged_in,
			'anon'         => $anon,
			'turnstileKey' => $turnstile_key,
		);

		$css      = esc_url( self::assetUrl( 'app.css' ) ) . '?v=' . self::PWA_ASSET_VERSION;
		$js       = esc_url( self::assetUrl( 'app.js' ) ) . '?v=' . self::PWA_ASSET_VERSION;
		$icon     = esc_url( self::assetUrl( 'icons/icon-192.png' ) );
		$apple    = esc_url( self::assetUrl( 'icons/icon-180.png' ) );
		$manifest = esc_url( self::appUrl( 'manifest.webmanifest' ) );

		echo '<!doctype html><html lang="en"><head>';
		echo '<meta charset="utf-8">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">';
		echo '<title>Partyline</title>';
		echo '<link rel="manifest" href="' . $manifest . '">';
		echo '<meta name="theme-color" content="#c3e617">';
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
		echo '<header class="pl-header">';
		echo '<span class="pl-brand"><img src="' . $icon . '" alt=""> Partyline</span>';
		echo '<span class="pl-user">' . esc_html( $logged_in ? $name : 'Guest' ) . '</span>';
		echo '</header>';

		echo '<main class="pl-main">';

		// --- HOME ---
		echo '<section id="screen-home" class="pl-screen">';
		$hero = $logged_in
			? 'Snap a photo and talk it through — we\'ll write it up for the newsroom.'
			: 'Snap a photo and tell us what\'s happening in Red Bank. We\'ll take it from there.';
		echo '<div class="pl-hero"><h1>Send in a Partyline</h1><p>' . esc_html( $hero ) . '</p></div>';
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
			echo '<div id="pl-rec-status" class="pl-status">Tap to dictate — or type it below.</div>';
			echo '</div>';
		}
		if ( $anon ) {
			echo '<label class="pl-label" for="pl-name">Your name</label>';
			echo '<input id="pl-name" class="pl-input" type="text" autocomplete="name" placeholder="Jane Doe">';
			echo '<label class="pl-label" for="pl-email">Your email</label>';
			echo '<input id="pl-email" class="pl-input" type="email" autocomplete="email" placeholder="you@example.com">';
		}
		echo '<label class="pl-label" for="pl-title">Title</label>';
		echo '<input id="pl-title" class="pl-input" type="text" placeholder="Headline (optional)">';
		echo '<label class="pl-label" for="pl-body">Story</label>';
		echo '<textarea id="pl-body" class="pl-textarea" rows="6" placeholder="Tell us what happened…"></textarea>';

		// Step 3 — submit
		echo '<h2 class="pl-step"><span class="pl-stepnum">3</span> Submit</h2>';
		echo '<p id="pl-submit-hint" class="pl-hint">Add a photo and a story to submit.</p>';
		if ( $can_publish ) {
			echo '<label class="pl-check"><input type="checkbox" id="pl-immediate"> Post immediately <span class="pl-check-note">(publish now, skip the draft)</span></label>';
		}
		if ( $turnstile_key ) {
			echo '<div id="pl-turnstile" class="cf-turnstile pl-turnstile" data-sitekey="' . esc_attr( $turnstile_key ) . '" data-callback="plTurnstileCb" data-expired-callback="plTurnstileCb" data-error-callback="plTurnstileCb"></div>';
		}
		echo '<div class="pl-actions">';
		echo '<button id="pl-submit" class="pl-btn pl-btn--primary" type="button" disabled>Submit Partyline</button>';
		echo '<button id="pl-cancel" class="pl-btn pl-btn--ghost" type="button">Save &amp; close</button>';
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
			'description'      => 'Send a photo and a story to the redbankgreen newsroom.',
			'start_url'        => self::appUrl(),
			'scope'            => self::appUrl(),
			'display'          => 'standalone',
			'orientation'      => 'portrait',
			'background_color' => '#ffffff',
			'theme_color'      => '#c3e617',
			'icons'            => array(
				array(
					'src'     => self::assetUrl( 'icons/icon-192.png' ),
					'sizes'   => '192x192',
					'type'    => 'image/png',
					'purpose' => 'any maskable',
				),
				array(
					'src'     => self::assetUrl( 'icons/icon-512.png' ),
					'sizes'   => '512x512',
					'type'    => 'image/png',
					'purpose' => 'any maskable',
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
			self::assetUrl( 'icons/icon-192.png' ),
			self::assetUrl( 'icons/icon-512.png' ),
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
			. 'Rewrite the reader\'s submission (and photo, if provided) as a short community-news item. '
			. 'Respond ONLY with a JSON object of the form {"title": "...", "body": "..."}. '
			. 'The body must be a short, professional blurb of 2-4 sentences in neutral third person. '
			. 'The title must be a concise headline. Do not invent facts beyond the account and photo.';
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

			if ( '' === $sub_name || ! is_email( $sub_email ) ) {
				return new WP_Error( 'partyline_contact', 'Please provide your name and a valid email address.', array( 'status' => 400 ) );
			}
			if ( '' === $body || ! $has_image ) {
				return new WP_Error( 'partyline_incomplete', 'A photo and a story are both required.', array( 'status' => 400 ) );
			}
			$verify = self::verifyTurnstile( (string) $request->get_param( 'turnstile' ) );
			if ( is_wp_error( $verify ) ) {
				return $verify;
			}
			$submitter = array( 'name' => $sub_name, 'email' => $sub_email );
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

		$post_id = self::createPost( array(
			'title'         => '' !== $title ? $title : Partyline_Core::DEFAULT_TITLE,
			'body'          => $body,
			'attachment_id' => $attachment_id,
			'author_id'     => $author_id,
			'author_name'   => $author_name,
			'from'          => $from,
			'status'        => $publish ? 'publish' : 'draft',
			'submitter'     => $submitter,
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

		if ( $attachment_id ) {
			set_post_thumbnail( $post_id, $attachment_id );
			wp_update_post( array( 'ID' => $attachment_id, 'post_parent' => $post_id ) );
		}

		// Hold onto anonymous submitters' contact info for follow-up.
		if ( ! empty( $args['submitter'] ) && is_array( $args['submitter'] ) ) {
			update_post_meta( $post_id, '_partyline_submitter_name', sanitize_text_field( $args['submitter']['name'] ) );
			update_post_meta( $post_id, '_partyline_submitter_email', sanitize_email( $args['submitter']['email'] ) );
		}

		Partyline_Utility::sendNotificationEmail( $post_id, $from, $post_content, $title, $author_name );

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
