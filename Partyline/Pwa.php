<?php
/**
 * Partyline PWA + REST submission channel.
 *
 * A second ingestion path alongside the Twilio SMS webhook: a logged-in
 * contributor uses the installable web app to capture a photo and dictate a
 * story, the audio is transcribed (Wispr Flow), AI drafts a title/body, and the
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

	const REST_NAMESPACE = 'partyline/v1';
	const WISPR_ENDPOINT = 'https://platform-api.wisprflow.ai/api/v1/dash/api';

	/**
	 * Register hooks. Bails immediately unless the PWA feature is enabled, so
	 * production behavior is unchanged until someone flips the setting on.
	 */
	public static function init() {
		if ( ! self::isEnabled() ) {
			return;
		}

		add_action( 'rest_api_init', array( __CLASS__, 'registerRoutes' ) );
	}

	/** Is the PWA feature turned on? */
	public static function isEnabled() {
		$settings = Partyline_Utility::getSettings();
		return ! empty( $settings->pwa_enabled );
	}

	/**
	 * Register REST routes. Every route requires a logged-in user (WordPress
	 * cookie auth + the standard `X-WP-Nonce` header the PWA will send).
	 */
	public static function registerRoutes() {
		$auth = array( __CLASS__, 'permissionLoggedIn' );

		register_rest_route( self::REST_NAMESPACE, '/transcribe', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'restTranscribe' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( self::REST_NAMESPACE, '/generate', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'restGenerate' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( self::REST_NAMESPACE, '/submit', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'restSubmit' ),
			'permission_callback' => $auth,
		) );
	}

	/** Permission callback: logged-in users only. */
	public static function permissionLoggedIn() {
		if ( is_user_logged_in() ) {
			return true;
		}
		return new WP_Error(
			'partyline_not_logged_in',
			'You must be logged in to submit a Partyline.',
			array( 'status' => 401 )
		);
	}

	/* --------------------------------------------------------------------- */
	/* POST /transcribe  — { audio: base64 16kHz WAV, language?: 'en' }        */
	/* --------------------------------------------------------------------- */
	public static function restTranscribe( WP_REST_Request $request ) {
		$settings = Partyline_Utility::getSettings();
		$key = isset( $settings->wispr_api_key ) ? trim( $settings->wispr_api_key ) : '';

		if ( empty( $key ) ) {
			return new WP_Error( 'partyline_no_wispr_key', 'Transcription is not configured.', array( 'status' => 500 ) );
		}

		$audio = $request->get_param( 'audio' );
		if ( empty( $audio ) || ! is_string( $audio ) ) {
			return new WP_Error( 'partyline_no_audio', 'No audio provided.', array( 'status' => 400 ) );
		}

		// Accept a data: URL too, not just raw base64.
		if ( false !== strpos( $audio, ',' ) && false !== strpos( substr( $audio, 0, 64 ), 'base64' ) ) {
			$audio = substr( $audio, strpos( $audio, ',' ) + 1 );
		}

		$language = $request->get_param( 'language' );
		$language = $language ? sanitize_text_field( $language ) : 'en';

		$properties = array(
			'language' => $language,
			'app_type' => 'other',
		);
		$dictionary = self::dictionary();
		if ( ! empty( $dictionary ) ) {
			$properties['dictionary'] = $dictionary;
		}

		$response = wp_remote_post( self::WISPR_ENDPOINT, array(
			'timeout' => 60,
			'headers' => array(
				'Authorization' => 'Bearer ' . $key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'audio'      => $audio,
				'properties' => $properties,
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'partyline_wispr_error', $response->get_error_message(), array( 'status' => 502 ) );
		}

		$code    = wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) $code || ! isset( $decoded['text'] ) ) {
			Partyline_Log::add( 'error', 'Wispr transcription failed (' . $code . '): ' . wp_remote_retrieve_body( $response ) );
			return new WP_Error( 'partyline_wispr_failed', 'Transcription failed.', array( 'status' => 502 ) );
		}

		return rest_ensure_response( array(
			'text'     => $decoded['text'],
			'language' => isset( $decoded['detected_language'] ) ? $decoded['detected_language'] : $language,
		) );
	}

	/**
	 * Custom transcription vocabulary (local place names, etc.), from the
	 * `wispr_dictionary` setting (comma/newline separated). Filterable.
	 */
	public static function dictionary() {
		$settings = Partyline_Utility::getSettings();
		$raw      = isset( $settings->wispr_dictionary ) ? (string) $settings->wispr_dictionary : '';
		$terms    = array_filter( array_map( 'trim', preg_split( '/[\n,]+/', $raw ) ) );
		return apply_filters( 'partyline_pwa_dictionary', array_values( $terms ) );
	}

	/* --------------------------------------------------------------------- */
	/* POST /generate  — { transcript } -> { title, body } via existing GPT   */
	/* --------------------------------------------------------------------- */
	public static function restGenerate( WP_REST_Request $request ) {
		$transcript = $request->get_param( 'transcript' );
		$transcript = is_string( $transcript ) ? trim( wp_strip_all_tags( $transcript ) ) : '';

		if ( '' === $transcript ) {
			return new WP_Error( 'partyline_no_transcript', 'No transcript provided.', array( 'status' => 400 ) );
		}

		return rest_ensure_response( array(
			'title' => Partyline_Utility::generateTitle( $transcript ),
			'body'  => Partyline_Utility::gptClean( $transcript ),
		) );
	}

	/* --------------------------------------------------------------------- */
	/* POST /submit  — multipart: title, body, optional image file            */
	/* --------------------------------------------------------------------- */
	public static function restSubmit( WP_REST_Request $request ) {
		$title = trim( (string) $request->get_param( 'title' ) );
		$body  = trim( (string) $request->get_param( 'body' ) );

		$has_image = ! empty( $_FILES['image'] ) && ! empty( $_FILES['image']['name'] );

		if ( '' === $title && '' === $body && ! $has_image ) {
			return new WP_Error( 'partyline_empty', 'Nothing to submit.', array( 'status' => 400 ) );
		}

		$title = sanitize_text_field( $title );
		$body  = wp_kses_post( $body );

		$user        = wp_get_current_user();
		$author_id   = $user->ID;
		$author_name = $user->display_name ? $user->display_name : $user->user_login;

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
			'from'          => $user->user_email,
		) );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return rest_ensure_response( array(
			'post_id'   => $post_id,
			'edit_link' => get_admin_url() . 'post.php?post=' . $post_id . '&action=edit',
			'message'   => 'Thanks! Your Partyline was submitted as a draft.',
		) );
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

		$post_content = '';
		if ( $attachment_id ) {
			$post_content .= wp_get_attachment_image( $attachment_id, 'full' );
		}
		$post_content .= wpautop( $body );
		$post_content .= '<p><em>Submitted by ' . esc_html( $author_name ) . '</em></p>';

		$post_id = wp_insert_post( array(
			'post_title'    => $title,
			'post_content'  => $post_content,
			'post_status'   => 'draft',
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
