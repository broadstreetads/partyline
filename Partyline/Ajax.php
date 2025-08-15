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
}