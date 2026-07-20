<?php
/**
 * This file contains a class which provides the AJAX callback functions required
 *  for Partyline.
 *
 * @author Broadstreet Ads <labs@broadstreetads.com>
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Partyline_Ajax
{
    /**
     * Save settings posted from the admin settings page.
     *
     * The request body is JSON, but the WordPress nonce is supplied via the
     *  `_wpnonce` query parameter on the AJAX URL so `check_ajax_referer()` can
     *  validate it without us having to parse JSON first.
     */
    public static function saveSettings()
    {
        check_ajax_referer( 'partyline_save_settings', '_wpnonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.', 403 );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $raw = file_get_contents( 'php://input' );
        $incoming = json_decode( $raw, true );

        if ( ! is_array( $incoming ) ) {
            wp_send_json_error( 'Invalid settings payload.', 400 );
        }

        $clean = (object) array(
            // Contributor app (PWA) — the primary channel. Enabled by default.
            'pwa_enabled'          => array_key_exists( 'pwa_enabled', $incoming ) ? ! empty( $incoming['pwa_enabled'] ) : true,
            'pwa_allow_anonymous'  => ! empty( $incoming['pwa_allow_anonymous'] ),
            'turnstile_site_key'   => isset( $incoming['turnstile_site_key'] )   ? sanitize_text_field( $incoming['turnstile_site_key'] )   : '',
            'turnstile_secret_key' => isset( $incoming['turnstile_secret_key'] ) ? sanitize_text_field( $incoming['turnstile_secret_key'] ) : '',

            // AI (OpenAI / ChatGPT) — optional. Enables auto-formatting + Whisper dictation.
            'chatgpt_api_key'          => isset( $incoming['chatgpt_api_key'] )          ? sanitize_text_field( $incoming['chatgpt_api_key'] )          : '',
            'ai_prompt'                => isset( $incoming['ai_prompt'] )                ? sanitize_textarea_field( $incoming['ai_prompt'] )                : '',
            'transcription_dictionary' => isset( $incoming['transcription_dictionary'] ) ? sanitize_textarea_field( $incoming['transcription_dictionary'] ) : '',

            // Twilio (SMS) — optional, secondary. Back-compat: default on when creds exist.
            'twilio_enabled'       => array_key_exists( 'twilio_enabled', $incoming ) ? ! empty( $incoming['twilio_enabled'] ) : ! empty( $incoming['twilio_account_sid'] ),
            'partyline_key'        => isset( $incoming['partyline_key'] )      ? sanitize_text_field( $incoming['partyline_key'] )      : '',
            'twilio_account_sid'   => isset( $incoming['twilio_account_sid'] ) ? sanitize_text_field( $incoming['twilio_account_sid'] ) : '',
            'twilio_auth_token'    => isset( $incoming['twilio_auth_token'] )  ? sanitize_text_field( $incoming['twilio_auth_token'] )  : '',

            // General.
            'partyline_category'   => isset( $incoming['partyline_category'] )  ? absint( $incoming['partyline_category'] )                    : 0,
            'email_notifications'  => isset( $incoming['email_notifications'] ) ? sanitize_textarea_field( $incoming['email_notifications'] ) : '',
        );

        Partyline_Utility::setOption( Partyline_Core::KEY_SETTINGS, $clean );

        wp_send_json_success( array( 'success' => true ) );
    }
}
