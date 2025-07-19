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

## License

This plugin is licensed under the GPL-2.0. For more information, see the [license file](https://www.gnu.org/licenses/gpl-2.0.html). 