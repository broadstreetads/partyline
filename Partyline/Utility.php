<?php
/**
 * This file contains a class for utility methods and/or wrappers for built-in
 *  Wordpress API calls
 *
 * @author Broadstreet Ads <labs@broadstreetads.com>
 */

/**
 * The class contains a number of utility methods that may be needed by various
 *  parts of Broadstreet
 */
class Partyline_Utility
{
    const KEY_NET_INFO          = 'PARTYLINE_NET_INFO';

    protected static $_apiKeyValid = NULL;

    protected static $_settingsCache = NULL;

    /**
     * Get the current user's Broadstreet API key
     * @return boolean
     */
    public static function getApiKey()
    {
        $api_key = Partyline_Utility::getOption(Partyline_Core::KEY_API_KEY);

        if(!$api_key)
            return FALSE;
        else
            return $api_key;
    }

    public static function getBroadstreetClient()
    {
        $settings = Partyline_Utility::getSettings();
        $host = 'api.broadstreetads.com';
        $secure = true;

        if (property_exists($settings, 'use_local_bsa') && $settings->use_local_bsa) {
            $host = 'localhost:3000';
            $secure = false;
        }

        $key = Partyline_Utility::getOption(Partyline_Core::KEY_API_KEY);
        return new Broadstreet($key, $host, $secure);
    }
    /**
     * Get this publication's network ID
     * @return boolean
     */
    public static function getNetworkId()
    {
        return Partyline_Utility::getOption(Partyline_Core::KEY_NETWORK_ID);
    }

    /**
     * Get info about the network this blog is registered as, and cache it
     * @return boolean
     */
    public static function getNetwork($force_refresh = false)
    {
        $info = false;

        if(!$force_refresh)
            $info = Partyline_Cache::get('network_info');

        if($info) return $info;

        try
        {
            $network_id = self::getNetworkId();

            if (!$network_id) {
                return false;
            }

            $broadstreet = self::getBroadstreetClient();
            $info = $broadstreet->getNetwork($network_id);

            Partyline_Cache::set('network_info', $info, Partyline_Config::get('network_cache_ttl_seconds'));

        }
        catch(Exception $ex)
        {
            return false;
        }

        return $info;
    }

    /**
     * Check that the user's API key exists and is valid
     * @return boolean
     */
    public static function checkApiKey($return_key = FALSE)
    {
        if(self::$_apiKeyValid !== NULL)
            return self::$_apiKeyValid;

        $api_key = self::getApiKey();

        if(!$api_key)
        {
            self::$_apiKeyValid = FALSE;
            return FALSE;
        }
        else
        {
            $api = self::getBroadstreetClient();

            try
            {
                $api->getNetworks();
                self::$_apiKeyValid = TRUE;

                if($return_key)
                    return $api_key;
                else
                    return TRUE;
            }
            catch(Exception $ex)
            {
                self::$_apiKeyValid = TRUE;
                return FALSE;
            }
        }
    }

    /**
     * Sets a Wordpress option
     * @param string $name The name of the option to set
     * @param string $value The value of the option to set
     */
    public static function setOption($name, $value)
    {
        if (get_option($name) !== FALSE)
        {
            update_option($name, $value);
        }
        else
        {
            $deprecated = ' ';
            $autoload   = 'no';
            add_option($name, $value);
        }
    }

    /**
     * Gets a Wordpress option
     * @param string    $name The name of the option
     * @param mixed     $default The default value to return if one doesn't exist
     * @return string   The value if the option does exist
     */
    public static function getOption($name, $default = FALSE)
    {
        $value = get_option($name);
        if( $value !== FALSE ) return $value;
        return $default;
    }


    public static function getSettings()
    {
        if (self::$_settingsCache === NULL) {
            self::$_settingsCache = Partyline_Utility::getOption(Partyline_Core::KEY_SETTINGS, (object)array());
        }

        return self::$_settingsCache;
    }

    /**
     * Fix a malformed URL
     * @param string $url
     * @return string
     */
    public static function fixURL($url)
    {
        if(!strstr($url, '://'))
            $url = "http://$url";

        return $url;
    }

    /**
     * Sets a Wordpress meta value
     * @param string $name The name of the field to set
     * @param string $value The value of the field to set
     */
    public static function setPostMeta($post_id, $name, $value)
    {
        if (get_post_meta($post_id, $name, true) !== FALSE)
        {
            update_post_meta($post_id, $name, $value);
        }
        else
        {
            add_post_meta($post_id, $name, $value);
        }
    }

    /**
     * Gets a post meta value
     * @param string    $name The name of the field
     * @param mixed     $default The default value to return if one doesn't exist
     * @return string   The value if the field does exist
     */
    public static function getPostMeta($post_id, $name, $default = FALSE)
    {
        $value = get_post_meta($post_id, $name, true);
        if( $value !== FALSE ) return maybe_unserialize($value);
        return $default;
    }

    /**
     * Gets post meta values, cleaned up, singlefied (or not)
     * @param int       $post_id The id of the post
     * $param array     $defaults Assoc array of meta key names with value defaults
     * @param bool      $singles Whether to collapse value field to first value
     *  (default true)
     */
    public static function getAllPostMeta($post_id, $defaults = array(), $singles = true)
    {
        $meta = get_post_meta($post_id);

        foreach($defaults as $key => $value)
        {
            if(!isset($meta[$key])) {
                $meta[$key] = $value;
            }
        }

        if(!$singles) return $meta;

        $new_meta = array();

        # Meta fields come back nested in an array, fix that
        # unless the option is intended to be an array,
        # given the defaults
        foreach($meta as $key => $value)
        {
            if(is_array(@$defaults[$key]) && count($value))
                $new_meta[$key] = maybe_unserialize($value[0]);
            else
                $new_meta[$key] = (is_array($value) && count($value)) ? $value[0] : $value;
        }

        return $new_meta;
    }

    public static function toTime($time)
    {
        return date("g:i a", strtotime($time));
    }

    /**
     * Get a value from an associative array. The specified key may or may
     *  not exist.
     * @param array $array Array to grab the value from
     * @param mixed $key The key to check the array
     * @param mixed $default A value to return if the key doesn't exist int he array (default is FALSE)
     * @return mixed The value if the key exists, and the default if it doesn't
     */
    public static function arrayGet($array, $key, $default = FALSE)
    {
        if(array_key_exists($key, $array))
            return $array[$key];
        else
            return $default;
    }

    /**
     * Get the site's base URL
     * @return string
     */
    public static function getSiteBaseURL()
    {
        return get_bloginfo('url');
    }

    /**
     * Get the base URL of the plugin installation
     * @return string the base URL
     */
    public static function getPartylineBaseURL()
    {
        # handle https
        $url = plugins_url( '/Partyline/', dirname(__FILE__) );
        return $url;
    }

    /**
     * Get the base URL for plugin images
     * @return string
     */
    public static function getImageBaseURL()
    {
        return self::getPartylineBaseURL() . 'Public/img/';
    }

    /**
     * Get the base URL for plugin CSS
     * @return string
     */
    public static function getCSSBaseURL()
    {
        return self::getPartylineBaseURL() . 'Public/css/';
    }

    /**
     * Get the base URL for plugin javascript
     * @return string
     */
    public static function getJSBaseURL()
    {
        return self::getPartylineBaseURL() . 'Public/js/';
    }

    /**
     * Get the base URL for plugin javascript
     * @return string
     */
    public static function getVendorBaseURL()
    {
        return self::getPartylineBaseURL() . 'Public/vendor/';
    }

    /**
     * Parse content from Twilio message
     * @param string $post_content The content to parse
     * @return array Array with title, body, and immediate flag
     */
    public static function parseContent($post_content)
    {
        Partyline_Log::add('debug', "Parsing content: " . $post_content);

        $components = array('title' => 'Post title', 'body' => 'Post Body', 'immediate' => false);
        $post_content = trim($post_content);
        $post_content = preg_split('/\n+/', $post_content);
        $post_content = array_map('trim', $post_content);
        $post_content = array_filter($post_content);

        // should it get posted right now?
        if (preg_match('/^now/i', $post_content[0])) {
            $components['immediate'] = true;
            array_shift($post_content);
        }

        if (count($post_content) > 1) {
            $title = $post_content[0];
            $body = implode("\n\n", array_slice($post_content, 1));
            if ($components['immediate']) {
                $components['title'] = strtoupper($post_content[0]);
                $components['body'] = $body;
            } else {
                $components['title'] = self::gptClean($post_content[0]);
                $components['body'] = self::gptClean($body) . "\n\n--Original before GPT--\n\n$body";
            }
        } else {
            $body = trim($post_content[0]);
            $components['title'] = self::generateTitle($body);
            $components['body'] = self::gptClean($body) . ($components['immediate'] ? '' : "\n\n--Original before GPT--\n\n$body");
        }

        return $components;
    }

    /**
     * Clean content using GPT for spelling and grammar
     * @param string $original_body The content to clean
     * @param string $default Default value if GPT fails
     * @return string Cleaned content
     */
    public static function gptClean($original_body, $default = null)
    {
        if ($default === null) {
            $default = $original_body;
        }

        $settings = self::getSettings();
        if (empty($settings->chatgpt_api_key)) {
            return $original_body;
        }

        $prompt = "Correct the following for JUST spelling and grammar. Do not add anything.";
        return self::gptCall($prompt, $original_body, $default);
    }

    /**
     * Generate title using GPT
     * @param string $original_body The content to generate title from
     * @param string $default Default value if GPT fails
     * @return string Generated title
     */
    public static function generateTitle($original_body, $default = null)
    {
        if ($default === null) {
            $default = Partyline_Core::DEFAULT_TITLE;
        }

        $settings = self::getSettings();
        if (empty($settings->chatgpt_api_key)) {
            $words = str_word_count($original_body, 1);
            return implode(' ', array_slice($words, 0, 5));
        }

        $prompt = isset($settings->chatgpt_prompt) ? $settings->chatgpt_prompt : 'Generate a short, catchy title for this content:';
        return self::gptCall($prompt, $original_body, $default);
    }

    /**
     * Make GPT API call
     * @param string $prompt The prompt to send to GPT
     * @param string $original_body The content to process
     * @param string $default Default value if API call fails
     * @return string GPT response or default
     */
    public static function gptCall($prompt, $original_body, $default = null)
    {
        if ($default === null) {
            $default = Partyline_Core::DEFAULT_TITLE;
        }

        $endpoint = 'https://api.openai.com/v1/chat/completions';
        $settings = self::getSettings();
        $api_key = isset($settings->chatgpt_api_key) ? $settings->chatgpt_api_key : '';

        Partyline_Log::add('debug', "Making GPT call with prompt: " . $prompt);

        if (empty($api_key)) {
            Partyline_Log::add('error', "ChatGPT API key not configured");
            return $default;
        }

        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => "Bearer $api_key",
                'Content-Type'  => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => 'gpt-4o-mini',
                'max_tokens' => strlen($original_body) + 100,
                'messages' => array (
                    array('role' => 'system', 'content' => $prompt),
                    array('role' => 'user', 'content' => $original_body)
                )
            ))
        ));

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
            $body = wp_remote_retrieve_body($response);
            Partyline_Log::add('debug', "GPT response: " . $body);
            $decoded_response = json_decode($body, true);
            return trim(trim($decoded_response['choices'][0]['message']['content']), "\"");
        } else {
            Partyline_Log::add('error', "GPT API call failed: " . print_r($response, true));
        }

        return $default;
    }

    /**
     * Send notification email for new posts
     * @param int $post_id The post ID
     * @param string $from The sender phone number
     * @param string $post_content The post content
     * @param string $title The post title
     */
    public static function sendNotificationEmail($post_id, $from, $post_content, $title, $author_name)
    {
        $settings = self::getSettings();
        $email_notifications = isset($settings->email_notifications) ? $settings->email_notifications : '';
        
        if (empty($email_notifications)) {
            return;
        }

        $emails = array_filter(array_map('trim', explode("\n", $email_notifications)));
        
        if (empty($emails)) {
            return;
        }

        $edit_link = get_admin_url() . "post.php?post=$post_id&action=edit";
        $notification = "A Partyline post has been sent in from $author_name ($from): {$edit_link}\n\n\n{$post_content}\n\n{$title}";
        
        wp_mail($emails, 'New Partyline submission', $notification, array('Content-Type: text/html; charset=UTF-8'));
    }
}