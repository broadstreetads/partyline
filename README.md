# Partyline

Local news is the original social media. Let your community share tell its side of the story with Partyline.

## Description

Partyline is a WordPress plugin that lets anyone in your community text in a story, a tip, a moment—or just a cute dog photo. It captures SMS messages sent via Twilio, optionally gives them a quick AI polish using OpenAI, and creates a draft post in your WordPress dashboard.

It’s the fastest way to turn real, spontaneous contributions into published content. Whether it’s breaking news or just something unexpected and delightful, Partyline brings your readers into the newsroom.

Made for local publishers who are short on time but big on community.

## Top 10 Uses for Partyline
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


### Note from the author, Kenny Katzgrau

My apologies to everyone who asked me for a copy of Partyline. I said, "Yes, of course!"—which was much easier said than done.

The original version of Partyline was a prototype that had some important bits hardcoded into it. It was a small task to remove those, but in the process of making it sufficiently generic for everyone's usage, it became clear that it was a better idea to restructure the plugin for future development.

Then there were a few basic features that should have been present but were not, so I had to add those.

Then I had to add some basic documentation! All of this was happening while [running Broadstreet](https://broadstreetads.com/), [traveling](https://www.kennykatzgrau.com/), [hosting webinars](https://www.youtube.com/@BroadstreetAds/videos), and [getting charged as a disorderly person](https://freedom.press/issues/nj-reporters-face-unconstitutional-charges-for-refusing-to-unpublish-news/) in the ordinary course of my duties as [Publisher of Red Bank Green](https://www.redbankgreen.com/).

Anyway, thank you for your patience.

LONG LIVE LOCAL NEWS

P.S. This project will eventually belong to the Engineering Local Media Foundation (still in formation), so the GitHub account that it's hosted on will change.

## Requirements

To use Partyline, you will need the following:

*   **Twilio Account**: A free or paid account with [Twilio](https://www.twilio.com/) and a phone number capable of receiving SMS messages.
*   **ChatGPT API Key (Optional)**: An API key from [OpenAI](https://platform.openai.com/account/api-keys) is not required but is highly recommended. It enables Partyline to automatically correct grammar and spelling in submissions and will be used for other AI-powered features in the future.

## Installation

1.  Upload the `partyline` directory to your `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Configure your Twilio webhook to point to `your-site.com?partyline_twilio_webhook=1`.
4.  Go to the Partyline settings page to configure any additional options.

## Usage

To use Partyline, simply send an SMS message to your configured Twilio number. The plugin will automatically pick it up, process it, and create a new draft post for you to review.

### Managing Partyliners

You can associate specific phone numbers with WordPress users to automatically attribute posts to them. To manage your Partyliners:

1.  Go to the **Users** page in your WordPress admin dashboard.
2.  Add a new user or edit an existing one.
3.  Fill in the **Partyline Phone Number** field with the user's full phone number in the format `+15555555555`.

You can also view all users with a Partyline phone number by going to **Partyline > All Partyliners** in the admin menu.

## External services ##

### Twilio (webhooks for incoming SMS) ###
What it is and what it’s used for: If enabled, the plugin accepts incoming SMS messages from Twilio via a webhook. Incoming messages can be optionally processed by OpenAI to create or update WordPress content, per your settings.

What data is sent and when:
- Twilio -> Your WordPress site (on each inbound SMS): Twilio posts a webhook payload that typically includes the message body and metadata such as From, To, MessageSid, and (if present) media URLs.
- Your WordPress site -> OpenAI (optional; only if you provide a key to OpenAI). The plugin sends only the message text (and any context/templates you configure) to OpenAI to generate a result. Phone numbers are not sent unless you include them in prompts/templates.
- The generated result may be stored in WordPress (e.g., as a post or log entry) depending on your settings.

Policies:
- Twilio Terms of Service: https://www.twilio.com/en-us/legal/tos
- Twilio Privacy Notice: https://www.twilio.com/en-us/legal/privacy

### OpenAI (ChatGPT API) ###
What it is and what it’s used for: This plugin can generate WordPress posts using OpenAI’s ChatGPT API. No requests are made unless an administrator adds an OpenAI API key in the plugin settings.

What data is sent and when:
- When an admin (or an automated workflow you configure) triggers content generation, the plugin sends the prompt text you provide (and any additional context/templates you configure) to OpenAI’s API.
- By default, only the message body/content needed to fulfill the request is sent. The plugin does not send phone numbers or other personal data unless you explicitly include such data in your prompts or templates.
- The API response (generated text) is saved in WordPress (e.g., as a draft or published post) according to your settings.

Policies:
- Terms of Use: https://openai.com/policies/row-terms-of-use
- Privacy Policy: https://openai.com/policies/row-privacy-policy
- Privacy Center (overview): https://privacy.openai.com/

## Data handling & controls ##
- Admin-only setup: No external calls occur unless valid credentials are provided (OpenAI API key and/or Twilio webhook credentials).
- Opt-out controls: You can disable external integrations at any time by removing the API key and/or disconnecting the Twilio webhook in the plugin settings.
- Data minimization: Only the text needed to fulfill the request is sent externally by default. Do not include personal data in prompts/templates unless necessary for your workflow.
- Storage: Prompts you enter and OpenAI responses you choose to save are stored in your WordPress database (drafts/posts/logs) per your settings. If you enable logging of webhooks, Twilio payloads may be stored as logs.
- Removal: Disabling a feature stops new transmissions. You may delete generated content or logs from within WordPress according to your site’s policies.


## License

This plugin is licensed under the GPL-2.0. For more information, see the [license file](https://www.gnu.org/licenses/gpl-2.0.html). 