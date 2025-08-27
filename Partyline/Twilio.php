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

        if ( isset( $_GET['partyline_twilio_webhook'] ) && $_GET['partyline_twilio_webhook'] == '1' ) {            

            Partyline_Log::add('debug', 'Raw Twilio POST body: ' . file_get_contents('php://input'));

            $twilio = new Partyline_Twilio();
                        
            // Extract message content from Twilio's data.
            $twilio->body = isset($_POST['Body']) ? $_POST['Body'] : '';

            // Collect all media attachments from Twilio webhook
            $num_media = isset($_POST['NumMedia']) ? intval($_POST['NumMedia']) : 0;
            if ($num_media > 0) {
                for ($i = 0; $i < $num_media; $i++) {
                    $url_key = 'MediaUrl' . $i;
                    $type_key = 'MediaContentType' . $i;

                    $raw_url = isset($_POST[$url_key]) ? $_POST[$url_key] : '';
                    $raw_type = isset($_POST[$type_key]) ? $_POST[$type_key] : '';

                    $media_url = filter_var($raw_url, FILTER_SANITIZE_URL);
                    $media_type = is_string($raw_type) ? preg_replace('/[^a-zA-Z0-9.+\-\/]/', '', $raw_type) : '';

                    if (!empty($media_url)) {
                        $twilio->attachments[] = (object) array(
                            'url' => $media_url,
                            'type' => $media_type
                        );
                    }
                }
            }

            $twilio->from = $_POST['From'];
            $twilio->to = $_POST['To'];
        }

        Partyline_Log::add('debug', 'Parsed Twilio body: ' . print_r($twilio, true));

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