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
    protected static $_settingsCache = NULL;

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

        $prompt = self::aiPrompt() . "\n\nWrite a short, catchy headline for the following. Reply with ONLY the headline, nothing else.";
        return self::gptCall($prompt, $original_body, $default);
    }

    /**
     * The single shared editorial voice/prompt (settings `ai_prompt`), used by
     * both the contributor app (story rewrite) and the SMS path (title). Each
     * caller appends its own task. Falls back to a neutral community-news voice.
     */
    public static function aiPrompt()
    {
        $settings = self::getSettings();
        $prompt = isset($settings->ai_prompt) ? trim($settings->ai_prompt) : '';
        return $prompt !== '' ? $prompt : self::defaultAiPrompt();
    }

    /**
     * The built-in default AI writing prompt. Single source of truth — also
     * passed to the settings page so it can pre-fill the (editable) field.
     */
    public static function defaultAiPrompt()
    {
        return 'You are an editor for a community news publication. A reader has sent in a short news item. Clean it up into publishable copy: correct spelling, grammar, and punctuation, and format it clearly. Stay as close as possible to the reader\'s original wording, changing only what is needed for grammatical correctness and readability. Do not add, embellish, or invent any facts, names, quotes, or details beyond what was provided. Keep it concise, neutral, and factual.';
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
    public static function sendNotificationEmail($args)
    {
        // Back-compat with the old positional signature
        //  ($post_id, $from, $post_content, $title, $author_name).
        if (! is_array($args)) {
            $a = func_get_args();
            $args = array(
                'post_id'     => isset($a[0]) ? $a[0] : 0,
                'from'        => isset($a[1]) ? $a[1] : '',
                'description' => isset($a[2]) ? $a[2] : '',
                'title'       => isset($a[3]) ? $a[3] : '',
                'author_name' => isset($a[4]) ? $a[4] : '',
            );
        }

        $emails = self::getNotificationEmails();
        if (empty($emails)) {
            return;
        }

        $title   = (isset($args['title']) && $args['title'] !== '') ? $args['title'] : 'New Partyline';
        $subject = 'New Partyline: ' . wp_strip_all_tags($title);

        wp_mail($emails, $subject, self::renderNotificationEmail($args), array('Content-Type: text/html; charset=UTF-8'));
    }

    /**
     * Build the HTML for the "new Partyline" notification email.
     *
     * @param array $args post_id, from, author_name, title, description,
     *                    original, attachment_id
     * @return string HTML
     */
    public static function renderNotificationEmail($args)
    {
        $post_id     = isset($args['post_id']) ? (int) $args['post_id'] : 0;
        $from        = isset($args['from']) ? trim((string) $args['from']) : '';
        $author      = (isset($args['author_name']) && $args['author_name'] !== '') ? $args['author_name'] : 'Anonymous Partyliner';
        $title       = (isset($args['title']) && $args['title'] !== '') ? $args['title'] : 'New Partyline';
        $description = isset($args['description']) ? (string) $args['description'] : '';
        $original    = isset($args['original']) ? trim((string) $args['original']) : '';
        $attach_id   = isset($args['attachment_id']) ? (int) $args['attachment_id'] : 0;
        $phone       = isset($args['phone']) ? trim((string) $args['phone']) : '';

        $logo      = set_url_scheme(self::getImageBaseURL() . 'partyline-black.png', 'https');
        $edit_link = get_admin_url() . 'post.php?post=' . $post_id . '&action=edit';

        // Correctly-sized image (never the full-res original).
        $img_url = '';
        if ($attach_id) {
            $img_url = wp_get_attachment_image_url($attach_id, 'large');
        }
        if (! $img_url && $post_id && has_post_thumbnail($post_id)) {
            $img_url = get_the_post_thumbnail_url($post_id, 'large');
        }
        if ($img_url) {
            $img_url = set_url_scheme($img_url, 'https');
        }

        // Show the original only when it adds something beyond the description.
        $norm = function ($s) { return strtolower(trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) $s)))); };
        $show_original = ($original !== '' && $norm($original) !== $norm($description));

        $ts = $post_id ? get_post_timestamp($post_id) : 0;
        if (! $ts) {
            $ts = time();
        }
        $when = wp_date('M j, Y \a\t g:i a', $ts);

        $meta = 'Submitted by <strong style="color:#52525b;">' . esc_html($author) . '</strong>';
        if ($from !== '') {
            $meta .= ' &middot; ' . esc_html($from);
        }
        if ($phone !== '') {
            $meta .= ' &middot; ' . esc_html($phone);
        }

        ob_start();
        ?>
<div style="background:#f4f4f5;margin:0;padding:24px 12px;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
    <tr><td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="border-collapse:collapse;max-width:600px;width:100%;background:#ffffff;border:1px solid #e4e4e7;border-radius:16px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
        <tr><td style="padding:28px 24px 20px;text-align:center;border-bottom:1px solid #f0f0f1;">
          <img src="<?php echo esc_url($logo); ?>" width="170" alt="Partyline" style="display:inline-block;width:170px;max-width:60%;height:auto;">
        </td></tr>
        <tr><td style="padding:22px 28px 0;">
          <span style="display:inline-block;background:#18181b;color:#ffffff;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:5px 11px;border-radius:999px;">New Partyline</span>
        </td></tr>
        <?php if ($img_url): ?>
        <tr><td style="padding:18px 28px 0;">
          <img src="<?php echo esc_url($img_url); ?>" alt="" style="display:block;width:100%;max-width:544px;height:auto;border-radius:12px;border:1px solid #eeeeee;">
        </td></tr>
        <?php endif; ?>
        <tr><td style="padding:20px 28px 0;">
          <h1 style="margin:0;font-size:22px;line-height:1.28;color:#18181b;font-weight:800;"><?php echo esc_html($title); ?></h1>
        </td></tr>
        <?php if (trim(wp_strip_all_tags($description)) !== ''): ?>
        <tr><td style="padding:6px 28px 0;color:#3f3f46;font-size:15px;line-height:1.6;">
          <?php echo wpautop(wp_kses_post($description)); ?>
        </td></tr>
        <?php endif; ?>
        <?php if ($show_original): ?>
        <tr><td style="padding:22px 28px 0;">
          <div style="font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#a1a1aa;margin-bottom:8px;">Original message</div>
          <div style="background:#f4f4f5;border-left:3px solid #d4d4d8;border-radius:8px;padding:12px 14px;color:#52525b;font-size:14px;line-height:1.55;white-space:pre-wrap;"><?php echo esc_html($original); ?></div>
        </td></tr>
        <?php endif; ?>
        <tr><td style="padding:26px 28px 6px;">
          <a href="<?php echo esc_url($edit_link); ?>" style="display:inline-block;background:#18181b;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:13px 22px;border-radius:12px;">Review &amp; edit in WordPress &rarr;</a>
        </td></tr>
        <tr><td style="padding:20px 28px 26px;margin-top:6px;border-top:1px solid #f0f0f1;color:#a1a1aa;font-size:13px;line-height:1.6;">
          <?php echo wp_kses_post($meta); ?><br>
          <?php echo esc_html($when); ?>
        </td></tr>
      </table>
      <div style="max-width:600px;margin:14px auto 0;color:#a1a1aa;font-size:12px;text-align:center;">redbankgreen &middot; Partyline</div>
    </td></tr>
  </table>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * When a Partyline post is published, make sure it's attributed to the
     *  registered Partyliner (if we can identify them) and email them that it's
     *  live. Safe to call more than once — it only acts on Partyline posts, and
     *  only sends once (guarded by a post-meta flag).
     *
     * @param int $post_id
     */
    public static function notifyPartylinePublished( $post_id )
    {
        $post_id = (int) $post_id;
        $post    = get_post( $post_id );

        if ( ! $post || 'publish' !== $post->post_status ) {
            return;
        }
        // Only act on posts that came in through Partyline.
        if ( ! get_post_meta( $post_id, '_partyline_submission', true ) ) {
            return;
        }
        // Notify at most once per post.
        if ( get_post_meta( $post_id, '_partyline_published_notified', true ) ) {
            return;
        }

        $sub_email = sanitize_email( (string) get_post_meta( $post_id, '_partyline_submitter_email', true ) );
        $sub_phone = (string) get_post_meta( $post_id, '_partyline_submitter_phone', true );
        $sub_name  = (string) get_post_meta( $post_id, '_partyline_submitter_name', true );

        // Resolve the registered/known user: submitter email, then phone, then
        //  the post author (when it's a real, non-admin account).
        $user = null;
        if ( $sub_email ) {
            $uid = email_exists( $sub_email );
            if ( $uid ) {
                $user = get_userdata( $uid );
            }
        }
        if ( ! $user && '' !== $sub_phone ) {
            $user = Partyline_Core::getUserByPhoneNumber( $sub_phone );
        }
        if ( ! $user ) {
            $author = get_userdata( (int) $post->post_author );
            if ( $author && 1 !== (int) $author->ID ) {
                $user = $author;
            }
        }

        // Mark as notified BEFORE any wp_update_post below, so the author change
        //  can't re-enter this method through transition_post_status.
        update_post_meta( $post_id, '_partyline_published_notified', 1 );

        // Attribute the published post to the known Partyliner.
        if ( $user && (int) $post->post_author !== (int) $user->ID ) {
            wp_update_post( array( 'ID' => $post_id, 'post_author' => (int) $user->ID ) );
        }

        // Who to email, and under what name.
        $to   = $user ? $user->user_email : $sub_email;
        $name = $user ? ( $user->display_name ? $user->display_name : $user->user_login ) : $sub_name;

        if ( ! is_email( $to ) ) {
            return; // no known contact for this submission
        }

        self::sendPublishedEmail( $post_id, $to, $name );
    }

    /**
     * Email a Partyliner that their submission has been published.
     *
     * @param int    $post_id
     * @param string $to    Recipient email.
     * @param string $name  Recipient display name (optional).
     */
    public static function sendPublishedEmail( $post_id, $to, $name = '' )
    {
        $post_id = (int) $post_id;
        if ( ! is_email( $to ) ) {
            return;
        }

        $title    = get_the_title( $post_id );
        $permalink = get_permalink( $post_id );
        $greeting = ( '' !== trim( (string) $name ) ) ? 'Hi ' . $name . ',' : 'Hi there,';
        $logo     = set_url_scheme( self::getImageBaseURL() . 'partyline-black.png', 'https' );

        $img_url = '';
        if ( has_post_thumbnail( $post_id ) ) {
            $img_url = get_the_post_thumbnail_url( $post_id, 'large' );
            if ( $img_url ) {
                $img_url = set_url_scheme( $img_url, 'https' );
            }
        }

        $subject = 'Your Partyline is live: ' . wp_strip_all_tags( $title );

        ob_start();
        ?>
<div style="background:#f4f4f5;margin:0;padding:24px 12px;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
    <tr><td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="border-collapse:collapse;max-width:600px;width:100%;background:#ffffff;border:1px solid #e4e4e7;border-radius:16px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
        <tr><td style="padding:28px 24px 20px;text-align:center;border-bottom:1px solid #f0f0f1;">
          <img src="<?php echo esc_url( $logo ); ?>" width="170" alt="Partyline" style="display:inline-block;width:170px;max-width:60%;height:auto;">
        </td></tr>
        <tr><td style="padding:22px 28px 0;">
          <span style="display:inline-block;background:#16a34a;color:#ffffff;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:5px 11px;border-radius:999px;">Published</span>
        </td></tr>
        <tr><td style="padding:16px 28px 0;color:#3f3f46;font-size:15px;line-height:1.6;">
          <?php echo esc_html( $greeting ); ?>
        </td></tr>
        <tr><td style="padding:8px 28px 0;color:#3f3f46;font-size:15px;line-height:1.65;">
          Great news! Your Partyline is now live on redbankgreen. Thanks for helping tell the story of our community.
        </td></tr>
        <?php if ( $img_url ): ?>
        <tr><td style="padding:20px 28px 0;">
          <img src="<?php echo esc_url( $img_url ); ?>" alt="" style="display:block;width:100%;max-width:544px;height:auto;border-radius:12px;border:1px solid #eeeeee;">
        </td></tr>
        <?php endif; ?>
        <tr><td style="padding:18px 28px 0;">
          <h1 style="margin:0;font-size:22px;line-height:1.28;color:#18181b;font-weight:800;"><?php echo esc_html( $title ); ?></h1>
        </td></tr>
        <tr><td style="padding:22px 28px 6px;">
          <a href="<?php echo esc_url( $permalink ); ?>" style="display:inline-block;background:#18181b;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:13px 22px;border-radius:12px;">View your Partyline &rarr;</a>
        </td></tr>
        <tr><td style="padding:20px 28px 26px;margin-top:6px;border-top:1px solid #f0f0f1;color:#a1a1aa;font-size:13px;line-height:1.6;">
          Got another story or photo? Send us a Partyline anytime.
        </td></tr>
      </table>
      <div style="max-width:600px;margin:14px auto 0;color:#a1a1aa;font-size:12px;text-align:center;">redbankgreen &middot; Partyline</div>
    </td></tr>
  </table>
</div>
        <?php
        $html = (string) ob_get_clean();

        wp_mail( $to, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
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

    /* --------------------------------------------------------------------- */
    /* Partyliners (contributor accounts) + phone normalization              */
    /* --------------------------------------------------------------------- */

    /**
     * Normalize a phone number to E.164 (e.g. +17325551234) so it matches the
     * `From` Twilio sends. US-centric with a best-effort fallback; filterable.
     */
    public static function normalizePhone( $raw, $default_cc = '1' )
    {
        $raw     = trim( (string) $raw );
        $plus    = ( strpos( $raw, '+' ) === 0 );
        $digits  = preg_replace( '/\D/', '', $raw );
        if ( $digits === '' ) {
            return '';
        }
        if ( $plus ) {
            $e164 = '+' . $digits;
        } elseif ( strlen( $digits ) === 11 && $digits[0] === '1' ) {
            $e164 = '+' . $digits;
        } elseif ( strlen( $digits ) === 10 ) {
            $e164 = '+' . $default_cc . $digits;
        } else {
            $e164 = '+' . $digits;
        }
        return apply_filters( 'partyline_normalize_phone', $e164, $raw );
    }

    /** Last 10 digits of a phone, for loose matching. */
    public static function phoneLast10( $phone )
    {
        return substr( preg_replace( '/\D/', '', (string) $phone ), -10 );
    }

    /**
     * Find an existing Partyliner (by email, then phone) or create a new one in
     * the locked-down `partyliner` role. Returns the user ID or WP_Error.
     *
     * @param array $args name, email, phone, address
     */
    public static function findOrCreatePartyliner( $args )
    {
        $name    = isset( $args['name'] ) ? sanitize_text_field( $args['name'] ) : '';
        $email   = isset( $args['email'] ) ? sanitize_email( $args['email'] ) : '';
        $phone   = isset( $args['phone'] ) ? self::normalizePhone( $args['phone'] ) : '';
        $address = isset( $args['address'] ) ? sanitize_textarea_field( $args['address'] ) : '';

        if ( ! is_email( $email ) ) {
            return new WP_Error( 'partyline_bad_email', 'A valid email address is required.' );
        }

        // Dedupe: email first, then phone.
        $user_id = email_exists( $email );
        if ( ! $user_id && $phone !== '' ) {
            $found = Partyline_Core::getUserByPhoneNumber( $phone );
            if ( $found ) {
                $user_id = $found->ID;
            }
        }

        if ( $user_id ) {
            self::savePartylinerMeta( $user_id, $name, $phone, $address );
            return (int) $user_id;
        }

        // Create a new, minimal-capability Partyliner (random password, no login needed).
        $base = sanitize_user( current( explode( '@', $email ) ), true );
        if ( $base === '' ) {
            $base = 'partyliner';
        }
        $username = $base;
        $n = 1;
        while ( username_exists( $username ) ) {
            $username = $base . $n;
            $n++;
        }

        $user_id = wp_insert_user( array(
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => wp_generate_password( 24, true, true ),
            'display_name' => $name !== '' ? $name : $username,
            'role'         => 'partyliner',
        ) );
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        self::savePartylinerMeta( $user_id, $name, $phone, $address );

        // Brand-new Partyliner: welcome them (and copy the newsroom).
        self::sendPartylinerWelcomeEmail( (int) $user_id );

        return (int) $user_id;
    }

    /**
     * Welcome a brand-new Partyliner with how-to instructions, copying the
     *  newsroom notification list. The instructions adapt to which channels are
     *  enabled (app, whether login is required, and text messages).
     *
     * @param int $user_id
     */
    public static function sendPartylinerWelcomeEmail( $user_id )
    {
        $user = get_userdata( (int) $user_id );
        if ( ! $user || ! is_email( $user->user_email ) ) {
            return;
        }

        $settings = self::getSettings();
        $name     = $user->display_name ? $user->display_name : $user->user_login;
        $greeting = 'Hi ' . $name . ',';
        $logo     = set_url_scheme( self::getImageBaseURL() . 'partyline-black.png', 'https' );

        $has_pwa   = ! class_exists( 'Partyline_Pwa' ) || Partyline_Pwa::isEnabled();
        $anon_on   = class_exists( 'Partyline_Pwa' ) && Partyline_Pwa::allowAnonymous();
        $app_url   = class_exists( 'Partyline_Pwa' ) ? Partyline_Pwa::appUrl() : home_url( '/partyline/' );
        $login_url = wp_login_url( $app_url );
        $lostpw    = wp_lostpassword_url();

        $twilio_on = isset( $settings->twilio_enabled )
            ? (bool) $settings->twilio_enabled
            : ! empty( $settings->twilio_account_sid );
        $twilio_no = isset( $settings->twilio_phone_number ) ? trim( (string) $settings->twilio_phone_number ) : '';

        // --- Build the "how to Partyline" rows based on settings. ---
        $rows = array();

        if ( $has_pwa ) {
            $body  = 'Open <a href="' . esc_url( $app_url ) . '" style="color:#7c3aed;">' . esc_html( $app_url ) . '</a> on your phone, then tap your browser&rsquo;s Share or menu button and choose <strong>Add to Home Screen</strong> to install it like an app. Snap a photo, add your story, and send it in.';
            if ( $anon_on ) {
                $body .= '<br><br>You can send one right away, no login needed. Just use the same name, email, and phone you gave us, and we&rsquo;ll credit your posts to you.';
            } else {
                $body .= '<br><br>You have a Partyliner account, so log in with your email to submit. First time? Use the <a href="' . esc_url( $lostpw ) . '" style="color:#7c3aed;">Lost your password?</a> link to set a password, then <a href="' . esc_url( $login_url ) . '" style="color:#7c3aed;">log in here</a>.';
            }
            $rows[] = array( '📱', 'The app (recommended)', $body );
        }

        if ( $twilio_on ) {
            $body = 'You can also send your Partyline (a photo and a few words) as a text message';
            $body .= $twilio_no !== '' ? ' to <strong>' . esc_html( $twilio_no ) . '</strong>.' : '.';
            if ( $has_pwa ) {
                $body .= ' The app is the easiest way if you can install it.';
            }
            $rows[] = array( '💬', 'Text it in', $body );
        }

        $rows_html = '';
        foreach ( $rows as $r ) {
            $rows_html .= '<tr><td style="padding:16px 28px 0;">'
                . '<table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;"><tr>'
                . '<td valign="top" style="width:40px;font-size:22px;line-height:1;padding-right:12px;">' . $r[0] . '</td>'
                . '<td valign="top" style="color:#3f3f46;font-size:15px;line-height:1.6;">'
                . '<strong style="color:#18181b;display:block;margin-bottom:3px;">' . esc_html( $r[1] ) . '</strong>'
                . $r[2]
                . '</td></tr></table></td></tr>';
        }

        $subject = 'You&rsquo;re a Partyliner! Here&rsquo;s how to send in your first story';

        ob_start();
        ?>
<div style="background:#f4f4f5;margin:0;padding:24px 12px;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
    <tr><td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="border-collapse:collapse;max-width:600px;width:100%;background:#ffffff;border:1px solid #e4e4e7;border-radius:16px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
        <tr><td style="padding:28px 24px 20px;text-align:center;border-bottom:1px solid #f0f0f1;"><img src="<?php echo esc_url( $logo ); ?>" width="170" alt="Partyline" style="width:170px;max-width:60%;height:auto;"></td></tr>
        <tr><td style="padding:22px 28px 0;"><span style="display:inline-block;background:#16a34a;color:#ffffff;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:5px 11px;border-radius:999px;">You&rsquo;re a Partyliner</span></td></tr>
        <tr><td style="padding:16px 28px 0;color:#3f3f46;font-size:15px;line-height:1.6;"><?php echo esc_html( $greeting ); ?></td></tr>
        <tr><td style="padding:8px 28px 0;color:#3f3f46;font-size:15px;line-height:1.65;">You&rsquo;re approved to send in Partylines. Here&rsquo;s how to get started:</td></tr>
        <?php echo $rows_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <tr><td style="padding:22px 28px 26px;margin-top:6px;border-top:1px solid #f0f0f1;color:#a1a1aa;font-size:13px;line-height:1.6;">Thanks for helping tell the story of our community. We can&rsquo;t wait to see what you send.</td></tr>
      </table>
      <div style="max-width:600px;margin:14px auto 0;color:#a1a1aa;font-size:12px;text-align:center;">redbankgreen &middot; Partyline</div>
    </td></tr>
  </table>
</div>
        <?php
        $html = (string) ob_get_clean();

        // Copy the newsroom notification list (Bcc, so the newsroom's internal
        //  addresses aren't exposed to the new Partyliner). Skip their own address.
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        $bcc = array();
        foreach ( self::getNotificationEmails() as $e ) {
            if ( is_email( $e ) && strtolower( $e ) !== strtolower( $user->user_email ) ) {
                $bcc[] = $e;
            }
        }
        if ( ! empty( $bcc ) ) {
            $headers[] = 'Bcc: ' . implode( ', ', $bcc );
        }

        wp_mail( $user->user_email, html_entity_decode( $subject, ENT_QUOTES ), $html, $headers );
    }

    /**
     * Email a Partyliner a one-tap link to set their password (so they can log
     *  in). Sent only to them, never copied to the newsroom, since it carries a
     *  reset token. Returns true on success.
     *
     * @param int $user_id
     */
    public static function sendPartylinerSetPasswordEmail( $user_id )
    {
        $user = get_userdata( (int) $user_id );
        if ( ! $user || ! is_email( $user->user_email ) ) {
            return false;
        }

        $key = get_password_reset_key( $user );
        if ( is_wp_error( $key ) ) {
            return false;
        }
        $set_url = network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user->user_login ), 'login' );

        $name     = $user->display_name ? $user->display_name : $user->user_login;
        $greeting = 'Hi ' . $name . ',';
        $logo     = set_url_scheme( self::getImageBaseURL() . 'partyline-black.png', 'https' );
        $app_url  = class_exists( 'Partyline_Pwa' ) ? Partyline_Pwa::appUrl() : home_url( '/partyline/' );

        ob_start();
        ?>
<div style="background:#f4f4f5;margin:0;padding:24px 12px;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
    <tr><td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="border-collapse:collapse;max-width:600px;width:100%;background:#ffffff;border:1px solid #e4e4e7;border-radius:16px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
        <tr><td style="padding:28px 24px 20px;text-align:center;border-bottom:1px solid #f0f0f1;"><img src="<?php echo esc_url( $logo ); ?>" width="170" alt="Partyline" style="width:170px;max-width:60%;height:auto;"></td></tr>
        <tr><td style="padding:22px 28px 0;"><span style="display:inline-block;background:#18181b;color:#ffffff;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:5px 11px;border-radius:999px;">Set your password</span></td></tr>
        <tr><td style="padding:16px 28px 0;color:#3f3f46;font-size:15px;line-height:1.6;"><?php echo esc_html( $greeting ); ?></td></tr>
        <tr><td style="padding:8px 28px 0;color:#3f3f46;font-size:15px;line-height:1.65;">You&rsquo;re a Partyliner! Set a password below so you can log in and send in your Partylines from the app.</td></tr>
        <tr><td style="padding:22px 28px 6px;"><a href="<?php echo esc_url( $set_url ); ?>" style="display:inline-block;background:#18181b;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:13px 22px;border-radius:12px;">Set your password &rarr;</a></td></tr>
        <tr><td style="padding:14px 28px 0;color:#a1a1aa;font-size:13px;line-height:1.6;">Once your password is set, log in and open <a href="<?php echo esc_url( $app_url ); ?>" style="color:#7c3aed;"><?php echo esc_html( $app_url ); ?></a> on your phone. Add it to your home screen for one-tap access.</td></tr>
        <tr><td style="padding:18px 28px 26px;margin-top:6px;border-top:1px solid #f0f0f1;color:#a1a1aa;font-size:12px;line-height:1.6;">If you didn&rsquo;t expect this, you can ignore this email. The link expires for security, but you can always use &ldquo;Lost your password?&rdquo; on the login page.</td></tr>
      </table>
      <div style="max-width:600px;margin:14px auto 0;color:#a1a1aa;font-size:12px;text-align:center;">redbankgreen &middot; Partyline</div>
    </td></tr>
  </table>
</div>
        <?php
        $html = (string) ob_get_clean();

        return wp_mail( $user->user_email, 'Set your Partyliner password', $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
    }

    /** Store a Partyliner's contact details in user meta. */
    public static function savePartylinerMeta( $user_id, $name, $phone, $address )
    {
        if ( $phone !== '' ) {
            update_user_meta( $user_id, 'partyline_phone', self::normalizePhone( $phone ) );
        }
        if ( $address !== '' ) {
            update_user_meta( $user_id, 'partyline_address', $address );
        }
        if ( $name !== '' ) {
            $parts = preg_split( '/\s+/', trim( $name ), 2 );
            update_user_meta( $user_id, 'first_name', $parts[0] );
            if ( isset( $parts[1] ) ) {
                update_user_meta( $user_id, 'last_name', $parts[1] );
            }
            wp_update_user( array( 'ID' => $user_id, 'display_name' => $name ) );
        }
        update_user_meta( $user_id, 'partyline_is_partyliner', 1 );
    }
}