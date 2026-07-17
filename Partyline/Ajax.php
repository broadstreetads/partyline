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
            'partyline_key'        => isset( $incoming['partyline_key'] )       ? sanitize_text_field( $incoming['partyline_key'] )       : '',
            'twilio_account_sid'   => isset( $incoming['twilio_account_sid'] )  ? sanitize_text_field( $incoming['twilio_account_sid'] )  : '',
            'twilio_auth_token'    => isset( $incoming['twilio_auth_token'] )   ? sanitize_text_field( $incoming['twilio_auth_token'] )   : '',
            'partyline_category'   => isset( $incoming['partyline_category'] )  ? absint( $incoming['partyline_category'] )               : 0,
            'chatgpt_api_key'      => isset( $incoming['chatgpt_api_key'] )     ? sanitize_text_field( $incoming['chatgpt_api_key'] )     : '',
            'chatgpt_prompt'       => isset( $incoming['chatgpt_prompt'] )      ? sanitize_textarea_field( $incoming['chatgpt_prompt'] )  : '',
            'email_notifications'  => isset( $incoming['email_notifications'] ) ? sanitize_textarea_field( $incoming['email_notifications'] ) : '',
        );

        Partyline_Utility::setOption( Partyline_Core::KEY_SETTINGS, $clean );

        wp_send_json_success( array( 'success' => true ) );
    }
}
