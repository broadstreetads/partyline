<?php
/**
 * This file contains a class for dealing with Twilio webhooks
 *
 * @author Broadstreet Ads <labs@broadstreetads.com>
 */

/**
 * The class represents a Twilio communication
 */
class Partyline_Twilio
{
    public $from;
    public $to;
    public $body;
    public $attachments = [];
    
    public static function fromPost() {
        $twilio = null;

        $settings = Partyline_Utility::getSettings();
        $partyline_key = $settings->partyline_key ?? '';

        // Twilio webhooks are authenticated by a shared secret (`partyline_key`) in the URL,
        // not by a WordPress nonce — Twilio's server cannot supply one.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $supplied_key = isset($_GET['partyline_twilio_webhook']) ? sanitize_text_field(wp_unslash($_GET['partyline_twilio_webhook'])) : '';

        if ( $supplied_key !== '' && hash_equals( (string) $partyline_key, $supplied_key ) ) {

            $twilio = new Partyline_Twilio();

            // Extract message content from Twilio's data.
            // Body — allow basic punctuation, strip tags
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $twilio->body = isset($_POST['Body'])
                ? sanitize_textarea_field( wp_unslash($_POST['Body']) )
                : '';

            // Phone numbers — plain text sanitizer is fine
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $twilio->from = isset($_POST['From'])
                ? sanitize_text_field( wp_unslash($_POST['From']) )
                : '';

            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $twilio->to = isset($_POST['To'])
                ? sanitize_text_field( wp_unslash($_POST['To']) )
                : '';

            // Collect all media attachments from Twilio webhook
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $num_media = isset($_POST['NumMedia']) ? intval($_POST['NumMedia']) : 0;
            if ($num_media > 0) {
                for ($i = 0; $i < $num_media; $i++) {
                    $url_key = 'MediaUrl' . $i;
                    $type_key = 'MediaContentType' . $i;

                    // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    $raw_url = isset($_POST[$url_key]) ? sanitize_text_field(wp_unslash($_POST[$url_key])) : '';
                    // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    $raw_type = isset($_POST[$type_key]) ? sanitize_text_field(wp_unslash($_POST[$type_key])) : '';

                    $media_url = esc_url_raw($raw_url);
                    $media_type = preg_replace('/[^a-zA-Z0-9.+\-\/]/', '', $raw_type);

                    if (!empty($media_url)) {
                        $twilio->attachments[] = (object) array(
                            'url' => $media_url,
                            'type' => $media_type
                        );
                    }
                }
            }
        }

        return $twilio;
    }



    public static function fromMock() {
        $twilio = new Partyline_Twilio();
        $twilio->from = '+1234567890';
        $twilio->to = '+1234567890';
        $twilio->body = 'Hello, world!';
        $twilio->attachments[] = (object) array(
            'url' => 'https://example.com/image.jpg',
            'type' => 'image/jpeg'
        );
        return $twilio;
    }

    public function sendResponse($message) {
        // Send a response back to Twilio.
        header('Content-Type: application/xml');
        $sanitized_message = htmlspecialchars($message, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo "<Response><Message>{$sanitized_message}</Message></Response>";
    }
}