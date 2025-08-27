<?php
/**
 * This file acts as the 'Controller' of the application. It contains a class
 *  that will load the required hooks, and the callback functions that those
 *  hooks execute.
 *
 * @author Broadstreet Ads <labs@broadstreetads.com>
 */

require_once dirname(__FILE__) . '/Ajax.php';
require_once dirname(__FILE__) . '/Cache.php';
require_once dirname(__FILE__) . '/Config.php';
require_once dirname(__FILE__) . '/Benchmark.php';
require_once dirname(__FILE__) . '/Log.php';
require_once dirname(__FILE__) . '/Twilio.php';
require_once dirname(__FILE__) . '/Utility.php';
require_once dirname(__FILE__) . '/View.php';
require_once dirname(__FILE__) . '/Exception.php';
require_once dirname(__FILE__) . '/Vendor/Broadstreet.php';

if (! class_exists('Partyline_Core')):

/**
 * This class contains the core code and callback for the behavior of Wordpress.
 *  It is instantiated and executed directly by the Broadstreet plugin loader file
 *  (which is most likely at the root of the Broadstreet installation).
 */
class Partyline_Core
{
    CONST KEY_API_KEY           = 'Partyline_API_Key';
    CONST KEY_NETWORK_ID        = 'Partyline_Network_Key';
    CONST KEY_SETTINGS          = 'Partyline_Settings';
    CONST DEFAULT_TITLE         = 'Partyline Post';

    public static $globals = null;

    /**
     * The constructor
     */
    public function __construct()
    {
        Partyline_Log::add('debug', "Partyline initializing..");
    }

    /**
     * Get the Broadstreet environment loaded and register Wordpress hooks
     */
    public function execute()
    {
        $this->_registerHooks();
    }

    /**
     * Get a Broadstreet client
     */
    public function getBroadstreetClient()
    {
        return Partyline_Utility::getBroadstreetClient();
    }

    /**
     * Register Wordpress hooks required for Broadstreet
     */
    private function _registerHooks()
    {
        Partyline_Log::add('debug', "Registering hooks..");

        # -- Below ajax hook --
        add_action('wp_ajax_partyline_save_settings', array('Partyline_Ajax', 'saveSettings'));

        # -- Below is core functionality --
        add_action('admin_menu', 	array($this, 'adminCallback'     ));
        add_action('admin_init', 	array($this, 'adminInitCallback' ));
        add_action('init', array($this, 'catchTwilioWebhook'));
        
        # -- User profile --
        add_action('show_user_profile', array($this, 'addPartylinePhoneField'));
        add_action('edit_user_profile', array($this, 'addPartylinePhoneField'));
        add_action('personal_options_update', array($this, 'savePartylinePhoneField'));
        add_action('edit_user_profile_update', array($this, 'savePartylinePhoneField'));

        # -- New User form --
        add_action('user_new_form', array($this, 'addPartylinePhoneFieldNewUser'));
        add_action('user_register', array($this, 'savePartylinePhoneField'));

        # -- Filter users --
        add_action('pre_get_users', array($this, 'filterUsersByPartylinePhone'));
        add_action('admin_notices', array($this, 'showPartylineUserNotice'));
    }

    /**
     * A callback executed whenever the user tried to access the Broadstreet admin page
     */
    public function adminCallback()
    {
        $icon_url = 'none';
        $posts = $this->getPartylinePosts();
        $notification_count = isset( $posts ) && is_array( $posts ) ? count( $posts ) : 0;

        add_menu_page(
            'Draft Partyline Posts',
            $notification_count > 0 ? sprintf('Partyline <span class="awaiting-mod">%d</span>', $notification_count) : 'Partyline',
            'manage_options',
            'Partyline',
            array($this, 'adminMenuCallback'),
            $icon_url,
            25
        );

        add_submenu_page('Partyline', 'Settings', 'Settings', 'edit_pages', 'Partyline-Settings', array($this, 'adminSettingsMenuCallback'));
        add_submenu_page('Partyline', 'All Partyliners', 'All Partyliners', 'list_users', 'users.php?has_partyline_phone=1');
    }

    /**
     * A callback executed when the admin page callback is a about to be called.
     *  Use this for loading stylesheets/css.
     */
    public function adminInitCallback()
    {
        wp_enqueue_style(
			'partyline-admin-styles', 
			Partyline_Utility::getCSSBaseURL() . 'admin.css',
			array(),
			PARTYLINE_VERSION
			);
        # Only register javascript and css if the Broadstreet admin page is loading
        if(isset($_SERVER['QUERY_STRING']) && strstr($_SERVER['QUERY_STRING'], 'Partyline'))
        {
			wp_enqueue_style(
				'partyline-styles', 
				Partyline_Utility::getCSSBaseURL() . 'broadstreet.css',
				array(),
				PARTYLINE_VERSION
			);            
            wp_enqueue_script(
				'partyline-main',
				Partyline_Utility::getJSBaseURL().'broadstreet.js',
				array(),
				PARTYLINE_VERSION
			);           
            wp_enqueue_script(
				'angular-js',
				Partyline_Utility::getJSBaseURL().'angular.min.js',
				array(),
				PARTYLINE_VERSION
			);
        }
    }

    /**
     * The callback that is executed when the user is loading the admin page.
     *  Basically, output the page content for the admin page. The function
     *  acts just like a controller method for and MVC app. That is, it loads
     *  a view.
     */
    public function adminMenuCallback()
    {
        Partyline_Log::add('debug', "Admin page callback executed");
        
        $data = array();
        $data['posts'] = $this->getPartylinePosts();
        $data['errors'] = array(); // Add any errors if needed

        Partyline_View::load('admin/posts', $data);
    }

    /**
     * The callback for the settings page
     */
    public function adminSettingsMenuCallback()
    {
        Partyline_Log::add('debug', "Admin settings page callback executed");
        $data = array();

        $data['api_key']            = Partyline_Utility::getOption(self::KEY_API_KEY);
        $data['network_id']         = Partyline_Utility::getOption(self::KEY_NETWORK_ID);
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

        Partyline_View::load('admin/settings', $data);
    }

    /**
     * Get pending Partyline posts
     */
    public function getPartylinePosts()
    {
        $settings = Partyline_Utility::getSettings();
        $selected_category = isset( $settings->partyline_category ) ? $settings->partyline_category : null;

		if ( $selected_category ) {

			$args = array (
				'category' => $selected_category,
				'posts_per_page' => -1,
				'post_status' => 'draft'
			);

			return get_posts($args);

		} else {
			return false;
		}
    }

    /**
     * Handle incoming Twilio webhook
     */
    public function catchTwilioWebhook()
    {
        $twilio = Partyline_Twilio::fromPost();
        // Check if the request has 'partyline_twilio_webhook' parameter.
        if ($twilio) {
            
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $settings = Partyline_Utility::getSettings();
            $selected_category = isset($settings->partyline_category) ? $settings->partyline_category : 0;

            // Find user by phone number
            $user = self::getUserByPhoneNumber($twilio->from);
            $author_name = 'Anonymous Partyliner';
            $author_id = 1; // Default to admin

            if ($user) {
                $author_name = $user->display_name;
                $author_id = $user->ID;
            }

            $post_content = '';

            // Sideload all image attachments and collect IDs
            $attachment_ids = array();
            if (!empty($twilio->attachments) && is_array($twilio->attachments)) {
                foreach ($twilio->attachments as $attachment) {
                    $attachment_url = isset($attachment->url) ? $attachment->url : '';
                    $attachment_type = isset($attachment->type) ? $attachment->type : '';

                    if ($attachment_url && strpos($attachment_type, 'image/') === 0) {
                        $attachment_id = Partyline_Utility::sideloadAuthenticatedImage($attachment_url, $attachment_type);
                        if ($attachment_id && !is_wp_error($attachment_id)) {
                            $attachment_ids[] = $attachment_id;
                            $post_content .= wp_get_attachment_image($attachment_id, 'full');
                        } else {
                            Partyline_Log::add('debug', 'Failed to sideload media: ' . print_r($attachment_id, true));
                        }
                    }
                }
            }

            // Parse and append message body
            $components = Partyline_Utility::parseContent($twilio->body);
            $post_content .= wpautop($components['body']);

            $post_content .= '<p><em>Submitted by ' . $author_name . '</em></p>';

            // Only create a post if we have content or attachments
            if (!empty(trim($twilio->body)) || !empty($attachment_ids)) {
                // Create a new post.
                $post_id = wp_insert_post(array(
                    'post_title'    => $components['title'],
                    'post_content'  => $post_content,
                    'post_status'   => $components['immediate'] ? 'publish' : 'draft',
                    'post_author'   => $author_id,
                    'post_category' => $selected_category ? array($selected_category) : array()
                ));

                // Set the first image attachment as the featured image
                if (!empty($attachment_ids)) {
                    set_post_thumbnail($post_id, $attachment_ids[0]);
                }

                Partyline_Utility::sendNotificationEmail($post_id, $twilio->from, $post_content, $components['title'], $author_name);
            }

            $twilio->sendResponse("Thank You! Not every post will always make it but if it's quality and authentic we'll sure as heck try!");
            exit;
        }
    }

    public function sendErrorEmail($message) {
        
    }

    /**
     * Show a notice on the users page when viewing Partyliners.
     */
    public function showPartylineUserNotice() {
        global $pagenow;
        if (is_admin() && 'users.php' == $pagenow && isset($_GET['has_partyline_phone'])) {
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <strong>Welcome to the Partyliners Hub!</strong> To add a new Partyliner, just click the "Add New" button above and make sure to fill in their "Partyline Phone Number".
                </p>
            </div>
            <?php
        }
    }

    /**
     * Filter the users list to show only users with a Partyline phone number.
     * @param object $query
     */
    public function filterUsersByPartylinePhone($query) {
        global $pagenow;
        if (is_admin() && 'users.php' == $pagenow && isset($_GET['has_partyline_phone'])) {
            $meta_query = array(
                array(
                    'key' => 'partyline_phone',
                    'value' => '',
                    'compare' => '!='
                )
            );
            $query->set('meta_query', $meta_query);
        }
    }

    /**
     * Get a user by their Partyline phone number
     * @param string $phone_number
     * @return WP_User|false
     */
    public static function getUserByPhoneNumber($phone_number) {
        $users = get_users(array(
            'meta_key' => 'partyline_phone',
            'meta_value' => $phone_number,
            'number' => 1,
            'count_total' => false
        ));

        if (!empty($users)) {
            return $users[0];
        }

        return false;
    }

    /**
     * Add phone number field to user profile
     * @param object $user
     */
    public function addPartylinePhoneField($user) {
        ?>
        <h3>Partyline Phone Number</h3>
        <table class="form-table">
            <tr>
                <th><label for="partyline_phone">Phone Number</label></th>
                <td>
                    <input type="tel" name="partyline_phone" id="partyline_phone" value="<?php echo esc_attr(get_user_meta($user->ID, 'partyline_phone', true)); ?>" class="regular-text" /><br />
                    <span class="description">The phone number this user will use to post to Partyline. Must be in the format +15555555555.</span>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Add phone number field to new user form
     */
    public function addPartylinePhoneFieldNewUser() {
        ?>
        <tr class="form-field">
            <th scope="row"><label for="partyline_phone">Partyline Phone Number</label></th>
            <td>
                <input type="tel" name="partyline_phone" id="partyline_phone" value="" class="regular-text" /><br />
                <span class="description">The phone number this user will use to post to Partyline. Must be in the format +15555555555.</span>
            </td>
        </tr>
        <?php
    }

    /**
     * Save Partyline phone number from user profile
     * @param int $user_id
     */
    public function savePartylinePhoneField($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        if(isset($_POST['partyline_phone']))
        {
            update_user_meta($user_id, 'partyline_phone', sanitize_text_field($_POST['partyline_phone']));
        }
    }
}

endif;
