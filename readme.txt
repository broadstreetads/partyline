=== Partyline by Broadstreet ===
Contributors: katzgrau
Donate link: https://broadstreetads.com/
Tags: twilio, sms, community, local news, chatgpt, openai, user generated content
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.0.0
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Local news is the original social media. Let your community share tell its side of the story with Partyline.

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
5. **Share sunsets and puppy pics** — without feeling like a complte sellout.
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

== Screenshots ==

1. The Partyline settings page.
2. The "All Partyliners" user management screen.
3. The user profile page with the "Partyline Phone Number" field.
4. An example of a post created by Partyline.

== Changelog ==

= 1.0.0 =
* Initial public release.

== Upgrade Notice ==

= 1.0.0 =
* Initial release. 