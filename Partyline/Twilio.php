<?php
namespace BroadstreetAds;
if (!defined('ABSPATH')) exit;
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

        if ( isset( $_GET['partyline_twilio_webhook'] ) && $_GET['partyline_twilio_webhook'] === $partyline_key ) {

            Partyline_Log::add('debug', 'Raw Twilio POST body: ' . file_get_contents('php://input'));

            $twilio = new Partyline_Twilio();
                        
            // Extract message content from Twilio's data.
            $twilio->body = isset($_POST['Body']) ? sanitize_text_field($_POST['Body']) : '';

            // Collect all media attachments from Twilio webhook
            $num_media = isset($_POST['NumMedia']) ? intval($_POST['NumMedia']) : 0;
            if ($num_media > 0) {
                for ($i = 0; $i < $num_media; $i++) {
                    $url_key = 'MediaUrl' . $i;
                    $type_key = 'MediaContentType' . $i;

                    $raw_url = isset($_POST[$url_key]) ? sanitize_text_field($_POST[$url_key]) : '';
                    $raw_type = isset($_POST[$type_key]) ? sanitize_text_field($_POST[$type_key]) : '';

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

            $twilio->from = isset($_POST['From']) ? sanitize_text_field($_POST['From']) : '';
            $twilio->to = isset($_POST['To']) ? sanitize_text_field($_POST['To']) : '';
        }
        
        Partyline_Log::add('debug', 'Parsed Twilio body: ' . print_r($twilio, true));

        return $twilio;
    }

    public function sendResponse($message) {
        // Send a response back to Twilio.
        header('Content-Type: application/xml');
        echo '<Response><Message>' . esc_xml((string)$message) . '</Message></Response>';
    }

    /**
     * The base webhook url for twilio
     */
    public static function getWebhookUrl()
    {
        return  esc_url(home_url('/'));
    }
}