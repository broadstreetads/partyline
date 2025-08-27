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
        return gmdate("g:i a", strtotime($time));
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
            Partyline_Log::add('error', "GPT API call failed: " . wp_json_encode($response));
        }

        return $default;
    }

    /**
     * Get notification emails in the Partyline settings
     */
    public static function getNotificationEmails()
    {
        $settings = self::getSettings();
        $email_notifications = isset($settings->email_notifications) ? $settings->email_notifications : '';
        
        if (empty($email_notifications)) {
            return [];
        }

        return array_filter(array_map('trim', explode("\n", $email_notifications)));
    }

    public static function sendErrorEmail($message)
    {
        $emails = self::getNotificationEmails();

        if (empty($emails)) {
            return;
        }

        $notification = "There has been an error in Partyline:\n\n\n$message";
        
        wp_mail($emails, 'Partyline Error', $notification, array('Content-Type: text/html; charset=UTF-8'));
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
        $emails = self::getNotificationEmails();

        if (empty($emails)) {
            return;
        }

        $edit_link = get_admin_url() . "post.php?post=$post_id&action=edit";
        $notification = "A Partyline post has been sent in from $author_name ($from): {$edit_link}\n\n\n{$post_content}\n\n{$title}";
        
        wp_mail($emails, 'New Partyline submission', $notification, array('Content-Type: text/html; charset=UTF-8'));
    }

    /**
     * Download a Twilio-hosted media item and add it to the Media Library.
     * Handles accounts with/without "Enforce HTTP Auth on Media URLs".
     *
     * @param string $image_url   Twilio MediaUrlN (e.g., .../Messages/MM.../Media/ME...)
     * @param string $media_type  MIME type from Twilio (e.g., image/jpeg). Optional but helpful.
     * @return int|WP_Error       Attachment ID on success; WP_Error on failure
     */
    public static function sideloadAuthenticatedImage( $image_url, $media_type = '' ) {
        Partyline_Log::add('debug', "Starting sideloadAuthenticatedImage for URL: $image_url with type: $media_type");

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Twilio creds
        $settings     = self::getSettings();
        $account_sid  = $settings->twilio_account_sid ?? '';
        $auth_token   = $settings->twilio_auth_token ?? '';

        if ( empty($account_sid) || empty($auth_token) ) {
            $msg = 'Twilio credentials are missing.';
            Partyline_Log::add('error', $msg);
            return new WP_Error('twilio_creds_missing', $msg);
        }

        Partyline_Log::add('debug', "Using Twilio account SID: $account_sid");

        // --- Step 1: Request Twilio media URL with auth, but DO NOT follow redirects ---
        $args1 = [
            'headers'     => [ 'Authorization' => 'Basic ' . base64_encode($account_sid . ':' . $auth_token) ],
            'timeout'     => 30,
            'redirection' => 0, // critical: don’t forward Authorization to a different host
        ];

        Partyline_Log::add('debug', "Requesting Twilio media (no redirect follow): $image_url");
        $resp1 = wp_remote_get( $image_url, $args1 );

        if ( is_wp_error($resp1) ) {
            $msg = 'Error requesting Twilio media: ' . $resp1->get_error_message();
            Partyline_Log::add('error', $msg);
            return new WP_Error('twilio_media_request_failed', $msg);
        }

        $code1 = wp_remote_retrieve_response_code($resp1);
        Partyline_Log::add('debug', "First-hop response code: $code1");

        $file_body   = '';
        $contentType = '';

        if ( $code1 >= 300 && $code1 < 400 ) {
            // Redirect expected — get the signed CDN URL.
            $location = wp_remote_retrieve_header($resp1, 'location');
            if ( empty($location) ) {
                $msg = 'Twilio returned a redirect without a Location header.';
                Partyline_Log::add('error', $msg);
                return new WP_Error('twilio_missing_location', $msg);
            }
            Partyline_Log::add('debug', "Following redirect to: $location");

            // --- Step 2: Fetch the redirected URL WITHOUT auth; allow further redirects ---
            $args2 = [
                'timeout'     => 30,
                'redirection' => 5,
                // No Authorization header here on purpose.
            ];
            $resp2 = wp_remote_get( $location, $args2 );

            if ( is_wp_error($resp2) ) {
                $msg = 'Error fetching redirected media: ' . $resp2->get_error_message();
                Partyline_Log::add('error', $msg);
                return new WP_Error('twilio_media_fetch_failed', $msg);
            }

            $code2 = wp_remote_retrieve_response_code($resp2);
            Partyline_Log::add('debug', "Second-hop response code: $code2");
            if ( $code2 < 200 || $code2 >= 300 ) {
                $msg = 'Unexpected response code fetching media: ' . $code2;
                Partyline_Log::add('error', $msg);
                return new WP_Error('twilio_media_bad_status', $msg);
            }

            $file_body   = wp_remote_retrieve_body($resp2);
            $contentType = wp_remote_retrieve_header($resp2, 'content-type');

        } elseif ( $code1 >= 200 && $code1 < 300 ) {
            // Rare but possible: body returned directly from Twilio
            $file_body   = wp_remote_retrieve_body($resp1);
            $contentType = wp_remote_retrieve_header($resp1, 'content-type');
        } elseif ( $code1 === 401 || $code1 === 403 ) {
            // Auth failed — surface a clear error
            $msg = "Unauthorized fetching Twilio media (HTTP $code1). Check SID/Auth Token.";
            Partyline_Log::add('error', $msg);
            return new WP_Error('twilio_media_unauthorized', $msg);
        } else {
            $msg = "Unexpected first-hop status code from Twilio: $code1";
            Partyline_Log::add('error', $msg);
            return new WP_Error('twilio_media_unexpected_status', $msg);
        }

        if ( empty($file_body) ) {
            $msg = 'Downloaded media body is empty.';
            Partyline_Log::add('error', $msg);
            return new WP_Error('twilio_media_empty', $msg);
        }

        // --- Determine final MIME type & extension ---
        if ( empty($contentType) && !empty($media_type) ) {
            $contentType = $media_type; // fallback to provided type
        }
        $contentType = is_string($contentType) ? trim(explode(';', $contentType)[0]) : '';

        $ext_map = [
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            'image/heic' => 'heic',
            'video/mp4'  => 'mp4',
            'audio/mpeg' => 'mp3',
        ];
        $ext = $ext_map[$contentType] ?? '';

        // Use the last path segment of the Media URL as a base; append extension.
        $base = sanitize_file_name( basename( wp_parse_url($image_url, PHP_URL_PATH) ?: 'twilio_media' ) );
        if ( $ext && !preg_match('/\.' . preg_quote($ext, '/') . '$/i', $base) ) {
            $filename = $base . '.' . $ext;
        } else {
            $filename = $base; // last resort
        }

        Partyline_Log::add('debug', "Resolved filename: $filename (MIME: $contentType)");

        // --- Write to a temp file ---
        $temp_file = tempnam( sys_get_temp_dir(), 'wp_twilio_media_' );
        if ( $temp_file === false ) {
            $msg = 'Failed to create temporary file.';
            Partyline_Log::add('error', $msg);
            return new WP_Error('tempfile_create_failed', $msg);
        }

        $bytes = file_put_contents( $temp_file, $file_body );
        Partyline_Log::add('debug', "Wrote $bytes bytes to temporary file: $temp_file");

        if ( $bytes === false || $bytes === 0 ) {
            @wp_delete_file($temp_file);
            $msg = 'Failed writing media to temporary file.';
            Partyline_Log::add('error', $msg);
            return new WP_Error('tempfile_write_failed', $msg);
        }

        // --- Sideload into Media Library ---
        $file_array = [
            'name'     => $filename,
            'tmp_name' => $temp_file,
        ];

        $overrides = [
            'test_form' => false,
            'type'      => $contentType ?: null, // let WP sniff if unknown
        ];

        Partyline_Log::add('debug', "Attempting to sideload file: $filename");
        $sideload = wp_handle_sideload( $file_array, $overrides );

        if ( is_wp_error($sideload) ) {
            @wp_delete_file($temp_file);
            $msg = 'Error sideloading file: ' . $sideload->get_error_message();
            Partyline_Log::add('error', $msg);
            return new WP_Error('sideload_failed', $msg);
        }

        $file_path = $sideload['file'] ?? '';
        $file_url  = $sideload['url']  ?? '';
        $file_type = $sideload['type'] ?? $contentType;

        Partyline_Log::add('debug', 'Sideloaded file path: ' . $file_path);
        Partyline_Log::add('debug', 'Sideloaded file URL: ' . $file_url);
        Partyline_Log::add('debug', 'Sideloaded file type: ' . $file_type);

        // Insert attachment
        $attach = [
            'post_title'     => sanitize_file_name( $base ),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_mime_type' => $file_type,
        ];

        Partyline_Log::add('debug', "Inserting attachment into media library");
        $attachment_id = wp_insert_attachment( $attach, $file_path, 0 );

        if ( is_wp_error($attachment_id) ) {
            $msg = 'Failed to insert attachment: ' . $attachment_id->get_error_message();
            Partyline_Log::add('error', $msg);
            return new WP_Error('attachment_insert_failed', $msg);
        }

        // Generate metadata / thumbnails
        self::regenerateImageThumbnails( $attachment_id );

        // Cleanup temp file
        @wp_delete_file( $temp_file );
        Partyline_Log::add('debug', "Cleaned up temporary file: $temp_file");

        Partyline_Log::add('debug', "sideloadAuthenticatedImage completed, returning attachment ID: $attachment_id");
        return (int) $attachment_id;
    }


    public static function regenerateImageThumbnails( $attachment_id ) {
		// Ensure the image.php file is loaded.
		require_once(ABSPATH . 'wp-admin/includes/image.php');
		
		// Get the path to the original file.
		$filepath = get_attached_file( $attachment_id );
		
		if ( !$filepath ) {
			return new WP_Error( 'regenerate_error', 'File path not found for attachment ID: ' . $attachment_id );
		}
    
		// Generate the new metadata, which also creates the image files.
		$attach_data = wp_generate_attachment_metadata( $attachment_id, $filepath );
		
		// Update the database with the new metadata.
		if ($attach_data) {
			wp_update_attachment_metadata( $attachment_id, $attach_data );
			return true;
		} else {
			return new WP_Error( 'regenerate_error', 'Failed to generate new attachment metadata.' );
		}
	}
}