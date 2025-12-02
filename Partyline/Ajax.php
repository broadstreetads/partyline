<?php
namespace BroadstreetAds;
if (!defined('ABSPATH')) exit;
/**
 * This file contains a class which provides the AJAX callback functions required
 *  for Broadstreet.
 *
 * @author Broadstreet Ads <labs@broadstreetads.com>
 */

/**
 * A class containing functions for the AJAX functionality in Broadstreet. These
 *  aren't executed directly by any Broadstreet code -- they are registered with
 *  the Wordpress hooks in Partyline_Core::_registerHooks(), and called as needed
 *  by the front-end and Wordpress. All of these methods output JSON.
 */
class Partyline_Ajax
{
    /**
     *
     */
    public static function saveSettings()
    {
        // Restrict to logged-in admins only
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $raw_input = file_get_contents("php://input");

        if (strlen($raw_input) > 200000) // limit body size to 200kb to prevent abuse
        { 
            wp_send_json_error(array('message' => 'Request body too large.'), 400);
        }
        else
        {
            $json = json_decode($raw_input, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($json)) // make sure json_decode doesn't throw an error
            {
                wp_send_json_error(array('message' => 'Invalid JSON.'), 400);
            }
            else {

                $nonce = $json['nonce'] ?? '';

                // Verify nonce manually
                if (!wp_verify_nonce($nonce, 'partyline_save_settings'))
                {
                    wp_send_json_error('Invalid nonce');
                }

                // Explicitly retrieve and sanitize settings
                $settings = array();
                if (isset($json['partyline_key'])) $settings['partyline_key'] = sanitize_text_field($json['partyline_key']);
                if (isset($json['email_notifications'])) $settings['email_notifications'] = sanitize_text_field($json['email_notifications']);
                if (isset($json['twilio_account_sid'])) $settings['twilio_account_sid'] = sanitize_text_field($json['twilio_account_sid']);
                if (isset($json['twilio_auth_token'])) $settings['twilio_auth_token'] = sanitize_text_field($json['twilio_auth_token']);
                if (isset($json['partyline_category'])) $settings['partyline_category'] = sanitize_text_field($json['partyline_category']);
                if (isset($json['chatgpt_api_key'])) $settings['chatgpt_api_key'] = sanitize_text_field($json['chatgpt_api_key']);
                if (isset($json['chatgpt_prompt'])) $settings['chatgpt_prompt'] = sanitize_text_field($json['chatgpt_prompt']);

                // Save sanitized settings
                Partyline_Utility::setOption(Partyline_Core::KEY_SETTINGS, $settings);

                // Done
                $response = array();
                $response['nonce'] = wp_create_nonce('partyline_save_settings'); // Generate new nonce for next request
                wp_send_json_success($response);
            }
        }

    }

    public static function getSettings()
    {
        Partyline_Log::add('debug', "Admin settings Partyline_Ajax::getSettings() callback executed");

        // Restrict to logged-in admins only
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $data = array();
        $data['ok']                 = '1'; // This flag just helps us know that json was parsed correctly and that the server is returning good data.
        $data['nonce']              = wp_create_nonce('partyline_save_settings'); // Generate new nonce for next request

        // Settings
        $data['api_key']            = Partyline_Utility::getOption(Partyline_Core::KEY_API_KEY);
        $data['network_id']         = Partyline_Utility::getOption(Partyline_Core::KEY_NETWORK_ID);
        $data['settings']           = Partyline_Utility::getSettings();
        $data['key_valid']          = false;
        $data['categories']         = get_categories(array('hide_empty' => false));
        $data['tags']               = get_tags(array('hide_empty' => false));
        $data['settings']           = Partyline_Utility::getSettings();

        if(!$data['api_key'])
        {
            //$data['errors'][] = '<strong>You dont have an API key set yet!</strong><ol><li>If you already have a Broadstreet account, <a href="http://my.broadstreetads.com/access-token">get your key here</a>.</li><li>If you don\'t have an account with us, <a target="blank" id="one-click-signup" href="#">then use our one-click signup</a>.</li></ol>';
        }
        else
        {
            //$api = $this->getBroadstreetClient();    
        }

        wp_send_json_success($data);
    }
}