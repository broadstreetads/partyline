<?php
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
        $settings = json_decode(file_get_contents("php://input"));

        if($settings)
        {
            Partyline_Utility::setOption(Partyline_Core::KEY_SETTINGS, $settings);
            $success = true;
        }
        else
        {
            $success = false;
        }

        die(json_encode(array('success' => true)));
    }

    public static function getSettings()
    {
        Partyline_Log::add('debug', "Admin settings Partyline_Ajax::getSettings() callback executed");

        // Restrict to logged-in admins only
        if (!current_user_can('manage_options') ) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $data = array();

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