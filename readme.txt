=== Partyline ===
Contributors: katzgrau, broadstreetads
Tags: community, local news, ai, sms, user generated content
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 1.2.1
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Local news is the original social media. Let your community share and tell its side of the story with Partyline.

== Description ==

Partyline is a WordPress plugin that lets anyone in your community text in a story, a tip, a moment—or just a cute dog photo. It captures SMS messages sent via Twilio, optionally gives them a quick AI polish using OpenAI, and creates a draft post in your WordPress dashboard.

It’s the fastest way to turn real, spontaneous contributions into published content. Whether it’s breaking news or just something unexpected and delightful, Partyline brings your readers into the newsroom.

Made for local publishers who are short on time but big on community.

**Top 10 Uses for Partyline**
*A lovingly curated list from experience — originally shared with LINA Publishers in Australia*

1. **Cover stories you'd never get to otherwise** — like proms, graduations, and scout ceremonies.
2. **Post ridiculous stuff that wouldn’t fly as a “real” article** — and watch the traffic surprise you.
3. **File an initial post when a breaking story hits** — straight from the street.
4. **Capture the in-the-moment energy** of a parade, protest, or town meeting.
5. **Share sunsets and puppy pics** — without feeling like a complete sellout.
6. **Keep your advertisers happy** by actually posting their community event (and looking cool doing it).
7. **Turn your readers into local mini-celebrities** — and spark conversations offline.
8. **Give voice to people who never get quoted** — Partyline lowers the barrier to entry.
9. **Redirect PR people** to a more productive outlet than your inbox.
10. **Reclaim your newsroom’s social media power** — because you were doing it before Facebook anyway.


== Installation ==

1. Upload the `partyline` directory to your `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure your Twilio webhook to point to `your-site.com?partyline_twilio_webhook=1`.
4. Go to the Partyline settings page to configure any additional options.

== Frequently Asked Questions ==

= What do I need to use Partyline? =

You will need the following:

*   **Twilio Account**: A free or paid account with [Twilio](https://www.twilio.com/) and a phone number capable of receiving SMS messages.
*   **ChatGPT API Key (Optional)**: An API key from [OpenAI](https://platform.openai.com/account/api-keys) is not required but is highly recommended. It enables Partyline to automatically correct grammar and spelling in submissions and will be used for other AI-powered features in the future.

= How do I manage Partyliners? =

You can associate specific phone numbers with WordPress users to automatically attribute posts to them. To manage your Partyliners:

1.  Go to the **Users** page in your WordPress admin dashboard.
2.  Add a new user or edit an existing one.
3.  Fill in the **Partyline Phone Number** field with the user's full phone number in the format `+15555555555`.

You can also view all users with a Partyline phone number by going to **Partyline > All Partyliners** in the admin menu.

== External services ==

This plugin connects to the following third-party services. None of them are contacted until you configure the corresponding credentials on the Partyline settings page.

**Twilio**

Used to receive SMS submissions from your community and to download any media (photos) attached to those messages.

- What is sent: when Twilio POSTs an inbound SMS to your site's webhook URL, the plugin reads the message body, sender phone number, recipient phone number, and any attached media URLs. To fetch authenticated media files, the plugin makes outbound HTTPS requests to `api.twilio.com` (and to the redirected media CDN URLs Twilio returns) using your Twilio Account SID and Auth Token.
- When it is sent: only when an SMS is received at the configured webhook URL and the message includes media attachments.
- Service: Twilio, Inc. Terms of Service: https://www.twilio.com/legal/tos . Privacy Policy: https://www.twilio.com/legal/privacy .

**OpenAI (ChatGPT)**

Optional. Used to clean up spelling/grammar of submitted messages and to generate post titles.

- What is sent: the text body of an inbound SMS submission, plus a short instruction prompt, are sent to `https://api.openai.com/v1/chat/completions` using the OpenAI API key you configure.
- When it is sent: only if you have entered an OpenAI API key in the Partyline settings, and only at the moment an inbound SMS is being processed into a draft post.
- Service: OpenAI, L.L.C. Terms of Use: https://openai.com/policies/terms-of-use . Privacy Policy: https://openai.com/policies/privacy-policy .

**Broadstreet**

Optional. Used only if you have entered a Broadstreet API key to associate Partyline with a Broadstreet network.

- What is sent: your Broadstreet API key and the network ID you have configured, sent to `https://api.broadstreetads.com` to look up network information.
- When it is sent: only when a Broadstreet API key has been configured and the settings page or a network lookup runs.
- Service: Broadstreet Ads, Inc. Terms of Service: https://broadstreetads.com/terms/ . Privacy Policy: https://broadstreetads.com/privacy/ .

== Screenshots ==

1. The Partyline settings page.
2. The "All Partyliners" user management screen.
3. The user profile page with the "Partyline Phone Number" field.
4. An example of a post created by Partyline.

== Changelog ==

= 1.2.1 =
* SECURITY: Settings AJAX endpoint now verifies a WordPress nonce, requires `manage_options`, and sanitizes every field before saving
* ENHANCEMENT: Moved inline admin scripts and styles into enqueued assets; settings page now bootstraps via `wp_localize_script`
* ENHANCEMENT: Bundled the Partyline menu icon locally instead of loading it from an external S3 URL
* ENHANCEMENT: Documented Twilio, OpenAI, and Broadstreet as external services in the readme
* ENHANCEMENT: Updated Alpine.js to 3.15.12
* ENHANCEMENT: Moved the Partyline admin menu out of the core admin hierarchy band (position 81)
* ENHANCEMENT: Stopped writing the raw Twilio webhook body to the log file
* FIX: Readme typos

= 1.2.0 =
* ENHANCEMENT: Replaced AngularJS with Alpine.js in the admin settings UI
* ENHANCEMENT: WordPress.org compliance pass — added ABSPATH guards, switched log file I/O to WP_Filesystem and `wp_mkdir_p()`, replaced `date()` with `gmdate()`, prefixed globals, hardened webhook key comparison with `hash_equals()`, unslashed/sanitized all `$_POST`/`$_SERVER` input, removed debug `print_r()` calls
* ENHANCEMENT: Cleaned up build script to exclude dev files from the distribution zip
* ENHANCEMENT: Bumped "Tested up to" to 7.0

= 1.1.0 =
* ENHANCEMENT: Support multiple image attachments
* ENHANCEMENT: Added fallback for Twilio images that don't require authentication
* ENHANCEMENT: Refactor code

= 1.0.2 =
* ENHANCEMENT: Applied escaped outputs as per WordPress Coding Standards
* ENHANCEMENT: Tightened up enqueue scripts

= 1.0.1 =
* ENHANCEMENT: Tightened up conditional checks on a number of variables
* ENHANCEMENT: Added server-side authentication to Twilio image retrieval
* ENHANCEMENT: Added Twilio-specific settings to the Admin settings page
* ENHANCEMENT: Enabled generation of thumbnails based on Twilio-supplied image

= 1.0.0 =
* Initial public release.