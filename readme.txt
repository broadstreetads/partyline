=== Partyline: Reader-Submitted Stories for Local News and Magazines ===
Contributors: katzgrau, broadstreetads
Tags: community, local news, ai, sms, user generated content
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 1.3.0
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Local news is the original social media. Let your community share and tell its side of the story with Partyline.

== Description ==

Partyline is a WordPress plugin that lets anyone in your community send in a story, a tip, a moment, or just a cute dog photo. They can submit right from an installable app on their phone, or by text message via Twilio. Partyline optionally gives submissions a quick AI polish using OpenAI, and creates a draft post in your WordPress dashboard for you to review.

It’s the fastest way to turn real, spontaneous contributions into published content. Whether it’s breaking news or just something unexpected and delightful, Partyline brings your readers into the newsroom.

Made for local publishers who are short on time but big on community.

**Top 10 Uses for Partyline**
*A lovingly curated list from experience, originally shared with LINA Publishers in Australia*

1. **Cover stories you'd never get to otherwise**, like proms, graduations, and scout ceremonies.
2. **Post ridiculous stuff that wouldn’t fly as a “real” article**, and watch the traffic surprise you.
3. **File an initial post when a breaking story hits**, straight from the street.
4. **Capture the in-the-moment energy** of a parade, protest, or town meeting.
5. **Share sunsets and puppy pics**, without feeling like a complete sellout.
6. **Keep your advertisers happy** by actually posting their community event (and looking cool doing it).
7. **Turn your readers into local mini-celebrities**, and spark conversations offline.
8. **Give voice to people who never get quoted.** Partyline lowers the barrier to entry.
9. **Redirect PR people** to a more productive outlet than your inbox.
10. **Reclaim your newsroom’s social media power**, because you were doing it before Facebook anyway.


== Installation ==

1. Upload the `partyline` directory to your `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to the Partyline settings page. The contributor app is enabled by default at `your-site.com/partyline`; share that link with your community.
4. (Optional) To also accept submissions by text message, enable Twilio in the settings, add your credentials, and point your Twilio number's messaging webhook at the URL shown there.
5. (Optional) Add an OpenAI API key to enable AI cleanup and voice dictation.

== Frequently Asked Questions ==

= What do I need to use Partyline? =

Nothing. The contributor app is enabled by default, so your community can start submitting right away. Twilio and ChatGPT are optional add-ons, not requirements:

*   **Twilio (Optional)**: a [Twilio](https://www.twilio.com/) account and an SMS-capable phone number, to also accept submissions by text.
*   **ChatGPT / OpenAI (Optional)**: an [OpenAI API key](https://platform.openai.com/account/api-keys), to auto-correct grammar and spelling and power voice dictation in the app.

= How do I manage Partyliners? =

A Partyliner is a WordPress user with a phone number, which is how a text message is matched back to their account. To manage them, go to **Partyline > Partyliners** in your admin menu. There you can see everyone at a glance and add a Partyliner directly (name, phone, email).

You can also turn on **public applications** in the settings so readers can apply. Either way, a Partyliner's phone number is stored in the format `+15555555555` (you can still set it on the standard WordPress user profile too).

== External services ==

This plugin connects to the following third-party services. None of them are contacted until you configure the corresponding credentials on the Partyline settings page.

**Twilio**

Used to receive SMS submissions from your community and to download any media (photos) attached to those messages.

- What is sent: when Twilio POSTs an inbound SMS to your site's webhook URL, the plugin reads the message body, sender phone number, recipient phone number, and any attached media URLs. To fetch authenticated media files, the plugin makes outbound HTTPS requests to `api.twilio.com` (and to the redirected media CDN URLs Twilio returns) using your Twilio Account SID and Auth Token.
- When it is sent: only when an SMS is received at the configured webhook URL and the message includes media attachments.
- Service: Twilio, Inc. Terms of Service: https://www.twilio.com/legal/tos . Privacy Policy: https://www.twilio.com/legal/privacy .

**OpenAI (ChatGPT)**

Optional. Used to clean up spelling/grammar of submitted messages, generate post titles, and (in the app) transcribe voice dictation and draft a title and blurb.

- What is sent: the text of a submission (from SMS or the app), plus a short instruction prompt, are sent to `https://api.openai.com/v1/chat/completions`. In the app, voice recordings are sent to `https://api.openai.com/v1/audio/transcriptions` (Whisper) to transcribe them, and the submitted photo may be included so the model can draft a fitting title and blurb. All requests use the OpenAI API key you configure.
- When it is sent: only if you have entered an OpenAI API key in the Partyline settings, and only at the moment a submission is being processed into a draft post.
- Service: OpenAI, L.L.C. Terms of Use: https://openai.com/policies/terms-of-use . Privacy Policy: https://openai.com/policies/privacy-policy .

**Cloudflare Turnstile**

Optional. Used to verify that anonymous submissions and public applications come from a real person rather than a bot.

- What is sent: when you configure a Turnstile site key, the public submission and application forms load Cloudflare's widget script from `https://challenges.cloudflare.com`, and the visitor's browser obtains a token. On submission, that token is sent to your server and verified against `https://challenges.cloudflare.com/turnstile/v0/siteverify` using your Turnstile secret key.
- When it is sent: only if you have entered a Turnstile site key in the settings, and only on the public submission or application forms. Without a key, a built-in math challenge is used instead and no third party is contacted.
- Service: Cloudflare, Inc. Terms: https://www.cloudflare.com/website-terms/ . Privacy Policy: https://www.cloudflare.com/privacypolicy/ .

== Screenshots ==

1. The Partyline settings page.
2. The Partyliners management screen.
3. The user profile page with the "Partyline Phone Number" field.
4. An example of a post created by Partyline.

== Changelog ==

= 1.3.0 =
* FEATURE: Contributor app (PWA), an installable web app where people submit a photo and a story from their phone; now the primary way to collect Partylines, with SMS/Twilio as an optional secondary channel
* FEATURE: Multiple photos per Partyline in the app; the first is the cover (featured image), and the AI write-up looks at the cover photo
* FEATURE: Optional anonymous submissions, protected by a built-in math challenge and honeypot (with optional Cloudflare Turnstile for stronger protection)
* FEATURE: Optional voice dictation (OpenAI Whisper) and an AI write-up of the photo and story in the app
* FEATURE: Public "apply to be a Partyliner" page (confirmed by email) plus a dedicated Partyline > Partyliners management screen
* FEATURE: New Partyliners get a welcome email with how-to instructions; hand-added Partyliners get a link to set their password, and admins can resend one from the Partyliners screen
* FEATURE: When a Partyline is published, it is attributed to the registered Partyliner and they are emailed that it is live
* ENHANCEMENT: Phone numbers are normalized to E.164 so text messages reliably match a Partyliner
* ENHANCEMENT: Redesigned admin screens and an in-plugin How-To guide
* ENHANCEMENT: The app title, home-screen text, and icon follow your site (site title and Site Icon)
* ENHANCEMENT: Refreshed the plugin icon and banner

= 1.2.2 =
* ENHANCEMENT: Removed the unused bundled Broadstreet API client (eliminates direct cURL usage and `Broadstreet*` class names)
* ENHANCEMENT: Removed redundant `wp-admin/includes/*` requires from the Twilio webhook handler — the sideload helper loads what it needs
* ENHANCEMENT: Renamed the `has_partyline_phone` query var to `partyline_has_phone` so the prefix leads
* ENHANCEMENT: Dropped the Broadstreet entry from the External services section of the readme

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